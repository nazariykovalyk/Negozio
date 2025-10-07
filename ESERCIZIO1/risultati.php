<?php
require_once 'config.php';
require_once 'functions.php';

// NON cancellare la sessione, così puoi tornare ai risultati
if (!isset($_SESSION['risultati_ricerca'])) {
    header('Location: index.php');
    exit;
}

$ordini = $_SESSION['risultati_ricerca'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Risultati Ricerca Fornitori</title>
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
    <h1>Risultati Ricerca Fornitori</h1>

    <div style="margin-bottom: 20px;">
        <a href="index.php" class="btn">← Nuova Ricerca</a>
        <a href="carrello.php" class="btn" style="background: #ffd814; color: #0F1111;">🛒 Vedi Carrello</a>
    </div>

    <?php if (empty($ordini)): ?>
        <p>Nessun prodotto selezionato o quantità insufficiente.</p>
    <?php else: ?>
        <?php foreach($ordini as $ordine): ?>
            <div class="articolo">
                <h2><?php echo htmlspecialchars($ordine['nome_articolo']); ?></h2>
                <p><strong>Quantità richiesta:</strong> <?php echo $ordine['quantita']; ?></p>

                <h3>Fornitori disponibili:</h3>
                <table>
                    <thead>
                    <tr>
                        <th>Fornitore</th>
                        <th>Prezzo Unitario</th>
                        <th>Sconto</th>
                        <th>Prezzo Totale</th>
                        <th>Giorni Spedizione</th>
                        <th>Quantità Disponibile</th>
                        <th>Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($ordine['fornitori'] as $index => $fornitore): ?>
                        <tr class="<?php echo $index === 0 ? 'migliore' : ''; ?>">
                            <td>
                                <?php echo htmlspecialchars($fornitore['nome_fornitore']); ?>
                                <?php if ($index === 0): ?>
                                    <br><span style="color: #28a745;">★ MIGLIORE</span>
                                <?php endif; ?>
                            </td>
                            <td>€<?php echo number_format($fornitore['prezzo_acquisto'], 2); ?></td>
                            <td class="sconto">
                                <?php if ($fornitore['sconto_applicato'] > 0): ?>
                                    <?php echo number_format($fornitore['sconto_applicato'], 1); ?>%
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="prezzo">€<?php echo number_format($fornitore['prezzo_finale'], 2); ?></td>
                            <td><?php echo $fornitore['giorni_spedizione']; ?> giorni</td>
                            <td><?php echo $fornitore['quantita_disponibile']; ?></td>
                            <td>
                                <!-- ORDINE IMMEDIATO -->
                                <form action="conferma_ordine.php" method="POST" class="form-ordine" style="margin-bottom: 5px;">
                                    <input type="hidden" name="id_articolo" value="<?php echo $ordine['id_articolo']; ?>">
                                    <input type="hidden" name="id_fornitore" value="<?php echo $fornitore['id_fornitore']; ?>">
                                    <input type="hidden" name="quantita" value="<?php echo $ordine['quantita']; ?>">
                                    <input type="hidden" name="prezzo_finale" value="<?php echo $fornitore['prezzo_finale']; ?>">
                                    <input type="hidden" name="prezzo_unitario" value="<?php echo $fornitore['prezzo_acquisto']; ?>">
                                    <input type="hidden" name="sconto_applicato" value="<?php echo $fornitore['sconto_applicato']; ?>">
                                    <input type="hidden" name="nome_articolo" value="<?php echo htmlspecialchars($ordine['nome_articolo']); ?>">
                                    <input type="hidden" name="nome_fornitore" value="<?php echo htmlspecialchars($fornitore['nome_fornitore']); ?>">
                                    <button type="submit" class="btn-ordine">Ordina Ora</button>
                                </form>

                                <!-- AGGIUNGI AL CARRELLO -->
                                <form action="aggiungi_carrello.php" method="POST" class="form-ordine">
                                    <input type="hidden" name="id_articolo" value="<?php echo $ordine['id_articolo']; ?>">
                                    <input type="hidden" name="quantita" value="<?php echo $ordine['quantita']; ?>">
                                    <input type="hidden" name="id_fornitore" value="<?php echo $fornitore['id_fornitore']; ?>">
                                    <button type="submit" class="btn-carrello">🛒 Aggiungi al Carrello</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>