<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";

if($_SESSION["role"] !== "SECRETAIRE_PRINCIPAL"){
    header("Location: ../auth/login.php");
    exit;
}

$totalEnseignants = $pdo->query("SELECT COUNT(*) FROM enseignant WHERE actif = 1")->fetchColumn();
$totalCours = $pdo->query("SELECT COUNT(*) FROM cours WHERE actif = 1")->fetchColumn();
$totalRessources = $pdo->query("SELECT COUNT(*) FROM ressource_pedagogique WHERE actif = 1")->fetchColumn();
$totalActivites = $pdo->query("SELECT COUNT(*) FROM activite_pedagogique")->fetchColumn();

?>

<?php require_once "../includes/header.php"; ?>

<div class="wrapper">

    <?php require_once "../includes/sidebar_secretaire.php"; ?>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Tableau de bord Secrétaire principal</h1>
                <p>Gestion administrative des enseignants et des activités pédagogiques.</p>
            </div>

            <div class="user-box">
                <span><?= date("d/m/Y") ?></span>
                <strong><?= htmlspecialchars($_SESSION["login"]) ?></strong>
                <small>SECRÉTAIRE PRINCIPAL</small>

            </div>
        </header>

        <section class="content">

            <div class="welcome-card">
                <div>
                    <h2>Bienvenue, <?= htmlspecialchars($_SESSION["login"]) ?>.</h2>
                    <p>
                        Cet espace permet l’enregistrement des enseignants, la gestion des cours
                        et ressources pédagogiques, la saisie et validation des activités,
                        le suivi des volumes horaires ainsi que la préparation des états de paiement.
                    </p>
                </div>

            </div>

            <div class="cards">

                
                    <div class="card">
                        <span class="card-icon purple">👨‍🏫</span>
                        <h3>Enseignants</h3>
                        <p><?= $totalEnseignants ?></p>
                        <small>Enseignant(s) enregistré(s)</small>
                    </div>
                

                
                    <div class="card">
                        <span class="card-icon blue">📘</span>
                        <h3>Cours</h3>
                        <p><?= $totalCours ?></p>
                        <small>Cours enregistré(s)</small>
                    </div>
                
        

                
                    <div class="card">
                        <span class="card-icon orange">📝</span>
                        <h3>Activités pédagogiques</h3>
                        <p><?= $totalActivites ?></p>
                        <small>Activité(s) saisie(s)</small>
                    </div>
                

            </div>

        </section>

        <?php require_once "../includes/footer.php"; ?>

    </main>

</div>