<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'perplexity_api.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $messaggio = trim($input['messaggio'] ?? '');
    $sessione_id = $input['sessione_id'] ?? session_id();

    if (empty($messaggio)) {
        echo json_encode(['success' => false, 'response' => 'Messaggio vuoto']);
        exit;
    }

    $id_utente = null;
    if (isset($_SESSION['user_id'])) {
        $id_utente = $_SESSION['user_id'];
    }

    $contesto = "";
    if ($id_utente) {
        $contesto = "L'utente è registrato nel sistema.";
    }

    $perplexity = new PerplexityAPI();

    $result = $perplexity->sendMessage($messaggio, $contesto);

    if ($result['success']) {
        salvaConversazione($id_utente, $sessione_id, $messaggio, $result['response']);

        echo json_encode([
            'success' => true,
            'response' => $result['response'],
            'timestamp' => date('H:i')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'response' => $result['response']
        ]);
    }
} else {
    echo json_encode(['success' => false, 'response' => 'Metodo non supportato']);
}
?>