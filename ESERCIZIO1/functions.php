<?php
require_once 'config.php';

// Ottieni tutti gli articoli disponibili
function getArticoli() {
    $conn = getDBConnection();
    $stmt = $conn->query("SELECT * FROM Articoli ORDER BY nome");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calcola sconto per un fornitore
function calcolaSconto($id_fornitore, $quantita, $prezzo_totale, $mese_corrente = null) {
    if ($mese_corrente === null) {
        $mese_corrente = date('n');
    }

    $conn = getDBConnection();
    $sconto_massimo = 0;

    // Sconti per quantità
    $stmt = $conn->prepare("
        SELECT percentuale 
        FROM Sconti 
        WHERE id_fornitore = ? AND tipo = 'quantita' AND quantita_min <= ?
        ORDER BY percentuale DESC LIMIT 1
    ");
    $stmt->execute([$id_fornitore, $quantita]);
    $sconto_quantita = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sconto_quantita) {
        $sconto_massimo = max($sconto_massimo, $sconto_quantita['percentuale']);
    }

    // Sconti per valore totale
    $stmt = $conn->prepare("
        SELECT percentuale 
        FROM Sconti 
        WHERE id_fornitore = ? AND tipo = 'valoreTOT' AND valore_min <= ?
        ORDER BY percentuale DESC LIMIT 1
    ");
    $stmt->execute([$id_fornitore, $prezzo_totale]);
    $sconto_valore = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sconto_valore) {
        $sconto_massimo = max($sconto_massimo, $sconto_valore['percentuale']);
    }

    // Sconti stagionali
    $stmt = $conn->prepare("
        SELECT percentuale 
        FROM Sconti 
        WHERE id_fornitore = ? AND tipo = 'stagionale' 
        AND mese_inizio <= ? AND mese_fine >= ?
    ");
    $stmt->execute([$id_fornitore, $mese_corrente, $mese_corrente]);
    $sconto_stagionale = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sconto_stagionale) {
        $sconto_massimo = max($sconto_massimo, $sconto_stagionale['percentuale']);
    }

    return $sconto_massimo;
}

// Trova fornitori per un articolo e quantità
function trovaFornitori($id_articolo, $quantita_richiesta) {
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        SELECT 
            af.id_articolo,
            af.id_fornitore,
            f.nome as nome_fornitore,
            af.prezzo_acquisto,
            af.quantita_disponibile,
            f.giorni_spedizione,
            a.nome as nome_articolo,
            (af.prezzo_acquisto * ?) as prezzo_totale
        FROM Articoli_Fornitori af
        JOIN Fornitori f ON af.id_fornitore = f.id_fornitore
        JOIN Articoli a ON af.id_articolo = a.id_articolo
        WHERE af.id_articolo = ? AND af.quantita_disponibile >= ?
        ORDER BY af.prezzo_acquisto ASC
    ");

    $stmt->execute([$quantita_richiesta, $id_articolo, $quantita_richiesta]);
    $fornitori = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcola prezzi finali con sconti
    foreach ($fornitori as &$fornitore) {
        $sconto = calcolaSconto(
            $fornitore['id_fornitore'],
            $quantita_richiesta,
            $fornitore['prezzo_totale']
        );

        $fornitore['sconto_applicato'] = $sconto;
        $fornitore['prezzo_finale'] = $fornitore['prezzo_totale'] * (1 - $sconto/100);
    }

    // Ordina per prezzo finale (versione compatibile)
    usort($fornitori, function($a, $b) {
        if ($a['prezzo_finale'] == $b['prezzo_finale']) {
            return 0;
        }
        return ($a['prezzo_finale'] < $b['prezzo_finale']) ? -1 : 1;
    });

    return $fornitori;
}

// Salva l'ordine nel database
function salvaOrdine($id_fornitore, $dettagli_ordine) {
    $conn = getDBConnection();

    try {
        $conn->beginTransaction();

        // 1. Crea l'ordine principale
        $stmt = $conn->prepare("
            INSERT INTO OrdiniAcquisto (data_ordine, id_fornitore, totale) 
            VALUES (CURDATE(), ?, ?)
        ");

        $totale_ordine = 0;
        foreach ($dettagli_ordine as $dettaglio) {
            $totale_ordine += $dettaglio['prezzo_finale'];
        }

        $stmt->execute([$id_fornitore, $totale_ordine]);
        $id_ordine = $conn->lastInsertId();

        // 2. Salva i dettagli dell'ordine
        foreach ($dettagli_ordine as $dettaglio) {
            $stmt = $conn->prepare("
                INSERT INTO DettagliOrdine 
                (id_ordine, id_articolo, quantita, prezzo_unitario, sconto_applicato) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id_ordine,
                $dettaglio['id_articolo'],
                $dettaglio['quantita'],
                $dettaglio['prezzo_unitario'],
                $dettaglio['sconto_applicato']
            ]);

            // 3. Aggiorna la quantità disponibile
            $stmt = $conn->prepare("
                UPDATE Articoli_Fornitori 
                SET quantita_disponibile = quantita_disponibile - ? 
                WHERE id_articolo = ? AND id_fornitore = ?
            ");
            $stmt->execute([
                $dettaglio['quantita'],
                $dettaglio['id_articolo'],
                $id_fornitore
            ]);
        }

        $conn->commit();
        return $id_ordine;

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

// Ottieni i dettagli di un ordine
function getDettagliOrdine($id_ordine) {
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        SELECT 
            o.id_ordine,
            o.data_ordine,
            o.totale,
            f.nome as nome_fornitore,
            f.giorni_spedizione,
            d.id_articolo,
            d.quantita,
            d.prezzo_unitario,
            d.sconto_applicato,
            a.nome as nome_articolo
        FROM OrdiniAcquisto o
        JOIN Fornitori f ON o.id_fornitore = f.id_fornitore
        JOIN DettagliOrdine d ON o.id_ordine = d.id_ordine
        JOIN Articoli a ON d.id_articolo = a.id_articolo
        WHERE o.id_ordine = ?
    ");

    $stmt->execute([$id_ordine]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Aggiorna la sessione dopo un ordine per riflettere le nuove quantità
function aggiornaSessionDopoOrdine($id_articolo, $id_fornitore, $quantita_ordinata) {
    if (!isset($_SESSION['risultati_ricerca'])) {
        return;
    }

    // Cerca l'articolo nella sessione e aggiorna la quantità
    foreach ($_SESSION['risultati_ricerca'] as &$ordine) {
        if ($ordine['id_articolo'] == $id_articolo) {
            foreach ($ordine['fornitori'] as &$fornitore) {
                if ($fornitore['id_fornitore'] == $id_fornitore) {
                    // Aggiorna la quantità disponibile
                    $fornitore['quantita_disponibile'] -= $quantita_ordinata;

                    // Se la quantità diventa insufficiente, rimuovi il fornitore
                    if ($fornitore['quantita_disponibile'] < $ordine['quantita']) {
                        // Troviamo l'indice del fornitore da rimuovere
                        $key = array_search($fornitore, $ordine['fornitori']);
                        if ($key !== false) {
                            unset($ordine['fornitori'][$key]);
                            // Ri-indexa l'array
                            $ordine['fornitori'] = array_values($ordine['fornitori']);
                        }
                    }
                    break;
                }
            }
            break;
        }
    }
}

// Aggiungi prodotto al carrello
function aggiungiAlCarrello($id_articolo, $quantita, $fornitore_scelto = null) {
    // Se il fornitore non è specificato, trova il migliore
    if ($fornitore_scelto === null) {
        $fornitori = trovaFornitori($id_articolo, $quantita);
        if (empty($fornitori)) {
            return false;
        }
        $fornitore_scelto = $fornitori[0]; // Prendi il migliore
    }

    $item_carrello = [
        'id_articolo' => $id_articolo,
        'quantita' => $quantita,
        'id_fornitore' => $fornitore_scelto['id_fornitore'],
        'nome_fornitore' => $fornitore_scelto['nome_fornitore'],
        'prezzo_unitario' => $fornitore_scelto['prezzo_acquisto'],
        'sconto_applicato' => $fornitore_scelto['sconto_applicato'],
        'prezzo_finale' => $fornitore_scelto['prezzo_finale'],
        'giorni_spedizione' => $fornitore_scelto['giorni_spedizione'],
        'nome_articolo' => $fornitore_scelto['nome_articolo'],
        'aggiunto_il' => date('Y-m-d H:i:s')
    ];

    // Controlla se l'articolo è già nel carrello
    $trovato = false;
    foreach ($_SESSION['carrello'] as &$item) {
        if ($item['id_articolo'] == $id_articolo && $item['id_fornitore'] == $fornitore_scelto['id_fornitore']) {
            $item['quantita'] += $quantita;
            $trovato = true;
            break;
        }
    }

    if (!$trovato) {
        $_SESSION['carrello'][] = $item_carrello;
    }

    // Salva automaticamente dopo aver aggiunto
    salvaCarrelloAutomatico();

    return true;
}

// Rimuovi prodotto dal carrello
function rimuoviDalCarrello($index) {
    if (isset($_SESSION['carrello'][$index])) {
        array_splice($_SESSION['carrello'], $index, 1);
        // Salva automaticamente dopo la rimozione
        salvaCarrelloAutomatico();
        return true;
    }
    return false;
}

// Aggiorna quantità nel carrello
function aggiornaQuantitaCarrello($index, $nuova_quantita) {
    if (isset($_SESSION['carrello'][$index]) && $nuova_quantita > 0) {
        $_SESSION['carrello'][$index]['quantita'] = $nuova_quantita;

        // Ricalcola il prezzo finale in base alla nuova quantità
        $item = $_SESSION['carrello'][$index];
        $prezzo_totale = $item['prezzo_unitario'] * $nuova_quantita;
        $sconto = calcolaSconto($item['id_fornitore'], $nuova_quantita, $prezzo_totale);

        $_SESSION['carrello'][$index]['sconto_applicato'] = $sconto;
        $_SESSION['carrello'][$index]['prezzo_finale'] = $prezzo_totale * (1 - $sconto/100);

        // Salva automaticamente dopo l'aggiornamento
        salvaCarrelloAutomatico();
        return true;
    }
    return false;
}

// Calcola totale carrello
function calcolaTotaleCarrello() {
    $totale = 0;
    foreach ($_SESSION['carrello'] as $item) {
        $totale += $item['prezzo_finale'];
    }
    return $totale;
}

// Conta articoli nel carrello
function contaArticoliCarrello() {
    $count = 0;
    foreach ($_SESSION['carrello'] as $item) {
        $count += $item['quantita'];
    }
    return $count;
}

// Svuota carrello
function svuotaCarrello() {
    $_SESSION['carrello'] = [];
    // Salva automaticamente dopo aver svuotato
    salvaCarrelloAutomatico();
}

// Ottieni dettagli carrello
function getCarrello() {
    return $_SESSION['carrello'];
}

// Processa ordine dal carrello
function processaOrdineCarrello() {
    // Controllo di sicurezza aggiuntivo
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Utente non autenticato");
    }

    if (empty($_SESSION['carrello'])) {
        throw new Exception("Carrello vuoto");
    }

    $conn = getDBConnection();

    try {
        $conn->beginTransaction();

        // Raggruppa per fornitore
        $ordini_per_fornitore = [];
        foreach ($_SESSION['carrello'] as $item) {
            $id_fornitore = $item['id_fornitore'];
            if (!isset($ordini_per_fornitore[$id_fornitore])) {
                $ordini_per_fornitore[$id_fornitore] = [];
            }
            $ordini_per_fornitore[$id_fornitore][] = $item;
        }

        $id_ordini = [];

        // Crea un ordine per ogni fornitore
        foreach ($ordini_per_fornitore as $id_fornitore => $items) {
            $totale_ordine = 0;
            $dettagli_ordine = [];

            foreach ($items as $item) {
                $totale_ordine += $item['prezzo_finale'];
                $dettagli_ordine[] = [
                    'id_articolo' => $item['id_articolo'],
                    'quantita' => $item['quantita'],
                    'prezzo_unitario' => $item['prezzo_unitario'],
                    'sconto_applicato' => $item['sconto_applicato'],
                    'prezzo_finale' => $item['prezzo_finale']
                ];
            }

            // Crea ordine principale
            $stmt = $conn->prepare("
                INSERT INTO OrdiniAcquisto (data_ordine, id_fornitore, totale) 
                VALUES (CURDATE(), ?, ?)
            ");
            $stmt->execute([$id_fornitore, $totale_ordine]);
            $id_ordine = $conn->lastInsertId();
            $id_ordini[] = $id_ordine;

            // Salva dettagli ordine
            foreach ($dettagli_ordine as $dettaglio) {
                $stmt = $conn->prepare("
                    INSERT INTO DettagliOrdine 
                    (id_ordine, id_articolo, quantita, prezzo_unitario, sconto_applicato) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id_ordine,
                    $dettaglio['id_articolo'],
                    $dettaglio['quantita'],
                    $dettaglio['prezzo_unitario'],
                    $dettaglio['sconto_applicato']
                ]);

                // Aggiorna quantità disponibile
                $stmt = $conn->prepare("
                    UPDATE Articoli_Fornitori 
                    SET quantita_disponibile = quantita_disponibile - ? 
                    WHERE id_articolo = ? AND id_fornitore = ?
                ");
                $stmt->execute([
                    $dettaglio['quantita'],
                    $dettaglio['id_articolo'],
                    $id_fornitore
                ]);
            }
        }

        $conn->commit();

        // Svuota carrello dopo ordine completato E salva nel database
        svuotaCarrello();

        return $id_ordini;

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

// Filtra articoli per ricerca
function filtraArticoli($articoli, $termine_ricerca) {
    $termine = strtolower($termine_ricerca);
    $risultati = [];

    foreach ($articoli as $articolo) {
        // Cerca nel nome, SKU e descrizione
        if (strpos(strtolower($articolo['nome']), $termine) !== false ||
            strpos(strtolower($articolo['SKU']), $termine) !== false ||
            strpos(strtolower($articolo['descrizione']), $termine) !== false) {
            $risultati[] = $articolo;
        }
    }

    return $risultati;
}

// Ottieni URL immagine prodotto
function getImmagineProdotto($nome_prodotto) {
    $base_path = 'images/';

    $immagini = [
        // Prodotti originali
        'Philips Monitor' => 'monitor.jpg',
        'Logitech Mouse' => 'mouse.jpg',
        'Tastiera Logitech' => 'tast.jpg',
        'HP LaserJet' => 'laser.jpg',
        'Cavo HDMI' => 'cavo.jpg',
        'SSD Samsung' => 'ssd.jpg',
        'Webcam Logitech' => 'web.jpg',

        // Nuovi prodotti hardware PC
        'Scheda Madre ASUS' => 'asus.jpg',
        'Alimentatore Corsair' => 'cs650m.jpg',
        'RAM Kingston' => 'ram.jpg',
        'CPU AMD Ryzen' => 'amd.jpg',
        'CPU Intel' => 'cpu.jpg',
        'Scheda Video NVIDIA' => 'video.jpg',
        'Case PC NZXT' => 'case.jpg',
        'Dissipatore Cooler Master' => 'dis.jpg',

        // Periferiche gaming
        'Mouse da Gaming Razer' => 'Razer.jpg',
        'Tastiera Meccanica Redragon' => 'red.jpg',

        // Monitor e display
        'Monitor LG' => 'lg.jpg',

        // Storage
        'Hard Disk WD' => 'image.jpg',

        // Stampanti
        'Stampante Epson' => 'Epson.jpg',

        // Networking
        'Router TP-Link' => 'Router.jpg',

        // Audio
        'Cuffie Sony' => 'Sony.jpg',

        // Accessori mobile
        'Powerbank Anker' => 'power.jpg',
        'Chiavetta USB 64GB Sandisk' => 'usb.jpg',
        'Tablet Samsung' => 'tab.jpg',
        'Smartwatch Amazfit' => 'amaz.jpg',
        'Caricatore Wireless Belkin' => 'Belkin.jpg'
    ];

    foreach ($immagini as $key => $file) {
        if (stripos($nome_prodotto, $key) !== false) {
            $path_completo = $base_path . $file;
            if (file_exists($path_completo)) {
                return $path_completo;
            }
        }
    }

    // Immagine di default se non trovata
    return $base_path . 'default.jpg';
}

// Tronca descrizione
function truncateDescription($testo, $lunghezza) {
    if (strlen($testo) <= $lunghezza) {
        return $testo;
    }
    return substr($testo, 0, $lunghezza) . '...';
}

// Salva carrello nel database
function salvaCarrelloDatabase($id_utente, $carrello) {
    $conn = getDBConnection();
    try {
        $carrello_json = json_encode($carrello);
        $stmt = $conn->prepare("
            INSERT INTO CarrelliSalvati (id_utente, carrello_data, data_salvataggio) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE carrello_data = ?, data_salvataggio = NOW()
        ");
        $stmt->execute([$id_utente, $carrello_json, $carrello_json]);
        return true;
    } catch (Exception $e) {
        error_log("Errore salvataggio carrello: " . $e->getMessage());
        return false;
    }
}

// Carica carrello dal database
function caricaCarrelloDatabase($id_utente) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("SELECT carrello_data FROM CarrelliSalvati WHERE id_utente = ?");
        $stmt->execute([$id_utente]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && !empty($result['carrello_data'])) {
            $carrello = json_decode($result['carrello_data'], true);
            return is_array($carrello) ? $carrello : [];
        }
    } catch (Exception $e) {
        error_log("Errore caricamento carrello: " . $e->getMessage());
    }
    return [];
}

// Salva carrello automaticamente (da chiamare quando si modifica il carrello)
function salvaCarrelloAutomatico() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['carrello'])) {
        salvaCarrelloDatabase($_SESSION['user_id'], $_SESSION['carrello']);
    }
}

// ========================================
// FUNZIONI METODI DI PAGAMENTO
// ========================================

// Ottieni tutti i metodi di pagamento di un utente
function getMetodiPagamento($id_utente) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("
            SELECT * FROM MetodiPagamento 
            WHERE id_utente = ? 
            ORDER BY preferito DESC, data_creazione DESC
        ");
        $stmt->execute([$id_utente]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Errore caricamento metodi pagamento: " . $e->getMessage());
        return [];
    }
}

// Ottieni metodo di pagamento preferito
function getMetodoPreferito($id_utente) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("
            SELECT * FROM MetodiPagamento 
            WHERE id_utente = ? AND preferito = TRUE 
            LIMIT 1
        ");
        $stmt->execute([$id_utente]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Errore caricamento metodo preferito: " . $e->getMessage());
        return null;
    }
}

// Aggiungi nuovo metodo di pagamento
function aggiungiMetodoPagamento($id_utente, $dati_metodo) {
    $conn = getDBConnection();
    try {
        // Validazione base
        if (empty($dati_metodo['tipo']) || empty($dati_metodo['titolare'])) {
            return ['success' => false, 'message' => 'Tipo di pagamento e titolare sono obbligatori'];
        }

        // Validazioni specifiche per tipo
        if ($dati_metodo['tipo'] === 'carta') {
            if (empty($dati_metodo['numero_carta']) || empty($dati_metodo['scadenza']) || empty($dati_metodo['cvv'])) {
                return ['success' => false, 'message' => 'Tutti i campi della carta sono obbligatori'];
            }

            // Verifica se la carta è già registrata
            $stmt = $conn->prepare("SELECT id_metodo FROM MetodiPagamento WHERE numero_carta = ? AND id_utente = ?");
            $stmt->execute([$dati_metodo['numero_carta'], $id_utente]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Questa carta è già registrata'];
            }

            // Verifica scadenza
            $scadenza = DateTime::createFromFormat('Y-m', $dati_metodo['scadenza']);
            $oggi = new DateTime();
            if ($scadenza < $oggi) {
                return ['success' => false, 'message' => 'La carta è scaduta'];
            }
        }
        elseif ($dati_metodo['tipo'] === 'paypal') {
            if (empty($dati_metodo['email_paypal'])) {
                return ['success' => false, 'message' => 'Email PayPal è obbligatoria'];
            }

            // Verifica se l'email PayPal è già registrata
            $stmt = $conn->prepare("SELECT id_metodo FROM MetodiPagamento WHERE email_paypal = ? AND id_utente = ?");
            $stmt->execute([$dati_metodo['email_paypal'], $id_utente]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Questo indirizzo PayPal è già registrato'];
            }
        }
        elseif ($dati_metodo['tipo'] === 'bonifico') {
            if (empty($dati_metodo['iban'])) {
                return ['success' => false, 'message' => 'IBAN è obbligatorio'];
            }

            // Verifica se l'IBAN è già registrato
            $stmt = $conn->prepare("SELECT id_metodo FROM MetodiPagamento WHERE iban = ? AND id_utente = ?");
            $stmt->execute([$dati_metodo['iban'], $id_utente]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Questo IBAN è già registrato'];
            }
        }

        // Se è il primo metodo, imposta come preferito
        $metodi_esistenti = getMetodiPagamento($id_utente);
        $preferito = empty($metodi_esistenti) ? true : ($dati_metodo['preferito'] ?? false);

        // Se si imposta come preferito, rimuovi preferito dagli altri
        if ($preferito) {
            $stmt = $conn->prepare("UPDATE MetodiPagamento SET preferito = FALSE WHERE id_utente = ?");
            $stmt->execute([$id_utente]);
        }

        $stmt = $conn->prepare("
            INSERT INTO MetodiPagamento 
            (id_utente, tipo, titolare, numero_carta, scadenza, cvv, email_paypal, iban, preferito) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $success = $stmt->execute([
            $id_utente,
            $dati_metodo['tipo'],
            $dati_metodo['titolare'],
            $dati_metodo['numero_carta'] ?? null,
            $dati_metodo['scadenza'] ?? null,
            $dati_metodo['cvv'] ?? null,
            $dati_metodo['email_paypal'] ?? null,
            $dati_metodo['iban'] ?? null,
            $preferito
        ]);

        return $success ?
            ['success' => true, 'message' => 'Metodo di pagamento aggiunto correttamente'] :
            ['success' => false, 'message' => 'Errore durante l\'aggiunta del metodo di pagamento'];

    } catch (Exception $e) {
        error_log("Errore aggiunta metodo pagamento: " . $e->getMessage());
        return ['success' => false, 'message' => 'Errore database: ' . $e->getMessage()];
    }
}

// Imposta metodo come preferito
function impostaMetodoPreferito($id_utente, $id_metodo) {
    $conn = getDBConnection();
    try {
        $conn->beginTransaction();

        // Rimuovi preferito da tutti i metodi
        $stmt = $conn->prepare("UPDATE MetodiPagamento SET preferito = FALSE WHERE id_utente = ?");
        $stmt->execute([$id_utente]);

        // Imposta preferito al metodo specificato
        $stmt = $conn->prepare("UPDATE MetodiPagamento SET preferito = TRUE WHERE id_metodo = ? AND id_utente = ?");
        $success = $stmt->execute([$id_metodo, $id_utente]);

        $conn->commit();
        return $success;

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Errore impostazione metodo preferito: " . $e->getMessage());
        return false;
    }
}

// Rimuovi metodo di pagamento
function rimuoviMetodoPagamento($id_utente, $id_metodo) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("DELETE FROM MetodiPagamento WHERE id_metodo = ? AND id_utente = ?");
        $success = $stmt->execute([$id_metodo, $id_utente]);

        if ($success) {
            // Se era il preferito, imposta un altro metodo come preferito
            $metodi_rimanenti = getMetodiPagamento($id_utente);
            if (!empty($metodi_rimanenti)) {
                impostaMetodoPreferito($id_utente, $metodi_rimanenti[0]['id_metodo']);
            }
        }

        return $success;

    } catch (Exception $e) {
        error_log("Errore rimozione metodo pagamento: " . $e->getMessage());
        return false;
    }
}

// Verifica se l'utente ha almeno un metodo di pagamento
function haMetodiPagamento($id_utente) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM MetodiPagamento WHERE id_utente = ?");
        $stmt->execute([$id_utente]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Errore verifica metodi pagamento: " . $e->getMessage());
        return false;
    }
}

// Formatta numero carta per la visualizzazione (nasconde la maggior parte delle cifre)
function formattaNumeroCarta($numero_carta) {
    if (empty($numero_carta)) return '';
    $numero_pulito = preg_replace('/\s+/', '', $numero_carta);
    return '****' . substr($numero_pulito, -4);
}

// Formatta IBAN per la visualizzazione (nasconde la maggior parte delle cifre)
function formattaIBAN($iban) {
    if (empty($iban)) return '';
    $iban_pulito = preg_replace('/\s+/', '', $iban);
    return substr($iban_pulito, 0, 4) . '**' . substr($iban_pulito, -4);
}

// Salva il metodo di pagamento utilizzato per un ordine
function salvaMetodoPagamentoOrdine($id_ordine, $id_metodo_pagamento) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("
            UPDATE OrdiniAcquisto 
            SET id_metodo_pagamento = ? 
            WHERE id_ordine = ?
        ");
        return $stmt->execute([$id_metodo_pagamento, $id_ordine]);
    } catch (Exception $e) {
        error_log("Errore salvataggio metodo pagamento ordine: " . $e->getMessage());
        return false;
    }
}

// Valida numero carta (Luhn algorithm)
function validaNumeroCarta($numero_carta) {
    $numero_pulito = preg_replace('/\s+/', '', $numero_carta);

    // Verifica che contenga solo numeri e sia lungo 13-19 cifre
    if (!preg_match('/^[0-9]{13,19}$/', $numero_pulito)) {
        return false;
    }

    // Algoritmo di Luhn
    $somma = 0;
    $alterna = false;

    for ($i = strlen($numero_pulito) - 1; $i >= 0; $i--) {
        $n = intval($numero_pulito[$i]);
        if ($alterna) {
            $n *= 2;
            if ($n > 9) {
                $n = ($n % 10) + 1;
            }
        }
        $somma += $n;
        $alterna = !$alterna;
    }

    return ($somma % 10) === 0;
}

// Valida IBAN (formato base)
function validaIBAN($iban) {
    $iban_pulito = strtoupper(preg_replace('/\s+/', '', $iban));

    // Verifica formato base (2 lettere + 2 cifre + 1-30 caratteri alfanumerici)
    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban_pulito)) {
        return false;
    }

    return true;
}

// Ottieni il tipo di carta dal numero (Visa, Mastercard, etc.)
function getTipoCarta($numero_carta) {
    $numero_pulito = preg_replace('/\s+/', '', $numero_carta);

    if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $numero_pulito)) {
        return 'Visa';
    } elseif (preg_match('/^5[1-5][0-9]{14}$/', $numero_pulito)) {
        return 'Mastercard';
    } elseif (preg_match('/^3[47][0-9]{13}$/', $numero_pulito)) {
        return 'American Express';
    } elseif (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $numero_pulito)) {
        return 'Discover';
    } else {
        return 'Altro';
    }
}
?>