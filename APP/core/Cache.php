<?php
namespace APP\core;
class Cache {
	public function __constructor(){
	}
	public function set($key, $data, $seconds = '3600'){
		$content['data'] = $data;
		$content['end_time'] = time() + $seconds;
		$file = WWW."/tmp/".md5($key).".txt";
		if ( file_put_contents($file, serialize($content) )) {
			return true;
		}
		return false;
	}
	public function get($key){
		$file = WWW."/tmp/".md5($key).".txt";
		if (file_exists($file)){
			$content = unserialize(file_get_contents($file));
			if (time() <= $content['end_time']){
				return $content['data'];
			} else{
				unlink($file);
				return false;
			}
		}else{ // Если не существует файла
			return false;
		}
	}
	public function delete($key){
		$file = WWW."/tmp/".md5($key).".txt";
		if (file_exists($file)){
			unlink($file);   }
	}
}
?>
