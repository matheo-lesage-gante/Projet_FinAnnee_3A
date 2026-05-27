<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();
requireRole(['student', 'team_leader']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $projectId = $_POST['project_id'];
    $file = $_FILES['file'];
    $filename = time() . '_' . basename($file['name']);
    $targetPath = "uploads/livrables/" . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $stmt = $pdo->prepare("INSERT INTO deliverables (project_id, uploaded_by, file_name, file_path) VALUES (?, ?, ?, ?)");
        $stmt->execute([$projectId, $_SESSION['user_id'], $file['name'], $targetPath]);
        header("Location: deliverables.php");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT p.id, p.title FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE pm.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$myProjects = $stmt->fetchAll();

include 'includes/header.php';
?>
<h1>Déposer un livrable</h1>
<form method="POST" enctype="multipart/form-data" class="form-container">
    <label>Projet :</label>
    <select name="project_id" required>
        <?php foreach($myProjects as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
        <?php endforeach; ?>
    </select>
    <label>Fichier (PDF, ZIP, DOCX) :</label>
    <input type="file" name="file" required>
    <button type="submit" class="btn btn-primary">Uploader</button>
</form>
<?php include 'includes/footer.php'; ?>