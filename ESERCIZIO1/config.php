<?php
session_start();
define('PERPLEXITY_API_KEY', '');
define('PERPLEXITY_API_URL', 'https://api.perplexity.ai/chat/completions');
define('DB_HOST', 'localhost');
define('DB_NAME', 'Negozio');
define('DB_USER', 'root');
define('DB_PASS', '');


function getDBConnection() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Errore di connessione: " . $e->getMessage());
    }
}


if (!isset($_SESSION['carrello'])) {
    $_SESSION['carrello'] = [];
}
?>