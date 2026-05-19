<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";
require_once "../includes/fonctions_metier.php";
require_once "../vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

if($_SESSION["role"] !== "SECRETAIRE_PRINCIPAL"){
    header("Location: ../auth/login.php");
    exit;
}

$dateDebut = $_GET["date_debut"] ?? "";
$dateFin = $_GET["date_fin"] ?? "";
$idAnnee = $_GET["id_annee"] ?? "";
$idEnseignant = $_GET["id_enseignant"] ?? "";

/*
|--------------------------------------------------------------------------
| ANNÉE ACADÉMIQUE
|--------------------------------------------------------------------------
*/

if($idAnnee === ""){
    $stmtAnneeActive = $pdo->query("
        SELECT id_annee
        FROM annee_academique
        WHERE est_active = 1
        LIMIT 1
    ");

    $idAnnee = $stmtAnneeActive->fetchColumn();
}

$stmtAnnee = $pdo->prepare("
    SELECT libelle_annee
    FROM annee_academique
    WHERE id_annee = ?
");
$stmtAnnee->execute([$idAnnee]);
$libelleAnnee = $stmtAnnee->fetchColumn() ?: "Non définie";

/*
|--------------------------------------------------------------------------
| CONSTRUCTION DES FILTRES
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| DONNÉES DE PAIEMENT
|--------------------------------------------------------------------------
*/

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
JOIN enseignant e 
    ON e.id_enseignant = ap.id_enseignant
LEFT JOIN grade g 
    ON g.id_grade = e.id_grade
JOIN cours c 
    ON c.id_cours = ap.id_cours
LEFT JOIN cours_filiere cf 
    ON cf.id_cours = c.id_cours
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
ORDER BY e.nom ASC, e.prenoms ASC, cf.niveau ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$donnees = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| REQUÊTE DE TAUX HORAIRE
|--------------------------------------------------------------------------
*/

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
| LIBELLÉ PÉRIODE
|--------------------------------------------------------------------------
*/

$periode = "Toutes les périodes";

if($dateDebut !== "" && $dateFin !== ""){
    $periode = "Du " . date("d/m/Y", strtotime($dateDebut)) . " au " . date("d/m/Y", strtotime($dateFin));
}

/*
|--------------------------------------------------------------------------
| HTML DU PDF
|--------------------------------------------------------------------------
*/

$totalGeneral = 0;
$totalVolume = 0;
$totalHeuresPayables = 0;
$totalEnseignants = [];

$html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">

    <style>
        body{
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111827;
        }

        .header{
            text-align: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .header h1{
            margin: 0;
            color: #1e3a8a;
            font-size: 22px;
            text-transform: uppercase;
        }

        .header p{
            margin: 5px 0 0;
            color: #475569;
        }

        .infos{
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 10px 12px;
            margin-bottom: 16px;
            line-height: 1.7;
        }

        .stats{
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .stats td{
            border: 1px solid #cbd5e1;
            padding: 9px;
            text-align: center;
            font-weight: bold;
        }

        .stats .label{
            background: #e0f2fe;
            color: #1e3a8a;
        }

        table{
            width: 100%;
            border-collapse: collapse;
        }

        th{
            background: #1e1b4b;
            color: white;
            padding: 7px;
            font-size: 10px;
        }

        td{
            border: 1px solid #cbd5e1;
            padding: 6px;
            font-size: 9.5px;
        }

        .right{
            text-align: right;
        }

        .center{
            text-align: center;
        }

        .danger{
            color: #991b1b;
            font-weight: bold;
        }

        .total{
            margin-top: 18px;
            padding: 10px;
            border: 1px solid #1e3a8a;
            background: #eff6ff;
            font-size: 13px;
            font-weight: bold;
            text-align: right;
        }

        .footer{
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #64748b;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>État global des paiements</h1>
        <p>Université Virtuelle de Côte d’Ivoire — Gestion des heures des enseignants</p>
    </div>

    <div class="infos">
        <strong>Année académique :</strong> '.htmlspecialchars($libelleAnnee).'<br>
        <strong>Période :</strong> '.htmlspecialchars($periode).'<br>
        <strong>Date d’édition :</strong> '.date("d/m/Y à H:i").'<br>
        <strong>Règle de paiement :</strong> Vacataire = tout le volume validé est payable ; Permanent = seules les heures complémentaires sont payables.
    </div>
';

ob_start();

?>

<table>
    <thead>
        <tr>
            <th>Enseignant</th>
            <th>Grade</th>
            <th>Statut</th>
            <th>Niveau cours</th>
            <th>Niveau taux</th>
            <th>Volume validé</th>
            <th>Charge statutaire</th>
            <th>Heures payables</th>
            <th>Taux horaire</th>
            <th>Montant</th>
        </tr>
    </thead>

    <tbody>

        <?php if(count($donnees) > 0): ?>

            <?php foreach($donnees as $d): ?>

                <?php

                $niveauCours = $d["niveau"];
                $niveauTaux = niveauTauxDepuisNiveauCours($niveauCours);

                $taux = 0;

                if($niveauTaux !== null && $idAnnee !== ""){
                    $stmtTaux->execute([
                        $d["statut"],
                        $d["id_grade"],
                        $niveauTaux,
                        $idAnnee
                    ]);

                    $tauxTrouve = $stmtTaux->fetch(PDO::FETCH_ASSOC);
                    $taux = (float)($tauxTrouve["montant"] ?? 0);
                }

                $volumeValide = (float)$d["volume_total"];
                $chargeStatutaire = (float)($d["charge_statutaire"] ?? 0);

                if($d["statut"] === "VACATAIRE"){
                    $heuresPayables = $volumeValide;
                    $chargeAffichee = "Non concerné";
                }else{
                    $heuresPayables = max(0, $volumeValide - $chargeStatutaire);
                    $chargeAffichee = number_format($chargeStatutaire, 2, ",", " ") . " h";
                }

                $montant = $heuresPayables * $taux;

                $totalGeneral += $montant;
                $totalVolume += $volumeValide;
                $totalHeuresPayables += $heuresPayables;
                $totalEnseignants[$d["id_enseignant"]] = true;

                ?>

                <tr>
                    <td><?= htmlspecialchars($d["nom"] . " " . $d["prenoms"]) ?></td>
                    <td><?= htmlspecialchars($d["libelle_grade"] ?? "Non défini") ?></td>
                    <td class="center"><?= htmlspecialchars($d["statut"]) ?></td>
                    <td class="center"><?= htmlspecialchars($niveauCours ?? "Non défini") ?></td>
                    <td class="center"><?= htmlspecialchars($niveauTaux ?? "Non défini") ?></td>
                    <td class="right"><?= number_format($volumeValide, 2, ",", " ") ?> h</td>
                    <td class="right"><?= htmlspecialchars($chargeAffichee) ?></td>
                    <td class="right"><?= number_format($heuresPayables, 2, ",", " ") ?> h</td>

                    <td class="right">
                        <?php if($taux > 0): ?>
                            <?= number_format($taux, 0, ",", " ") ?> FCFA
                        <?php else: ?>
                            <span class="danger">Taux manquant</span>
                        <?php endif; ?>
                    </td>

                    <td class="right">
                        <?= number_format($montant, 0, ",", " ") ?> FCFA
                    </td>
                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="10" class="center">
                    Aucun état de paiement disponible pour cette période.
                </td>
            </tr>

        <?php endif; ?>

    </tbody>
</table>

<?php

$tableau = ob_get_clean();

$html .= '

<table class="stats">
    <tr>
        <td class="label">Enseignants concernés</td>
        <td class="label">Volume validé</td>
        <td class="label">Heures payables</td>
        <td class="label">Montant total</td>
    </tr>
    <tr>
        <td>'.count($totalEnseignants).'</td>
        <td>'.number_format($totalVolume, 2, ",", " ").' h</td>
        <td>'.number_format($totalHeuresPayables, 2, ",", " ").' h</td>
        <td>'.number_format($totalGeneral, 0, ",", " ").' FCFA</td>
    </tr>
</table>

';

$html .= $tableau;

$html .= '
    <div class="total">
        Montant global à payer : '.number_format($totalGeneral, 0, ",", " ").' FCFA
    </div>

    <div class="footer">
        Document généré automatiquement par la plateforme de gestion des heures UVCI.
    </div>

</body>
</html>
';

/*
|--------------------------------------------------------------------------
| GÉNÉRATION PDF
|--------------------------------------------------------------------------
*/

$options = new Options();
$options->set("isHtml5ParserEnabled", true);
$options->set("isRemoteEnabled", true);

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper("A4", "landscape");
$dompdf->render();

$filename = "etat_paiements_" . date("Ymd_His") . ".pdf";

$dompdf->stream($filename, ["Attachment" => true]);
exit;