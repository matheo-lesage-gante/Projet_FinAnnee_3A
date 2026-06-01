<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Bloque l'accès si l'utilisateur est un élève
requireProf();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = $_POST['group_id'] ?? null;

    if ($group_id) {
        try {
            // Vérifier si le professeur est déjà dans ce groupe
            $stmtCheck = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
            $stmtCheck->execute([$group_id, $_SESSION['user_id']]);
            
            if ($stmtCheck->fetch()) {
                $error = "Vous faites déjà partie de ce groupe.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
                $stmt->execute([$group_id, $_SESSION['user_id']]);
                $success = "Vous avez rejoint ce groupe avec succès !";
            }
        } catch (PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

// Récupération des groupes pour le formulaire
$stmtGroups = $pdo->query("SELECT * FROM groups");
$groups = $stmtGroups->fetchAll();

include 'includes/header.php';
?>

<h1>Rejoindre un groupe</h1>

<?php if($error): ?><p class="error" style="color: red; font-weight: bold;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if($success): ?><p class="success" style="color: green; font-weight: bold;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

<form method="POST" class="form-container">
    <div class="form-group">
        <label>Choisir un groupe :</label>
        <select name="group_id" required>
            <option value="">-- Sélectionner un groupe --</option>
            <?php foreach($groups as $g): ?>
                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <button type="submit" class="btn btn-primary" style="margin-top: 15px;">Rejoindre le groupe</button>
</form>

<?php include 'includes/footer.php'; ?>