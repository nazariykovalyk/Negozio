<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';

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
        // Controlla se l'utente è loggato
        if (!isUtenteLoggato()) {
            $_SESSION['error'] = "Devi essere registrato per completare l'acquisto";
            header('Location: login.php');
            exit;
        }

        // Controlla se l'utente ha metodi di pagamento
        $utente_corrente = getUtenteCorrente();
        $metodi_pagamento = getMetodiPagamento($utente_corrente['id']);
        $ha_metodi_pagamento = !empty($metodi_pagamento);

        if (!$ha_metodi_pagamento) {
            $_SESSION['error'] = "Devi aggiungere un metodo di pagamento prima di procedere all'acquisto";
            header('Location: profilo.php#metodi-pagamento');
            exit;
        }

        try {
            $id_ordini = processaOrdineCarrello();
            $_SESSION['ordine_completato'] = $id_ordini;
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
$utente_corrente = getUtenteCorrente();

// Controlla metodi di pagamento solo per utenti loggati
if ($utente_corrente) {
    $metodi_pagamento = getMetodiPagamento($utente_corrente['id']);
    $ha_metodi_pagamento = !empty($metodi_pagamento);
} else {
    $ha_metodi_pagamento = false;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Carrello della Spesa - Sistema Acquisti</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <style>
        .login-required, .payment-required {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .payment-required {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .login-required a, .payment-required a {
            color: #856404;
            font-weight: bold;
            text-decoration: none;
        }

        .login-required a:hover, .payment-required a:hover {
            text-decoration: underline;
        }

        .btn-disabled {
            background: #6c757d !important;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .auth-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
        }

        .payment-info {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }

        .metodo-pagamento {
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
        }

        .metodo-pagamento.preferito {
            border-color: #28a745;
            background: #f8fff9;
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

<div class="container1">
    <div class="header1">
        <h1>🛒 Carrello della Spesa</h1>
        <p><?php echo $conta_articoli; ?> articoli nel carrello</p>
    </div>

    <?php if (isset($errore)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <h3>❌ Errore</h3>
            <p><?php echo htmlspecialchars($errore); ?></p>
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
        <?php elseif ($utente_corrente && !$ha_metodi_pagamento): ?>
            <div class="payment-required">
                <h3>💳 Metodo di Pagamento Richiesto</h3>
                <p>Per completare l'acquisto, devi aggiungere un metodo di pagamento.</p>
                <div class="auth-buttons">
                    <a href="profilo.php#metodi-pagamento" class="btn" style="background: #007bff; color: white;">➕ Aggiungi Carta</a>
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">Continua Acquisti</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Sezione Metodi di Pagamento (solo per utenti loggati con metodi) -->
            <div class="payment-info">
                <h3>💳 Metodo di Pagamento</h3>
                <p><strong>Metodo selezionato:</strong>
                    <?php
                    $metodo_preferito = getMetodoPreferito($utente_corrente['id']);
                    if ($metodo_preferito):
                        ?>
                        <?php echo htmlspecialchars($metodo_preferito['tipo']); ?> -
                        <?php
                        if ($metodo_preferito['tipo'] === 'carta') {
                            echo formattaNumeroCarta($metodo_preferito['numero_carta']);
                        } elseif ($metodo_preferito['tipo'] === 'paypal') {
                            echo htmlspecialchars($metodo_preferito['email_paypal']);
                        } elseif ($metodo_preferito['tipo'] === 'bonifico') {
                            echo formattaIBAN($metodo_preferito['iban']);
                        }
                        ?>
                        <br><small style="color: #28a745;">✓ Preferito</small>
                    <?php else: ?>
                        <em>Nessun metodo preferito impostato</em>
                    <?php endif; ?>
                </p>
                <a href="profilo.php#metodi-pagamento" class="btn btn-outline" style="padding: 5px 10px; font-size: 12px;">
                    Cambia Metodo
                </a>
            </div>
        <?php endif; ?>

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
                <a href="risultati.php" class="btn btn-secondary">Torna ai risultati</a>
                <a href="index.php" class="btn btn-secondary">Continua gli acquisti</a>

                <form method="POST" style="display: inline;">
                    <button type="submit" name="svuota_carrello" class="btn btn-danger">Svuota carrello</button>
                </form>

                <?php if ($utente_corrente && $ha_metodi_pagamento): ?>
                    <!-- Pulsante acquisto abilitato per utenti loggati CON metodi di pagamento -->
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="procedi_acquisto" class="btn btn-success">
                            Procedi all'acquisto ›
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Pulsante acquisto disabilitato -->
                    <button type="button" class="btn btn-success btn-disabled" disabled>
                        Procedi all'acquisto ›
                    </button>
                    <div style="text-align: center; margin-top: 10px;">
                        <small style="color: #dc3545;">
                            <?php if (!$utente_corrente): ?>
                                ⚠️ Devi <a href="registrazione.php" style="color: #dc3545;">registrarti</a> per completare l'acquisto
                            <?php else: ?>
                                ⚠️ Devi <a href="profilo.php#metodi-pagamento" style="color: #dc3545;">aggiungere una carta</a> per completare l'acquisto
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Previeni l'acquisto se l'utente non è loggato o non ha metodi di pagamento
    document.addEventListener('DOMContentLoaded', function() {
        const acquistoButtons = document.querySelectorAll('button[name="procedi_acquisto"]');
        acquistoButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                <?php if (!$utente_corrente): ?>
                e.preventDefault();
                alert('Devi essere registrato per completare l\'acquisto. Clicca su "Registrati" in alto a sinistra.');
                window.location.href = 'registrazione.php';
                <?php elseif ($utente_corrente && !$ha_metodi_pagamento): ?>
                e.preventDefault();
                alert('Devi aggiungere un metodo di pagamento per completare l\'acquisto. Vai al tuo profilo per aggiungere una carta.');
                window.location.href = 'profilo.php#metodi-pagamento';
                <?php endif; ?>
            });
        });

        // Mostra messaggio se si clicca sul pulsante disabilitato
        const btnDisabled = document.querySelector('.btn-disabled');
        if (btnDisabled) {
            btnDisabled.addEventListener('click', function(e) {
                e.preventDefault();
                <?php if (!$utente_corrente): ?>
                alert('Devi essere registrato per completare l\'acquisto. Clicca su "Registrati" in alto a sinistra.');
                window.location.href = 'registrazione.php';
                <?php else: ?>
                alert('Devi aggiungere un metodo di pagamento per completare l\'acquisto. Vai al tuo profilo per aggiungere una carta.');
                window.location.href = 'profilo.php#metodi-pagamento';
                <?php endif; ?>
            });
        }
    });
</script>

</body>
<?php
require_once 'footer.php';
?>
</html>