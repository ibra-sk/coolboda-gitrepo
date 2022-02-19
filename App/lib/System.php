<?php

class PlatformSystem {
	public $db;
	public function __construct($db) {
		$this->db = $db;
	}
	
	
	function getPromoPicture(){
		$this->db->where("field_name", "promotion");
		$field = $this->db->getOne("settings");	
		if(empty($field)){
			return null;
		}else{
			return $field['field_value'];
		}
	}
	
	function getRideFees(){
		$this->db->where("field_name", "fair");
		$field = $this->db->getOne("settings");	
		if(empty($field)){
			return null;
		}else{
			return $field['field_value'];
		}
	}
	
}
?>