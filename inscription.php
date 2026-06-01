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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $first_name = trim($_POST['first_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($password !== $confirm_password) {
            $error = "Les mots de passe ne correspondent pas.";
        } else {
            
            // 🎯 L'ASTUCE FINALE : On utilise les mots exacts de ta base de données
            $role = '';
            if (str_ends_with($email, '@junia.com')) {
                $role = 'encadrant'; // 'encadrant' correspond au Professeur dans ta BDD
            } elseif (str_ends_with($email, '@student.junia.com')) {
                $role = 'etudiant';  // 'etudiant' correspond à l'Élève dans ta BDD
            } else {
                $error = "Inscription refusée. Vous devez utiliser votre adresse @junia.com (Professeur) ou @student.junia.com (Élève).";
            }

            if (empty($error)) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    $error = "Cet email est déjà utilisé.";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $last_name = ''; 

                    $stmt = $pdo->prepare(
                        'INSERT INTO users (first_name, last_name, email, password, role)
                         VALUES (?, ?, ?, ?, ?)'
                    );

                    $stmt->execute([
                        $first_name,
                        $last_name,
                        $email,
                        $hashedPassword,
                        $role,
                    ]);

                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    $_SESSION['role'] = $role;
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
    <title>Inscription</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">

<div class="login-card">
    <h2>Inscription</h2>
    <?php if($error): ?>
        <p class="error" style="color: red; font-weight: bold; margin-bottom: 15px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>Prénom</label>
            <input type="text" name="first_name" required>
        </div>
        <div class="form-group">
            <label>Email (Junia)</label>
            <input type="email" name="email" placeholder="nom@junia.com ou nom@student.junia.com" required>
        </div>
        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-group">
            <label>Confirmer mot de passe</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-primary">S'inscrire</button>
        <a href="login.php" class="btn btn-secondary">Retour connexion</a>
    </form>
</div>
</body>
</html>