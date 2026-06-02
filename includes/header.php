<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Projets</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">
</head>
<body>
    <div class="layout">
        <?php if(isLoggedIn()): ?>
        <aside class="sidebar">
            <div class="logo">EduProject</div>
            <nav>
                <a href="dashboard.php">Vue d'ensemble</a>
                <a href="projects.php">Projets</a>
                <a href="tasks.php">Tâches & Kanban</a>
                <a href="deliverables.php">Livrables</a>
                <a href="planning.php">Planning Gantt</a> 
                
                <?php if(hasRole('encadrant')): ?>
                    <a href="liste_utilisateurs.php">Utilisateurs</a>
                    <a href="evaluations.php">Évaluations</a>
                <?php endif; ?>
                
                <a href="logout.php" style="margin-top: 20px; color: #666;">Déconnexion</a>
            </nav>
        </aside>
        <?php endif; ?>
        <main class="main-content">
            <header class="topbar">
                <?php if(isLoggedIn()): ?>
                    <span>Connecté : <?= htmlspecialchars($_SESSION['first_name']) ?> (<?= ucfirst($_SESSION['role'] == 'encadrant' ? 'Professeur' : 'Élève') ?>)</span>
                <?php endif; ?>
            </header>
            <div class="content">