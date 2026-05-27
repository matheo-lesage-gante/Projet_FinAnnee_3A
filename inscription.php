<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm_password) {

        $error = "Les mots de passe ne correspondent pas.";

    } else {

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $error = "Cet email est déjà utilisé.";

        } else {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $role = 'student';
            $category = 'student';

            $stmt = $pdo->prepare(
                'INSERT INTO users (first_name, email, password, role)
                 VALUES (?, ?, ?, ?)'
            );

            $stmt->execute([
                $first_name,
                $email,
                $hashedPassword,
                $role,
            ]);

            $success = "Compte créé avec succès !";

            header("Refresh:2; url=login.php");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">

<div class="login-card">

    <h2>Inscription</h2>

    <?php if($error): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <?php if($success): ?>
        <p class="success"><?= $success ?></p>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label>Prénom</label>
            <input type="text" name="first_name" required>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-group">
            <label>Confirmer mot de passe</label>
            <input type="password" name="confirm_password" required>
        </div>

        <button type="submit" class="btn btn-primary">
            S'inscrire
        </button>

        <a href="login.php" class="btn btn-secondary">
            Retour connexion
        </a>

    </form>

</div>

</body>
</html>