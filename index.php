<?php
session_start();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>UVCI | Gestion des Heures des Enseignants</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:Arial, sans-serif;
        }

        body{
            background:#f4f7fb;
            color:#1e293b;
        }

        .header{
            background:linear-gradient(135deg, #871f78,#059669);
            color:white;
            padding:10px 30px 10px;
        }

        .top{
            display:flex;
            justify-content:space-between;
            align-items:center;
        }

        .brand{
            display:flex;
            align-items:center;
            gap:15px;
        }

        .brand img{
            width:80px;
            height:80px;
            border-radius:50%;
            background:white;
            padding:6px;
        }

        .brand h1{
            font-size:30px;
        }

        .brand p{
            font-size:20px;
            opacity:.9;
        }

        .badge{
            border:1px solid rgba(255, 255, 255, 0.42);
            padding:10px 20px;
            border-radius:30px 30px 30px 30px;
            font-weight:bold;
            font-size:13px;
        }

        .hero{
            text-align:center;
            margin-top:55px;
        }

        .hero h2{
            font-size:42px;
            margin-bottom:10px;
        }

        .hero p{
            font-size:16px;
            opacity:.9;
        }

        .content{
            max-width:1100px;
            margin:20px auto 0;
            padding:0 25px;
        }

        .section-title{
            text-align:center;
            margin-bottom:20px;
        }

        .section-title h2{
            font-size:34px;
            color:#1e1b8f;
        }

        .section-title p{
            color:#64748b;
            margin-top:8px;
        }

        .cards{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:25px;
        }

        .card{
            background:white;
            border-radius:18px;
            padding:35px 25px;
            text-align:center;
            box-shadow:0 10px 25px rgba(0,0,0,.08);
            border-top:5px solid #1e40af;
        }

        .card:nth-child(2){
            border-top-color:#059669;
        }

        .card:nth-child(3){
            border-top-color:#2563eb;
        }

        .icon{
            font-size:42px;
            margin-bottom:18px;
        }

        .card h3{
            font-size:24px;
            color:#1e1b8f;
            margin-bottom:20px;
        }

        .btn{
            display:inline-block;
            padding:12px 28px;
            background:#1d4ed8;
            color:white;
            text-decoration:none;
            border-radius:8px;
            font-weight:bold;
        }

        .card:nth-child(2) .btn{
            background:#059669;
        }

        footer{
            margin-top:90px;
            text-align:center;
            padding:10px;
            background:#871f78;
            color:white;
            font-size:15px;
        }

        @media(max-width:800px){
            .cards{
                grid-template-columns:1fr;
            }

            .top{
                flex-direction:column;
                gap:20px;
                text-align:center;
            }

            .hero h2{
                font-size:32px;
            }

            .header{
                padding:25px 20px 70px;
            }
        }
    </style>
</head>

<body>

<header class="header">

    <div class="top">
        <div class="brand">
            <img src="assets/img/logo_uvci.png" alt="Logo UVCI">

            <div>
                <h1>Université Virtuelle de Côte d’Ivoire</h1>
                <p>Mon université avec moi, partout et à tout moment</p>
            </div>
        </div>

        <div class="badge">
            PLATEFORME ACADÉMIQUE
        </div>
    </div>

    <div class="hero">
        <h2>Gestion des Heures des Enseignants</h2>
        <p>
            Enregistrement, consultation et contrôle des volumes horaires des enseignants.
        </p>
    </div>

</header>

<main class="content">

    <div class="section-title">
        <h3>Choisissez votre profil</h3>
    </div>

    <div class="cards">

        <div class="card">
            <div class="icon">👨‍💼</div>
            <h3>Administrateur</h3>
            <a href="auth/login.php" class="btn">Accéder</a>
        </div>

        <div class="card">
            <div class="icon">📋</div>
            <h3>Secrétaire principal</h3>
            <a href="auth/login.php" class="btn">Accéder</a>
        </div>

        <div class="card">
            <div class="icon">👨‍🏫</div>
            <h3>Enseignant</h3>
            <a href="auth/login.php" class="btn">Accéder</a>
        </div>

    </div>

</main>

<footer>
    © <?= date("Y") ?> UVCI - Gestion des Heures d’Enseignement
</footer>

</body>
</html>