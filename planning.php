<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$project_id = $_GET['project_id'] ?? null;
$projects   = $pdo->query("SELECT id, title FROM projects ORDER BY title")->fetchAll();
$tasks      = [];

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

$error     = '';
$success   = '';
$edit_task = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']       ?? '';
    $task_id   = (int)($_POST['task_id']    ?? 0);
    $proj_id   = (int)($_POST['project_id'] ?? 0);
    $label     = trim($_POST['label']       ?? '');
    $start     = $_POST['start_date']       ?? null;
    $end       = $_POST['end_date']         ?? null;
    $progress  = (int)($_POST['progress']   ?? 0);
    $assigned  = $_POST['assigned_to']      ?? null;
    $parent_id = $_POST['parent_id'] ? (int)$_POST['parent_id'] : null;

    if ($action === 'delete' && $task_id) {
        $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$task_id]);
        $success = 'Tâche supprimée.';
    } elseif ($label && $action === 'edit' && $task_id) {
        if ($start && $end && $start > $end) {
            $error = 'La date de début doit être antérieure à la date de fin.';
        } else {
            $status = 'en cours';
            if ($progress === 0)   $status = 'à faire';
            if ($progress === 100) $status = 'terminé';
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

if (isset($_GET['edit']) && $project_id) {
    $stmt = $pdo->prepare("SELECT *, title AS label FROM tasks WHERE id = ? AND project_id = ?");
    $stmt->execute([(int)$_GET['edit'], $project_id]);
    $edit_task = $stmt->fetch(PDO::FETCH_ASSOC);
}

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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ── Tokens Gantt — accordés au style.css v2 ── */
:root {
    --g-bg:        #16161c;   /* fond principal du gantt */
    --g-surface:   #1e1e24;   /* surface des cartes / header */
    --g-surface2:  #252530;   /* surface légèrement plus claire */
    --g-input:     #2a2a34;   /* fonds des inputs */
    --g-border:    rgba(255,255,255,0.08);
    --g-border2:   rgba(255,255,255,0.12);
    --g-text:      #eeeef2;
    --g-muted:     #9898a4;
    --g-faint:     #60606c;
    --g-accent:    #0a84ff;
    --g-accent-gl: rgba(10,132,255,0.18);
    --g-success:   #30d158;
    --g-warning:   #ff9f0a;
    --g-danger:    #ff453a;
    --g-row-h:     44px;
    --g-header-h:  52px;
    --g-label-w:   240px;
    --g-radius:    12px;
    --g-ease:      cubic-bezier(0.22,1,0.36,1);
}

/* ── Reset local ── */
.planning-wrap * { box-sizing: border-box; }
.planning-wrap {
    font-family: 'Inter', -apple-system, sans-serif;
    color: var(--g-text);
    -webkit-font-smoothing: antialiased;
    letter-spacing: -0.01em;
}

/* ── Toolbar ── */
.planning-toolbar {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 28px;
    flex-wrap: wrap;
}

.planning-toolbar h1 {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.04em;
    margin: 0;
    flex: 1;
    color: #111113;
}

.select-project {
    padding: 9px 14px;
    border-radius: var(--g-radius);
    border: 1px solid var(--g-border2);
    background: var(--g-surface);
    color: var(--g-text);
    font-family: inherit;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    min-width: 200px;
    transition: border-color 200ms var(--g-ease);
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%239898a4' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 34px;
}

.select-project:hover  { border-color: rgba(255,255,255,0.2); }
.select-project:focus  { outline: none; border-color: var(--g-accent); box-shadow: 0 0 0 3px var(--g-accent-gl); }

/* ── Alerts ── */
.alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: var(--g-radius);
    margin-bottom: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    border-width: 1px;
    border-style: solid;
}
.alert-success { background: rgba(48,209,88,0.08); border-color: rgba(48,209,88,0.2); color: var(--g-success); }
.alert-error   { background: rgba(255,69,58,0.08);  border-color: rgba(255,69,58,0.2);  color: var(--g-danger); }

/* ── Gantt outer wrapper ── */
.gantt-outer {
    background: var(--g-surface);
    border: 1px solid var(--g-border);
    border-radius: var(--g-radius);
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.15);
}

/* ── Controls bar ── */
.gantt-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--g-border);
    background: var(--g-bg);
    flex-wrap: wrap;
}

.zoom-btn {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 1px solid var(--g-border2);
    background: var(--g-surface2);
    color: var(--g-text);
    cursor: pointer;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    transition: background 150ms, border-color 150ms;
    line-height: 1;
}
.zoom-btn:hover { background: var(--g-input); border-color: rgba(255,255,255,0.18); }

.zoom-label {
    font-size: 0.8rem;
    color: var(--g-muted);
    min-width: 56px;
    text-align: center;
    font-variant-numeric: tabular-nums;
}

.gantt-today-info {
    font-size: 0.78rem;
    color: var(--g-faint);
    margin-left: 4px;
}
.gantt-today-info strong { color: var(--g-muted); font-weight: 600; }

/* ── Scroll area ── */
.gantt-scroll { overflow-x: auto; overflow-y: visible; }
.gantt-scroll::-webkit-scrollbar { height: 6px; }
.gantt-scroll::-webkit-scrollbar-track { background: transparent; }
.gantt-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }

/* ── Header row ── */
.gantt-head-row {
    display: flex;
    height: var(--g-header-h);
    border-bottom: 1px solid var(--g-border);
    position: sticky;
    top: 0;
    z-index: 4;
    background: var(--g-bg);
}

.gantt-head-label {
    min-width: var(--g-label-w);
    width: var(--g-label-w);
    padding: 0 16px;
    display: flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--g-faint);
    border-right: 1px solid var(--g-border);
    position: sticky;
    left: 0;
    background: var(--g-bg);
    z-index: 5;
}

.gantt-day-header {
    min-width: 32px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 0.68rem;
    color: var(--g-faint);
    border-right: 1px solid var(--g-border);
    user-select: none;
    gap: 1px;
}
.gantt-day-header .day-month  { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; opacity: 0.7; }
.gantt-day-header .day-num    { font-weight: 600; font-size: 0.75rem; color: var(--g-muted); }
.gantt-day-header.weekend     { background: rgba(255,255,255,0.02); }
.gantt-day-header.today-head  { background: rgba(10,132,255,0.1); }
.gantt-day-header.today-head .day-num { color: var(--g-accent); }

/* ── Task rows ── */
.gantt-task-row {
    display: flex;
    height: var(--g-row-h);
    border-bottom: 1px solid var(--g-border);
    transition: background 150ms;
}
.gantt-task-row:last-child { border-bottom: none; }
.gantt-task-row:hover { background: rgba(255,255,255,0.02); }

.gantt-label-cell {
    min-width: var(--g-label-w);
    width: var(--g-label-w);
    padding: 0 12px 0 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    border-right: 1px solid var(--g-border);
    font-size: 0.83rem;
    background: var(--g-surface);
    position: sticky;
    left: 0;
    z-index: 2;
}

.task-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--g-text);
    font-weight: 500;
}

.progress-pill {
    font-size: 0.68rem;
    font-weight: 700;
    border-radius: 20px;
    padding: 2px 7px;
    white-space: nowrap;
    flex-shrink: 0;
}

.task-actions {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
}

.task-actions a,
.task-actions button {
    background: none;
    border: 1px solid var(--g-border);
    border-radius: 6px;
    color: var(--g-faint);
    cursor: pointer;
    font-size: 0.72rem;
    padding: 3px 7px;
    text-decoration: none;
    font-family: inherit;
    line-height: 1;
    transition: color 150ms, border-color 150ms, background 150ms;
}
.task-actions a:hover      { color: var(--g-accent);  border-color: var(--g-accent); background: var(--g-accent-gl); }
.task-actions button:hover { color: var(--g-danger);  border-color: var(--g-danger); background: rgba(255,69,58,0.1); }

/* ── Bar area ── */
.gantt-bar-area { position: relative; flex: 1; height: var(--g-row-h); }

.gantt-bar {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    overflow: hidden;
    cursor: pointer;
    min-width: 6px;
    transition: filter 150ms, box-shadow 150ms;
}
.gantt-bar:hover { filter: brightness(1.15); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }

.gantt-bar-fill {
    height: 100%;
    background: rgba(0,0,0,0.22);
    border-radius: 6px 0 0 6px;
    flex-shrink: 0;
    transition: width 400ms var(--g-ease);
}

.gantt-bar-label {
    position: absolute;
    left: 9px;
    right: 5px;
    font-size: 0.7rem;
    font-weight: 600;
    color: rgba(255,255,255,0.92);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    pointer-events: none;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.today-line {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 1.5px;
    background: var(--g-accent);
    opacity: 0.6;
    pointer-events: none;
    z-index: 3;
}

/* ── Empty state ── */
.empty-state {
    text-align: center;
    padding: 60px 24px;
    color: var(--g-muted);
}
.empty-state .empty-icon {
    font-size: 2.5rem;
    margin-bottom: 12px;
    opacity: 0.7;
}
.empty-state p {
    font-size: 0.9rem;
    color: var(--g-muted);
    line-height: 1.6;
}

/* ── Edit form card ── */
.form-card {
    background: var(--g-surface);
    border: 1px solid var(--g-border);
    border-radius: var(--g-radius);
    padding: 28px;
    margin-top: 24px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.14);
}

.form-card h2 {
    margin: 0 0 20px;
    font-size: 1rem;
    font-weight: 700;
    color: var(--g-text);
    letter-spacing: -0.02em;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--g-border);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 16px;
}

.form-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--g-muted);
    margin-bottom: 7px;
    text-transform: uppercase;
    letter-spacing: 0.07em;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 9px 13px;
    border-radius: 8px;
    border: 1px solid var(--g-border);
    background: var(--g-input);
    color: var(--g-text);
    font-family: inherit;
    font-size: 0.875rem;
    transition: border-color 200ms, box-shadow 200ms, background 200ms;
    -webkit-appearance: none;
}

.form-group input:hover,
.form-group select:hover { border-color: rgba(255,255,255,0.16); background: #31313d; }

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--g-accent);
    box-shadow: 0 0 0 3px var(--g-accent-gl);
}

.form-group input[type="range"] {
    padding: 4px 0;
    background: transparent;
    border: none;
    box-shadow: none;
    cursor: pointer;
}

.form-group input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(0.6);
    cursor: pointer;
}

.form-group select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%239898a4' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 34px;
}

.form-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    padding-top: 16px;
    border-top: 1px solid var(--g-border);
}

.btn-save {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    font-weight: 600;
    font-size: 0.875rem;
    background: var(--g-accent);
    color: #fff;
    transition: background 200ms, transform 150ms;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    text-decoration: none;
}
.btn-save:hover { background: #0070d4; transform: translateY(-1px); }
.btn-save:active { transform: translateY(0); }

.btn-cancel {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border: 1px solid var(--g-border2);
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    font-weight: 600;
    font-size: 0.875rem;
    background: var(--g-input);
    color: var(--g-muted);
    transition: background 200ms, color 150ms;
    text-decoration: none;
}
.btn-cancel:hover { background: #31313d; color: var(--g-text); }
</style>

<div class="planning-wrap">

<div class="planning-toolbar">
    <h1>Planning Gantt</h1>
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
    <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error">✕ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$project_id): ?>
    <div class="gantt-outer">
        <div class="empty-state">
            <div class="empty-icon">📁</div>
            <p>Sélectionnez un projet pour afficher son planning.</p>
        </div>
    </div>
<?php elseif (empty($tasks)): ?>
    <div class="gantt-outer">
        <div class="empty-state">
            <div class="empty-icon">🗓️</div>
            <p>Aucune tâche pour ce projet.<br>Créez vos tâches depuis le tableau principal.</p>
        </div>
    </div>
<?php else: ?>

<div class="gantt-outer">
    <div class="gantt-controls">
        <button class="zoom-btn" id="zoom-out" aria-label="Dézoomer">−</button>
        <span class="zoom-label" id="zoom-label">32 px/j</span>
        <button class="zoom-btn" id="zoom-in" aria-label="Zoomer">+</button>
        <span class="gantt-today-info">
            Aujourd'hui : <strong><?= date('d/m/Y') ?></strong>
        </span>
    </div>
    <div class="gantt-scroll" id="gantt-scroll">
        <div id="gantt-inner"></div>
    </div>
</div>

<script>
const ALL_TASKS = <?= json_encode(array_values($tasks)) ?>;
const TASKS     = ALL_TASKS.filter(t => t.start_date && t.end_date);
const TODAY     = new Date(); TODAY.setHours(0,0,0,0);
let DAY_W       = 32;

function dateDiff(a, b) { return Math.round((b - a) / 86400000); }
function parseDate(s)   { const [y,m,d] = s.split('-').map(Number); return new Date(y, m-1, d); }
function esc(s)         { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* Couleur de barre selon progression */
function barColor(prog) {
    if (prog === 100) return { bg: 'rgba(48,209,88,0.55)',  accent: '#30d158' };
    if (prog >= 60)  return { bg: 'rgba(10,132,255,0.50)',  accent: '#0a84ff' };
    if (prog >= 30)  return { bg: 'rgba(255,159,10,0.50)',  accent: '#ff9f0a' };
    return                  { bg: 'rgba(152,152,164,0.40)', accent: '#9898a4' };
}

function pillStyle(prog) {
    const c = barColor(prog);
    return `background:${c.bg.replace('0.5','0.15').replace('0.4','0.12')};color:${c.accent};`;
}

function render() {
    const container = document.getElementById('gantt-inner');
    if (!TASKS.length) {
        container.innerHTML = '<div class="empty-state"><p>Aucune tâche planifiée avec des dates de début et de fin.</p></div>';
        return;
    }

    let minDate = new Date(Math.min(...TASKS.map(t => parseDate(t.start_date))));
    let maxDate = new Date(Math.max(...TASKS.map(t => parseDate(t.end_date))));
    minDate.setDate(minDate.getDate() - 3);
    maxDate.setDate(maxDate.getDate() + 5);

    const totalDays = dateDiff(minDate, maxDate) + 1;
    const LABEL_W   = 240;
    const totalW    = LABEL_W + totalDays * DAY_W;

    let html = `<div style="width:${totalW}px;position:relative;">`;

    /* Header */
    html += `<div class="gantt-head-row" style="width:${totalW}px;">`;
    html += `<div class="gantt-head-label">Tâche</div>`;
    let prevMonth = -1;
    for (let i = 0; i < totalDays; i++) {
        const d = new Date(minDate); d.setDate(minDate.getDate() + i);
        const isToday   = d.getTime() === TODAY.getTime();
        const isWeekend = d.getDay() === 0 || d.getDay() === 6;
        const monthStr  = d.getMonth() !== prevMonth
            ? ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'][d.getMonth()]
            : '';
        prevMonth = d.getMonth();
        const cls = isToday ? 'today-head' : isWeekend ? 'weekend' : '';
        html += `<div class="gantt-day-header ${cls}" style="width:${DAY_W}px;min-width:${DAY_W}px;">
            <span class="day-month">${monthStr}</span>
            <span class="day-num">${d.getDate()}</span>
        </div>`;
    }
    html += `</div>`;

    /* Today line */
    const todayLeft = LABEL_W + dateDiff(minDate, TODAY) * DAY_W + DAY_W / 2;
    html += `<div class="today-line" style="left:${todayLeft}px;top:${52}px;height:${TASKS.length * 44}px;"></div>`;

    /* Rows */
    TASKS.forEach(task => {
        const start = parseDate(task.start_date);
        const end   = parseDate(task.end_date);
        const dur   = dateDiff(start, end) + 1;
        const left  = LABEL_W + dateDiff(minDate, start) * DAY_W;
        const width = dur * DAY_W;
        const prog  = Math.max(0, Math.min(100, parseInt(task.progress) || 0));
        const col   = barColor(prog);

        html += `<div class="gantt-task-row">
            <div class="gantt-label-cell">
                <span class="task-name" title="${esc(task.label)}">${esc(task.label)}</span>
                <span class="progress-pill" style="${pillStyle(prog)}">${prog}%</span>
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
            <div class="gantt-bar-area">
                <div class="gantt-bar" style="left:${left - LABEL_W}px;width:${width}px;background:${col.bg};"
                     title="${esc(task.label)} · ${task.start_date} → ${task.end_date} (${prog}%)">
                    <div class="gantt-bar-fill" style="width:${prog}%;"></div>
                    <span class="gantt-bar-label">${esc(task.label)}</span>
                </div>
            </div>
        </div>`;
    });

    html += `</div>`;
    container.innerHTML = html;

    /* Auto-scroll to today */
    const scroll = document.getElementById('gantt-scroll');
    const target = LABEL_W + dateDiff(minDate, TODAY) * DAY_W - scroll.clientWidth / 2 + DAY_W / 2;
    scroll.scrollLeft = Math.max(0, target);
}

document.getElementById('zoom-in').addEventListener('click', () => {
    DAY_W = Math.min(80, DAY_W + 8);
    document.getElementById('zoom-label').textContent = DAY_W + ' px/j';
    render();
});
document.getElementById('zoom-out').addEventListener('click', () => {
    DAY_W = Math.max(16, DAY_W - 8);
    document.getElementById('zoom-label').textContent = DAY_W + ' px/j';
    render();
});

render();
</script>

<?php endif; ?>

<?php if ($project_id && $edit_task): ?>
<div class="form-card" id="edit-form">
    <h2>Modifier la tâche · <?= htmlspecialchars($edit_task['label']) ?></h2>
    <form method="post">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <input type="hidden" name="action"     value="edit">
        <input type="hidden" name="task_id"    value="<?= $edit_task['id'] ?>">

        <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1;">
                <label>Nom de la tâche *</label>
                <input type="text" name="label" required maxlength="120"
                       value="<?= htmlspecialchars($edit_task['label'] ?? '') ?>">
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
                <label>Avancement (<span id="prog-val"><?= $edit_task['progress'] ?? 0 ?></span>%)</label>
                <input type="range" name="progress" min="0" max="100"
                       value="<?= $edit_task['progress'] ?? 0 ?>"
                       oninput="document.getElementById('prog-val').textContent=this.value">
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
            <button type="submit" class="btn-save">Enregistrer</button>
            <a href="planning.php?project_id=<?= $project_id ?>" class="btn-cancel">Annuler</a>
        </div>
    </form>
</div>
<?php endif; ?>

</div>
<?php include 'includes/footer.php'; ?>