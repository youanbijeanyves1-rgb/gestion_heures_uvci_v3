<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";

if($_SESSION["role"] !== "ADMINISTRATEUR"){
    header("Location: ../auth/login.php");
    exit;
}

$totalUtilisateurs = $pdo->query("SELECT COUNT(*) FROM utilisateur")->fetchColumn();
$totalAnnees = $pdo->query("SELECT COUNT(*) FROM annee_academique")->fetchColumn();
$totalParametres = $pdo->query("SELECT COUNT(*) FROM parametre_calcul WHERE actif = 1")->fetchColumn();
$totalTaux = $pdo->query("SELECT COUNT(*) FROM taux_horaire WHERE actif = 1")->fetchColumn();

?>

<?php require_once "../includes/header.php"; ?>

<div class="wrapper">

    <?php require_once "../includes/sidebar_admin.php"; ?>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Tableau de bord </h1>
                <p>Administration et supervision du système</p>
            </div>

            <div class="user-box">
                <span><?= date("d/m/Y") ?></span>
                <small>ADMINISTRATEUR</small>
            </div>
        </header>

        <section class="content">

            <div class="welcome-card">
                <div>
                    <h2>Bienvenue, <?= htmlspecialchars($_SESSION["login"]) ?>.</h2>
                    <p>
                        Vous êtes connecté à l’espace administrateur.
                        Cet espace permet de gérer les comptes, les années académiques,
                        les paramètres de calcul, les taux horaires et la supervision du système.
                    </p>
                </div>

            </div>

            <div class="cards">

                    <div class="card">
                        <span class="card-icon purple">👤</span>
                        <h3>Comptes utilisateurs</h3>
                        <p><?= $totalUtilisateurs ?></p>
                        <small>Compte(s)</small>
                    </div>
            

                
                    <div class="card">
                        <span class="card-icon green">📅</span>
                        <h3>Années académiques</h3>
                        <p><?= $totalAnnees ?></p>
                        <small>Paramétrer les années</small>
                    </div>
                </a>

                
                    <div class="card">
                        <span class="card-icon blue">⚙️</span>
                        <h3>Paramètres de calcul</h3>
                        <p><?= $totalParametres ?></p>
                        <small>Configurer les coefficients</small>
                    </div>
                </a>

                
                    <div class="card">
                        <span class="card-icon orange">💰</span>
                        <h3>Taux horaires</h3>
                        <p><?= $totalTaux ?></p>
                        <small>Gérer les taux actifs</small>
                    </div>
                </a>

            </div>

        </section>

        <?php require_once "../includes/footer.php"; ?>

    </main>

</div>