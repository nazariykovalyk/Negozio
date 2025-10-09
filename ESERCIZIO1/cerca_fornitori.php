<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantita'])) {
    $ordini = [];

    foreach ($_POST['quantita'] as $id_articolo => $quantita) {
        if (($quantita = intval($quantita)) > 0) {
            if ($fornitori = trovaFornitori($id_articolo, $quantita)) {
                $ordini[] = [
                    'id_articolo' => $id_articolo,
                    'nome_articolo' => $fornitori[0]['nome_articolo'],
                    'quantita' => $quantita,
                    'fornitori' => $fornitori
                ];
            }
        }
    }

    $_SESSION['risultati_ricerca'] = $ordini;
    header('Location: risultati.php');
    exit;
}

header('Location: index.php');
exit;
?>