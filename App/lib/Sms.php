<?php

class Sms {
	public $db;
	public function __construct($db) {
		$this->db = $db;
	}
	
	protected function generateSmsCode() {
		$bool = false;
		$length = 6;
		$key;
		do {
			$key = substr(str_shuffle(str_repeat($x='0123456789', ceil($length/strlen($x)) )),1,$length);
			$this->db->where("verify_key", $key);
			$this->db->where("status", "pending");
			$avalable = $this->db->getOne("sms_validation");
			if(empty($avalable)){
				$bool = true;
			}
		} while (!($bool));
		return $key;
	}
	
	function createVerifyKey($phone){
		$exp_time = date("Y-m-d H:i:s",strtotime(date("Y-m-d H:i:s")." +10 minutes"));
		$key = $this->generateSmsCode();
		$status = 'pending';
		$data = [
			'verify_key'	=> $key,
			'expire_time' 	=> $exp_time,
			'phone_number' 	=> $phone,
			'status'		=> $status
		];
				
		if($this->db->insert("sms_validation",$data)){
			return $key;
		}else{
			return false;
		}	
	}
	
	function sendPhoneValidation($verify_key, $phone){
		// Send SMS code Here
		$message = "<#>". urlencode($verify_key)." is your activation code from CoolBoda";
		$body = [
			'username'	=>"silicon-pay",
			'to'		=>$phone,
			'message'	=>$message
		];
		$body = http_build_query($body);

		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => 'https://api.africastalking.com/version1/messaging',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS => $body,
		  CURLOPT_HTTPHEADER => array(
			'apiKey: 000000000000000',
			'Content-Type: application/x-www-form-urlencoded'
		  ),
		));
		
		$response = curl_exec($curl);
		curl_close($curl);
		return $response;
	}
	
	function verifySmsKey($verify_key, $phone){		
		$this->db->where("verify_key", $verify_key);
		$this->db->where("phone_number", $phone);
		$verified = $this->db->getOne("sms_validation");
		if(!(empty($verified))){
			return true;
		}else{
			return false;
		}	
	}
	
	function checkOveretrySms($phone){
		$today = date("Y-m-d");
		$this->db->where("status","pending");
		$this->db->where("phone_number",$phone);
		$this->db->where("create_time", $today.'%', 'like'); //Array('LIKE' => "%".$today."%"));
		$query = $this->db->get("sms_validation");
		if(count($query) > 3){
			return true;
		}else{
			return false;
		}	
	}
	
}
?>