<?php
// Configurazione database
define('DB_HOST', 'localhost');
define('DB_NAME', 'Negozio');
define('DB_USER', 'root');
define('DB_PASS', '');

// Connessione al database
function getDBConnection() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Errore di connessione: " . $e->getMessage());
    }
}

// Avvia sessione
session_start();

// Inizializza carrello se non esiste
if (!isset($_SESSION['carrello'])) {
    $_SESSION['carrello'] = [];
}
?>