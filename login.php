<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Email ou mot de passe incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — EduProject</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #f0f0f3;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-font-smoothing: antialiased;
            letter-spacing: -0.01em;
        }

        .login-shell {
            width: 100%;
            max-width: 360px;
            padding: 20px;
        }

        /* Logo / marque */
        .brand {
            text-align: center;
            margin-bottom: 20px;
        }
        .brand-name {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #60606c;
        }

        /* Card */
        .auth-card {
            background: #1e1e24;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            padding: 28px 28px 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1), 0 8px 32px rgba(0,0,0,0.18), 0 24px 64px rgba(0,0,0,0.12);
        }

        .auth-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #eeeef2;
            letter-spacing: -0.04em;
            margin-bottom: 4px;
        }

        .auth-subtitle {
            font-size: 0.85rem;
            color: #9898a4;
            margin-bottom: 20px;
            font-weight: 400;
        }

        /* Alert */
        .alert-error {
            display: flex;
            align-items: center;
            gap: 9px;
            background: rgba(255,69,58,0.1);
            border: 1px solid rgba(255,69,58,0.22);
            border-radius: 10px;
            color: #ff453a;
            font-size: 0.83rem;
            font-weight: 500;
            padding: 11px 14px;
            margin-bottom: 20px;
        }

        /* Form */
        .form-group { margin-bottom: 13px; }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #9898a4;
            margin-bottom: 7px;
        }

        .form-group input {
            width: 100%;
            padding: 9px 12px;
            background: #2a2a34;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            color: #eeeef2;
            font-family: inherit;
            font-size: 0.9rem;
            transition: border-color 200ms, box-shadow 200ms, background 200ms;
            -webkit-appearance: none;
        }
        .form-group input::placeholder { color: #60606c; }
        .form-group input:hover  { background: #31313d; border-color: rgba(255,255,255,0.14); }
        .form-group input:focus  {
            outline: none;
            border-color: #0a84ff;
            background: #31313d;
            box-shadow: 0 0 0 3px rgba(10,132,255,0.18);
        }

        /* Buttons */
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 18px;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 9px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: -0.01em;
            transition: all 200ms cubic-bezier(0.22,1,0.36,1);
            text-decoration: none;
        }

        .btn-primary {
            background: #0a84ff;
            color: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .btn-primary:hover  { background: #0070d4; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(10,132,255,0.35); }
        .btn-primary:active { transform: translateY(0); }

        .btn-secondary {
            background: #2a2a34;
            color: #9898a4;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .btn-secondary:hover { background: #31313d; color: #eeeef2; border-color: rgba(255,255,255,0.14); }

        /* Footer */
        .auth-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.78rem;
            color: #60606c;
        }
    </style>
</head>
<body>

<div class="login-shell">
    <div class="brand">
        <span class="brand-name">EduProject</span>
    </div>

    <div class="auth-card">
        <h1 class="auth-title">Connexion</h1>
        <p class="auth-subtitle">Accédez à votre espace de travail.</p>

        <?php if ($error): ?>
            <div class="alert-error">✕ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Adresse email</label>
                <input type="email" name="email" placeholder="nom@junia.com" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
        </form>
    </div>

    <div class="auth-footer">EduProject · Projet de fin d'année 3A</div>
</div>

</body>
</html>