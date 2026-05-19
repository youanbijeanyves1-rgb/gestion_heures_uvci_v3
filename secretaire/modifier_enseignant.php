<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";

if($_SESSION["role"] !== "SECRETAIRE_PRINCIPAL"){
    header("Location: ../auth/login.php");
    exit;
}

$message = "";
$typeMessage = "";

$idEnseignant = (int)($_GET["id"] ?? 0);

if($idEnseignant <= 0){
    header("Location: liste_enseignants.php");
    exit;
}

$stmtEns = $pdo->prepare("
    SELECT *
    FROM enseignant
    WHERE id_enseignant = ?
    LIMIT 1
");
$stmtEns->execute([$idEnseignant]);
$enseignant = $stmtEns->fetch(PDO::FETCH_ASSOC);

if(!$enseignant){
    header("Location: liste_enseignants.php");
    exit;
}

$grades = $pdo->query("
    SELECT id_grade, libelle_grade 
    FROM grade 
    ORDER BY libelle_grade
")->fetchAll(PDO::FETCH_ASSOC);

$departements = $pdo->query("
    SELECT id_departement, nom_departement 
    FROM departement 
    WHERE actif = 1 
    ORDER BY nom_departement
")->fetchAll(PDO::FETCH_ASSOC);

$tauxHoraires = $pdo->query("
    SELECT 
        th.id_taux,
        th.statut,
        th.niveau,
        th.categorie,
        th.montant,
        g.libelle_grade,
        a.libelle_annee
    FROM taux_horaire th
    JOIN grade g ON g.id_grade = th.id_grade
    JOIN annee_academique a ON a.id_annee = th.id_annee
    WHERE th.actif = 1
    ORDER BY th.statut, g.libelle_grade, th.niveau
")->fetchAll(PDO::FETCH_ASSOC);

$comptesEnseignants = $pdo->prepare("
    SELECT u.id_utilisateur, u.login
    FROM utilisateur u
    JOIN role r ON r.id_role = u.id_role
    LEFT JOIN enseignant e ON e.id_utilisateur = u.id_utilisateur
    WHERE r.libelle_role = 'ENSEIGNANT'
      AND u.actif = 1
      AND (
            e.id_enseignant IS NULL
            OR e.id_enseignant = ?
          )
    ORDER BY u.login
");
$comptesEnseignants->execute([$idEnseignant]);
$comptesEnseignants = $comptesEnseignants->fetchAll(PDO::FETCH_ASSOC);

$stmtTauxActuels = $pdo->prepare("
    SELECT id_taux
    FROM enseignant_taux_horaire
    WHERE id_enseignant = ?
      AND actif = 1
");
$stmtTauxActuels->execute([$idEnseignant]);
$tauxActuels = $stmtTauxActuels->fetchAll(PDO::FETCH_COLUMN);

if($_SERVER["REQUEST_METHOD"] === "POST"){

    $nom = trim($_POST["nom"] ?? "");
    $prenoms = trim($_POST["prenoms"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $telephone = trim($_POST["telephone"] ?? "");
    $statut = $_POST["statut"] ?? "";
    $idGrade = $_POST["id_grade"] ?? "";
    $idDepartement = $_POST["id_departement"] ?? "";
    $idUtilisateur = $_POST["id_utilisateur"] ?? null;
    $idTauxSelectionnes = $_POST["id_taux"] ?? [];

    if($idUtilisateur === ""){
        $idUtilisateur = null;
    }

    if(!is_array($idTauxSelectionnes)){
        $idTauxSelectionnes = [$idTauxSelectionnes];
    }

    $idTauxSelectionnes = array_values(array_filter($idTauxSelectionnes));

    if(
        $nom === "" ||
        $prenoms === "" ||
        $email === "" ||
        $telephone === "" ||
        $statut === "" ||
        $idGrade === "" ||
        $idDepartement === "" ||
        empty($idTauxSelectionnes)
    ){
        $message = "Veuillez remplir tous les champs obligatoires.";
        $typeMessage = "error";
    }
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $message = "L'adresse email n'est pas valide.";
        $typeMessage = "error";
    }
    elseif(!in_array($statut, ["PERMANENT", "VACATAIRE"])){
        $message = "Statut invalide.";
        $typeMessage = "error";
    }
    else{

        $verifEmail = $pdo->prepare("
            SELECT COUNT(*) 
            FROM enseignant 
            WHERE email = ?
              AND id_enseignant <> ?
        ");
        $verifEmail->execute([$email, $idEnseignant]);

        if($verifEmail->fetchColumn() > 0){
            $message = "Cet email est déjà utilisé par un autre enseignant.";
            $typeMessage = "error";
        }else{

            try{

                $pdo->beginTransaction();

                $idTauxPrincipal = $idTauxSelectionnes[0];

                $stmtUpdate = $pdo->prepare("
                    UPDATE enseignant
                    SET
                        nom = :nom,
                        prenoms = :prenoms,
                        email = :email,
                        telephone = :telephone,
                        statut = :statut,
                        id_departement = :id_departement,
                        id_grade = :id_grade,
                        id_taux = :id_taux,
                        id_utilisateur = :id_utilisateur
                    WHERE id_enseignant = :id_enseignant
                ");

                $stmtUpdate->execute([
                    "nom" => $nom,
                    "prenoms" => $prenoms,
                    "email" => $email,
                    "telephone" => $telephone,
                    "statut" => $statut,
                    "id_departement" => $idDepartement,
                    "id_grade" => $idGrade,
                    "id_taux" => $idTauxPrincipal,
                    "id_utilisateur" => $idUtilisateur,
                    "id_enseignant" => $idEnseignant
                ]);

                $stmtDeleteTaux = $pdo->prepare("
                    DELETE FROM enseignant_taux_horaire
                    WHERE id_enseignant = ?
                ");
                $stmtDeleteTaux->execute([$idEnseignant]);

                $stmtInsertTaux = $pdo->prepare("
                    INSERT INTO enseignant_taux_horaire(
                        id_enseignant,
                        id_taux,
                        actif
                    )
                    VALUES(
                        :id_enseignant,
                        :id_taux,
                        1
                    )
                ");

                foreach($idTauxSelectionnes as $idTaux){
                    $stmtInsertTaux->execute([
                        "id_enseignant" => $idEnseignant,
                        "id_taux" => $idTaux
                    ]);
                }

                $pdo->commit();

                $message = "Enseignant modifié avec succès.";
                $typeMessage = "success";

                $stmtEns->execute([$idEnseignant]);
                $enseignant = $stmtEns->fetch(PDO::FETCH_ASSOC);

                $stmtTauxActuels->execute([$idEnseignant]);
                $tauxActuels = $stmtTauxActuels->fetchAll(PDO::FETCH_COLUMN);

            }catch(Exception $e){

                if($pdo->inTransaction()){
                    $pdo->rollBack();
                }

                $message = "Erreur lors de la modification : " . $e->getMessage();
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
                <h1>Modification d’un enseignant</h1>
                <p>Modifier les informations administratives et les taux horaires associés.</p>
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
                        <label>Nom <span>*</span></label>
                        <input 
                            type="text" 
                            name="nom" 
                            required
                            value="<?= htmlspecialchars($enseignant["nom"]) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Prénoms <span>*</span></label>
                        <input 
                            type="text" 
                            name="prenoms" 
                            required
                            value="<?= htmlspecialchars($enseignant["prenoms"]) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Email <span>*</span></label>
                        <input 
                            type="email" 
                            name="email" 
                            required
                            value="<?= htmlspecialchars($enseignant["email"]) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Téléphone <span>*</span></label>
                        <input 
                            type="text" 
                            name="telephone" 
                            required
                            value="<?= htmlspecialchars($enseignant["telephone"]) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Statut <span>*</span></label>
                        <select name="statut" required>
                            <option value="">-- Sélectionner le statut --</option>

                            <option value="PERMANENT" <?= $enseignant["statut"] === "PERMANENT" ? "selected" : "" ?>>
                                Permanent
                            </option>

                            <option value="VACATAIRE" <?= $enseignant["statut"] === "VACATAIRE" ? "selected" : "" ?>>
                                Vacataire
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Grade <span>*</span></label>
                        <select name="id_grade" required>
                            <option value="">-- Sélectionner un grade --</option>

                            <?php foreach($grades as $grade): ?>
                                <option 
                                    value="<?= $grade["id_grade"] ?>"
                                    <?= (string)$enseignant["id_grade"] === (string)$grade["id_grade"] ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($grade["libelle_grade"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Département <span>*</span></label>
                        <select name="id_departement" required>
                            <option value="">-- Sélectionner un département --</option>

                            <?php foreach($departements as $departement): ?>
                                <option 
                                    value="<?= $departement["id_departement"] ?>"
                                    <?= (string)$enseignant["id_departement"] === (string)$departement["id_departement"] ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($departement["nom_departement"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Taux horaires <span>*</span></label>
                        <small>
                            Maintenir CTRL pour sélectionner plusieurs taux horaires si nécessaire.
                        </small>

                        <select name="id_taux[]" multiple required size="7">
                            <?php foreach($tauxHoraires as $taux): ?>
                                <option 
                                    value="<?= $taux["id_taux"] ?>"
                                    <?= in_array($taux["id_taux"], $tauxActuels) ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($taux["libelle_annee"]) ?>
                                    —
                                    <?= htmlspecialchars($taux["statut"]) ?>
                                    —
                                    <?= htmlspecialchars($taux["libelle_grade"]) ?>
                                    —
                                    <?= htmlspecialchars($taux["niveau"]) ?>
                                    —
                                    <?= number_format($taux["montant"], 0, ',', ' ') ?> FCFA
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Compte utilisateur lié</label>
                        <select name="id_utilisateur">
                            <option value="">Aucun compte lié</option>

                            <?php foreach($comptesEnseignants as $compte): ?>
                                <option 
                                    value="<?= $compte["id_utilisateur"] ?>"
                                    <?= (string)($enseignant["id_utilisateur"] ?? "") === (string)$compte["id_utilisateur"] ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($compte["login"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            Enregistrer les modifications
                        </button>

                        <a href="liste_enseignants.php" class="btn-secondary">
                            Retour
                        </a>
                    </div>

                </form>

            </div>

        </section>

        <?php require_once "../includes/footer.php"; ?>

    </main>

</div>