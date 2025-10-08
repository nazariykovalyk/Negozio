<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php';

$error = '';
$success = '';

// Redirect se già loggato
if (isUtenteLoggato()) {
    header('Location: index.php');
    exit;
}

// Gestione registrazione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $conferma_password = $_POST['conferma_password'];
    $nome = trim($_POST['nome']);
    $cognome = trim($_POST['cognome']);
    $telefono = trim($_POST['telefono']);
    $indirizzo = trim($_POST['indirizzo']);
    $citta = trim($_POST['citta']);

    // Validazioni
    if ($password !== $conferma_password) {
        $error = 'Le password non coincidono';
    } elseif (strlen($password) < 6) {
        $error = 'La password deve essere di almeno 6 caratteri';
    } else {
        $result = registraUtente($email, $password, $nome, $cognome, 'cliente', $telefono, $indirizzo, $citta);

        if ($result['success']) {
            $success = $result['message'] . ' <a href="login.php">Accedi ora</a>';
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Registrazione - ShopOnline</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .logo p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: bold;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #5a6fd8;
        }

        .links {
            text-align: center;
            margin-top: 20px;
        }

        .links a {
            color: #667eea;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 480px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .register-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="register-container">
    <div class="logo">
        <h1>🛍️ ShopOnline</h1>
        <p>Crea il tuo account</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" required value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="cognome">Cognome</label>
                <input type="text" id="cognome" name="cognome" required value="<?php echo isset($_POST['cognome']) ? htmlspecialchars($_POST['cognome']) : ''; ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="conferma_password">Conferma Password</label>
                <input type="password" id="conferma_password" name="conferma_password" required>
            </div>
        </div>

        <div class="form-group">
            <label for="telefono">Telefono (opzionale)</label>
            <input type="text" id="telefono" name="telefono" value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="indirizzo">Indirizzo (opzionale)</label>
            <textarea id="indirizzo" name="indirizzo" rows="2"><?php echo isset($_POST['indirizzo']) ? htmlspecialchars($_POST['indirizzo']) : ''; ?></textarea>
        </div>

        <div class="form-group">
            <label for="citta">Città (opzionale)</label>
            <input type="text" id="citta" name="citta" value="<?php echo isset($_POST['citta']) ? htmlspecialchars($_POST['citta']) : ''; ?>">
        </div>

        <button type="submit" name="register" class="btn">Registrati</button>
    </form>

    <div class="links">
        <p>Hai già un account? <a href="login.php">Accedi</a></p>
        <p><a href="index.php">← Torna allo shopping</a></p>
    </div>
</div>
</body>
</html>