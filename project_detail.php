<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$projectId = $_GET['id'] ?? 0;
$isProf = hasRole('encadrant'); 

$success = '';
$error = '';

// --- GESTION DES ACTIONS (PROF UNIQUEMENT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isProf) {
    $action = $_POST['action'] ?? '';

    // Ajouter un étudiant
    if ($action === 'add_student') {
        $studentId = $_POST['student_id'] ?? '';
        $roleInProject = $_POST['role_in_project'] ?? 'membre';

        if ($studentId) {
            try {
                $check = $pdo->prepare("SELECT id FROM project_members WHERE project_id = ? AND user_id = ?");
                $check->execute([$projectId, $studentId]);
                if ($check->fetch()) {
                    $error = "Cet étudiant participe déjà à ce projet.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO project_members (project_id, user_id, role_in_project) VALUES (?, ?, ?)");
                    $stmt->execute([$projectId, $studentId, $roleInProject]);
                    $success = "Étudiant ajouté au projet avec succès !";
                }
            } catch (PDOException $e) {
                $error = "Erreur lors de l'ajout : " . $e->getMessage();
            }
        }
    } 
    // Retirer un étudiant du projet
    elseif ($action === 'remove_student') {
        $memberId = $_POST['member_id'] ?? '';
        if ($memberId) {
            $stmt = $pdo->prepare("DELETE FROM project_members WHERE id = ?");
            $stmt->execute([$memberId]);
            $success = "Membre retiré du projet.";
        }
    }
    // Supprimer tout le projet
    elseif ($action === 'delete_project') {
        try {
            // La base de données supprimera automatiquement les tâches et membres associés (ON DELETE CASCADE)
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            header('Location: projects.php');
            exit;
        } catch (PDOException $e) {
            $error = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// --- RÉCUPÉRATION DES DONNÉES DU PROJET ---
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if(!$project) die("Projet introuvable.");

// Récupération des membres du projet
$stmtMembers = $pdo->prepare("
    SELECT pm.id as member_id, pm.role_in_project, u.first_name, u.email 
    FROM project_members pm
    JOIN users u ON pm.user_id = u.id
    WHERE pm.project_id = ?
");
$stmtMembers->execute([$projectId]);
$members = $stmtMembers->fetchAll();

// Récupération de tous les élèves pour le formulaire du prof
if ($isProf) {
    $stmtStudents = $pdo->query("SELECT id, first_name, email FROM users WHERE role = 'etudiant' ORDER BY first_name ASC");
    $allStudents = $stmtStudents->fetchAll();
}

include 'includes/header.php';
?>

<div class="flex-between">
    <h1><?= htmlspecialchars($project['title']) ?></h1>
    
    <?php if($isProf): ?>
    <form method="POST" onsubmit="return confirm('⚠️ ATTENTION : Êtes-vous sûr de vouloir supprimer définitivement ce projet ? Toutes les tâches et membres associés seront perdus.');">
        <input type="hidden" name="action" value="delete_project">
        <button type="submit" class="btn" style="background-color: #dc3545; color: white;">🗑️ Supprimer le projet</button>
    </form>
    <?php endif; ?>
</div>

<?php if($error): ?><p class="error" style="color: red; font-weight: bold; margin-bottom:15px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if($success): ?><p class="success" style="color: green; font-weight: bold; margin-bottom:15px;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

<p><span class="badge status-<?= str_replace(' ', '-', $project['status']) ?>"><?= $project['status'] ?></span></p>

<div class="card" style="margin-bottom: 30px;">
    <h3>Description</h3>
    <p style="margin-top: 10px;"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
    <p style="margin-top: 15px;"><strong>📅 Échéance :</strong> <?= $project['end_date'] ?></p>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
    <h2>👥 Membres du projet</h2>
    
    <?php if($isProf): ?>
    <form method="POST" style="display: flex; gap: 10px; align-items: center; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <input type="hidden" name="action" value="add_student">
        <select name="student_id" required style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
            <option value="">Ajouter un élève...</option>
            <?php foreach($allStudents as $student): ?>
                <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['first_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="role_in_project" required style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
            <option value="membre">Membre</option>
            <option value="team_leader">Chef de projet</option>
        </select>
        <button type="submit" class="btn btn-primary">Ajouter</button>
    </form>
    <?php endif; ?>
</div>

<table class="data-table" style="margin-bottom: 30px;">
    <thead>
        <tr>
            <th>Prénom</th>
            <th>Email</th>
            <th>Rôle dans le projet</th>
            <?php if($isProf): ?><th>Action</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php if(empty($members)): ?>
            <tr><td colspan="<?= $isProf ? '4' : '3' ?>" style="text-align:center;">Aucun membre dans ce projet.</td></tr>
        <?php else: ?>
            <?php foreach($members as $m): ?>
            <tr>
                <td><strong><?= htmlspecialchars($m['first_name']) ?></strong></td>
                <td><?= htmlspecialchars($m['email']) ?></td>
                <td>
                    <?php if($m['role_in_project'] == 'team_leader'): ?>
                        <span style="color: #d97706; font-weight: bold;">⭐ Chef de projet</span>
                    <?php else: ?>
                        Membre
                    <?php endif; ?>
                </td>
                
                <?php if($isProf): ?>
                <td>
                    <form method="POST" onsubmit="return confirm('Retirer cet étudiant du projet ?');">
                        <input type="hidden" name="action" value="remove_student">
                        <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-small" style="background-color: #6c757d;">Retirer</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<a href="tasks.php?project_id=<?= $project['id'] ?>" class="btn btn-primary" style="margin-top: 10px;">✅ Voir le Kanban des tâches</a>

<?php include 'includes/footer.php'; ?>