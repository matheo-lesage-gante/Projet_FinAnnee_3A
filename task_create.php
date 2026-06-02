<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$projectId = $_GET['project_id'] ?? null;
if (!$projectId) die("Sélectionnez un projet d'abord.");

$stmtCat = $pdo->prepare("SELECT id, title FROM categories WHERE project_id = ? ORDER BY id ASC");
$stmtCat->execute([$projectId]);
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = ?");
$stmt->execute([$projectId]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le titre du projet
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

    if ($startDate && $endDate && $startDate > $endDate) {
        $error = "La date de début doit être antérieure à la date de fin.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO tasks (project_id, category_id, title, priority, created_by, status, start_date, end_date, assigned_to) VALUES (?, ?, ?, ?, ?, 'à faire', ?, ?, ?)");
        $stmt->execute([$projectId, $categoryId ?: null, $title, $priority, $_SESSION['user_id'], $startDate ?: null, $endDate ?: null, $assignedTo ?: null]);
        header("Location: tasks.php?project_id=$projectId");
        exit;
    }
}

include 'includes/header.php';
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
    color: var(--text-card);
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
    color: var(--text-card);
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
.fe-field { display: flex; flex-direction: column; }
</style>

<div style="display:flex;align-items:center;gap:6px;color:var(--text-muted);font-size:0.8rem;margin-bottom:14px;">
    <a href="tasks.php?project_id=<?= $projectId ?>" style="color:var(--text-muted);text-decoration:none;"
       onmouseover="this.style.color='var(--text-page)'" onmouseout="this.style.color='var(--text-muted)'"><?= htmlspecialchars($project['title'] ?? 'Projet') ?></a>
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
    <span>Nouvelle tâche</span>
</div>

<div style="max-width:680px;">

    <div style="margin-bottom:14px;">
        <h1 style="margin:0 0 2px;font-size:1.2rem; color: var(--text-page);">Nouvelle tâche</h1>
        <p style="color:var(--text-muted);font-size:0.82rem;margin:0;">Ajoutez une tâche au projet.</p>
    </div>

    <?php if ($error): ?>
        <div class="card" style="border-left:3px solid #ff453a;color:#ff453a;margin-bottom:12px;padding:10px 16px;font-size:0.88rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card" style="padding:20px;">
        <form method="POST" style="display:flex;flex-direction:column;gap:12px;">

            <div class="fe-field">
                <label class="fe-label">Titre *</label>
                <input type="text" name="title" required placeholder="Nom de la tâche..." class="fe-input">
            </div>

            <div class="fe-grid-2">
                <div class="fe-field">
                    <label class="fe-label">Jalon / Sous-catégorie</label>
                    <div class="fe-sel-wrap">
                        <select name="category_id" class="fe-select">
                            <option value="">— Global —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="fe-sel-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg>
                    </div>
                </div>
                <div class="fe-field">
                    <label class="fe-label">Assigner à</label>
                    <div class="fe-sel-wrap">
                        <select name="assigned_to" class="fe-select">
                            <option value="">— Non assigné —</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="fe-sel-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg>
                    </div>
                </div>
            </div>

            <div class="fe-grid-2">
                <div class="fe-field">
                    <label class="fe-label">Date de début *</label>
                    <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>" class="fe-input">
                </div>
                <div class="fe-field">
                    <label class="fe-label">Date de fin *</label>
                    <input type="date" name="end_date" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="fe-input">
                </div>
            </div>

            <div class="fe-field">
                <label class="fe-label">Priorité</label>
                <div style="display:flex;gap:8px;">
                    <?php foreach (['basse' => ['var(--success)','rgba(48,209,88,0.12)'], 'moyenne' => ['var(--warning)','rgba(255,159,10,0.12)'], 'haute' => ['var(--danger)','rgba(255,69,58,0.12)']] as $val => [$color, $bg]): ?>
                    <label style="flex:1;cursor:pointer;">
                        <input type="radio" name="priority" value="<?= $val ?>" <?= $val === 'moyenne' ? 'checked' : '' ?> style="display:none;">
                        <div class="priority-btn" data-value="<?= $val ?>" data-color="<?= $color ?>" data-bg="<?= $bg ?>"
                            style="text-align:center;padding:7px 4px;border:1px solid <?= $val === 'moyenne' ? $color : 'var(--border-color)' ?>;background:<?= $val === 'moyenne' ? $bg : 'transparent' ?>;border-radius:7px;font-size:0.8rem;font-weight:600;color:<?= $val === 'moyenne' ? $color : 'var(--text-muted)' ?>;transition:all 0.15s;cursor:pointer;"
                            onclick="selectPriority('<?= $val ?>','<?= $color ?>','<?= $bg ?>')">
                            <?= ucfirst($val) ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:flex;gap:10px;padding-top:12px;border-top:1px solid var(--border-color);">
                <a href="tasks.php?project_id=<?= $projectId ?>" class="btn btn-secondary" style="flex:1;text-align:center;text-decoration:none;padding:8px;">Annuler</a>
                <button type="submit" class="btn btn-primary" style="flex:2;padding:8px;">Créer la tâche</button>
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
</script>

<?php include 'includes/footer.php'; ?>