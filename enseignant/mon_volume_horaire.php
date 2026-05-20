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

$dateDebut = $_GET["date_debut"] ?? "";
$dateFin = $_GET["date_fin"] ?? "";

$where = "ap.id_enseignant = ? AND ap.statut_validation = 'VALIDEE'";
$params = [$idEnseignant];

if($dateDebut !== ""){
    $where .= " AND DATE(ap.date_saisie) >= ?";
    $params[] = $dateDebut;
}

if($dateFin !== ""){
    $where .= " AND DATE(ap.date_saisie) <= ?";
    $params[] = $dateFin;
}

$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_activites,
        COALESCE(SUM(ap.volume_horaire_calcule), 0) AS total_volume
    FROM activite_pedagogique ap
    WHERE $where
");
$stmtStats->execute($params);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

$totalActivites = (int)$stats["total_activites"];
$totalVolume = (float)$stats["total_volume"];

$stmtActivites = $pdo->prepare("
    SELECT 
        ap.observation,
        ap.type_activite,
        ap.niveau_complexite,
        ap.nb_sequences,
        ap.volume_horaire_calcule,
        ap.date_saisie,
        c.intitule_cours AS cours
    FROM activite_pedagogique ap
    LEFT JOIN cours c ON c.id_cours = ap.id_cours
    WHERE $where
    ORDER BY ap.date_saisie DESC
");
$stmtActivites->execute($params);
$activites = $stmtActivites->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon volume horaire</title>
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

        input{
            width:100%;
            margin-top:7px;
            padding:13px;
            border:1px solid #cbd5e1;
            border-radius:10px;
            font-size:14px;
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

        .stats{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
            gap:20px;
            margin-bottom:25px;
        }

        .stat-card{
            background:white;
            padding:24px;
            border-radius:18px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
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
            min-width:850px;
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
            background:#dcfce7;
            color:#166534;
            font-weight:bold;
            font-size:12px;
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
            <h1>Mon volume horaire</h1>
            <p>
                Enseignant :
                <strong><?= htmlspecialchars($enseignant["nom"] . " " . $enseignant["prenoms"]) ?></strong>
                —
                Grade :
                <strong><?= htmlspecialchars($enseignant["libelle_grade"] ?? "Non défini") ?></strong>
            </p>
        </div>

        <a href="dashboard.php" class="btn-back">Retour au dashboard</a>
    </div>

    <div class="filter-card">
        <form method="GET" class="filter-form">

            <div>
                <label>Date début</label>
                <input type="date" name="date_debut" value="<?= htmlspecialchars($dateDebut) ?>">
            </div>

            <div>
                <label>Date fin</label>
                <input type="date" name="date_fin" value="<?= htmlspecialchars($dateFin) ?>">
            </div>

            <button type="submit" class="btn btn-blue">Filtrer</button>

            <a href="mon_volume_horaire.php" class="btn btn-gray">Réinitialiser</a>

        </form>
    </div>

    <div class="stats">

        <div class="stat-card">
            <h3>Activités validées</h3>
            <strong><?= $totalActivites ?></strong>
        </div>

        <div class="stat-card">
            <h3>Volume horaire validé</h3>
            <strong><?= number_format($totalVolume, 2, ',', ' ') ?> h</strong>
        </div>

    </div>

    <div class="table-card">

        <h2>Détail des activités validées</h2>

        <table>
            <thead>
                <tr>
                    <th>Date saisie</th>
                    <th>Cours</th>
                    <th>Type activité</th>
                    <th>Niveau</th>
                    <th>Séquences</th>
                    <th>Volume</th>
                    <th>Statut</th>
                    <th>Observation</th>
                </tr>
            </thead>

            <tbody>

                <?php if(empty($activites)): ?>

                    <tr>
                        <td colspan="8">Aucune activité validée trouvée pour cette période.</td>
                    </tr>

                <?php else: ?>

                    <?php foreach($activites as $activite): ?>

                        <tr>
                            <td><?= htmlspecialchars($activite["date_saisie"]) ?></td>
                            <td><?= htmlspecialchars($activite["cours"] ?? "Non renseigné") ?></td>
                            <td><?= htmlspecialchars($activite["type_activite"]) ?></td>
                            <td><?= htmlspecialchars($activite["niveau_complexite"]) ?></td>
                            <td><?= htmlspecialchars($activite["nb_sequences"]) ?></td>
                            <td><?= number_format((float)$activite["volume_horaire_calcule"], 2, ',', ' ') ?> h</td>
                            <td><span class="badge">VALIDÉE</span></td>
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