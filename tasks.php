<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$projectId = $_GET['project_id'] ?? null;
if(!$projectId) die("Sélectionnez un projet d'abord.");

// --- TRAITEMENT AJAX : Mise à jour du statut lors du Drag & Drop ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax_update'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $taskId = (int)($_POST['task_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    
    $allowedStatuses = ['à faire', 'en cours', 'terminé'];
    
    if ($taskId && in_array($newStatus, $allowedStatuses)) {
        // Par défaut, on calcule selon le statut
        $progress = 0;
        if ($newStatus === 'terminé') {
            $progress = 100;
        } elseif ($newStatus === 'en cours') {
            // Si un pourcentage personnalisé a été envoyé par le JavaScript, on l'utilise
            if (isset($_POST['progress'])) {
                $progress = max(0, min(100, (int)$_POST['progress']));
            } else {
                $progress = 50; // Valeur de secours
            }
        }

        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, progress = ? WHERE id = ? AND project_id = ?");
        $success = $stmt->execute([$newStatus, $progress, $taskId, $projectId]);
        
        echo json_encode(['success' => $success, 'new_progress' => $progress]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Données invalides ou projet incorrect.']);
    exit;
}

// --- Chargement initial des tâches ---
$stmt = $pdo->prepare("SELECT t.*, u.first_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE project_id = ?");
$stmt->execute([$projectId]);
$tasks = $stmt->fetchAll();

$kanban = ['à faire' => [], 'en cours' => [], 'terminé' => []];
foreach($tasks as $t) {
    $status = $t['status'] ?: 'à faire';
    if (array_key_exists($status, $kanban)) {
        $kanban[$status][] = $t;
    }
}

include 'includes/header.php';
?>
<div class="flex-between">
    <h1>Kanban des Tâches</h1>
    <a href="task_create.php?project_id=<?= $projectId ?>" class="btn btn-primary">+ Nouvelle Tâche</a>
</div>

<div class="kanban-board" style="display: flex; gap: 1rem; align-items: flex-start; margin-top: 1rem; user-select: none;">
    <?php foreach($kanban as $status => $list): ?>
    
    <div class="kanban-column" 
         data-status="<?= htmlspecialchars($status) ?>"
         ondragover="allowDrop(event)" 
         ondragenter="highlightColumn(this)"
         ondragleave="unhighlightColumn(this)"
         ondrop="dropTask(event, this)"
         style="flex: 1; background: #1a1d27; border: 1px solid #2a2d3e; border-radius: 10px; padding: 1rem; min-height: 550px; transition: background 0.2s, border-color 0.2s; display: flex; flex-direction: column;">
        
        <h3 style="color: #e2e8f0; margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem; border-bottom: 2px solid #2a2d3e; padding-bottom: .5rem; pointer-events: none;">
            <?= ucfirst($status) ?> (<span class="task-count"><?= count($list) ?></span>)
        </h3>
        
        <div class="cards-container" style="flex: 1; display: flex; flex-direction: column; gap: 0.75rem; pointer-events: none;">
            <?php foreach($list as $task): ?>
            <?php 
                $prog = max(0, min(100, (int)($task['progress'] ?? 0)));
                $hue = ($prog / 100) * 120;
                $dynamicColor = "hsl($hue, 75%, 45%)";
            ?>
            <div class="kanban-card" 
                 id="task-<?= $task['id'] ?>"
                 data-task-id="<?= $task['id'] ?>"
                 data-current-progress="<?= $prog ?>"
                 draggable="true" 
                 ondragstart="dragStart(event)"
                 ondragend="dragEnd(event)"
                 style="background: #0f1117; border: 1px solid #2a2d3e; border-left: 4px solid <?= $dynamicColor ?>; border-radius: 6px; padding: .85rem; color: #e2e8f0; position: relative; cursor: grab; pointer-events: auto;">
                
                <span class="progress-badge" style="position: absolute; top: .85rem; right: .85rem; font-size: .7rem; background: <?= $dynamicColor ?>20; color: <?= $dynamicColor ?>; font-weight: bold; padding: 2px 6px; border-radius: 10px;">
                    <?= $prog ?>%
                </span>
                <h4 style="margin: 0 3rem 0 0; font-size: .95rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($task['title']) ?></h4>
                <p style="margin: .5rem 0 .25rem 0; font-size: .8rem; color: #64748b;">Priorité : <span style="color: #e2e8f0;"><?= htmlspecialchars($task['priority'] ?? 'Non définie') ?></span></p>
                <p style="margin: 0 0 .5rem 0; font-size: .8rem; color: #64748b;">Assigné à : <span style="color: #e2e8f0;"><?= htmlspecialchars($task['first_name'] ?? 'Non assigné') ?></span></p>
                
                <?php if($task['start_date'] && $task['end_date']): ?>
                    <small style="color:#64748b; font-size: .75rem; display: block; margin-top: .5rem; border-top: 1px solid #2a2d3e; padding-top: .4rem;">
                        📅 <?= date('d/m', strtotime($task['start_date'])) ?> au <?= date('d/m', strtotime($task['end_date'])) ?>
                    </small>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
function dragStart(e) {
    e.dataTransfer.setData("text/plain", e.target.id);
    e.target.style.opacity = "0.4";
    e.target.style.cursor = "grabbing";
}

function dragEnd(e) {
    e.target.style.opacity = "1";
    e.target.style.cursor = "grab";
    document.querySelectorAll('.kanban-column').forEach(unhighlightColumn);
}

function allowDrop(e) {
    e.preventDefault();
}

function highlightColumn(column) {
    column.style.background = "#222634";
    column.style.borderColor = "#7c6af7";
}

function unhighlightColumn(column) {
    column.style.background = "#1a1d27";
    column.style.borderColor = "#2a2d3e";
}

function updateCounts() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        const count = col.querySelectorAll('.kanban-card').length;
        col.querySelector('.task-count').textContent = count;
    });
}

function dropTask(e, column) {
    e.preventDefault();
    unhighlightColumn(column);
    
    const cardId = e.dataTransfer.getData("text/plain");
    const card = document.getElementById(cardId);
    
    if (!card) return;
    
    const taskId = card.getAttribute('data-task-id');
    const newStatus = column.getAttribute('data-status');
    let currentProg = parseInt(card.getAttribute('data-current-progress')) || 0;
    let chosenProgress = null;

    // Si déplacement vers "en cours", on demande le pourcentage à l'utilisateur
    if (newStatus === 'en cours') {
        let userInput = prompt("Entrez le pourcentage d'avancement (0 à 100) :", currentProg);
        
        // Si l'utilisateur clique sur "Annuler", on annule le déplacement
        if (userInput === null) return; 
        
        chosenProgress = parseInt(userInput);
        if (isNaN(chosenProgress) || chosenProgress < 0 || chosenProgress > 100) {
            alert("Veuillez entrer un nombre valide entre 0 et 100.");
            return;
        }
    }

    // Déplacement visuel de la carte
    const container = column.querySelector('.cards-container');
    container.appendChild(card); 
    updateCounts();

    // Préparation des données AJAX
    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('status', newStatus);
    if (chosenProgress !== null) {
        formData.append('progress', chosenProgress);
    }

    // ATTENTION : J'utilise ici "tasks.php" conformément à ton URL fetch précédente
    fetch(`tasks.php?project_id=<?= $projectId ?>&ajax_update=1`, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Erreur de communication serveur');
        return response.json();
    })
    .then(data => {
        if(data.success) {
            const badge = card.querySelector('.progress-badge');
            const newProg = data.new_progress;
            
            // Mise à jour des attributs et styles de la carte
            card.setAttribute('data-current-progress', newProg);
            badge.textContent = newProg + '%';
            
            const hue = (newProg / 100) * 120;
            const dynamicColor = `hsl(${hue}, 75%, 45%)`;
            
            card.style.borderLeftColor = dynamicColor;
            badge.style.color = dynamicColor;
            badge.style.backgroundColor = dynamicColor + '20';
        } else {
            alert("Erreur système : impossible de modifier la tâche en base de données.");
            window.location.reload();
        }
    })
    .catch(error => {
        console.error("Erreur AJAX :", error);
        alert("Connexion perdue avec le serveur. Rechargement de la page...");
        window.location.reload();
    });
}
</script>

<?php include 'includes/footer.php'; ?>