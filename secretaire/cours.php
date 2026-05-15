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
                <h1>Gestion des cours</h1>
                <p>
                    Création, consultation et gestion des cours de l’UVCI.
                </p>
            </div>

            <div class="user-box">
                <span><?= date("d/m/Y") ?></span>
                <strong><?= htmlspecialchars($_SESSION["login"]) ?></strong>
                <small>SECRÉTAIRE PRINCIPAL</small>
            </div>

        </header>

        <section class="cards-container">

            <!-- CREATION COURS -->

            <div class="dashboard-card">

                <div class="card-icon blue">
                    📘
                </div>

                <h3>Création d’un cours</h3>

                <p>
                    Enregistrer un cours, l’affecter à un enseignant
                    et le rattacher à une filière.
                </p>

                <a href="creer_cours.php" class="btn-card">
                    Accéder 
                </a>

            </div>

            <!-- GESTION COURS -->

            <div class="dashboard-card">

                <div class="card-icon green">
                    📚
                </div>

                <h3>Gestion des cours</h3>

                <p>
                    Consulter, modifier, activer ou désactiver
                    les cours enregistrés.
                </p>

                <a href="liste_cours.php" class="btn-card">
                    Accéder 
                </a>

            </div>

        </section>

        <?php require_once "../includes/footer.php"; ?>

    </main>

</div>