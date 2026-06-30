<?php
define('PROJECT_ROOT', __DIR__.'/');
define('SCRIPT_NAME',rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/');



$D = $_REQUEST['D'] ?? null; //Data Array
$SD = $_REQUEST['SD'] ?? null; // security Data array
$D['R'] = $R = ($_REQUEST['R']??null); //Request Array
$D['C'] = $C = null; //Klassen Instanz Array
$D['SESSION'] = null; 

include_once(__dir__.'/system/core/Packagist.php');
include_once(__dir__.'/system/core/ComposerManager.php');

$C['Packagist'] = new Packagist();
$C['ComposerManager'] = new ComposerManager(__DIR__.'/system/core/composer.phar', 'data_c/composer_log.txt');

include('system/vendor/autoload.php');
#Load Core
require_once "system/vendor/fremeo/core/init.php";
#require_once "system/vendor/fremeo/core/start.php";
#end