<?php

class Drivers {
	public $db;
	public function __construct($db) {
		$this->db = $db;
	}
	
	protected function generateDriverID() {
		$bool = false;
		$length = 20;
		$user_id;
		do {
			$user_id = substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
			$this->db->where("driver_id", $user_id);
			$avalable = $this->db->getOne("drivers");
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
			$avalable = $this->db->getOne("drivers");
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
		if($this->db->insert("drivers_session",$data)){
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
		$account = $this->db->getOne("drivers");
		if(!(empty($account))){
			return true;
		}else{
			return false;
		}	
	}
	
	function createDriverAccount($mobile,$device,$email,$fname,$lname,$gender){
		$token = $this->generateTokenKey();
		$driver_id = $this->generateDriverID();
		$data = [
			'driver_id'     => $driver_id,
			'firstname'		=> $fname,
			'lastname'		=> $lname,
			'phone_number'	=> $mobile,
			'email'			=> $email,
			'gender'		=> $gender,
			'device_name'	=> $device,
			'token'		 	=> $token
		];
		if($this->db->insert("drivers",$data)){
			return true;
		}else{
			return false;
		}	
	}
	
	function getDriverAccount($mobile){
		$this->db->where("phone_number", $mobile);
		$account = $this->db->getOne("drivers");
		return $account;
	}
	
	function getDriverAccountbyID($driver_id){
		$this->db->where("driver_id", $driver_id);
		$account = $this->db->getOne("drivers");
		return $account;
	}
	
	function getRejectedDrivers($ride_token){
		$allReject = [];
		$this->db->where("ride_token", $ride_token);
		$drivers = $this->db->get("enquire_reject", null, Array ("driver_id"));
		if(!(empty($drivers))){
			foreach($drivers as $driver){
				array_push($allReject, $driver['driver_id']);
			}
		}
		return $allReject;	
	}
	
	function setChannelKey($driver_id,$channel){
		$data = Array ( 'channel_key' => $channel );
				$this->db->where("driver_id", $driver_id);
		$bool = $this->db->update("drivers", $data);		
		return $bool;
	}
	
	function setDriverRoute($driver_id,$location,$speed,$isfree){
		$data = Array ( 
			'driver_id' => $driver_id,
			'location' => $location,
			'speed' => $speed,
			'available' => $isfree
		);
		
		if($this->db->insert("driver_route",$data)){
			return true;
		}else{
			return false;
		}	
	}
	
	function channelToken($driver_id){
		$this->db->where("driver_id", $driver_id);
		$account = $this->db->getOne("drivers");
		return $account['channel_key'];
	}
	
	function getAvailableDrivers(){
		$current_date = date("Y-m-d");
		//$current_time = "";//date("h");
		//echo $current_date.' '.$current_time.'%';
		//$this->db->where("available", 1);
		//$this->db->where("timestamp", $current_date.' '.$current_time.'%', 'like');
		//$this->db->groupBy("driver_id");
		//$drivers = $this->db->get("driver_route");
		
		$drivers = $this->db->rawQuery('select `driver_id`, `location`, `speed`, `timestamp` from driver_route where (`driver_id`, `timestamp`) in ( select `driver_id`, max(`timestamp`) as `timestamp` from driver_route group by `driver_id`) AND `available`=1 AND `timestamp` LIKE "'.$current_date.'%"', null);	
		return $drivers;	
		
		// ABOVE IS THE OLD WAY ^^
		
		
	}
	
	function getLiveDrivers(){
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => 'https://coolboda-ws.herokuapp.com/drivers',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'GET',
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
		  ),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		
		$arr = json_decode($response, true);
		return $arr;
	}
	
	function nearestDriver($lat1, $long1, $lat2, $long2){
		$DistanceinMiles = 3963.0 * acos((sin($lat1) * sin($lat2)) + cos($lat1) * cos($lat2) * cos($long2 - $long1)); //in Miles
		$Distanceinkilometer = 1.609344 * $DistanceinMiles; //in Kilometer
		return $Distanceinkilometer;
	}
	
	function driverPosition($driver_id){
		$this->db->where("driver_id", $driver_id);
		//$this->db->where("available", 1);
		$this->db->orderby("timestamp",'desc');
		$position = $this->db->getOne("driver_route");
		return $position;
	}

}
?>