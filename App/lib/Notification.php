<?php

class Notification {
	public $db;
	public function __construct($db) {
		$this->db = $db;
	}
	
	protected function SendPushNotification($payload, $notifyData, $channel){
		$url = 'https://fcm.googleapis.com/fcm/send';
		
		//SERVER API
		$apikey = FIREBASE_KEY;
		$header = array(
				'Authorization:key=' . $apikey,
				'Content-Type:application/json'
		);		
		
		//$reqBody = [
		//	'notification' => $notifyData,
		//	'data' => $payload, 		//Optional
		//	'time_to_live' => 3600,			//Optional max is 4 weeks
		//	//'to' => '/topics/realtimegps'	//0759794334
		//	//'registration_ids' => array ( $device_id ),
		//	'to' => $channel		//Topics or Device Token
		//];
		
		$reqBody = [
			'data' => $payload,			
			'to' => $channel		//Topics or Device Token
		];
		
		// Initialize curl with the prepared headers and body, Then execute call and save result
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_POST, true);
		curl_setopt ($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, json_encode($reqBody));
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}
	
	
	function sendDriverEnquire($channel, $user_id, $pickup, $dropoff, $user_name, $user_phone, $ride_token, $amount, $coordinate){
		
		//NOTIFICATION
		$notifyData = [
			'title' => 'New Ride Request',
			'body'  => 'Click to accept new rider to drop off location'
			//'image' => 'IMAGE - URL',
			//'click_action' => "activities.NotifHandlerActivity" //Action/Activity - Optional
		];
		
		$dataPayload = [
			'action' 	=> 'enquire',
			'pickup' 	=> $pickup,
			'dropoff' 	=> $dropoff,
			'user_id' 	=> $user_id,
			'user_name' => $user_name,
			'user_phone'=> $user_phone,
			'ride_token'=> $ride_token,
			'amount' 	=> $amount,
			'coordinate'=> $coordinate,
			'timestamp' => date("Y-m-d H:i:s")
		];
		
		$result = $this->SendPushNotification($dataPayload, $notifyData, $channel);
		return $result;
	}
	
	function sendRideCancellation($channel, $ride_token){
		
		//NOTIFICATION
		$notifyData = [
			'title' => 'Ride Cancelled',
			'body'  => 'Current Ride Travel have been cancelled by Customer'
			//'image' => 'IMAGE - URL',
			//'click_action' => "activities.NotifHandlerActivity" //Action/Activity - Optional
		];
		
		$dataPayload = [
			'action' 	=> 'cancellation',
			'ride_token'=> $ride_token,
			'timestamp' => date("Y-m-d H:i:s")
		];
		
		$result = $this->SendPushNotification($dataPayload, $notifyData, $channel);
		return $result;
	}
	
	function sendUserRideEnd($channel, $ride_token){
		
		//NOTIFICATION
		$notifyData = [
			'title' => 'Ride Completed',
			'body'  => 'You have reached your Destination Address'
			//'image' => 'IMAGE - URL',
			//'click_action' => "activities.NotifHandlerActivity" //Action/Activity - Optional
		];
		
		$dataPayload = [
			'action' 	=> 'completed',
			'ride_token'=> $ride_token,
			'timestamp' => date("Y-m-d H:i:s")
		];
		
		$result = $this->SendPushNotification($dataPayload, $notifyData, $channel);
		return $result;
	}
	
	function updateUserWallet($channel){
		
		//NOTIFICATION
		$notifyData = [
			'title' => 'Ride Completed',
			'body'  => 'You have reached your Destination Address'
			//'image' => 'IMAGE - URL',
			//'click_action' => "activities.NotifHandlerActivity" //Action/Activity - Optional
		];
		
		$dataPayload = [
			'action' 	=> 'refresh',
			'timestamp' => date("Y-m-d H:i:s")
		];
		
		$result = $this->SendPushNotification($dataPayload, $notifyData, $channel);
		return $result;
	}
	
	
}
?>