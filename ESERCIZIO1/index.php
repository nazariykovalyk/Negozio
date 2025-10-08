<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';

$articoli = getArticoli();

$termine_ricerca = '';
if (isset($_GET['cerca']) && !empty($_GET['cerca'])) {
    $termine_ricerca = trim($_GET['cerca']);
    $articoli = filtraArticoli($articoli, $termine_ricerca);
}

$utente_corrente = getUtenteCorrente();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Shop Online - Sistema Acquisti</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <style>
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-right: auto;
        }

        .user-info {
            color: white;
            font-size: 14px;
        }

        .user-actions {
            display: flex;
            gap: 10px;
        }

        .btn-auth {
            padding: 8px 16px;
            background: #ff9900;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-auth:hover {
            background: #e68900;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #ff9900;
        }

        .btn-outline:hover {
            background: #ff9900;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #ff9900;
            margin-right: auto;
        }

        .welcome-message {
            text-align: center;
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }

        .guest-message {
            text-align: center;
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
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

<div class="header">
    <div class="header-content">
        <div class="logo">🛍️ ShopOnline</div>

        <div class="user-menu">
            <?php if ($utente_corrente): ?>
                <!-- Utente loggato -->
                <div class="user-info">
                    Ciao, <strong><?php echo htmlspecialchars($utente_corrente['nome']); ?>
                </div>
                <div class="user-actions">
                    <a href="profilo.php" class="btn-auth btn-outline">👤 Profilo</a>
                    <a href="logout.php" class="btn-auth">🚪 Logout</a>
                </div>
            <?php else: ?>
                <!-- Utente non loggato -->
                <div class="user-actions">
                    <a href="login.php" class="btn-auth btn-outline">🔐 Login</a>
                    <a href="registrazione.php" class="btn-auth">📝 Registrati</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container">
    <?php if ($utente_corrente): ?>
        <div class="welcome-message">
            <h3>👋 Bentornato, <?php echo htmlspecialchars($utente_corrente['nome']); ?>!</h3>
            <p>Pronto per trovare le migliori offerte? Cerca i prodotti che ti interessano e confronta i prezzi tra i fornitori.</p>
        </div>
    <?php else: ?>
        <div class="guest-message">
            <h3>👋 Benvenuto Ospite!</h3>
            <p>Puoi cercare prodotti, confrontare i prezzi dei fornitori e aggiungere articoli al carrello. <strong>Registrati</strong> per completare gli acquisti!</p>
        </div>
    <?php endif; ?>

    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #232f3e; margin-bottom: 10px;">Benvenuto nel nostro Shop</h1>
        <p style="color: #666; font-size: 16px;">Trova i prodotti migliori al prezzo più conveniente</p>
    </div>

    <form method="GET" action="index.php" class="search-bar">
        <input type="text"
               name="cerca"
               class="search-input"
               placeholder="Cerca prodotti per nome, descrizione o SKU..."
               value="<?php echo htmlspecialchars($termine_ricerca); ?>">
        <button type="submit" class="search-btn">🔍 Cerca</button>
        <?php if (!empty($termine_ricerca)): ?>
            <a href="index.php" class="btn" style="background: #6c757d; color: white;">❌ Cancella</a>
        <?php endif; ?>
    </form>

    <?php if (!empty($termine_ricerca)): ?>
        <div style="margin-bottom: 20px; color: #666;">
            <strong>Risultati per:</strong> "<?php echo htmlspecialchars($termine_ricerca); ?>"
            <span style="color: #ff9900;">(<?php echo count($articoli); ?> prodotti trovati)</span>
        </div>
    <?php endif; ?>

    <?php if (empty($articoli)): ?>
        <div class="no-results">
            <h2>Nessun prodotto trovato</h2>
            <p>Prova a modificare i termini di ricerca o <a href="index.php">visualizza tutti i prodotti</a>.</p>
        </div>
    <?php else: ?>
        <form action="cerca_fornitori.php" method="POST" id="form-ricerca">
            <div class="products-grid">
                <?php foreach($articoli as $articolo): ?>
                    <div class="product-card">
                        <!-- Immagine prodotto -->
                        <img src="<?php echo getImmagineProdotto($articolo['nome']); ?>"
                             alt="<?php echo htmlspecialchars($articolo['nome']); ?>"
                             class="product-image"
                             onerror="this.src='https://via.placeholder.com/250x200/007bff/ffffff?text=<?php echo urlencode($articolo['nome']); ?>'">

                        <div class="product-name"><?php echo htmlspecialchars($articolo['nome']); ?></div>
                        <div class="product-sku">SKU: <?php echo htmlspecialchars($articolo['SKU']); ?></div>
                        <div class="product-price">€<?php echo number_format($articolo['prezzo_vendita'], 2); ?></div>

                        <div class="product-description">
                            <?php echo htmlspecialchars(truncateDescription($articolo['descrizione'] ?? '', 100)); ?>
                        </div>

                        <div class="product-form">
                            <div class="form-group">
                                <label for="quantita_<?php echo $articolo['id_articolo']; ?>">
                                    Quantità desiderata:
                                </label>
                                <input type="number"
                                       id="quantita_<?php echo $articolo['id_articolo']; ?>"
                                       name="quantita[<?php echo $articolo['id_articolo']; ?>]"
                                       min="0"
                                       max="100"
                                       value="0"
                                       class="quantity-input">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pulsante di invio -->
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" class="btn btn-primary" style="padding: 15px 30px; font-size: 18px;">
                    🔍 Cerca Migliori Fornitori
                </button>

                <?php if (!$utente_corrente): ?>
                    <div style="margin-top: 10px;">
                        <small style="color: #17a2b8;">
                            ℹ️ Puoi cercare fornitori anche da ospite! Registrati per completare gli acquisti.
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>

    <!-- Pulsante carrello se ci sono articoli -->
    <?php if (contaArticoliCarrello() > 0): ?>
        <div style="text-align: center; margin-top: 30px;">
            <a href="carrello.php" class="btn btn-cart" style="padding: 12px 24px; font-size: 16px;">
                🛒 Vai al Carrello (<?php echo contaArticoliCarrello(); ?> articoli)
            </a>
            <?php if (!$utente_corrente): ?>
                <div style="margin-top: 10px;">
                    <small style="color: #17a2b8;">
                        ℹ️ Il carrello viene salvato durante la sessione. Registrati per completare l'acquisto.
                    </small>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const cartButtons = document.querySelectorAll('.btn-cart');
        cartButtons.forEach(button => {
            button.addEventListener('click', function() {
                const notification = document.createElement('div');
                notification.className = 'cart-notification';
                notification.textContent = 'Prodotto aggiunto al carrello!';
                notification.style.display = 'block';
                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.remove();
                }, 3000);
            });
        });
    });
</script>
</body>
</html>