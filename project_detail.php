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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isProf) {
    $action = $_POST['action'] ?? '';

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
                    $success = "Membre ajouté avec succès.";
                }
            } catch (PDOException $e) {
                $error = "Erreur : " . $e->getMessage();
            }
        }
    } elseif ($action === 'remove_student') {
        $memberId = $_POST['member_id'] ?? '';
        if ($memberId) {
            $stmt = $pdo->prepare("DELETE FROM project_members WHERE id = ?");
            $stmt->execute([$memberId]);
            $success = "Membre retiré du projet.";
        }
    } elseif ($action === 'delete_project') {
        try {
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            header('Location: projects.php');
            exit;
        } catch (PDOException $e) {
            $error = "Erreur suppression : " . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) die("Projet introuvable.");

$stmtMembers = $pdo->prepare("
    SELECT pm.id as member_id, pm.role_in_project, u.first_name, u.email
    FROM project_members pm
    JOIN users u ON pm.user_id = u.id
    WHERE pm.project_id = ?
");
$stmtMembers->execute([$projectId]);
$members = $stmtMembers->fetchAll();

if ($isProf) {
    $stmtStudents = $pdo->query("SELECT id, first_name, email FROM users WHERE role = 'etudiant' ORDER BY first_name ASC");
    $allStudents = $stmtStudents->fetchAll();
}

include 'includes/header.php';
?>

<!-- Header breadcrumb -->
<div style="display:flex; align-items:center; gap:8px; color:var(--text-muted); font-size:0.85rem; margin-bottom:24px;">
    <a href="projects.php" style="color:var(--text-muted); text-decoration:none; transition:color 0.15s;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text-muted)'">Projets</a>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
    <span><?= htmlspecialchars($project['title']) ?></span>
</div>

<!-- Title row -->
<div class="flex-between" style="margin-bottom:28px; align-items:flex-start;">
    <div>
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
            <h1 style="margin:0;"><?= htmlspecialchars($project['title']) ?></h1>
            <?php
            $statusColors = [
                'en cours'   => ['bg' => 'rgba(48,209,88,0.12)',  'color' => '#30d158'],
                'terminé'    => ['bg' => 'rgba(10,132,255,0.12)', 'color' => '#0a84ff'],
                'en attente' => ['bg' => 'rgba(255,159,10,0.12)', 'color' => '#ff9f0a'],
            ];
            $sc = $statusColors[strtolower($project['status'])] ?? ['bg' => 'rgba(255,255,255,0.08)', 'color' => 'var(--text-muted)'];
            ?>
            <span style="background:<?= $sc['bg'] ?>; color:<?= $sc['color'] ?>; font-size:0.75rem; font-weight:700; padding:4px 12px; border-radius:20px; text-transform:uppercase; letter-spacing:0.06em;"><?= htmlspecialchars($project['status']) ?></span>
        </div>
        <p style="color:var(--text-muted); font-size:0.85rem; margin:0;">Échéance : <?= date('d/m/Y', strtotime($project['end_date'])) ?></p>
    </div>

    <?php if ($isProf): ?>
    <div style="display:flex; gap:10px;">
        <a href="tasks.php?project_id=<?= $project['id'] ?>" class="btn btn-primary" style="display:flex; align-items:center; gap:7px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Kanban
        </a>
        <button onclick="if(confirm('Supprimer définitivement ce projet ?')){document.getElementById('form-delete').submit()}" class="btn" style="background:rgba(255,69,58,0.12); color:#ff453a; border:1px solid rgba(255,69,58,0.25); display:flex; align-items:center; gap:7px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3,6 5,6 21,6"/><path d="M19,6l-1,14H6L5,6"/><path d="M10,11v6"/><path d="M14,11v6"/><path d="M9,6V4h6v2"/></svg>
            Supprimer
        </button>
        <form id="form-delete" method="POST" style="display:none;"><input type="hidden" name="action" value="delete_project"></form>
    </div>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="card" style="border-left:3px solid #ff453a; color:#ff453a; margin-bottom:20px; padding:14px 20px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="card" style="border-left:3px solid #30d158; color:#30d158; margin-bottom:20px; padding:14px 20px;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Description card -->
<div class="card" style="margin-bottom:24px;">
    <p style="font-size:0.75rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em; margin:0 0 12px;">Description</p>
    <p style="color:var(--text); line-height:1.7; margin:0;"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
</div>

<!-- Members section -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <div>
        <h2 style="margin:0 0 4px; font-size:1.1rem;">Membres</h2>
        <p style="color:var(--text-muted); font-size:0.85rem; margin:0;"><?= count($members) ?> participant<?= count($members) > 1 ? 's' : '' ?></p>
    </div>
    <?php if ($isProf): ?>
    <button onclick="document.getElementById('modal-add').style.display='flex'" class="btn btn-secondary" style="display:flex; align-items:center; gap:7px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Ajouter un membre
    </button>
    <?php endif; ?>
</div>

<div class="card" style="padding:0; overflow:hidden;">
    <?php if (empty($members)): ?>
        <div style="text-align:center; color:var(--text-muted); padding:48px 20px; font-size:0.9rem;">Aucun membre dans ce projet.</div>
    <?php else: ?>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid var(--border-color);">
                    <th style="text-align:left; padding:13px 24px; font-size:0.73rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em;">Prénom</th>
                    <th style="text-align:left; padding:13px 24px; font-size:0.73rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em;">Email</th>
                    <th style="text-align:left; padding:13px 24px; font-size:0.73rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em;">Rôle</th>
                    <?php if ($isProf): ?>
                    <th style="text-align:right; padding:13px 24px; font-size:0.73rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em;">Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                <tr style="border-bottom:1px solid var(--border-color); transition:background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
                    <td style="padding:15px 24px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:32px; height:32px; border-radius:50%; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; font-size:0.85rem; font-weight:600; flex-shrink:0;">
                                <?= strtoupper(substr($m['first_name'], 0, 1)) ?>
                            </div>
                            <span style="font-weight:500;"><?= htmlspecialchars($m['first_name']) ?></span>
                        </div>
                    </td>
                    <td style="padding:15px 24px; color:var(--text-muted); font-size:0.88rem;"><?= htmlspecialchars($m['email']) ?></td>
                    <td style="padding:15px 24px;">
                        <?php if ($m['role_in_project'] === 'team_leader'): ?>
                            <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(255,159,10,0.12); color:#ff9f0a; font-size:0.78rem; font-weight:600; padding:3px 10px; border-radius:20px;">
                                ⭐ Chef de projet
                            </span>
                        <?php else: ?>
                            <span style="color:var(--text-muted); font-size:0.88rem;">Membre</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($isProf): ?>
                    <td style="padding:15px 24px; text-align:right;">
                        <form method="POST" onsubmit="return confirm('Retirer ce membre du projet ?')" style="display:inline;">
                            <input type="hidden" name="action" value="remove_student">
                            <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                            <button type="submit" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:0.82rem; padding:5px 10px; border-radius:6px; transition:all 0.15s;" onmouseover="this.style.background='rgba(255,69,58,0.1)';this.style.color='#ff453a'" onmouseout="this.style.background='none';this.style.color='var(--text-muted)'">Retirer</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (!$isProf): ?>
<div style="margin-top:20px;">
    <a href="tasks.php?project_id=<?= $project['id'] ?>" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:8px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Voir le Kanban
    </a>
</div>
<?php endif; ?>


<!-- MODAL Ajouter un membre -->
<?php if ($isProf): ?>
<div id="modal-add" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(8px); z-index:1000; align-items:center; justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
    <div class="card" style="width:100%; max-width:460px; padding:32px; margin:20px; position:relative;">
        <button onclick="document.getElementById('modal-add').style.display='none'" style="position:absolute; top:20px; right:20px; background:rgba(255,255,255,0.08); border:none; color:var(--text-muted); width:30px; height:30px; border-radius:50%; cursor:pointer; font-size:1rem; display:flex; align-items:center; justify-content:center; line-height:1;">✕</button>

        <h2 style="margin:0 0 6px;">Ajouter un membre</h2>
        <p style="color:var(--text-muted); font-size:0.88rem; margin:0 0 28px;">Sélectionnez un étudiant et son rôle.</p>

        <form method="POST" style="display:flex; flex-direction:column; gap:18px;">
            <input type="hidden" name="action" value="add_student">

            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:8px;">Étudiant</label>
                <select name="student_id" required style="width:100%; padding:10px 14px; background:rgba(255,255,255,0.05); border:1px solid var(--border-color); border-radius:8px; color:var(--text); font-size:0.95rem; outline:none; box-sizing:border-box; appearance:none;">
                    <option value="">Choisir un étudiant...</option>
                    <?php foreach ($allStudents as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['first_name']) ?> — <?= htmlspecialchars($s['email']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:8px;">Rôle dans le projet</label>
                <select name="role_in_project" required style="width:100%; padding:10px 14px; background:rgba(255,255,255,0.05); border:1px solid var(--border-color); border-radius:8px; color:var(--text); font-size:0.95rem; outline:none; box-sizing:border-box; appearance:none;">
                    <option value="membre">Membre</option>
                    <option value="team_leader">Chef de projet</option>
                </select>
            </div>

            <div style="display:flex; gap:12px; margin-top:8px;">
                <button type="button" onclick="document.getElementById('modal-add').style.display='none'" class="btn btn-secondary" style="flex:1;">Annuler</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">Ajouter</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>