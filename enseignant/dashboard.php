<?php

session_start();

if(
    !isset($_SESSION["id_utilisateur"]) ||
    !isset($_SESSION["role"])
){
    header("Location: ../auth/login.php");
    exit;
}

if($_SESSION["role"] !== "ENSEIGNANT"){
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$idUtilisateur = $_SESSION["id_utilisateur"];
$login = $_SESSION["login"];

/*
|--------------------------------------------------------------------------
| STATISTIQUES ENSEIGNANT
|--------------------------------------------------------------------------
*/

$totalActivites = 0;
$totalVolume = 0;
$totalComplementaires = 0;

try{

    $sql = "
        SELECT 
            COUNT(*) AS total_activites,
            COALESCE(SUM(volume_horaire_calcule),0) AS total_volume
        FROM activite_pedagogique
        WHERE id_utilisateur = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idUtilisateur]);

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if($stats){

        $totalActivites = $stats["total_activites"];
        $totalVolume = $stats["total_volume"];

        /*
        |--------------------------------------------------------------------------
        | HEURES COMPLEMENTAIRES
        |--------------------------------------------------------------------------
        */

        if($totalVolume > 240){
            $totalComplementaires = $totalVolume - 240;
        }
    }

}catch(Exception $e){

    $totalActivites = 0;
    $totalVolume = 0;
    $totalComplementaires = 0;
}

/*
|--------------------------------------------------------------------------
| ACTIVITES RECENTES
|--------------------------------------------------------------------------
*/

$activites = [];

try{

    $sql = "
        SELECT 
            observation,
            type_activite,
            volume_horaire_calcule,
            date_activite
        FROM activite_pedagogique
        WHERE id_utilisateur = ?
        ORDER BY date_activite DESC
        LIMIT 5
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idUtilisateur]);

    $activites = $stmt->fetchAll(PDO::FETCH_ASSOC);

}catch(Exception $e){
    $activites = [];
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
        }

        .topbar{
            background:linear-gradient(135deg,#1e3a8a,#06b6d4);
            color:white;
            padding:20px 30px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
        }

        .topbar h1{
            margin:0;
            font-size:28px;
        }

        .logout-btn{
            background:white;
            color:#1e3a8a;
            padding:10px 18px;
            border-radius:10px;
            text-decoration:none;
            font-weight:bold;
        }

        .container{
            padding:30px;
        }

        .welcome-box{
            background:white;
            padding:25px;
            border-radius:18px;
            margin-bottom:25px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
        }

        .cards{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:20px;
            margin-bottom:30px;
        }

        .card{
            background:white;
            border-radius:18px;
            padding:25px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
        }

        .card h3{
            margin:0 0 10px;
            color:#64748b;
            font-size:15px;
        }

        .card .number{
            font-size:35px;
            font-weight:bold;
            color:#0f172a;
        }

        .table-box{
            background:white;
            padding:25px;
            border-radius:18px;
            box-shadow:0 8px 20px rgba(0,0,0,.08);
        }

        table{
            width:100%;
            border-collapse:collapse;
        }

        th{
            background:#0f172a;
            color:white;
            padding:14px;
            text-align:left;
        }

        td{
            padding:14px;
            border-bottom:1px solid #e2e8f0;
        }

        .actions{
            margin-top:25px;
            display:flex;
            gap:15px;
            flex-wrap:wrap;
        }

        .btn{
            padding:14px 20px;
            border-radius:12px;
            text-decoration:none;
            color:white;
            font-weight:bold;
        }

        .btn-blue{
            background:#2563eb;
        }

        .btn-green{
            background:#16a34a;
        }

        .btn-orange{
            background:#ea580c;
        }

        @media(max-width:768px){

            .topbar{
                flex-direction:column;
                gap:15px;
                text-align:center;
            }

            table{
                font-size:13px;
            }
        }

    </style>

</head>

<body>

    <div class="topbar">

        <h1>Dashboard Enseignant</h1>

        <a href="../auth/logout.php" class="logout-btn">
            Déconnexion
        </a>

    </div>

    <div class="container">

        <div class="welcome-box">

            <h2>
                Bienvenue <?= htmlspecialchars($login) ?>
            </h2>

            <p>
                Espace personnel de suivi des activités pédagogiques UVCI.
            </p>

        </div>

        <div class="cards">

            <div class="card">
                <h3>Total activités</h3>
                <div class="number">
                    <?= $totalActivites ?>
                </div>
            </div>

            <div class="card">
                <h3>Volume horaire</h3>
                <div class="number">
                    <?= $totalVolume ?> h
                </div>
            </div>

            <div class="card">
                <h3>Heures complémentaires</h3>
                <div class="number">
                    <?= $totalComplementaires ?> h
                </div>
            </div>

        </div>

        <div class="table-box">

            <h2>
                Mes activités récentes
            </h2>

            <br>

            <table>

                <thead>
                    <tr>
                        <th>Activité</th>
                        <th>Type</th>
                        <th>Volume</th>
                        <th>Date</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if(empty($activites)): ?>

                        <tr>
                            <td colspan="4">
                                Aucune activité enregistrée.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach($activites as $activite): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($activite["observation"]) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($activite["type_activite"]) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($activite["volume_horaire_calcule"]) ?> h
                                </td>

                                <td>
                                    <?= htmlspecialchars($activite["date_activite"]) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

            <div class="actions">

                <a href="mes_activites.php" class="btn btn-blue">
                    Consulter mes activités
                </a>

                <a href="#" class="btn btn-green">
                    Télécharger récapitulatif
                </a>

                <a href="#" class="btn btn-orange">
                    Suivre mes heures complémentaires
                </a>

            </div>

        </div>

    </div>

</body>
</html>