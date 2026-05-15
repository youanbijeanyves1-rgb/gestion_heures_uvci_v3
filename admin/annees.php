<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";

if($_SESSION["role"] !== "ADMINISTRATEUR"){
    header("Location: ../auth/login.php");
    exit;
}

$message = "";
$typeMessage = "";

if($_SERVER["REQUEST_METHOD"] === "POST"){

    $libelle = trim($_POST["libelle_annee"] ?? "");
    $dateDebut = $_POST["date_debut"] ?? "";
    $dateFin = $_POST["date_fin"] ?? "";
    $estActive = isset($_POST["est_active"]) ? 1 : 0;

    if($libelle === "" || $dateDebut === "" || $dateFin === ""){
        $message = "Veuillez renseigner tous les champs.";
        $typeMessage = "error";
    }
    elseif($dateFin < $dateDebut){
        $message = "La date de fin doit être supérieure à la date de début.";
        $typeMessage = "error";
    }
    else{
        try{
            $pdo->beginTransaction();

            if($estActive === 1){
                $pdo->query("UPDATE annee_academique SET est_active = 0");
            }

            $stmt = $pdo->prepare("
                INSERT INTO annee_academique(
                    libelle_annee,
                    date_debut,
                    date_fin,
                    est_active
                )
                VALUES(?, ?, ?, ?)
            ");

            $stmt->execute([
                $libelle,
                $dateDebut,
                $dateFin,
                $estActive
            ]);

            $pdo->commit();

            $message = "Année académique enregistrée avec succès.";
            $typeMessage = "success";

        }catch(Exception $e){
            $pdo->rollBack();

            $message = "Erreur : " . $e->getMessage();
            $typeMessage = "error";
        }
    }
}

if(isset($_GET["activer"])){
    $id = (int)$_GET["activer"];

    $pdo->beginTransaction();

    $pdo->query("UPDATE annee_academique SET est_active = 0");

    $stmt = $pdo->prepare("
        UPDATE annee_academique
        SET est_active = 1
        WHERE id_annee = ?
    ");
    $stmt->execute([$id]);

    $pdo->commit();

    header("Location: annees.php");
    exit;
}

$annees = $pdo->query("
    SELECT *
    FROM annee_academique
    ORDER BY date_debut DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require_once "../includes/header.php"; ?>

<div class="wrapper">

    <?php require_once "../includes/sidebar_admin.php"; ?>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Années académiques</h1>
                <p>Paramétrage des années académiques du système.</p>
            </div>

            <div class="user-box">
                <span><?= date("d/m/Y") ?></span>
                <strong><?= htmlspecialchars($_SESSION["login"]) ?></strong>
                <small>ADMINISTRATEUR</small>

            </div>
        </header>

        <section class="content">

            <div class="form-card">

                <h2>Créer une année académique</h2>

                <?php if($message !== ""): ?>
                    <div class="alert <?= $typeMessage ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <div class="form-group">
                        <label>Libellé de l’année <span>*</span></label>
                        <input 
                            type="text" 
                            name="libelle_annee" 
                            placeholder="Exemple : 2025-2026"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Date début <span>*</span></label>
                        <input type="date" name="date_debut" required>
                    </div>

                    <div class="form-group">
                        <label>Date fin <span>*</span></label>
                        <input type="date" name="date_fin" required>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="est_active">
                            Définir comme année active
                        </label>
                    </div>

                    <button type="submit" class="btn-primary">
                        Enregistrer
                    </button>

                </form>

            </div>

            <br>

            <div class="table-card">

                <h2>Liste des années académiques</h2>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Libellé</th>
                            <th>Date début</th>
                            <th>Date fin</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if(count($annees) > 0): ?>

                            <?php foreach($annees as $a): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($a["libelle_annee"]) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= date("d/m/Y", strtotime($a["date_debut"])) ?>
                                    </td>

                                    <td>
                                        <?= date("d/m/Y", strtotime($a["date_fin"])) ?>
                                    </td>

                                    <td>
                                        <?php if((int)$a["est_active"] === 1): ?>
                                            <span class="badge success">ACTIVE</span>
                                        <?php else: ?>
                                            <span class="badge warning">INACTIVE</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if((int)$a["est_active"] !== 1): ?>
                                            <a 
                                                href="annees.php?activer=<?= $a["id_annee"] ?>" 
                                                class="btn-small success"
                                                onclick="return confirm('Activer cette année académique ?');"
                                            >
                                                Activer
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="empty">
                                    Aucune année académique enregistrée.
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