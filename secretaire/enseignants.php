<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "../auth/verifier_session.php";

if($_SESSION["role"] !== "SECRETAIRE_PRINCIPAL"){
    header("Location: ../auth/login.php");
    exit;
}

?>

<?php require_once "../includes/header.php"; ?>

<div class="wrapper">

    <?php require_once "../includes/sidebar_secretaire.php"; ?>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Gestion des enseignants</h1>
                <p>Création, consultation, modification et désactivation des enseignants.</p>
            </div>

            <div class="user-box">
                <span><?= date("d/m/Y") ?></span>
                <strong><?= htmlspecialchars($_SESSION["login"]) ?></strong>
                <small>SECRÉTAIRE PRINCIPAL</small>
            </div>
        </header>

        <section class="content">

            <div class="module-grid">

                    <div class="module-card">
                        <div class="module-icon purple">👨‍🏫</div>

                        <h3>Création d’un enseignant</h3>

                        <p>
                            Enregistrer un enseignant avec son grade, son statut,
                            son département, son taux horaire et ses coordonnées.
                        </p>
                        <a href="creer_enseignant.php" class="module-link">
                        <span class="module-btn">Accéder</span>
                        </a>
                    </div>
                

            
                    <div class="module-card">
                        <div class="module-icon blue">📋</div>

                        <h3>Suivi des enseignants</h3>

                        <p>
                            Consulter, modifier, désactiver ou réactiver les enseignants enregistrés.
                        </p>
                        <a href="liste_enseignants.php" class="module-link">
                        <span class="module-btn">Accéder</span>
                        </a>
                    </div>
                

            </div>

        </section>

        <?php require_once "../includes/footer.php"; ?>

    </main>

</div>