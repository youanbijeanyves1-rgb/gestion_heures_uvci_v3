<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";
require_once "../includes/fonctions_metier.php";

if($_SESSION["role"] !== "SECRETAIRE_PRINCIPAL"){
    header("Location: ../auth/login.php");
    exit;
}

$dateDebut = $_GET["date_debut"] ?? "";
$dateFin = $_GET["date_fin"] ?? "";
$idAnnee = $_GET["id_annee"] ?? "";
$idEnseignant = $_GET["id_enseignant"] ?? "";

$annees = $pdo->query("
    SELECT id_annee, libelle_annee, est_active
    FROM annee_academique
    ORDER BY date_debut DESC
")->fetchAll(PDO::FETCH_ASSOC);

$enseignants = $pdo->query("
    SELECT id_enseignant, nom, prenoms
    FROM enseignant
    ORDER BY nom, prenoms
")->fetchAll(PDO::FETCH_ASSOC);

if($idAnnee === ""){
    foreach($annees as $a){
        if((int)$a["est_active"] === 1){
            $idAnnee = $a["id_annee"];
            break;
        }
    }
}

$params = [];
$where = "ap.statut_validation = 'VALIDEE'";

if($idAnnee !== ""){
    $where .= " AND ap.id_annee = :id_annee";
    $params["id_annee"] = $idAnnee;
}

if($dateDebut !== "" && $dateFin !== ""){
    $where .= " AND DATE(ap.date_saisie) BETWEEN :date_debut AND :date_fin";
    $params["date_debut"] = $dateDebut;
    $params["date_fin"] = $dateFin;
}

if($idEnseignant !== ""){
    $where .= " AND e.id_enseignant = :id_enseignant";
    $params["id_enseignant"] = $idEnseignant;
}

$sql = "
SELECT
    e.id_enseignant,
    e.nom,
    e.prenoms,
    e.statut,
    e.id_grade,
    g.libelle_grade,
    g.charge_statutaire,
    cf.niveau,
    SUM(ap.volume_horaire_calcule) AS volume_total
FROM activite_pedagogique ap
JOIN enseignant e ON e.id_enseignant = ap.id_enseignant
LEFT JOIN grade g ON g.id_grade = e.id_grade
JOIN cours c ON c.id_cours = ap.id_cours
LEFT JOIN cours_filiere cf ON cf.id_cours = c.id_cours
WHERE $where
GROUP BY
    e.id_enseignant,
    e.nom,
    e.prenoms,
    e.statut,
    e.id_grade,
    g.libelle_grade,
    g.charge_statutaire,
    cf.niveau
ORDER BY e.nom, e.prenoms, cf.niveau
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtTaux = $pdo->prepare("
    SELECT montant
    FROM taux_horaire
    WHERE statut = ?
      AND id_grade = ?
      AND niveau = ?
      AND id_annee = ?
      AND actif = 1
    LIMIT 1
");

/*
|--------------------------------------------------------------------------
| CALCUL GLOBAL PAR ENSEIGNANT
|--------------------------------------------------------------------------
*/

$totauxParEnseignant = [];

foreach($lignes as $ligne){
    $id = $ligne["id_enseignant"];

    if(!isset($totauxParEnseignant[$id])){
        $totauxParEnseignant[$id] = [
            "volume_total" => 0,
            "charge_statutaire" => (float)($ligne["charge_statutaire"] ?? 0),
            "statut" => $ligne["statut"]
        ];
    }

    $totauxParEnseignant[$id]["volume_total"] += (float)$ligne["volume_total"];
}

/*
|--------------------------------------------------------------------------
| CALCUL PAR LIGNE AVEC RÉPARTITION PROPORTIONNELLE
|--------------------------------------------------------------------------
*/

$totalGeneral = 0;
$totalVolume = 0;
$totalPayable = 0;
$totalEnseignants = [];

foreach($lignes as &$l){

    $id = $l["id_enseignant"];
    $niveauCours = $l["niveau"];
    $niveauTaux = niveauTauxDepuisNiveauCours($niveauCours);

    $volumeLigne = (float)$l["volume_total"];
    $volumeGlobalEnseignant = $totauxParEnseignant[$id]["volume_total"];
    $charge = $totauxParEnseignant[$id]["charge_statutaire"];
    $statut = $totauxParEnseignant[$id]["statut"];

    $taux = 0;

    if($niveauTaux !== null && $idAnnee !== ""){
        $stmtTaux->execute([
            $l["statut"],
            $l["id_grade"],
            $niveauTaux,
            $idAnnee
        ]);

        $tauxTrouve = $stmtTaux->fetch(PDO::FETCH_ASSOC);
        $taux = (float)($tauxTrouve["montant"] ?? 0);
    }

    if($statut === "VACATAIRE"){
        $heuresPayables = $volumeLigne;
    }else{
        $heuresComplementairesGlobales = max(0, $volumeGlobalEnseignant - $charge);

        if($volumeGlobalEnseignant > 0){
            $proportion = $volumeLigne / $volumeGlobalEnseignant;
            $heuresPayables = $heuresComplementairesGlobales * $proportion;
        }else{
            $heuresPayables = 0;
        }
    }

    $montant = $heuresPayables * $taux;

    $l["niveau_taux"] = $niveauTaux;
    $l["taux_horaire"] = $taux;
    $l["volume_global_enseignant"] = $volumeGlobalEnseignant;
    $l["heures_payables"] = $heuresPayables;
    $l["montant_a_payer"] = $montant;

    $totalVolume += $volumeLigne;
    $totalPayable += $heuresPayables;
    $totalGeneral += $montant;
    $totalEnseignants[$id] = true;
}
unset($l);

$lienPdf = "export_paiement_pdf.php?id_annee=" . urlencode($idAnnee)
    . "&id_enseignant=" . urlencode($idEnseignant)
    . "&date_debut=" . urlencode($dateDebut)
    . "&date_fin=" . urlencode($dateFin);

$lienExcel = "export_paiement_excel.php?id_annee=" . urlencode($idAnnee)
    . "&id_enseignant=" . urlencode($idEnseignant)
    . "&date_debut=" . urlencode($dateDebut)
    . "&date_fin=" . urlencode($dateFin);

?>

<?php require_once "../includes/header.php"; ?>

<div class="wrapper">

    <?php require_once "../includes/sidebar_secretaire.php"; ?>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>États de paiement</h1>
                <p>État global et état individuel des paiements.</p>

                <a href="<?= htmlspecialchars($lienPdf) ?>" class="btn-export-pdf">
                    Exporter PDF
                </a>

                <a href="<?= htmlspecialchars($lienExcel) ?>" class="btn-export-excel">
                    Exporter EXCEL
                </a>
            </div>

            <div class="badge-role">
                SECRÉTAIRE PRINCIPAL
            </div>
        </header>

        <section class="content">

            <div class="filter-card no-print">
                <form method="GET" class="filter-form">

                    <div class="form-group">
                        <label>Année académique</label>
                        <select name="id_annee" required>
                            <?php foreach($annees as $a): ?>
                                <option value="<?= $a["id_annee"] ?>" <?= $idAnnee == $a["id_annee"] ? "selected" : "" ?>>
                                    <?= htmlspecialchars($a["libelle_annee"]) ?>
                                    <?= (int)$a["est_active"] === 1 ? " — Active" : "" ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Enseignant</label>
                        <select name="id_enseignant">
                            <option value="">Tous les enseignants</option>

                            <?php foreach($enseignants as $e): ?>
                                <option value="<?= $e["id_enseignant"] ?>" <?= $idEnseignant == $e["id_enseignant"] ? "selected" : "" ?>>
                                    <?= htmlspecialchars($e["nom"] . " " . $e["prenoms"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Date début</label>
                        <input type="date" name="date_debut" value="<?= htmlspecialchars($dateDebut) ?>">
                    </div>

                    <div class="form-group">
                        <label>Date fin</label>
                        <input type="date" name="date_fin" value="<?= htmlspecialchars($dateFin) ?>">
                    </div>

                    <button type="submit" class="btn-primary">
                        Générer
                    </button>

                    <a href="paiements.php" class="btn-secondary">
                        Réinitialiser
                    </a>

                </form>
            </div>

            <div class="cards">

                <div class="card">
                    <h3>Enseignants concernés</h3>
                    <p><?= count($totalEnseignants) ?></p>
                </div>

                <div class="card">
                    <h3>Volume validé</h3>
                    <p><?= number_format($totalVolume, 2, ',', ' ') ?> h</p>
                </div>

                <div class="card">
                    <h3>Heures payables</h3>
                    <p><?= number_format($totalPayable, 2, ',', ' ') ?> h</p>
                </div>

                <div class="card">
                    <h3>Montant total</h3>
                    <p><?= number_format($totalGeneral, 0, ',', ' ') ?> FCFA</p>
                </div>

            </div>

            <br>

            <div class="card">

                <div class="card-header">
                    <h2>
                        <?= $idEnseignant === "" ? "État global de paiement" : "État individuel de paiement" ?>
                    </h2>

                    <div class="regle-paiement">
                        <p>
                            <strong>Période :</strong>
                            <?php if($dateDebut !== "" && $dateFin !== ""): ?>
                                du <?= date("d/m/Y", strtotime($dateDebut)) ?>
                                au <?= date("d/m/Y", strtotime($dateFin)) ?>
                            <?php else: ?>
                                Toutes les périodes
                            <?php endif; ?>
                        </p>

                        <p>
                            <strong>Règle niveau :</strong>
                            L1, L2, L3 utilisent le taux LICENCE.
                            M1, M2 utilisent le taux MASTER.
                        </p>

                        <p>
                            <strong>Règle paiement :</strong>
                            Vacataire = tout le volume validé est payable.
                            Permanent = les heures complémentaires sont calculées sur le total validé de l’enseignant, puis réparties proportionnellement entre les niveaux.
                        </p>
                    </div>
                </div>

                <div class="table-responsive">

                    <table class="table table-paiement">

                        <thead>
                            <tr>
                                <th>Enseignant</th>
                                <th>Grade</th>
                                <th>Statut</th>
                                <th>Niveau cours</th>
                                <th>Niveau taux</th>
                                <th>Volume validé</th>
                                <th>Charge statutaire</th>
                                <th>Volume global</th>
                                <th>Heures payables</th>
                                <th>Taux horaire</th>
                                <th>Montant à payer</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if(count($lignes) > 0): ?>

                                <?php foreach($lignes as $l): ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <?= htmlspecialchars($l["nom"] . " " . $l["prenoms"]) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($l["libelle_grade"] ?? "Non défini") ?>
                                        </td>

                                        <td>
                                            <span class="badge-statut">
                                                <?= htmlspecialchars($l["statut"]) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($l["niveau"] ?? "Non défini") ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($l["niveau_taux"] ?? "Non défini") ?>
                                        </td>

                                        <td>
                                            <?= number_format($l["volume_total"], 2, ',', ' ') ?> h
                                        </td>

                                        <td>
                                            <?php if($l["statut"] === "PERMANENT"): ?>
                                                <?= number_format($l["charge_statutaire"], 2, ',', ' ') ?> h
                                            <?php else: ?>
                                                Non concerné
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?= number_format($l["volume_global_enseignant"], 2, ',', ' ') ?> h
                                        </td>

                                        <td>
                                            <span class="badge-volume">
                                                <?= number_format($l["heures_payables"], 2, ',', ' ') ?> h
                                            </span>
                                        </td>

                                        <td>
                                            <?php if($l["taux_horaire"] > 0): ?>
                                                <?= number_format($l["taux_horaire"], 0, ',', ' ') ?> FCFA
                                            <?php else: ?>
                                                <span class="badge danger">Taux manquant</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if($l["montant_a_payer"] > 0): ?>
                                                <span class="montant">
                                                    <?= number_format($l["montant_a_payer"], 0, ',', ' ') ?> FCFA
                                                </span>
                                            <?php else: ?>
                                                <span class="montant-zero">
                                                    0 FCFA
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="11" class="text-center">
                                        Aucun état de paiement disponible pour cette période.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </section>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>