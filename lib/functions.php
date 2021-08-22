<?php
// ВЫКЛЮЧАТЬ ОШИБКИ
$er = ERRORS;
if($er == 1){
	//ВЫВОД ОШИБОК
	ini_set('display_errors',1);
	error_reporting(E_ALL);
	//ВЫВОД ОШИБОК
} else{
	error_reporting(E_ERROR);
}
function not_found() { // ЕСЛИ НЕ НАЙДЕНА СТРАНЦИА


	if (isset($_SESSION['ulogin'])){
		echo "<script>document.location.href='/panel';</script>\n";
		exit();
	}
	else{
		http_response_code(404);
		include ("404.php");
		exit();
	}
} // ЕСЛИ НЕ НАЙДЕНА СТРАНЦИА


// Функция вывода сообщений в обработчике форм
function message( $text ) {
	exit('{ "message" : "'.$text.'"}');
}
// Функция вывода сообщений в обработчике форм
// Функция редирект при возвращении из формы через ajax
function go( $url ) {
	exit('{ "go" : "'.$url.'"}');
}
// Функция редирект при возвращении из формы через ajax



function redir($http = FALSE){
	if($http){
		$redirect = $http;
	}else{
		$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
	}
	header("Location: $redirect");
	exit();
}
// Екзит из интерфейса




function dumpf($PARAM){
	file_put_contents($_SERVER["DOCUMENT_ROOT"] .'/log.log', var_export($PARAM, true), FILE_APPEND);
}


function hurl($url){
	$url = str_replace("http://", "", $url); // Убираем http
	$url = str_replace("https://", "", $url); // Убираем https
	$url = str_replace("www.", "", $url); // Убираем https
	return $url;
}


// Генерируем рандом символы 30 шт
function random_str( $num = 30 ) {
	return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $num);
}
// Генерируем рандом символы 30 шт
function show($par){
	echo "<pre>";
	print_r($par);
	echo "</pre>";
}
// ФУНКЦИЯ ЗАГРУЗКИ КЛАССОВ
spl_autoload_register(function ($class) {
		$path = str_replace( '\\', '/', $class.'.php' );
		if (file_exists($path)){
			require $path;
		}
	});
// ФУНКЦИЯ ЗАГРУЗКИ КЛАССОВ
function h($str){
	return htmlspecialchars($str, ENT_QUOTES);
}

/**
 * Выводит ошибку в специальном формате
 * @param string $error_message
 */
function e(string $error_message): void
{
    if(ERRORS) {
        ob_clean();
        ob_start();
        echo(sprintf('<h1 style="color:red">%s</h1>', $error_message));
        echo('<pre>' . print_r(debug_backtrace(), true) . '</pre>');
        ob_end_flush();
        exit();
    }
}



function monthtoday(){
    $datetoday = date ("Y-m-d");
    $d = date_parse_from_format("Y-m-d", $datetoday);

    if ($d['month'] == 1) $d['month'] = "январь";
    if ($d['month'] == 2) $d['month'] = "февраль";
    if ($d['month'] == 3) $d['month'] = "март";
    if ($d['month'] == 4) $d['month'] = "апрель";
    if ($d['month'] == 5) $d['month'] = "май";
    if ($d['month'] == 6) $d['month'] = "июнь";
    if ($d['month'] == 7) $d['month'] = "июль";
    if ($d['month'] == 8) $d['month'] = "август";
    if ($d['month'] == 9) $d['month'] = "сентябрь";
    if ($d['month'] == 10) $d['month'] = "октябрь";
    if ($d['month'] == 11) $d['month'] = "ноябрь";
    if ($d['month'] == 12) $d['month'] = "декабрь";

    return $d['month'];
}

function obrezanie ($text, $symbols){

    $result = mb_strimwidth($text, 0, $symbols, "...");

    return $result;

}


function getExtension($filename) {
    $path_info = pathinfo($filename);
    return $path_info['extension'];
}



function getconversion ($value1, $value2){
    if ($value2 == 0) return 0;
    $result = $value1/$value2*100;
    $result = round($result);
    return $result;
}


function SystemUserId(){
    return  md5(uniqid().$_SERVER['REMOTE_ADDR'].$_SERVER['UNIQUE_ID']);
}



function delDir($dir) {
    $files = array_diff(scandir($dir), ['.','..']);
    foreach ($files as $file) {
        (is_dir($dir.'/'.$file)) ? delDir($dir.'/'.$file) : unlink($dir.'/'.$file);
    }
    return rmdir($dir);
}

function fCURL($url, $PARAMS = [], $headers = []){

    $ch = curl_init();

    if (!empty($PARAMS['GET'])){
        $url = $url."?".http_build_query($PARAMS['GET']);
    }

    if ($headers != []){
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    }else{
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json-patch+json'));
    }



    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);


    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);


    //  curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
//    curl_setopt($ch, CURLOPT_COOKIEFILE, $_SERVER['DOCUMENT_ROOT'].'/cookie.txt');
//    curl_setopt($ch, CURLOPT_COOKIEJAR, $_SERVER['DOCUMENT_ROOT'].'/cookie.txt');


    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);


    if (!empty($PARAMS['POST'])){
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $PARAMS['POST']);
    }

    if (!empty($PARAMS['PATCH'])){
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $PARAMS['PATCH']);
    }



    if (!empty($PARAMS['DELETE'])){
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $PARAMS['DELETE']);
    }





    $result = curl_exec($ch);

    curl_close($ch);

    //  $resultJson = $result;
    $resultJson = json_decode($result, TRUE);


    return $resultJson;



}


function get_signed_params_bybit($public_key, $secret_key, $params) {
    $params = array_merge(['api_key' => $public_key], $params);
    ksort($params);
    //decode return value of http_build_query to make sure signing by plain parameter string
    $signature = hash_hmac('sha256', urldecode(http_build_query($params)), $secret_key);
    return http_build_query($params) . "&sign=$signature";
}


// Форматирование цен.
function format_price($value)
{
    return number_format($value, 2, ',', ' ');
}


/**
 * Склоняем словоформу
 * @ author runcore
 */
function morph($n, $f1, $f2, $f5) {
    $n = abs(intval($n)) % 100;
    if ($n>10 && $n<20) return $f5;
    $n = $n % 10;
    if ($n>1 && $n<5) return $f2;
    if ($n==1) return $f1;
    return $f5;
}

?>