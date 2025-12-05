<?php

$D = $_REQUEST['D'] ?? null; //Data Array
$SD = $_REQUEST['R'] ?? null; // security Data array
$D['R'] = $R = $_REQUEST['R'] ?? null; //Request Array
$D['C'] = $C = null; //Klassen Instanz Array
$D['SESSION'] = null; 


$a = include('system/autoload.php');

// Alle init.php Dateien in system/*/*/init.php laden
$vendorDir = __DIR__ . '/system';
foreach (glob($vendorDir . '/*/*/init.php') as $initFile) {
    require_once $initFile;
}

$vendorDir = __DIR__ . '/system';
foreach (glob($vendorDir . '/*/*/start.php') as $initFile) {
    require_once $initFile;
}