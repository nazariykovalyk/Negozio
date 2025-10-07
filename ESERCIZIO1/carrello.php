<?php
require_once 'config.php';
require_once 'functions.php';

// Gestisci azioni carrello
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['aggiorna_quantita'])) {
        $index = $_POST['index'];
        $nuova_quantita = intval($_POST['quantita']);
        aggiornaQuantitaCarrello($index, $nuova_quantita);
    }
    elseif (isset($_POST['rimuovi'])) {
        $index = $_POST['index'];
        rimuoviDalCarrello($index);
    }
    elseif (isset($_POST['svuota_carrello'])) {
        svuotaCarrello();
    }
    elseif (isset($_POST['procedi_acquisto'])) {
        try {
            $id_ordini = processaOrdineCarrello();
            $_SESSION['ordine_completato'] = $id_ordini; // ⭐ CORRETTO: usa 'ordine_completato'
            header('Location: ordine_completato.php');
            exit;
        } catch (Exception $e) {
            $errore = "Errore durante l'ordine: " . $e->getMessage();
        }
    }
}

$carrello = getCarrello();
$totale = calcolaTotaleCarrello();
$conta_articoli = contaArticoliCarrello();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Carrello della Spesa - Sistema Acquisti</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">

</head>
<body>
<!-- Icona Carrello -->
<div class="header-carrello">
    <a href="carrello.php" style="background: #232f3e; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; display: flex; align-items: center;">
        🛒 Carrello
        <?php if (contaArticoliCarrello() > 0): ?>
            <span class="badge-carrello"><?php echo contaArticoliCarrello(); ?></span>
        <?php endif; ?>
    </a>
</div>

<div class="container">
    <div class="header">
        <h1>🛒 Carrello della Spesa</h1>
        <p><?php echo $conta_articoli; ?> articoli nel carrello</p>
    </div>

    <?php if (isset($errore)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo htmlspecialchars($errore); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($carrello)): ?>
        <div class="carrello-vuoto">
            <h2>Il tuo carrello è vuoto</h2>
            <p>Non hai ancora aggiunto articoli al carrello.</p>
            <a href="index.php" class="btn btn-primary">Inizia a fare acquisti</a>
        </div>
    <?php else: ?>
        <!-- Lista articoli nel carrello -->
        <?php foreach ($carrello as $index => $item): ?>
            <div class="item-carrello">
                <div class="item-header">
                    <div>
                        <div class="item-nome"><?php echo htmlspecialchars($item['nome_articolo']); ?></div>
                        <div class="item-fornitore">Fornitore: <?php echo htmlspecialchars($item['nome_fornitore']); ?></div>
                    </div>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                        <button type="submit" name="rimuovi" class="btn btn-danger">Rimuovi</button>
                    </form>
                </div>

                <div class="item-dettagli">
                    <div>
                        <strong>Prezzo unitario:</strong><br>
                        €<?php echo number_format($item['prezzo_unitario'], 2); ?>
                    </div>
                    <div>
                        <strong>Quantità:</strong><br>
                        <form method="POST" class="quantita-control">
                            <input type="hidden" name="index" value="<?php echo $index; ?>">
                            <input type="number" name="quantita" value="<?php echo $item['quantita']; ?>" min="1" class="quantita-input">
                            <button type="submit" name="aggiorna_quantita" class="btn btn-secondary">Aggiorna</button>
                        </form>
                    </div>
                    <div>
                        <strong>Sconto:</strong><br>
                        <?php echo number_format($item['sconto_applicato'], 1); ?>%
                    </div>
                    <div>
                        <strong>Totale:</strong><br>
                        <span style="color: #B12704; font-weight: bold;">
                                €<?php echo number_format($item['prezzo_finale'], 2); ?>
                            </span>
                    </div>
                </div>
                <div style="margin-top: 10px; font-size: 14px; color: #565959;">
                    <strong>Spedizione:</strong> <?php echo $item['giorni_spedizione']; ?> giorni lavorativi
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Totale e azioni -->
        <div class="totale-section">
            <h2>Totale carrello: <span class="totale-importo">€<?php echo number_format($totale, 2); ?></span></h2>
            <p style="color: #565959;">Spedizione: Calcolata per ogni fornitore</p>

            <div class="azioni-carrello">
                <a href="index.php" class="btn btn-secondary">Continua gli acquisti</a>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="svuota_carrello" class="btn btn-danger">Svuota carrello</button>
                </form>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="procedi_acquisto" class="btn btn-success">
                        Procedi all'acquisto ›
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>