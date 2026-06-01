<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$projectId = $_GET['project_id'] ?? null;
if(!$projectId) die("Sélectionnez un projet d'abord.");

$stmt = $pdo->prepare("SELECT t.*, u.first_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE project_id = ?");
$stmt->execute([$projectId]);
$tasks = $stmt->fetchAll();

$kanban = ['à faire' => [], 'en cours' => [], 'terminé' => []];
foreach($tasks as $t) {
    // AJUSTEMENT : On gère un fallback si le statut est vide suite à un ajout depuis Gantt
    $status = $t['status'] ?: 'à faire';
    if (array_key_exists($status, $kanban)) {
        $kanban[$status][] = $t;
    }
}

include 'includes/header.php';
?>
<div class="flex-between">
    <h1>Kanban des Tâches</h1>
    <?php if(hasRole(['team_leader', 'supervisor'])): ?>
        <a href="task_create.php?project_id=<?= $projectId ?>" class="btn btn-primary">+ Nouvelle Tâche</a>
    <?php endif; ?>
</div>

<div class="kanban-board">
    <?php foreach($kanban as $status => $list): ?>
    <div class="kanban-column">
        <h3><?= ucfirst($status) ?> (<?= count($list) ?>)</h3>
        <?php foreach($list as $task): ?>
        <div class="kanban-card">
            <h4><?= htmlspecialchars($task['title']) ?></h4>
            <p>Priorité : <?= htmlspecialchars($task['priority'] ?? 'Non définie') ?></p>
            <p>Assigné à : <?= htmlspecialchars($task['first_name'] ?? 'Non assigné') ?></p>
            <?php if($task['start_date'] && $task['end_date']): ?>
                <small style="color:#64748b;">📅 <?= date('d/m', strtotime($task['start_date'])) ?> au <?= date('d/m', strtotime($task['end_date'])) ?></small>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>