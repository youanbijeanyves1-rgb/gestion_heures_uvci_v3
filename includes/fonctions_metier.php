<?php

function niveauTauxDepuisNiveauCours($niveauCours){
    $niveauCours = strtoupper(trim($niveauCours));

    if(in_array($niveauCours, ["LICENCE", "L1", "L2", "L3"])){
        return "LICENCE";
    }

    if(in_array($niveauCours, ["MASTER", "M1", "M2"])){
        return "MASTER";
    }

    return null;
}