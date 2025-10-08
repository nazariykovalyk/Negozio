<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php'; // AGGIUNGI QUESTA RIGA

// SALVA il carrello nel database prima del logout
if (isset($_SESSION['user_id']) && isset($_SESSION['carrello'])) {
    salvaCarrelloDatabase($_SESSION['user_id'], $_SESSION['carrello']);
}

logoutUtente();
header('Location: login.php');
exit;
?>