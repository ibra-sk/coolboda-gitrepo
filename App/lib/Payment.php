<?php

class Payment {
	public $db;
	public function __construct($db) {
		$this->db = $db;
	}
	
	function init_payment_momo($currency,$phone_num,$amount,$email,$callback,$txref){
		$data_req = [
			"req"      		=> "mobile_money",
			"currency" 		=> $currency,
			"phone"    		=> $phone_num,
			"encryption_key"=> ENCRYPT_KEY,
			"amount"        => $amount,
			"emailAddress"  => $email,
			'call_back'		=> $callback,
			"txRef"			=> $txref
		];
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => 'https://silicon-pay.com/process_payments',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>json_encode($data_req),
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
		  ),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		return $response;
	}
	
	function createNewWallet($account_id, $account_holder){
		$last_update = date("Y-m-d H:i:s");
		$data = [
			'account_id'	=> $account_id,
			'balance'		=> 0,
			'account_holder'=> $account_holder,
			'last_update'	=> $last_update
		];
		if($this->db->insert("wallet_balance",$data)){
			return true;
		}else{
			return false;
		}
	}
	
	function getWallet($account_id){
		$this->db->where("account_id", $account_id);
		$wallet = $this->db->getOne("wallet_balance");
		return $wallet;
	}
	
	function getTransactionHistory($account_id){
		$this->db->orderby("timestamp",'desc');
		$this->db->where("sender_id", $account_id);
		$this->db->orwhere("receiver_id", $account_id);
		$tx = $this->db->get("transactions");
		return $tx;
	}
	
	function TopUpWallet($txref, $user_id, $amount, $fee){
		$data = [
			'txref' => $txref,
			'sender_id' => $user_id ,
			'receiver_id'=>	'System',
			'ride_token'=> 'none',
			'amount'=> $amount,
			'fee' => $fee,
			'tx_type' => 'deposit',
			'status' => 'pending'
		];
		
		//Insert into Database and init transaction 
		if($this->db->insert("transactions",$data)){
			return true;
		}else{
			return false;
		}
	}
	
	function updateBalance($account_id, $txref){
		$this->db->where("txref", $txref);
		$tx = $this->db->getOne("transactions");
		
		$amount = $tx['amount'];
		$tx_type = $tx['tx_type'];
		$timestamp = $tx['timestamp'];
		
		$this->db->where("account_id", $account_id);
		$wallet = $this->db->getOne("wallet_balance");
		$balance = (empty($wallet)) ? 0 : $wallet['balance'];
		
		$new_balance;
		if($tx_type == 'deposit'){
			$new_balance = $balance + $amount;
		}
		if($tx_type == 'withdraw'){
			$new_balance = $balance - $amount;
		}
		if($tx_type == 'payment'){
			$new_balance = $balance - $amount;
		}
		
		$this->db->where("account_id", $account_id);
		$bool = $this->db->update("wallet_balance", ['balance' => $new_balance, 'last_update' => $timestamp]);
		return $bool;
	}
	
	function payRideFromWallet($txRef, $user_id, $driver_id, $ride_token, $amount, $fee){
		$last_update = date("Y-m-d H:i:s");
		$data = [
			'txref'			=> $txRef,
			'sender_id'		=> $user_id,
			'receiver_id'	=> $driver_id,
			'ride_token'	=> $ride_token,
			'amount'		=> $amount,
			'fee'			=> $fee,
			'tx_type'		=> 'payment',
			'status'		=> 'success',
			'timestamp'		=> $last_update
		];
		if($this->db->insert("transactions",$data)){
			return true;
		}else{
			return false;
		}
	}
	
	function HandleWebhook($reference, $status){
		// The call back came from us. 
		// Give value to your customers.
		
		// Generate a secure hash on your end.
		$this->db->where('txref', $reference);
		$user_id = $this->db->getOne("transactions")['sender_id'];
		if(!empty($user_id)){
			if($status == 'successful'){
				$this->db->where('txref', $reference)->update("transactions", ['status' => 'success']);
				$this->updateBalance($user_id, $reference);
			}else{
				$this->db->where('txref', $reference)->update("transactions", ['status' => 'failed']);
			}
			return $user_id;
		}else{
			return 'none';
		}
		
	}
	
	
}
?>