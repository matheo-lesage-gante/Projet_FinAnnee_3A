<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$isProf = hasRole('encadrant'); 

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isProf) {
    $action = $_POST['action'] ?? '';

    // Action : Création du projet par le prof
    if ($action === 'create_project') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $end = $_POST['end_date'] ?? '';

        if ($title && $desc && $end) {
            try {
                // 🎯 MODIFICATION ICI : On force le statut à 'en cours'
                $stmt = $pdo->prepare("INSERT INTO projects (title, description, end_date, created_by, status) VALUES (?, ?, ?, ?, 'en cours')");
                $stmt->execute([$title, $desc, $end, $userId]);
                $success = "Nouveau projet créé avec succès !";
            } catch (PDOException $e) {
                $error = "Erreur création projet : " . $e->getMessage();
            }
        } else {
            $error = "Veuillez remplir tous les champs.";
        }
    } 
}

// Récupération des données
if ($isProf) {
    $stmt = $pdo->query("SELECT * FROM projects ORDER BY created_at DESC");
    $projects = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT p.* FROM projects p 
        JOIN project_members pm ON p.id = pm.project_id 
        WHERE pm.user_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$userId]);
    $projects = $stmt->fetchAll();
}

include 'includes/header.php';
?>

<h1>Espace Central des Projets</h1>

<?php if($error): ?><p class="error" style="color: red; font-weight: bold; margin-bottom:15px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if($success): ?><p class="success" style="color: green; font-weight: bold; margin-bottom:15px;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

<?php if($isProf): ?>
<div class="card" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <h2 style="margin-bottom: 15px; color: var(--primary);">➕ Créer un nouveau projet</h2>
    <form method="POST" style="display: grid; gap: 15px;">
        <input type="hidden" name="action" value="create_project">
        <div>
            <label style="font-weight: bold;">Titre du projet :</label><br>
            <input type="text" name="title" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div>
            <label style="font-weight: bold;">Description :</label><br>
            <textarea name="description" required rows="3" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
        </div>
        <div>
            <label style="font-weight: bold;">Date de fin (Échéance) :</label><br>
            <input type="date" name="end_date" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <button type="submit" class="btn btn-primary" style="width: fit-content;">Créer le projet</button>
    </form>
</div>
<?php endif; ?>

<h2><?= $isProf ? "Tous les projets" : "Mes projets assignés" ?></h2>

<table class="data-table" style="margin-top: 15px;">
    <thead>
        <tr>
            <th>Titre</th>
            <th>Statut</th>
            <th>Date de fin</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if(empty($projects)): ?>
            <tr><td colspan="4" style="text-align:center; padding: 20px;">Aucun projet à afficher pour le moment.</td></tr>
        <?php else: ?>
            <?php foreach($projects as $p): ?>
            <tr>
                <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                <td><span class="badge status-<?= str_replace(' ', '-', $p['status']) ?>"><?= $p['status'] ?></span></td>
                <td><?= $p['end_date'] ?></td>
                <td><a href="project_detail.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-small">Détails et Membres</a></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include 'includes/footer.php'; ?>