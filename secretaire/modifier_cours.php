<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";

if($_SESSION["role"] !== "SECRETAIRE_PRINCIPAL"){
    header("Location: ../auth/login.php");
    exit;
}

$message = "";
$typeMessage = "";

$idCours = (int)($_GET["id"] ?? 0);

if($idCours <= 0){
    header("Location: cours.php");
    exit;
}

$filieres = $pdo->query("
    SELECT id_filiere, nom_filiere
    FROM filiere
    WHERE actif = 1
    ORDER BY nom_filiere
")->fetchAll(PDO::FETCH_ASSOC);

$enseignants = $pdo->query("
    SELECT id_enseignant, nom, prenoms
    FROM enseignant
    WHERE actif = 1
    ORDER BY nom, prenoms
")->fetchAll(PDO::FETCH_ASSOC);

$stmtCours = $pdo->prepare("
    SELECT 
        c.id_cours,
        c.code_cours,
        c.intitule_cours,
        c.id_enseignant,
        c.nombre_heures,
        c.nb_sequences,
        c.nombre_credits,
        c.actif,
        cf.id_filiere,
        cf.niveau,
        cf.semestre
    FROM cours c
    LEFT JOIN cours_filiere cf ON cf.id_cours = c.id_cours
    WHERE c.id_cours = ?
    LIMIT 1
");

$stmtCours->execute([$idCours]);
$cours = $stmtCours->fetch(PDO::FETCH_ASSOC);

if(!$cours){
    header("Location: cours.php");
    exit;
}

if($_SERVER["REQUEST_METHOD"] === "POST"){

    $codeCours = trim($_POST["code_cours"] ?? "");
    $intituleCours = trim($_POST["intitule_cours"] ?? "");
    $idEnseignant = $_POST["id_enseignant"] ?? null;
    $idFiliere = $_POST["id_filiere"] ?? "";
    $niveau = $_POST["niveau"] ?? "";
    $semestre = $_POST["semestre"] ?? "";
    $nombreHeures = $_POST["nombre_heures"] ?? "";
    $nombreCredits = $_POST["nombre_credits"] ?? "";

    if($idEnseignant === ""){
        $idEnseignant = null;
    }

    if(
        $codeCours === "" ||
        $intituleCours === "" ||
        $idFiliere === "" ||
        $niveau === "" ||
        $semestre === "" ||
        $nombreHeures === "" ||
        $nombreCredits === ""
    ){
        $message = "Veuillez remplir tous les champs obligatoires.";
        $typeMessage = "error";
    }
    elseif(!is_numeric($nombreHeures) || $nombreHeures <= 0){
        $message = "Le nombre d’heures doit être supérieur à 0.";
        $typeMessage = "error";
    }
    elseif(!is_numeric($nombreCredits) || $nombreCredits < 0){
        $message = "Le nombre de crédits doit être supérieur ou égal à 0.";
        $typeMessage = "error";
    }
    else{

        $verif = $pdo->prepare("
            SELECT COUNT(*)
            FROM cours
            WHERE code_cours = ?
              AND id_cours <> ?
        ");
        $verif->execute([$codeCours, $idCours]);

        if($verif->fetchColumn() > 0){
            $message = "Ce code cours est déjà utilisé par un autre cours.";
            $typeMessage = "error";
        }else{

            try{

                $pdo->beginTransaction();

                $nbSequences = $nombreHeures * 4;

                $stmtUpdateCours = $pdo->prepare("
                    UPDATE cours
                    SET
                        code_cours = :code_cours,
                        intitule_cours = :intitule_cours,
                        id_enseignant = :id_enseignant,
                        nombre_heures = :nombre_heures,
                        nb_sequences = :nb_sequences,
                        nombre_credits = :nombre_credits
                    WHERE id_cours = :id_cours
                ");

                $stmtUpdateCours->execute([
                    "code_cours" => $codeCours,
                    "intitule_cours" => $intituleCours,
                    "id_enseignant" => $idEnseignant,
                    "nombre_heures" => $nombreHeures,
                    "nb_sequences" => $nbSequences,
                    "nombre_credits" => $nombreCredits,
                    "id_cours" => $idCours
                ]);

                $verifAssociation = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM cours_filiere
                    WHERE id_cours = ?
                ");
                $verifAssociation->execute([$idCours]);

                if($verifAssociation->fetchColumn() > 0){

                    $stmtUpdateAssoc = $pdo->prepare("
                        UPDATE cours_filiere
                        SET
                            id_filiere = :id_filiere,
                            niveau = :niveau,
                            semestre = :semestre
                        WHERE id_cours = :id_cours
                    ");

                    $stmtUpdateAssoc->execute([
                        "id_filiere" => $idFiliere,
                        "niveau" => $niveau,
                        "semestre" => $semestre,
                        "id_cours" => $idCours
                    ]);

                }else{

                    $stmtInsertAssoc = $pdo->prepare("
                        INSERT INTO cours_filiere(
                            id_cours,
                            id_filiere,
                            niveau,
                            semestre
                        )
                        VALUES(
                            :id_cours,
                            :id_filiere,
                            :niveau,
                            :semestre
                        )
                    ");

                    $stmtInsertAssoc->execute([
                        "id_cours" => $idCours,
                        "id_filiere" => $idFiliere,
                        "niveau" => $niveau,
                        "semestre" => $semestre
                    ]);
                }

                $pdo->commit();

                $message = "Cours modifié avec succès.";
                $typeMessage = "success";

                $stmtCours->execute([$idCours]);
                $cours = $stmtCours->fetch(PDO::FETCH_ASSOC);

            }catch(Exception $e){

                if($pdo->inTransaction()){
                    $pdo->rollBack();
                }

                $message = "Erreur lors de la modification du cours : " . $e->getMessage();
                $typeMessage = "error";
            }
        }
    }
}

?>

<?php require_once "../includes/header.php"; ?>

<div class="wrapper">

    <?php require_once "../includes/sidebar_secretaire.php"; ?>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Modification d’un cours</h1>
                <p>Modifier les informations du cours, son enseignant responsable et son rattachement à une filière.</p>
            </div>

            <div class="user-box">
                <span><?= date("d/m/Y") ?></span>
                <strong><?= htmlspecialchars($_SESSION["login"]) ?></strong>
                <small>SECRÉTAIRE PRINCIPAL</small>
            </div>
        </header>

        <section class="content">

            <div class="form-card">

                <?php if($message !== ""): ?>
                    <div class="alert <?= htmlspecialchars($typeMessage) ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <div class="form-group">
                        <label>Code du cours <span>*</span></label>
                        <input
                            type="text"
                            name="code_cours"
                            required
                            value="<?= htmlspecialchars($cours["code_cours"]) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Intitulé du cours <span>*</span></label>
                        <input
                            type="text"
                            name="intitule_cours"
                            required
                            value="<?= htmlspecialchars($cours["intitule_cours"]) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Enseignant responsable</label>
                        <select name="id_enseignant">
                            <option value="">Aucun enseignant affecté pour l’instant</option>

                            <?php foreach($enseignants as $enseignant): ?>
                                <option
                                    value="<?= htmlspecialchars($enseignant["id_enseignant"]) ?>"
                                    <?= (string)$cours["id_enseignant"] === (string)$enseignant["id_enseignant"] ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($enseignant["nom"] . " " . $enseignant["prenoms"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Filière <span>*</span></label>
                        <select name="id_filiere" required>
                            <option value="">-- Sélectionner une filière --</option>

                            <?php foreach($filieres as $filiere): ?>
                                <option
                                    value="<?= htmlspecialchars($filiere["id_filiere"]) ?>"
                                    <?= (string)$cours["id_filiere"] === (string)$filiere["id_filiere"] ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($filiere["nom_filiere"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Niveau <span>*</span></label>
                        <select name="niveau" required>
                            <option value="">-- Sélectionner le niveau --</option>
                            <option value="L1" <?= $cours["niveau"] === "L1" ? "selected" : "" ?>>L1</option>
                            <option value="L2" <?= $cours["niveau"] === "L2" ? "selected" : "" ?>>L2</option>
                            <option value="L3" <?= $cours["niveau"] === "L3" ? "selected" : "" ?>>L3</option>
                            <option value="M1" <?= $cours["niveau"] === "M1" ? "selected" : "" ?>>M1</option>
                            <option value="M2" <?= $cours["niveau"] === "M2" ? "selected" : "" ?>>M2</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Semestre <span>*</span></label>
                        <select name="semestre" required>
                            <option value="">-- Sélectionner le semestre --</option>
                            <option value="S1" <?= $cours["semestre"] === "S1" ? "selected" : "" ?>>S1</option>
                            <option value="S2" <?= $cours["semestre"] === "S2" ? "selected" : "" ?>>S2</option>
                            <option value="S3" <?= $cours["semestre"] === "S3" ? "selected" : "" ?>>S3</option>
                            <option value="S4" <?= $cours["semestre"] === "S4" ? "selected" : "" ?>>S4</option>
                            <option value="S5" <?= $cours["semestre"] === "S5" ? "selected" : "" ?>>S5</option>
                            <option value="S6" <?= $cours["semestre"] === "S6" ? "selected" : "" ?>>S6</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nombre d’heures <span>*</span></label>
                        <input
                            type="number"
                            name="nombre_heures"
                            min="1"
                            step="0.5"
                            required
                            value="<?= htmlspecialchars($cours["nombre_heures"]) ?>"
                        >
                        <p class="info-text">
                            Le nombre de séquences est recalculé automatiquement : 1 heure = 4 séquences.
                        </p>
                    </div>

                    <div class="form-group">
                        <label>Nombre de crédits <span>*</span></label>
                        <input
                            type="number"
                            name="nombre_credits"
                            min="0"
                            required
                            value="<?= htmlspecialchars($cours["nombre_credits"]) ?>"
                        >
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            Enregistrer les modifications
                        </button>

                        <a href="cours.php" class="btn-secondary">
                            Retour
                        </a>
                    </div>

                </form>

            </div>

        </section>

        <?php require_once "../includes/footer.php"; ?>

    </main>

</div>