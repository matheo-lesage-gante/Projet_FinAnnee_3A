<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Bloque l'accès si l'utilisateur est un élève
requireProf();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error = "Le nom du groupe est obligatoire.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO groups (name, description, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $_SESSION['user_id']]);
            $success = "Groupe créé avec succès !";
        } catch (PDOException $e) {
            $error = "Erreur lors de la création : " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<h1>Créer un groupe</h1>

<?php if($error): ?><p class="error" style="color: red; font-weight: bold;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if($success): ?><p class="success" style="color: green; font-weight: bold;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

<form method="POST" class="form-container">
    <div class="form-group">
        <label>Nom du groupe :</label>
        <input type="text" name="name" required>
    </div>
    
    <div class="form-group">
        <label>Description :</label>
        <textarea name="description" rows="4"></textarea>
    </div>
    
    <button type="submit" class="btn btn-primary" style="margin-top: 15px;">Créer le groupe</button>
</form>

<?php include 'includes/footer.php'; ?>