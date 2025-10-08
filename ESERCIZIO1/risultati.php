<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';

$utente_corrente = getUtenteCorrente();

// CONTROLLO METODI DI PAGAMENTO - Solo per utenti loggati
if ($utente_corrente) {
    $metodi_pagamento = getMetodiPagamento($utente_corrente['id']);
    $ha_metodi_pagamento = !empty($metodi_pagamento);
} else {
    $ha_metodi_pagamento = false;
}

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
    <style>
        .login-prompt, .payment-prompt {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }

        .payment-prompt {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .login-prompt a, .payment-prompt a {
            color: #856404;
            font-weight: bold;
            text-decoration: none;
        }

        .login-prompt a:hover, .payment-prompt a:hover {
            text-decoration: underline;
        }

        .btn-disabled {
            background: #6c757d !important;
            cursor: not-allowed;
            opacity: 0.6;
        }
    </style>
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

    <?php if (!$utente_corrente): ?>
        <div class="login-prompt">
            <strong>🔐 Accesso Richiesto per Ordinare</strong>
            <p>Puoi aggiungere articoli al carrello e confrontare i prezzi, ma per completare gli ordini devi
                <a href="registrazione.php">registrarti</a> o <a href="login.php">accedere</a> al tuo account.</p>
        </div>
    <?php elseif ($utente_corrente && !$ha_metodi_pagamento): ?>
        <div class="payment-prompt">
            <strong>💳 Metodo di Pagamento Richiesto</strong>
            <p>Per completare gli ordini, devi aggiungere un metodo di pagamento nel tuo
                <a href="profilo.php#metodi-pagamento">profilo</a>.</p>
            <p><small>Puoi comunque aggiungere articoli al carrello e completare l'acquisto dopo aver aggiunto la carta.</small></p>
        </div>
    <?php endif; ?>

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
                                <!-- AGGIUNGI AL CARRELLO (disponibile per tutti) -->
                                <form action="aggiungi_carrello.php" method="POST" class="form-ordine" style="margin-bottom: 5px;">
                                    <input type="hidden" name="id_articolo" value="<?php echo $ordine['id_articolo']; ?>">
                                    <input type="hidden" name="quantita" value="<?php echo $ordine['quantita']; ?>">
                                    <input type="hidden" name="id_fornitore" value="<?php echo $fornitore['id_fornitore']; ?>">
                                    <button type="submit" class="btn-carrello">🛒 Aggiungi al Carrello</button>
                                </form>

                                <?php if ($utente_corrente && $ha_metodi_pagamento): ?>
                                    <!-- ORDINE IMMEDIATO (solo per utenti loggati CON metodi di pagamento) -->
                                    <form action="conferma_ordine.php" method="POST" class="form-ordine">
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
                                <?php else: ?>
                                    <!-- Messaggio per utenti non loggati o senza metodi di pagamento -->
                                    <div style="text-align: center; margin-top: 5px;">
                                        <small style="color: #dc3545;">
                                            <?php if (!$utente_corrente): ?>
                                                ⚠️ <a href="registrazione.php" style="color: #dc3545;">Registrati</a> per ordinare
                                            <?php else: ?>
                                                ⚠️ <a href="profilo.php#metodi-pagamento" style="color: #dc3545;">Aggiungi carta</a> per ordinare
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    // Previeni l'ordine se l'utente non è loggato o non ha metodi di pagamento
    document.addEventListener('DOMContentLoaded', function() {
        const ordinaButtons = document.querySelectorAll('.btn-ordine');
        ordinaButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                <?php if (!$utente_corrente): ?>
                e.preventDefault();
                alert('Devi essere registrato per effettuare ordini. Clicca su "Registrati" in alto a sinistra.');
                window.location.href = 'registrazione.php';
                <?php elseif ($utente_corrente && !$ha_metodi_pagamento): ?>
                e.preventDefault();
                alert('Devi aggiungere un metodo di pagamento per effettuare ordini. Vai al tuo profilo per aggiungere una carta.');
                window.location.href = 'profilo.php#metodi-pagamento';
                <?php endif; ?>
            });
        });
    });
</script>

</body>
<?php
require_once 'footer.php';
?>
</html>