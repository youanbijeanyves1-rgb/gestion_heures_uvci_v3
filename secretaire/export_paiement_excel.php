<?php
date_default_timezone_set('Africa/Abidjan');
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

if($idAnnee === ""){
    $stmtAnnee = $pdo->query("
        SELECT id_annee 
        FROM annee_academique 
        WHERE est_active = 1 
        LIMIT 1
    ");
    $idAnnee = $stmtAnnee->fetchColumn();
}

$stmtLibelleAnnee = $pdo->prepare("
    SELECT libelle_annee 
    FROM annee_academique 
    WHERE id_annee = ?
");
$stmtLibelleAnnee->execute([$idAnnee]);
$libelleAnnee = $stmtLibelleAnnee->fetchColumn() ?: "Non définie";

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
| TOTAUX GLOBAUX PAR ENSEIGNANT
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

    $totauxParEnseignant[$id]["volume_total"] +=
        (float)$ligne["volume_total"];
}

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=etat_paiements.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF";

$periode = "Toutes les périodes";

if($dateDebut !== "" && $dateFin !== ""){
    $periode = "Du " .
        date("d/m/Y", strtotime($dateDebut)) .
        " au " .
        date("d/m/Y", strtotime($dateFin));
}

echo "
<table border='1'>

<tr>
    <th colspan='11' style='font-size:18px;'>
        ÉTAT GLOBAL DES PAIEMENTS
    </th>
</tr>

<tr>
    <td colspan='11'>
        <strong>Année académique :</strong>
        ".htmlspecialchars($libelleAnnee)."
    </td>
</tr>

<tr>
    <td colspan='11'>
        <strong>Période :</strong>
        ".htmlspecialchars($periode)."
    </td>
</tr>

<tr>
    <td colspan='11'>
        <strong>Date d’édition :</strong>
        ".date("d/m/Y à H:i")."
    </td>
</tr>

<tr>
    <td colspan='11'>
        <strong>Règle paiement :</strong>
        Vacataire = tout le volume validé est payable ;
        Permanent = les heures complémentaires sont calculées globalement puis réparties proportionnellement entre les niveaux.
    </td>
</tr>

<tr></tr>

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
";

$totalMontant = 0;
$totalVolume = 0;
$totalPayable = 0;

foreach($lignes as $ligne){

    $id = $ligne["id_enseignant"];

    $niveauCours = $ligne["niveau"];
    $niveauTaux = niveauTauxDepuisNiveauCours($niveauCours);

    $taux = 0;

    if($niveauTaux !== null){

        $stmtTaux->execute([
            $ligne["statut"],
            $ligne["id_grade"],
            $niveauTaux,
            $idAnnee
        ]);

        $tauxTrouve = $stmtTaux->fetch(PDO::FETCH_ASSOC);

        $taux = (float)($tauxTrouve["montant"] ?? 0);
    }

    $volumeValide = (float)$ligne["volume_total"];

    $volumeGlobal =
        $totauxParEnseignant[$id]["volume_total"];

    $charge =
        $totauxParEnseignant[$id]["charge_statutaire"];

    if($ligne["statut"] === "VACATAIRE"){

        $heuresPayables = $volumeValide;
        $chargeAffichee = "Non concerné";

    }else{

        $heuresComplementairesGlobales =
            max(0, $volumeGlobal - $charge);

        if($volumeGlobal > 0){

            $proportion =
                $volumeValide / $volumeGlobal;

            $heuresPayables =
                $heuresComplementairesGlobales * $proportion;

        }else{

            $heuresPayables = 0;
        }

        $chargeAffichee =
            number_format($charge, 2, ",", " ") . " h";
    }

    $montantPayer = $heuresPayables * $taux;

    $totalMontant += $montantPayer;
    $totalVolume += $volumeValide;
    $totalPayable += $heuresPayables;

    echo "
    <tr>

        <td>
            ".htmlspecialchars(
                $ligne["nom"] . " " . $ligne["prenoms"]
            )."
        </td>

        <td>
            ".htmlspecialchars(
                $ligne["libelle_grade"] ?? "Non défini"
            )."
        </td>

        <td>
            ".htmlspecialchars($ligne["statut"])."
        </td>

        <td>
            ".htmlspecialchars($niveauCours ?? "Non défini")."
        </td>

        <td>
            ".htmlspecialchars($niveauTaux ?? "Non défini")."
        </td>

        <td>
            ".number_format(
                $volumeValide,
                2,
                ",",
                " "
            )." h
        </td>

        <td>
            ".$chargeAffichee."
        </td>

        <td>
            ".number_format(
                $volumeGlobal,
                2,
                ",",
                " "
            )." h
        </td>

        <td>
            ".number_format(
                $heuresPayables,
                2,
                ",",
                " "
            )." h
        </td>

        <td>
            ".number_format(
                $taux,
                0,
                ",",
                " "
            )." FCFA
        </td>

        <td>
            ".number_format(
                $montantPayer,
                0,
                ",",
                " "
            )." FCFA
        </td>

    </tr>
    ";
}

echo "

<tr>
    <td colspan='5'>
        <strong>TOTAUX</strong>
    </td>

    <td>
        <strong>
            ".number_format(
                $totalVolume,
                2,
                ",",
                " "
            )." h
        </strong>
    </td>

    <td></td>

    <td></td>

    <td>
        <strong>
            ".number_format(
                $totalPayable,
                2,
                ",",
                " "
            )." h
        </strong>
    </td>

    <td></td>

    <td>
        <strong>
            ".number_format(
                $totalMontant,
                0,
                ",",
                " "
            )." FCFA
        </strong>
    </td>
</tr>

</table>
";

exit;