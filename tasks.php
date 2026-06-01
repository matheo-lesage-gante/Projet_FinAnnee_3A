<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$projectId = $_GET['project_id'] ?? null;
if(!$projectId) die("Sélectionnez un projet d'abord.");

// --- ACTION : Créer une sous-catégorie ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_category'])) {
    $catTitle = trim($_POST['category_title'] ?? '');
    if (!empty($catTitle)) {
        $stmt = $pdo->prepare("INSERT INTO categories (project_id, title) VALUES (?, ?)");
        $stmt->execute([$projectId, $catTitle]);
    }
    header("Location: tasks.php?project_id=" . $projectId);
    exit;
}

// --- TRAITEMENT AJAX : Mise à jour du statut lors du Drag & Drop ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax_update'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $taskId = (int)($_POST['task_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    
    $allowedStatuses = ['à faire', 'en cours', 'terminé'];
    
    if ($taskId && in_array($newStatus, $allowedStatuses)) {
        $progress = 0;
        if ($newStatus === 'terminé') {
            $progress = 100;
        } elseif ($newStatus === 'en cours') {
            if (isset($_POST['progress'])) {
                $progress = max(0, min(100, (int)$_POST['progress']));
            } else {
                $progress = 50;
            }
        }

        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, progress = ? WHERE id = ? AND project_id = ?");
        $success = $stmt->execute([$newStatus, $progress, $taskId, $projectId]);
        
        echo json_encode(['success' => $success, 'new_progress' => $progress]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Données invalides.']);
    exit;
}

// --- CHARGEMENT DES DONNÉES ---
$stmt = $pdo->prepare("SELECT * FROM categories WHERE project_id = ? ORDER BY id ASC");
$stmt->execute([$projectId]);
$categories = $stmt->fetchAll(PDO::FETCH_UNIQUE);

$categories[0] = ['id' => 0, 'project_id' => $projectId, 'title' => 'Tâches globales (Sans catégorie)'];

$stmt = $pdo->prepare("SELECT t.*, u.first_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.project_id = ?");
$stmt->execute([$projectId]);
$tasks = $stmt->fetchAll();

// --- CALCULS DES AVANCEMENTS PONDÉRÉS ---
$coefficients = ['haute' => 2.0, 'moyenne' => 1.5, 'basse' => 1.0];

$catCalculations = [];
foreach ($categories as $catId => $cat) {
    $catCalculations[$catId] = ['sum_weighted' => 0, 'sum_coef' => 0, 'progress' => 0];
}

foreach ($tasks as $t) {
    $catId = (int)($t['category_id'] ?? 0);
    if (!isset($catCalculations[$catId])) { $catId = 0; }
    
    $priority = mb_strtolower(trim($t['priority'] ?? 'basse'));
    $coef = $coefficients[$priority] ?? 1.0;
    $taskProgress = max(0, min(100, (int)($t['progress'] ?? 0)));
    
    $catCalculations[$catId]['sum_weighted'] += ($taskProgress * $coef);
    $catCalculations[$catId]['sum_coef'] += $coef;
}

$projectWeightedSum = 0;
$projectTotalWeights = 0;

foreach ($catCalculations as $catId => $data) {
    if ($data['sum_coef'] > 0) {
        $catCalculations[$catId]['progress'] = round($data['sum_weighted'] / $data['sum_coef']);
        $projectWeightedSum += ($catCalculations[$catId]['progress'] * $data['sum_coef']);
        $projectTotalWeights += $data['sum_coef'];
    }
}

$projectProgress = 0;
if ($projectTotalWeights > 0) {
    $projectProgress = round($projectWeightedSum / $projectTotalWeights);
}

// --- FILTRE ---
$currentCatFilter = isset($_GET['filter_category']) ? (int)$_GET['filter_category'] : null;

$kanban = ['à faire' => [], 'en cours' => [], 'terminé' => []];
foreach($tasks as $t) {
    $catId = (int)($t['category_id'] ?? 0);
    if ($currentCatFilter !== null && $catId !== $currentCatFilter) { continue; }
    
    $status = $t['status'] ?: 'à faire';
    if (array_key_exists($status, $kanban)) {
        $kanban[$status][] = $t;
    }
}

include 'includes/header.php';
?>

<div class="project-header" style="background: #1a1d27; border: 1px solid #2a2d3e; border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
        <div>
            <h1 style="margin: 0; color: #e2e8f0; font-size: 1.75rem;">Tableau de Bord & Kanban</h1>
            <p style="margin: 0.25rem 0 0 0; color: #64748b; font-size: 0.9rem;">Avancement global (Moyenne des sous-catégories pondérées)</p>
        </div>
        <div style="display: flex; gap: 1rem;">
            <form method="POST" style="background: #0f1117; padding: 0.5rem; border: 1px solid #2a2d3e; border-radius: 6px; display: flex; gap: 0.5rem;">
                <input type="text" name="category_title" placeholder="Nouvelle sous-catégorie..." required style="background: transparent; border: none; color: #e2e8f0; outline: none; font-size: 0.85rem; padding: 0 0.5rem;">
                <button type="submit" name="action_create_category" style="background: #7c6af7; border: none; color: white; padding: 0.3rem 0.7rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">+</button>
            </form>
            <a href="task_create.php?project_id=<?= $projectId ?>" class="btn btn-primary" style="padding: 0.6rem 1.2rem; background: #10b981; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 500; font-size: 0.9rem;">+ Nouvelle Tâche</a>
        </div>
    </div>

    <div style="display: flex; align-items: center; gap: 1rem;">
        <span style="color: #e2e8f0; font-weight: 600; font-size: 0.9rem; min-width: 110px;">GLOBAL PROJET :</span>
        <div style="flex: 1; background: #0f1117; height: 16px; border-radius: 8px; border: 1px solid #2a2d3e; overflow: hidden;">
            <div style="width: <?= $projectProgress ?>%; height: 100%; background: linear-gradient(90deg, #7c6af7, #10b981); transition: width 0.4s;"></div>
        </div>
        <span style="color: #10b981; font-weight: bold; font-size: 1.2rem; min-width: 45px; text-align: right;"><?= $projectProgress ?>%</span>
    </div>
</div>

<div style="background: #1a1d27; border: 1px solid #2a2d3e; border-radius: 10px; padding: 1.2rem; margin-bottom: 2rem;">
    <h3 style="color: #e2e8f0; margin-top: 0; margin-bottom: 1rem; font-size: 1rem;">📊 Avancement des Sous-Catégories</h3>
    <div style="display: flex; flex-direction: column; gap: 0.8rem;">
        <div style="display: flex; align-items: center; gap: 1rem; padding: 0.4rem; border-radius: 6px; background: <?= $currentCatFilter === null ? '#222634' : 'transparent' ?>;">
            <a href="tasks.php?project_id=<?= $projectId ?>" style="color: #e2e8f0; text-decoration: none; font-size: 0.85rem; min-width: 250px; font-weight: <?= $currentCatFilter === null ? 'bold' : 'normal' ?>;">
                📂 [VOIR TOUTES LES CATÉGORIES]
            </a>
        </div>

        <?php foreach ($categories as $catId => $cat): 
            if ($catId === 0 && $catCalculations[0]['sum_coef'] == 0) continue;
            $catProg = $catCalculations[$catId]['progress'];
            $isFiltered = ($currentCatFilter === $catId);
        ?>
            <div style="display: flex; align-items: center; gap: 1rem; padding: 0.5rem; border-radius: 6px; background: <?= $isFiltered ? '#222634' : 'transparent' ?>; border: 1px solid <?= $isFiltered ? '#7c6af7' : 'transparent' ?>;">
                <a href="tasks.php?project_id=<?= $projectId ?>&filter_category=<?= $catId ?>" style="color: #e2e8f0; text-decoration: none; font-size: 0.85rem; min-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    📁 <?= htmlspecialchars($cat['title']) ?> 
                </a>
                <div style="flex: 1; background: #0f1117; height: 8px; border-radius: 4px; overflow: hidden;">
                    <div style="width: <?= $catProg ?>%; height: 100%; background: #7c6af7;"></div>
                </div>
                <span style="color: #e2e8f0; font-size: 0.85rem; font-weight: 600; min-width: 40px; text-align: right;"><?= $catProg ?>%</span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<h2 style="color: #e2e8f0; font-size: 1.2rem; margin-bottom: 1rem;">
    📋 Kanban : <?= $currentCatFilter !== null ? htmlspecialchars($categories[$currentCatFilter]['title']) : 'Toutes les catégories' ?>
</h2>

<div class="kanban-board" style="display: flex; gap: 1rem; align-items: flex-start; user-select: none;">
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
                $taskCatTitle = $categories[(int)($task['category_id'] ?? 0)]['title'] ?? 'Globale';
            ?>
            <div class="kanban-card" 
                 id="task-<?= $task['id'] ?>"
                 data-task-id="<?= $task['id'] ?>"
                 data-current-progress="<?= $prog ?>"
                 draggable="true" 
                 ondragstart="dragStart(event)"
                 ondragend="dragEnd(event)"
                 style="background: #0f1117; border: 1px solid #2a2d3e; border-left: 4px solid <?= $dynamicColor ?>; border-radius: 6px; padding: .85rem; color: #e2e8f0; cursor: grab; pointer-events: auto; display: flex; flex-direction: column; gap: 0.4rem;">
                
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem;">
                    <h4 style="margin: 0; font-size: .95rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; flex: 1;"><?= htmlspecialchars($task['title']) ?></h4>
                    <a href="task_edit.php?task_id=<?= $task['id'] ?>" style="color: #64748b; text-decoration: none; font-size: 0.85rem;" title="Modifier la tâche">✏️</a>
                </div>
                
                <?php if($currentCatFilter === null): ?>
                    <div>
                        <span style="display:inline-block; font-size: 0.7rem; background: #2a2d3e; color: #cbd5e1; padding: 1px 6px; border-radius: 4px;">
                            📁 <?= htmlspecialchars($taskCatTitle) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <p style="margin: 0; font-size: .8rem; color: #64748b;">Priorité : <span style="color: #e2e8f0;"><?= htmlspecialchars($task['priority'] ?? 'Non définie') ?></span></p>
                <p style="margin: 0; font-size: .8rem; color: #64748b;">Assigné à : <span style="color: #e2e8f0;"><?= htmlspecialchars($task['first_name'] ?? 'Non assigné') ?></span></p>
                
                <div style="margin-top: .4rem; border-top: 1px solid #2a2d3e; padding-top: .5rem; display: flex; justify-content: space-between; align-items: center;">
                    
                    <span style="color:#64748b; font-size: .75rem;">
                        <?php if($task['start_date'] && $task['end_date']): ?>
                            📅 <?= date('d/m', strtotime($task['start_date'])) ?> au <?= date('d/m', strtotime($task['end_date'])) ?>
                        <?php else: ?>
                            Pas de date
                        <?php endif; ?>
                    </span>

                    <span class="progress-badge" style="font-size: .75rem; background: <?= $dynamicColor ?>20; color: <?= $dynamicColor ?>; font-weight: bold; padding: 2px 8px; border-radius: 10px; transition: all 0.3s;">
                        <?= $prog ?>%
                    </span>
                </div>

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

function allowDrop(e) { e.preventDefault(); }
function highlightColumn(column) { column.style.background = "#222634"; column.style.borderColor = "#7c6af7"; }
function unhighlightColumn(column) { column.style.background = "#1a1d27"; column.style.borderColor = "#2a2d3e"; }

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

    if (newStatus === 'en cours') {
        let userInput = prompt("Entrez le pourcentage d'avancement (0 à 100) :", currentProg);
        if (userInput === null) return; 
        
        chosenProgress = parseInt(userInput);
        if (isNaN(chosenProgress) || chosenProgress < 0 || chosenProgress > 100) {
            alert("Veuillez entrer un nombre valide entre 0 et 100.");
            return;
        }
    }

    const container = column.querySelector('.cards-container');
    container.appendChild(card); 
    updateCounts();

    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('status', newStatus);
    if (chosenProgress !== null) {
        formData.append('progress', chosenProgress);
    }

    fetch(`tasks.php?project_id=<?= $projectId ?>&ajax_update=1`, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Erreur serveur');
        return response.json();
    })
    .then(data => {
        if(data.success) {
            window.location.reload();
        } else {
            alert("Erreur système.");
            window.location.reload();
        }
    })
    .catch(error => {
        console.error("Erreur:", error);
        window.location.reload();
    });
}
</script>

<?php include 'includes/footer.php'; ?>