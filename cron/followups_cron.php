#!/usr/bin/php-cgi -q
<?php

error_reporting(0);
ini_set("display_errors",0);

set_time_limit(0);

$dbHost = 'localhost';
$dbName = 'sanfran_crm';
$dbUser = 'sanfran_crm';
$dbPass	= 'swe7apRa';

define('FOLLOW_UP_STEP',259200); // 3 days
define('EMAIL_FILES_PATH', dirname(__FILE__) . '/email_tpls');
define('SENDER_EMAIL_ADDRESS','info@sanfranciscocasinoparty.com');
define('SENDER_NAME','San Francisco Casino Party');

set_include_path(
    dirname(__FILE__) . '/../library'
    . PATH_SEPARATOR . get_include_path()
);
require_once('../library/Zend/Mail.php');

mysql_connect($dbHost,$dbUser,$dbPass) or die('Could not connect to database.');
mysql_select_db($dbName) or die('Could not select database.');

// READ LEADS THAT NEEDS FOLLOWUP TODAY
//$sql = "SELECT SQL_CALC_FOUND_ROWS CONCAT_WS(' ',t1.first_name,t1.last_name) ,t1.email,t2.name,t1.date_added,t1.next_followup,t1.id
//FROM leads AS t1 LEFT JOIN campuses AS t2 ON t2.id=t1.campus_id WHERE t1.approval = '1' AND 1265707520-t1.next_followup>259200 AND t1.sales_person_id = 1 LIMIT 0, 5 ";

$startTime = time();
$sql = "SELECT `t1`.`id` AS lead_id, `t1`.`first_name` AS `{first_name}`, `t1`.`last_name` AS `{last_name}`, `t1`.`email` AS `{email_address}`,
				`t2`.`name` AS `{sales_person_name}`, `t2`.`email` AS `{sales_person_email}`, `t3`.`id` AS `{campus_id}`,
				`t3`.`name` AS `{campus_name}`, `t1`.`next_followup`
			FROM `leads` AS `t1`
			INNER JOIN `users` AS `t2` ON t2.id = t1.sales_person_id
			INNER JOIN `campuses` AS `t3` ON t3.id = t1.campus_id
			WHERE t1.next_followup-".$startTime."<86400 AND t1.approval = '1' AND t1.cold_lead = 0";

$leadsRs = mysql_query($sql);

if (!mysql_error() && mysql_num_rows($leadsRs)) {
	while ($leadRow = mysql_fetch_assoc($leadsRs)) {
		$followRs = mysql_query("SELECT times FROM followups WHERE lead_id = {$leadRow['lead_id']}");
		if (!mysql_error()) {
			if (!mysql_num_rows($followRs)) {
				$sql = "INSERT INTO followups (lead_id) ";
				$sql.= "VALUES({$leadRow['lead_id']})";
				mysql_query($sql);
				if (mysql_error()) {
					// error, skip do contact
					$timesContacted = 6;
				} else {
					$timesContacted = 1;
				}
			} else {
				$timesContacted = mysql_result($followRs,0,'times');
				$timesContacted++;
			}
			
			if ($timesContacted < 4) {
				$status = sendEmail($leadRow, $leadRow['{campus_id}'], $timesContacted);
				if ($status) {
					//updateFollowUp($leadRow['lead_id']);
					$next_followup = time() + FOLLOW_UP_STEP;
					mysql_query("UPDATE leads SET next_followup = '{$next_followup}' WHERE id = {$leadRow['lead_id']}");
					mysql_query("UPDATE followups SET last_contact = '" . time() . "', times = {$timesContacted} WHERE lead_id = {$leadRow['lead_id']}");
				}
			}
		}
	}
}

@mysql_query($query);
@mysql_close();


function sendEmail($leadData, $campusId, $contactStep) {
	if (empty($leadData) || empty($campusId) || empty($contactStep)) {
		return false;
	}
	
	//$emailTpl = EMAIL_FILES_PATH . '/' . $campusId . '_' . $contactStep . '.html';
	$emailTpl = EMAIL_FILES_PATH . '/' . $contactStep . '.html';
	if (!file_exists($emailTpl)) {
		return false;
	}
	
	$emailBody = file_get_contents($emailTpl);
	$emailBody = str_replace(array_keys($leadData),array_values($leadData),$emailBody);

	$mail = new Zend_Mail();
	$mail->setBodyText('You need an HTML compliant email client to see this message')
		 ->setBodyHtml($emailBody)
	     ->setFrom(SENDER_EMAIL_ADDRESS, SENDER_NAME)
	     ->addTo($leadData['{email_address}'])
	     ->setSubject('Reminder of your quote');

	if (!$mail->send()) {
		return false;
	}
	return true;
}

?>