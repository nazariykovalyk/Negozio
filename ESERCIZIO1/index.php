<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';

// Carica e filtra articoli
$articoli = getArticoli();
$termine_ricerca = isset($_GET['cerca']) && !empty($_GET['cerca']) ? trim($_GET['cerca']) : '';
$utente_corrente = getUtenteCorrente();

if ($termine_ricerca) {
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

<!-- Icona Carrello sempre visibile -->
<div class="header-carrello">
    <a href="carrello.php">
        🛒 Carrello
        <?php if (contaArticoliCarrello() > 0): ?>
            <span class="badge-carrello"><?php echo contaArticoliCarrello(); ?></span>
        <?php endif; ?>
    </a>
</div>

<!-- Header con logo e menu utente -->
<div class="header">
    <div class="header-content">
        <div class="logo">🛍️ ShopOnline</div>
        <div class="user-menu">
            <?php if ($utente_corrente): ?>
                <!-- Utente loggato -->
                <div class="user-info">Ciao, <strong><?php echo htmlspecialchars($utente_corrente['nome']); ?></strong></div>
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
    <!-- Messaggio di benvenuto personalizzato -->
    <div class="<?php echo $utente_corrente ? 'welcome-message' : 'guest-message'; ?>">
        <h3>👋 <?php echo $utente_corrente ? "Bentornato, " . htmlspecialchars($utente_corrente['nome']) : "Benvenuto Ospite!"; ?>!</h3>
        <p>
            <?php echo $utente_corrente
                    ? "Pronto per trovare le migliori offerte? Cerca i prodotti che ti interessano e confronta i prezzi tra i fornitori."
                    : "Puoi cercare prodotti, confrontare i prezzi dei fornitori e aggiungere articoli al carrello. <strong>Registrati</strong> per completare gli acquisti!"; ?>
        </p>
    </div>

    <!-- Intestazione pagina e barra di ricerca -->
    <div class="page-header">
        <h1>Benvenuto nel nostro Shop</h1>
        <p>Trova i prodotti migliori al prezzo più conveniente</p>
    </div>

    <!-- Form di ricerca prodotti -->
    <form method="GET" action="index.php" class="search-bar">
        <input type="text" name="cerca" class="search-input"
               placeholder="Cerca prodotti per nome, descrizione o SKU..."
               value="<?php echo htmlspecialchars($termine_ricerca); ?>">
        <button type="submit" class="search-btn">🔍 Cerca</button>
        <?php if ($termine_ricerca): ?>
            <a href="index.php" class="btn btn-secondary">❌ Cancella</a>
        <?php endif; ?>
    </form>

    <!-- Risultati ricerca -->
    <?php if ($termine_ricerca): ?>
        <div class="search-results-info">
            <strong>Risultati per:</strong> "<?php echo htmlspecialchars($termine_ricerca); ?>"
            <span>(<?php echo count($articoli); ?> prodotti trovati)</span>
        </div>
    <?php endif; ?>

    <!-- Lista prodotti -->
    <?php if (empty($articoli)): ?>
        <div class="no-results">
            <h2>Nessun prodotto trovato</h2>
            <p>Prova a modificare i termini di ricerca o <a href="index.php">visualizza tutti i prodotti</a>.</p>
        </div>
    <?php else: ?>
        <!-- Form per cercare fornitori (richiede quantità) -->
        <form action="cerca_fornitori.php" method="POST">
            <div class="products-grid">
                <?php foreach($articoli as $articolo): ?>
                    <div class="product-card">
                        <!-- Immagine prodotto con fallback -->
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

                        <!-- Input quantità per ogni prodotto -->
                        <div class="product-form">
                            <label for="quantita_<?php echo $articolo['id_articolo']; ?>">Quantità desiderata:</label>
                            <input type="number" id="quantita_<?php echo $articolo['id_articolo']; ?>"
                                   name="quantita[<?php echo $articolo['id_articolo']; ?>]"
                                   min="0" max="100" value="0" class="quantity-input">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Azioni principali -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-large">
                    🔍 Cerca Migliori Fornitori
                </button>
                <?php if (!$utente_corrente): ?>
                    <div class="info-note">ℹ️ Registrati per completare gli acquisti.</div>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>

    <!-- Pulsante carrello se ci sono articoli -->
    <?php if (contaArticoliCarrello() > 0): ?>
        <div class="cart-actions">
            <a href="carrello.php" class="btn btn-cart btn-large">
                🛒 Vai al Carrello (<?php echo contaArticoliCarrello(); ?> articoli)
            </a>
            <?php if (!$utente_corrente): ?>
                <div class="info-note">ℹ️ Il carrello viene salvato durante la sessione. Registrati per completare l'acquisto.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
</body>
</html>