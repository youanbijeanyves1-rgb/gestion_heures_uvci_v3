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

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Enseignant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body{
            margin:0;
            font-family:Arial, Helvetica, sans-serif;
            background:#f1f5f9;
            color:#0f172a;
        }

        .container{
            padding:30px;
        }

        .welcome-card{
            background:white;
            padding:25px;
            border-radius:18px;
            margin-bottom:25px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:20px;
            flex-wrap:wrap;
        }

        .welcome-card h2{
            margin:0;
            font-size:26px;
        }

        .welcome-card p{
            margin:8px 0 0;
            color:#475569;
        }

        .cards{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
            gap:22px;
        }

        .card{
            background:white;
            padding:28px;
            border-radius:20px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
            min-height:210px;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
        }

        .card-icon{
            width:54px;
            height:54px;
            border-radius:14px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:26px;
            color:white;
            margin-bottom:18px;
        }

        .blue{background:#2563eb;}
        .green{background:#059669;}
        .orange{background:#ea580c;}
        .purple{background:#7c3aed;}

        .card h3{
            margin:0 0 10px;
            font-size:20px;
            color:#1e293b;
        }

        .card p{
            color:#64748b;
            line-height:1.5;
            font-size:14px;
        }

        .btn{
            display:inline-block;
            margin-top:18px;
            padding:12px 16px;
            border-radius:10px;
            text-decoration:none;
            color:white;
            font-weight:bold;
            font-size:14px;
            text-align:center;
        }

        .btn-blue{background:#2563eb;}
        .btn-green{background:#059669;}
        .btn-orange{background:#ea580c;}
        .btn-purple{background:#7c3aed;}

        @media(max-width:768px){
            .container{
                padding:18px;
            }

            .welcome-card h2{
                font-size:22px;
            }
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="welcome-card">
            <div>
                <h2>
                    Bienvenue <?= htmlspecialchars($enseignant["nom"] . " " . $enseignant["prenoms"]) ?>
                </h2>

                <p>
                    Espace personnel enseignant — 
                    Grade : <strong><?= htmlspecialchars($enseignant["libelle_grade"] ?? "Non défini") ?></strong>
                    —
                    Statut : <strong><?= htmlspecialchars($enseignant["statut"]) ?></strong>
                </p>
            </div>

            <a href="../auth/logout.php" class="btn btn-orange">Déconnexion</a>
        </div>

        <div class="cards">

            <div class="card">
                <div>
                    <div class="card-icon blue">📋</div>
                    <h3>Mes activités pédagogiques</h3>
                    <p>
                        Consulter la liste des activités pédagogiques qui me sont attribuées.
                    </p>
                </div>

                <a href="mes_activites.php" class="btn btn-blue">
                    Consulter
                </a>
            </div>

            <div class="card">
                <div>
                    <div class="card-icon green">⏱️</div>
                    <h3>Mon volume horaire</h3>
                    <p>
                        Vérifier mon volume horaire validé sur une période donnée.
                    </p>
                </div>

                <a href="mon_volume_horaire.php" class="btn btn-green">
                    Vérifier par période
                </a>
            </div>

            <div class="card">
                <div>
                    <div class="card-icon orange">➕</div>
                    <h3>Mes heures complémentaires</h3>
                    <p>
                        Suivre les heures complémentaires calculées selon mon statut et ma charge statutaire.
                    </p>
                </div>

                <a href="mes_heures_complementaires.php" class="btn btn-orange">
                    Suivre
                </a>
            </div>

            <div class="card">
                <div>
                    <div class="card-icon purple">📄</div>
                    <h3>Mon récapitulatif</h3>
                    <p>
                        Télécharger mon récapitulatif par période.
                    </p>
                </div>

                <a href="mon_recapitulatif.php" class="btn btn-purple">
                    Télécharger
                </a>
            </div>

        </div>

    </div>

</body>
</html>