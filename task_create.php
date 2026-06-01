<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();
// Le requireRole restrictif a été retiré ici pour autoriser les élèves

$projectId = $_GET['project_id'] ?? null;
if (!$projectId) {
    die("Sélectionnez un projet d'abord.");
}

// --- NOUVEAU : Récupération des sous-catégories du projet ---
$stmtCat = $pdo->prepare("SELECT id, title FROM categories WHERE project_id = ? ORDER BY id ASC");
$stmtCat->execute([$projectId]);
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

// --- Récupération des membres du projet pour le select ---
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name
    FROM project_members pm
    JOIN users u ON pm.user_id = u.id
    WHERE pm.project_id = ?
");
$stmt->execute([$projectId]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $priority = $_POST['priority'];
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $assignedTo = $_POST['assigned_to'] ?? null; 
    $categoryId = $_POST['category_id'] ?? null; // Récupération de la sous-catégorie

    // Sécurité sur les dates
    if ($startDate && $endDate && $startDate > $endDate) {
        $error = "La date de début doit être antérieure à la date de fin.";
    } else {
        // Insertion en base de données avec le champ assigned_to et category_id (on met NULL si vide)
        $stmt = $pdo->prepare("
            INSERT INTO tasks (project_id, category_id, title, priority, created_by, status, start_date, end_date, assigned_to) 
            VALUES (?, ?, ?, ?, ?, 'à faire', ?, ?, ?)
        ");
        $stmt->execute([
            $projectId, 
            $categoryId ?: null, // Enregistre l'ID de la sous-catégorie ou NULL
            $title, 
            $priority, 
            $_SESSION['user_id'], 
            $startDate ?: null, 
            $endDate ?: null, 
            $assignedTo ?: null
        ]);
        
        header("Location: tasks.php?project_id=$projectId");
        exit;
    }
}
include 'includes/header.php';
?>

<h1>Ajouter une tâche</h1>

<?php if ($error): ?>
    <div class="alert alert-error" style="color: #e05c5c; background: rgba(224,92,92,.1); padding: 10px; border-radius: 5px; margin-bottom: 15px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST" class="form-container">
    <div class="form-group" style="margin-bottom: 1rem;">
        <label style="display:block; margin-bottom:.5rem;">Titre *</label>
        <input type="text" name="title" required style="width:100%; padding:.45rem;">
    </div>

    <div class="form-group" style="margin-bottom: 1rem;">
        <label style="display:block; margin-bottom:.5rem;">Sous-catégorie / Jalon</label>
        <select name="category_id" style="width:100%; padding:.45rem;">
            <option value="">— Aucune (Tâche globale) —</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>">
                    📁 <?= htmlspecialchars($cat['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" style="margin-bottom: 1rem;">
        <label style="display:block; margin-bottom:.5rem;">Priorité</label>
        <select name="priority" style="width:100%; padding:.45rem;">
            <option value="basse">Basse</option>
            <option value="moyenne" selected>Moyenne</option>
            <option value="haute">Haute</option>
        </select>
    </div>

    <div class="form-group" style="margin-bottom: 1rem;">
        <label style="display:block; margin-bottom:.5rem;">Assigner à</label>
        <select name="assigned_to" style="width:100%; padding:.45rem;">
            <option value="">— Personne —</option>
            <?php foreach ($members as $m): ?>
                <option value="<?= $m['id'] ?>">
                    <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" style="margin-bottom: 1rem;">
        <label style="display:block; margin-bottom:.5rem;">Date de début *</label>
        <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>" style="width:100%; padding:.45rem;">
    </div>

    <div class="form-group" style="margin-bottom: 1.5rem;">
        <label style="display:block; margin-bottom:.5rem;">Date de fin *</label>
        <input type="date" name="end_date" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>" style="width:100%; padding:.45rem;">
    </div>

    <button type="submit" class="btn btn-primary">Créer et assigner</button>
</form>

<?php include 'includes/footer.php'; ?>