<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

if (hasRole(['student', 'team_leader'])) {
    $stmt = $pdo->prepare("SELECT p.* FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE pm.user_id = ?");
    $stmt->execute([$userId]);
} else {
    $stmt = $pdo->query("SELECT * FROM projects");
}
$projects = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="flex-between">
    <h1>Liste des Projets</h1>
    <?php if(hasRole('team_leader')): ?>
        <a href="project_create.php" class="btn btn-primary">+ Nouveau projet</a>
    <?php endif; ?>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Titre</th>
            <th>Statut</th>
            <th>Date de fin</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($projects as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['title']) ?></td>
            <td><span class="badge status-<?= str_replace(' ', '-', $p['status']) ?>"><?= $p['status'] ?></span></td>
            <td><?= $p['end_date'] ?></td>
            <td><a href="project_detail.php?id=<?= $p['id'] ?>" class="btn btn-small">Détails</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include 'includes/footer.php'; ?>