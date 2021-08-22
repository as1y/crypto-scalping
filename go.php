<?php


require 'vendor/autoload.php';
require 'lib/functions.php'; //ОБЩИЕ ФУНКЦИИ
require 'lib/functions_app.php'; //ФУНКЦИИ ПРИЛОЖЕНИЯ

$url = "https://buran.team/long";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);

curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$result = curl_exec($ch);

curl_close($ch);

show($result);

echo "fu";


exit();


