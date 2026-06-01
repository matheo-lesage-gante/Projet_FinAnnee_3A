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

<div class="kanban-board" style="display: flex; gap: 1rem; align-items: flex-start; margin-top: 1rem;">
    <?php foreach($kanban as $status => $list): ?>
    <div class="kanban-column" style="flex: 1; background: #1a1d27; border: 1px solid #2a2d3e; border-radius: 10px; padding: 1rem; min-height: 500px;">
        <h3 style="color: #e2e8f0; margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem; border-bottom: 2px solid #2a2d3e; padding-bottom: .5rem;">
            <?= ucfirst($status) ?> (<?= count($list) ?>)
        </h3>
        
        <?php foreach($list as $task): ?>
        <?php 
            // Calcul de la couleur HSL identique au Gantt pour la bordure ou le badge du Kanban
            $prog = max(0, min(100, (int)($task['progress'] ?? 0)));
            $hue = ($prog / 100) * 120;
            $dynamicColor = "hsl($hue, 75%, 45%)";
        ?>
        <div class="kanban-card" style="background: #0f1117; border: 1px solid #2a2d3e; border-left: 4px solid <?= $dynamicColor ?>; border-radius: 6px; padding: .85rem; margin-bottom: .75rem; color: #e2e8f0; position: relative;">
            <span style="position: absolute; top: .85rem; right: .85rem; font-size: .7rem; background: <?= $dynamicColor ?>20; color: <?= $dynamicColor ?>; font-weight: bold; padding: 2px 6px; border-radius: 10px;">
                <?= $prog ?>%
            </span>
            <h4 style="margin: 0 3rem 0 0; font-size: .95rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($task['title']) ?></h4>
            <p style="margin: .5rem 0 .25rem 0; font-size: .8rem; color: #64748b;">Priorité : <span style="color: #e2e8f0;"><?= htmlspecialchars($task['priority'] ?? 'Non définie') ?></span></p>
            <p style="margin: 0 0 .5rem 0; font-size: .8rem; color: #64748b;">Assigné à : <span style="color: #e2e8f0;"><?= htmlspecialchars($task['first_name'] ?? 'Non assigné') ?></span></p>
            
            <?php if($task['start_date'] && $task['end_date']): ?>
                <small style="color:#64748b; font-size: .75rem; display: block; margin-top: .5rem; border-top: 1px solid #2a2d3e; padding-top: .4rem;">
                    📅 <?= date('d/m', strtotime($task['start_date'])) ?> au <?= date('d/m', strtotime($task['end_date'])) ?>
                </small>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>