<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";

if($_SESSION["role"] !== "SECRETAIRE_PRINCIPAL"){
    header("Location: ../auth/login.php");
    exit;
}

$id = $_GET["id"] ?? null;
$action = $_GET["action"] ?? null;

if(!$id || !in_array($action, ["activer", "desactiver"])){
    header("Location: liste_enseignants.php");
    exit;
}

$nouveauStatut = ($action === "activer") ? 1 : 0;

$stmt = $pdo->prepare("
    UPDATE enseignant
    SET actif = :actif
    WHERE id_enseignant = :id_enseignant");

$stmt->execute([
    "actif" => $nouveauStatut,
    "id_enseignant" => $id
]);

header("Location: liste_enseignants.php");
exit;