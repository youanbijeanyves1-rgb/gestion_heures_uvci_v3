<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";

if($_SESSION["role"] !== "SECRETAIRE_PRINCIPAL"){
    header("Location: ../auth/login.php");
    exit;
}

$recherche = trim($_GET["recherche"] ?? "");

$sql = "
    SELECT 
        e.id_enseignant,
        e.nom,
        e.prenoms,
        e.email,
        e.telephone,
        e.statut,
        e.actif,
        g.libelle_grade,
        d.nom_departement,
        u.login AS compte_utilisateur,
        GROUP_CONCAT(
            DISTINCT CONCAT(t.niveau, ' : ', FORMAT(t.montant, 0), ' FCFA')
            ORDER BY t.niveau
            SEPARATOR ' | '
        ) AS taux_horaires
    FROM enseignant e
    JOIN grade g ON g.id_grade = e.id_grade
    JOIN departement d ON d.id_departement = e.id_departement
    LEFT JOIN enseignant_taux_horaire eth 
        ON eth.id_enseignant = e.id_enseignant
       AND eth.actif = 1
    LEFT JOIN taux_horaire t 
        ON t.id_taux = eth.id_taux
       AND t.actif = 1
    LEFT JOIN utilisateur u 
        ON u.id_utilisateur = e.id_utilisateur
";

$params = [];

if($recherche !== ""){
    $sql .= "
        WHERE e.nom LIKE :recherche
           OR e.prenoms LIKE :recherche
           OR e.email LIKE :recherche
           OR e.telephone LIKE :recherche
           OR g.libelle_grade LIKE :recherche
           OR d.nom_departement LIKE :recherche
           OR e.statut LIKE :recherche
           OR u.login LIKE :recherche
    ";

    $params["recherche"] = "%".$recherche."%";
}

$sql .= "
    GROUP BY 
        e.id_enseignant,
        e.nom,
        e.prenoms,
        e.email,
        e.telephone,
        e.statut,
        e.actif,
        g.libelle_grade,
        d.nom_departement,
        u.login
    ORDER BY e.nom ASC, e.prenoms ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require_once "../includes/header.php"; ?>

<div class="wrapper">

    <?php require_once "../includes/sidebar_secretaire.php"; ?>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Liste des enseignants</h1>
                <p>Consultation, recherche et gestion administrative des enseignants.</p>
            </div>

            <div class="user-box">
                <span><?= date("d/m/Y") ?></span>
                <strong><?= htmlspecialchars($_SESSION["login"]) ?></strong>
                <small>SECRÉTAIRE PRINCIPAL</small>
            </div>
        </header>

        <section class="content">

            <div class="table-card">

                <div class="table-header">

                    <form method="GET" class="search-form">
                        <input
                            type="text"
                            name="recherche"
                            placeholder="Rechercher un enseignant..."
                            value="<?= htmlspecialchars($recherche) ?>"
                        >

                        <button type="submit" class="btn-primary">
                            Rechercher
                        </button>

                        <a href="liste_enseignants.php" class="btn-secondary">
                            Réinitialiser
                        </a>
                    </form>

                    <a href="creer_enseignant.php" class="btn-primary">
                        + Nouvel enseignant
                    </a>

                </div>

                <div class="desktop-table">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nom & prénoms</th>
                                <th>Grade</th>
                                <th>Statut</th>
                                <th>Département</th>
                                <th>Taux horaires</th>
                                <th>Coordonnées</th>
                                <th>Compte lié</th>
                                <th>État</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if(count($enseignants) > 0): ?>

                                <?php foreach($enseignants as $enseignant): ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <?= htmlspecialchars($enseignant["nom"] . " " . $enseignant["prenoms"]) ?>
                                            </strong>
                                        </td>

                                        <td><?= htmlspecialchars($enseignant["libelle_grade"]) ?></td>

                                        <td>
                                            <?php if($enseignant["statut"] === "PERMANENT"): ?>
                                                <span class="badge success">PERMANENT</span>
                                            <?php else: ?>
                                                <span class="badge warning">VACATAIRE</span>
                                            <?php endif; ?>
                                        </td>

                                        <td><?= htmlspecialchars($enseignant["nom_departement"]) ?></td>

                                        <td>
                                            <?php if(!empty($enseignant["taux_horaires"])): ?>
                                                <?= htmlspecialchars($enseignant["taux_horaires"]) ?>
                                            <?php else: ?>
                                                <span class="badge danger">Aucun taux</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($enseignant["email"]) ?><br>
                                            <small><?= htmlspecialchars($enseignant["telephone"]) ?></small>
                                        </td>

                                        <td>
                                            <?php if($enseignant["compte_utilisateur"]): ?>
                                                <span class="badge success">
                                                    <?= htmlspecialchars($enseignant["compte_utilisateur"]) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge neutral">Aucun</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if($enseignant["actif"]): ?>
                                                <span class="badge success">ACTIF</span>
                                            <?php else: ?>
                                                <span class="badge danger">INACTIF</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="actions">
                                            <a href="modifier_enseignant.php?id=<?= $enseignant["id_enseignant"] ?>" class="btn-small">
                                                Modifier
                                            </a>

                                            <?php if($enseignant["actif"]): ?>
                                                <a
                                                    href="toggle_enseignant.php?id=<?= $enseignant["id_enseignant"] ?>&action=desactiver"
                                                    class="btn-small danger"
                                                    onclick="return confirm('Voulez-vous vraiment désactiver cet enseignant ?');"
                                                >
                                                    Désactiver
                                                </a>
                                            <?php else: ?>
                                                <a
                                                    href="toggle_enseignant.php?id=<?= $enseignant["id_enseignant"] ?>&action=activer"
                                                    class="btn-small success"
                                                    onclick="return confirm('Voulez-vous vraiment réactiver cet enseignant ?');"
                                                >
                                                    Activer
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="empty">
                                        Aucun enseignant trouvé.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mobile-cards">

                    <?php if(count($enseignants) > 0): ?>

                        <?php foreach($enseignants as $enseignant): ?>

                            <div class="teacher-card">

                                <div class="teacher-card-header">
                                    <div>
                                        <h3>
                                            <?= htmlspecialchars($enseignant["nom"] . " " . $enseignant["prenoms"]) ?>
                                        </h3>
                                        <p><?= htmlspecialchars($enseignant["libelle_grade"]) ?></p>
                                    </div>

                                    <?php if($enseignant["actif"]): ?>
                                        <span class="badge success">ACTIF</span>
                                    <?php else: ?>
                                        <span class="badge danger">INACTIF</span>
                                    <?php endif; ?>
                                </div>

                                <div class="teacher-info">
                                    <p><strong>Statut :</strong> <?= htmlspecialchars($enseignant["statut"]) ?></p>
                                    <p><strong>Département :</strong> <?= htmlspecialchars($enseignant["nom_departement"]) ?></p>
                                    <p>
                                        <strong>Taux horaires :</strong>
                                        <?= !empty($enseignant["taux_horaires"]) ? htmlspecialchars($enseignant["taux_horaires"]) : "Aucun taux" ?>
                                    </p>
                                    <p><strong>Email :</strong> <?= htmlspecialchars($enseignant["email"]) ?></p>
                                    <p><strong>Téléphone :</strong> <?= htmlspecialchars($enseignant["telephone"]) ?></p>
                                    <p>
                                        <strong>Compte :</strong>
                                        <?= $enseignant["compte_utilisateur"] ? htmlspecialchars($enseignant["compte_utilisateur"]) : "Aucun" ?>
                                    </p>
                                </div>

                                <div class="actions">
                                    <a href="modifier_enseignant.php?id=<?= $enseignant["id_enseignant"] ?>" class="btn-small">
                                        Modifier
                                    </a>

                                    <?php if($enseignant["actif"]): ?>
                                        <a
                                            href="toggle_enseignant.php?id=<?= $enseignant["id_enseignant"] ?>&action=desactiver"
                                            class="btn-small danger"
                                            onclick="return confirm('Voulez-vous vraiment désactiver cet enseignant ?');"
                                        >
                                            Désactiver
                                        </a>
                                    <?php else: ?>
                                        <a
                                            href="toggle_enseignant.php?id=<?= $enseignant["id_enseignant"] ?>&action=activer"
                                            class="btn-small success"
                                            onclick="return confirm('Voulez-vous vraiment réactiver cet enseignant ?');"
                                        >
                                            Activer
                                        </a>
                                    <?php endif; ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="empty">
                            Aucun enseignant trouvé.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </section>

        <?php require_once "../includes/footer.php"; ?>

    </main>

</div>