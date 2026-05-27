<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();
requireRole('team_leader');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $end = $_POST['end_date'];

    $stmt = $pdo->prepare("INSERT INTO projects (title, description, end_date, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $desc, $end, $_SESSION['user_id']]);
    $projectId = $pdo->lastInsertId();

    $stmt2 = $pdo->prepare("INSERT INTO project_members (project_id, user_id, role_in_project) VALUES (?, ?, 'leader')");
    $stmt2->execute([$projectId, $_SESSION['user_id']]);

    header('Location: projects.php');
    exit;
}
include 'includes/header.php';
?>
<h1>Créer un projet</h1>
<form method="POST" class="form-container">
    <label>Titre :</label> <input type="text" name="title" required>
    <label>Description :</label> <textarea name="description" required></textarea>
    <label>Date de fin :</label> <input type="date" name="end_date" required>
    <button type="submit" class="btn btn-primary">Enregistrer</button>
</form>
<?php include 'includes/footer.php'; ?>