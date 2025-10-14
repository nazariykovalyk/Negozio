<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';
// GESTIONE RICHIESTE POST (azioni sul carrello)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['aggiorna_quantita'])) {
        aggiornaQuantitaCarrello($_POST['index'], intval($_POST['quantita']));
    }
    elseif (isset($_POST['rimuovi'])) {
        rimuoviDalCarrello($_POST['index']);
    }
    elseif (isset($_POST['svuota_carrello'])) {
        svuotaCarrello();
    }
    elseif (isset($_POST['procedi_acquisto'])) {
        if (!isUtenteLoggato()) {
            $_SESSION['error'] = "Devi essere registrato per completare l'acquisto";
            header('Location: login.php');
            exit;
        }
        $utente_corrente = getUtenteCorrente();
        if (!haMetodiPagamento($utente_corrente['id'])) {
            $_SESSION['error'] = "Devi aggiungere un metodo di pagamento prima di procedere all'acquisto";
            header('Location: profilo.php#metodi-pagamento');
            exit;
        }
        $carrello = getCarrello();
        $errori_disponibilita = verificaDisponibilitaCarrello($carrello);
        if (!empty($errori_disponibilita)) {
            $errore = "Alcuni articoli non sono più disponibili:<br>" . implode("<br>", $errori_disponibilita);
        }
        // Se tutto è OK, procedi con l'elaborazione dell'ordine
        else {
            try {
                $id_ordini = processaOrdineCarrello();

                // Salva gli ID ordine in sessione per la pagina di conferma
                $_SESSION['ordine_completato'] = $id_ordini;

                // Reindirizza alla pagina di conferma ordine
                header('Location: ordine_completato.php');
                exit;

            } catch (Exception $e) {
                // Gestione errori durante l'elaborazione dell'ordine
                $errore = "Errore durante l'ordine: " . $e->getMessage();
            }
        }
    }
}

// RECUPERO DATI PER LA VISUALIZZAZIONE DELLA PAGINA

$carrello = getCarrello();
$totale = calcolaTotaleCarrello();
$conta_articoli = contaArticoliCarrello();
$utente_corrente = getUtenteCorrente();

// Verifica se l'utente ha metodi di pagamento registrati
$ha_metodi_pagamento = $utente_corrente ? haMetodiPagamento($utente_corrente['id']) : false;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Carrello della Spesa - Sistema Acquisti</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <style>
        .login-required, .payment-required { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 20px; margin: 20px 0; text-align: center; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #dc3545; }
        .payment-required { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .login-required a, .payment-required a { color: #856404; font-weight: bold; text-decoration: none; }
        .login-required a:hover, .payment-required a:hover { text-decoration: underline; }
        .btn-disabled { background: #6c757d !important; cursor: not-allowed; opacity: 0.6; }
        .auth-buttons { display: flex; gap: 10px; justify-content: center; margin-top: 15px; }
        .payment-info { background: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; margin: 15px 0; border-radius: 5px; }
    </style>
</head>
<body>

<div class="header-carrello">
    <a href="carrello.php" style="background: #232f3e; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; display: flex; align-items: center;">
        🛒 Carrello
        <?php if ($conta_articoli > 0): ?>
            <span class="badge-carrello"><?php echo $conta_articoli; ?></span>
        <?php endif; ?>
    </a>
</div>

<div class="container1">
    <div class="header1">
        <h1>🛒 Carrello della Spesa</h1>
        <p><?php echo $conta_articoli; ?> articoli nel carrello</p>
    </div>

    <?php if (!empty($errore)): ?>
        <div class="alert-error">
            <h3>❌ Errore</h3>
            <p><?php echo $errore; ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($carrello)): ?>
        <div class="carrello-vuoto">
            <h2>Il tuo carrello è vuoto</h2>
            <p>Non hai ancora aggiunto articoli al carrello.</p>
            <a href="index.php" class="btn btn-primary">Inizia a fare acquisti</a>
        </div>
    <?php else: ?>

        <?php if (!$utente_corrente): ?>
            <div class="login-required">
                <h3>🔐 Accesso Richiesto</h3>
                <p>Per completare l'acquisto devi essere registrato. Il carrello viene salvato temporaneamente.</p>
                <div class="auth-buttons">
                    <a href="login.php" class="btn" style="background: #ff9900; color: white;">🔐 Accedi</a>
                    <a href="registrazione.php" class="btn" style="background: #28a745; color: white;">📝 Registrati</a>
                </div>
            </div>
        <?php elseif (!$ha_metodi_pagamento): ?>
            <div class="payment-required">
                <h3>💳 Metodo di Pagamento Richiesto</h3>
                <p>Per completare l'acquisto, devi aggiungere un metodo di pagamento.</p>
                <div class="auth-buttons">
                    <a href="profilo.php#metodi-pagamento" class="btn" style="background: #007bff; color: white;">➕ Aggiungi Carta</a>
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">Continua Acquisti</a>
                </div>
            </div>
        <?php else: ?>
            <div class="payment-info">
                <h3>💳 Metodo di Pagamento</h3>
                <p><strong>Metodo selezionato:</strong>
                    <?php
                    $metodo_preferito = getMetodoPreferito($utente_corrente['id']);
                    if ($metodo_preferito):
                        echo htmlspecialchars($metodo_preferito['tipo']) . ' - ';
                        echo match($metodo_preferito['tipo']) {
                            'carta' => formattaNumeroCarta($metodo_preferito['numero_carta']),
                            'paypal' => htmlspecialchars($metodo_preferito['email_paypal']),
                            'bonifico' => formattaIBAN($metodo_preferito['iban']),
                            default => ''
                        };
                        echo '<br><small style="color: #28a745;">✓ Preferito</small>';
                    else:
                        echo '<em>Nessun metodo preferito impostato</em>';
                    endif;
                    ?>
                </p>
                <a href="profilo.php#metodi-pagamento" class="btn btn-outline" style="padding: 5px 10px; font-size: 12px;color: #0F1111;">Cambia Metodo</a>
            </div>
        <?php endif; ?>

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
                    <div><strong>Prezzo unitario:</strong><br>€<?php echo number_format($item['prezzo_unitario'], 2); ?></div>
                    <div>
                        <strong>Quantità:</strong><br>
                        <form method="POST" class="quantita-control">
                            <input type="hidden" name="index" value="<?php echo $index; ?>">
                            <input type="number" name="quantita" value="<?php echo $item['quantita']; ?>" min="1" class="quantita-input">
                            <button type="submit" name="aggiorna_quantita" class="btn btn-secondary">Aggiorna</button>
                        </form>
                    </div>
                    <div><strong>Sconto:</strong><br><?php echo number_format($item['sconto_applicato'], 1); ?>%</div>
                    <div><strong>Totale:</strong><br><span style="color: #B12704; font-weight: bold;">€<?php echo number_format($item['prezzo_finale'], 2); ?></span></div>
                </div>
                <div style="margin-top: 10px; font-size: 14px; color: #565959;">
                    <strong>Spedizione:</strong> <?php echo $item['giorni_spedizione']; ?> giorni lavorativi
                </div>
            </div>
        <?php endforeach; ?>

        <div class="totale-section">
            <h2>Totale carrello: <span class="totale-importo">€<?php echo number_format($totale, 2); ?></span></h2>
            <p style="color: #565959;">Spedizione: Calcolata per ogni fornitore</p>

            <div class="azioni-carrello">
                <a href="risultati.php" class="btn btn-secondary">Torna ai risultati</a>
                <a href="index.php" class="btn btn-secondary">Continua gli acquisti</a>
                <form method="POST" style="display: inline;"><button type="submit" name="svuota_carrello" class="btn btn-danger">Svuota carrello</button></form>

                <?php if ($utente_corrente && $ha_metodi_pagamento): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="procedi_acquisto" class="btn btn-success">Procedi all'acquisto ›</button>
                    </form>
                <?php else: ?>
                    <button type="button" class="btn btn-success btn-disabled" disabled>Procedi all'acquisto ›</button>
                    <div style="text-align: center; margin-top: 10px;">
                        <small style="color: #dc3545;">
                            ⚠️ Devi <a href="<?php echo !$utente_corrente ? 'registrazione.php' : 'profilo.php#metodi-pagamento'; ?>" style="color: #dc3545;">
                                <?php echo !$utente_corrente ? 'registrarti' : 'aggiungere una carta'; ?>
                            </a> per completare l'acquisto
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const acquistoButtons = document.querySelectorAll('button[name="procedi_acquisto"]');
        acquistoButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                <?php if (!$utente_corrente): ?>
                e.preventDefault();
                alert('Devi essere registrato per completare l\'acquisto. Clicca su "Registrati" in alto a sinistra.');
                window.location.href = 'registrazione.php';
                <?php elseif (!$ha_metodi_pagamento): ?>
                e.preventDefault();
                alert('Devi aggiungere un metodo di pagamento per completare l\'acquisto. Vai al tuo profilo per aggiungere una carta.');
                window.location.href = 'profilo.php#metodi-pagamento';
                <?php endif; ?>
            });
        });

        document.querySelector('.btn-disabled')?.addEventListener('click', function(e) {
            e.preventDefault();
            <?php if (!$utente_corrente): ?>
            alert('Devi essere registrato per completare l\'acquisto. Clicca su "Registrati" in alto a sinistra.');
            window.location.href = 'registrazione.php';
            <?php else: ?>
            alert('Devi aggiungere un metodo di pagamento per completare l\'acquisto. Vai al tuo profilo per aggiungere una carta.');
            window.location.href = 'profilo.php#metodi-pagamento';
            <?php endif; ?>
        });
    });
</script>

</body>
<?php require_once 'footer.php'; ?>
</html>