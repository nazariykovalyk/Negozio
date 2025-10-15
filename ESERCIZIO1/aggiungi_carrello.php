<?php
require_once 'config.php';
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // RECUPERO DATI DAL FORM
    $id_articolo = $_POST['id_articolo'];
    $quantita = $_POST['quantita'];
    $id_fornitore = $_POST['id_fornitore'];

    // La funzione trovaFornitori() restituisce un array di fornitori che possono soddisfare l'ordine
    $fornitori = trovaFornitori($id_articolo, $quantita);
    $fornitore_scelto = null;

    // CERCA IL FORNITORE SPECIFICO tra quelli disponibili
    foreach ($fornitori as $fornitore) {
        if ($fornitore['id_fornitore'] == $id_fornitore) {
            $fornitore_scelto = $fornitore;  // Trovato il fornitore corrispondente
            break;
        }
    }
    // SE IL FORNITORE È STATO TROVATO, procedi con l'aggiunta al carrello
    if ($fornitore_scelto) {
        aggiungiAlCarrello($id_articolo, $quantita, $fornitore_scelto);

        $_SESSION['messaggio'] = "Prodotto aggiunto al carrello!";
    }
    //Se il fornitore non viene trovato, non viene aggiunto nulla al carrello
}

header('Location: carrello.php');
exit;
?>