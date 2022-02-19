<?php

class Route {
	
	private $_uri = array();
	private $_method = array();
	private $_action = array();
	
	public function add($uri, $method, $action = null){
		$this->_uri[] = '/' . trim($uri, '/');
		$this->_method[] = $method;
		$this->_action[] = ($action != null) ? $action : 'index';
	}
	
	public function submit(){
		$bool = false;
		$uriGetParam = isset($_GET['uri']) ? '/' . $_GET['uri'] : '/';
		foreach($this->_uri as $key => $value){
			if(preg_match("#^$value$#", $uriGetParam)){
				$useMethod = $this->_method[$key];
				$useAction = $this->_action[$key];
				//echo $useMethod . ' - ' . $useAction;
				$bool = true;
				$page = new $useMethod();
				$page->$useAction();
			}			
		}
		//Handle Unknown or Bad URL
		if(!$bool){
			$page = new ErrHandler();
			$page->PageNotFound();
		}
	}
	
}



?>