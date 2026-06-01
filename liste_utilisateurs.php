<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Réservé au prof uniquement zuu
requireProf();

try {
    // Récupération des profs (encadrant)
    $stmtProfs = $pdo->prepare("SELECT first_name, email, created_at FROM users WHERE role = 'encadrant' ORDER BY first_name ASC");
    $stmtProfs->execute();
    $profs = $stmtProfs->fetchAll();

    // Récupération des élèves (etudiant)
    $stmtEleves = $pdo->prepare("SELECT first_name, email, created_at FROM users WHERE role = 'etudiant' ORDER BY first_name ASC");
    $stmtEleves->execute();
    $eleves = $stmtEleves->fetchAll();
} catch (PDOException $e) {
    die("Erreur de chargement des listes : " . $e->getMessage());
}

include 'includes/header.php';
?>

<h1>Gestion des Utilisateurs</h1>

<h2 style="margin: 20px 0 10px 0; color: var(--primary);">Professeurs</h2>
<table class="data-table" style="margin-bottom: 40px;">
    <thead><tr><th>Prénom</th><th>Email</th><th>Date d'inscription</th></tr></thead>
    <tbody>
        <?php if(empty($profs)): ?>
            <tr><td colspan="3">Aucun professeur inscrit.</td></tr>
        <?php else: ?>
            <?php foreach($profs as $p): ?>
            <tr><td><strong><?= htmlspecialchars($p['first_name']) ?></strong></td><td><?= htmlspecialchars($p['email']) ?></td><td><?= $p['created_at'] ?></td></tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<h2 style="margin: 20px 0 10px 0; color: #6B7280;">Élèves</h2>
<table class="data-table">
    <thead><tr><th>Prénom</th><th>Email</th><th>Date d'inscription</th></tr></thead>
    <tbody>
        <?php if(empty($eleves)): ?>
            <tr><td colspan="3">Aucun élève inscrit pour le moment.</td></tr>
        <?php else: ?>
            <?php foreach($eleves as $e): ?>
            <tr><td><?= htmlspecialchars($e['first_name']) ?></td><td><?= htmlspecialchars($e['email']) ?></td><td><?= $e['created_at'] ?></td></tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include 'includes/footer.php'; ?>