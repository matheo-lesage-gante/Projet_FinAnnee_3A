<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$taskId = $_GET['task_id'] ?? null;
if (!$taskId) die("Aucune tâche spécifiée.");

$stmtTask = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmtTask->execute([$taskId]);
$task = $stmtTask->fetch(PDO::FETCH_ASSOC);
if (!$task) die("Tâche introuvable.");

$projectId = $task['project_id'];

$stmtCat = $pdo->prepare("SELECT id, title FROM categories WHERE project_id = ? ORDER BY id ASC");
$stmtCat->execute([$projectId]);
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

$stmtMembers = $pdo->prepare("SELECT u.id, u.first_name, u.last_name FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = ?");
$stmtMembers->execute([$projectId]);
$members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

$stmtProj = $pdo->prepare("SELECT title FROM projects WHERE id = ?");
$stmtProj->execute([$projectId]);
$project = $stmtProj->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = $_POST['title'];
    $priority   = $_POST['priority'];
    $startDate  = $_POST['start_date'] ?? null;
    $endDate    = $_POST['end_date'] ?? null;
    $assignedTo = $_POST['assigned_to'] ?? null;
    $categoryId = $_POST['category_id'] ?? null;
    $status     = $_POST['status'] ?? 'à faire';
    $progress   = (int)($_POST['progress'] ?? 0);

    if ($status === 'terminé')  $progress = 100;
    elseif ($status === 'à faire') $progress = 0;

    if ($startDate && $endDate && $startDate > $endDate) {
        $error = "La date de début doit être antérieure à la date de fin.";
    } else {
        $stmt = $pdo->prepare("UPDATE tasks SET category_id=?, title=?, priority=?, status=?, progress=?, start_date=?, end_date=?, assigned_to=? WHERE id=?");
        $stmt->execute([$categoryId ?: null, $title, $priority, $status, $progress, $startDate ?: null, $endDate ?: null, $assignedTo ?: null, $taskId]);
        header("Location: tasks.php?project_id=$projectId");
        exit;
    }
}

include 'includes/header.php';

$currentPrio   = $task['priority'] ?? 'moyenne';
$currentStatus = $task['status'] ?? 'à faire';
?>

<style>
.fe-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 4px;
}
.fe-input {
    width: 100%;
    padding: 8px 10px;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border-color);
    border-radius: 7px;
    color: var(--text);
    font-size: 0.88rem;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.15s;
}
.fe-input:focus { border-color: rgba(255,255,255,0.3); }
.fe-select {
    width: 100%;
    padding: 8px 30px 8px 10px;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border-color);
    border-radius: 7px;
    color: var(--text);
    font-size: 0.88rem;
    outline: none;
    box-sizing: border-box;
    appearance: none;
    transition: border-color 0.15s;
}
.fe-select:focus { border-color: rgba(255,255,255,0.3); }
.fe-sel-wrap { position: relative; }
.fe-sel-arrow {
    position: absolute; right: 10px; top: 50%;
    transform: translateY(-50%);
    pointer-events: none; opacity: 0.4;
}
.fe-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.fe-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
.fe-field { display: flex; flex-direction: column; }
</style>

<!-- Breadcrumb compact -->
<div style="display:flex;align-items:center;gap:6px;color:var(--text-muted);font-size:0.8rem;margin-bottom:14px;">
    <a href="tasks.php?project_id=<?= $projectId ?>" style="color:var(--text-muted);text-decoration:none;"
       onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text-muted)'"><?= htmlspecialchars($project['title'] ?? 'Kanban') ?></a>
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
    <span>Modifier la tâche</span>
</div>

<div style="max-width:680px;">

    <div style="margin-bottom:14px;">
        <h1 style="margin:0 0 2px;font-size:1.2rem;">Modifier la tâche</h1>
        <p style="color:var(--text-muted);font-size:0.82rem;margin:0;">Mettez à jour les informations de la tâche.</p>
    </div>

    <?php if ($error): ?>
        <div class="card" style="border-left:3px solid #ff453a;color:#ff453a;margin-bottom:12px;padding:10px 16px;font-size:0.88rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card" style="padding:20px;">
        <form method="POST" style="display:flex;flex-direction:column;gap:12px;">

            <!-- Titre -->
            <div class="fe-field">
                <label class="fe-label">Titre *</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($task['title']) ?>" class="fe-input">
            </div>

            <!-- Jalon + Assigné -->
            <div class="fe-grid-2">
                <div class="fe-field">
                    <label class="fe-label">Jalon</label>
                    <div class="fe-sel-wrap">
                        <select name="category_id" class="fe-select">
                            <option value="">— Global —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $task['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="fe-sel-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg>
                    </div>
                </div>
                <div class="fe-field">
                    <label class="fe-label">Assigné à</label>
                    <div class="fe-sel-wrap">
                        <select name="assigned_to" class="fe-select">
                            <option value="">— Personne —</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $task['assigned_to'] == $m['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="fe-sel-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg>
                    </div>
                </div>
            </div>

            <!-- Priorité -->
            <div class="fe-field">
                <label class="fe-label">Priorité</label>
                <div style="display:flex;gap:8px;">
                    <?php foreach (['basse' => ['#30d158','rgba(48,209,88,0.12)'], 'moyenne' => ['#ff9f0a','rgba(255,159,10,0.12)'], 'haute' => ['#ff453a','rgba(255,69,58,0.12)']] as $val => [$color, $bg]): ?>
                    <label style="flex:1;cursor:pointer;">
                        <input type="radio" name="priority" value="<?= $val ?>" <?= $currentPrio === $val ? 'checked' : '' ?> style="display:none;">
                        <div class="priority-btn" data-value="<?= $val ?>" data-color="<?= $color ?>" data-bg="<?= $bg ?>"
                            style="text-align:center;padding:7px 4px;border:1px solid <?= $currentPrio === $val ? $color : 'var(--border-color)' ?>;background:<?= $currentPrio === $val ? $bg : 'transparent' ?>;border-radius:7px;font-size:0.8rem;font-weight:600;color:<?= $currentPrio === $val ? $color : 'var(--text-muted)' ?>;transition:all 0.15s;cursor:pointer;"
                            onclick="selectPriority('<?= $val ?>','<?= $color ?>','<?= $bg ?>')">
                            <?= ucfirst($val) ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Statut + Progression + Dates sur une ligne -->
            <div class="fe-grid-2">
                <div class="fe-field">
                    <label class="fe-label">Statut</label>
                    <div class="fe-sel-wrap">
                        <select name="status" id="status-select" onchange="syncProgress(this.value)" class="fe-select">
                            <option value="à faire"  <?= $currentStatus === 'à faire'  ? 'selected' : '' ?>>À faire</option>
                            <option value="en cours" <?= $currentStatus === 'en cours' ? 'selected' : '' ?>>En cours</option>
                            <option value="terminé"  <?= $currentStatus === 'terminé'  ? 'selected' : '' ?>>Terminé</option>
                        </select>
                        <svg class="fe-sel-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg>
                    </div>
                </div>
                <div class="fe-field">
                    <label class="fe-label">Progression — <span id="prog-label" style="color:var(--text);"><?= (int)$task['progress'] ?>%</span></label>
                    <div style="padding-top:4px;">
                        <input type="range" id="progress-range" name="progress" min="0" max="100" value="<?= (int)$task['progress'] ?>"
                            style="width:100%;accent-color:#7c6af7;cursor:pointer;"
                            oninput="document.getElementById('prog-label').textContent=this.value+'%'">
                        <div style="display:flex;justify-content:space-between;margin-top:1px;">
                            <span style="font-size:0.68rem;color:var(--text-muted);">0%</span>
                            <span style="font-size:0.68rem;color:var(--text-muted);">100%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dates -->
            <div class="fe-grid-2">
                <div class="fe-field">
                    <label class="fe-label">Date de début *</label>
                    <input type="date" name="start_date" required value="<?= htmlspecialchars($task['start_date'] ?? '') ?>" class="fe-input">
                </div>
                <div class="fe-field">
                    <label class="fe-label">Date de fin *</label>
                    <input type="date" name="end_date" required value="<?= htmlspecialchars($task['end_date'] ?? '') ?>" class="fe-input">
                </div>
            </div>

            <!-- Actions -->
            <div style="display:flex;gap:10px;padding-top:12px;border-top:1px solid var(--border-color);">
                <a href="tasks.php?project_id=<?= $projectId ?>" class="btn btn-secondary" style="flex:1;text-align:center;text-decoration:none;padding:8px;">Annuler</a>
                <button type="submit" class="btn btn-primary" style="flex:2;padding:8px;">Enregistrer</button>
            </div>

        </form>
    </div>
</div>

<script>
function selectPriority(val, color, bg) {
    document.querySelectorAll('.priority-btn').forEach(function(btn) {
        btn.style.borderColor = 'var(--border-color)';
        btn.style.background  = 'transparent';
        btn.style.color       = 'var(--text-muted)';
    });
    var active = document.querySelector('.priority-btn[data-value="' + val + '"]');
    if (active) {
        active.style.borderColor = color;
        active.style.background  = bg;
        active.style.color       = color;
    }
    document.querySelector('input[name="priority"][value="' + val + '"]').checked = true;
}

function syncProgress(status) {
    var range = document.getElementById('progress-range');
    var label = document.getElementById('prog-label');
    if (status === 'terminé') {
        range.value = 100; label.textContent = '100%'; range.disabled = true;
    } else if (status === 'à faire') {
        range.value = 0;   label.textContent = '0%';   range.disabled = true;
    } else {
        range.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    syncProgress(document.getElementById('status-select').value);
});
</script>

<?php include 'includes/footer.php'; ?>