<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php';

// Controlla se l'utente è loggato
if (!isUtenteLoggato()) {
    header('Location: login.php');
    exit;
}

$utente_corrente = getUtenteCorrente();
$utente_db = getUtenteById($utente_corrente['id']);
$messaggio_successo = '';
$errore = '';

// Gestione modifica profilo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiorna_profilo'])) {
    $nome = trim($_POST['nome']);
    $cognome = trim($_POST['cognome']);
    $telefono = trim($_POST['telefono']);
    $indirizzo = trim($_POST['indirizzo']);
    $citta = trim($_POST['citta']);

    if (empty($nome) || empty($cognome)) {
        $errore = 'Nome e cognome sono obbligatori';
    } else {
        $conn = getDBConnection();
        try {
            $stmt = $conn->prepare("UPDATE Utenti SET nome = ?, cognome = ?, telefono = ?, indirizzo = ?, citta = ? WHERE id_utente = ?");
            //Aggiornamento dei dati
            if ($stmt->execute([$nome, $cognome, $telefono, $indirizzo, $citta, $utente_corrente['id']])) {
                $_SESSION['user_nome'] = $nome;
                $_SESSION['user_cognome'] = $cognome;
                $utente_db = getUtenteById($utente_corrente['id']);
                $messaggio_successo = 'Profilo aggiornato con successo';
            } else {
                $errore = 'Errore durante l\'aggiornamento';
            }
        } catch (PDOException $e) {
            $errore = 'Errore database: ' . $e->getMessage();
        }
    }
}

// Gestione cambio password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambia_password'])) {
    $vecchia_password = $_POST['vecchia_password'];
    $nuova_password = $_POST['nuova_password'];
    $conferma_password = $_POST['conferma_password'];

    if (empty($vecchia_password) || empty($nuova_password) || empty($conferma_password)) {
        $errore = 'Tutti i campi della password sono obbligatori';
    } elseif ($nuova_password !== $conferma_password) {
        $errore = 'Le nuove password non coincidono';
    } elseif (strlen($nuova_password) < 6) {
        $errore = 'La password deve contenere almeno 6 caratteri';
    } else {
        $result = cambiaPasswordUtente($utente_corrente['id'], $vecchia_password, $nuova_password);
        if ($result['success']) {
            $messaggio_successo = $result['message'];
        } else {
            $errore = $result['message'];
        }
    }
}

// Gestione metodi di pagamento
$metodi_pagamento = getMetodiPagamento($utente_corrente['id']);

// Aggiungi nuovo metodo di pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiungi_metodo_pagamento'])) {
    $tipo = $_POST['tipo_pagamento'];
    $titolare = trim($_POST['titolare']);
    $preferito = isset($_POST['preferito']);

    $dati_metodo = [
            'tipo' => $tipo,
            'titolare' => $titolare,
            'preferito' => $preferito
    ];

    // Dati specifici per tipo
    if ($tipo === 'carta') {
        $dati_metodo['numero_carta'] = str_replace(' ', '', $_POST['numero_carta']);
        $dati_metodo['scadenza'] = $_POST['scadenza'];
        $dati_metodo['cvv'] = $_POST['cvv'];
    } elseif ($tipo === 'paypal') {
        $dati_metodo['email_paypal'] = trim($_POST['email_paypal']);
    } elseif ($tipo === 'bonifico') {
        $dati_metodo['iban'] = str_replace(' ', '', $_POST['iban']);//elimina spazi indesiderati
    }

    $result = aggiungiMetodoPagamento($utente_corrente['id'], $dati_metodo);
    if ($result['success']) {
        $messaggio_successo = $result['message'];
        $metodi_pagamento = getMetodiPagamento($utente_corrente['id']);
    } else {
        $errore = $result['message'];
    }
}

// Imposta metodo come preferito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['imposta_preferito'])) {
    $id_metodo = $_POST['id_metodo'];
    if (impostaMetodoPreferito($utente_corrente['id'], $id_metodo)) {
        $messaggio_successo = 'Metodo di pagamento impostato come preferito';
        $metodi_pagamento = getMetodiPagamento($utente_corrente['id']);
    } else {
        $errore = 'Errore durante l\'impostazione del metodo preferito';
    }
}

// Rimuovi metodo di pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rimuovi_metodo'])) {
    $id_metodo = $_POST['id_metodo'];
    if (rimuoviMetodoPagamento($utente_corrente['id'], $id_metodo)) {
        $messaggio_successo = 'Metodo di pagamento rimosso';
        $metodi_pagamento = getMetodiPagamento($utente_corrente['id']);
    } else {
        $errore = 'Errore durante la rimozione del metodo di pagamento';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilo Utente - ShopOnline</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <style>
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .user-menu { display: flex; align-items: center; gap: 15px; margin-right: auto; }
        .user-info { color: white; font-size: 14px; }
        .user-actions { display: flex; gap: 10px; }
        .btn-auth { padding: 8px 16px; background: #ff9900; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; transition: background 0.3s; }
        .btn-auth:hover { background: #e68900; }
        .btn-outline { background: transparent; border: 1px solid #ff9900; }
        .btn-outline:hover { background: #ff9900; }
        .logo { font-size: 24px; font-weight: bold; color: #ff9900; margin-right: auto; }
        .profilo-header { background: white; padding: 30px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .profilo-header h1 { color: #232f3e; margin-bottom: 10px; font-size: 28px; }
        .info-utente { display: flex; align-items: center; gap: 20px; margin-top: 15px; }
        .avatar { width: 80px; height: 80px; background: #ff9900; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; color: white; }
        .info-testo strong { color: #232f3e; }
        .info-testo p { margin: 5px 0; color: #666; }
        .due-colonne { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        @media (max-width: 768px) { .due-colonne { grid-template-columns: 1fr; } }
        .sezione { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .sezione h2 { color: #232f3e; margin-bottom: 20px; font-size: 20px; border-bottom: 2px solid #ff9900; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: bold; font-size: 14px; }
        input[type="text"], input[type="email"], input[type="password"], input[type="tel"], input[type="month"], select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; transition: border-color 0.3s; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #ff9900; box-shadow: 0 0 5px rgba(255, 153, 0, 0.2); }
        textarea { resize: vertical; min-height: 80px; }
        .form-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn-update { flex: 1; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background 0.3s; }
        .btn-update:hover { background: #218838; }
        .btn-cancel { flex: 1; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background 0.3s; text-decoration: none; display: flex; align-items: center; justify-content: center; }
        .btn-cancel:hover { background: #5a6268; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info-riga { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .info-riga:last-child { border-bottom: none; }
        .info-label { color: #666; font-weight: bold; }
        .info-valore { color: #232f3e; }
        .link-back { display: inline-block; margin-bottom: 20px; color: #ff9900; text-decoration: none; font-size: 14px; }
        .link-back:hover { text-decoration: underline; }
        .metodo-pagamento { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 15px; transition: all 0.3s; }
        .metodo-pagamento.preferito { border-color: #28a745; background: #f8fff9; box-shadow: 0 2px 5px rgba(40, 167, 69, 0.2); }
        .metodo-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .metodo-tipo { font-weight: bold; color: #232f3e; font-size: 16px; }
        .metodo-preferito { background: #28a745; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .metodo-azioni { display: flex; gap: 10px; }
        .btn-small { padding: 6px 12px; font-size: 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary-small { background: #007bff; color: white; }
        .btn-danger-small { background: #dc3545; color: white; }
        .metodo-dettagli { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px; }
        .dettaglio-label { color: #666; font-weight: bold; }
        .dettaglio-valore { color: #232f3e; }
        .nessun-metodo { text-align: center; padding: 40px; color: #666; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6; }
        .tipo-pagamento-options { display: flex; gap: 15px; margin-bottom: 20px; }
        .tipo-option { flex: 1; text-align: center; padding: 15px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
        .tipo-option:hover, .tipo-option.selected { border-color: #007bff; }
        .tipo-option.selected { background: #e7f3ff; }
        .tipo-option input[type="radio"] { display: none; }
        .campi-tipo { display: none; }
        .campi-tipo.attivo { display: block; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin-top: 15px; }
        .checkbox-group input[type="checkbox"] { width: auto; }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-content">
        <div class="logo">🛍️ ShopOnline</div>
        <div class="user-menu">
            <?php if ($utente_corrente): ?>
                <div class="user-info">Ciao, <strong><?php echo htmlspecialchars($utente_corrente['nome']); ?></strong></div>
                <div class="user-actions">
                    <a href="index.php" class="btn-auth btn-outline">🏠 Home</a>
                    <a href="logout.php" class="btn-auth">🚪 Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container">
    <a href="index.php" class="link-back">← Torna allo shopping</a>

    <!-- Header Profilo -->
    <div class="profilo-header">
        <h1>👤 Il Mio Profilo</h1>
        <div class="info-utente">
            <!--Prima lettera della foto profilo-->
            <div class="avatar"><?php echo strtoupper(substr($utente_corrente['nome'], 0, 1)); ?></div>
            <div class="info-testo">
                <strong><?php echo htmlspecialchars($utente_corrente['nome'] . ' ' . $utente_corrente['cognome']); ?></strong>
                <p><?php echo htmlspecialchars($utente_corrente['email']); ?></p>
                <p style="font-size: 12px; color: #999;">Iscritto dal <?php echo date('d/m/Y', strtotime($utente_db['data_registrazione'])); ?></p>
            </div>
        </div>
    </div>

    <!-- Messaggi -->
    <?php if ($messaggio_successo): ?>
        <div class="alert alert-success">✓ <?php echo htmlspecialchars($messaggio_successo); ?></div>
    <?php endif; ?>
    <?php if ($errore): ?>
        <div class="alert alert-error">✗ <?php echo htmlspecialchars($errore); ?></div>
    <?php endif; ?>

    <div class="due-colonne">
        <!-- Dati Personali -->
        <div class="sezione">
            <h2>Informazioni Personali</h2>
            <form method="POST" action="">
                <?php
                $campi = [
                        ['nome' => 'nome', 'label' => 'Nome *', 'tipo' => 'text', 'required' => true],
                        ['nome' => 'cognome', 'label' => 'Cognome *', 'tipo' => 'text', 'required' => true],
                        ['nome' => 'email', 'label' => 'Email', 'tipo' => 'email', 'disabled' => true],
                        ['nome' => 'telefono', 'label' => 'Telefono', 'tipo' => 'tel'],
                        ['nome' => 'indirizzo', 'label' => 'Indirizzo', 'tipo' => 'text'],
                        ['nome' => 'citta', 'label' => 'Città', 'tipo' => 'text']
                ];

                foreach ($campi as $campo): ?>
                    <div class="form-group">
                        <label for="<?php echo $campo['nome']; ?>"><?php echo $campo['label']; ?></label>
                        <input type="<?php echo $campo['tipo']; ?>" id="<?php echo $campo['nome']; ?>" name="<?php echo $campo['nome']; ?>"
                               value="<?php echo htmlspecialchars($utente_db[$campo['nome']] ?? ''); ?>"
                                <?php echo $campo['required'] ?? false ? 'required' : ''; ?>
                                <?php echo $campo['disabled'] ?? false ? 'disabled style="background: #f5f5f5; color: #999;"' : ''; ?>>
                        <?php if ($campo['nome'] === 'email'): ?>
                            <small style="color: #999;">Non può essere modificata</small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" name="aggiorna_profilo" class="btn-update">Salva Modifiche</button>
                    <a href="profilo.php" class="btn-cancel">Annulla</a>
                </div>
            </form>
        </div>

        <!-- Cambio Password -->
        <div class="sezione">
            <h2>Cambio Password</h2>
            <form method="POST" action="">
                <?php
                $campi_password = [
                        ['nome' => 'vecchia_password', 'label' => 'Password Attuale *', 'tipo' => 'password'],
                        ['nome' => 'nuova_password', 'label' => 'Nuova Password *', 'tipo' => 'password'],
                        ['nome' => 'conferma_password', 'label' => 'Conferma Password *', 'tipo' => 'password']
                ];

                foreach ($campi_password as $campo): ?>
                    <div class="form-group">
                        <label for="<?php echo $campo['nome']; ?>"><?php echo $campo['label']; ?></label>
                        <input type="<?php echo $campo['tipo']; ?>" id="<?php echo $campo['nome']; ?>" name="<?php echo $campo['nome']; ?>" required>
                        <?php if ($campo['nome'] === 'nuova_password'): ?>
                            <small style="color: #666;">Minimo 6 caratteri</small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" name="cambia_password" class="btn-update">Aggiorna Password</button>
                    <a href="profilo.php" class="btn-cancel">Annulla</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Metodi di Pagamento -->
    <div class="sezione" id="metodi-pagamento">
        <h2>💳 Metodi di Pagamento</h2>

        <!-- Lista Metodi Esistenti -->
        <div style="margin-bottom: 30px;">
            <h3 style="color: #232f3e; margin-bottom: 15px;">I tuoi metodi di pagamento</h3>

            <?php if (empty($metodi_pagamento)): ?>
                <div class="nessun-metodo">
                    <h3>Nessun metodo di pagamento aggiunto</h3>
                    <p>Aggiungi un metodo di pagamento per completare i tuoi acquisti più velocemente.</p>
                </div>
            <?php else: ?>
                <?php foreach($metodi_pagamento as $metodo):
                    $icona = match($metodo['tipo']) {//uso matc al posto di switch
                        'carta' => '💳',
                        'paypal' => '📧',
                        'bonifico' => '🏦',
                        default => '💳'
                    };
                    ?>
                    <div class="metodo-pagamento <?php echo $metodo['preferito'] ? 'preferito' : ''; ?>">
                        <div class="metodo-header">
                            <div class="metodo-tipo">
                                <?php echo $icona . ' ' . strtoupper($metodo['tipo']); ?>
                                <?php if ($metodo['preferito']): ?><!--se vero mette preferito-->
                                    <span class="metodo-preferito">PREFERITO</span>
                                <?php endif; ?>
                            </div>
                            <div class="metodo-azioni">
                                <?php if (!$metodo['preferito']): ?><!--Imposta il preferito-->
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id_metodo" value="<?php echo $metodo['id_metodo']; ?>">
                                        <button type="submit" name="imposta_preferito" class="btn-small btn-primary-small">Imposta Preferito</button>
                                    </form>
                                <?php endif; ?>
                                <!--rimuove il metodo di pagamento-->
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Sei sicuro di voler rimuovere questo metodo di pagamento?');">
                                    <input type="hidden" name="id_metodo" value="<?php echo $metodo['id_metodo']; ?>">
                                    <button type="submit" name="rimuovi_metodo" class="btn-small btn-danger-small">Rimuovi</button>
                                </form>
                            </div>
                        </div>
                        <div class="metodo-dettagli">
                            <div>
                                <span class="dettaglio-label">Titolare:</span>
                                <span class="dettaglio-valore"><?php echo htmlspecialchars($metodo['titolare']); ?></span>
                            </div>
                            <?php if ($metodo['tipo'] === 'carta'): ?>
                                <div><span class="dettaglio-label">Numero Carta:</span><span class="dettaglio-valore"><?php echo formattaNumeroCarta($metodo['numero_carta']); ?></span></div>
                                <div><span class="dettaglio-label">Scadenza:</span><span class="dettaglio-valore"><?php echo $metodo['scadenza']; ?></span></div>
                            <?php elseif ($metodo['tipo'] === 'paypal'): ?>
                                <div><span class="dettaglio-label">Email PayPal:</span><span class="dettaglio-valore"><?php echo htmlspecialchars($metodo['email_paypal']); ?></span></div>
                            <?php elseif ($metodo['tipo'] === 'bonifico'): ?>
                                <div><span class="dettaglio-label">IBAN:</span><span class="dettaglio-valore"><?php echo formattaIBAN($metodo['iban']); ?></span></div>
                            <?php endif; ?>
                            <div><span class="dettaglio-label">Aggiunto il:</span><span class="dettaglio-valore"><?php echo date('d/m/Y', strtotime($metodo['data_creazione'])); ?></span></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Form Aggiungi Nuovo Metodo -->
        <h3 style="color: #232f3e; margin-bottom: 15px;">Aggiungi nuovo metodo di pagamento</h3>
        <form method="POST" action="">
            <!-- Selezione Tipo -->
            <div class="form-group">
                <label>Tipo di Pagamento *</label>
                <div class="tipo-pagamento-options">
                    <?php
                    $tipi_pagamento = [
                            'carta' => '💳 Carta di Credito',
                            'paypal' => '📧 PayPal',
                            'bonifico' => '🏦 Bonifico Bancario'
                    ];
                    foreach ($tipi_pagamento as $valore => $testo): ?>
                        <label class="tipo-option" id="option-<?php echo $valore; ?>">
                            <input type="radio" name="tipo_pagamento" value="<?php echo $valore; ?>" required>
                            <?php echo $testo; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Campi Comuni -->
            <div class="form-group">
                <label for="titolare">Titolare *</label>
                <input type="text" id="titolare" name="titolare" placeholder="Nome e cognome del titolare" required>
            </div>

            <!-- Campi Specifici -->
            <div class="campi-tipo" id="campi-carta">
                <div class="form-group">
                    <label for="numero_carta">Numero Carta *</label>
                    <input type="text" id="numero_carta" name="numero_carta" placeholder="1234 5678 9012 3456" maxlength="19">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="scadenza">Scadenza *</label>
                        <input type="month" id="scadenza" name="scadenza" min="<?php echo date('Y-m'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="cvv">CVV *</label>
                        <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="3" pattern="[0-9]{3}">
                    </div>
                </div>
            </div>

            <div class="campi-tipo" id="campi-paypal">
                <div class="form-group">
                    <label for="email_paypal">Email PayPal *</label>
                    <input type="email" id="email_paypal" name="email_paypal" placeholder="tuo@email.com">
                </div>
            </div>

            <div class="campi-tipo" id="campi-bonifico">
                <div class="form-group">
                    <label for="iban">IBAN *</label>
                    <input type="text" id="iban" name="iban" placeholder="IT00 X123 4567 8901 2345 6789 012" maxlength="34">
                </div>
            </div>

            <!-- Preferito -->
            <div class="checkbox-group">
                <input type="checkbox" id="preferito" name="preferito" value="1" <?php echo empty($metodi_pagamento) ? 'checked' : ''; ?>>
                <label for="preferito" style="font-weight: normal;">Imposta come metodo di pagamento preferito</label>
            </div>

            <div class="form-actions">
                <button type="submit" name="aggiungi_metodo_pagamento" class="btn-update">Aggiungi Metodo di Pagamento</button>
                <a href="profilo.php#metodi-pagamento" class="btn-cancel">Annulla</a>
            </div>
        </form>
    </div>

    <!-- Informazioni Account -->
    <div class="sezione">
        <h2>Informazioni Account</h2>
        <?php
        $info_account = [
                'Email' => htmlspecialchars($utente_db['email']),
                'Stato Account' => $utente_db['attivo'] ? '✓ Attivo' : '✗ Disattivato',
                'Data Iscrizione' => date('d/m/Y H:i', strtotime($utente_db['data_registrazione'])),
                'Ultimo Accesso' => $utente_db['ultimo_accesso'] ? date('d/m/Y H:i', strtotime($utente_db['ultimo_accesso'])) : 'Mai'
        ];

        foreach ($info_account as $label => $valore): ?>
            <div class="info-riga">
                <span class="info-label"><?php echo $label; ?>:</span>
                <span class="info-valore"><?php echo $valore; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    //Attende che il DOM della pagina sia completamente caricato e pronto
    document.addEventListener('DOMContentLoaded', function() {
        //Seleziona tutti gli elementi con classe tipo-option
        const tipoOptions = document.querySelectorAll('.tipo-option');
        //Crea un oggetto che mappa ogni tipo di pagamento al suo rispettivo contenitore di campi
        const campi = {
            'carta': document.getElementById('campi-carta'),
            'paypal': document.getElementById('campi-paypal'),
            'bonifico': document.getElementById('campi-bonifico')
        };
        //Funzione per mostrare solo i campi relativi al tipo di pagamento selezionato
        function mostraCampi(tipo) {
            // Nasconde tutti i campi rimuovendo la classe 'attivo' da tutti i contenitori
            Object.values(campi).forEach(campo => campo.classList.remove('attivo'));
            if (campi[tipo]) campi[tipo].classList.add('attivo');//Se esiste aggiunge la classe 'attivo'
        }
        //Aggiunge event listener a ogni opzione di pagamento
        tipoOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            option.addEventListener('click', () => {
                tipoOptions.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                mostraCampi(radio.value);
            });
            if (radio.checked) {
                option.classList.add('selected');
                mostraCampi(radio.value);
            }
        });

        //verifica se l'elemento esiste prima di aggiungere l'event listener
        document.getElementById('numero_carta')?.addEventListener('input', function(e) {
            //Rimuove spazi e caratteri non numerici dal valore
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            //Formatta il numero in gruppi di 4 cifre separati da spazio
            // Esempio: "1234567812345678" → "1234 5678 1234 5678"
            e.target.value = value.match(/.{1,4}/g)?.join(' ') || value;
        });

        document.getElementById('iban')?.addEventListener('input', function(e) {
            //Rimuove spazi e converte in maiuscolo
            let value = e.target.value.replace(/\s+/g, '').toUpperCase();
            // Esempio: "IT60X0542811101000000123456" → "IT60 X054 2811 1010 0000 0123 456"
            e.target.value = value.match(/.{1,4}/g)?.join(' ') || value;
        });
    });</script>

<?php include 'footer.php'; ?>
</body>
</html>