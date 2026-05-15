<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";

if($_SESSION["role"] !== "ADMINISTRATEUR"){
    header("Location: ../auth/login.php");
    exit;
}

$message = "";
$typeMessage = "";

$grades = $pdo->query("
    SELECT id_grade, libelle_grade
    FROM grade
    ORDER BY 
        CASE libelle_grade
            WHEN 'Assistant' THEN 1
            WHEN 'Maître-Assistant' THEN 2
            WHEN 'Professeur' THEN 3
            ELSE 4
        END
")->fetchAll(PDO::FETCH_ASSOC);

$annees = $pdo->query("
    SELECT id_annee, libelle_annee, est_active
    FROM annee_academique
    ORDER BY date_debut DESC
")->fetchAll(PDO::FETCH_ASSOC);

if($_SERVER["REQUEST_METHOD"] === "POST"){

    $statut = $_POST["statut"] ?? "";
    $idGrade = $_POST["id_grade"] ?? "";
    $niveau = $_POST["niveau"] ?? "";
    $idAnnee = $_POST["id_annee"] ?? "";
    $montant = $_POST["montant"] ?? "";
    $dateEffet = $_POST["date_effet"] ?? "";
    $actif = isset($_POST["actif"]) ? 1 : 0;

    if($statut === "" || $idGrade === "" || $niveau === "" || $idAnnee === "" || $montant === "" || $dateEffet === ""){
        $message = "Veuillez renseigner tous les champs obligatoires.";
        $typeMessage = "error";
    }
    elseif(!is_numeric($montant) || $montant <= 0){
        $message = "Le montant doit être un nombre positif.";
        $typeMessage = "error";
    }
    else{
        $stmtGrade = $pdo->prepare("SELECT libelle_grade FROM grade WHERE id_grade = ?");
        $stmtGrade->execute([$idGrade]);
        $grade = $stmtGrade->fetch(PDO::FETCH_ASSOC);

        $categorie = $statut . "_" . strtoupper(str_replace([" ", "-"], "_", $grade["libelle_grade"])) . "_" . $niveau;

        try{
            $stmt = $pdo->prepare("
                INSERT INTO taux_horaire(
                    statut,
                    id_grade,
                    niveau,
                    id_annee,
                    categorie,
                    montant,
                    date_effet,
                    actif
                )
                VALUES(?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $statut,
                $idGrade,
                $niveau,
                $idAnnee,
                $categorie,
                $montant,
                $dateEffet,
                $actif
            ]);

            $message = "Taux horaire enregistré avec succès.";
            $typeMessage = "success";

        }catch(PDOException $e){
            if($e->getCode() == 23000){
                $message = "Ce taux existe déjà pour ce statut, ce grade, ce niveau et cette année académique.";
            }else{
                $message = "Erreur : " . $e->getMessage();
            }

            $typeMessage = "error";
        }
    }
}

if(isset($_GET["toggle"])){
    $id = (int)$_GET["toggle"];

    $stmt = $pdo->prepare("
        UPDATE taux_horaire
        SET actif = IF(actif = 1, 0, 1)
        WHERE id_taux = ?
    ");
    $stmt->execute([$id]);

    header("Location: taux_horaires.php");
    exit;
}

$taux = $pdo->query("
    SELECT 
        th.id_taux,
        th.statut,
        th.niveau,
        th.categorie,
        th.montant,
        th.date_effet,
        th.actif,
        g.libelle_grade,
        a.libelle_annee
    FROM taux_horaire th
    JOIN grade g ON g.id_grade = th.id_grade
    JOIN annee_academique a ON a.id_annee = th.id_annee
    ORDER BY 
        a.date_debut DESC,
        th.statut,
        CASE g.libelle_grade
            WHEN 'Assistant' THEN 1
            WHEN 'Maître-Assistant' THEN 2
            WHEN 'Professeur' THEN 3
            ELSE 4
        END,
        th.niveau
")->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require_once "../includes/header.php"; ?>

<div class="wrapper">

    <?php require_once "../includes/sidebar_admin.php"; ?>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Taux horaires</h1>
                <p>Gestion des taux par statut, grade, niveau et année académique.</p>
            </div>

            <div class="user-box">
                <span><?= date("d/m/Y") ?></span>
                <strong><?= htmlspecialchars($_SESSION["login"]) ?></strong>
                <small>ADMINISTRATEUR</small>

            </div>
        </header>

        <section class="content">

            <div class="form-card">

                <h2>Ajouter un taux horaire</h2>

                <?php if($message !== ""): ?>
                    <div class="alert <?= $typeMessage ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <div class="form-group">
                        <label>Statut <span>*</span></label>
                        <select name="statut" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="PERMANENT">Permanent</option>
                            <option value="VACATAIRE">Vacataire</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Grade <span>*</span></label>
                        <select name="id_grade" required>
                            <option value="">-- Sélectionner --</option>

                            <?php foreach($grades as $g): ?>
                                <option value="<?= $g["id_grade"] ?>">
                                    <?= htmlspecialchars($g["libelle_grade"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Niveau <span>*</span></label>
                        <select name="niveau" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="LICENCE">Licence</option>
                            <option value="MASTER">Master</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Année académique <span>*</span></label>
                        <select name="id_annee" required>
                            <option value="">-- Sélectionner --</option>

                            <?php foreach($annees as $a): ?>
                                <option value="<?= $a["id_annee"] ?>">
                                    <?= htmlspecialchars($a["libelle_annee"]) ?>
                                    <?= (int)$a["est_active"] === 1 ? " — Active" : "" ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Montant horaire <span>*</span></label>
                        <input 
                            type="number" 
                            step="0.01" 
                            name="montant"
                            placeholder="Exemple : 15000"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Date d’effet <span>*</span></label>
                        <input type="date" name="date_effet" required>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="actif" checked>
                            Taux actif
                        </label>
                    </div>

                    <button type="submit" class="btn-primary">
                        Enregistrer
                    </button>

                </form>

            </div>

            <br>

            <div class="table-card">

                <h2>Liste des taux horaires</h2>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Année</th>
                            <th>Statut</th>
                            <th>Grade</th>
                            <th>Niveau</th>
                            <th>Catégorie générée</th>
                            <th>Montant</th>
                            <th>Date d’effet</th>
                            <th>État</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if(count($taux) > 0): ?>

                            <?php foreach($taux as $t): ?>
                                <tr>
                                    <td><?= htmlspecialchars($t["libelle_annee"]) ?></td>

                                    <td><?= htmlspecialchars($t["statut"]) ?></td>

                                    <td><?= htmlspecialchars($t["libelle_grade"]) ?></td>

                                    <td><?= htmlspecialchars($t["niveau"]) ?></td>

                                    <td>
                                        <strong><?= htmlspecialchars($t["categorie"]) ?></strong>
                                    </td>

                                    <td>
                                        <?= number_format($t["montant"], 0, ',', ' ') ?> FCFA
                                    </td>

                                    <td>
                                        <?= date("d/m/Y", strtotime($t["date_effet"])) ?>
                                    </td>

                                    <td>
                                        <?php if((int)$t["actif"] === 1): ?>
                                            <span class="badge success">ACTIF</span>
                                        <?php else: ?>
                                            <span class="badge danger">INACTIF</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <a 
                                            href="taux_horaires.php?toggle=<?= $t["id_taux"] ?>"
                                            class="btn-small"
                                            onclick="return confirm('Changer le statut de ce taux ?');"
                                        >
                                            <?= (int)$t["actif"] === 1 ? "Désactiver" : "Activer" ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="9" class="empty">
                                    Aucun taux horaire enregistré.
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