<?php

class Jornal {
	public $db;
	public function __construct($db) {
		$this->db = $db;
	}
		
	function createRideJornal($user_id, $driver_id, $ridetoken, $pickup, $dropoff, $coordinate, $distance, $charge, $fee, $payment){
		$start_time = date("Y-m-d H:i:s");
		$data = [
			'user_id' => $user_id,
			'driver_id' => $driver_id,
			'start_address' => $pickup,
			'end_address' => $dropoff,
			'route_lanlon' => $coordinate,
			'route_direction' => $distance,
			'amount' => $charge,
			'driver_fee' => $fee,
			'payment_method' => $payment,
			'ride_token' => $ridetoken,
			'status' => 'requested',
			'user_cancelled'=>0,
			'start_time' => $start_time
		];
		if($this->db->insert("rides_jornal",$data)){
			return true;
		}else{
			return false;
		}	
	}
	
	function updateRideRequest($ride_token, $driver_id, $action){
		$data = Array ( 'status' => $action );
				$this->db->where("driver_id", $driver_id);
				$this->db->where("ride_token", $ride_token);
		$bool = $this->db->update("rides_jornal", $data);		
		return $bool;
	}
	
	function getRideStatus($ride_token){
		$this->db->where("ride_token", $ride_token);
		$ride = $this->db->getOne("rides_jornal");	
		$status = empty($ride) ? ""	: $ride['status'];
		return $status;
	}
	
	function getRideDriver($ride_token){
		$this->db->where("ride_token", $ride_token);
		$ride = $this->db->getOne("rides_jornal");	
		$driver_id = empty($ride) ? ""	: $ride['driver_id'];
		return $driver_id;
	}
	
	function getRideDetail($ride_token){
		$this->db->where("ride_token", $ride_token);
		$ride = $this->db->getOne("rides_jornal");	
		return $ride;
	}
	
	function userCancelRide($ride_token, $user_id){
		$data = Array ( 'status' => 'cancelled', 'user_cancelled' => 1 );
				$this->db->where("user_id", $user_id);
				$this->db->where("ride_token", $ride_token);
		$bool = $this->db->update("rides_jornal", $data);		
		return $bool;
	}
	
	function getRideDistanceHistory($account_id){
		$this->db->where("status", "completed");
		$this->db->where('(`driver_id`="'.$account_id.'" or `user_id`="'.$account_id.'")');
		$distance = $this->db->getOne("rides_jornal", "sum(route_direction) as TotalDistance");
		return $distance['TotalDistance'];
	}
	
	function getRideList($account_id){
		$this->db->orderby("start_time",'desc');
		$this->db->where("user_id", $account_id);
		$this->db->orwhere("driver_id", $account_id);
		$rides = $this->db->get("rides_jornal");
		return $rides;
	}
	
	function getPaymentMethod($ride_token){
		$this->db->where("ride_token", $ride_token);
		$ride = $this->db->getOne("rides_jornal");	
		$method = empty($ride) ? "cash"	: $ride['payment_method'];
		return $method;
	}
	
	function rejectRideRequest($driver_id, $ride_token, $action){
		$data = [
			'driver_id'     => $driver_id,
			'ride_token'	=> $ride_token,
			'state'			=> $action
		];
		if($this->db->insert("enquire_reject",$data)){
			return true;
		}else{
			return false;
		}	
	}
	
	function getCollectedMoneyTodaty($driver_id){
		$today = date("Y-m-d");		
		$this->db->where("status", "completed");
		$this->db->where("start_time", $today.'%', 'like');
		$this->db->where("driver_id", $driver_id);
		$money = $this->db->getOne("rides_jornal", "sum(amount) as TotalMoney");
		$money = ($money['TotalMoney'] == null) ? 0 : $money['TotalMoney'];
		return $money;
	}
	
	
}
?>