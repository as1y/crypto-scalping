<?php
namespace APP\controllers;
use APP\core\PHPM;
use APP\models\User;


class UserController extends AppController
{
	public $layaout = 'USER'; //Перераспределяем массив layaout
    public $BreadcrumbsControllerLabel = APPNAME;
    public $BreadcrumbsControllerUrl = "/";

	public function registerAction()
	{

		if( isset($_SESSION['ulogin']['id']) ) redir('/panel/');

        $META = [
            'title' => 'Регистрация пользователя',
            'description' => 'Регистрация пользователя',
            'keywords' => 'Регистрация пользователя',
        ];

        $BREADCRUMBS['HOME'] = ['Label' => $this->BreadcrumbsControllerLabel, 'Url' => $this->BreadcrumbsControllerUrl];
        $BREADCRUMBS['DATA'][] = ['Label' => "Регистрация пользователя"];


        $ASSETS[] = ["js" => "/global_assets/js/plugins/forms/styling/uniform.min.js"];
        $ASSETS[] = ["js" => "/global_assets/js/plugins/forms/styling/switchery.min.js"];
        $ASSETS[] = ["js" => "/global_assets/js/plugins/forms/styling/switch.min.js"];
        $ASSETS[] = ["js" => "/global_assets/js/plugins/forms/validation/validate.min.js"];
        $ASSETS[] = ["js" => "/global_assets/js/plugins/forms/styling/uniform.min.js"];
        $ASSETS[] = ["js" => "/assets/js/login_validation.js"];



        $ASSETS[] = ["js" => "/global_assets/js/demo_pages/form_checkboxes_radios.js"];





         \APP\core\base\View::setAssets($ASSETS);
        \APP\core\base\View::setMeta($META);
        \APP\core\base\View::setBreadcrumbs($BREADCRUMBS);

        $user = new User; //Вызываем Моудль

        if ($_POST){

              $validate =  $user->validateregistration($_POST);


            if(!$validate || !$user->checkUniq(CONFIG['USERTABLE'], $_POST['email'] ))
            {
                $user->getErrorsVali(); //Записываем ошибки в сессию
                redir("/user/register/");
            }


            $passorig = $_POST['password'];
            $_POST['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT); // Хеш пароля
            $_SESSION['confirm'] = $_POST; //Базовые параметры
            //Доп. Параметры в сессию
            $_SESSION['confirm']['code'] = $code = random_str(20); //Код подтверждения
            if(isset($_COOKIE['ref'])) $_SESSION['confirm']['ref'] = $_COOKIE['ref']; //ID реферала
            //Доп. Параметры в сессию
            //Доп. Параметры в сессию



            // Отправка на почту кода подтверждения
          //  PHPM::sendMail("code",'Подтверждение регистрации на '.APPNAME.'',null,$_POST['email']);



            if($user->saveuser(CONFIG['USERTABLE']))
            {

                $_POST['email'] = $_SESSION['confirm']['email'];
                $_POST['password'] = $_SESSION['confirm']['password2'];

                $_SESSION['confirm'] = [];


                $user->login(CONFIG['USERTABLE']);


                if ($_SESSION['ulogin']['role'] == "R") redir('/panel/operator/');
                if ($_SESSION['ulogin']['role'] == "O") redir('/operator/');


            }
            else
            {
                $_SESSION['errors'] = "Ошибка базы данных. Попробуйте позже.";
                redir('/user/confirmRegister/');
            }





//   redir('/user/confirmRegister/');







        }





	}



	public function indexAction()
	{


	    //Если юзер залогинен, то редиректим его на панель
		if( isset($_SESSION['ulogin']['id']) ) redir('/panel/');

		if($_POST){

			$user = new User;

			if($user->login(CONFIG['USERTABLE'])){
				//АВТОРИЗАЦИЯ

                redir('/panel/');


				//АВТОРИЗАЦИЯ
			}
			else
			{
				$_SESSION['errors'] = "Логин/Пароль введены не верно";
                redir('/user/');

			}
		}


        $META = [
            'title' => 'Логин',
            'description' => 'Логин',
            'keywords' => 'Логин',
        ];
        \APP\core\base\View::setMeta($META);



        $BREADCRUMBS['HOME'] = ['Label' => $this->BreadcrumbsControllerLabel, 'Url' => $this->BreadcrumbsControllerUrl];
        $BREADCRUMBS['DATA'][] = ['Label' => "Логин"];
        \APP\core\base\View::setBreadcrumbs($BREADCRUMBS);



        $ASSETS[] = ["js" => "/global_assets/js/plugins/forms/validation/validate.min.js"];
        $ASSETS[] = ["js" => "/global_assets/js/plugins/forms/styling/uniform.min.js"];
        $ASSETS[] = ["js" => "/assets/js/login_validation.js"];

        \APP\core\base\View::setAssets($ASSETS);






	}


	public function logoutAction()
	{
		if(isset($_SESSION['ulogin'])){
			$_SESSION['ulogin'] = array();
			redir('/user/login');
		}
	}



    public function confirmEmailAction(){


        $META = [
            'title' => 'Подтверждение E-mail',
            'description' => 'Подтверждение E-mail',
            'keywords' => 'Подтверждение E-mail',
        ];

        $BREADCRUMBS['HOME'] = ['Label' => $this->BreadcrumbsControllerLabel, 'Url' => $this->BreadcrumbsControllerUrl];
        $BREADCRUMBS['DATA'][] = ['Label' => "Подтверждение E-mail"];

        $user = new User;

        if (!empty($_GET['code']) && !empty($_GET['email']) ){


           $confirm = $user->confirmemail($_GET['email'], $_GET['code']);

            if ($confirm == false) redir("/");

            $_SESSION['success'] = "E-mail успешно подтвержден";
            if ($_SESSION['ulogin']){
                $_SESSION['ulogin']['code'] = NULL;
                if ($_SESSION['ulogin']['role'] == "R") redir('/master/');
                if ($_SESSION['ulogin']['role'] == "O") redir('/operator/');
            }

            redir("/user/login");





        } else{

            redir("/");


        }





	    return true;

    }

	public function recoveryAction()
	{

        $META = [
            'title' => 'Восстановление пароля',
            'description' => 'Восстановление пароля',
            'keywords' => 'Восстановление пароля',
        ];

        $BREADCRUMBS['HOME'] = ['Label' => $this->BreadcrumbsControllerLabel, 'Url' => $this->BreadcrumbsControllerUrl];
        $BREADCRUMBS['DATA'][] = ['Label' => "Восстановление пароля"];

        \APP\core\base\View::setMeta($META);
        \APP\core\base\View::setBreadcrumbs($BREADCRUMBS);

		if(!empty($_POST)){
			$user = new User;
			if(filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)){
				if($user->checkemail(CONFIG['USERTABLE'], $_POST['email'])){
					$_SESSION['confirm']['recode'] = random_str(20);
					$_SESSION['confirm']['remail'] = $_POST['email'];
                    PHPM::sendMail("resetpassword",' Сборс пароля в '.CONFIG['NAME'],null, $_POST['reminder-email']);

                    $_SESSION['success'] = "Код для сброса пароля отправлен на почту. ";

					redir('/user/confirmRecovery/');
				}
				else
				{
					$_SESSION['errors'] = "Пользователь с таким E-mail не существует";
                    redir('/user/recovery/');
				}
			}
			else
			{
				$_SESSION['errors'] = "E-mail указан не корректно";
                redir('/user/recovery/');

			}
		}



	}
	// Страница ввода кода при сбросе пароля
	public function confirmRecoveryAction()
	{

        $META = [
            'title' => 'Восстановление пароля',
            'description' => 'Восстановление пароля',
            'keywords' => 'Восстановление пароля',
        ];

        $BREADCRUMBS['HOME'] = ['Label' => $this->BreadcrumbsControllerLabel, 'Url' => $this->BreadcrumbsControllerUrl];
        $BREADCRUMBS['DATA'][] = ['Label' => "Восстановление пароля"];

        \APP\core\base\View::setMeta($META);
        \APP\core\base\View::setBreadcrumbs($BREADCRUMBS);



		if( !isset($_SESSION['confirm']['recode']) )
		{
            $_SESSION['errors'] = "Код подтверждения устарел. Необходимо выполнить процедуру повторно.";
			redir('/user/recovery/');
		}


		if(!empty($_POST['code']))
		{
			if($_POST['code'] == $_SESSION['confirm']['recode'])
			{
				$user    = new User;
				$newpass = $user->newpass('user');
				if(!empty($newpass))
				{
					$_SESSION['confirm']['newpass'] = $newpass;
                    PHPM::sendMail("newpass",  'Ваш новый пароль в '.CONFIG['NAME'],null,$_SESSION['confirm']['remail']);
					$_SESSION = array();

                    $_SESSION['success'] = "Новый пароль <b>".$newpass."</b> отправлен на почту!";
					redir('/user/');
				}
				else
				{
					$_SESSION['errors'] = "Ошибка базы данных. Попробуйте позже.";
                    redir('/user/confirmRecovery');
				}
			}
			else
			{
				$_SESSION['errors'] = "Код не совпдает с кодом в E-mail";
                redir('/user/confirmRecovery');

			}
		}




	}
	// Страница ввода кода при сбросе пароля
	public function refAction()
	{
		if(!empty($_GET['partner']))
		{
			if( !preg_match('/^[0-9]{1,5}$/', $_GET['partner']) )  exit('Неправильная реф.ссылка');
			setcookie('ref', $_GET['partner'], strtotime('+15 days'), '/');
			redir("/");
    //			header('Location: /');
		}
		else
		{
			exit ('Неправильная реф.ссылка');
		}
	}




	public function formAction()
	{
		if(!empty($_POST)){
			$_SESSION['form_data']['signup-email'] = $_POST['email'];
			$_SESSION['form_data']['signup-telephone'] = $_POST['telephone'];
			$_SESSION['form_data']['signup-username'] = $_POST['username'];
			redir('/user/register/');
		}
		exit();
	}






	public function operatorAction(){


        if (empty($_GET['name'])) redir("/");

             $user = new User;
             $mass = explode("-", $_GET['name']);
               $userinfo =  $user->loaduser(CONFIG['USERTABLE'], $mass[1]);
            if (!$userinfo) redir("/");
            if (translit_sef($userinfo['username']) != $mass[0]) redir("/");


            $roletext = ($userinfo['role'] == "O") ? "Оператор" : "Рекламодатель";

        $META = [
            'title' => $roletext.' '.$userinfo['username'],
            'description' => $roletext.' '.$userinfo['aboutme'],
            'keywords' => $roletext.' '.$userinfo['username'],
        ];

        $BREADCRUMBS['HOME'] = ['Label' => $this->BreadcrumbsControllerLabel, 'Url' => $this->BreadcrumbsControllerUrl];
        $BREADCRUMBS['DATA'][] = ['Label' => "Публичный юзер"];

        \APP\core\base\View::setMeta($META);
        \APP\core\base\View::setBreadcrumbs($BREADCRUMBS);




        $this->set(compact('userinfo'));



    }









}
?>