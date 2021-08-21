<?php
ini_set('display_errors',1);
error_reporting(1);


# Given an array of configuration data
$configArr = array(
    'database' => array(
        'adapter' => 'pdo_mysql',
        'params'  => array(
            'host'     => 'localhost',
            'username' => 'sanfranc_dba',
            'password' => 'swe7apRa',
            'dbname'   => 'sanfranc_crm'
        )
    )
);


# Create the object-oriented wrapper upon the configuration data
$config = new Zend_Config($configArr);

# Connect to the database
$db = Zend_Db::factory($config->database);
Zend_Db_Table::setDefaultAdapter($db);
Zend_Registry::set('db', $db);

# Result per page
define('DEFAULT_RESULTS_PER_PAGE',5);

# Follow-up length in seconds
define('FOLLOW_UP_STEP',259200); // 3 days

# Default email address and name used for sending emails
define('SENDER_EMAIL_ADDRESS','info@sanfranciscocasinoparty.com');
define('SENDER_NAME','San Francisco Casino Party');
define('NEWSLETTER_EMAIL_SUBJECT','San Francisco Casino Party - Newsletter');
define('ENROLLMENT_FORM_EMAIL_SUBJECT','San Francisco Casino Party - Your Quote');

# clickatell sms library configurations
define('SMS_APP_ID', '2903808');
define('SMS_USER', 'killermobile');
define('SMS_PASSWORD', 'jack5fox');

function dumpvar($var) {
	if (is_object($var)) {
		echo '<pre>';
		var_dump($var);
		echo '</pre>';
	} elseif (is_array($var)) {
		echo '<pre>';
		print_r($var);
		echo '</pre>';
	} else {
		echo '<pre>';
		var_dump($var);
		echo '</pre>';
	}
	die();
}

?>
