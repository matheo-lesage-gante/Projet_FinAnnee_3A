<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function hasRole($roles) {
    if (!isLoggedIn()) return false;
    return in_array($_SESSION['role'], (array)$roles);
}

// On vérifie si c'est un 'encadrant' (notre professeur)
function requireProf() {
    requireLogin();
    if ($_SESSION['role'] !== 'encadrant') {
        die("Accès refusé. Cette action est réservée uniquement aux professeurs.");
    }
}
?>