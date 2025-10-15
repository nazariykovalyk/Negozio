<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';
// Verifica se la richiesta è POST e se è presente il campo 'quantita'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantita'])) {
    $ordini = [];

    // Itera su tutte le quantità ricevute dal form
    // $_POST['quantita'] è un array associativo: id_articolo => quantita
    foreach ($_POST['quantita'] as $id_articolo => $quantita) {
        if (($quantita = intval($quantita)) > 0) {
            //Cerca fornitori disponibili per questo articolo e quantità
            // La funzione trovaFornitori() restituisce un array di fornitori che possono soddisfare l'ordine
            if ($fornitori = trovaFornitori($id_articolo, $quantita)) {
                $ordini[] = [//Se trovati fornitori, aggiunge l'ordine all'array
                    'id_articolo' => $id_articolo,
                    'nome_articolo' => $fornitori[0]['nome_articolo'],
                    'quantita' => $quantita,
                    'fornitori' => $fornitori
                ];
            }
        }
    }
    //Salva tutti gli ordini nella sessione
    $_SESSION['risultati_ricerca'] = $ordini;
    header('Location: risultati.php');
    exit;
}

header('Location: index.php');
exit;
?>