<?php

//SHOP ONLINE - Pagina principale - Home page con elenco prodotti e ricerca
//Inclusione file di configurazione e funzioni principali
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';

//Dati iniziali
$utenteCorrente = getUtenteCorrente();
$termineRicerca = isset($_GET['cerca']) && (!empty($_GET['cerca'])) ? trim($_GET['cerca']) : '';
$articoli = getArticoli();

//Filtro di ricerca (se non è vuoto)
if (!empty($termineRicerca)) {
    $articoli = filtraArticoli($articoli, $termineRicerca);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Shop Online - Sistema Acquisti</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!--SEZIONE HEADER E MENU UTENTE-->
<header class="header">
    <div class="header-content">

        <!-- Logo -->
        <div class="logo">🛍️ ShopOnline</div>

        <!-- Menu utente -->
        <div class="user-menu">
            <?php if ($utenteCorrente): ?>
                <div class="user-info">
                    Ciao, <strong><?= htmlspecialchars($utenteCorrente['nome']); ?></strong>
                </div>
                <div class="user-actions">
                    <a href="profilo.php" class="btn-auth btn-outline">👤 Profilo</a>
                    <a href="logout.php" class="btn-auth">🚪 Logout</a>
                </div>
            <?php else: ?>
                <div class="user-actions">
                    <a href="login.php" class="btn-auth btn-outline">🔐 Login</a>
                    <a href="registrazione.php" class="btn-auth">📝 Registrati</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Icona carrello -->
        <div class="header-carrello">
            <a href="carrello.php">
                🛒 Carrello
                <?php if (contaArticoliCarrello() > 0): ?>
                    <span class="badge-carrello"><?= contaArticoliCarrello(); ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</header>

<!--SEZIONE PRINCIPALE-->
<main class="container">

    <!-- Messaggio di benvenuto -->
    <section class="<?= $utenteCorrente ? 'welcome-message' : 'guest-message'; ?>">
        <h3>
            👋 <?= $utenteCorrente
                    ? "Bentornato, " . htmlspecialchars($utenteCorrente['nome'])
                    : "Benvenuto Ospite!"; ?>
        </h3>
        <p>
            <?= $utenteCorrente
                    ? "Pronto per trovare le migliori offerte? Cerca i prodotti e confronta i prezzi tra fornitori."
                    : "Puoi cercare prodotti, confrontare i prezzi e aggiungerli al carrello. <strong>Registrati</strong> per completare gli acquisti!"; ?>
        </p>
    </section>

    <!-- Intestazione pagina -->
    <header class="page-header">
        <h1>Benvenuto nel nostro Shop</h1>
        <p>Trova i prodotti migliori al prezzo più conveniente</p>
    </header>

    <!-- Barra di ricerc    a -->
    <form method="GET" action="index.php" class="search-bar">
        <input
                type="text"
                name="cerca"
                class="search-input"
                placeholder="Cerca prodotti per nome, descrizione o SKU..."
                value="<?= htmlspecialchars($termineRicerca); ?>"
        >
        <button type="submit" class="search-btn">🔍 Cerca</button>

        <?php if ($termineRicerca): ?>
            <a href="index.php" class="btn btn-secondary">❌ Cancella</a>
        <?php endif; ?>
    </form>

    <!-- Informazioni sui risultati -->
    <?php if ($termineRicerca): ?>
        <div class="search-results-info">
            <strong>Risultati per:</strong> "<?= htmlspecialchars($termineRicerca); ?>"
            <span>(<?= count($articoli); ?> prodotti trovati)</span>
        </div>
    <?php endif; ?>

    <!--LISTA PRODOTTI-->
    <?php if (empty($articoli)): ?>

        <div class="no-results">
            <h2>Nessun prodotto trovato</h2>
            <p>
                Prova a modificare i termini di ricerca o
                <a href="index.php">visualizza tutti i prodotti</a>.
            </p>
        </div>

    <?php else: ?>

        <form action="cerca_fornitori.php" method="POST">
            <div class="products-grid">
                <?php foreach ($articoli as $articolo): ?>
                    <?php
                    $idArticolo = $articolo['id_articolo'];
                    $nomeArticolo = htmlspecialchars($articolo['nome']);
                    $skuArticolo = htmlspecialchars($articolo['SKU']);
                    $descrizioneBreve = htmlspecialchars(truncateDescription($articolo['descrizione'] ?? '', 100));
                    $immagine = getImmagineProdotto($articolo['nome']);
                    ?>
                    <div class="product-card">

                        <!-- Immagine prodotto -->
                        <img
                                src="<?= $immagine; ?>"
                                alt="<?= $nomeArticolo; ?>"
                                class="product-image"
                                onerror="this.src='https://via.placeholder.com/250x200/007bff/ffffff?text=<?= urlencode($articolo['nome']); ?>'"
                        >

                        <div class="product-name"><?= $nomeArticolo; ?></div>
                        <div class="product-sku">SKU: <?= $skuArticolo; ?></div>
                        <div class="product-price">€<?= number_format($articolo['prezzo_unitario'], 2); ?></div>
                        <div class="product-description"><?= $descrizioneBreve; ?></div>

                        <!-- Quantità desiderata -->
                        <div class="product-form">
                            <label for="quantita_<?= $idArticolo; ?>">Quantità desiderata:</label>
                            <input
                                    type="number"
                                    id="quantita_<?= $idArticolo; ?>"
                                    name="quantita[<?= $idArticolo; ?>]"
                                    min="0"
                                    max="100"
                                    value="0"
                                    class="quantity-input"
                            >
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Azioni principali -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-large">
                    🔍 Cerca Migliori Fornitori
                </button>

                <?php if (!$utenteCorrente): ?>
                    <div class="info-note">
                        ℹ️ Registrati per completare gli acquisti.
                    </div>
                <?php endif; ?>
            </div>
        </form>

    <?php endif; ?>

    <!--AZIONI CARRELLO-->
    <?php if (contaArticoliCarrello() > 0): ?>
        <div class="cart-actions">
            <a href="carrello.php" class="btn btn-cart btn-large">
                🛒 Vai al Carrello (<?= contaArticoliCarrello(); ?> articoli)
            </a>

            <?php if (!$utenteCorrente): ?>
                <div class="info-note">
                    ℹ️ Il carrello viene salvato durante la sessione. Registrati per completare l'acquisto.
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Footer -->
<?php include 'footer.php'; ?>

</body>
</html>
