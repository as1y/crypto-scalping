<?php
namespace APP\core;
class Router {
	protected $routes = [];
	protected $route = [];


	public function __construct(){
	}
	public function add($regexp, $route = []){
		$this->routes[$regexp] = $route;
	}
	public function getroute(){
		return $this->route;
	}
	public function getroutes(){
		return $this->routes;
	}
	public function match(){
		$url = trim($_SERVER['REQUEST_URI'], '/');
		$url = $this->removequerystring($url);
		foreach ($this->routes as $pattern => $route){
			if (preg_match("#$pattern#i", $url, $matches)){
				foreach ($matches as $k => $v){
					if (is_string($k)) $route[$k] = $v;
				}
				if(!isset($route['action'])) $route['action'] = "index";
				$route['controller'] = $this->upperCamel($route['controller']);
				$this->route = $route;
				return true;
			}
		}
		return false;
	}
	public function run(){
		if ( $this->match()) {

			$controller = "APP\controllers\\".$this->route['controller']."Controller";


			if (class_exists($controller)) // ВЫЗОВ КОНТРОЛЛЕРА
				{
				$cObj = new $controller($this->route);
				$action =  $this->lowerCamel($this->route['action'])."Action";
				if (method_exists($cObj, $action)){
					$cObj->$action();
					$cObj->getView();
				} else{
					//ВЫВОД ОШИБОК
					if(ERRORS == 1){
						echo " Метод ".$controller.":: ".$action." не найден";
					} else{
						redir("/panel");
					}
					//ВЫВОД ОШИБОК
				}
			} // ВЫЗОВ КОНТРОЛЛЕРА
			else{
				//ВЫВОД ОШИБОК
				if(ERRORS == 1){
					echo " Контроллер ".$controller." не найден";
				} else{
					redir("/panel");
				}
				//ВЫВОД ОШИБОК
			}
		} else{
			not_found();
		}
	}
	// Перенаправляет URL по корректному маршруту
	protected static function upperCamel($name){
		return str_replace(" ", "", ucwords(str_replace("-", " ", $name)));
	}
	// Перенаправляет URL по корректному маршруту
	protected static function lowerCamel($name){
		return lcfirst(str_replace(" ", "", ucwords(str_replace("-", " ", $name))));
	}
	protected static function removequerystring($url){
		if(isset($url)){
			$params = explode('?', $url,2);
			if (false === strpos($params['0'], '=')){
				return rtrim($params['0'],'/');
			}else{
				return '';
			}
		}
		return($url);
	}
}
?>