<?php

//Questo codice riceve i messaggi dalla
// chat frontend e li invia all'AI di Perplexity,
// poi restituisce le risposte formattate in JSON al browser.
// È il "ponte" tra l'utente e l'intelligenza artificiale.

require_once 'config.php';
require_once 'auth.php';
require_once 'perplexity_api.php';

// Imposta l'header per indicare che la risposta è in formato JSON
header('Content-Type: application/json');

// Verifica che la richiesta sia di tipo POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // LEGGI E DECODIFICA I DATI JSON INVIATI NELLA RICHIESTA
    $input = json_decode(file_get_contents('php://input'), true);

    // ESTRAI I DATI DALLA RICHIESTA
    $messaggio = trim($input['messaggio'] ?? '');          // Messaggio dell'utente, rimuovendo spazi superflui
    $sessione_id = $input['sessione_id'] ?? session_id();  // ID sessione conversazione (default: sessione corrente)

    //Verifica che il messaggio non sia vuoto
    if (empty($messaggio)) {
        echo json_encode(['success' => false, 'response' => 'Messaggio vuoto']);
        exit;
    }

    // GESTIONE AUTENTICAZIONE UTENTE
    $id_utente = null;
    if (isset($_SESSION['user_id'])) {
        $id_utente = $_SESSION['user_id'];  // Recupera ID utente se loggato
    }

    // PREPARAZIONE CONTESTO PER L'AI
    //Arricchisce il prompt per l'AI con informazioni utente
    $contesto = "";
    if ($id_utente) {
        $contesto = "L'utente è registrato nel sistema.";  // Aggiungi contesto per utenti registrati
    }

    // INIZIALIZZA IL CLIENT PER L'API DI PERPLEXITY
    $perplexity = new PerplexityAPI();

    // INVIA IL MESSAGGIO ALL'API DI PERPLEXITYII
    //Delega la logica complessa alla classe specializzata
    $result = $perplexity->sendMessage($messaggio);

    if ($result['success']) {
        // SALVA LA CONVERSAZIONE NEL DATABASE
        // Registra sia il messaggio utente che la risposta AI per storico
        //salvaConversazione($id_utente, $sessione_id, $messaggio, $result['response']);

        // RESTITUISCI RISPOSTA DI SUCCESSO AL CLIENT
        echo json_encode([
            'success' => true,
            'response' => $result['response'],  // Risposta generata dall'AI
            'timestamp' => date('H:i')          // Orario della risposta
        ]);

    } else {
        // se ce un Problema con l'API di Perplexity
        echo json_encode([
            'success' => false,
            'response' => $result['response']   // Messaggio di errore dall'API
        ]);
    }

} else {
    //non è Richiesta post
    echo json_encode(['success' => false, 'response' => 'Metodo non supportato']);
}
?>