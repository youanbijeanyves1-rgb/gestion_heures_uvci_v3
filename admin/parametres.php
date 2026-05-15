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

    $typeActivite = $_POST["type_activite"] ?? "";
    $niveauComplexite = $_POST["niveau_complexite"] ?? "";
    $coefficient = $_POST["coefficient"] ?? "";
    $actif = isset($_POST["actif"]) ? 1 : 0;

    if($typeActivite === "" || $niveauComplexite === "" || $coefficient === ""){
        $message = "Veuillez renseigner tous les champs.";
        $typeMessage = "error";
    }
    elseif(!is_numeric($coefficient) || $coefficient <= 0){
        $message = "Le coefficient doit être un nombre positif.";
        $typeMessage = "error";
    }
    else{
        $stmt = $pdo->prepare("
            INSERT INTO parametre_calcul(
                type_activite,
                niveau_complexite,
                coefficient,
                actif
            )
            VALUES(?, ?, ?, ?)
        ");

        $stmt->execute([
            $typeActivite,
            $niveauComplexite,
            $coefficient,
            $actif
        ]);

        $message = "Paramètre de calcul enregistré avec succès.";
        $typeMessage = "success";
    }
}

if(isset($_GET["toggle"])){
    $id = (int)$_GET["toggle"];

    $stmt = $pdo->prepare("
        UPDATE parametre_calcul
        SET actif = IF(actif = 1, 0, 1)
        WHERE id_parametre = ?
    ");
    $stmt->execute([$id]);

    header("Location: parametres.php");
    exit;
}

$parametres = $pdo->query("
    SELECT *
    FROM parametre_calcul
    ORDER BY type_activite, niveau_complexite
")->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require_once "../includes/header.php"; ?>

<div class="wrapper">

    <?php require_once "../includes/sidebar_admin.php"; ?>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Paramètres de calcul</h1>
                <p>Définition des coefficients utilisés pour calculer les volumes horaires.</p>
            </div>

            <div class="user-box">
                <span><?= date("d/m/Y") ?></span>
                <strong><?= htmlspecialchars($_SESSION["login"]) ?></strong>
                <small>ADMINISTRATEUR</small>

            </div>
        </header>

        <section class="content">

            <div class="form-card">

                <h2>Ajouter un paramètre de calcul</h2>

                <?php if($message !== ""): ?>
                    <div class="alert <?= $typeMessage ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <div class="form-group">
                        <label>Type d’activité <span>*</span></label>
                        <select name="type_activite" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="CREATION_RESSOURCE">Création de ressource</option>
                            <option value="MISE_A_JOUR_RESSOURCE">Mise à jour de ressource</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Niveau de complexité <span>*</span></label>
                        <select name="niveau_complexite" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="NIVEAU_1">Niveau 1</option>
                            <option value="NIVEAU_2">Niveau 2</option>
                            <option value="NIVEAU_3">Niveau 3</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Coefficient <span>*</span></label>
                        <input 
                            type="number" 
                            step="0.001" 
                            name="coefficient"
                            placeholder="Exemple : 1.5"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="actif" checked>
                            Paramètre actif
                        </label>
                    </div>

                    <button type="submit" class="btn-primary">
                        Enregistrer
                    </button>

                </form>

            </div>

            <br>

            <div class="table-card">

                <h2>Liste des paramètres de calcul</h2>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type d’activité</th>
                            <th>Niveau</th>
                            <th>Coefficient</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if(count($parametres) > 0): ?>

                            <?php foreach($parametres as $p): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($p["type_activite"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($p["niveau_complexite"]) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= number_format($p["coefficient"], 3, ',', ' ') ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php if((int)$p["actif"] === 1): ?>
                                            <span class="badge success">ACTIF</span>
                                        <?php else: ?>
                                            <span class="badge danger">INACTIF</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <a 
                                            href="parametres.php?toggle=<?= $p["id_parametre"] ?>"
                                            class="btn-small"
                                            onclick="return confirm('Changer le statut de ce paramètre ?');"
                                        >
                                            <?= (int)$p["actif"] === 1 ? "Désactiver" : "Activer" ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="empty">
                                    Aucun paramètre de calcul enregistré.
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