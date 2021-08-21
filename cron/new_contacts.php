#!/usr/bin/php-cgi -q
<?php

error_reporting(0);
ini_set("display_errors",0);

set_time_limit(0);

$dbHost = 'localhost';
$dbName = 'sanfran_crm';
$dbUser = 'sanfran_crm';
$dbPass	= 'swe7apRa';

define('SENDER_EMAIL_ADDRESS','info@sanfranciscocasinoparty.com');
define('SENDER_NAME','San Francisco Casino Party');

set_include_path(
    dirname(__FILE__) . '/../library'
    . PATH_SEPARATOR . get_include_path()
);
require_once('../library/Zend/Mail.php');

mysql_connect($dbHost,$dbUser,$dbPass) or die('Could not connect to database.');
mysql_select_db($dbName) or die('Could not select database.');

$startTime = time();
$sql = "SELECT `t1`.*, t2.name AS campus_name
			FROM `new_leads` AS `t1`
			INNER JOIN `campuses` AS `t2` ON t2.id = t1.campus_id
			WHERE ".$startTime."-t1.date_added<3600 AND t1.cold_lead = 0";

$leadsRs = mysql_query($sql);

if (!mysql_error() && mysql_num_rows($leadsRs)) {
	while ($leadRow = mysql_fetch_assoc($leadsRs)) {
		if (!empty($leadRow['first_name']) && !empty($leadRow['email'])) {
			$status = sendEmail($leadRow);
		} else {
			$sql = "DELETE FROM `new_leads` WHERE id = {$leadRow['id']}";
			mysql_query($sql);
		}
	}
}

@mysql_close();


function sendEmail($leadData) {
	if (empty($leadData)) {
		return false;
	}
	
	
	$emailBody = <<<ENDD
You have a new contact for sanfranciscocasinoparty.com<br />
<br />
You can see the new contact at this URL: http://www.sanfranciscocasinoparty.com/crm/profile/?type=new_leads&id={$leadData['id']}
<br /><br />
<strong>First Name:</strong> {$leadData['first_name']}<br />
<strong>Last Name:</strong> {$leadData['last_name']}<br />
<strong>Company:</strong> {$leadData['company']}<br />
<strong>Home Phone:</strong> {$leadData['home_phone']}<br />
<strong>Mobile Phone:</strong> {$leadData['mobile_phone']}<br />
<strong>Email Address:</strong> {$leadData['email']}<br />
<strong>Address:</strong> {$leadData['address']}<br />
<strong>City, State, Zip:</strong> {$leadData['city']}, {$leadData['state']}, {$leadData['zip']}<br />
<strong>Market:</strong> {$leadData['campus_name']}<br />
<strong>Event Date:</strong> {$leadData['event_date']}<br />
ENDD;

	$mail = new Zend_Mail();
	$mail->setBodyText('You need an HTML compliant email client to see this message')
		 ->setBodyHtml($emailBody)
	     ->setFrom(SENDER_EMAIL_ADDRESS, SENDER_NAME)
	     ->setSubject('New contact on sanfranciscocasinoparty.com quote request');
	     
	switch($leadData['campus_name']){
		default:
			$toemail = 'info@sanfranciscocasinoparty.com'; break;
		break;
	}
	
	$mail->addTo($toemail);

	if (!$mail->send()) {
		return false;
	}
	return true;
}

?>