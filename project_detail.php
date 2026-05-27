<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$projectId = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if(!$project) die("Projet introuvable.");

include 'includes/header.php';
?>
<h1><?= htmlspecialchars($project['title']) ?></h1>
<p><span class="badge status-<?= str_replace(' ', '-', $project['status']) ?>"><?= $project['status'] ?></span></p>
<div class="card">
    <h3>Description</h3>
    <p><?= nl2br(htmlspecialchars($project['description'])) ?></p>
    <p><strong>Échéance :</strong> <?= $project['end_date'] ?></p>
</div>
<a href="tasks.php?project_id=<?= $project['id'] ?>" class="btn btn-secondary">Voir les tâches Kanban</a>
<?php include 'includes/footer.php'; ?>