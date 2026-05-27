<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
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
    <title>Connexion</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="login-page">

<div class="login-card">

    <h2>Connexion</h2>

    <?php if($error): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label>Email</label>

            <input type="email"
                   name="email"
                   required>
        </div>

        <div class="form-group">
            <label>Mot de passe</label>

            <input type="password"
                   name="password"
                   required>
        </div>

        <button type="submit" class="btn btn-primary">
            Se connecter
        </button>

        <a href="inscription.php" class="btn btn-secondary">
            S'inscrire
        </a>

    </form>

</div>

</body>
</html>