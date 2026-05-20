<?php

require_once "../auth/verifier_session.php";
require_once "../config/database.php";
require_once "../includes/fonctions_metier.php";
require_once "../vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

if($_SESSION["role"] !== "ENSEIGNANT"){
    header("Location: ../auth/login.php");
    exit;
}

$idUtilisateur = $_SESSION["id_utilisateur"];

$dateDebut = $_GET["date_debut"] ?? "";
$dateFin = $_GET["date_fin"] ?? "";

$stmtEns = $pdo->prepare("
    SELECT 
        e.id_enseignant,
        e.nom,
        e.prenoms,
        e.statut,
        e.id_grade,
        g.libelle_grade,
        g.charge_statutaire
    FROM enseignant e
    LEFT JOIN grade g ON g.id_grade = e.id_grade
    WHERE e.id_utilisateur = ?
    LIMIT 1
");
$stmtEns->execute([$idUtilisateur]);
$enseignant = $stmtEns->fetch(PDO::FETCH_ASSOC);

if(!$enseignant){
    die("Aucun enseignant associé à ce compte utilisateur.");
}

$idEnseignant = $enseignant["id_enseignant"];
$statut = $enseignant["statut"];
$chargeStatutaire = (float)($enseignant["charge_statutaire"] ?? 0);

$where = "ap.id_enseignant = ? AND ap.statut_validation = 'VALIDEE'";
$params = [$idEnseignant];

if($dateDebut !== ""){
    $where .= " AND DATE(ap.date_saisie) >= ?";
    $params[] = $dateDebut;
}

if($dateFin !== ""){
    $where .= " AND DATE(ap.date_saisie) <= ?";
    $params[] = $dateFin;
}

$stmtActivites = $pdo->prepare("
    SELECT 
        ap.date_saisie,
        ap.type_activite,
        ap.niveau_complexite,
        ap.nb_sequences,
        ap.volume_horaire_calcule,
        ap.observation,
        c.intitule_cours AS cours,
        cf.niveau AS niveau_cours
    FROM activite_pedagogique ap
    LEFT JOIN cours c ON c.id_cours = ap.id_cours
    LEFT JOIN cours_filiere cf ON cf.id_cours = c.id_cours
    WHERE $where
    ORDER BY ap.date_saisie ASC
");
$stmtActivites->execute($params);
$activites = $stmtActivites->fetchAll(PDO::FETCH_ASSOC);

$totalVolume = 0;

foreach($activites as $activite){
    $totalVolume += (float)$activite["volume_horaire_calcule"];
}

$heuresComplementaires = 0;

if($statut === "PERMANENT"){
    $heuresComplementaires = max(0, $totalVolume - $chargeStatutaire);
}

$stmtAnnee = $pdo->query("
    SELECT id_annee 
    FROM annee_academique 
    WHERE est_active = 1 
    LIMIT 1
");
$idAnnee = $stmtAnnee->fetchColumn();

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

$repartition = [];
$montantTotalComplementaire = 0;

foreach($activites as $activite){

    $niveauCours = $activite["niveau_cours"] ?? "Non défini";
    $niveauTaux = niveauTauxDepuisNiveauCours($niveauCours);
    $volumeLigne = (float)$activite["volume_horaire_calcule"];

    if(!isset($repartition[$niveauCours])){
        $repartition[$niveauCours] = [
            "niveau_cours" => $niveauCours,
            "niveau_taux" => $niveauTaux,
            "volume" => 0,
            "part_complementaire" => 0,
            "taux" => 0,
            "montant" => 0
        ];
    }

    $repartition[$niveauCours]["volume"] += $volumeLigne;
}

foreach($repartition as $niveauCours => &$ligne){

    $niveauTaux = $ligne["niveau_taux"];
    $taux = 0;

    if($niveauTaux !== null && $idAnnee !== false && $idAnnee !== null){
        $stmtTaux->execute([
            $statut,
            $enseignant["id_grade"],
            $niveauTaux,
            $idAnnee
        ]);

        $tauxTrouve = $stmtTaux->fetch(PDO::FETCH_ASSOC);
        $taux = (float)($tauxTrouve["montant"] ?? 0);
    }

    if($statut === "VACATAIRE"){
        $partComplementaire = $ligne["volume"];
    }else{
        if($totalVolume > 0){
            $partComplementaire = $heuresComplementaires * ($ligne["volume"] / $totalVolume);
        }else{
            $partComplementaire = 0;
        }
    }

    $montant = $partComplementaire * $taux;

    $ligne["part_complementaire"] = $partComplementaire;
    $ligne["taux"] = $taux;
    $ligne["montant"] = $montant;

    $montantTotalComplementaire += $montant;
}
unset($ligne);

$periode = "Toutes les périodes";

if($dateDebut !== "" && $dateFin !== ""){
    $periode = "Du " . date("d/m/Y", strtotime($dateDebut)) . " au " . date("d/m/Y", strtotime($dateFin));
}
elseif($dateDebut !== ""){
    $periode = "À partir du " . date("d/m/Y", strtotime($dateDebut));
}
elseif($dateFin !== ""){
    $periode = "Jusqu’au " . date("d/m/Y", strtotime($dateFin));
}

ob_start();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">

    <style>
        body{
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color:#111827;
        }

        .header{
            text-align:center;
            border-bottom:2px solid #1e3a8a;
            padding-bottom:12px;
            margin-bottom:18px;
        }

        h1{
            color:#1e3a8a;
            margin:0;
            font-size:21px;
            text-transform:uppercase;
        }

        h2{
            color:#1e3a8a;
            font-size:15px;
            margin-top:18px;
            margin-bottom:8px;
        }

        .subtitle{
            margin-top:6px;
            color:#475569;
        }

        .info{
            margin-bottom:14px;
            padding:10px;
            background:#f1f5f9;
            border:1px solid #cbd5e1;
        }

        .info p{
            margin:4px 0;
        }

        .notice{
            margin:12px 0;
            padding:10px;
            background:#fff7ed;
            border-left:4px solid #ea580c;
            color:#7c2d12;
            font-size:10.5px;
            line-height:1.5;
        }

        table{
            width:100%;
            border-collapse:collapse;
            margin-bottom:14px;
        }

        th{
            background:#1e1b4b;
            color:white;
            padding:7px;
            font-size:10px;
        }

        td{
            border:1px solid #cbd5e1;
            padding:6px;
            font-size:9.7px;
        }

        .stats td{
            text-align:center;
            font-weight:bold;
        }

        .stats .label{
            background:#e0f2fe;
            color:#1e3a8a;
        }

        .right{
            text-align:right;
        }

        .center{
            text-align:center;
        }

        .danger{
            color:#991b1b;
            font-weight:bold;
        }

        .total{
            background:#eff6ff;
            font-weight:bold;
        }

        .footer{
            margin-top:18px;
            font-size:9px;
            color:#64748b;
            text-align:center;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>Récapitulatif enseignant</h1>
        <div class="subtitle">Université Virtuelle de Côte d’Ivoire — Gestion des heures</div>
    </div>

    <div class="info">
        <p><strong>Enseignant :</strong> <?= htmlspecialchars($enseignant["nom"] . " " . $enseignant["prenoms"]) ?></p>
        <p><strong>Grade :</strong> <?= htmlspecialchars($enseignant["libelle_grade"] ?? "Non défini") ?></p>
        <p><strong>Statut :</strong> <?= htmlspecialchars($statut) ?></p>
        <p><strong>Période :</strong> <?= htmlspecialchars($periode) ?></p>
        <p><strong>Date d’édition :</strong> <?= date("d/m/Y à H:i") ?></p>
    </div>

    <table class="stats">
        <tr>
            <td class="label">Volume validé</td>
            <td class="label">Charge statutaire</td>
            <td class="label">Heures complémentaires / payables</td>
            <td class="label">Montant estimatif</td>
        </tr>

        <tr>
            <td><?= number_format($totalVolume, 2, ",", " ") ?> h</td>

            <td>
                <?php if($statut === "VACATAIRE"): ?>
                    Non concerné
                <?php else: ?>
                    <?= number_format($chargeStatutaire, 2, ",", " ") ?> h
                <?php endif; ?>
            </td>

            <td>
                <?php if($statut === "VACATAIRE"): ?>
                    <?= number_format($totalVolume, 2, ",", " ") ?> h
                <?php else: ?>
                    <?= number_format($heuresComplementaires, 2, ",", " ") ?> h
                <?php endif; ?>
            </td>

            <td>
                <?= number_format($montantTotalComplementaire, 0, ",", " ") ?> FCFA
            </td>
        </tr>
    </table>

    <div class="notice">
        <?php if($statut === "PERMANENT"): ?>
            Pour un enseignant permanent, les heures complémentaires sont calculées sur le volume global validé.
            Elles sont ensuite réparties proportionnellement entre les niveaux enseignés afin d’appliquer le taux horaire correspondant.
        <?php else: ?>
            Pour un enseignant vacataire, tout le volume validé est considéré comme payable.
        <?php endif; ?>
    </div>

    <h2>Détail de rémunération</h2>

    <table>
        <thead>
            <tr>
                <th>Niveau cours</th>
                <th>Niveau taux</th>
                <th>Volume validé</th>
                <th>Part payable</th>
                <th>Taux horaire</th>
                <th>Montant</th>
            </tr>
        </thead>

        <tbody>
            <?php if(empty($repartition)): ?>
                <tr>
                    <td colspan="6" class="center">Aucune donnée de rémunération disponible.</td>
                </tr>
            <?php else: ?>
                <?php foreach($repartition as $r): ?>
                    <tr>
                        <td class="center"><?= htmlspecialchars($r["niveau_cours"]) ?></td>
                        <td class="center"><?= htmlspecialchars($r["niveau_taux"] ?? "Non défini") ?></td>
                        <td class="right"><?= number_format($r["volume"], 2, ",", " ") ?> h</td>
                        <td class="right"><?= number_format($r["part_complementaire"], 2, ",", " ") ?> h</td>

                        <td class="right">
                            <?php if($r["taux"] > 0): ?>
                                <?= number_format($r["taux"], 0, ",", " ") ?> FCFA
                            <?php else: ?>
                                <span class="danger">Taux manquant</span>
                            <?php endif; ?>
                        </td>

                        <td class="right"><?= number_format($r["montant"], 0, ",", " ") ?> FCFA</td>
                    </tr>
                <?php endforeach; ?>

                <tr class="total">
                    <td colspan="3">TOTAL</td>
                    <td class="right">
                        <?php if($statut === "VACATAIRE"): ?>
                            <?= number_format($totalVolume, 2, ",", " ") ?> h
                        <?php else: ?>
                            <?= number_format($heuresComplementaires, 2, ",", " ") ?> h
                        <?php endif; ?>
                    </td>
                    <td></td>
                    <td class="right"><?= number_format($montantTotalComplementaire, 0, ",", " ") ?> FCFA</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h2>Détail des activités validées</h2>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Cours</th>
                <th>Niveau cours</th>
                <th>Type activité</th>
                <th>Niveau complexité</th>
                <th>Séquences</th>
                <th>Volume</th>
                <th>Observation</th>
            </tr>
        </thead>

        <tbody>
            <?php if(empty($activites)): ?>
                <tr>
                    <td colspan="8" class="center">
                        Aucune activité validée trouvée pour cette période.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach($activites as $activite): ?>
                    <tr>
                        <td><?= htmlspecialchars(date("d/m/Y", strtotime($activite["date_saisie"]))) ?></td>
                        <td><?= htmlspecialchars($activite["cours"] ?? "Non renseigné") ?></td>
                        <td class="center"><?= htmlspecialchars($activite["niveau_cours"] ?? "Non défini") ?></td>
                        <td><?= htmlspecialchars($activite["type_activite"]) ?></td>
                        <td class="center"><?= htmlspecialchars($activite["niveau_complexite"]) ?></td>
                        <td class="right"><?= htmlspecialchars($activite["nb_sequences"]) ?></td>
                        <td class="right"><?= number_format((float)$activite["volume_horaire_calcule"], 2, ",", " ") ?> h</td>
                        <td><?= htmlspecialchars($activite["observation"]) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        Document généré automatiquement par la plateforme de gestion des heures UVCI.
    </div>

</body>
</html>

<?php

$html = ob_get_clean();

$options = new Options();
$options->set("isHtml5ParserEnabled", true);
$options->set("isRemoteEnabled", true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper("A4", "landscape");
$dompdf->render();

$filename = "recapitulatif_" . $enseignant["nom"] . "_" . date("Ymd_His") . ".pdf";

$dompdf->stream($filename, ["Attachment" => true]);
exit;