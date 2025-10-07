<?php
require_once 'config.php';
require_once 'functions.php';

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
</html>