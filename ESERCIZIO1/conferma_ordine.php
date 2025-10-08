<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isUtenteLoggato()) {
    $_SESSION['error'] = "Devi essere registrato per effettuare ordini";
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

try {
    // Prepara i dati per l'ordine
    $id_fornitore = $_POST['id_fornitore'];
    $dettaglio_ordine = [
            'id_articolo' => $_POST['id_articolo'],
            'quantita' => $_POST['quantita'],
            'prezzo_unitario' => $_POST['prezzo_unitario'],
            'sconto_applicato' => $_POST['sconto_applicato'],
            'prezzo_finale' => $_POST['prezzo_finale'],
            'nome_articolo' => $_POST['nome_articolo']
    ];

    $id_ordine = salvaOrdine($id_fornitore, [$dettaglio_ordine]);

    $dettagli_ordine = getDettagliOrdine($id_ordine);

    aggiornaSessionDopoOrdine($_POST['id_articolo'], $_POST['id_fornitore'], $_POST['quantita']);

} catch (Exception $e) {
    $errore = "Errore durante il salvataggio dell'ordine: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Conferma Ordine - Sistema Acquisti</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">

</head>
<body>
<div class="container">
    <h1>Conferma Ordine</h1>

    <?php if (isset($errore)): ?>
        <div class="error">
            <h3>❌ Errore</h3>
            <p><?php echo htmlspecialchars($errore); ?></p>
            <a href="risultati.php" class="btn">Torna ai risultati</a>
        </div>
    <?php else: ?>
        <div class="success">
            <h3>✅ Ordine Confermato!</h3>
            <p>Il tuo ordine è stato registrato con successo.</p>
        </div>

        <div class="ordine-info">
            <h2>Dettagli Ordine #<?php echo $id_ordine; ?></h2>

            <p><strong>Data ordine:</strong> <?php echo $dettagli_ordine[0]['data_ordine']; ?></p>
            <p><strong>Fornitore:</strong> <?php echo htmlspecialchars($dettagli_ordine[0]['nome_fornitore']); ?></p>
            <p><strong>Tempo di spedizione stimato:</strong> <?php echo $dettagli_ordine[0]['giorni_spedizione']; ?> giorni lavorativi</p>

            <table>
                <thead>
                <tr>
                    <th>Articolo</th>
                    <th>Quantità</th>
                    <th>Prezzo Unitario</th>
                    <th>Sconto</th>
                    <th>Totale</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($dettagli_ordine as $dettaglio): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($dettaglio['nome_articolo']); ?></td>
                        <td><?php echo $dettaglio['quantita']; ?></td>
                        <td>€<?php echo number_format($dettaglio['prezzo_unitario'], 2); ?></td>
                        <td><?php echo number_format($dettaglio['sconto_applicato'], 1); ?>%</td>
                        <td>€<?php echo number_format($dettaglio['prezzo_unitario'] * $dettaglio['quantita'] * (1 - $dettaglio['sconto_applicato']/100), 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="4" style="text-align: right;"><strong>Totale Ordine:</strong></td>
                    <td class="totale">€<?php echo number_format($dettagli_ordine[0]['totale'], 2); ?></td>
                </tr>
                </tfoot>
            </table>
        </div>

        <div>
            <a href="index.php" class="btn btn-success">Nuovo Ordine</a>
            <a href="risultati.php" class="btn">Torna ai Risultati</a>
        </div>
    <?php endif; ?>
</div>
</body>
<?php
require_once 'footer.php';
?>
</html>