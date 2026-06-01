<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$taskId = $_GET['task_id'] ?? null;
if (!$taskId) {
    die("Aucune tâche spécifiée.");
}

// 1. Récupérer les informations actuelles de la tâche
$stmtTask = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmtTask->execute([$taskId]);
$task = $stmtTask->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    die("Tâche introuvable.");
}

$projectId = $task['project_id'];

// 2. Récupérer les sous-catégories du projet
$stmtCat = $pdo->prepare("SELECT id, title FROM categories WHERE project_id = ? ORDER BY id ASC");
$stmtCat->execute([$projectId]);
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

// 3. Récupérer les membres du projet pour l'assignation
$stmtMembers = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name
    FROM project_members pm
    JOIN users u ON pm.user_id = u.id
    WHERE pm.project_id = ?
");
$stmtMembers->execute([$projectId]);
$members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

$error = '';

// 4. Traitement de la modification du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $priority = $_POST['priority'];
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $assignedTo = $_POST['assigned_to'] ?? null; 
    $categoryId = $_POST['category_id'] ?? null;
    $status = $_POST['status'] ?? 'à faire';
    $progress = (int)($_POST['progress'] ?? 0);

    // Ajustements automatiques de la cohérence Statut / Progression
    if ($status === 'terminé') {
        $progress = 100;
    } elseif ($status === 'à faire') {
        $progress = 0;
    }

    // Sécurité sur les dates
    if ($startDate && $endDate && $startDate > $endDate) {
        $error = "La date de début doit être antérieure à la date de fin.";
    } else {
        // Mise à jour de la tâche
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET category_id = ?, title = ?, priority = ?, status = ?, progress = ?, start_date = ?, end_date = ?, assigned_to = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $categoryId ?: null,
            $title, 
            $priority, 
            $status,
            $progress,
            $startDate ?: null, 
            $endDate ?: null, 
            $assignedTo ?: null,
            $taskId
        ]);
        
        header("Location: tasks.php?project_id=$projectId");
        exit;
    }
}

include 'includes/header.php';
?>

<h1>Modifier la tâche</h1>

<?php if ($error): ?>
    <div class="alert alert-error" style="color: #e05c5c; background: rgba(224,92,92,.1); padding: 10px; border-radius: 5px; margin-bottom: 15px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST" class="form-container">
    <div class="form-group" style="margin-bottom: 1rem;">
        <label style="display:block; margin-bottom:.5rem;">Titre *</label>
        <input type="text" name="title" value="<?= htmlspecialchars($task['title']) ?>" required style="width:100%; padding:.45rem;">
    </div>

    <div class="form-group" style="margin-bottom: 1rem;">
        <label style="display:block; margin-bottom:.5rem;">Sous-catégorie / Jalon</label>
        <select name="category_id" style="width:100%; padding:.45rem;">
            <option value="">— Aucune (Tâche globale) —</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $task['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                    📁 <?= htmlspecialchars($cat['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" style="margin-bottom: 1rem;">
        <label style="display:block; margin-bottom:.5rem;">Priorité</label>
        <select name="priority" style="width:100%; padding:.45rem;">
            <option value="basse" <?= $task['priority'] === 'basse' ? 'selected' : '' ?>>Basse</option>
            <option value="moyenne" <?= $task['priority'] === 'moyenne' ? 'selected' : '' ?>>Moyenne</option>
            <option value="haute" <?= $task['priority'] === 'haute' ? 'selected' : '' ?>>Haute</option>
        </select>
    </div>

    <div class="form-group" style="margin-bottom: 1rem;">
        <label style="display:block; margin-bottom:.5rem;">Statut actuel</label>
        <select name="status" style="width:100%; padding:.45rem;">
            <option value="à faire" <?= $task['status'] === 'à faire' ? 'selected' : '' ?>>À faire</option>
            <option value="en cours" <?= $task['status'] === 'en cours' ? 'selected' : '' ?>>En cours</option>
            <option value="terminé" <?= $task['status'] === 'terminé' ? 'selected' : '' ?>>Terminé</option>
        </select>
    </div>

    <div class="form-group" style="margin-bottom: 1rem;">
        <label style="display:block; margin-bottom:.5rem;">Progression (%)</label>
        <input type="number" name="progress" min="0" max="100" value="<?= (int)$task['progress'] ?>" style="width:100%; padding:.45rem;">
        <small style="color: #64748b;">Note: Sera forcé à 0% pour "À faire" et 100% pour "Terminé".</small>
    </div>

    <div class="form-group" style="margin-bottom: 1rem;">
        <label style="display:block; margin-bottom:.5rem;">Assigner à</label>
        <select name="assigned_to" style="width:100%; padding:.45rem;">
            <option value="">— Personne —</option>
            <?php foreach ($members as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $task['assigned_to'] == $m['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" style="margin-bottom: 1rem;">
        <label style="display:block; margin-bottom:.5rem;">Date de début *</label>
        <input type="date" name="start_date" required value="<?= htmlspecialchars($task['start_date']) ?>" style="width:100%; padding:.45rem;">
    </div>

    <div class="form-group" style="margin-bottom: 1.5rem;">
        <label style="display:block; margin-bottom:.5rem;">Date de fin *</label>
        <input type="date" name="end_date" required value="<?= htmlspecialchars($task['end_date']) ?>" style="width:100%; padding:.45rem;">
    </div>

    <div style="display: flex; gap: 1rem;">
        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
        <a href="tasks.php?project_id=<?= $projectId ?>" style="padding: .6rem 1.2rem; background: #2a2d3e; color: #fff; border-radius: 6px; text-decoration: none; font-size: .9rem;">Annuler</a>
    </div>
</form>

<?php include 'includes/footer.php'; ?>