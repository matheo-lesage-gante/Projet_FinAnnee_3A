<?php
// ... [Garde tout ton code PHP du haut de dashboard.php exactement pareil jusqu'à include 'includes/header.php';] ...
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$isProf = hasRole('encadrant'); 

if ($isProf) {
    $stmt = $pdo->query("SELECT * FROM projects ORDER BY created_at DESC");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT p.* FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE pm.user_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$userId]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$dashboardData = [];
$coefficients = ['haute' => 2.0, 'moyenne' => 1.5, 'basse' => 1.0]; 

foreach ($projects as $p) {
    $pid = $p['id'];
    $stmtCat = $pdo->prepare("SELECT id, title FROM categories WHERE project_id = ? ORDER BY id ASC");
    $stmtCat->execute([$pid]);
    $categories = $stmtCat->fetchAll(PDO::FETCH_UNIQUE);
    $categories[0] = ['id' => 0, 'project_id' => $pid, 'title' => 'Tâches globales'];

    $stmtTasks = $pdo->prepare("SELECT category_id, priority, progress FROM tasks WHERE project_id = ?");
    $stmtTasks->execute([$pid]);
    $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

    $catCalculations = [];
    foreach ($categories as $catId => $cat) { $catCalculations[$catId] = ['sum_weighted' => 0, 'sum_coef' => 0, 'progress' => 0, 'title' => $cat['title']]; }

    foreach ($tasks as $t) {
        $catId = (int)($t['category_id'] ?? 0);
        if (!isset($catCalculations[$catId])) { $catId = 0; }
        $coef = $coefficients[mb_strtolower(trim($t['priority'] ?? 'basse'))] ?? 1.0;
        $taskProgress = max(0, min(100, (int)($t['progress'] ?? 0)));
        $catCalculations[$catId]['sum_weighted'] += ($taskProgress * $coef);
        $catCalculations[$catId]['sum_coef'] += $coef;
    }

    $projectWeightedSum = 0; $projectTotalWeights = 0; $finalCats = [];
    foreach ($catCalculations as $catId => $data) {
        if ($data['sum_coef'] > 0) {
            $progress = round($data['sum_weighted'] / $data['sum_coef']);
            $projectWeightedSum += ($progress * $data['sum_coef']);
            $projectTotalWeights += $data['sum_coef'];
            $finalCats[] = ['title' => $data['title'], 'progress' => $progress];
        }
    }
    $projectProgress = ($projectTotalWeights > 0) ? round($projectWeightedSum / $projectTotalWeights) : 0;
    $dashboardData[] = ['project' => $p, 'global_progress' => $projectProgress, 'categories' => $finalCats];
}

include 'includes/header.php';
?>

<div class="flex-between">
    <div>
        <h1>Vue d'ensemble</h1>
        <p style="color: var(--text-muted);"><?= $isProf ? "Progression globale des projets." : "Progression de vos projets." ?></p>
    </div>
</div>

<?php if (empty($dashboardData)): ?>
    <div class="card" style="text-align: center; color: var(--text-muted);">Aucun projet à afficher.</div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
        <?php foreach ($dashboardData as $data): ?>
            <?php 
                $p = $data['project'];
                $globalProg = $data['global_progress'];
                $opacity = 0.2 + (0.8 * ($globalProg / 100)); // Plus on avance, plus c'est blanc
                $color = "rgba(255, 255, 255, $opacity)";
            ?>
            <div class="card" style="display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 16px;">
                    <div>
                        <h2 style="margin: 0; font-size: 1.2rem;"><?= htmlspecialchars($p['title']) ?></h2>
                        <p style="color: var(--text-muted); font-size: 0.8rem; margin-top: 5px; text-transform: uppercase;">Échéance : <?= date('d/m/Y', strtotime($p['end_date'])) ?></p>
                    </div>
                    <div style="width: 60px; height: 60px; flex-shrink: 0;">
                        <svg viewBox="0 0 36 36" style="display: block; margin: 0 auto;">
                            <path fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <path fill="none" stroke="<?= $color ?>" stroke-width="3" stroke-linecap="round" stroke-dasharray="<?= $globalProg ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <text x="18" y="21" fill="#fff" font-size="0.6em" font-weight="500" text-anchor="middle"><?= $globalProg ?>%</text>
                        </svg>
                    </div>
                </div>

                <?php if (!empty($data['categories'])): ?>
                    <div style="margin-bottom: 20px;">
                        <p style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 1px;">Jalons</p>
                        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                            <?php foreach ($data['categories'] as $cat): ?>
                                <?php 
                                    $catProg = $cat['progress'];
                                    $catColor = "rgba(255, 255, 255, " . (0.2 + (0.8 * ($catProg / 100))) . ")";
                                ?>
                                <div style="display: flex; flex-direction: column; align-items: center; width: 50px; text-align: center;">
                                    <div style="width: 40px; height: 40px; margin-bottom: 8px;">
                                        <svg viewBox="0 0 36 36">
                                            <path fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="2.5" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                            <path fill="none" stroke="<?= $catColor ?>" stroke-width="2.5" stroke-dasharray="<?= $catProg ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                        </svg>
                                    </div>
                                    <span style="font-size: 0.7rem; color: var(--text-muted); line-height: 1.1; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"><?= htmlspecialchars($cat['title']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <a href="tasks.php?project_id=<?= $p['id'] ?>" class="btn btn-secondary" style="margin-top: auto; width: 100%;">Ouvrir le projet</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>