<?php

require_once "../auth/verifier_session.php";

if($_SESSION["role"] !== "ADMINISTRATEUR"){
    header("Location: ../auth/login.php");
    exit;
}

$date = date("Y-m-d_H-i-s");

$backupDir = "C:\\xampp\\htdocs\\gestion_heures_uvci\\sauvegardes";
$backupFile = $backupDir . "\\gestion_heures_uvci_" . $date . ".sql";
$errorFile = $backupDir . "\\erreur_sauvegarde_" . $date . ".txt";

$mysqlDump = "C:\\xampp\\mysql\\bin\\mysqldump.exe";

$dbHost = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "gestion_heures_uvci";

if(!is_dir($backupDir)){
    mkdir($backupDir, 0777, true);
}

if(!file_exists($mysqlDump)){
    die("Erreur : mysqldump.exe introuvable.");
}

$command = "\"$mysqlDump\""
    . " --host=$dbHost"
    . " --user=$dbUser";

if($dbPassword !== ""){
    $command .= " --password=$dbPassword";
}

$command .= " --databases $dbName";
$command .= " --result-file=\"$backupFile\"";
$command .= " 2> \"$errorFile\"";

exec($command, $output, $result);

if($result === 0 && file_exists($backupFile) && filesize($backupFile) > 0){
    echo "<h2>✅ Sauvegarde automatique réussie</h2>";
    echo "<p>Fichier généré :</p>";
    echo "<pre>" . htmlspecialchars($backupFile) . "</pre>";
}else{
    echo "<h2>❌ Erreur lors de la sauvegarde</h2>";
    echo "<p>Code erreur : " . htmlspecialchars($result) . "</p>";

    if(file_exists($errorFile)){
        echo "<h3>Détail de l’erreur :</h3>";
        echo "<pre>" . htmlspecialchars(file_get_contents($errorFile)) . "</pre>";
    }
}