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

    return true;
}

// Rimuovi prodotto dal carrello
function rimuoviDalCarrello($index) {
    if (isset($_SESSION['carrello'][$index])) {
        array_splice($_SESSION['carrello'], $index, 1);
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
}

// Ottieni dettagli carrello
function getCarrello() {
    return $_SESSION['carrello'];
}

// Processa ordine dal carrello
function processaOrdineCarrello() {
    if (empty($_SESSION['carrello'])) {
        return false;
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

        // Svuota carrello dopo ordine completato
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
// Aggiungi questa funzione in functions.php
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
?>
