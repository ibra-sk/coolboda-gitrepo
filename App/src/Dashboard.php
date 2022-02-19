<?php
class Dashboard {
	public $member_id;	
	public $member;
	public $db;
	public function __construct() {
		session_start();
		$this->db = new MysqliDb(DB_HOST, DB_USER, DB_PASS, DB_NAME);
		if(!isset($_SESSION['staff_access_id'])){
			header('location: '.DOMAIN.'login');
			exit();
		}else{
			$this->member_id = $_SESSION['staff_access_id'];
			$this->db->where('staff_id', $this->member_id);
			$this->member = $this->db->getOne('staff');
			if(empty($this->member)){
				unset($_SESSION['staff_access_id']);
				header('location: '.DOMAIN.'login');
				exit();
			}
		}
	}
	
	protected function render($view_file,$view_data){
		$this->view_file = $view_file;
		$this->view_data = $view_data;
		if(file_exists(APP . 'view/' . $view_file . '.phtml'))
		{
		  include APP . 'view/' . $view_file . '.phtml';
		}
	}
	
	protected function startsWith( $haystack, $needle ) {
		$length = strlen( $needle );
		return substr( $haystack, 0, $length ) === $needle;
	}
	
	protected function generateID() {
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
	
	public function index() {
		$this->db->where('status', 'completed');
		$done_ride = $this->db->get('rides_jornal');
		if(empty($done_ride)){
			$sum_ride = 0;
		}else{
			$sum_ride = count($done_ride);
		}
		
		
		$done_users = $this->db->get('users');
		if(empty($done_users)){
			$sum_users = 0;
		}else{
			$sum_users = count($done_users);
		}
		
		
		$done_drivers = $this->db->get('drivers');
		if(empty($done_drivers)){
			$sum_drivers = 0;
		}else{
			$sum_drivers = count($done_drivers);
		}
				
		$this->render('dashboard/include/header', []);
		$this->render('dashboard/include/sidenav', ['active' => 'home', 'name' => $this->member['display_name'], 'email' => $this->member['email']]);
		$this->render('dashboard/index', ['totalUser' => $sum_users, 'totalRide' => $sum_ride, 'totalDriver' => $sum_drivers]);
		$this->render('dashboard/include/footer', []);
	}
	
	public function tabCustomers() {
		$users = $this->db->get('users');		
		$this->render('dashboard/include/header', []);
		$this->render('dashboard/include/sidenav', ['active' => 'customers', 'name' => $this->member['display_name'], 'email' => $this->member['email']]);
		$this->render('dashboard/users', ['users' => $users]);
		$this->render('dashboard/include/footer', []);
	}
	
	public function tabDrivers() {
		$drivers = $this->db->get('drivers');		
		$this->render('dashboard/include/header', []);
		$this->render('dashboard/include/sidenav', ['active' => 'drivers', 'name' => $this->member['display_name'], 'email' => $this->member['email']]);
		$this->render('dashboard/drivers', ['drivers' => $drivers]);
		$this->render('dashboard/include/footer', []);
	}
	
	public function tabRides() {
		$rides = $this->db->get('rides_jornal');		
		$this->render('dashboard/include/header', []);
		$this->render('dashboard/include/sidenav', ['active' => 'rides', 'name' => $this->member['display_name'], 'email' => $this->member['email']]);
		$this->render('dashboard/rides', ['rides' => $rides]);
		$this->render('dashboard/include/footer', []);
	}
	
	public function tabPayments() {
		$tx = $this->db->get('transactions');

		$sum_wallet = $this->db->getOne('wallet_balance', "SUM(balance) as TotalAmount");
		$sum_wallet = empty($sum_wallet['TotalAmount']) ? 0 : $sum_wallet['TotalAmount'];
		
		
		$nowyear = date('Y');
		$khart = [];
		foreach($tx as $query){
			$year = date('Y', strtotime($query['timestamp']));
			if($year == $nowyear){
				$month = date('M', strtotime($query['timestamp']));
				if(isset($khart[$month])){ 
					$khart[$month] += 1; 
				}else{
					$khart[$month] = 1; 
				}
			}
		}
		$month_name = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Set', 'Oct', 'Nov', 'Dec'];
		$chart = [];
		foreach($month_name as $mnth){
			$num_count = 0;
			if(isset($khart[$mnth])){$num_count = $khart[$mnth];}
			$chart[$mnth] = $num_count;
		}
		
		$this->db->where("tx_type", "deposit");
		$query = $this->db->get('transactions');
		$deposit_ratio = (empty($query)) ? 0 : count($query);
		
		$this->db->where("tx_type", "payment");
		$query = $this->db->get('transactions');
		$payment_ratio = (empty($query)) ? 0 : count($query);
		
		
		$this->render('dashboard/include/header', []);
		$this->render('dashboard/include/sidenav', ['active' => 'payments', 'name' => $this->member['display_name'], 'email' => $this->member['email']]);
		$this->render('dashboard/payments', ['tx' => $tx, 'sumwallet' => $sum_wallet, 'chart' => $chart, 'deposit_ratio' => $deposit_ratio, 'payment_ratio' => $payment_ratio]);
		$this->render('dashboard/include/footer', []);
	}
	
	public function tabSettings() {		
		$this->render('dashboard/include/header', []);
		$this->render('dashboard/include/sidenav', ['active' => 'settings', 'name' => $this->member['display_name'], 'email' => $this->member['email']]);
		$this->render('dashboard/settings', ['account' => $this->member]);
		$this->render('dashboard/include/footer', []);
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	public function viewCustomer() {
		if(isset($_GET['user'])){
			$user_id = $_GET['user'];
			$this->db->where('user_id', $user_id);
			$user = $this->db->getOne('users');	
			if(!empty($user)){
				$this->db->where("status", "completed");
				$this->db->where("user_id", $user_id);
				$distance = $this->db->getOne("rides_jornal", "sum(route_direction) as TotalDistance");
				$distance = empty($distance['TotalDistance']) ? 0 : $distance['TotalDistance'];
				
				$this->db->where("status", "completed");
				$this->db->where("user_id", $user_id);
				$allrides = $this->db->get("rides_jornal");
				
				$this->db->where("status", "completed");
				$this->db->where("user_id", $user_id);
				$this->db->groupBy("driver_id");
				$num_driver = $this->db->get("rides_jornal");
				$num_driver = count($num_driver);
				
				$this->db->where("user_cancelled", "1");
				$this->db->where("user_id", $user_id);
				$num_cancel = $this->db->get("rides_jornal");
				$num_cancel = count($num_cancel);
				
				$this->render('dashboard/include/header', []);
				$this->render('dashboard/include/sidenav', ['active' => 'customers', 'name' => $this->member['display_name'], 'email' => $this->member['email']]);
				$this->render('dashboard/view_user', ['user' => $user, 'distanceKm' => $distance, 'rides' => $allrides, 'numdrivers' => $num_driver, 'numcancel' => $num_cancel]);
				$this->render('dashboard/include/footer', []);
			}else{
				echo 'no user';
			}
		}else{
			header('location: '.DOMAIN.'dashboard/customers');
			exit();
		}
		
	}
	
	public function viewDriver() {
		if(isset($_GET['user'])){
			$driver_id = $_GET['user'];
			$this->db->where('driver_id', $driver_id);
			$driver = $this->db->getOne('drivers');	
			if(!empty($driver)){
				$this->db->where("status", "completed");
				$this->db->where("driver_id", $driver_id);
				$distance = $this->db->getOne("rides_jornal", "sum(route_direction) as TotalDistance");
				$distance = empty($distance['TotalDistance']) ? 0 : $distance['TotalDistance'];
				
				$this->db->where("status", "completed");
				$this->db->where("driver_id", $driver_id);
				$allrides = $this->db->get("rides_jornal");
				
				$this->db->where("status", "completed");
				$this->db->where("driver_id", $driver_id);
				$this->db->groupBy("user_id");
				$num_user = $this->db->get("rides_jornal", null, array("driver_id", "user_id"));
				$num_user = count($num_user);
				
				$this->db->where("state", "rejected");
				$this->db->where("driver_id", $driver_id);
				$this->db->groupBy("ride_token");
				$num_reject = $this->db->get("enquire_reject", null, array("driver_id", "ride_token"));
				$num_reject = count($num_reject);
				
				$this->render('dashboard/include/header', []);
				$this->render('dashboard/include/sidenav', ['active' => 'drivers', 'name' => $this->member['display_name'], 'email' => $this->member['email']]);
				$this->render('dashboard/view_driver', ['driver' => $driver, 'distanceKm' => $distance, 'rides' => $allrides, 'numusers' => $num_user, 'numreject' => $num_reject]);
				$this->render('dashboard/include/footer', []);
			}else{
				header('location: '.DOMAIN.'dashboard/drivers');
				exit();
			}
		}else{
			header('location: '.DOMAIN.'dashboard/drivers');
			exit();
		}
		
	}
	
	public function newDriver() {
		if(isset($_POST['firstname']) && isset($_POST['lastname']) && isset($_POST['phone']) && isset($_POST['email']) && isset($_POST['gender']) && isset($_POST['boda']) && isset($_FILES['driverimg'])){
			
			$driver_id = $this->generateID();
			$token = $this->generateTokenKey();
			$fname = $_POST['firstname'];
			$lname = $_POST['lastname'];
			$phone = $_POST['phone'];
			$email = $_POST['email'];
			$gender = $_POST['gender'];
			$boda = $_POST['boda'];
			$image = $driver_id.'.jpg';
			$image_url = DOMAIN.'storage/drivers/'.$driver_id.'.jpg';
			
			if (!($this->startsWith($phone, '256'))) {
				if ($this->startsWith($phone, '0')){
					$phone = '256'.ltrim($phone, '0');
				}else{
					$phone = '256'.$phone;
				}
			}
			
			$sourcePath = $_FILES['driverimg']['tmp_name'];
			$targetPath = DATA."drivers/".$image;
			if(move_uploaded_file($sourcePath,$targetPath)){
				
				$data = [
					'driver_id'     => $driver_id,
					'firstname'		=> $fname,
					'lastname'		=> $lname,
					'phone_number'	=> $phone,
					'boda_number'	=> $boda,
					'picture'		=> $image_url,
					'email'			=> $email,
					'gender'		=> $gender,
					'device_name'	=> "Empty",
					'token'		 	=> $token,
					'channel_key'	=> "0",
					'status'		=> "active"
				];
				if($this->db->insert("drivers",$data)){
					header('location: '.DOMAIN.'dashboard/drivers');
					exit();
				}else{
					header('location: '.DOMAIN.'dashboard/drivers/new?alert=save_fail');
					exit();
				}	
			}else{
				header('location: '.DOMAIN.'dashboard/drivers/new?alert=photo_fail');
				exit();
			}
		}else{
			$this->render('dashboard/include/header', []);
			$this->render('dashboard/include/sidenav', ['active' => 'drivers', 'name' => $this->member['display_name'], 'email' => $this->member['email']]);
			$this->render('dashboard/newdriver', []);
			$this->render('dashboard/include/footer', []);
		}
	}
	
	public function reviewPayments() {	
		if(isset($_GET['dated'])){
			$dated = $_GET['dated'];
			$this->db->where("status", 'success');
			$this->db->where("tx_type", 'payment');
			$this->db->where("timestamp", '%'.$dated.'%', 'like');
			$tx = $this->db->get('transactions');
			
			$num_tx = count($tx);
			
			$this->db->where("status", 'success');
			$this->db->where("tx_type", 'payment');
			$this->db->where("timestamp", '%'.$dated.'%', 'like');
			$sum_tx = $this->db->getOne('transactions', "SUM(amount) as TotalAmount");
			$sum_tx = empty($sum_tx['TotalAmount']) ? 0 : $sum_tx['TotalAmount'];
			
			$this->render('dashboard/include/header', []);
			$this->render('dashboard/include/sidenav', ['active' => 'payments', 'name' => $this->member['display_name'], 'email' => $this->member['email']]);
			$this->render('dashboard/review_payments', ['tx' => $tx, 'numTx' => $num_tx, 'sumTx' => $sum_tx]);
			$this->render('dashboard/include/footer', []);
		}else{
			$thisDate = date('Y-m-d');
			header('location: '.DOMAIN.'dashboard/payments/review?dated='.$thisDate.'');
			exit();
		}
		
	}
	
}

?>