<?php

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
                <h1>Gestion des activités pédagogiques</h1>
                <p>Création, mise à jour et suivi des activités liées aux ressources pédagogiques.</p>
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
                        <div class="module-icon purple">📝</div>

                        <h3>Enregistrer une activité pédagogique</h3>

                        <p>
                            Enregistrer la création ou la mise à jour d’une ressource pédagogique.
                        </p>
                        <a href="creer_activite.php" class="module-link">
                        <span class="module-btn">Accéder </span>
                        </a>
                    </div>
                

                    <div class="module-card">
                        <div class="module-icon blue">📋</div>

                        <h3>Gestion des activités pédagogiques</h3>

                        <p>
                            Consulter, suivre et contrôler les activités pédagogiques enregistrées.
                        </p>
                        <a href="liste_activites.php" class="module-link">
                        <span class="module-btn">Accéder </span>
                        </a>
                    </div>

            </div>

        </section>

        <?php require_once "../includes/footer.php"; ?>

    </main>

</div>