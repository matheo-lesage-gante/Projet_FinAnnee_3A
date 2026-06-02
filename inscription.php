<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $first_name       = trim($_POST['first_name']       ?? '');
        $email            = trim($_POST['email']            ?? '');
        $password         = $_POST['password']              ?? '';
        $confirm_password = $_POST['confirm_password']      ?? '';

        if ($password !== $confirm_password) {
            $error = "Les mots de passe ne correspondent pas.";
        } else {
            $role = '';
            if (str_ends_with($email, '@junia.com')) {
                $role = 'encadrant';
            } elseif (str_ends_with($email, '@student.junia.com')) {
                $role = 'etudiant';
            } else {
                $error = "Utilisez votre adresse @junia.com ou @student.junia.com.";
            }

            if (empty($error)) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "Cet email est déjà utilisé.";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        'INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$first_name, '', $email, $hashedPassword, $role]);
                    $_SESSION['user_id']    = $pdo->lastInsertId();
                    $_SESSION['role']       = $role;
                    $_SESSION['first_name'] = $first_name;
                    header('Location: dashboard.php');
                    exit;
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur SQL : " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Erreur inattendue : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — EduProject</title>
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
            padding: 24px 20px;
        }

        .login-shell {
            width: 100%;
            max-width: 380px;
        }

        .brand {
            text-align: center;
            margin-bottom: 18px;
        }
        .brand-name {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #60606c;
        }

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
            margin-bottom: 18px;
            font-weight: 400;
            line-height: 1.5;
        }

        /* Divider */
        .form-divider {
            height: 1px;
            background: rgba(255,255,255,0.07);
            margin: 14px 0;
        }

        /* Role hint */
        .role-hint {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .role-chip {
            flex: 1;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 10px;
            padding: 8px 10px;
            font-size: 0.75rem;
            color: #9898a4;
            line-height: 1.5;
        }
        .role-chip strong { display: block; color: #eeeef2; font-weight: 600; margin-bottom: 2px; font-size: 0.8rem; }

        /* Alert */
        .alert-error {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            background: rgba(255,69,58,0.1);
            border: 1px solid rgba(255,69,58,0.22);
            border-radius: 10px;
            color: #ff453a;
            font-size: 0.83rem;
            font-weight: 500;
            padding: 11px 14px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        /* Password strength */
        .pw-strength { margin-top: 6px; display: flex; gap: 4px; }
        .pw-bar { flex: 1; height: 3px; border-radius: 2px; background: rgba(255,255,255,0.08); transition: background 300ms; }
        .pw-bar.weak   { background: #ff453a; }
        .pw-bar.medium { background: #ff9f0a; }
        .pw-bar.strong { background: #30d158; }

        /* Form */
        .form-group { margin-bottom: 12px; }

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
        .form-group input.valid:not(:placeholder-shown) {
            border-color: rgba(48,209,88,0.4);
        }

        .form-hint {
            font-size: 0.73rem;
            color: #60606c;
            margin-top: 5px;
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
        <h1 class="auth-title">Créer un compte</h1>
        <p class="auth-subtitle">Utilisez votre adresse Junia pour vous inscrire.</p>

        <div class="role-hint">
            <div class="role-chip">
                <strong>Étudiant</strong>
                @student.junia.com
            </div>
            <div class="role-chip">
                <strong>Encadrant</strong>
                @junia.com
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">✕ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="inscriptionForm">
            <div class="form-group">
                <label>Prénom</label>
                <input type="text" name="first_name" placeholder="Votre prénom" required
                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
            </div>

            <div class="form-divider"></div>

            <div class="form-group">
                <label>Adresse email Junia</label>
                <input type="email" name="email" id="emailInput"
                       placeholder="nom@junia.com" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <p class="form-hint" id="roleHint"></p>
            </div>

            <div class="form-divider"></div>

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" id="pwInput"
                       placeholder="8 caractères minimum" required>
                <div class="pw-strength">
                    <div class="pw-bar" id="bar1"></div>
                    <div class="pw-bar" id="bar2"></div>
                    <div class="pw-bar" id="bar3"></div>
                </div>
            </div>
            <div class="form-group">
                <label>Confirmer le mot de passe</label>
                <input type="password" name="confirm_password" id="pwConfirm"
                       placeholder="••••••••" required>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Créer mon compte</button>
                <a href="login.php" class="btn btn-secondary">Déjà inscrit ? Se connecter</a>
            </div>
        </form>
    </div>

    <div class="auth-footer">EduProject · Projet de fin d'année 3A</div>
</div>

<script>
/* Role hint dynamique */
const emailInput = document.getElementById('emailInput');
const roleHint   = document.getElementById('roleHint');

emailInput.addEventListener('input', () => {
    const val = emailInput.value;
    if (val.endsWith('@junia.com')) {
        roleHint.textContent = '✓ Compte Encadrant';
        roleHint.style.color = '#30d158';
    } else if (val.endsWith('@student.junia.com')) {
        roleHint.textContent = '✓ Compte Étudiant';
        roleHint.style.color = '#30d158';
    } else if (val.includes('@')) {
        roleHint.textContent = '✕ Domaine non autorisé';
        roleHint.style.color = '#ff453a';
    } else {
        roleHint.textContent = '';
    }
});

/* Password strength */
const pwInput = document.getElementById('pwInput');
const bars    = [document.getElementById('bar1'), document.getElementById('bar2'), document.getElementById('bar3')];

pwInput.addEventListener('input', () => {
    const v = pwInput.value;
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v) && /[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    bars.forEach((b, i) => {
        b.className = 'pw-bar';
        if (i < score) b.classList.add(score === 1 ? 'weak' : score === 2 ? 'medium' : 'strong');
    });
});
</script>

</body>
</html>