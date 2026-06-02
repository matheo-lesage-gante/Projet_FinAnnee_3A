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

    if ($action === 'create_project') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $end = $_POST['end_date'] ?? '';

        if ($title && $desc && $end) {
            try {
                $stmt = $pdo->prepare("INSERT INTO projects (title, description, end_date, created_by, status) VALUES (?, ?, ?, ?, 'en cours')");
                $stmt->execute([$title, $desc, $end, $userId]);
                $success = "Nouveau projet créé avec succès.";
            } catch (PDOException $e) {
                $error = "Erreur création projet : " . $e->getMessage();
            }
        } else {
            $error = "Veuillez remplir tous les champs.";
        }
    }
}

if ($isProf) {
    $stmt = $pdo->query("SELECT * FROM projects ORDER BY created_at DESC");
    $projects = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT p.* FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE pm.user_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$userId]);
    $projects = $stmt->fetchAll();
}

include 'includes/header.php';
?>

<div class="flex-between" style="margin-bottom: 28px;">
    <div>
        <h1>Projets</h1>
        <p style="color: var(--text-muted); margin-top: 4px;"><?= $isProf ? "Gérez et suivez tous les projets." : "Vos projets assignés." ?></p>
    </div>
    <?php if ($isProf): ?>
        <button onclick="document.getElementById('modal-create').style.display='flex'" class="btn btn-primary" style="display:flex; align-items:center; gap:8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nouveau projet
        </button>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="card" style="border-left: 3px solid #ff453a; color: #ff453a; margin-bottom: 20px; padding: 14px 20px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="card" style="border-left: 3px solid #30d158; color: #30d158; margin-bottom: 20px; padding: 14px 20px;">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<?php if (empty($projects)): ?>
    <div class="card" style="text-align: center; color: var(--text-muted); padding: 60px 20px;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin: 0 auto 16px; display:block; opacity:0.3;"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        Aucun projet à afficher.
    </div>
<?php else: ?>
    <div class="card" style="padding: 0; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <th style="text-align: left; padding: 14px 24px; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em;">Titre</th>
                    <th style="text-align: left; padding: 14px 24px; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em;">Statut</th>
                    <th style="text-align: left; padding: 14px 24px; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em;">Échéance</th>
                    <th style="text-align: right; padding: 14px 24px; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $i => $p): ?>
                <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 16px 24px;">
                        <span style="font-weight: 600; font-size: 0.95rem;"><?= htmlspecialchars($p['title']) ?></span>
                    </td>
                    <td style="padding: 16px 24px;">
                        <?php
                        $statusColors = [
                            'en cours'  => ['bg' => 'rgba(48,209,88,0.12)',  'color' => '#30d158', 'dot' => '#30d158'],
                            'terminé'   => ['bg' => 'rgba(10,132,255,0.12)', 'color' => '#0a84ff', 'dot' => '#0a84ff'],
                            'en attente'=> ['bg' => 'rgba(255,159,10,0.12)', 'color' => '#ff9f0a', 'dot' => '#ff9f0a'],
                        ];
                        $sc = $statusColors[strtolower($p['status'])] ?? ['bg' => 'rgba(255,255,255,0.08)', 'color' => 'var(--text-muted)', 'dot' => '#888'];
                        ?>
                        <span style="display:inline-flex; align-items:center; gap:6px; background:<?= $sc['bg'] ?>; color:<?= $sc['color'] ?>; font-size:0.78rem; font-weight:600; padding:4px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:0.05em;">
                            <span style="width:6px;height:6px;background:<?= $sc['dot'] ?>;border-radius:50%;display:inline-block;"></span>
                            <?= htmlspecialchars($p['status']) ?>
                        </span>
                    </td>
                    <td style="padding: 16px 24px; color: var(--text-muted); font-size: 0.9rem;">
                        <?= date('d/m/Y', strtotime($p['end_date'])) ?>
                    </td>
                    <td style="padding: 16px 24px; text-align: right;">
                        <a href="project_detail.php?id=<?= $p['id'] ?>" class="btn btn-secondary" style="font-size: 0.82rem; padding: 6px 14px;">Détails →</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>


<?php if ($isProf): ?>
<!-- MODAL Créer un projet -->
<div id="modal-create" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(8px); z-index:1000; align-items:center; justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
    <div class="card" style="width:100%; max-width:520px; padding:32px; margin:20px; position:relative;">
        <button onclick="document.getElementById('modal-create').style.display='none'" style="position:absolute; top:20px; right:20px; background:rgba(255,255,255,0.08); border:none; color:var(--text-muted); width:30px; height:30px; border-radius:50%; cursor:pointer; font-size:1rem; display:flex; align-items:center; justify-content:center;">✕</button>
        
        <h2 style="margin:0 0 6px;">Nouveau projet</h2>
        <p style="color:var(--text-muted); font-size:0.88rem; margin:0 0 28px;">Remplissez les informations du projet.</p>

        <form method="POST" style="display:flex; flex-direction:column; gap:18px;">
            <input type="hidden" name="action" value="create_project">

            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:8px;">Titre du projet</label>
                <input type="text" name="title" required placeholder="Ex : Application Mobile..." style="width:100%; padding:10px 14px; background:rgba(255,255,255,0.05); border:1px solid var(--border-color); border-radius:8px; color:var(--text); font-size:0.95rem; outline:none; box-sizing:border-box;" onfocus="this.style.borderColor='rgba(255,255,255,0.3)'" onblur="this.style.borderColor='var(--border-color)'">
            </div>

            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:8px;">Description</label>
                <textarea name="description" required rows="3" placeholder="Décrivez les objectifs du projet..." style="width:100%; padding:10px 14px; background:rgba(255,255,255,0.05); border:1px solid var(--border-color); border-radius:8px; color:var(--text); font-size:0.95rem; outline:none; resize:vertical; box-sizing:border-box; font-family:inherit;" onfocus="this.style.borderColor='rgba(255,255,255,0.3)'" onblur="this.style.borderColor='var(--border-color)'"></textarea>
            </div>

            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:8px;">Date d'échéance</label>
                <input type="date" name="end_date" required style="width:100%; padding:10px 14px; background:rgba(255,255,255,0.05); border:1px solid var(--border-color); border-radius:8px; color:var(--text); font-size:0.95rem; outline:none; box-sizing:border-box;" onfocus="this.style.borderColor='rgba(255,255,255,0.3)'" onblur="this.style.borderColor='var(--border-color)'">
            </div>

            <div style="display:flex; gap:12px; margin-top:8px;">
                <button type="button" onclick="document.getElementById('modal-create').style.display='none'" class="btn btn-secondary" style="flex:1;">Annuler</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">Créer le projet</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>