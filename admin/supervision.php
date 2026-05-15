<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";

if($_SESSION["role"] !== "ADMINISTRATEUR"){
    header("Location: ../auth/login.php");
    exit;
}

$totalUtilisateurs = $pdo->query("SELECT COUNT(*) FROM utilisateur")->fetchColumn();
$totalEnseignants = $pdo->query("SELECT COUNT(*) FROM enseignant")->fetchColumn();
$totalCours = $pdo->query("SELECT COUNT(*) FROM cours")->fetchColumn();
$totalRessources = $pdo->query("SELECT COUNT(*) FROM ressource_pedagogique")->fetchColumn();

$totalActivites = $pdo->query("SELECT COUNT(*) FROM activite_pedagogique")->fetchColumn();

$activitesEnAttente = $pdo->query("
    SELECT COUNT(*) 
    FROM activite_pedagogique 
    WHERE statut_validation = 'EN_ATTENTE'
")->fetchColumn();

$activitesValidees = $pdo->query("
    SELECT COUNT(*) 
    FROM activite_pedagogique 
    WHERE statut_validation = 'VALIDEE'
")->fetchColumn();

$activitesRejetees = $pdo->query("
    SELECT COUNT(*) 
    FROM activite_pedagogique 
    WHERE statut_validation = 'REJETEE'
")->fetchColumn();

$volumeTotalValide = $pdo->query("
    SELECT COALESCE(SUM(volume_horaire_calcule), 0)
    FROM activite_pedagogique
    WHERE statut_validation = 'VALIDEE'
")->fetchColumn();

$anneeActive = $pdo->query("
    SELECT libelle_annee
    FROM annee_academique
    WHERE est_active = 1
    LIMIT 1
")->fetchColumn();

$derniersUtilisateurs = $pdo->query("
    SELECT 
        u.login,
        u.actif,
        u.date_creation,
        r.libelle_role
    FROM utilisateur u
    JOIN role r ON r.id_role = u.id_role
    ORDER BY u.date_creation DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$dernieresActivites = $pdo->query("
    SELECT 
        ap.date_saisie,
        ap.type_activite,
        ap.statut_validation,
        ap.volume_horaire_calcule,
        e.nom,
        e.prenoms,
        c.intitule_cours
    FROM activite_pedagogique ap
    JOIN enseignant e ON e.id_enseignant = ap.id_enseignant
    JOIN cours c ON c.id_cours = ap.id_cours
    ORDER BY ap.date_saisie DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require_once "../includes/header.php"; ?>

<div class="wrapper">

    <?php require_once "../includes/sidebar_admin.php"; ?>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Supervision générale</h1>
                <p>Vue globale de l’ensemble du système de gestion des heures.</p>
            </div>

            <div class="user-box">
                <span><?= date("d/m/Y") ?></span>
                <strong><?= htmlspecialchars($_SESSION["login"]) ?></strong>
                <small>ADMINISTRATEUR</small>

            </div>
        </header>

        <section class="content">

            <div class="welcome-card">
                <h2>Tableau de supervision</h2>
                <p>
                    Année académique active :
                    <strong>
                        <?= htmlspecialchars($anneeActive ?: "Aucune année active définie") ?>
                    </strong>
                </p>
            </div>

            <div class="cards">

                <div class="card">
                    <h3>Utilisateurs</h3>
                    <p><?= (int)$totalUtilisateurs ?></p>
                </div>

                <div class="card">
                    <h3>Enseignants</h3>
                    <p><?= (int)$totalEnseignants ?></p>
                </div>

                <div class="card">
                    <h3>Cours</h3>
                    <p><?= (int)$totalCours ?></p>
                </div>

                <div class="card">
                    <h3>Ressources</h3>
                    <p><?= (int)$totalRessources ?></p>
                </div>

                <div class="card">
                    <h3>Activités totales</h3>
                    <p><?= (int)$totalActivites ?></p>
                </div>

                <div class="card">
                    <h3>En attente</h3>
                    <p><?= (int)$activitesEnAttente ?></p>
                </div>

                <div class="card">
                    <h3>Validées</h3>
                    <p><?= (int)$activitesValidees ?></p>
                </div>

                <div class="card">
                    <h3>Volume validé</h3>
                    <p><?= number_format($volumeTotalValide, 2, ',', ' ') ?></p>
                    <small>heures</small>
                </div>

            </div>

            <br>

            <div class="table-card">

                <h2>État des validations</h2>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Statut</th>
                            <th>Nombre d’activités</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td><span class="badge warning">EN ATTENTE</span></td>
                            <td><?= (int)$activitesEnAttente ?></td>
                        </tr>

                        <tr>
                            <td><span class="badge success">VALIDÉES</span></td>
                            <td><?= (int)$activitesValidees ?></td>
                        </tr>

                        <tr>
                            <td><span class="badge danger">REJETÉES</span></td>
                            <td><?= (int)$activitesRejetees ?></td>
                        </tr>
                    </tbody>
                </table>

            </div>

            <br>

            <div class="table-card">

                <h2>Dernières activités pédagogiques enregistrées</h2>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Enseignant</th>
                            <th>Cours</th>
                            <th>Type</th>
                            <th>Volume</th>
                            <th>Statut</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if(count($dernieresActivites) > 0): ?>

                            <?php foreach($dernieresActivites as $a): ?>
                                <tr>
                                    <td><?= date("d/m/Y", strtotime($a["date_saisie"])) ?></td>

                                    <td>
                                        <?= htmlspecialchars($a["nom"] . " " . $a["prenoms"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($a["intitule_cours"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($a["type_activite"]) ?>
                                    </td>

                                    <td>
                                        <?= number_format($a["volume_horaire_calcule"], 2, ',', ' ') ?> h
                                    </td>

                                    <td>
                                        <?php if($a["statut_validation"] === "VALIDEE"): ?>
                                            <span class="badge success">VALIDÉE</span>
                                        <?php elseif($a["statut_validation"] === "REJETEE"): ?>
                                            <span class="badge danger">REJETÉE</span>
                                        <?php else: ?>
                                            <span class="badge warning">EN ATTENTE</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="empty">
                                    Aucune activité enregistrée.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>

            </div>

            <br>

            <div class="table-card">

                <h2>Derniers utilisateurs créés</h2>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Login</th>
                            <th>Rôle</th>
                            <th>État</th>
                            <th>Date création</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if(count($derniersUtilisateurs) > 0): ?>

                            <?php foreach($derniersUtilisateurs as $u): ?>
                                <tr>
                                    <td><?= htmlspecialchars($u["login"]) ?></td>

                                    <td><?= htmlspecialchars($u["libelle_role"]) ?></td>

                                    <td>
                                        <?php if((int)$u["actif"] === 1): ?>
                                            <span class="badge success">ACTIF</span>
                                        <?php else: ?>
                                            <span class="badge danger">INACTIF</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= date("d/m/Y", strtotime($u["date_creation"])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="4" class="empty">
                                    Aucun utilisateur enregistré.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>

            </div>

        </section>

        <?php require_once "../includes/footer.php"; ?>

    </main>

</div>