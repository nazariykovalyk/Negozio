<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';

if (!isset($_SESSION['ordine_completato'])) {
    header('Location: index.php');
    exit;
}

$id_ordini = $_SESSION['ordine_completato'];
unset($_SESSION['ordine_completato']);
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
            <div class="icon-success">✅</div>
            <h1>Ordine Completato con Successo!</h1>
            <p>Grazie per il tuo acquisto. I tuoi ordini sono stati processati.</p>
            <p><strong>Numeri ordine:</strong>
                <?php foreach ($id_ordini as $id_ordine): ?>
                    #<?php echo $id_ordine; ?>
                <?php endforeach; ?>
            </p>

            <div style="margin-top: 30px;">
                <a href="index.php" class="btn btn-success">Torna allo Shopping</a>
                <a href="carrello.php" class="btn">Vedi Carrello</a>
            </div>
        </div>
    </div>
    </body>
    <?php
    require_once 'footer.php';
    ?>
    </html>ordine_confermato(per chi clicca ordina senza aggiungere nel carello):<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantita'])) {
    $ordini = [];

    foreach ($_POST['quantita'] as $id_articolo => $quantita) {
        $quantita = intval($quantita);
        if ($quantita > 0) {
            $fornitori = trovaFornitori($id_articolo, $quantita);
            if (!empty($fornitori)) {
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
} else {
    header('Location: index.php');
    exit;
}
?>