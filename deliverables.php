<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$stmt = $pdo->query("SELECT d.*, p.title as project_title, u.first_name FROM deliverables d JOIN projects p ON d.project_id = p.id JOIN users u ON d.uploaded_by = u.id");
$deliverables = $stmt->fetchAll();

include 'includes/header.php';
?>
<div class="flex-between">
    <h1>Livrables</h1>
    <?php if(hasRole(['student', 'team_leader'])): ?>
    <a href="deliverable_upload.php" class="btn btn-primary">+ Déposer un livrable</a>
    <?php endif; ?>
</div>

<table class="data-table">
    <thead><tr><th>Fichier</th><th>Projet</th><th>Déposé par</th><th>Statut</th></tr></thead>
    <tbody>
        <?php foreach($deliverables as $d): ?>
        <tr>
            <td><a href="<?= htmlspecialchars($d['file_path']) ?>" target="_blank"><?= htmlspecialchars($d['file_name']) ?></a></td>
            <td><?= htmlspecialchars($d['project_title']) ?></td>
            <td><?= htmlspecialchars($d['first_name']) ?></td>
            <td><span class="badge"><?= $d['status'] ?></span></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php include 'includes/footer.php'; ?>