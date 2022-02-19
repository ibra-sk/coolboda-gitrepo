<?php

class ErrHandler {
	
	public function __construct() {
		
		
	}
	
	protected function render($view_file,$view_data){
		$this->view_file = $view_file;
		$this->view_data = $view_data;
		$set_language = ($_SERVER['HTTP_ACCEPT_LANGUAGE'] == 'en') ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : system($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		if(file_exists(APP . 'view/' . $view_file . '.phtml'))
		{
		  include APP . 'view/' . $view_file . '.phtml';
		}
	}
	
	public function PageNotFound() {
		//echo 'This is Home page';
		$this->render('404', []);
	}
	
	
}

?>