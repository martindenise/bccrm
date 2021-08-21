<?php

function addToCrm() {
	$info = $_POST;
	$emptyString = '';

	$dbHost = 'localhost';
	$dbName = 'sanfran_crm';
	$dbUser = 'sanfran_crm';
	$dbPass	= 'swe7apRa';

    try {

        $dbh = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $dbh->prepare( "INSERT INTO new_leads (first_name,last_name,company,home_phone,mobile_phone,fax,email,address,city,state,event_date,campus_id,comments,date_added,sales_person_id,cold_lead)
            VALUES(:first_name,:last_name,:company,:home_phone,:mobile_phone,:fax,:email,:address,:city,:state,:event_date,:campus_id,:comments,:date_added,'1','0')");

        // Name
        if (empty($_POST['lname'])) {
	        $tmp = explode(' ',$_POST['name']);
	        if (is_array($tmp) && !empty($tmp[0]) && !empty($tmp[1])) {
	       		$stmt->bindParam(':first_name', $tmp[0]);
	       		$stmt->bindParam(':last_name', $tmp[1]);
	        }
	        else {
	        	$stmt->bindParam(':first_name', $_POST['name']);
	        	$stmt->bindParam(':last_name', $emptyString);
	        }
        }
        else {
	        $stmt->bindParam(':first_name', $_POST['name']);
	        $stmt->bindParam(':last_name', $_POST['lname']);
        }
        
        $company = (!empty($_POST['company']) ? $_POST['company'] : '');
        $stmt->bindParam(':company', $company);

        $homePhone = (!empty($_POST['phone']) ? $_POST['phone'] : '');
        $stmt->bindParam(':home_phone', $homePhone);

        $mobilePhone = (!empty($_POST['mobile']) ? $_POST['mobile'] : '');
        $stmt->bindParam(':mobile_phone', $mobilePhone);

        $email = (!empty($_POST['email']) ? $_POST['email'] : '');
        $stmt->bindParam(':email', $email);
        
        $fax = (!empty($_POST['fax']) ? $_POST['fax'] : '');
        $stmt->bindParam(':fax', $fax);
        

        $address = (!empty($_POST['location']) ? $_POST['location'] : '');
        $stmt->bindParam(':address', $address);

        $city = (!empty($_POST['city']) ? $_POST['city'] : '');
        $stmt->bindParam(':city', $city);
        
        $date = (!empty($_POST['date']) ? $_POST['date'] : '');
        $stmt->bindParam(':event_date', $date);

        $state = ((!empty($_POST['state']) && strlen($_POST['state']) == 2) ? $_POST['state'] : '');
        $stmt->bindParam(':state', $state);

        $comments = (!empty($_POST['comments']) ? $_POST['comments'] : '');
        $stmt->bindParam(':comments', $comments);

        $dateAdded = time();
        $stmt->bindParam(':date_added',$dateAdded);

        // Campus
    /* 	switch ($_POST['school']) {
			case "Hollywood, California": $campus_id = 1; break;
			case "Orange County, California": $campus_id = 2; break;
			case "West Los Angeles, California": $campus_id = 3; break;
			case "Southbay, California": $campus_id = 4; break;
			case "Costa Mesa, California": $campus_id = 5; break;
			default: $campus_id = 1; break;
    	} */
        $campus_id = 4;

        $stmt->bindParam(':campus_id', $campus_id);
        
        $stmt->execute();
    } catch (PDOException $e) {
        $dbh = null;
        return false;
    }

    $dbh = null;
}

?>
