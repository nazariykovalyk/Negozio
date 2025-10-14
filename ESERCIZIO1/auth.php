<?php
require_once 'config.php';
require_once 'functions.php';

// Funzione per registrare un nuovo utente
function registraUtente($email, $password, $nome, $cognome, $ruolo = 'cliente', $telefono = null, $indirizzo = null, $citta = null) {
    $conn = getDBConnection();

    try {
        // Verifica se l'email esiste già
        $stmt = $conn->prepare("SELECT id_utente FROM Utenti WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email già registrata'];
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO Utenti (email, password_hash, nome, cognome, ruolo, telefono, indirizzo, citta) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt->execute([$email, $password_hash, $nome, $cognome, $ruolo, $telefono, $indirizzo, $citta])) {
            return ['success' => true, 'message' => 'Registrazione completata'];
        }
        return ['success' => false, 'message' => 'Errore durante la registrazione'];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Errore database: ' . $e->getMessage()];
    }
}

// Funzione per il login
function loginUtente($email, $password) {
    $conn = getDBConnection();

    try {
        $stmt = $conn->prepare("SELECT id_utente, email, password_hash, nome, cognome, ruolo, attivo FROM Utenti WHERE email = ? AND attivo = 1");
        $stmt->execute([$email]);
        $utente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($utente && password_verify($password, $utente['password_hash'])) {
            // Aggiorna ultimo accesso
            $stmt = $conn->prepare("UPDATE Utenti SET ultimo_accesso = NOW() WHERE id_utente = ?");
            $stmt->execute([$utente['id_utente']]);

            // Imposta session
            $_SESSION['user_id'] = $utente['id_utente'];
            $_SESSION['user_email'] = $utente['email'];
            $_SESSION['user_nome'] = $utente['nome'];
            $_SESSION['user_cognome'] = $utente['cognome'];
            $_SESSION['user_ruolo'] = $utente['ruolo'];

            // Carica il carrello salvato
            $_SESSION['carrello'] = caricaCarrelloDatabase($utente['id_utente']);

            return ['success' => true, 'message' => 'Login effettuato', 'user' => $utente];
        }
        return ['success' => false, 'message' => 'Email o password errati'];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Errore database: ' . $e->getMessage()];
    }
}

// Funzione per il logout
function logoutUtente() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['carrello'])) {
        salvaCarrelloDatabase($_SESSION['user_id'], $_SESSION['carrello']);
    }

    session_destroy();
    session_start();

    return ['success' => true, 'message' => 'Logout effettuato'];
}

// Verifica se l'utente è loggato
function isUtenteLoggato() {
    return isset($_SESSION['user_id']);
}

// Ottieni dati utente corrente
function getUtenteCorrente() {
    return isUtenteLoggato() ? [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'],
        'nome' => $_SESSION['user_nome'],
        'cognome' => $_SESSION['user_cognome'],
        'ruolo' => $_SESSION['user_ruolo']
    ] : null;
}

// Cambia password
function cambiaPasswordUtente($id_utente, $vecchia_password, $nuova_password) {
    $conn = getDBConnection();

    try {
        // Verifica vecchia password
        $stmt = $conn->prepare("SELECT password_hash FROM Utenti WHERE id_utente = ?");
        $stmt->execute([$id_utente]);
        $utente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$utente || !password_verify($vecchia_password, $utente['password_hash'])) {
            return ['success' => false, 'message' => 'Vecchia password errata'];
        }

        // Aggiorna password
        $nuova_password_hash = password_hash($nuova_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE Utenti SET password_hash = ? WHERE id_utente = ?");

        if ($stmt->execute([$nuova_password_hash, $id_utente])) {
            return ['success' => true, 'message' => 'Password cambiata con successo'];
        }
        return ['success' => false, 'message' => 'Errore durante il cambio password'];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Errore database: ' . $e->getMessage()];
    }
}

// Ottieni utente by ID
function getUtenteById($id_utente) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM Utenti WHERE id_utente = ?");
    $stmt->execute([$id_utente]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
