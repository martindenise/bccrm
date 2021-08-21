#!/usr/bin/php-cgi -q
<?php

error_reporting(0);
ini_set("display_errors",0);

$dbHost = 'localhost';
$dbName = 'sanfran_crm';
$dbUser = 'sanfran_crm';
$dbPass	= 'swe7apRa';

mysql_connect($dbHost,$dbUser,$dbPass) or die('Could not connect to database.');
mysql_select_db($dbName) or die('Could not select database.');

# COLD LEAD TO DEAD LEAD AFTER 90 DAYS
// Leads
$sql = 'SELECT id,DATE_FORMAT(toLead_date,"%m/%d/%Y") AS date FROM leads WHERE cold_lead = 1';
$rs = mysql_query($sql);
if (mysql_error()) die('MySQL error: 1');
while ($row = mysql_fetch_assoc($rs)) {
	$date = strtotime($row['date']);
	if (!$date)
		continue;

	if (7776000 < (time() - $date)) {
		mysql_query('UPDATE leads SET cold_lead = 2 WHERE id = '.$row['id']);
	}
}
// Enrollments
$sql = 'SELECT id,DATE_FORMAT(enrolled_date,"%m/%d/%Y") AS date FROM booked WHERE cold_lead = 1';
$rs = mysql_query($sql);
if (mysql_error()) die('MySQL error: 2');
while ($row = mysql_fetch_assoc($rs)) {
	$date = strtotime($row['date']);
	if (!$date)
		continue;

	if (7776000 < (time() - $date)) {
		mysql_query('UPDATE enrollments SET cold_lead = 2 WHERE id = '.$row['id']);
	}
}


require('JSON.php');

# TO COLD LEAD AFTER 6 CONTACT ATTEMPTS
$sql = 'SELECT t1.user_id,t1.user_type,t1.log,t2.next_followup FROM communication AS t1, leads AS t2 WHERE user_type = \'leads\' AND t2.id=t1.user_id';
$rs = mysql_query($sql);
if (mysql_error()) die('MySQL error: 3');
while ($row = mysql_fetch_assoc($rs)) {
	if ($row['log'] == '')
		continue;

	$contactsNo = count(json_decode($row['log'],true));
	if ($contactsNo >= 6) {

		if((time() - $row['next_followup']) >= 0) {
			$sql = 'UPDATE leads SET cold_lead = 1 WHERE id = '.$row['user_id'];
			mysql_query($sql);
		}
	}
}

@mysql_query($query);
@mysql_close();


?>