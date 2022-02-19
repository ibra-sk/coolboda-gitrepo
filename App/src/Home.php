<?php
class Home {
	public $db;
	
	public function __construct() {
		$this->db = new MysqliDb(DB_HOST, DB_USER, DB_PASS, DB_NAME);
	}
	
	protected function render($view_file,$view_data){
		$this->view_file = $view_file;
		$this->view_data = $view_data;
		if(file_exists(APP . 'view/' . $view_file . '.phtml'))
		{
		  include APP . 'view/' . $view_file . '.phtml';
		}
	}
	
	public function index() {
		$this->render('include/header', []);
		$this->render('index', []);
		$this->render('include/footer', []);
	}
		
	public function login() {		
		session_start();
		if(isset($_SESSION['staff_access_id'])){
			$staff_id = $_SESSION['staff_access_id'];
			$this->db->where('staff_id', $staff_id);
			$member = $this->db->getOne('staff');
			if(empty($member)){
				unset($_SESSION['staff_access_id']);
			}else{
				if($member['staff_role'] == 'staff'){
					header('location: '.DOMAIN.'portal/home');
					exit();
				}
				if($member['staff_role'] == 'admin'){
					header('location: '.DOMAIN.'dashboard/home');
					exit();
				}
			}
		}
		
		$alert;
		if(isset($_POST['submit'])){
			if(isset($_POST['email']) && isset($_POST['password'])){
				$email = $_POST['email'];
				$password = $_POST['password'];
				$hashedpass = hash("sha256", $password);
				
				$this->db->where ("email", $email);
				$this->db->where ("password", $hashedpass);
				$data = $this->db->getOne ("staff");
				if(empty($data)){
					$alert = 'Wrong Email or Password, please try again';
					$this->render('dashboard/include/header', []);
					$this->render('login',  ['status' => '201', 'alert' => $alert]);
				}else{
					$_SESSION['staff_access_id'] = $data['staff_id'];
					//echo $data['role'];
					//echo $_SESSION['staff_access_id'];
					if($data['staff_role'] == 'staff'){
						header('location: '.DOMAIN.'portal/home');
						exit();
					}
					if($data['staff_role'] == 'admin'){
						header('location: '.DOMAIN.'dashboard/home');
						exit();
					}
				}
			}else{
				$alert = 'Please fill all Fields before submitting';
				$this->render('dashboard/include/header', []);
				$this->render('login',  ['status' => '201', 'alert' => $alert]);
			}
		}else{
			$this->render('dashboard/include/header', []);
			$this->render('login',  ['status' => '200']);
		}
	}
	
	public function logout() {
		session_start();
		unset($_SESSION['staff_access_id']);
		header('location: home');
	}
	
}
?>