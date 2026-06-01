<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Projets Étudiants</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="layout">
        <?php if(isLoggedIn()): ?>
        <aside class="sidebar">
            <div class="logo">EduProject</div>
            <nav>
                <a href="dashboard.php">📊 Tableau de bord</a>
                <a href="projects.php">📁 Projets</a>
                <a href="tasks.php">✅ Tâches</a>
                <a href="deliverables.php">📤 Livrables</a>
                 <a href="planning.php">📅 Planning</a> 
                
                <!-- MENU RÉSERVÉ AUX PROFESSEURS ('encadrant') -->
                <?php if(hasRole('encadrant')): ?>
                    <a href="group_create.php">➕ Créer un groupe</a>
                    <a href="group_join.php">🔗 Rejoindre un groupe</a>
                    <a href="liste_utilisateurs.php">👥 Liste Utilisateurs</a>
                    <a href="evaluations.php">⭐ Évaluations</a>
                <?php endif; ?>
                
                <a href="logout.php">🚪 Déconnexion</a>
            </nav>
        </aside>
        <?php endif; ?>
        <main class="main-content">
            <header class="topbar">
                <?php if(isLoggedIn()): ?>
                    <span>Bonjour, <?= htmlspecialchars($_SESSION['first_name']) ?> (<?= ucfirst($_SESSION['role'] == 'encadrant' ? 'Professeur' : 'Élève') ?>)</span>
                <?php endif; ?>
            </header>
            <div class="content">