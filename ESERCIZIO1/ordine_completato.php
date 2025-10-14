<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';


if (!isset($_SESSION['ordine_completato'])) {
    // Se non c'è un ordine completato in sessione, reindirizza alla homepage
    // Previene accessi diretti alla pagina senza aver effettuato ordini
    header('Location: index.php');
    exit;
}


$id_ordini = $_SESSION['ordine_completato'];  // Recupera array con ID ordini generati
unset($_SESSION['ordine_completato']);        // Rimuove dalla sessione per sicurezza
// La pulizia previene la doppia visualizzazione dello stesso ordine
?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Ordine Completato - Sistema Acquisti</title>
        <link rel="stylesheet" type="text/css" href="css/style.css">
    </head>
    <body>
    <div class="container">
        <div class="success-box">
            <!-- ICONA VISUALE DI SUCCESSO -->
            <div class="icon-success">✅</div>

            <!-- MESSAGGIO DI CONFERMA PRINCIPALE -->
            <h1>Ordine Completato con Successo!</h1>
            <p>Grazie per il tuo acquisto. I tuoi ordini sono stati processati.</p>

            <!-- ELENCO NUMERI ORDINE GENERATI -->
            <p><strong>Numeri ordine:</strong>
                <?php foreach ($id_ordini as $id_ordine): ?>
                    #<?php echo $id_ordine; ?>  <!-- Mostra ogni ID ordine preceduto da # -->
                <?php endforeach; ?>
            </p>

            <!-- NAVIGAZIONE POST-ORDINE -->
            <div style="margin-top: 30px;">
                <!-- Bottone primario: torna allo shopping -->
                <a href="index.php" class="btn btn-success">Torna allo Shopping</a>

                <!-- Bottone secondario: vedi carrello (probabilmente vuoto ora) -->
                <a href="carrello.php" class="btn">Vedi Carrello</a>
            </div>
        </div>
    </div>
    </body>
    <?php
    // Inclusione footer della pagina
    require_once 'footer.php';
    ?>
    </html>

<?php


// Inclusione file necessari
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';


//Gestisce richieste POST con quantità articoli

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantita'])) {
    $ordini = [];  // Array per raccogliere gli ordini validi

    foreach ($_POST['quantita'] as $id_articolo => $quantita) {
        $quantita = intval($quantita);  // Converti in intero per sicurezza

        // Processa solo articoli con quantità positiva
        if ($quantita > 0) {
            // CERCA FORNITORI DISPONIBILI per l'articolo e quantità
            $fornitori = trovaFornitori($id_articolo, $quantita);

            // Se ci sono fornitori disponibili, aggiungi all'array ordini
            if (!empty($fornitori)) {
                $ordini[] = [
                        'id_articolo' => $id_articolo,
                        'nome_articolo' => $fornitori[0]['nome_articolo'],  // Prende nome dal primo fornitore
                        'quantita' => $quantita,
                        'fornitori' => $fornitori  // Array completo di tutti i fornitori disponibili
                ];
            }
            //Articoli senza fornitori disponibili vengono ignorati
        }
    }


    $_SESSION['risultati_ricerca'] = $ordini;  // Salva ordini validi in sessione
    header('Location: risultati.php');         // Reindirizza alla pagina risultati
    exit;

} else {
     //Se non è una POST con dati quantità, reindirizza alla homepage
    header('Location: index.php');
    exit;
}
?>