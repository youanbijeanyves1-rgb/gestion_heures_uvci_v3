<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";

if($_SESSION["role"] !== "ADMINISTRATEUR"){
    header("Location: ../auth/login.php");
    exit;
}

$id = (int)($_GET["id"] ?? 0);

if($id <= 0){
    header("Location: liste_utilisateurs.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Récupération utilisateur
|--------------------------------------------------------------------------
*/

$sqlUtilisateur = "
SELECT
    u.id_utilisateur,
    u.login,
    u.id_role,
    u.actif,
    r.libelle_role
FROM utilisateur u
JOIN role r
    ON r.id_role = u.id_role
WHERE u.id_utilisateur = ?
LIMIT 1
";

$stmtUtilisateur = $pdo->prepare($sqlUtilisateur);
$stmtUtilisateur->execute([$id]);

$utilisateur = $stmtUtilisateur->fetch(PDO::FETCH_ASSOC);

if(!$utilisateur){
    header("Location: liste_utilisateurs.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Liste des rôles
|--------------------------------------------------------------------------
*/

$roles = $pdo->query("
    SELECT id_role, libelle_role
    FROM role
    ORDER BY libelle_role ASC
")->fetchAll(PDO::FETCH_ASSOC);

$erreur = "";
$succes = "";

/*
|--------------------------------------------------------------------------
| Traitement formulaire
|--------------------------------------------------------------------------
*/

if($_SERVER["REQUEST_METHOD"] === "POST"){

    $login = trim($_POST["login"] ?? "");
    $idRole = (int)($_POST["id_role"] ?? 0);
    $motDePasse = trim($_POST["mot_de_passe"] ?? "");

    if($login === ""){
        $erreur = "Le login est obligatoire.";
    }

    elseif($idRole <= 0){
        $erreur = "Le rôle est obligatoire.";
    }

    else{

        /*
        |--------------------------------------------------------------------------
        | Vérification doublon login
        |--------------------------------------------------------------------------
        */

        $sqlDoublon = "
        SELECT id_utilisateur
        FROM utilisateur
        WHERE login = ?
        AND id_utilisateur != ?
        LIMIT 1
        ";

        $stmtDoublon = $pdo->prepare($sqlDoublon);
        $stmtDoublon->execute([$login, $id]);

        if($stmtDoublon->fetch()){

            $erreur = "Ce login existe déjà.";

        }else{

            /*
            |--------------------------------------------------------------------------
            | Mise à jour
            |--------------------------------------------------------------------------
            */

            if($motDePasse !== ""){

                $motDePasseHash = password_hash(
                    $motDePasse,
                    PASSWORD_DEFAULT
                );

                $sqlUpdate = "
                UPDATE utilisateur
                SET
                    login = ?,
                    id_role = ?,
                    mot_de_passe_hash = ?
                WHERE id_utilisateur = ?
                ";

                $stmtUpdate = $pdo->prepare($sqlUpdate);

                $stmtUpdate->execute([
                    $login,
                    $idRole,
                    $motDePasseHash,
                    $id
                ]);

            }else{

                $sqlUpdate = "
                UPDATE utilisateur
                SET
                    login = ?,
                    id_role = ?
                WHERE id_utilisateur = ?
                ";

                $stmtUpdate = $pdo->prepare($sqlUpdate);

                $stmtUpdate->execute([
                    $login,
                    $idRole,
                    $id
                ]);
            }

            $succes = "Utilisateur modifié avec succès.";

            /*
            |--------------------------------------------------------------------------
            | Recharge utilisateur
            |--------------------------------------------------------------------------
            */

            $stmtUtilisateur->execute([$id]);
            $utilisateur = $stmtUtilisateur->fetch(PDO::FETCH_ASSOC);
        }
    }
}

?>

<?php require_once "../includes/header.php"; ?>

<div class="wrapper">

    <?php require_once "../includes/sidebar_admin.php"; ?>

    <main class="main">

        <header class="topbar">

            <div>
                <h1>Modification d'un utilisateur</h1>
                <p>Modifier les informations du compte utilisateur.</p>
            </div>

            <div class="user-box">
                <span><?= date("d/m/Y") ?></span>
                <strong><?= htmlspecialchars($_SESSION["login"]) ?></strong>
                <small>ADMINISTRATEUR</small>
            </div>

        </header>

        <section class="content">

            <div class="form-card">

                <?php if($erreur !== ""): ?>
                    <div class="alert danger">
                        <?= htmlspecialchars($erreur) ?>
                    </div>
                <?php endif; ?>

                <?php if($succes !== ""): ?>
                    <div class="alert success">
                        <?= htmlspecialchars($succes) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="form-grid">

                    <div class="form-group">
                        <label>Login *</label>

                        <input
                            type="text"
                            name="login"
                            required
                            value="<?= htmlspecialchars($utilisateur["login"]) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Rôle *</label>

                        <select name="id_role" required>

                            <option value="">
                                Sélectionner un rôle
                            </option>

                            <?php foreach($roles as $role): ?>

                                <option
                                    value="<?= $role["id_role"] ?>"
                                    <?= $role["id_role"] == $utilisateur["id_role"] ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($role["libelle_role"]) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>
                            Nouveau mot de passe
                            (laisser vide pour conserver l'ancien)
                        </label>

                        <input
                            type="password"
                            name="mot_de_passe"
                        >
                    </div>

                    <div class="form-actions">

                        <button type="submit" class="btn-primary">
                            Enregistrer les modifications
                        </button>

                        <a href="liste_utilisateurs.php"
                           class="btn-secondary">
                            Retour
                        </a>

                    </div>

                </form>

            </div>

        </section>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>