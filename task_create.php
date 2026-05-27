<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();
requireRole(['team_leader', 'supervisor']);

$projectId = $_GET['project_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $priority = $_POST['priority'];
    $stmt = $pdo->prepare("INSERT INTO tasks (project_id, title, priority, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$projectId, $title, $priority, $_SESSION['user_id']]);
    header("Location: tasks.php?project_id=$projectId");
    exit;
}
include 'includes/header.php';
?>
<h1>Ajouter une tâche</h1>
<form method="POST" class="form-container">
    <label>Titre</label><input type="text" name="title" required>
    <label>Priorité</label>
    <select name="priority">
        <option value="basse">Basse</option>
        <option value="moyenne">Moyenne</option>
        <option value="haute">Haute</option>
    </select>
    <button type="submit" class="btn btn-primary">Créer</button>
</form>
<?php include 'includes/footer.php'; ?>