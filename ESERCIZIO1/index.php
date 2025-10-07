<?php
require_once 'config.php';
require_once 'functions.php';

$articoli = getArticoli();

// Gestisci ricerca
$termine_ricerca = '';
if (isset($_GET['cerca']) && !empty($_GET['cerca'])) {
    $termine_ricerca = trim($_GET['cerca']);
    $articoli = filtraArticoli($articoli, $termine_ricerca);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Shop Online - Sistema Acquisti</title>
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

<!-- Header -->
<div class="header">
    <div class="header-content">
        <div class="logo">🛍️ ShopOnline</div>
        <div style="color: white; font-size: 14px;">
            Sistema di acquisto intelligente
        </div>
    </div>
</div>

<div class="container">
    <!-- Titolo e ricerca -->
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #232f3e; margin-bottom: 10px;">Benvenuto nel nostro Shop</h1>
        <p style="color: #666; font-size: 16px;">Trova i prodotti migliori al prezzo più conveniente</p>
    </div>

    <!-- Barra di ricerca -->
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

    <!-- Risultati ricerca -->
    <?php if (!empty($termine_ricerca)): ?>
        <div style="margin-bottom: 20px; color: #666;">
            <strong>Risultati per:</strong> "<?php echo htmlspecialchars($termine_ricerca); ?>"
            <span style="color: #ff9900;">(<?php echo count($articoli); ?> prodotti trovati)</span>
        </div>
    <?php endif; ?>

    <!-- Griglia prodotti -->
    <?php if (empty($articoli)): ?>
        <div class="no-results">
            <h2>Nessun prodotto trovato</h2>
            <p>Prova a modificare i termini di ricerca o <a href="index.php">visualizza tutti i prodotti</a>.</p>
        </div>
    <?php else: ?>
        <form action="cerca_fornitori.php" method="POST">
            <div class="products-grid">
                <?php foreach($articoli as $articolo): ?>
                    <div class="product-card">
                        <!-- Immagine prodotto -->
                        <img src="<?php echo getImmagineProdotto($articolo['nome']); ?>"
                             alt="<?php echo htmlspecialchars($articolo['nome']); ?>"
                             class="product-image"
                             onerror="this.src='https://via.placeholder.com/250x200/007bff/ffffff?text=<?php echo urlencode($articolo['nome']); ?>'">

                        <!-- Informazioni prodotto -->
                        <div class="product-name"><?php echo htmlspecialchars($articolo['nome']); ?></div>
                        <div class="product-sku">SKU: <?php echo htmlspecialchars($articolo['SKU']); ?></div>
                        <div class="product-price">€<?php echo number_format($articolo['prezzo_vendita'], 2); ?></div>

                        <!-- Descrizione -->
                        <div class="product-description">
                            <?php echo htmlspecialchars(truncateDescription($articolo['descrizione'] ?? '', 100)); ?>
                        </div>

                        <!-- Selezione quantità -->
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
            </div>
        </form>
    <?php endif; ?>

    <!-- Pulsante carrello se ci sono articoli -->
    <?php if (contaArticoliCarrello() > 0): ?>
        <div style="text-align: center; margin-top: 30px;">
            <a href="carrello.php" class="btn btn-cart" style="padding: 12px 24px; font-size: 16px;">
                🛒 Vai al Carrello (<?php echo contaArticoliCarrello(); ?> articoli)
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<div class="footer">
    <p>© 2025 ShopOnline - Sistema di Gestione Acquisti. Tutti i diritti riservati.</p>
</div>

<script>
    // Animazione per il carrello
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