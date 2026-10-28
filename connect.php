<?php
// Chemin vers le fichier de logs
$logFile = 'log.txt';

// Obtenir les données de connexion
$date       = date('Y-m-d');
$time       = date('H:i:s');
$ip         = $_SERVER['REMOTE_ADDR'];
$userAgent  = $_SERVER['HTTP_USER_AGENT'];

// Préparer la ligne de log
$logEntry = "$date $time - IP: $ip - Navigateur: $userAgent" . PHP_EOL;

// Écrire dans le fichier (le créer s’il n’existe pas)
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Redirection vers Google
header("Location: https://www.google.com");
exit;
?>
