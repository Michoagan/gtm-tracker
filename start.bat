@echo off
title GTM Tracker - Serveur Local (PostgreSQL Aiven)
cd /d "%~dp0"
echo ========================================================
echo       GTM Tracker - Demarrage du Serveur Web Local
echo ========================================================
echo.
echo Base de donnees : PostgreSQL en ligne (Aiven Cloud)
echo Identifiants de connexion :
echo   Admin : admin@gtmtracker.com / Admin@2024!
echo.
echo Ouverture automatique de votre navigateur sur http://localhost:8000 ...
start "" cmd /c "timeout /t 1 >nul & start http://localhost:8000"
echo.
echo Serveur en cours d'execution.
echo Laissez cette fenetre ouverte pendant l'utilisation.
echo Pour arreter le serveur : faites Ctrl + C
echo.
php -S 127.0.0.1:8000 router.php
pause