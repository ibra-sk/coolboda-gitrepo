<?php
const DS = DIRECTORY_SEPARATOR;
define('DOMAIN', "https://coolboda.xyz". DS);
define('TITLE', "Cool Boda");
define('APP', dirname(__DIR__) . DS . "coolboda" . DS . "App" . DS);
define('DATA', dirname(__DIR__) . DS . "coolboda" . DS . "storage" . DS);
define('DB_TYPE', 'mysql');
define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');
define('ENCRYPT_KEY', '');
define('SECRET_KEY', '');
define('FIREBASE_KEY', 'AAAAmJz_BnY:APA91bEvwM1WmsiihijWX2jJJ_6Gd6stvSoMRMXSx4F1sX8coKAZwWrYSx5zBWgsIvqO_-MocVpG5jm3UAY7kPFQkmPahtB0HcmRAgZYcb0-po-Fqwa3TGxtoiOwtp6bL3XpaoiUB70R');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'route.php';


//Hanlders
include APP . 'src/ErrorHandle.php';

//Controllers
include APP . 'src/API.v1.php';
include APP . 'src/Home.php';
include APP . 'src/Dashboard.php';


$route = new Route();
$route->add('/', 'Home', '');
$route->add('/home', 'Home', '');
$route->add('/login', 'Home', 'login');
$route->add('/logout', 'Home', 'logout');
$route->add('/test', 'Home', 'testthis');
//$route->add('/testhook', 'Home', 'Testhook');


//Dashboard Routes
$route->add('/dashboard', 'Dashboard', 'index');
$route->add('/dashboard/home', 'Dashboard', 'index');
$route->add('/dashboard/customers', 'Dashboard', 'tabCustomers');
$route->add('/dashboard/customers/view', 'Dashboard', 'viewCustomer');
$route->add('/dashboard/drivers', 'Dashboard', 'tabDrivers');
$route->add('/dashboard/drivers/view', 'Dashboard', 'viewDriver');
$route->add('/dashboard/drivers/new', 'Dashboard', 'newDriver');
$route->add('/dashboard/rides', 'Dashboard', 'tabRides');
$route->add('/dashboard/payments', 'Dashboard', 'tabPayments');
$route->add('/dashboard/payments/review', 'Dashboard', 'reviewPayments');
$route->add('/dashboard/settings', 'Dashboard', 'tabSettings');



//API Routes
$route->add('/api/v1/login', 'APIv1', 'login');
$route->add('/api/v1/verify', 'APIv1', 'verifySms');
$route->add('/api/v1/resendsms', 'APIv1', 'ResendSms');
$route->add('/api/v1/newaccount', 'APIv1', 'createAccount');
$route->add('/api/v1/profile/edit', 'APIv1', 'saveProfileEdit');
$route->add('/api/v1/cancel/ride', 'APIv1', 'cancelUserRide');
$route->add('/api/v1/ride/fairamount', 'APIv1', 'calculateRideFair');
$route->add('/api/v1/find/driver', 'APIv1', 'searchRideDriver');
$route->add('/api/v1/enquire/driver', 'APIv1', 'enquireDriver');
$route->add('/api/v1/update/channelkey', 'APIv1', 'updateChannelKey');
$route->add('/api/v1/payment/topup', 'APIv1', 'paymentTopUp');

$route->add('/api/v1/fetch/help', 'APIv1', 'fetchHelp');
$route->add('/api/v1/fetch/wallet', 'APIv1', 'fetchBalance');
$route->add('/api/v1/fetch/promotion', 'APIv1', 'fetchPromoImage');
$route->add('/api/v1/fetch/miles', 'APIv1', 'fetchMiles');
$route->add('/api/v1/fetch/ridehistory', 'APIv1', 'fetchRideHistory');
$route->add('/api/v1/fetch/wallethistory', 'APIv1', 'fetchWalletHistory');
$route->add('/api/v1/fetch/driver/location', 'APIv1', 'fetchDriverLocation');
$route->add('/api/v1/fetch/collected/money', 'APIv1', 'fetchEarnedMoney');

$route->add('/api/v1/driver/login', 'APIv1', 'loginDriver');
$route->add('/api/v1/driver/verify', 'APIv1', 'verifySmsDriver');
$route->add('/api/v1/driver/gpsbeacon', 'APIv1', 'updateDriverBeacon');
$route->add('/api/v1/driver/action/enquire', 'APIv1', 'actionRideEnquire');

$route->add('/webhook/ipn', 'APIv1', 'IPN_Webhook');


//Test Purpose Only -- DELETE AFTER FINISH
$route->add('/api/v1/test/driver', 'APIv1', 'testdriver');
$route->add('/api/v1/redo/driver', 'APIv1', 'testfinder');

$route->submit();

?>