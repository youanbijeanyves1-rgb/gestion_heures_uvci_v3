@echo off
setlocal

set DATE=%date:~6,4%-%date:~3,2%-%date:~0,2%
set HEURE=%time:~0,2%-%time:~3,2%-%time:~6,2%
set HEURE=%HEURE: =0%

set BACKUP_DIR=E:\SAUVEGARDE_UVCI
set DB_DIR=%BACKUP_DIR%\db
set PROJECT_DIR_BACKUP=%BACKUP_DIR%\projet
set LOG_DIR=%BACKUP_DIR%\logs

set DB_BACKUP=%DB_DIR%\gestion_heures_uvci_%DATE%_%HEURE%.sql
set PROJECT_BACKUP=%PROJECT_DIR_BACKUP%\gestion_heures_uvci_%DATE%_%HEURE%
set LOG_FILE=%LOG_DIR%\sauvegarde_%DATE%_%HEURE%.log

set MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe
set PROJECT_DIR=C:\xampp\htdocs\gestion_heures_uvci

if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"
if not exist "%DB_DIR%" mkdir "%DB_DIR%"
if not exist "%PROJECT_DIR_BACKUP%" mkdir "%PROJECT_DIR_BACKUP%"
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"
if not exist "%PROJECT_BACKUP%" mkdir "%PROJECT_BACKUP%"

echo ===================================== > "%LOG_FILE%"
echo SAUVEGARDE AUTOMATIQUE UVCI >> "%LOG_FILE%"
echo Date : %DATE% %HEURE% >> "%LOG_FILE%"
echo ===================================== >> "%LOG_FILE%"
echo. >> "%LOG_FILE%"

echo [1/2] Sauvegarde de la base de donnees... >> "%LOG_FILE%"

"%MYSQLDUMP%" --host=localhost --user=root --databases gestion_heures_uvci --ignore-table=gestion_heures_uvci.vue_volume_par_enseignant --result-file="%DB_BACKUP%" 2>> "%LOG_FILE%"

if exist "%DB_BACKUP%" (
    echo Base sauvegardee avec succes : %DB_BACKUP% >> "%LOG_FILE%"
) else (
    echo ERREUR : sauvegarde de la base echouee >> "%LOG_FILE%"
)

echo. >> "%LOG_FILE%"
echo [2/2] Sauvegarde des fichiers du projet... >> "%LOG_FILE%"

robocopy "%PROJECT_DIR%" "%PROJECT_BACKUP%" /E >> "%LOG_FILE%"

if exist "%PROJECT_BACKUP%" (
    echo Projet sauvegarde avec succes : %PROJECT_BACKUP% >> "%LOG_FILE%"
) else (
    echo ERREUR : sauvegarde du projet echouee >> "%LOG_FILE%"
)

echo. >> "%LOG_FILE%"
echo Sauvegarde terminee. >> "%LOG_FILE%"

endlocal