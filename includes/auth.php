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

function requireRole($roles) {
    if (!hasRole($roles)) {
        die("Accès refusé. Vous n'avez pas les permissions nécessaires.");
    }
}
?>