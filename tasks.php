<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$projectId = $_GET['project_id'] ?? null;
if (!$projectId) die("Sélectionnez un projet d'abord.");

// --- ACTION : Créer une sous-catégorie ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_category'])) {
    $catTitle = trim($_POST['category_title'] ?? '');
    if (!empty($catTitle)) {
        $stmt = $pdo->prepare("INSERT INTO categories (project_id, title) VALUES (?, ?)");
        $stmt->execute([$projectId, $catTitle]);
    }
    header("Location: tasks.php?project_id=" . $projectId);
    exit;
}

// --- TRAITEMENT AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax_update'])) {
    header('Content-Type: application/json; charset=utf-8');
    $taskId    = (int)($_POST['task_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $allowedStatuses = ['à faire', 'en cours', 'terminé'];

    if ($taskId && in_array($newStatus, $allowedStatuses)) {
        $progress = 0;
        if ($newStatus === 'terminé') $progress = 100;
        elseif ($newStatus === 'en cours') {
            $progress = isset($_POST['progress']) ? max(0, min(100, (int)$_POST['progress'])) : 50;
        }
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, progress = ? WHERE id = ? AND project_id = ?");
        $success = $stmt->execute([$newStatus, $progress, $taskId, $projectId]);
        echo json_encode(['success' => $success, 'new_progress' => $progress]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Données invalides.']);
    exit;
}

// --- CHARGEMENT ---
$stmt = $pdo->prepare("SELECT * FROM categories WHERE project_id = ? ORDER BY id ASC");
$stmt->execute([$projectId]);
$categories = $stmt->fetchAll(PDO::FETCH_UNIQUE);
$categories[0] = ['id' => 0, 'project_id' => $projectId, 'title' => 'Tâches globales'];

$stmtProj = $pdo->prepare("SELECT title FROM projects WHERE id = ?");
$stmtProj->execute([$projectId]);
$project = $stmtProj->fetch();

$stmt = $pdo->prepare("SELECT t.*, u.first_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.project_id = ?");
$stmt->execute([$projectId]);
$tasks = $stmt->fetchAll();

// --- CALCULS PONDÉRÉS ---
$coefficients = ['haute' => 2.0, 'moyenne' => 1.5, 'basse' => 1.0];
$catCalculations = [];
foreach ($categories as $catId => $cat) {
    $catCalculations[$catId] = ['sum_weighted' => 0, 'sum_coef' => 0, 'progress' => 0];
}
foreach ($tasks as $t) {
    $catId = (int)($t['category_id'] ?? 0);
    if (!isset($catCalculations[$catId])) $catId = 0;
    $coef = $coefficients[mb_strtolower(trim($t['priority'] ?? 'basse'))] ?? 1.0;
    $taskProgress = max(0, min(100, (int)($t['progress'] ?? 0)));
    $catCalculations[$catId]['sum_weighted'] += ($taskProgress * $coef);
    $catCalculations[$catId]['sum_coef'] += $coef;
}
$projectWeightedSum = 0;
$projectTotalWeights = 0;
foreach ($catCalculations as $catId => $data) {
    if ($data['sum_coef'] > 0) {
        $catCalculations[$catId]['progress'] = round($data['sum_weighted'] / $data['sum_coef']);
        $projectWeightedSum += ($catCalculations[$catId]['progress'] * $data['sum_coef']);
        $projectTotalWeights += $data['sum_coef'];
    }
}
$projectProgress = $projectTotalWeights > 0 ? round($projectWeightedSum / $projectTotalWeights) : 0;

// --- FILTRE ---
$currentCatFilter = isset($_GET['filter_category']) ? (int)$_GET['filter_category'] : null;
$kanban = ['à faire' => [], 'en cours' => [], 'terminé' => []];
foreach ($tasks as $t) {
    $catId = (int)($t['category_id'] ?? 0);
    if ($currentCatFilter !== null && $catId !== $currentCatFilter) continue;
    $status = $t['status'] ?: 'à faire';
    if (array_key_exists($status, $kanban)) $kanban[$status][] = $t;
}

include 'includes/header.php';
?>

<style>
.tk-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.tk-header h1 {
    margin: 0 0 1px;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-card);
}
.tk-header p {
    margin: 0;
    font-size: 0.72rem;
    color: var(--text-muted);
}
.tk-progress-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-top: 8px;
    border-top: 1px solid var(--border-color);
    margin-top: 2px;
}
.tk-progress-label {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    white-space: nowrap;
}
.tk-progress-bar-wrap {
    flex: 1;
    background: var(--input-bg);
    height: 4px;
    border-radius: 4px;
    overflow: hidden;
}
.tk-pct {
    font-size: 0.78rem;
    font-weight: 700;
    min-width: 34px;
    text-align: right;
}

.tk-jalons {
    margin-bottom: 15px;
}
.tk-jalons-title {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--text-page);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    margin: 0 0 6px;
    padding: 0 4px;
}
.tk-jalons-list {
    display: flex;
    flex-direction: column;
    gap: 1px;
    background: var(--border-color);
    border-radius: var(--radius-sm);
    overflow: hidden;
    border: 1px solid var(--border-color);
}
.tk-jalon-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--card-bg);
    text-decoration: none;
    transition: background 0.12s;
}
.tk-jalon-row:hover { background: var(--input-bg); }
.tk-jalon-row.active { background: var(--input-bg); }
.tk-jalon-name {
    flex: 1;
    font-size: 0.85rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.tk-jalon-bar {
    width: 60px;
    background: var(--input-bg);
    height: 4px;
    border-radius: 3px;
    overflow: hidden;
    flex-shrink: 0;
}
.tk-jalon-pct {
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 28px;
    text-align: right;
}
</style>

<?php
$columnStyles = [
    'à faire'  => ['accent' => 'var(--text-muted)', 'label' => 'À faire'],
    'en cours' => ['accent' => 'var(--accent)',     'label' => 'En cours'],
    'terminé'  => ['accent' => 'var(--success)',    'label' => 'Terminé'],
];
?>

<div style="display:flex; align-items:center; gap:8px; color:var(--text-page); font-size:0.85rem; margin-bottom:24px; font-weight: 500;">
    <a href="projects.php" style="color:var(--text-page); text-decoration:none;">Projets</a>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
    <span><?= htmlspecialchars($project['title'] ?? 'Kanban') ?></span>
</div>

<div class="card" style="margin-bottom:20px; padding:20px;">
    <div class="tk-header">
        <div>
            <h1><?= htmlspecialchars($project['title'] ?? '') ?></h1>
            <p>Avancement pondéré par priorité</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <form method="POST" style="display:flex; align-items:center; background:var(--input-bg); border:1px solid var(--border-color); border-radius:var(--radius-sm); overflow:hidden;">
                <input type="text" name="category_title" placeholder="Nouveau jalon..." required
                    style="background:transparent; border:none; color:var(--text-card); padding:8px 12px; outline:none; font-size:0.85rem; min-width:150px;">
                <button type="submit" name="action_create_category" class="btn btn-secondary" style="border-radius:0; padding: 8px 15px;">+</button>
            </form>
            <a href="task_create.php?project_id=<?= $projectId ?>" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:5px; white-space:nowrap; padding:9px 15px; font-size:0.85rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nouvelle tâche
            </a>
        </div>
    </div>
    <?php $progColor = $projectProgress == 100 ? 'var(--success)' : ($projectProgress > 0 ? 'var(--accent)' : 'var(--text-muted)'); ?>
    <div class="tk-progress-row">
        <span class="tk-progress-label">Avancement global</span>
        <div class="tk-progress-bar-wrap">
            <div style="width:<?= $projectProgress ?>%; height:100%; background:<?= $progColor ?>; border-radius:4px; transition:width 0.5s ease;"></div>
        </div>
        <span class="tk-pct" style="color:<?= $progColor ?>;"><?= $projectProgress ?>%</span>
    </div>
</div>

<div class="tk-jalons">
    <p class="tk-jalons-title">Jalons</p>
    <div class="tk-jalons-list">
        <a href="tasks.php?project_id=<?= $projectId ?>" class="tk-jalon-row <?= $currentCatFilter === null ? 'active' : '' ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="<?= $currentCatFilter === null ? 'var(--text-card)' : 'var(--text-muted)' ?>" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span class="tk-jalon-name" style="color:<?= $currentCatFilter === null ? 'var(--text-card)' : 'var(--text-muted)' ?>; font-weight:<?= $currentCatFilter === null ? '600' : '400' ?>;">Toutes les catégories</span>
        </a>
        <?php foreach ($categories as $catId => $cat):
            if ($catId === 0 && $catCalculations[0]['sum_coef'] == 0) continue;
            $catProg = $catCalculations[$catId]['progress'];
            $isFiltered = ($currentCatFilter === $catId);
            $catBarColor = $catProg == 100 ? 'var(--success)' : ($catProg > 0 ? 'var(--accent)' : 'var(--text-muted)');
        ?>
        <a href="tasks.php?project_id=<?= $projectId ?>&filter_category=<?= $catId ?>" class="tk-jalon-row <?= $isFiltered ? 'active' : '' ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="<?= $isFiltered ? 'var(--accent)' : 'var(--text-muted)' ?>" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <span class="tk-jalon-name" style="color:<?= $isFiltered ? 'var(--text-card)' : 'var(--text-muted)' ?>; font-weight:<?= $isFiltered ? '600' : '400' ?>;"><?= htmlspecialchars($cat['title']) ?></span>
            <div class="tk-jalon-bar">
                <div style="width:<?= $catProg ?>%; height:100%; background:<?= $catBarColor ?>; border-radius:3px;"></div>
            </div>
            <span class="tk-jalon-pct" style="color:<?= $isFiltered ? 'var(--accent)' : 'var(--text-muted)' ?>;"><?= $catProg ?>%</span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<div style="display:flex; align-items:center; gap:10px; margin-bottom:12px; margin-top:24px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-page)" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    <h2 style="margin:0; font-size:1rem; color:var(--text-page); font-weight:600;">
        <?= $currentCatFilter !== null ? htmlspecialchars($categories[$currentCatFilter]['title']) : 'Toutes les catégories' ?>
    </h2>
</div>

<div class="kanban-board" style="display:grid; grid-template-columns:repeat(3,1fr); gap:15px; align-items:start; user-select:none;">
    <?php foreach ($kanban as $status => $list):
        $cs = $columnStyles[$status];
    ?>
    <div class="kanban-column"
         data-status="<?= htmlspecialchars($status) ?>"
         ondragover="allowDrop(event)"
         ondragenter="highlightColumn(this)"
         ondragleave="unhighlightColumn(this)"
         ondrop="dropTask(event, this)"
         style="padding:16px; min-height:300px; display:flex; flex-direction:column; transition:background 0.2s, border-color 0.2s;">

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; pointer-events:none;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="width:10px; height:10px; border-radius:50%; background:<?= $cs['accent'] ?>; display:inline-block; flex-shrink:0;"></span>
                <span style="font-size:0.9rem; font-weight:600; color:var(--text-card); letter-spacing:0.02em;"><?= $cs['label'] ?></span>
            </div>
            <span class="task-count" style="background:var(--input-bg); color:var(--text-muted); font-size:0.8rem; font-weight:700; padding:4px 12px; border-radius:12px; min-width:30px; text-align:center;"><?= count($list) ?></span>
        </div>

        <div style="height:2px; background:<?= $cs['accent'] ?>; border-radius:2px; margin-bottom:15px; pointer-events:none;"></div>

        <div class="cards-container" style="flex:1; display:flex; flex-direction:column; gap:10px; pointer-events:none;">
            <?php foreach ($list as $task):
                $prog = max(0, min(100, (int)($task['progress'] ?? 0)));
                $dynamicColor = $prog == 100 ? 'var(--success)' : ($prog > 0 ? 'var(--accent)' : 'var(--text-muted)');
                $taskCatTitle = $categories[(int)($task['category_id'] ?? 0)]['title'] ?? 'Globale';

                // Couleur priorité
                $prioColors = ['haute' => 'var(--danger)', 'moyenne' => 'var(--warning)', 'basse' => 'var(--success)'];
                $prioColor = $prioColors[strtolower($task['priority'] ?? 'basse')] ?? 'var(--text-muted)';
                $prioBgColor = 'var(--bg-page)';

                $isLate = $task['end_date'] && $task['end_date'] < date('Y-m-d') && $status !== 'terminé';
            ?>
            <div class="kanban-card"
                 id="task-<?= $task['id'] ?>"
                 data-task-id="<?= $task['id'] ?>"
                 data-current-progress="<?= $prog ?>"
                 draggable="true"
                 ondragstart="dragStart(event)"
                 ondragend="dragEnd(event)"
                 style="border-left: 3px solid <?= $prioColor ?> !important; padding:15px; cursor:grab; pointer-events:auto; display:flex; flex-direction:column; gap:10px; transition:transform 0.15s, box-shadow 0.15s;"
                 onmouseover="this.style.transform='translateY(-2px)'"
                 onmouseout="this.style.transform='none'">

                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                    <h4 style="margin:0; font-size:0.95rem; font-weight:600; line-height:1.3; flex:1;"><?= htmlspecialchars($task['title']) ?></h4>
                    <a href="task_edit.php?task_id=<?= $task['id'] ?>" style="color:var(--text-muted); text-decoration:none; flex-shrink:0;" title="Modifier">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                    <?php if ($currentCatFilter === null): ?>
                    <span style="display:inline-flex; align-items:center; gap:4px; font-size:0.75rem; background:var(--card-bg); color:var(--text-muted); border: 1px solid var(--border-color); padding:3px 8px; border-radius:5px;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        <?= htmlspecialchars($taskCatTitle) ?>
                    </span>
                    <?php endif; ?>
                    <span style="display:inline-block; font-size:0.75rem; border: 1px solid <?= $prioColor ?>; color:<?= $prioColor ?>; padding:3px 8px; border-radius:5px; font-weight:600;">
                        <?= ucfirst($task['priority'] ?? 'basse') ?>
                    </span>
                    <?php if ($isLate): ?>
                    <span style="display:inline-block; font-size:0.75rem; background:rgba(255,69,58,0.15); color:var(--danger); padding:3px 8px; border-radius:5px; font-weight:700;">Retard</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($task['first_name'])): ?>
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="width:24px; height:24px; border-radius:50%; background:var(--card-bg); border:1px solid var(--border-color); display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:700; flex-shrink:0; color:var(--text-card);">
                        <?= strtoupper(substr($task['first_name'], 0, 1)) ?>
                    </div>
                    <span style="font-size:0.85rem; color:var(--text-muted);"><?= htmlspecialchars($task['first_name']) ?></span>
                </div>
                <?php endif; ?>

                <div style="display:flex; justify-content:space-between; align-items:center; padding-top:10px; border-top:1px solid var(--border-color); margin-top:0;">
                    <span style="font-size:0.75rem; color:var(--text-muted); display:flex; align-items:center; gap:5px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?php if ($task['start_date'] && $task['end_date']): ?>
                            <?= date('d/m', strtotime($task['start_date'])) ?> → <?= date('d/m', strtotime($task['end_date'])) ?>
                        <?php else: ?>
                            Pas de date
                        <?php endif; ?>
                    </span>
                    <span class="progress-badge" style="font-size:0.75rem; font-weight:700; color:<?= $dynamicColor ?>; padding:2px 8px; border:1px solid <?= $dynamicColor ?>; border-radius:10px;">
                        <?= $prog ?>%
                    </span>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div id="progress-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:9999; align-items:center; justify-content:center; user-select:none; touch-action:none;">
    <div class="card" style="width:100%; max-width:380px; position:relative; background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <h3 style="margin:0 0 10px; color: var(--text-card);">Avancement</h3>
        <p style="color:var(--text-muted); font-size:0.9rem; margin:0 0 20px;">Indiquez le pourcentage d'avancement.</p>
        
        <input type="range" id="progress-slider" min="0" max="100" value="50" 
               style="width:100%; margin-bottom:15px; cursor: pointer; accent-color: var(--accent);">
               
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
            <span style="color:var(--text-muted); font-size:0.85rem;">0%</span>
            <span id="progress-val" style="font-size:1.4rem; font-weight:700; color:var(--accent);">50%</span>
            <span style="color:var(--text-muted); font-size:0.85rem;">100%</span>
        </div>
        <div style="display:flex; gap:10px;">
            <button onclick="cancelDrop()" class="btn btn-secondary" style="flex:1;">Annuler</button>
            <button onclick="confirmDrop()" class="btn btn-primary" style="flex:1;">Confirmer</button>
        </div>
    </div>
</div>

<script>
let pendingDrop = null;

function dragStart(e) {
    e.dataTransfer.setData("text/plain", e.target.id);
    e.target.style.opacity = "0.4";
    e.target.style.cursor = "grabbing";
}

function dragEnd(e) {
    e.target.style.opacity = "1";
    e.target.style.cursor = "grab";
    document.querySelectorAll('.kanban-column').forEach(unhighlightColumn);
}

function allowDrop(e) { e.preventDefault(); }

function highlightColumn(col) {
    col.style.borderColor = "var(--text-muted)";
}

function unhighlightColumn(col) {
    col.style.borderColor = "var(--border-color)";
}

function updateCounts() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        const count = col.querySelectorAll('.kanban-card').length;
        col.querySelector('.task-count').textContent = count;
    });
}

function dropTask(e, column) {
    e.preventDefault();
    unhighlightColumn(column);
    const cardId = e.dataTransfer.getData("text/plain");
    const card = document.getElementById(cardId);
    if (!card) return;
    
    const taskId = card.getAttribute('data-task-id');
    const newStatus = column.getAttribute('data-status');
    const currentProg = parseInt(card.getAttribute('data-current-progress')) || 0;

    if (newStatus === 'en cours') {
        pendingDrop = { card, column, taskId, newStatus };
        const slider = document.getElementById('progress-slider');
        slider.value = currentProg;
        document.getElementById('progress-val').textContent = currentProg + '%';
        document.getElementById('progress-modal').style.display = 'flex';
        return;
    }
    executeMove(card, column, taskId, newStatus, null);
}

// Mise à jour ultra-fluide du texte du slider
const slider = document.getElementById('progress-slider');
const progressVal = document.getElementById('progress-val');

slider.addEventListener('input', function(e) {
    progressVal.textContent = this.value + '%';
});

// Empêcher la propagation du drag sur le slider pour éviter les bugs
slider.addEventListener('mousedown', function(e) {
    e.stopPropagation();
});

function confirmDrop() {
    if (!pendingDrop) return;
    const progress = parseInt(document.getElementById('progress-slider').value);
    document.getElementById('progress-modal').style.display = 'none';
    executeMove(pendingDrop.card, pendingDrop.column, pendingDrop.taskId, pendingDrop.newStatus, progress);
    pendingDrop = null;
}

function cancelDrop() {
    document.getElementById('progress-modal').style.display = 'none';
    pendingDrop = null;
}

function executeMove(card, column, taskId, newStatus, progress) {
    const container = column.querySelector('.cards-container');
    container.appendChild(card);
    updateCounts();

    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('status', newStatus);
    if (progress !== null) formData.append('progress', progress);

    fetch(`tasks.php?project_id=<?= $projectId ?>&ajax_update=1`, {
        method: 'POST', body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else { 
            alert("Erreur serveur."); 
            window.location.reload(); 
        }
    })
    .catch(() => { window.location.reload(); });
}
</script>

<?php include 'includes/footer.php'; ?>