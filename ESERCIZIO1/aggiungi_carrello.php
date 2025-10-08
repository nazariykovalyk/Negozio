<?php
require_once 'config.php';
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_articolo = $_POST['id_articolo'];
    $quantita = $_POST['quantita'];
    $id_fornitore = $_POST['id_fornitore'];

    $fornitori = trovaFornitori($id_articolo, $quantita);
    $fornitore_scelto = null;

    foreach ($fornitori as $fornitore) {
        if ($fornitore['id_fornitore'] == $id_fornitore) {
            $fornitore_scelto = $fornitore;
            break;
        }
    }

    if ($fornitore_scelto) {
        aggiungiAlCarrello($id_articolo, $quantita, $fornitore_scelto);
        $_SESSION['messaggio'] = "Prodotto aggiunto al carrello!";
    }
}

header('Location: carrello.php');
exit;
?>