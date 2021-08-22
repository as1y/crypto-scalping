<?php
namespace APP\core\base;
use APP\models\Panel;

abstract class Controller {
	public $route = [];
	public $view;
	public $layaout;
	public $data=[];
	public function __construct($route){
		$this->route = $route;
		$this->view = $route['action'];


		// Роутинг ВНЕ СЕССИЙ
		if ($route['controller'] == "Pay" ) return true;
        // Роутинг ВНЕ СЕССИЙ




	}



	public function getView(){
		$vObj = new View($this->route, $this->layaout, $this->view );
		$vObj->render($this->data);
	}


	public function set($data){
		$this->data = $data;
	}



	function isAjax ()
	{
		if (
			isset($_SERVER['HTTP_X_REQUESTED_WITH'])
			&& $_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest")
			return true;
		return false;
	}
}



?>