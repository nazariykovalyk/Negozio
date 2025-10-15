<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';

// Recupera l’utente loggato
$utente_corrente = getUtenteCorrente();

// Verifica se l’utente ha già salvato almeno un metodo di pagamento
$ha_metodi_pagamento = $utente_corrente && !empty(getMetodiPagamento($utente_corrente['id']));

// Se non ci sono risultati salvati nella sessione, reindirizza alla homepage
if (!isset($_SESSION['risultati_ricerca'])) {
    header('Location: index.php');
    exit;
}

// Recupera i risultati di ricerca dalla sessione
$ordini = $_SESSION['risultati_ricerca'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Risultati Ricerca Fornitori</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">

    <!-- Stile aggiuntivo locale (identico all’originale) -->
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

<!-- Pulsante Carrello fisso in alto -->
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

    <!-- Pulsanti di navigazione -->
    <div style="margin-bottom: 20px;">
        <a href="index.php" class="btn">← Nuova Ricerca</a>
        <a href="carrello.php" class="btn" style="background: #ffd814; color: #0F1111;">🛒 Vedi Carrello</a>
    </div>

    <!-- Messaggi informativi in base allo stato dell’utente -->
    <?php if (!$utente_corrente): ?>
        <!-- Caso: utente non loggato -->
        <div class="login-prompt">
            <strong>🔐 Accesso Richiesto per Ordinare</strong>
            <p>
                Puoi aggiungere articoli al carrello e confrontare i prezzi, ma per completare gli ordini devi
                <a href="registrazione.php">registrarti</a> o <a href="login.php">accedere</a> al tuo account.
            </p>
        </div>

    <?php elseif (!$ha_metodi_pagamento): ?>
        <!-- Caso: utente loggato ma senza metodi di pagamento -->
        <div class="payment-prompt">
            <strong>💳 Metodo di Pagamento Richiesto</strong>
            <p>
                Per completare gli ordini, devi aggiungere un metodo di pagamento nel tuo
                <a href="profilo.php#metodi-pagamento">profilo</a>.
            </p>
        </div>
    <?php endif; ?>

    <!-- Caso: nessun prodotto selezionato -->
    <?php if (empty($ordini)): ?>
        <p>Nessun prodotto selezionato o quantità insufficiente.</p>

    <?php else: ?>
        <!-- Elenco degli articoli trovati -->
        <?php foreach ($ordini as $ordine): ?>
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
                    <?php foreach ($ordine['fornitori'] as $index => $fornitore): ?>
                        <!-- Evidenzia il miglior fornitore (il primo) -->
                        <tr class="<?php echo $index === 0 ? 'migliore' : ''; ?>">
                            <td>
                                <?php echo htmlspecialchars($fornitore['nome_fornitore']); ?>
                                <?php if ($index === 0): ?>
                                    <br><span style="color: #28a745;">★ MIGLIORE</span>
                                <?php endif; ?>
                            </td>

                            <!--prezzo -->
                            <td>€<?php echo number_format($fornitore['prezzo_acquisto'], 2); ?></td>

                            <!-- se > 0 applica sconto se no - -->
                            <td class="sconto">
                                <?php echo $fornitore['sconto_applicato'] > 0
                                        ? number_format($fornitore['sconto_applicato'], 1) . '%'
                                        : '-'; ?>
                            </td>

                            <td class="prezzo">€<?php echo number_format($fornitore['prezzo_finale'], 2); ?></td>
                            <!-- numero giorni spedizione -->
                            <td><?php echo $fornitore['giorni_spedizione']; ?> giorni</td>
                            <!-- quantita dei articoli presente -->
                            <td><?php echo $fornitore['quantita_disponibile']; ?></td>

                            <td>
                                <!-- aggiungi al carrello -->
                                <form action="aggiungi_carrello.php" method="POST" class="form-ordine" style="margin-bottom: 5px;">
                                    <input type="hidden" name="id_articolo" value="<?php echo $ordine['id_articolo']; ?>">
                                    <input type="hidden" name="quantita" value="<?php echo $ordine['quantita']; ?>">
                                    <input type="hidden" name="id_fornitore" value="<?php echo $fornitore['id_fornitore']; ?>">
                                    <button type="submit" class="btn-carrello">🛒 Aggiungi al Carrello</button>
                                </form>

                                <!-- ordina diretto -->
                                <?php if ($utente_corrente && $ha_metodi_pagamento): ?>
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
                                    <!-- Avviso: non loggato o senza metodo pagamento -->
                                    <div style="text-align: center; margin-top: 5px;">
                                        <small style="color: #dc3545;">
                                            ⚠️
                                            <a href="<?php echo !$utente_corrente ? 'registrazione.php' : 'profilo.php#metodi-pagamento'; ?>" style="color: #dc3545;">
                                                <?php echo !$utente_corrente ? 'Registrati' : 'Aggiungi carta'; ?> per ordinare
                                            </a>
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

<!-- Script per controllo pulsanti ordine -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ordinaButtons = document.querySelectorAll('.btn-ordine');

        ordinaButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                <?php if (!$utente_corrente): ?>
                // Utente non registrato
                e.preventDefault();
                alert('Devi essere registrato per effettuare ordini. Clicca su "Registrati" in alto a sinistra.');
                window.location.href = 'registrazione.php';

                <?php elseif (!$ha_metodi_pagamento): ?>
                // Utente loggato ma senza metodi di pagamento
                e.preventDefault();
                alert('Devi aggiungere un metodo di pagamento per effettuare ordini. Vai al tuo profilo per aggiungere una carta.');
                window.location.href = 'profilo.php#metodi-pagamento';
                <?php endif; ?>
            });
        });
    });
</script>

<?php require_once 'footer.php'; ?>
</body>
</html>
