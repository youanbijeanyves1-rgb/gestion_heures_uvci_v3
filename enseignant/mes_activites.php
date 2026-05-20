<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";

if($_SESSION["role"] !== "ENSEIGNANT"){
    header("Location: ../auth/login.php");
    exit;
}

$idUtilisateur = $_SESSION["id_utilisateur"];

$stmtEns = $pdo->prepare("
    SELECT 
        e.id_enseignant,
        e.nom,
        e.prenoms,
        e.statut,
        g.libelle_grade
    FROM enseignant e
    LEFT JOIN grade g ON g.id_grade = e.id_grade
    WHERE e.id_utilisateur = ?
    LIMIT 1
");

$stmtEns->execute([$idUtilisateur]);
$enseignant = $stmtEns->fetch(PDO::FETCH_ASSOC);

if(!$enseignant){
    die("Aucun enseignant associé à ce compte utilisateur.");
}

$idEnseignant = $enseignant["id_enseignant"];

$statutFiltre = $_GET["statut"] ?? "";

$where = "ap.id_enseignant = ?";
$params = [$idEnseignant];

if($statutFiltre !== ""){
    $where .= " AND ap.statut_validation = ?";
    $params[] = $statutFiltre;
}

$stmtActivites = $pdo->prepare("
    SELECT
        ap.id_activite,
        ap.type_activite,
        ap.niveau_complexite,
        ap.nombre_heures,
        ap.nb_sequences,
        ap.volume_horaire_calcule,
        ap.statut_validation,
        ap.date_saisie,
        ap.observation,
        c.intitule_cours AS cours
    FROM activite_pedagogique ap
    LEFT JOIN cours c ON c.id_cours = ap.id_cours
    WHERE $where
    ORDER BY ap.date_saisie DESC
");

$stmtActivites->execute($params);
$activites = $stmtActivites->fetchAll(PDO::FETCH_ASSOC);

$totalActivites = count($activites);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes activités pédagogiques</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        *{
            box-sizing:border-box;
            font-family:Arial, Helvetica, sans-serif;
        }

        body{
            margin:0;
            background:#f1f5f9;
            color:#0f172a;
        }

        .container{
            padding:30px;
        }

        .top-card{
            background:white;
            padding:25px;
            border-radius:18px;
            margin-bottom:22px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
            display:flex;
            justify-content:space-between;
            gap:20px;
            flex-wrap:wrap;
        }

        .top-card h1{
            margin:0;
            font-size:26px;
            color:#1e3a8a;
        }

        .top-card p{
            margin:8px 0 0;
            color:#475569;
        }

        .btn-back{
            background:#1e3a8a;
            color:white;
            text-decoration:none;
            padding:12px 16px;
            border-radius:10px;
            font-weight:bold;
            height:max-content;
        }

        .filter-card{
            background:white;
            padding:22px;
            border-radius:18px;
            margin-bottom:22px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
        }

        .filter-form{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:16px;
            align-items:end;
        }

        label{
            font-weight:bold;
            color:#334155;
            font-size:14px;
        }

        select{
            width:100%;
            margin-top:7px;
            padding:13px;
            border:1px solid #cbd5e1;
            border-radius:10px;
            font-size:14px;
            background:white;
        }

        .btn{
            border:none;
            padding:13px 18px;
            border-radius:10px;
            color:white;
            font-weight:bold;
            cursor:pointer;
            text-decoration:none;
            text-align:center;
            display:inline-block;
        }

        .btn-blue{
            background:#2563eb;
        }

        .btn-gray{
            background:#64748b;
        }

        .stat-card{
            background:white;
            padding:24px;
            border-radius:18px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
            margin-bottom:22px;
        }

        .stat-card h3{
            margin:0 0 12px;
            font-size:15px;
            color:#64748b;
        }

        .stat-card strong{
            font-size:34px;
            color:#1e3a8a;
        }

        .table-card{
            background:white;
            padding:24px;
            border-radius:18px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
            overflow-x:auto;
        }

        table{
            width:100%;
            border-collapse:collapse;
            min-width:950px;
            font-size:14px;
        }

        th{
            background:#1e1b4b;
            color:white;
            text-align:left;
            padding:13px;
        }

        td{
            padding:13px;
            border-bottom:1px solid #e2e8f0;
        }

        .badge{
            padding:6px 10px;
            border-radius:999px;
            font-weight:bold;
            font-size:12px;
        }

        .validee{
            background:#dcfce7;
            color:#166534;
        }

        .attente{
            background:#fef3c7;
            color:#92400e;
        }

        .rejetee{
            background:#fee2e2;
            color:#991b1b;
        }

        @media(max-width:768px){
            .container{
                padding:16px;
            }

            .top-card h1{
                font-size:22px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="top-card">
        <div>
            <h1>Mes activités pédagogiques</h1>
            <p>
                Enseignant :
                <strong><?= htmlspecialchars($enseignant["nom"] . " " . $enseignant["prenoms"]) ?></strong>
                —
                Grade :
                <strong><?= htmlspecialchars($enseignant["libelle_grade"] ?? "Non défini") ?></strong>
                —
                Statut :
                <strong><?= htmlspecialchars($enseignant["statut"]) ?></strong>
            </p>
        </div>

        <a href="dashboard.php" class="btn-back">Retour au dashboard</a>
    </div>

    <div class="filter-card">
        <form method="GET" class="filter-form">

            <div>
                <label>Filtrer par statut</label>
                <select name="statut">
                    <option value="">Toutes les activités</option>
                    <option value="EN_ATTENTE" <?= $statutFiltre === "EN_ATTENTE" ? "selected" : "" ?>>
                        En attente
                    </option>
                    <option value="VALIDEE" <?= $statutFiltre === "VALIDEE" ? "selected" : "" ?>>
                        Validée
                    </option>
                    <option value="REJETEE" <?= $statutFiltre === "REJETEE" ? "selected" : "" ?>>
                        Rejetée
                    </option>
                </select>
            </div>

            <button type="submit" class="btn btn-blue">Filtrer</button>

            <a href="mes_activites.php" class="btn btn-gray">Réinitialiser</a>

        </form>
    </div>

    <div class="stat-card">
        <h3>Total des activités trouvées</h3>
        <strong><?= $totalActivites ?></strong>
    </div>

    <div class="table-card">

        <h2>Liste de mes activités pédagogiques</h2>

        <table>
            <thead>
                <tr>
                    <th>Date saisie</th>
                    <th>Cours</th>
                    <th>Type activité</th>
                    <th>Niveau</th>
                    <th>Heures</th>
                    <th>Séquences</th>
                    <th>Volume calculé</th>
                    <th>Statut</th>
                    <th>Observation</th>
                </tr>
            </thead>

            <tbody>

                <?php if(empty($activites)): ?>

                    <tr>
                        <td colspan="9">Aucune activité pédagogique trouvée.</td>
                    </tr>

                <?php else: ?>

                    <?php foreach($activites as $activite): ?>

                        <?php
                            $classeBadge = "attente";

                            if($activite["statut_validation"] === "VALIDEE"){
                                $classeBadge = "validee";
                            }
                            elseif($activite["statut_validation"] === "REJETEE"){
                                $classeBadge = "rejetee";
                            }
                        ?>

                        <tr>
                            <td><?= htmlspecialchars($activite["date_saisie"]) ?></td>
                            <td><?= htmlspecialchars($activite["cours"] ?? "Non renseigné") ?></td>
                            <td><?= htmlspecialchars($activite["type_activite"]) ?></td>
                            <td><?= htmlspecialchars($activite["niveau_complexite"]) ?></td>
                            <td><?= number_format((float)$activite["nombre_heures"], 2, ',', ' ') ?> h</td>
                            <td><?= htmlspecialchars($activite["nb_sequences"]) ?></td>
                            <td><?= number_format((float)$activite["volume_horaire_calcule"], 2, ',', ' ') ?> h</td>
                            <td>
                                <span class="badge <?= $classeBadge ?>">
                                    <?= htmlspecialchars($activite["statut_validation"]) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($activite["observation"]) ?></td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>
        </table>

    </div>

</div>

</body>
</html>