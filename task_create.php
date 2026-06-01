<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();
requireRole(['team_leader', 'supervisor']);

$projectId = $_GET['project_id'] ?? null;
if (!$projectId) {
    die("Sélectionnez un projet d'abord.");
}

// --- NOUVEAU : Récupération des membres du projet pour le select ---
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
    $assignedTo = $_POST['assigned_to'] ?? null; // Récupération de l'ID de la personne assignée

    // Sécurité sur les dates
    if ($startDate && $endDate && $startDate > $endDate) {
        $error = "La date de début doit être antérieure à la date de fin.";
    } else {
        // Insertion en base de données avec le champ assigned_to (on met NULL si personne n'est choisi)
        $stmt = $pdo->prepare("
            INSERT INTO tasks (project_id, title, priority, created_by, status, start_date, end_date, assigned_to) 
            VALUES (?, ?, ?, ?, 'à faire', ?, ?, ?)
        ");
        $stmt->execute([
            $projectId, 
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
        <label style="display:block; margin-bottom:.5rem;">Priorité</label>
        <select name="priority" style="width:100%; padding:.45rem;">
            <option value="basse">Basse</option>
            <option value="moyenne" selected>Moyenne</option>
            <option value="haute">Haute</option>
        </select>
    </div>

    <!-- NOUVEAU : Sélection du membre assigné -->
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