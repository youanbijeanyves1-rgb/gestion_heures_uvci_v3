<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";

if($_SESSION["role"] !== "SECRETAIRE_PRINCIPAL"){
    header("Location: ../auth/login.php");
    exit;
}

$message = "";
$typeMessage = "";

/*
|--------------------------------------------------------------------------
| COURS ACTIFS AVEC ENSEIGNANT RESPONSABLE
|--------------------------------------------------------------------------
| Nouvelle règle métier :
| Une activité pédagogique est liée à un cours.
| L'enseignant de l'activité est automatiquement l'enseignant affecté au cours.
*/

$cours = $pdo->query("
    SELECT 
        c.id_cours,
        c.code_cours,
        c.intitule_cours,
        c.id_enseignant,
        e.nom,
        e.prenoms
    FROM cours c
    LEFT JOIN enseignant e 
        ON e.id_enseignant = c.id_enseignant
    WHERE c.actif = 1
    ORDER BY c.intitule_cours
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| ANNÉE ACADÉMIQUE ACTIVE
|--------------------------------------------------------------------------
*/

$anneeActive = $pdo->query("
    SELECT id_annee, libelle_annee
    FROM annee_academique
    WHERE est_active = 1
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| TRAITEMENT DU FORMULAIRE
|--------------------------------------------------------------------------
*/

if($_SERVER["REQUEST_METHOD"] === "POST"){

    $idCours = $_POST["id_cours"] ?? "";
    $titreRessource = trim($_POST["titre_ressource"] ?? "");
    $typeActivite = $_POST["type_activite"] ?? "";
    $niveauComplexite = $_POST["niveau_complexite"] ?? "";
    $nombreHeures = $_POST["nombre_heures"] ?? "";
    $observation = trim($_POST["observation"] ?? "");

    if(!$anneeActive){

        $message = "Aucune année académique active n’est définie.";
        $typeMessage = "error";

    }
    elseif(
        $idCours === "" ||
        $titreRessource === "" ||
        $typeActivite === "" ||
        $niveauComplexite === "" ||
        $nombreHeures === ""
    ){

        $message = "Veuillez remplir tous les champs obligatoires.";
        $typeMessage = "error";

    }
    elseif(!is_numeric($nombreHeures) || $nombreHeures <= 0){

        $message = "Le nombre d’heures doit être supérieur à 0.";
        $typeMessage = "error";

    }
    else{

        /*
        |--------------------------------------------------------------------------
        | RÉCUPÉRATION DE L'ENSEIGNANT DU COURS
        |--------------------------------------------------------------------------
        | Sécurité métier :
        | On ne fait pas confiance au formulaire pour l'enseignant.
        | On le récupère directement depuis la table cours.
        */

        $stmtCours = $pdo->prepare("
            SELECT 
                c.id_cours,
                c.id_enseignant,
                c.intitule_cours,
                e.nom,
                e.prenoms
            FROM cours c
            LEFT JOIN enseignant e 
                ON e.id_enseignant = c.id_enseignant
            WHERE c.id_cours = ?
              AND c.actif = 1
            LIMIT 1
        ");

        $stmtCours->execute([$idCours]);
        $coursSelectionne = $stmtCours->fetch(PDO::FETCH_ASSOC);

        if(!$coursSelectionne){

            $message = "Le cours sélectionné est introuvable ou inactif.";
            $typeMessage = "error";

        }
        elseif(empty($coursSelectionne["id_enseignant"])){

            $message = "Impossible d’enregistrer l’activité : aucun enseignant n’est affecté à ce cours.";
            $typeMessage = "error";

        }
        else{

            $idEnseignant = $coursSelectionne["id_enseignant"];

            /*
            |--------------------------------------------------------------------------
            | PARAMÈTRE DE CALCUL
            |--------------------------------------------------------------------------
            */

            $stmtParam = $pdo->prepare("
                SELECT id_parametre
                FROM parametre_calcul
                WHERE type_activite = :type_activite
                  AND niveau_complexite = :niveau_complexite
                  AND actif = 1
                LIMIT 1
            ");

            $stmtParam->execute([
                "type_activite" => $typeActivite,
                "niveau_complexite" => $niveauComplexite
            ]);

            $parametre = $stmtParam->fetch(PDO::FETCH_ASSOC);

            if(!$parametre){

                $message = "Aucun paramètre de calcul actif ne correspond à cette activité.";
                $typeMessage = "error";

            }else{

                try{

                    $pdo->beginTransaction();

                    /*
                    |--------------------------------------------------------------------------
                    | CRÉATION DE LA RESSOURCE PÉDAGOGIQUE
                    |--------------------------------------------------------------------------
                    */

                    $sqlRessource = "
                        INSERT INTO ressource_pedagogique(
                            titre_ressource,
                            type_ressource,
                            description,
                            actif,
                            id_cours
                        )
                        VALUES(
                            :titre_ressource,
                            :type_ressource,
                            :description,
                            1,
                            :id_cours
                        )
                    ";

                    $stmtRessource = $pdo->prepare($sqlRessource);

                    $stmtRessource->execute([
                        "titre_ressource" => $titreRessource,
                        "type_ressource" => "DOCUMENT_PEDAGOGIQUE",
                        "description" => $observation,
                        "id_cours" => $idCours
                    ]);

                    $idRessource = $pdo->lastInsertId();

                    /*
                    |--------------------------------------------------------------------------
                    | CRÉATION DE L’ACTIVITÉ PÉDAGOGIQUE
                    |--------------------------------------------------------------------------
                    | Important :
                    | nombre_heures ici est le nombre d'heures déclaré pour l'activité.
                    | Ce n'est pas le nombre d'heures du cours.
                    */

                    $sql = "
                        INSERT INTO activite_pedagogique(
                            type_activite,
                            niveau_complexite,
                            nombre_heures,
                            id_enseignant,
                            id_cours,
                            id_ressource,
                            id_annee,
                            id_parametre,
                            id_saisi_par,
                            observation
                        )
                        VALUES(
                            :type_activite,
                            :niveau_complexite,
                            :nombre_heures,
                            :id_enseignant,
                            :id_cours,
                            :id_ressource,
                            :id_annee,
                            :id_parametre,
                            :id_saisi_par,
                            :observation
                        )
                    ";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        "type_activite" => $typeActivite,
                        "niveau_complexite" => $niveauComplexite,
                        "nombre_heures" => $nombreHeures,
                        "id_enseignant" => $idEnseignant,
                        "id_cours" => $idCours,
                        "id_ressource" => $idRessource,
                        "id_annee" => $anneeActive["id_annee"],
                        "id_parametre" => $parametre["id_parametre"],
                        "id_saisi_par" => $_SESSION["id_utilisateur"],
                        "observation" => $observation
                    ]);

                    $pdo->commit();

                    $message = "Activité pédagogique enregistrée avec succès pour l’enseignant affecté au cours : "
                             . $coursSelectionne["nom"] . " " . $coursSelectionne["prenoms"]
                             . ". Elle est en attente de validation.";

                    $typeMessage = "success";

                }catch(Exception $e){

                    if($pdo->inTransaction()){
                        $pdo->rollBack();
                    }

                    $message = "Erreur lors de l’enregistrement de l’activité : " . $e->getMessage();
                    $typeMessage = "error";
                }
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
                <h1>Enregistrer une activité pédagogique</h1>
                <p>Création ou mise à jour d’une ressource pédagogique liée à un cours.</p>
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

                <?php if($anneeActive): ?>
                    <p class="info-text">
                        Année académique active :
                        <strong><?= htmlspecialchars($anneeActive["libelle_annee"]) ?></strong>
                    </p>
                <?php endif; ?>

                <form method="POST">

                    <div class="form-group">
                        <label>Cours <span>*</span></label>

                        <select name="id_cours" required>
                            <option value="">-- Sélectionner un cours --</option>

                            <?php foreach($cours as $c): ?>
                                <option value="<?= htmlspecialchars($c["id_cours"]) ?>">
                                    <?= htmlspecialchars($c["code_cours"] . " - " . $c["intitule_cours"]) ?>

                                    <?php if(!empty($c["id_enseignant"])): ?>
                                        —
                                        Enseignant :
                                        <?= htmlspecialchars($c["nom"] . " " . $c["prenoms"]) ?>
                                    <?php else: ?>
                                        —
                                        Aucun enseignant affecté
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <p class="info-text">
                            L’enseignant de l’activité est automatiquement celui affecté au cours sélectionné.
                        </p>
                    </div>

                    <div class="form-group">
                        <label>Ressource pédagogique <span>*</span></label>
                        <input 
                            type="text"
                            name="titre_ressource"
                            required
                            placeholder="Ex : Support de cours Algorithmique"
                        >
                    </div>

                    <div class="form-group">
                        <label>Type d’activité <span>*</span></label>
                        <select name="type_activite" required>
                            <option value="">-- Sélectionner le type --</option>
                            <option value="CREATION_RESSOURCE">Création de ressource pédagogique</option>
                            <option value="MISE_A_JOUR_RESSOURCE">Mise à jour de ressource pédagogique</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Niveau de complexité <span>*</span></label>
                        <select name="niveau_complexite" required>
                            <option value="">-- Sélectionner le niveau --</option>
                            <option value="NIVEAU_1">Niveau 1 : contenus simples + quiz + évaluations</option>
                            <option value="NIVEAU_2">Niveau 2 : activités interactives + quiz + évaluations</option>
                            <option value="NIVEAU_3">Niveau 3 : serious games, simulations, haute qualité</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nombre d’heures de l’activité <span>*</span></label>
                        <input type="number" name="nombre_heures" min="1" step="0.5" required>

                        <p class="info-text">
                            Ce nombre d’heures concerne l’activité pédagogique déclarée.
                            Il est indépendant du volume horaire administratif du cours.
                            Le nombre de séquences sera calculé automatiquement :
                            1 heure = 4 séquences.
                        </p>
                    </div>

                    <div class="form-group">
                        <label>Observation</label>
                        <textarea name="observation" rows="4"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            Enregistrer l’activité
                        </button>

                        <a href="activites.php" class="btn-secondary">
                            Retour
                        </a>
                    </div>

                </form>

            </div>

        </section>

        <?php require_once "../includes/footer.php"; ?>

    </main>

</div>