<?php

$host = 'localhost';
$dbname = 'projet_3a';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $user,
        $pass
    );

    // Gestion des erreurs
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Retour des résultats en tableau associatif
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // L'echo a été supprimé ici pour ne pas bloquer les redirections (header)

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>