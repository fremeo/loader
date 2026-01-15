<?php
define('PROJECT_ROOT', __DIR__.'/');

$D = $_REQUEST['D'] ?? null; //Data Array
$SD = $_REQUEST['S'] ?? null; // security Data array
$D['R'] = $R = $_REQUEST['R'] ?? null; //Request Array
$D['C'] = $C = null; //Klassen Instanz Array
$D['SESSION'] = null; 


#Load framework
require_once "system/papp/phpapp/init.php";
require_once "system/papp/phpapp/start.php";
#end

/*
include('system/autoload.php');




// 1. Module scannen und Metadaten sammeln
foreach (glob(PROJECT_ROOT . '/system' . '/*-/*', GLOB_ONLYDIR) as $moduleDir) {
	$path = realpath($moduleDir); // Pfad zum Projektordner
	$parts = explode(DIRECTORY_SEPARATOR, $path);
	$vendor = $parts[count($parts)-2]; // xx
	$package = $parts[count($parts)-1]; // yy
	$Id = "{$vendor}/{$package}";
	
	$D['MODUL']['D'][ $Id ] = [
		'Id'			=> $Id,
		'ModulDir'		=> $moduleDir,
		'VendorName'	=> $vendor,
		'PackageName'	=> $package,
		'CacheDir'		=> "data_c/{$vendor}_{$package}/",
		'DataDir'		=> "data/{$vendor}_{$package}/",
	];
}


# 2.0. Lade framework als erstes
$info_framework_Id  = 'papp/framework';
require_once "system/{$info_framework_Id}/init.php"; #Todo: Hotfix, zum starten von framework. 

// 2.1. Phase: alle init.php laden
foreach ($D['MODUL']['D'] as $moduleDir => $info) {
	if($info_framework_Id != $moduleDir) {
		$D['MY'] = $info;
		
		$init = $info['ModulDir'] . '/init.php';
		if (is_file($init)) {
			require_once $init;
		}
	}
}

// 3. Phase: alle start.php laden
foreach ($D['MODUL']['D'] as $moduleDir => $info) {
    $D['MY'] = $info;
	
	$start = $info['ModulDir'] . '/start.php';
    if (is_file($start)) {
        require_once $start;
    }
}
*/