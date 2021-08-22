<?php
namespace APP\core\base;
use APP\models\Panel;
use RedBeanPHP\R;

abstract class Model
{
	public $errors = [];
    static $COMPANIES = [];
    static $CATEGORY = [];
    static $BRANDS = [];
    static $USER = [];


	public function __construct()
	{
        if(!R::testConnection()){

            R::setup(CONFIG['db']['dsn'],CONFIG['db']['user'],CONFIG['db']['pass']);
//              R::fancyDebug( TRUE );
            //  R::freeze(TRUE);

//            if (!empty($_SESSION['ulogin']['id'])){
//                self::$USER = $this->loaduser(CONFIG['USERTABLE'], $_SESSION['ulogin']['id']);
//            }

            if (!empty($_SESSION['ulogin']['id'])){
                self::$USER = $this->loaduser(CONFIG['USERTABLE'], $_SESSION['ulogin']['id']);
            }


            self::$CATEGORY = R::findAll('category', 'ORDER by `countshop` DESC');

            self::$COMPANIES = R::findAll('companies', 'ORDER by `id` ASC');

            self::$BRANDS = R::findAll('brands', 'ORDER by `countview` DESC');



        }

	}
	// ЗАГРУЗКА ДАННЫХ ИЗ ФОРМЫ



	public function load($data)
	{
		foreach($this->ATR as $name => $val)
		{
			if(isset($data[$name])) $this->ATR[$name] = $data[$name];
		}
	}
	// ЗАГРУЗКА ДАННЫХ ИЗ ФОРМЫ
	// ПРОВЕРКА ДАННЫХ



	public function validate($data)
	{
		$v = new \Valitron\Validator($data);
		$v->rules($this->rules);
		if($v->validate())
		{
			return true;
		}
		$this->errors = $v->errors();
		return false;
	}
	// ПРОВЕРКА ДАННЫХ
	// Красивый вывод ошибок валидации


    public function validatenew($data, $rules, $labels)
    {

        foreach ($data as $key=>$val){
            $data[$key] = trim($val);
            $data[$key] = strip_tags($val);
            $data[$key] = htmlspecialchars($val);
        }



        $v = new \Valitron\Validator($data);
        $v->rules($rules);
        $v->labels($labels);

        if($v->validate())
        {
            return true;
        }
        $this->errors = $v->errors();
        return false;
    }



    public static function getBal(){

        return self::$USER['bal'];
    }


    public static function getTopProduct(){

        return R::findAll('product', 'ORDER by `used` DESC LIMIT 5');
    }




	public function getErrorsVali()
	{
		$errors = "<ul>";
		foreach($this->errors as $error) {
			foreach($error as $item)
			{
				$errors .= "<li>".$item."</li>";
			}
		}
		$errors .= "</ul>";
		$_SESSION['errors'] = $errors;
	}
	// Красивый вывод ошибок валидации


    public function loaduser($table,$iduser) {
        return R::load($table, $iduser);
    }






    public static function getCategory($limit = ""){

        if (!$limit) return self::$CATEGORY;

	    $categorylimit = self::$CATEGORY;

            $i = 0;
	        foreach ($categorylimit as $key=>$value ){
                $i++;
                if ($i > $limit) unset($categorylimit[$key]);
            }

        return $categorylimit;
    }


    public static function getShops($PARAMS){

	    $companies = self::$COMPANIES;

        // Проверка на наличие купонов в магазине
        foreach ($companies as $k=>$v) {
            if ($v->countOwn("coupons") == 0) unset($companies[$k]);
        }


        if (!empty($PARAMS['banner'])) {
            foreach ($companies as $k=>$v){
            // Если у Магазина нет баннеров
            if ($v['addbanner'] == 0)  unset($companies[$k]);
            }
        }


        if (!empty($PARAMS['custom'])){
            foreach ($companies as $k=>$v){
                    if (!in_array($v['id'], $PARAMS['custom'] )) {
                        unset($companies[$k]);
                    }
            }
        }

        if (!empty($PARAMS['sort']) && $PARAMS['sort'] == "random"){
           shuffle($companies);
        }





        if (empty($PARAMS['limit'])) return $companies;


        $i = 0;
        foreach ($companies as $key=>$company ){
            $i++;
            if ($i > $PARAMS['limit']) unset($companies[$key]);
        }


        return $companies;
    }



    public static function SaveUsr (){


	    if (!empty($_GET['cmpid']) && $_GET['cmpid']){


            $Panel =  new Panel();
            $_SESSION['SystemUserId'] = SystemUserId();
            $Panel->AddUtminBD($_GET);




        }






    }



    public static function countShops(){
	    return R::count("companies");;
    }











    public function myresize($url, $weight, $height ){



        $src = imagecreatefromjpeg($url);
        $w_src = imagesx($src);
        $h_src = imagesy($src);


        $needtype = getsizetypeimage($weight, $height); // Какой типа картинки нам нужен
        $sizetype = getsizetypeimage($w_src, $h_src); // Какую по факту дали


        $image_p = imagecreatetruecolor($weight, $height); // Создаем изображение

        if ($needtype == "kvadrat"){
            $w = 600;
            $dest = imagecreatetruecolor($w,$w);



                if ($sizetype == "kvadrat")
                    imagecopyresized($dest, $src, 0, 0, 0, 0, $w, $w, $w_src, $w_src);

                if ($sizetype == "horizont")
                    imagecopyresized($dest, $src, 0, 0,
                        round((max($w_src,$h_src)-min($w_src,$h_src))/2),
                        0, $w, $w, min($w_src,$h_src), min($w_src,$h_src));

                if ($sizetype == "vertikal"){
                    imagecopyresized($dest, $src, 0, 0, 0, 0, $w, $w,
                        min($w_src,$h_src), min($w_src,$h_src));
                }


            imagecopyresampled($image_p, $dest, 0, 0, 0, 0, $weight, $height, $w, $w);



        }


        if ($needtype == "horizont"){
            imagecopyresampled($image_p, $src, 0, 0, 0, 0, $weight, $height, $w_src, $h_src);
        }

        if ($needtype == "vertikal"){
            imagecopyresampled($image_p, $src, 0, 0, 0, 0, $weight, $height, $w_src, $h_src);
        }


        imagejpeg ($image_p ,$url, 100); // Сохраняем

        return true;


    }




    public static function LoadCustomCompany($id) {
        return R::load("companies", $id);
    }


    public static function GenerateBanner($PARAMS){
        $result = false;


        // forma = форма
        // type = случный или по CTR

        // horizont
        // vertikal
        // kvadrat




        $BANNERS = $PARAMS['company']->withCondition(' `forma` = "'.$PARAMS['forma'].'" ORDER BY `views` DESC')->ownBannersList;






        // Фильтр на размеры. Баннер не должен быть в 2 раза больше требуемых размеров
        foreach ($BANNERS as $key=>$val){
          $validate = validatebanner(['w' => $PARAMS['wn'], 'h' => $PARAMS['hn']], ['w' => $val['size_width'], 'h' =>$val['size_height'] ]);
          if ($validate == false) unset ($BANNERS[$key]);
        }



        // Если баннера не найдено, то выдаем заглушку
        if (count($BANNERS) == 0){
            	    $result['img'] = "https://cdn.admitad.com/bs/2020/07/06/e907d48ee3c66da1271af4d4d817b6a2.jpg";
            	    $result['link'] = "http://ad.admitad.com/g/jt7fikzsrg88fe65ef3aa67c98cd55/";
            return $result;
        }


        // Перемешиваем массив
        shuffle($BANNERS);

        $Banner = array_shift($BANNERS);

        $Banner->views = $Banner->views+1;
        R::store($Banner);

        $result['img'] = $Banner['pictureurl'];
        $result['link'] = $Banner['direct_link'];

        return $result;

    }




    public static function LoadCompany($id) {
        return R::load("companies", $id);
    }


    public static function addnewBD($table, $DATA) {

        $tbl = R::dispense($table);
        //ФОРМИРУЕМ МАССИВ ДАННЫХ ДЛЯ РЕГИСТРАЦИИ
        foreach($DATA as $name=>$value)
        {
            $tbl->$name = $value;
        }
        return R::store($tbl);

        // По поводу валидации

    }

    public function filevalidation($FILE, $PARAMS = []){

        if ($FILE['size'] > 3000000) {
            $this->errors[] = ['Файл' => "Размер не должен превышать 3МБ" ];
            return false;
        }

        if ($FILE['size'] < 10) {
            $this->errors[] = ['Файл' => "Файл очень маленький" ];
            return false;
        }

        if ($FILE['type'] != $PARAMS['type']) {
            $this->errors[] = ['Файл' => "Не корректный формат1" ];
            return false;
        }

//        show($PARAMS['ext']);

        // Проверка на допустимый формат
        $format = false;
        foreach ($PARAMS['ext'] as $item) {
            if(preg_match("/$item\$/i", $FILE['name']))  $format = true;
        }
        if ($format == false){
            $this->errors[] = ['Файл' => "Не корректный формат" ];
            return false;
        }
        // Проверка на допустимый формат









	    return true;



    }










}
?>