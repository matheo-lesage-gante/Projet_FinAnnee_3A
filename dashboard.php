<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Statistiques simplifiées
if (hasRole('eleve')) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM project_members WHERE user_id = ?");
    $stmt->execute([$userId]);
    $myProjectsCount = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND status != 'terminé'");
    $stmt->execute([$userId]);
    $myTasksCount = $stmt->fetch()['total'];
} else { // Si c'est un prof
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM projects");
    $myProjectsCount = $stmt->fetch()['total'];
}

include 'includes/header.php';
?>

<h1>Tableau de bord</h1>
<div class="stats-grid">
    <div class="stat-card">
        <h3>Projets</h3>
        <div class="number"><?= $myProjectsCount ?></div>
    </div>
    <?php if (hasRole('eleve')): ?>
    <div class="stat-card">
        <h3>Tâches à faire</h3>
        <div class="number"><?= $myTasksCount ?></div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>