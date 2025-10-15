<?php
require_once 'config.php';

// Ottieni tutti gli articoli disponibili
function getArticoli() {
    $conn = getDBConnection();
    try {
        $stmt = $conn->query("
            SELECT 
                a.id_articolo, 
                a.nome, 
                a.SKU, 
                a.descrizione,
                a.prezzo_vendita,
                MIN(af.prezzo_acquisto) as prezzo_unitario
            FROM Articoli a
            LEFT JOIN Articoli_Fornitori af ON a.id_articolo = af.id_articolo
            GROUP BY a.id_articolo, a.nome, a.SKU, a.descrizione, a.prezzo_vendita
            ORDER BY a.nome ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Errore recupero articoli: " . $e->getMessage());
        return [];
    }
}

// Calcola sconto per un fornitore
function calcolaSconto($id_fornitore, $quantita, $prezzo_totale, $mese_corrente = null) {
    //x Se mese_corrente non è fornito, usa il mese attuale (1-12)
    $mese_corrente = $mese_corrente ?: date('n');
    $conn = getDBConnection();
    $sconto_massimo = 0;

    // Sconti per quantità
    $stmt = $conn->prepare("SELECT percentuale FROM Sconti WHERE id_fornitore = ? AND tipo = 'quantita' AND quantita_min <= ? ORDER BY percentuale DESC LIMIT 1");
    $stmt->execute([$id_fornitore, $quantita]);
    if ($sconto = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sconto_massimo = max($sconto_massimo, $sconto['percentuale']);
    }

    // Sconti per valore totale
    $stmt = $conn->prepare("SELECT percentuale FROM Sconti WHERE id_fornitore = ? AND tipo = 'valoreTOT' AND valore_min <= ? ORDER BY percentuale DESC LIMIT 1");
    $stmt->execute([$id_fornitore, $prezzo_totale]);
    if ($sconto = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sconto_massimo = max($sconto_massimo, $sconto['percentuale']);
    }

    // Sconti stagionali
    $stmt = $conn->prepare("SELECT percentuale FROM Sconti WHERE id_fornitore = ? AND tipo = 'stagionale' AND mese_inizio <= ? AND mese_fine >= ?");
    $stmt->execute([$id_fornitore, $mese_corrente, $mese_corrente]);
    if ($sconto = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sconto_massimo = max($sconto_massimo, $sconto['percentuale']);
    }

    return $sconto_massimo;
}

// Trova fornitori per un articolo e quantità
function trovaFornitori($id_articolo, $quantita_richiesta) {
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        SELECT af.id_articolo, af.id_fornitore, f.nome as nome_fornitore, af.prezzo_acquisto, 
               af.quantita_disponibile, f.giorni_spedizione, a.nome as nome_articolo,
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
        $sconto = calcolaSconto($fornitore['id_fornitore'], $quantita_richiesta, $fornitore['prezzo_totale']);
        $fornitore['sconto_applicato'] = $sconto;
        $fornitore['prezzo_finale'] = $fornitore['prezzo_totale'] * (1 - $sconto/100);
    }

    // Ordina per prezzo finale
    usort($fornitori, fn($a, $b) => $a['prezzo_finale'] <=> $b['prezzo_finale']);

    return $fornitori;
}

// Salva l'ordine nel database
function salvaOrdine($id_fornitore, $dettagli_ordine) {
    $conn = getDBConnection();

    try {
        $conn->beginTransaction();

        $totale_ordine = array_sum(array_column($dettagli_ordine, 'prezzo_finale'));
        //inserire i dettagli del ordine singolo
        $stmt = $conn->prepare("INSERT INTO OrdiniAcquisto (data_ordine, id_fornitore, totale) VALUES (CURDATE(), ?, ?)");
        $stmt->execute([$id_fornitore, $totale_ordine]);
        $id_ordine = $conn->lastInsertId();//recupera id dell'ordine creato
        // INSERISCE DETTAGLIO ORDINE
        foreach ($dettagli_ordine as $dettaglio) {
            $stmt = $conn->prepare("INSERT INTO DettagliOrdine (id_ordine, id_articolo, quantita, prezzo_unitario, sconto_applicato) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_ordine, $dettaglio['id_articolo'], $dettaglio['quantita'], $dettaglio['prezzo_unitario'], $dettaglio['sconto_applicato']]);

            //Aggiornamento quantita dopo acquisto, sottrae la quantita acquistata dal magazzino del fornitore
            $stmt = $conn->prepare("UPDATE Articoli_Fornitori SET quantita_disponibile = quantita_disponibile - ? WHERE id_articolo = ? AND id_fornitore = ?");
            $stmt->execute([$dettaglio['quantita'], $dettaglio['id_articolo'], $id_fornitore]);
        }

        $conn->commit();
        return $id_ordine;

    } catch (Exception $e) {
        $conn->rollBack();//rollback se l'operazione non è andata bene
        throw $e;
    }
}

function getDettagliOrdine($id_ordine) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT o.id_ordine, o.data_ordine, o.totale, f.nome as nome_fornitore, f.giorni_spedizione,
               d.id_articolo, d.quantita, d.prezzo_unitario, d.sconto_applicato, a.nome as nome_articolo
        FROM OrdiniAcquisto o
        JOIN Fornitori f ON o.id_fornitore = f.id_fornitore
        JOIN DettagliOrdine d ON o.id_ordine = d.id_ordine
        JOIN Articoli a ON d.id_articolo = a.id_articolo
        WHERE o.id_ordine = ?
    ");
    $stmt->execute([$id_ordine]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Aggiorna la sessione dopo un ordine
function aggiornaSessionDopoOrdine($id_articolo, $id_fornitore, $quantita_ordinata) {
    //Se non esistono risultati di ricerca in sessione, esce
    if (!isset($_SESSION['risultati_ricerca'])) return;

        //Itera su tutti gli ordini nei risultati di ricerca
        foreach ($_SESSION['risultati_ricerca'] as &$ordine) {
        if ($ordine['id_articolo'] == $id_articolo) {//Cerca l'articolo specifico che è stato ordinato

            //Itera su tutti i fornitori di questo articolo
            foreach ($ordine['fornitori'] as &$fornitore) {
                //Trova il fornitore specifico da cui è stato ordinato
                if ($fornitore['id_fornitore'] == $id_fornitore) {
                    //sottrae la quantità ordinata
                    $fornitore['quantita_disponibile'] -= $quantita_ordinata;

                    // Se la disponibilità è minore della quantità richiesta
                    if ($fornitore['quantita_disponibile'] < $ordine['quantita']) {
                        //TROVA LA POSIZIONE DEL FORNITORE NELL'ARRAY
                        $key = array_search($fornitore, $ordine['fornitori']);
                        //SE TROVATO, RIMUOVI IL FORNITORE
                        if ($key !== false) {
                            unset($ordine['fornitori'][$key]);
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

// Carrello functions
function aggiungiAlCarrello($id_articolo, $quantita, $fornitore_scelto = null) {
    //SE FORNITORE NON SPECIFICATO: cerca automaticamente il primo disponibile
    if ($fornitore_scelto === null) {
        $fornitori = trovaFornitori($id_articolo, $quantita);
        if (empty($fornitori)) return false;// Nessun fornitore disponibile
        $fornitore_scelto = $fornitori[0]; // Prende il primo fornitore
    }
    //CREA L'OGGETTO DA AGGIUNGERE AL CARRELLO
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
    // 4. CONTROLLA SE L'ARTICOLO È GIA NEL CARRELLO (STESSO FORNITORE)
    foreach ($_SESSION['carrello'] as &$item) {
        if ($item['id_articolo'] == $id_articolo && $item['id_fornitore'] == $fornitore_scelto['id_fornitore']) {
            $item['quantita'] += $quantita;//somma alla quantità esistente
            salvaCarrelloAutomatico();
            return true;//Articolo aggiornato
        }
    }
    //SE ARTICOLO NON TROVATO: aggiungi nuovo elemento al carrello
    $_SESSION['carrello'][] = $item_carrello;
    salvaCarrelloAutomatico();
    return true;
}

function rimuoviDalCarrello($index) {
    if (isset($_SESSION['carrello'][$index])) {
        //rimuove un elemento specifico dal carrello
        array_splice($_SESSION['carrello'], $index, 1);
        salvaCarrelloAutomatico();
        return true;
    }
    return false;
}

function aggiornaQuantitaCarrello($index, $nuova_quantita) {
    if (isset($_SESSION['carrello'][$index]) && $nuova_quantita > 0) {
        $item = $_SESSION['carrello'][$index];//Recupera l'item dal carrello per riferimento
        $_SESSION['carrello'][$index]['quantita'] = $nuova_quantita;//aggiorna la quantità nell'item del carrello

        //calcolo prezzo totale poi sconto
        $prezzo_totale = $item['prezzo_unitario'] * $nuova_quantita;
        $sconto = calcolaSconto($item['id_fornitore'], $nuova_quantita, $prezzo_totale);

        //aggiorna campi scnto e prezzo tot
        $_SESSION['carrello'][$index]['sconto_applicato'] = $sconto;
        $_SESSION['carrello'][$index]['prezzo_finale'] = $prezzo_totale * (1 - $sconto/100);

        salvaCarrelloAutomatico();
        return true;
    }
    return false;
}

function calcolaTotaleCarrello() {
    return array_sum(array_column($_SESSION['carrello'], 'prezzo_finale'));
}

function contaArticoliCarrello() {
    return array_sum(array_column($_SESSION['carrello'], 'quantita'));
}

function svuotaCarrello() {
    $_SESSION['carrello'] = [];//elimina tutti i prodotti
    salvaCarrelloAutomatico();
}

function getCarrello() {
    return $_SESSION['carrello'];
}

// Processa ordine dal carrello
function processaOrdineCarrello() {
    if (!isset($_SESSION['user_id'])) throw new Exception("Utente non autenticato");
    if (empty($_SESSION['carrello'])) throw new Exception("Carrello vuoto");
    //VERIFICA DISPONIBILITÀ DI TUTTI GLI ARTICOLI
    foreach ($_SESSION['carrello'] as $item) {
        if (!verificaDisponibilita($item['id_articolo'], $item['id_fornitore'], $item['quantita'])) {
            throw new Exception("Quantità non disponibile per: " . $item['nome_articolo']);
        }
    }

    $conn = getDBConnection();
    try {
        $conn->beginTransaction();

        // Raggruppa per fornitore
        $ordini_per_fornitore = [];
        foreach ($_SESSION['carrello'] as $item) {
            $ordini_per_fornitore[$item['id_fornitore']][] = $item;
        }
        // CREA ORDINI SEPARATI PER OGNI FORNITORE
        $id_ordini = [];
        foreach ($ordini_per_fornitore as $id_fornitore => $items) {
            //CALCOLA TOTALE ORDINE PER QUESTO FORNITORE
            $totale_ordine = array_sum(array_column($items, 'prezzo_finale'));
            //CREA ORDINE PRINCIPALE
            $stmt = $conn->prepare("INSERT INTO OrdiniAcquisto (data_ordine, id_fornitore, totale) VALUES (CURDATE(), ?, ?)");
            $stmt->execute([$id_fornitore, $totale_ordine]);
            $id_ordine = $conn->lastInsertId();
            $id_ordini[] = $id_ordine;

            //AGGIUNGI DETTAGLI ORDINE E AGGIORNA MAGAZZINO
            foreach ($items as $item) {
                // Inserisce dettaglio ordine
                $stmt = $conn->prepare("INSERT INTO DettagliOrdine (id_ordine, id_articolo, quantita, prezzo_unitario, sconto_applicato) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id_ordine, $item['id_articolo'], $item['quantita'], $item['prezzo_unitario'], $item['sconto_applicato']]);
                // Aggiorna disponibilità magazzino
                $stmt = $conn->prepare("UPDATE Articoli_Fornitori SET quantita_disponibile = quantita_disponibile - ? WHERE id_articolo = ? AND id_fornitore = ?");
                $stmt->execute([$item['quantita'], $item['id_articolo'], $id_fornitore]);
            }
        }

        $conn->commit();
        svuotaCarrello();
        return $id_ordini;

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

// Utility functions
function filtraArticoli($articoli, $termine_ricerca) {
    $termine = strtolower($termine_ricerca);
    return array_filter($articoli, function($articolo) use ($termine) {
        return strpos(strtolower($articolo['nome']), $termine) !== false ||
            strpos(strtolower($articolo['SKU']), $termine) !== false ||
            strpos(strtolower($articolo['descrizione']), $termine) !== false;
    });
}

function getImmagineProdotto($nome_prodotto) {
    $immagini = [
        'Philips Monitor' => 'monitor.jpg', 'Logitech Mouse' => 'mouse.jpg', 'Tastiera Logitech' => 'tast.jpg',
        'HP LaserJet' => 'laser.jpg', 'Cavo HDMI' => 'cavo.jpg', 'SSD Samsung' => 'ssd.jpg',
        'Webcam Logitech' => 'web.jpg', 'Scheda Madre ASUS' => 'asus.jpg', 'Alimentatore Corsair' => 'cs650m.jpg',
        'RAM Kingston' => 'ram.jpg', 'CPU AMD Ryzen' => 'amd.jpg', 'CPU Intel' => 'cpu.jpg',
        'Scheda Video NVIDIA' => 'video.jpg', 'Case PC NZXT' => 'case.jpg', 'Dissipatore Cooler Master' => 'dis.jpg',
        'Mouse da Gaming Razer' => 'Razer.jpg', 'Tastiera Meccanica Redragon' => 'red.jpg', 'Monitor LG' => 'lg.jpg',
        'Hard Disk WD' => 'image.jpg', 'Stampante Epson' => 'Epson.jpg', 'Router TP-Link' => 'Router.jpg',
        'Cuffie Sony' => 'Sony.jpg', 'Powerbank Anker' => 'power.jpg', 'Chiavetta USB 64GB Sandisk' => 'usb.jpg',
        'Tablet Samsung' => 'tab.jpg', 'Smartwatch Amazfit' => 'amaz.jpg', 'Caricatore Wireless Belkin' => 'Belkin.jpg'
    ];

    foreach ($immagini as $key => $file) {
        if (stripos($nome_prodotto, $key) !== false && file_exists("images/$file")) {
            return "images/$file";
        }
    }
    return 'images/default.jpg';
}

function truncateDescription($testo, $lunghezza) {
    return strlen($testo) <= $lunghezza ? $testo : substr($testo, 0, $lunghezza) . '...';
}

// Carrello database functions
function salvaCarrelloDatabase($id_utente, $carrello) {
    $conn = getDBConnection();
    try {
        $carrello_json = json_encode($carrello);//codicifica in json
        $stmt = $conn->prepare("INSERT INTO CarrelliSalvati (id_utente, carrello_data, data_salvataggio) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE carrello_data = ?, data_salvataggio = NOW()");
        $stmt->execute([$id_utente, $carrello_json, $carrello_json]);
        return true;
    } catch (Exception $e) {
        error_log("Errore salvataggio carrello: " . $e->getMessage());
        return false;
    }
}

function caricaCarrelloDatabase($id_utente) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("SELECT carrello_data FROM CarrelliSalvati WHERE id_utente = ?");
        $stmt->execute([$id_utente]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);//array associativo con carello_data
        // Verifica  se $result esiste E se carrello_data non è vuoto
        // Se vero: decodifica il JSON in array associativo
        // Se falso: restituisce array vuoto
        return $result && !empty($result['carrello_data']) ? json_decode($result['carrello_data'], true) : [];
    } catch (Exception $e) {
        error_log("Errore caricamento carrello: " . $e->getMessage());
        return [];
    }
}

function salvaCarrelloAutomatico() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['carrello'])) {//se esiste utente e se ha un carrello
        salvaCarrelloDatabase($_SESSION['user_id'], $_SESSION['carrello']);
    }
}

// Metodi di pagamento
function getMetodiPagamento($id_utente) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("SELECT * FROM MetodiPagamento WHERE id_utente = ? ORDER BY preferito DESC, data_creazione DESC");
        $stmt->execute([$id_utente]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Errore caricamento metodi pagamento: " . $e->getMessage());
        return [];
    }
}

function getMetodoPreferito($id_utente) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("SELECT * FROM MetodiPagamento WHERE id_utente = ? AND preferito = TRUE LIMIT 1");
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

            // CONVERSIONE FORMATO DATA: da YYYY-MM a YYYY-MM-DD
            if (!empty($dati_metodo['scadenza']) && strlen($dati_metodo['scadenza']) === 7) {//"2025-09"
                $dati_metodo['scadenza'] = $dati_metodo['scadenza'] . '-01';
            }

            // Verifica se la carta è già registrata
            $stmt = $conn->prepare("SELECT id_metodo FROM MetodiPagamento WHERE numero_carta = ? AND id_utente = ?");
            $stmt->execute([$dati_metodo['numero_carta'], $id_utente]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Questa carta è già registrata'];
            }

            // Verifica scadenza
            if (!empty($dati_metodo['scadenza'])) {
                $scadenza = DateTime::createFromFormat('Y-m-d', $dati_metodo['scadenza']);
                $oggi = new DateTime();
                // Imposta la scadenza all'ultimo giorno del mese per una verifica corretta
                $scadenza->modify('last day of this month');
                if ($scadenza < $oggi) {
                    return ['success' => false, 'message' => 'La carta è scaduta'];
                }
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

function impostaMetodoPreferito($id_utente, $id_metodo) {
    $conn = getDBConnection();
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("UPDATE MetodiPagamento SET preferito = FALSE WHERE id_utente = ?");
        $stmt->execute([$id_utente]);
        $stmt = $conn->prepare("UPDATE MetodiPagamento SET preferito = TRUE WHERE id_metodo = ? AND id_utente = ?");
        $success = $stmt->execute([$id_metodo, $id_utente]);
        $conn->commit();//applica modifiche
        return $success;
    } catch (Exception $e) {
        $conn->rollBack();//se va storto non applica modifiche
        error_log("Errore impostazione metodo preferito: " . $e->getMessage());
        return false;
    }
}

function rimuoviMetodoPagamento($id_utente, $id_metodo) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("DELETE FROM MetodiPagamento WHERE id_metodo = ? AND id_utente = ?");
        $success = $stmt->execute([$id_metodo, $id_utente]);

        if ($success) {
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

function haMetodiPagamento($id_utente) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM MetodiPagamento WHERE id_utente = ?");
        $stmt->execute([$id_utente]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    } catch (Exception $e) {
        error_log("Errore verifica metodi pagamento: " . $e->getMessage());
        return false;
    }
}

// Utility pagamento
function formattaNumeroCarta($numero_carta) {
    return empty($numero_carta) ? '' : '****' . substr(preg_replace('/\s+/', '', $numero_carta), -4);
}

function formattaIBAN($iban) {
    return empty($iban) ? '' : substr(preg_replace('/\s+/', '', $iban), 0, 4) . '**' . substr(preg_replace('/\s+/', '', $iban), -4);
}

function salvaMetodoPagamentoOrdine($id_ordine, $id_metodo_pagamento) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("UPDATE OrdiniAcquisto SET id_metodo_pagamento = ? WHERE id_ordine = ?");
        return $stmt->execute([$id_metodo_pagamento, $id_ordine]);
    } catch (Exception $e) {
        error_log("Errore salvataggio metodo pagamento ordine: " . $e->getMessage());
        return false;
    }
}

// Verifica disponibilità
function verificaDisponibilita($id_articolo, $id_fornitore, $quantita_richiesta) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT quantita_disponibile FROM Articoli_Fornitori WHERE id_articolo = ? AND id_fornitore = ?");
    $stmt->execute([$id_articolo, $id_fornitore]);
    $disponibilita = $stmt->fetch(PDO::FETCH_ASSOC);
    // - Se il record esiste ($disponibilita non è false)
    // - E la quantità disponibile è >= a quella richiesta
    return $disponibilita && $disponibilita['quantita_disponibile'] >= $quantita_richiesta;
}

function verificaDisponibilitaCarrello($carrello) {
    $errori = [];
    foreach ($carrello as $item) {
        if (!verificaDisponibilita($item['id_articolo'], $item['id_fornitore'], $item['quantita'])) {
            $errori[] = $item['nome_articolo'];
        }
    }
    return $errori;
}
?>