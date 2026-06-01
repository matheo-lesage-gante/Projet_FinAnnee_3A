<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// --- Récupération des tâches ---
$project_id = $_GET['project_id'] ?? null;

// Chargement des projets disponibles
$projects = $pdo->query("SELECT id, title FROM projects ORDER BY title")->fetchAll();

$tasks = [];
if ($project_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, t.title AS label, u.first_name, u.last_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.project_id = ?
        ORDER BY COALESCE(t.start_date, '9999-12-31') ASC, t.id ASC
    ");
    $stmt->execute([$project_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Traitement du formulaire (Modification uniquement / Suppression) ---
$error   = '';
$success = '';
$edit_task = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']      ?? '';
    $task_id   = (int)($_POST['task_id']   ?? 0);
    $proj_id   = (int)($_POST['project_id'] ?? 0);
    $label     = trim($_POST['label']      ?? ''); 
    $start     = $_POST['start_date']      ?? null;
    $end       = $_POST['end_date']        ?? null;
    $progress  = (int)($_POST['progress']  ?? 0);
    $assigned  = $_POST['assigned_to']     ?? null;
    $parent_id = $_POST['parent_id'] ? (int)$_POST['parent_id'] : null;

    if ($action === 'delete' && $task_id) {
        $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$task_id]);
        $success = 'Tâche supprimée.';
    } elseif ($label && $action === 'edit' && $task_id) {
        if ($start && $end && $start > $end) {
            $error = 'La date de début doit être antérieure à la date de fin.';
        } else {
            // --- AJUSTEMENT DYNAMIQUE DU STATUT SELON L'AVANCEMENT ---
            $status = 'en cours';
            if ($progress === 0) {
                $status = 'à faire';
            } elseif ($progress === 100) {
                $status = 'terminé';
            }

            // Mise à jour incluant le calcul automatique du champ status
            $pdo->prepare("UPDATE tasks SET title=?, start_date=?, end_date=?, progress=?, status=?, assigned_to=?, parent_id=? WHERE id=?")
                ->execute([$label, $start ?: null, $end ?: null, $progress, $status, $assigned ?: null, $parent_id, $task_id]);
            $success = 'Tâche mise à jour.';
        }
    }

    if ($proj_id && empty($error)) {
        header("Location: planning.php?project_id=$proj_id");
        exit;
    }
}

// Édition d'une tâche existante
if (isset($_GET['edit']) && $project_id) {
    $stmt = $pdo->prepare("SELECT *, title AS label FROM tasks WHERE id = ? AND project_id = ?");
    $stmt->execute([(int)$_GET['edit'], $project_id]);
    $edit_task = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Utilisateurs du projet pour l'assignation
$members = [];
if ($project_id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name
        FROM project_members pm
        JOIN users u ON pm.user_id = u.id
        WHERE pm.project_id = ?
    ");
    $stmt->execute([$project_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<style>
:root {
    --gantt-bg:       #0f1117;
    --gantt-surface:  #1a1d27;
    --gantt-border:   #2a2d3e;
    --gantt-accent:   #7c6af7;
    --gantt-accent2:  #4fd1c5;
    --gantt-text:     #e2e8f0;
    --gantt-muted:    #64748b;
    --gantt-today:    rgba(124,106,247,.25);
    --gantt-row-h:    44px;
    --gantt-header-h: 56px;
    --gantt-label-w:  240px;
    --radius:         10px;
}
.planning-wrap { font-family: 'DM Sans', sans-serif; color: var(--gantt-text); }
.planning-toolbar { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.planning-toolbar h1 { font-size: 1.4rem; font-weight: 700; margin: 0; flex: 1; }
.select-project { padding: .45rem .9rem; border-radius: var(--radius); border: 1px solid var(--gantt-border); background: var(--gantt-surface); color: var(--gantt-text); font-size: .9rem; cursor: pointer; }
.btn-primary { padding: .45rem 1.1rem; border-radius: var(--radius); border: none; background: var(--gantt-accent); color: #fff; font-weight: 600; cursor: pointer; font-size: .88rem; transition: opacity .15s; }
.btn-primary:hover { opacity: .85; }
.gantt-outer { background: var(--gantt-surface); border: 1px solid var(--gantt-border); border-radius: var(--radius); overflow: hidden; }
.gantt-scroll { overflow-x: auto; }
.gantt-grid { display: grid; }
.gantt-header-row, .gantt-row { display: contents; }
.gantt-label-cell { width: var(--gantt-label-w); min-width: var(--gantt-label-w); padding: 0 1rem; display: flex; align-items: center; gap: .5rem; border-right: 1px solid var(--gantt-border); font-size: .85rem; background: var(--gantt-surface); position: sticky; left: 0; z-index: 2; }
.gantt-label-cell.header { height: var(--gantt-header-h); font-weight: 700; font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; color: var(--gantt-muted); background: var(--gantt-bg); }
.gantt-day-header { height: var(--gantt-header-h); min-width: 32px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: .7rem; color: var(--gantt-muted); border-right: 1px solid var(--gantt-border); border-bottom: 1px solid var(--gantt-border); background: var(--gantt-bg); user-select: none; }
.gantt-day-header.weekend { background: #141620; }
.gantt-day-header.today   { background: var(--gantt-today); color: var(--gantt-accent); font-weight: 700; }
.gantt-day-header .month-label { font-size: .62rem; text-transform: uppercase; letter-spacing: .06em; opacity: .6; }
.gantt-task-row { display: flex; height: var(--gantt-row-h); border-bottom: 1px solid var(--gantt-border); position: relative; }
.gantt-task-row:hover { background: rgba(255,255,255,.025); }
.gantt-task-row .gantt-label-cell { height: var(--gantt-row-h); border-bottom: 1px solid var(--gantt-border); }
.gantt-bar-area { position: relative; flex: 1; height: var(--gantt-row-h); }
.gantt-bar { position: absolute; top: 50%; transform: translateY(-50%); height: 22px; border-radius: 6px; display: flex; align-items: center; overflow: hidden; cursor: pointer; transition: filter .15s; min-width: 6px; }
.gantt-bar:hover { filter: brightness(1.18); }
.gantt-bar-fill { height: 100%; background: rgba(0,0,0,.25); border-radius: 6px 0 0 6px; transition: width .3s ease; }
.gantt-bar-label { position: absolute; left: 8px; right: 4px; font-size: .72rem; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; pointer-events: none; }
.today-line { position: absolute; top: 0; bottom: 0; width: 2px; background: var(--gantt-accent); opacity: .7; pointer-events: none; z-index: 3; }
.form-card { background: var(--gantt-surface); border: 1px solid var(--gantt-border); border-radius: var(--radius); padding: 1.5rem; margin-top: 1.5rem; }
.form-card h2 { margin: 0 0 1.2rem; font-size: 1rem; font-weight: 700; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: .9rem; }
.form-group label { display: block; font-size: .78rem; color: var(--gantt-muted); margin-bottom: .35rem; text-transform: uppercase; letter-spacing: .05em; }
.form-group input, .form-group select { width: 100%; padding: .45rem .75rem; border-radius: 7px; border: 1px solid var(--gantt-border); background: var(--gantt-bg); color: var(--gantt-text); font-size: .88rem; box-sizing: border-box; }
.form-group input[type=range] { padding: 0; }
.form-actions { margin-top: 1rem; display: flex; gap: .7rem; flex-wrap: wrap; }
.gantt-controls { display: flex; align-items: center; gap: .7rem; padding: .75rem 1rem; border-bottom: 1px solid var(--gantt-border); background: var(--gantt-bg); flex-wrap: wrap; }
.zoom-btn { width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--gantt-border); background: var(--gantt-surface); color: var(--gantt-text); cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; }
.zoom-label { font-size: .8rem; color: var(--gantt-muted); min-width: 60px; text-align: center; }
.empty-state { text-align: center; padding: 4rem 2rem; color: var(--gantt-muted); }
.empty-state .icon { font-size: 3rem; margin-bottom: .5rem; }
.alert { padding: .7rem 1rem; border-radius: var(--radius); margin-bottom: 1rem; font-size: .88rem; }
.alert-success { background: rgba(79,209,197,.12); border: 1px solid rgba(79,209,197,.3); color: var(--gantt-accent2); }
.alert-error    { background: rgba(224,92,92,.12);  border: 1px solid rgba(224,92,92,.3);  color: #e05c5c; }
.progress-pill { font-size: .7rem; border-radius: 20px; padding: 2px 8px; white-space: nowrap; font-weight: bold; }
.task-actions { display: flex; gap: .35rem; margin-left: auto; }
.task-actions a, .task-actions button { background: none; border: 1px solid var(--gantt-border); border-radius: 5px; color: var(--gantt-muted); cursor: pointer; font-size: .8rem; padding: 2px 6px; text-decoration: none; transition: color .15s, border-color .15s; }
.task-actions a:hover { color: var(--gantt-accent); border-color: var(--gantt-accent); }
.task-actions button:hover { color: #e05c5c; border-color: #e05c5c; }
</style>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">

<div class="planning-wrap">

<div class="planning-toolbar">
    <h1>📅 Planning Gantt</h1>
    <form method="get" style="display:flex;gap:.5rem;align-items:center;">
        <select name="project_id" class="select-project" onchange="this.form.submit()">
            <option value="">— Choisir un projet —</option>
            <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $project_id == $p['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$project_id): ?>
    <div class="gantt-outer">
        <div class="empty-state">
            <div class="icon">📁</div>
            <p>Sélectionnez un projet pour afficher son planning.</p>
        </div>
    </div>
<?php elseif (empty($tasks)): ?>
    <div class="gantt-outer">
        <div class="empty-state">
            <div class="icon">🗓️</div>
            <p>Aucune tâche pour ce projet. Créez vos tâches depuis le tableau principal.</p>
        </div>
    </div>
<?php else: ?>

<div class="gantt-outer">
    <div class="gantt-controls">
        <button class="zoom-btn" id="zoom-out">−</button>
        <span class="zoom-label" id="zoom-label">32 px/j</span>
        <button class="zoom-btn" id="zoom-in">+</button>
        <span style="font-size:.78rem;color:var(--gantt-muted);margin-left:.5rem;">
            Aujourd'hui : <strong><?= date('d/m/Y') ?></strong>
        </span>
    </div>
    <div class="gantt-scroll" id="gantt-scroll">
        <div id="gantt-inner"></div>
    </div>
</div>

<script>
const ALL_TASKS = <?= json_encode(array_values($tasks)) ?>;
const TASKS = ALL_TASKS.filter(t => t.start_date && t.end_date);
const TODAY = new Date(); TODAY.setHours(0,0,0,0);

let DAY_W = 32; 

function dateDiff(a, b) { return Math.round((b - a) / 86400000); }
function parseDate(s) { const [y,m,d] = s.split('-').map(Number); return new Date(y, m-1, d); }

function getProgressColor(prog) {
    const hue = (prog / 100) * 120;
    return `hsl(${hue}, 75%, 45%)`;
}

function render() {
    const container = document.getElementById('gantt-inner');
    if(TASKS.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>Aucune tâche planifiée avec des dates de début et de fin.</p></div>';
        return;
    }

    let minDate = new Date(Math.min(...TASKS.map(t => parseDate(t.start_date))));
    let maxDate = new Date(Math.max(...TASKS.map(t => parseDate(t.end_date))));
    minDate.setDate(minDate.getDate() - 3);
    maxDate.setDate(maxDate.getDate() + 5);

    const totalDays = dateDiff(minDate, maxDate) + 1;
    const LABEL_W = 240;
    const totalW  = LABEL_W + totalDays * DAY_W;

    let html = `<div style="width:${totalW}px;position:relative;">`;
    html += `<div style="display:flex;height:56px;border-bottom:1px solid var(--gantt-border);position:sticky;top:0;z-index:4;background:var(--gantt-bg);">`;
    html += `<div style="width:${LABEL_W}px;min-width:${LABEL_W}px;border-right:1px solid var(--gantt-border);display:flex;align-items:center;padding:0 1rem;font-size:.75rem;font-weight:700;color:var(--gantt-muted);text-transform:uppercase;letter-spacing:.08em;position:sticky;left:0;background:var(--gantt-bg);z-index:5;">Tâche</div>`;

    let prevMonth = -1;
    for (let i = 0; i < totalDays; i++) {
        const d = new Date(minDate); d.setDate(minDate.getDate() + i);
        const isToday   = d.getTime() === TODAY.getTime();
        const isWeekend = d.getDay() === 0 || d.getDay() === 6;
        const monthStr  = d.getMonth() !== prevMonth ? ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'][d.getMonth()] : '';
        prevMonth = d.getMonth();
        const cls = isToday ? 'today' : isWeekend ? 'weekend' : '';
        html += `<div class="gantt-day-header ${cls}" style="width:${DAY_W}px;min-width:${DAY_W}px;">
            <span class="month-label">${monthStr}</span>
            <span>${d.getDate()}</span>
        </div>`;
    }
    html += `</div>`;

    const todayOffset = LABEL_W + dateDiff(minDate, TODAY) * DAY_W + DAY_W / 2;
    html += `<div class="today-line" style="left:${todayOffset}px;top:56px;height:${TASKS.length * 44}px;"></div>`;

    TASKS.forEach((task) => {
        const start  = parseDate(task.start_date);
        const end    = parseDate(task.end_date);
        const dur    = dateDiff(start, end) + 1;
        const left   = LABEL_W + dateDiff(minDate, start) * DAY_W;
        const width  = dur * DAY_W;
        const prog   = Math.max(0, Math.min(100, parseInt(task.progress) || 0));
        
        const dynamicColor = getProgressColor(prog);

        html += `<div class="gantt-task-row" style="display:flex;">
            <div class="gantt-label-cell" style="width:${LABEL_W}px;min-width:${LABEL_W}px;position:sticky;left:0;z-index:2;">
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;">${escHtml(task.label)}</span>
                <span class="progress-pill" style="background:${dynamicColor}20; color:${dynamicColor};">${prog}%</span>
                <div class="task-actions">
                    <a href="?project_id=<?= $project_id ?>&edit=${task.id}#edit-form" title="Modifier">✏️</a>
                    <form method="post" style="margin:0;" onsubmit="return confirm('Supprimer cette tâche ?')">
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
                        <input type="hidden" name="action"     value="delete">
                        <input type="hidden" name="task_id"    value="${task.id}">
                        <button type="submit" title="Supprimer">🗑</button>
                    </form>
                </div>
            </div>
            <div class="gantt-bar-area" style="flex:1;position:relative;">
                <div class="gantt-bar" style="left:${left - LABEL_W}px;width:${width}px;background:${dynamicColor};" title="${escHtml(task.label)} — ${task.start_date} → ${task.end_date} (${prog}%)">
                    <div class="gantt-bar-fill" style="width:${prog}%;"></div>
                    <span class="gantt-bar-label">${escHtml(task.label)}</span>
                </div>
            </div>
        </div>`;
    });

    html += `</div>`;
    container.innerHTML = html;

    const scroll = document.getElementById('gantt-scroll');
    const scrollTarget = LABEL_W + dateDiff(minDate, TODAY) * DAY_W - scroll.clientWidth / 2 + DAY_W / 2;
    scroll.scrollLeft = Math.max(0, scrollTarget);
}

function escHtml(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

document.getElementById('zoom-in').addEventListener('click', () => { DAY_W = Math.min(80, DAY_W + 8); document.getElementById('zoom-label').textContent = DAY_W + ' px/j'; render(); });
document.getElementById('zoom-out').addEventListener('click', () => { DAY_W = Math.max(16, DAY_W - 8); document.getElementById('zoom-label').textContent = DAY_W + ' px/j'; render(); });

render();
</script>
<?php endif; ?>

<?php if ($project_id && $edit_task): ?>
<div class="form-card" id="edit-form">
    <h2>✏️ Modifier la tâche : <?= htmlspecialchars($edit_task['label']) ?></h2>
    <form method="post">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <input type="hidden" name="action"     value="edit">
        <input type="hidden" name="task_id"    value="<?= $edit_task['id'] ?>">

        <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1;">
                <label>Nom de la tâche *</label>
                <input type="text" name="label" required maxlength="120" value="<?= htmlspecialchars($edit_task['label'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Date de début</label>
                <input type="date" name="start_date" value="<?= $edit_task['start_date'] ?>">
            </div>
            <div class="form-group">
                <label>Date de fin</label>
                <input type="date" name="end_date" value="<?= $edit_task['end_date'] ?>">
            </div>
            <div class="form-group">
                <label>Avancement (<?= $edit_task['progress'] ?? 0 ?>%)</label>
                <input type="range" name="progress" min="0" max="100" value="<?= $edit_task['progress'] ?? 0 ?>" oninput="this.previousElementSibling.textContent='Avancement ('+this.value+'%)'">
            </div>
            <?php if (!empty($members)): ?>
            <div class="form-group">
                <label>Assigné à</label>
                <select name="assigned_to">
                    <option value="">— Personne —</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= ($edit_task['assigned_to'] ?? null) == $m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if (!empty($tasks)): ?>
            <div class="form-group">
                <label>Tâche parente</label>
                <select name="parent_id">
                    <option value="">— Aucune —</option>
                    <?php foreach ($tasks as $t): ?>
                        <?php if ($t['id'] != $edit_task['id']): ?>
                        <option value="<?= $t['id'] ?>" <?= ($edit_task['parent_id'] ?? null) == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['label']) ?>
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">💾 Enregistrer les modifications</button>
            <a href="planning.php?project_id=<?= $project_id ?>" class="btn-primary" style="background:var(--gantt-muted);text-decoration:none;">Annuler</a>
        </div>
    </form>
</div>
<?php endif; ?>

</div>
<?php include 'includes/footer.php'; ?>