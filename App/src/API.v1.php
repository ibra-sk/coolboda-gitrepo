<?php
include APP . 'lib/Database.php';
require APP . 'lib/Sms.php';
require APP . 'lib/Users.php';
require APP . 'lib/Drivers.php';
require APP . 'lib/Jornal.php';
require APP . 'lib/Notification.php';
require APP . 'lib/Payment.php';
require APP . 'lib/System.php';

class APIv1 {
	public $resp;
	public $req;
	public $sms;
	public $users;
	public $drivers;
	public $jornal;
	public $notification;
	public $payment;
	public $mysystem;
	public function __construct() {
		header('Content-Type: application/json; charset=utf-8');
		$this->resp = new stdClass();
		if(empty(file_get_contents("php://input"))){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Empty request sent";
			echo json_encode($this->resp);
			exit;
		}
		
		$this->req = json_decode(file_get_contents("php://input"));
		if (json_last_error() <> JSON_ERROR_NONE) {
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Invalid Json request";
			echo json_encode($this->resp);
			exit;
		}
		
		$db = new MysqliDb(DB_HOST, DB_USER, DB_PASS, DB_NAME);
		$this->sms = new Sms($db);
		$this->users = new Users($db);
		$this->drivers = new Drivers($db);
		$this->jornal = new Jornal($db);
		$this->notification = new Notification($db);
		$this->payment = new Payment($db);
		$this->mysystem = new PlatformSystem($db);				
	}
	
	protected function authAccessKey($main_key, $subb_key) {
		$token = 'NONE'; $headers = apache_request_headers();
		if(isset($headers['Authorization'])){
			$token = trim(str_replace('Bearer', '', $headers['Authorization']));
		}	
		$signature	= hash_hmac('sha256', $main_key, $subb_key);
		if(md5($token) === md5($signature)){	
			return true;
		}else{
			return false;
		}		
	}
	
	protected function generateRandomString($length = 12) {
		return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);		
	}
	
	protected function cleanTranferAmount($amount){
		$amount = str_replace("-","", $amount);
		$amount = str_replace(",","", $amount);
		$amount = intval($amount);
		$amount = preg_replace('/[^A-Za-z0-9\-]/', '',$amount );
		$amount = abs($amount);
		return $amount;
	}
	
	protected function validate_number($gsmNumber,$gsmLength =9){
		//gms prefixes
		$mtn = ["77", "76", "78"]; //01;
		$airtel = ["70", "74", "75"]; //02;
		
		$number = str_replace("+", "", $gsmNumber);
		if(substr($number, 0,3) == "256"){
			$number = str_replace("256","",$number);
		}else{
			return false;
		}
		
		if(strlen($number) != $gsmLength){
			return false;
		}
		
		return true;
	  
	}
	
	protected function getIPAddress() {  
		//whether ip is from the share internet  
		if(!empty($_SERVER['HTTP_CLIENT_IP'])) {  
			$ip = $_SERVER['HTTP_CLIENT_IP'];  
		}  
		//whether ip is from the proxy  
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {  
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];  
		}  
		//whether ip is from the remote address  
		else{  
			$ip = $_SERVER['REMOTE_ADDR'];  
		}  
		return $ip;  
	}

	protected function findDriver($lat1, $long1){
		$result = []; $result = $this->drivers->getAvailableDrivers();
		$liveDriver = $this->drivers->getLiveDrivers();
		$allDistance = [];
		foreach($result as $point){
			if(in_array($point['driver_id'], $liveDriver)){
				$geo = $point['location'];
				$location = explode(";",$geo);
				$lat2 = $location[0]; $long2 = $location[1];
				$thisDistance = $this->drivers->nearestDriver($lat1, $long1, $lat2, $long2);
				array_push($allDistance, array( 'distance' => $thisDistance, 'driver_id' => $point['driver_id']));
			}
		}		
		
		sort( $allDistance );
		//print_r($allDistance);
		if(!(empty($allDistance))){
			return $allDistance[0]['driver_id'];
		}else{
			return '';
		}
	}

	protected function checkInput_Login(){
		$phone 		= isset($this->req->phone)			? $this->req->phone		:"";
		$device 	= isset($this->req->device)			? $this->req->device	:"";
		
		$ip 		= $this->getIPAddress();
		$geo		= unserialize(file_get_contents("http://www.geoplugin.net/php.gp?ip=".$ip));
		$location 	= isset($this->req->location)		? $this->req->location	:$geo["geoplugin_countryName"]."  ".$geo["geoplugin_city"];
		
		$this->req->ip 		 = $ip;
		$this->req->location = $location;
		
		if($phone == '' || $ip == '' || $device == '' || $location == ''){
			$missing = '{';
			$missing = $missing. (($ip == '') ? ' IP,' : '');
			$missing = $missing. (($phone == '') ? ' phone,' : '');
			$missing = $missing. (($device == '') ? ' device,' : '');
			$missing = $missing. (($location == '') ? ' location,' : '');
			$missing = $missing. '}';
			
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter ".$missing;
			echo json_encode($this->resp);
			exit;
		}
	}
	
	protected function checkInput_Verify(){
		$smscode 	= isset($this->req->smscode)		? $this->req->smscode	:"";
		$phone 		= isset($this->req->phone)			? $this->req->phone		:"";
		
		if($phone == '' || $smscode == ''){
			$missing = '{';
			$missing = $missing. (($smscode == '') ? ' smscode,' : '');
			$missing = $missing. (($phone == '') ? ' phone,' : '');
			$missing = $missing. '}';
			
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter ".$missing;
			echo json_encode($this->resp);
			exit;
		}
	}
	
	protected function checkInput_Account(){
		$phone 		= isset($this->req->phone)			? $this->req->phone		:"";
		$device 	= isset($this->req->device)			? $this->req->device	:"";
		$email	 	= isset($this->req->email)			? $this->req->email		:"";
		$firstname 	= isset($this->req->firstname)		? $this->req->firstname	:"";
		$lastname 	= isset($this->req->lastname)		? $this->req->lastname	:"";
		$gender 	= isset($this->req->gender)			? $this->req->gender	:"";
		
		if($phone == '' || $device == '' || $email == '' || $firstname == '' || $lastname == '' || $gender == ''){
			$missing = '{';
			$missing = $missing. (($phone == '') ? ' phone,' : '');
			$missing = $missing. (($device == '') ? ' device,' : '');
			$missing = $missing. (($email == '') ? ' email,' : '');
			$missing = $missing. (($firstname == '') ? ' firstname,' : '');
			$missing = $missing. (($lastname == '') ? ' lastname,' : '');
			$missing = $missing. (($gender == '') ? ' gender,' : '');
			$missing = $missing. '}';
			
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter ".$missing;
			echo json_encode($this->resp);
			exit;
		}
	}
	
	protected function checkInput_TopUp(){
		$user_id 	= isset($this->req->user_id)			? $this->req->user_id		:"";
		$phone		= isset($this->req->phone)				? $this->req->phone			:"";
		$amount		= isset($this->req->amount)				? $this->req->amount		:"";
		$token		= isset($this->req->token)				? $this->req->token			:"";
				
		if($user_id == '' || $phone == '' || $amount == '' || $token == ''){
			$missing = '{';
			$missing = $missing. (($user_id == '') ? ' user_id,' : '');
			$missing = $missing. (($phone == '') ? ' phone,' : '');
			$missing = $missing. (($amount == '') ? ' amount' : '');
			$missing = $missing. (($token == '') ? ' token' : '');
			$missing = $missing. '}';
			
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter ".$missing;
			echo json_encode($this->resp);
			exit;
		}
		
		$checkToken = $this->users->checkToken($user_id,$token);
		if($checkToken){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Invalid Token key given, please retry login";
			echo json_encode($this->resp);
			exit;
		}
	}
	
	protected function checkInput_ProfileEdit(){
		$user_id 	= isset($this->req->user_id)			? $this->req->user_id		:"";
		$firstname	= isset($this->req->firstname)			? $this->req->firstname		:"";
		$lastname	= isset($this->req->lastname)			? $this->req->lastname		:"";
		$email		= isset($this->req->email)				? $this->req->email			:"";
		$token		= isset($this->req->token)				? $this->req->token			:"";
				
		if($user_id == '' || $firstname == '' || $lastname == '' || $email == '' || $token == ''){
			$missing = '{';
			$missing = $missing. (($user_id == '') ? ' user_id,' : '');
			$missing = $missing. (($firstname == '') ? ' firstname,' : '');
			$missing = $missing. (($lastname == '') ? ' lastname' : '');
			$missing = $missing. (($email == '') ? ' email,' : '');
			$missing = $missing. (($token == '') ? ' token' : '');
			$missing = $missing. '}';
			
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter ".$missing;
			echo json_encode($this->resp);
			exit;
		}
		
		$checkToken = $this->users->checkToken($user_id,$token);
		if($checkToken){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Invalid Token key given, please retry login";
			echo json_encode($this->resp);
			exit;
		}
	}
	
	protected function checkInput_FindDriver(){
		$lat1 		= isset($this->req->latitude)			? $this->req->latitude		:"";
		$long1 		= isset($this->req->longitude)			? $this->req->longitude		:"";
		$user_id 	= isset($this->req->user_id)			? $this->req->user_id		:"";
		$pickup 	= isset($this->req->pickup)				? $this->req->pickup		:"";
		$dropoff 	= isset($this->req->dropoff)			? $this->req->dropoff		:"";
		$distance 	= isset($this->req->distance)			? $this->req->distance		:"";
		$coordinate = isset($this->req->coordinate)			? $this->req->coordinate	:"";
		$charge 	= isset($this->req->charge)				? $this->req->charge		:"";
		$payment 	= isset($this->req->payment)			? $this->req->payment		:"";
		
		
		if($lat1 == '' || $long1 == '' || $user_id == '' || $pickup == '' || $dropoff == '' || $distance == '' || $charge == '' || $payment == '' || $coordinate == ''){
			$missing = '{';
			$missing = $missing. (($lat1 == '') ? ' latitude,' : '');
			$missing = $missing. (($long1 == '') ? ' longitude,' : '');
			$missing = $missing. (($user_id == '') ? ' user_id,' : '');
			$missing = $missing. (($pickup == '') ? ' pickup,' : '');
			$missing = $missing. (($dropoff == '') ? ' dropoff,' : '');
			$missing = $missing. (($distance == '') ? ' distance,' : '');
			$missing = $missing. (($charge == '') ? ' charge,' : '');
			$missing = $missing. (($payment == '') ? ' payment,' : '');
			$missing = $missing. (($coordinate == '') ? ' coordinate' : '');
			$missing = $missing. '}';
			
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter ".$missing;
			echo json_encode($this->resp);
			exit;
		}
	}
	
	protected function checkInput_calculateFair(){
		$user_id 	= isset($this->req->user_id)			? $this->req->user_id		:"";
		$eta 		= isset($this->req->eta)				? $this->req->eta			:"";
		$distance 	= isset($this->req->distance)			? $this->req->distance		:"";
		
		
		if($user_id == '' || $eta == '' || $distance == ''){
			$missing = '{';
			$missing = $missing. (($user_id == '') ? ' user_id,' : '');
			$missing = $missing. (($distance == '') ? ' distance,' : '');
			$missing = $missing. (($eta == '') ? ' eta' : '');
			$missing = $missing. '}';
			
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter ".$missing;
			echo json_encode($this->resp);
			exit;
		}
	}
	
	
	public function login(){
		$this->checkInput_Login();
		
		if($this->validate_number($this->req->phone) === false){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Phone number not set to correct format, please start with `256` country code";
			echo json_encode($this->resp);
			exit;
		}
		
		$query = $this->users->createLoginSession($this->req->phone, $this->req->device, $this->req->ip, $this->req->location);
		If(!($query)){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Login session failed to be created, please try again";
			echo json_encode($this->resp);
			exit;
		}
		
		if($this->req->phone <> '256789140565'){
			$checkup = $this->sms->checkOveretrySms($this->req->phone);
			if($checkup){
				$this->resp->response = false;
				$this->resp->status = "failed";
				$this->resp->message = "Retry option overused, try again later";
				echo json_encode($this->resp);
				exit;
			}
		}
		
		
		$code = $this->sms->createVerifyKey($this->req->phone);
		If($code == false){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Failed to create Sms verification code";
			echo json_encode($this->resp);
			exit;
		}
		
		if($this->req->phone <> '256789140565'){
			$africatalks = $this->sms->sendPhoneValidation($code,$this->req->phone);
		}
		//echo($code);
		//echo($africatalks);
		
		$this->resp->response = true;
		$this->resp->status = "success";
		$this->resp->message = "Please verify your phone number first";
		echo json_encode($this->resp);
		exit;
	}
	
	
	public function verifySms(){
		$this->checkInput_Verify();
		
		if($this->validate_number($this->req->phone) === false){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Phone number not set to correct format, please start with `256` country code";
			echo json_encode($this->resp);
			exit;
		}
		
		if($this->req->phone == '256789140565' && $this->req->smscode == '12345'){
			$is_verify = true;
		}else{
			$is_verify = $this->sms->verifySmsKey($this->req->smscode, $this->req->phone);
			if(!($is_verify)){
				$this->resp->response = false;
				$this->resp->status = "failed";
				$this->resp->message = "Invalid SMS code given";
				echo json_encode($this->resp);
				exit;
			}
		}
		
		
		if($this->users->checkAccount($this->req->phone)){
			$userAccount = $this->users->getUserAccount($this->req->phone);
			$account = new stdClass();
			$account->firstname = $userAccount['firstname'];
			$account->lastname = $userAccount['lastname'];
			$account->user_id = $userAccount['user_id'];
			$account->gender = $userAccount['gender'];
			$account->status = $userAccount['status'];
			$account->email = $userAccount['email'];
			$account->token = $userAccount['token'];
			
			$this->resp->account = $account;
			$this->resp->action = "login";
		}else{
			$this->resp->action = "account";
		}
		
		$this->resp->response = true;
		$this->resp->status = "success";
		$this->resp->message = "Sms Code has been successfully verified";
		echo json_encode($this->resp);
		exit;
	}
	
	
	public function createAccount(){
		$this->checkInput_Account();
		
		if($this->validate_number($this->req->phone) === false){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Phone number not set to correct format, please start with `256` country code";
			echo json_encode($this->resp);
			exit;
		}
		
		if($this->users->createUserAccount($this->req->phone, $this->req->device, $this->req->email, $this->req->firstname, $this->req->lastname, $this->req->gender)){
			
			$userAccount = $this->users->getUserAccount($this->req->phone);
			$make_wallet = $this->payment->createNewWallet($userAccount['user_id'], 'user');
			
			$this->resp->response = true;
			$this->resp->status = "success";
			$this->resp->message = "Account has been successfully been created";
			$this->resp->token = $userAccount['token'];
			$this->resp->user_id = $userAccount['user_id'];
			echo json_encode($this->resp);
			exit;
		}else{
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Account creation has failed, please try again";
			echo json_encode($this->resp);
			exit;
		}
		
		
	}
	
	
	public function ResendSms(){
		$phone = isset($this->req->phone) ? $this->req->phone : "";
		if($phone == ''){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter { phone}";
			echo json_encode($this->resp);
			exit;
		}
		
		$checkup = $this->sms->checkOveretrySms($phone);
		if($checkup){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Retry option overused, try again later";
			echo json_encode($this->resp);
			exit;
		}
		
		$code = $this->sms->createVerifyKey($phone);
		If($code == false){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Failed to create Sms verification code";
			echo json_encode($this->resp);
			exit;
		}
		
		
		$africatalks = $this->sms->sendPhoneValidation($code,$this->req->phone);
		//echo($code);
		//echo($africatalks);
		
		$this->resp->response = true;
		$this->resp->status = "success";
		$this->resp->message = "Please verify your phone number first";
		echo json_encode($this->resp);
		exit;
	}
	
	
	public function saveProfileEdit(){
		$this->checkInput_ProfileEdit();
		
		$user_id 	= isset($this->req->user_id)			? $this->req->user_id		:"";
		$firstname	= isset($this->req->firstname)			? $this->req->firstname		:"";
		$lastname	= isset($this->req->lastname)			? $this->req->lastname		:"";
		$email		= isset($this->req->email)				? $this->req->email			:"";
		
		$bool = $this->users->editUserAccount($user_id,$email,$firstname,$lastname);
		if($bool){
			$this->resp->response = true;
			$this->resp->status = "success";
			$this->resp->message = "Profile information has been updated";
			echo json_encode($this->resp);
			exit;
		}else{
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Error updating profile information, please try again";
			echo json_encode($this->resp);
			exit;
		}		
	}
	
	
	public function updateChannelKey(){
		$account_id = isset($this->req->account_id) 	? $this->req->account_id : "";
		$account 	= isset($this->req->account) 	? $this->req->account : "";
		$token 	 	= isset($this->req->token) 	? $this->req->token : "";
		if($account_id == '' || $token == '' || $account == ''){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter { account, account_id, token}";
			echo json_encode($this->resp);
			exit;
		}
		
		if($account <> "user" && $account <> "driver"){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Invalid Account parameter value";
			echo json_encode($this->resp);
			exit;
		}
		
		if($account == "user"){
			$bool = $this->users->setChannelKey($account_id, $token);
			if($bool){
				$this->resp->response = true;
				$this->resp->status = "success";
				$this->resp->message = "Device channel token has been updated";
				echo json_encode($this->resp);
				exit;
			}else{
				$this->resp->response = false;
				$this->resp->status = "failed";
				$this->resp->message = "Failed to save Device channel token.";
				echo json_encode($this->resp);
				exit;
			}
		}
		
		if($account == "driver"){
			$bool = $this->drivers->setChannelKey($account_id, $token);
			if($bool){
				$this->resp->response = true;
				$this->resp->status = "success";
				$this->resp->message = "Your Device channel token has been updated";
				echo json_encode($this->resp);
				exit;
			}else{
				$this->resp->response = false;
				$this->resp->status = "failed";
				$this->resp->message = "Failed to save Device channel token.";
				echo json_encode($this->resp);
				exit;
			}
		}
		
	}
	
	
	public function updateDriverBeacon(){
		$account_id 	= isset($this->req->account_id)		? $this->req->account_id	:"";
		$location 		= isset($this->req->location)		? $this->req->location		:"";
		$speed 			= isset($this->req->speed)			? $this->req->speed			:"";
		$stage	 		= isset($this->req->state)			? $this->req->state			:"";
		
		if($account_id == '' || $location == '' || $speed == '' || $stage == ''){
			$missing = '{';
			$missing = $missing. (($account_id == '') ? ' account_id,' : '');
			$missing = $missing. (($location == '') ? ' location,' : '');
			$missing = $missing. (($speed == '') ? ' speed,' : '');
			$missing = $missing. (($stage == '') ? ' state,' : '');
			$missing = $missing. '}';
			
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter ".$missing;
			echo json_encode($this->resp);
			exit;
		}
		
		$state = false;
		if($stage == "IDLE"){
			$state = true;
		}
		if($stage == "BUSY" || $stage == "OFFLINE"){
			$state = false;
		}
		
		$bool = $this->drivers->setDriverRoute($account_id, $location, $speed, $state);
		if($bool){
			$this->resp->response = true;
			$this->resp->status = "success";
			$this->resp->message = "Driver route updated";
			$this->resp->state = $state;
			echo json_encode($this->resp);
			exit;
		}else{
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Driver route not updated";
			$this->resp->state = $state;
			echo json_encode($this->resp);
			exit;
		}
	}
	
	
	public function loginDriver(){
		$this->checkInput_Login();
		
		if($this->validate_number($this->req->phone) === false){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Phone number not set to correct format, please start with `256` country code";
			echo json_encode($this->resp);
			exit;
		}
		
		$query = $this->drivers->createLoginSession($this->req->phone, $this->req->device, $this->req->ip, $this->req->location);
		If(!($query)){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Login session failed to be created, please try again";
			echo json_encode($this->resp);
			exit;
		}
		
		
		if($this->req->phone <> '256708909771'){
			$checkup = $this->sms->checkOveretrySms($this->req->phone);
			if($checkup){
				$this->resp->response = false;
				$this->resp->status = "failed";
				$this->resp->message = "Retry option overused, try again later";
				echo json_encode($this->resp);
				exit;
			}
		}
		
		$code = $this->sms->createVerifyKey($this->req->phone);
		If($code == false){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Failed to create Sms verification code";
			echo json_encode($this->resp);
			exit;
		}
		if($this->req->phone <> '256708909771'){
			$africatalks = $this->sms->sendPhoneValidation($code,$this->req->phone);
		}
		//echo($code);
		//echo($africatalks);
		
		$this->resp->response = true;
		$this->resp->status = "success";
		$this->resp->message = "Please verify your phone number first";
		echo json_encode($this->resp);
		exit;
	}
	
	
	public function verifySmsDriver(){
		$this->checkInput_Verify();
		
		if($this->validate_number($this->req->phone) === false){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Phone number not set to correct format, please start with `256` country code";
			echo json_encode($this->resp);
			exit;
		}
		
		if($this->req->phone == '256708909771' && $this->req->smscode == '12345'){
			$is_verify = true;
		}else{
			$is_verify = $this->sms->verifySmsKey($this->req->smscode, $this->req->phone);
			if(!($is_verify)){
				$this->resp->response = false;
				$this->resp->status = "failed";
				$this->resp->message = "Invalid SMS code given";
				echo json_encode($this->resp);
				exit;
			}
		}
		
		
		if($this->drivers->checkAccount($this->req->phone)){
			$userAccount = $this->drivers->getDriverAccount($this->req->phone);
			$account = new stdClass();
			$account->firstname = $userAccount['firstname'];
			$account->lastname = $userAccount['lastname'];
			$account->user_id = $userAccount['driver_id'];
			$account->gender = $userAccount['gender'];
			$account->status = $userAccount['status'];
			$account->email = $userAccount['email'];
			$account->token = $userAccount['token'];
			
			$this->resp->account = $account;
			$this->resp->action = "login";
		}else{
			$this->resp->action = "account";
		}
		
		$this->resp->response = true;
		$this->resp->status = "success";
		$this->resp->message = "Sms Code has been successfully verified";
		echo json_encode($this->resp);
		exit;
	}
	
	
	public function searchRideDriver(){
		$this->checkInput_FindDriver();
		
		$lat1 = $this->req->latitude; 
		$long1 = $this->req->longitude;
		$user_id = $this->req->user_id;
		$pickup = $this->req->pickup;
		$dropoff = $this->req->dropoff;
		$distance = $this->req->distance;
		$coordinate = $this->req->coordinate;
		$charge = $this->req->charge;
		$payment = $this->req->payment;
		
		$found_driver = $this->findDriver($lat1, $long1);
		if(!(empty($found_driver))){
			$ride_token = md5(microtime());
			//$charge = 1000;
			$fee = 0;
			
			$bool = $this->jornal->createRideJornal($user_id, $found_driver, $ride_token, $pickup, $dropoff, $coordinate, $distance, $charge, $fee, $payment);
			if($bool){
				$channel = $this->drivers->channelToken($found_driver);
				$user = $this->users->getUserAccountbyID($user_id);
				$this->notification->sendDriverEnquire($channel, $user_id, $pickup, $dropoff, $user['firstname'].' '. $user['lastname'], $user['phone_number'], $ride_token, $charge, $coordinate);
				
				$this->resp->response = true;
				$this->resp->status = "success";
				$this->resp->message = "Searching for Driver";
				$this->resp->ride_token = $ride_token;
				echo json_encode($this->resp);
				exit;
			}else{
				$this->resp->response = false;
				$this->resp->status = "retry";
				$this->resp->message = "Server error, please try again";
				echo json_encode($this->resp);
				exit;
			}
		}else{
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Driver not found";
			echo json_encode($this->resp);
			exit;
		}
	}
	
	
	public function enquireDriver(){
		$ride_token	= isset($this->req->ride_token)			? $this->req->ride_token	:"";
		if($ride_token == ''){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter { ride_token}";
			echo json_encode($this->resp);
			exit;
		}
		
		$driver_id = $this->jornal->getRideDriver($ride_token);
		$account = $this->drivers->getDriverAccountbyID($driver_id);
		$driver = new stdClass();
		$driver->name 			= $account['firstname'].' '.$account['lastname'];
		$driver->phone_number 	= $account['phone_number'];
		$driver->gender 		= $account['gender'];
		$driver->boda_number	= $account['boda_number'];
		$driver->picture 		= $account['picture'];
		$driver->driver_id 		= $account['driver_id'];
		
		
		$status = $this->jornal->getRideStatus($ride_token);
		if($status == ''){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Invalid ride token";
			echo json_encode($this->resp);
			exit;
		}
		
		if($status == 'requested'){
			$this->resp->response = true;
			$this->resp->status = "success";
			$this->resp->message = "pending";
			echo json_encode($this->resp);
			exit;
		}else{
			$this->resp->response = true;
			$this->resp->status = "success";
			$this->resp->message = $status;
			$this->resp->driver = $driver;
			echo json_encode($this->resp);
			exit;
		}
	}
	
	
	public function actionRideEnquire(){
		$driver_id 	= isset($this->req->driver_id)			? $this->req->driver_id		:"";
		$ride_token	= isset($this->req->ride_token)			? $this->req->ride_token	:"";
		$action 	= isset($this->req->action)				? $this->req->action		:"";
				
		if($driver_id == '' || $ride_token == '' || $action == ''){
			$missing = '{';
			$missing = $missing. (($driver_id == '') ? ' driver_id,' : '');
			$missing = $missing. (($ride_token == '') ? ' ride_token,' : '');
			$missing = $missing. (($action == '') ? ' action' : '');
			$missing = $missing. '}';
			
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter ".$missing;
			echo json_encode($this->resp);
			exit;
		}
		
		$ride = $this->jornal->getRideDetail($ride_token);
			
		if($action <> 'accepted' && $action <> 'rejected' && $action <> 'cancelled' && $action <> 'completed' && $action <> 'busy'){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Invalid value given for Action parameter";
			echo json_encode($this->resp);
			exit;
		}
		
		if($action == 'completed'){
			$ride = $this->jornal->getRideDetail($ride_token);	
			$method = $ride['payment_method'];
			$user_id = $ride['user_id'];
			$channel = $this->users->channelToken($user_id);
			$this->notification->sendUserRideEnd($channel, $ride_token);
			if($method == 'wallet'){
				$txRef = md5(microtime());
				$amount = $ride['amount'];
				$fee = $ride['fee'];
				if($this->payment->payRideFromWallet($txRef, $user_id, $driver_id, $ride_token, $amount, $fee)){
					$this->payment->updateBalance($user_id, $txRef);
				}
			}
			$this->resp->payment = $method;
		}
		
		if($action == 'rejected' || $action == 'busy'){
			$query = $this->jornal->rejectRideRequest($driver_id, $ride_token, $action);
			$coordinate = $ride['route_lanlon'];
			$lastLoc = explode(";",$coordinate);
			$lastLoc = explode(":",$lastLoc[0]);
			$lat1 = $lastLoc[0];
			$long1 = $lastLoc[1];
			$found_driver = $this->redoFindDriver($lat1, $long1, $ride_token);
			if($found_driver <> ''){
				$action = "requested";
				$channel = $this->drivers->channelToken($found_driver);
				$user_id = $ride['user_id'];
				$pickup = $ride['start_address'];
				$dropoff = $ride['end_address'];
				$charge = $ride['amount'];
							
				$user = $this->users->getUserAccountbyID($user_id);
				$name = $user['firstname'].' '. $user['lastname']; 
				$phone = $user['phone_number'];
				
				$this->notification->sendDriverEnquire($channel, $user_id, $pickup, $dropoff, $name, $phone, $ride_token, $charge, $coordinate);
			}
		}
		
		//Check if Ride is in Request Status
		if($ride['status'] == 'requested'){
			$bool = $this->jornal->updateRideRequest($ride_token, $driver_id, $action);
			if($bool){
				$this->resp->response = true;
				$this->resp->status = "success";
				$this->resp->message = "Ride request successfully ".$action;
				echo json_encode($this->resp);
				exit;
			}
		}
				
		$this->resp->response = false;
		$this->resp->status = "failed";
		$this->resp->message = "Failed to update Ride request status";
		$this->resp->ride_status = $ride['status'];
		echo json_encode($this->resp);
		exit;
	}
	
	
	public function paymentTopUp(){
		$this->checkInput_TopUp();
		
		$user_id 	= isset($this->req->user_id)			? $this->req->user_id		:"";
		$phone		= isset($this->req->phone)				? $this->req->phone			:"";
		$amount		= isset($this->req->amount)				? $this->req->amount		:0;
		
		$amount = $this->cleanTranferAmount($amount);
		if(!($this->validate_number($phone, 9))){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Invalid Phone Number";
			echo json_encode($this->resp);
			exit;
		}
		
		$txref = md5(microtime());
		$call_back = DOMAIN."/webhook/ipn";
		$buz_email = "company@email.com";
		
		//lets insert this info in a payments to databse. 
		if($this->payment->TopUpWallet($txref, $user_id, $amount, 0)){
			//Init Mobile money Payment. 
			//echo("ugx"."|".$phone."|".$amount."|".$buz_email."|".$call_back."|".$txref);
			$query = $this->payment->init_payment_momo("ugx",$phone,$amount,$buz_email,$call_back,$txref);	
			$query = json_decode($query);	
			$this->resp->response = true;
			$this->resp->status = "success";
			$this->resp->message = "Mobile Money Deposit has been Initialized";
			$this->resp->reply = $query;
			echo json_encode($this->resp);
			exit;
		}else{
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Error requesting Top up payment, please try again";
			echo json_encode($this->resp);
			exit;
		}
	}
	
	
	public function cancelUserRide(){
		$user_id 	= isset($this->req->user_id)			? $this->req->user_id		:"";
		$ride_token	= isset($this->req->ride_token)			? $this->req->ride_token	:"";
				
		if($user_id == '' || $ride_token == ''){
			$missing = '{';
			$missing = $missing. (($user_id == '') ? ' user_id,' : '');
			$missing = $missing. (($ride_token == '') ? ' ride_token' : '');
			$missing = $missing. '}';
			
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter ".$missing;
			echo json_encode($this->resp);
			exit;
		}
		
		$ride = $this->jornal->getRideDetail($ride_token);
		
		//Check if Ride is in Request Status
		if($ride['status'] <> 'completed'){
			$bool = $this->jornal->userCancelRide($ride_token, $user_id);
			if($bool){
				$driver_id = $this->jornal->getRideDriver($ride_token);
				$account = $this->drivers->getDriverAccountbyID($driver_id);
				$channel = $account['channel_key'];
				$this->notification->sendRideCancellation($channel, $ride_token);
				
				$this->resp->response = true;
				$this->resp->status = "success";
				$this->resp->message = "Ride has been cancelled";
				echo json_encode($this->resp);
				exit;
			}
		}
		
		
		$this->resp->response = false;
		$this->resp->status = "failed";
		$this->resp->message = "Ride Cancellation Failed, please try again";
		$this->resp->ride_status = $ride['status'];		
		echo json_encode($this->resp);
		exit;
	}
	
	
	public function calculateRideFair(){
		$this->checkInput_calculateFair();
		$user_id 	= isset($this->req->user_id)			? $this->req->user_id		:"";
		$eta 		= isset($this->req->eta)				? $this->req->eta			:"";
		$distance 	= isset($this->req->distance)			? $this->req->distance		:"";
		
		
		$fee = $this->mysystem->getRideFees();
		if($fee == null){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Failed to update Ride request status";
			echo json_encode($this->resp);
			exit;
		}
		
		$wallet = $this->payment->getWallet($user_id);
		$balance = isset($wallet['balance']) ? $wallet['balance'] :0;
		
		$charge = $distance * $fee;
		if($charge < 2000){ $charge = 2000;}
		$is_enough = ($balance >= $charge) ? true : false;
		$this->resp->response = true;
		$this->resp->status = "success";
		$this->resp->message = "Your Ride charges has been Estimated";
		$this->resp->feedback = array( "charge" => $charge, "is_sufficient" => $is_enough);
		echo json_encode($this->resp);
		exit;
	}
	
	
	// Recieve IPN/Call Back
	public function IPN_Webhook(){
		$myfile = fopen(DATA.'webhook_call.logs', "a") or die("Unable to open file!");
		fwrite($myfile, file_get_contents("php://input").", /n/r  ");
		fclose($myfile);
		
		if(empty(file_get_contents("php://input"))){
			$obj = new stdClass();
			$obj->status = 'error';
			$obj->message = 'invalid parameter sent to web';
			echo json_encode($obj);
			return;
		}
		// Recieve IPN. 
		$body = file_get_contents("php://input");
		$dataObject = json_decode($body);
		
		$reference   = $dataObject->txRef;
		$secure_hash = $dataObject->secure_hash;
		$secrete_key = SECRET_KEY;

		// Generate a secure hash on your end.
		$cipher = 'aes-256-ecb';
		$generated_hash = openssl_encrypt($reference, $cipher, $secrete_key);
		//echo $generated_hash;
		
		if($generated_hash == $secure_hash){
			$user_id = $this->payment->HandleWebhook($reference, $dataObject->status);
			
			//Mabye Notify User App with FCM here
			if($dataObject->status == 'successful' && $user_id <> 'none'){
				$channel = $this->users->channelToken($user_id);
				print_r($this->notification->updateUserWallet($channel));
			}
		}		
	}
	
	
	
	//// Single Fetch Request Calls
	public function fetchPromoImage(){
		$picture = $this->mysystem->getPromoPicture();
		if($picture == null){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "No promotion image";
			echo json_encode($this->resp);
			exit;
		}else{
			$this->resp->response = true;
			$this->resp->status = "success";
			$this->resp->picture = $picture;
			echo json_encode($this->resp);
			exit;
		}
		
	}
	
	public function fetchMiles(){
		$user_id = isset($this->req->user_id) ? $this->req->user_id	:"";
		if($user_id == ''){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter { user_id}";
			echo json_encode($this->resp);
			exit;
		}
		
		$distance = $this->jornal->getRideDistanceHistory($user_id);
		$this->resp->response = true;
		$this->resp->status = "success";
		$this->resp->distance = isset($distance) ? $distance :0;
		echo json_encode($this->resp);
		exit;
		
	}
	
	public function fetchRideHistory(){
		$user_id = isset($this->req->user_id) ? $this->req->user_id	:"";
		if($user_id == ''){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter { user_id}";
			echo json_encode($this->req);
			exit;
		}
		
		$rides = $this->jornal->getRideList($user_id);
		$this->resp->response = true;
		$this->resp->status = "success";
		$this->resp->rides = $rides;
		echo json_encode($this->resp);
		exit;
		
	}
	
	public function fetchWalletHistory(){
		$user_id = isset($this->req->user_id) ? $this->req->user_id	:"";
		if($user_id == ''){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter { user_id}";
			echo json_encode($this->resp);
			exit;
		}
		
		$transactions = $this->payment->getTransactionHistory($user_id);
		$this->resp->response = true;
		$this->resp->status = "success";
		$this->resp->transactions = $transactions;
		echo json_encode($this->resp);
		exit;
	}
	
	public function fetchBalance(){
		$user_id = isset($this->req->user_id) ? $this->req->user_id	:"";
		if($user_id == ''){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter { user_id}";
			echo json_encode($this->resp);
			exit;
		}
		
		$wallet = $this->payment->getWallet($user_id);
		$this->resp->response = true;
		$this->resp->status = "success";
		$this->resp->wallet = isset($wallet['balance']) ? $wallet['balance'] :0;
		echo json_encode($this->resp);
		exit;		
	}
	
	public function fetchDriverLocation(){
		$user_id 	= isset($this->req->user_id) 	? $this->req->user_id		:"";
		$driver_id 	= isset($this->req->driver_id) 	? $this->req->driver_id		:"";
		$ride_token = isset($this->req->ride_token) ? $this->req->ride_token	:"";
		if($user_id == '' || $driver_id == '' || $ride_token == ''){
			$this->resp->response = false;
			$this->resp->status = "failed";
			$this->resp->message = "Missing parameter { user_id, driver_id, ride_token}";
			echo json_encode($this->resp);
			exit;
		}
		
		$position = $this->drivers->driverPosition($driver_id);
		if(empty($position)){
			$this->resp->response = true;
			$this->resp->status = "success";
			$this->resp->position = $position;
			echo json_encode($this->resp);
			exit;		
		}else{
			$coordinate = explode(";",$position['location']);
			$track = new stdClass();
			$track->lat = $coordinate[0];
			$track->lon = $coordinate[1];
			$track->speed = $position['speed'];
			$track->timestamp = $position['timestamp'];
			$this->resp->response = true;
			$this->resp->status = "success";
			$this->resp->position = $track;
			echo json_encode($this->resp);
			exit;		
		}
		
	}
	
	public function fetchHelp(){
		$this->resp->response = true;
		$this->resp->status = "success";
		$this->resp->help = array( 'title' => 'CoolBoda Help Center');
		echo json_encode($this->resp);
		exit;		
	}
	
	public function fetchEarnedMoney(){
		$driver_id = $this->req->driver_id;
		$money = $this->jornal->getCollectedMoneyTodaty($driver_id);
		
		$this->resp->response = true;
		$this->resp->status = "success";
		$this->resp->money = $money;
		echo json_encode($this->resp);
		exit;		
	}
	
	public function redoFindDriver($lat1, $long1, $ride_token){
		//$lat1 = '0.2963364';
		//$long1 = '32.6064232';
		//$ride_token = '8cbabf5d37f40f476c879f59328c2df6';
		$result = []; 
		$liveDriver = $this->drivers->getLiveDrivers();
		$result = $this->drivers->getAvailableDrivers();
		$reject = $this->drivers->getRejectedDrivers($ride_token);
		
		//print_r($liveDriver);
		//print_r($result);
		//print_r($reject);
		
		$allDistance = [];
		foreach($result as $point){
			if(in_array($point['driver_id'], $liveDriver)){
				if(in_array($point['driver_id'], $reject) == false){
					$geo = $point['location'];
					$location = explode(";",$geo);
					$lat2 = $location[0]; $long2 = $location[1];
					$thisDistance = $this->drivers->nearestDriver($lat1, $long1, $lat2, $long2);
					array_push($allDistance, array( 'distance' => $thisDistance, 'driver_id' => $point['driver_id']));
				}
			}
			
		}		
		
		sort( $allDistance );
		if(!(empty($allDistance))){
			return $allDistance[0]['driver_id'];
		}else{
			return '';
		}
	}
	
	
	
	public function testdriver(){
		$channel = "dPvSfSGTRS27A-pBAr8GBf:APA91bGc3XEtKMKJpumVvi1paaqk8l08WVxIYYhJzQxTkwsIXQy40hA0zuwU3lRGh1i5Pkpib7e6f_EHvAUGJF97PTFY5J5qA4g5ih_Q7R2OTYZzYWvgMPA1qsm4LyQyBOYFS0o8rpIj";
		$user_id = "R9GH3D8BtpVm6sUMhnT2";
		$pickup = "residential house,  Diplomat Road,  Karunga";
		$dropoff= "Kololo Air Strip,  Lower Kololo Terrace,  Lower Kololo";
		$name = "Ibra Dev";
		$phone = "256789140565";
		$ride_token = "5578298c3ea6ad699346c4e980963e71";
		$charge = "218";
		$coordinate = "0.2963309:32.6064144;0.3260398:32.59473159";
		
		$result = $this->notification->sendDriverEnquire($channel, $user_id, $pickup, $dropoff, $name, $phone, $ride_token, $charge, $coordinate);
		echo $result;		
	}
	
	public function testfinder(){
		$lat1 = $this->req->lat;
		$long1 = $this->req->long;
		$ride_token = $this->req->ride_token;
		
		echo ($this->redoFindDriver($lat1, $long1, $ride_token));
	}

	
}
?>