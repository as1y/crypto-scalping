<?php

$_SERVER["DOCUMENT_ROOT"] = '/home/bitrix/ext_www/coupons.gallery';
set_time_limit(0);


require $_SERVER["DOCUMENT_ROOT"].'/vendor/autoload.php';
require $_SERVER["DOCUMENT_ROOT"].'/lib/functions.php';
require $_SERVER["DOCUMENT_ROOT"].'/lib/functions_app.php';
define('CONFIG', require $_SERVER["DOCUMENT_ROOT"].'/config/main.php');


use RedBeanPHP\R;

R::setup(CONFIG['db']['dsn'],CONFIG['db']['user'],CONFIG['db']['pass']);

// ОБНОВЛЕНИЕ КОМПАНИЙ


$url = "https://coupons.gallery/update/";
$type = "GET";

$PARAMS = [
    'action' => "updatecompany",
];


fCURL($url, [$type => $PARAMS]);


//file_put_contents($_SERVER["DOCUMENT_ROOT"] .'/logs/log.log', var_export($PARAMS, true), FILE_APPEND);




exit();





?>
