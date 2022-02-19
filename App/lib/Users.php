<?php

class Users {
	public $db;
	public function __construct($db) {
		$this->db = $db;
	}
	
	protected function generateUserID() {
		$bool = false;
		$length = 20;
		$user_id;
		do {
			$user_id = substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
			$this->db->where("user_id", $user_id);
			$avalable = $this->db->getOne("users");
			if(empty($avalable)){
				$bool = true;
			}
		} while (!($bool));
		return $user_id;
	}
	
	protected function generateTokenKey() {
		$bool = false;
		$length = 15;
		$token_key;
		do {
			$token_key = substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
			$this->db->where("token", $token_key);
			$avalable = $this->db->getOne("users");
			if(empty($avalable)){
				$bool = true;
			}
		} while (!($bool));
		return $token_key;
	}
		
	function createLoginSession($mobile,$device,$ip,$geo){
		$data = [
			'phone_number'	=> $mobile,
			'device' 		=> $device,
			'ip_address' 	=> $ip,
			'geolocation'	=> $geo
		];
		if($this->db->insert("users_session",$data)){
			return true;
		}else{
			return false;
		}	
	}
	
	function checkAccount($mobile){
		$data = [
			'phone_number'	=> $mobile
		];
		
		$this->db->where("phone_number", $mobile);
		$account = $this->db->getOne("users");
		if(!(empty($account))){
			return true;
		}else{
			return false;
		}	
	}
	
	function createUserAccount($mobile,$device,$email,$fname,$lname,$gender){
		$token = $this->generateTokenKey();
		$user_id = $this->generateUserID();
		$data = [
			'user_id'       => $user_id,
			'firstname'		=> $fname,
			'lastname'		=> $lname,
			'phone_number'	=> $mobile,
			'email'			=> $email,
			'gender'		=> $gender,
			'device_name'	=> $device,
			'token'		 	=> $token,
			'channel_key'	=> 'none'
		];
		$bool = $this->db->insert("users",$data);
		if($bool){
			return true;
		}else{
			return false;
		}	
	}
	
	function editUserAccount($user_id,$email,$fname,$lname){
		$data = [
			'firstname'		=> $fname,
			'lastname'		=> $lname,
			'email'			=> $email,
		];
		$this->db->where("user_id",$user_id);
		if($this->db->update("users",$data)){
			return true;
		}else{
			return false;
		}	
	}
	
	function getUserAccount($mobile){
		$this->db->where("phone_number", $mobile);
		$account = $this->db->getOne("users");
		return $account;
	}
	
	function getUserAccountbyID($user_id){
		$this->db->where("user_id", $user_id);
		$account = $this->db->getOne("users");
		return $account;
	}
	
	function setChannelKey($user_id,$channel){
		$data = Array ( 'channel_key' => $channel );
				$this->db->where("user_id", $user_id);
		$bool = $this->db->update("users", $data);		
		return $bool;
	}
	
	function channelToken($user_id){
		$this->db->where("user_id", $user_id);
		$account = $this->db->getOne("users");
		return $account['channel_key'];
	}
	
	function checkToken($user_id,$token){
		$this->db->where("user_id", $user_id);
		$account = $this->db->getOne("users");
		if(empty($account)){
			if($account['token'] == $token){
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
	
}
?>