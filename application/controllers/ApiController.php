<?php

class ApiController extends Zend_Controller_Action
{
	const API_KEY = 'qut4spa95Drese';
	public $leadType = '';
	public $leadId = '';
	protected $db = null;

	function preDispatch() {
		
	}
	
	public function init()
    {
		// Disable layout and views
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);
		
		// Disable error reporting
		error_reporting(E_ALL);
		ini_set('display_errors', 1);
		
		// Bigger script timeout
		set_time_limit(120); // 2 minutes
		
		$this->db = Zend_Registry::get('db');
		
		$apiKey = $this->_request->getParam('api_key', null);
		if ($apiKey != self::API_KEY) {
			echo 'ERR:Invalid request'; exit;
		}
    }

    public function indexAction()
    {
		$leadType = $this->_request->getParam('type',null);
		$leadId = $this->_request->getParam('id',null);
		
		if (empty($leadType) || empty($leadId)) {
			echo 'ERR:Invalid request'; exit;
		}
		
		// get leadsheet id
		try {
			$sql = "SELECT id FROM quotes WHERE user_id = {$leadId} AND user_type = '{$leadType}' AND active = 1 LIMIT 1";
			$leadsheetId = $this->db->fetchOne($sql);
		} catch (ErrorException $e) {
			echo 'ERR:Invalid request';
			exit;
		}
		
		if (empty($leadsheetId)) {
			echo 'ERR:There is no quote created for you';
			exit;
		}
		
		// check date
   		try {
			$sql = "SELECT details FROM quotes WHERE user_id = {$leadId} AND user_type = '{$leadType}' LIMIT 1";
			$qDetails = $this->db->fetchOne($sql);
		} catch (ErrorException $e) {
			echo 'ERR:Invalid event date';
			exit;
		}
		$qDetailsArr = Zend_Json::decode($qDetails, true);
    	$mysqlFormat = date('Y-m-d', strtotime($qDetailsArr['event_date']));
		$boDate = $this->db->fetchOne("SELECT id FROM `blackout-dates` WHERE campus_id = {$qDetailsArr['campus_id']} AND '{$mysqlFormat}' BETWEEN date_start AND date_end");
		if (!empty($boDate)) {
			echo 'ERR:The date you requested has been blacked out for this campus';
			exit;
		}
		
		$leadsheetObj = new MyLibs_Quote($leadsheetId, 'D');
		$prices = $leadsheetObj->getQuotePrices(true);
		
		if (is_array($prices) && !empty($prices[0]) && $prices[0] == 'fail') {
			echo 'ERR:'.$prices[1];
			exit;
		}
		
		$status = $leadsheetObj->buildPDF();
		if (strlen($status) > 5) {
			echo "ERR:$status";
			exit;
		}
		$pdfFileName = 'MyQuote-' . date('d_m_Y');
		$leadsheetObj->OutputPDF($pdfFileName);
		exit;
    }

    /**
     * Generate leadsheet action
     *
     */
    public function quoteAction() {
    	$this->view->errors = array();
		$this->leadType = $this->_request->getParam('type',null);
		$this->leadId = $this->_request->getParam('id',null);
		
		$leadsheetId = $this->_request->getParam('lId', 0);

		if (!$this->leadType) {
			$this->_redirect('error/show/?NO_LEAD_TYPE');
		}
		if (!$this->leadId) {
			$this->_redirect('error/show/?NO_LEAD_ID');
		}

		$db = Zend_Registry::get('db');

		if ($this->_request->isPost()) {
			$leadsheetData = $this->_request->getPost();
			$outputMode = $leadsheetData['output_mode'];
			unset($leadsheetData['output_mode']);
			unset($leadsheetData['button']);
	   		// validate the input
	    		$validators = array(
	    				'*' => array('allowEmpty' => true),
			    		'first_name' => array(
				    		'Alpha',
				    		'messages' => array(
				    			 array( Zend_Validate_Alpha::STRING_EMPTY => "First name is required" ),
				    			 'You need to enter a valid first name'
				    			 )
			    		),
			    		'last_name' => array(
				    		'Alpha',
				    		'messages' => array(
				    			 array( Zend_Validate_Alpha::STRING_EMPTY => "Last name is required" ),
				    			'You need to enter a valid last name'
				    			)
			    		),
			    		'home_phone' => array(
				    		'Phone',
				    		'allowEmpty' => true,
				    		'messages' => 'The home phone number can only contain numbers, ., -, ( and )'
			    		),
			    		'mobile_phone' => array(
				    		'Phone',
				    		'allowEmpty' => true,
				    		'messages' => 'The mobile phone number can only contain numbers, ., -, ( and )'
			    		),
			    		'email' => array(
				    		'EmailAddress',
				    		'allowEmpty' => true,
				    		'messages' => 'You need to enter a valid email address'
			    		),
			    		'campus_id' => array(
				    		'Digits',
				    		'messages' => array(
				    			 array( Zend_Validate_Digits::STRING_EMPTY => "Market is required" ),
				    			 'Invalid office'
				    			 )
			    		),
			    		'sales_person_id' => array(
				    		'Digits',
				    		'messages' =>  array(
				    			 array( Zend_Validate_Digits::STRING_EMPTY => "Market representative is required" ),
				    			 'Invalid market representative'
				    			 )
			    		)
		    		);

	    	$leadsheetData = new Zend_Filter_Input(array('*'=>'StringTrim'),$validators,$leadsheetData);
    		if ($leadsheetData->hasInvalid() || $leadsheetData->hasMissing()) { // output errors messages
    			// repopulate the form
    			$this->view->formData = $this->_request->getPost();

    			$errorMessages = $leadsheetData->getMessages();
    			foreach ($errorMessages as $field => $error) {
    					$this->view->errors[] = array_pop($error);
    			}
    		}
			else {
				$leadsheetData = $leadsheetData->getUnescaped();
				$eventStartDate = !empty($leadsheetData['event_date']) ? $leadsheetData['event_date'] : 'n/a';
				$quoteCreated = time('m/d/Y');
				if (empty($leadsheetData['event_location'])) {
					$eventLocationArr = array();
					if (!empty($leadsheetData['address'])) {
						$eventLocationArr[] = $leadsheetData['address'];
					}
					if (!empty($leadsheetData['city'])) {
						$eventLocationArr[] = $leadsheetData['city'];
					}
					if (!empty($leadsheetData['state'])) {
						$eventLocationArr[] = $leadsheetData['state'];
					}
					if (!empty($leadsheetData['zip'])) {
						$eventLocationArr[] = $leadsheetData['zip'];
					}
					$eventLocation = implode(', ', $eventLocationArr);
					$leadsheetData['event_location'] = $eventLocation;
				} else {
					$eventLocation = $leadsheetData['event_location'];
				}
				
				// everything OK until now, let's check to make sure the event date is not blacked out
				$mysqlFormat = date('Y-m-d', strtotime($eventStartDate));
				$boDate = $db->fetchOne("SELECT id FROM `blackout-dates` WHERE campus_id = {$leadsheetData['campus_id']} AND '{$mysqlFormat}' BETWEEN date_start AND date_end");
				if (!empty($boDate)) {
					$this->_redirect('profile/?type=' . $this->leadType . '&id=' . $this->leadId . '&error=EVENT_DATE_BLACKED_OUT');
				}
				
				// check to see if a leadsheet info exists for this user
				// $leadsheetId = $db->fetchOne('SELECT id FROM quotes WHERE user_id = '.$this->leadId.' AND user_type=\''.$this->leadType.'\'');
				if ($leadsheetId > 0 ) {
				// update the financials with the total paid price too
					$financial = $db->fetchOne('SELECT financial FROM quotes WHERE id = ' . $leadsheetId);
					if (!empty($financial)) {
						$financialArr = Zend_Json::decode($financial, true);
						$financialArr['total_paid'] = $leadsheetData['paid_amount'];
						$financial = Zend_Json::encode($financialArr);
					}
					$db->update('quotes',array('details' => Zend_Json::encode($leadsheetData), 'event_date' => $eventStartDate,
										'event_location' => $eventLocation,
										'financial' => $financial,
										'created' => $quoteCreated), 'id = '.$leadsheetId);
				}
				else {
					$db->insert('quotes',array('user_id' => $this->leadId, 'user_type' => $this->leadType, 'details' => Zend_Json::encode($leadsheetData), 'event_date' => $eventStartDate, 'event_location' => $eventLocation, 'created' => $quoteCreated));
					$leadsheetId = $db->lastInsertId();
				}
				/*if ($this->leadType != 'new_leads' && $this->leadType != 'leads') {
					$db->update($this->leadType,array('total_amount' => $leadsheetData['total_amount']),'id = '.$this->leadId);
				}*/
				
				// if we only need to save it
				if ('X' == $outputMode) {
					$this->_redirect('profile/?type=' . $this->leadType . '&id=' . $this->leadId . '&message=QUOTE_SAVED');
				}
				
				error_reporting(0);
				ini_set('display_errors',0);
				$this->_helper->layout()->disableLayout();
				$this->_helper->viewRenderer->setNoRender(true);

				$leadsheetObj = new MyLibs_Quote($leadsheetId,$outputMode);
				$leadsheetObj->buildPDF();
				$pdfFileName = $leadsheetData['first_name'].'_'.$leadsheetData['last_name'];
				$leadsheetObj->OutputPDF($pdfFileName);
				// if we only needed to send it, redirect to the profile page
				if (false !== strpos($outputMode, 'S')) {
					$this->_redirect('profile/?type=' . $this->leadType . '&id=' . $this->leadId . '&message=QUOTE_SENT');
				}
			}
		}

		$this->view->leadData = array();
		if ($leadsheetId > 0) {
			$tmp = $db->fetchOne('SELECT details FROM quotes WHERE user_id = '.$this->leadId.' AND user_type=\''.$this->leadType.'\' AND id = ' .$leadsheetId);
			if (!empty($tmp)) {
				$this->view->leadData = Zend_Json::decode($tmp);
			}
		}
		// quote ok ?
		if (!count($this->view->leadData)) {
			// get user market id
			$marketId = $db->fetchOne("SELECT campus_id FROM {$this->leadType} WHERE id = {$this->leadId}");
			// get event date from the profile info; used only for compatibility
			$eventDate = $db->fetchOne("SELECT event_date FROM {$this->leadType} WHERE id = {$this->leadId}");
			$emptyDetails = '{"first_name":"","last_name":"","company":"","home_phone":"","mobile_phone":"","fax":"","address":"","city":"","state":"","zip":"","email":"","campus_id":"'.$marketId.'","hear_about":"","event_date":"","no_of_guests":"","time_from_hour":"","time_from_time":"","time_to_hour":"","time_to_time":"","time_delivery_hour":"","time_delivery_time":"","paid_amount":"","total_amount":"","comments":"","sales_person_id":"","class_info":"","lId":""}';
			$db->insert('quotes', array('user_id' => $this->leadId, 'user_type' => $this->leadType, 'details' => $emptyDetails, 'event_date' => $eventDate, 'created' => time()));
			$leadsheetId = $db->lastInsertId();
			
			// redirect
			$this->_redirect('profile/quote/?useProfile&type=' . $this->leadType . '&id=' . $this->leadId . '&lId=' . $leadsheetId);
			
			$this->view->leadData = array();
			$this->view->leadData['paid_amount'] = 0;
		}
		
		if ($this->_request->__isset('useProfile') || (!$this->view->leadData && !is_array($this->view->leadData)) ) {
			// Read lead info
			$select = $db->select()
						 ->from(array('t1' => $this->leadType))
						 ->where('t1.id = '.$this->leadId);

			$this->view->leadData = $select->query()->fetch();
		}

		// copy the data from the quote financial info if set
		$financial = $db->fetchOne("SELECT financial FROM quotes WHERE id = {$leadsheetId}");
		if (!empty($financial)) {
			$financialArr = Zend_Json::decode($financial, true);
			if (!empty($financialArr) && count($financialArr)) {
				if (isset($financialArr['total_paid'])) {
					$this->view->leadData['paid_amount'] = $financialArr['total_paid'];
				}
				if (isset($financialArr['total_price'])) {
					$this->view->leadData['total_amount'] = $financialArr['total_price'];
				}
			}
		}

		// UPDATE: allow leadsheet generation for leads and new leads too

    	// read the campuses from the database for select options
    	$this->view->campuses = $db->fetchPairs('SELECT id,name FROM campuses');
    	$this->view->selectedCampus = $db->fetchOne('SELECT campus_id FROM '.$this->leadType.' WHERE id = '.$this->leadId);
    	//$this->view->selectedCampus = $this->view->leadData['campus_id'];

    	// fetch for payment discout for this market
    	$this->view->marketFullPayDiscount = $db->fetchOne("SELECT full_pay_discount FROM campuses WHERE id = {$this->view->selectedCampus}");
    	if (empty($this->view->marketFullPayDiscount)) { $this->view->marketFullPayDiscount = 0; }

    	// read the sales persons from the database for select options
    	$this->view->sPersons = $db->fetchPairs('SELECT id,name FROM users WHERE id > 0');
    	$this->view->selectedSPerson = $db->fetchOne('SELECT sales_person_id FROM '.$this->leadType.' WHERE id = '.$this->leadId);
    	//$this->view->selectedSPerson = $this->view->leadData['sales_person_id'];

    	if (!$this->view->formData) $this->view->formData = $this->view->leadData;

    	$this->view->leadType = $this->leadType;
    	$this->view->leadId = $this->leadId;
    	$this->view->lId = $leadsheetId;
    }
    
    /**
     * Payments page (add/delete)
     *
     */
    public function paymentAction() {
    	// check for lead type and lead id
		$this->leadType = $this->_request->getParam('type',null);
		$this->leadId = $this->_request->getParam('id',null);
		$this->quoteId = $this->_request->getParam('qid',null);

		if (!$this->leadType) {
			$this->_redirect('error/show/?NO_LEAD_TYPE');
		}
		if (!$this->leadId) {
			$this->_redirect('error/show/?NO_LEAD_ID');
		}
		
    	$db = Zend_Registry::get('db');

		// check to see if we have to delete a payment entry
		if ($this->_request->getParam('do') == 'delete') {
			$paymentId = $this->_request->getParam('pid');
			if (!$paymentId) {
				$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=DEL_PAYMENT_FAIL');
			}

			if (!$db->delete('payments','id = '.$paymentId)) {
				$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=DEL_PAYMENT_FAIL');
			}
			else {
				$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&message=DEL_PAYMENT_SUCC');
			}
		}
		
    	if (!$this->quoteId) {
			$this->_redirect('error/show/?NO_QUOTE_ID');
		}

		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}

		

		// add the new entry to the database
		if ($this->_request->isPost()) {
			$gasga = $this->_request->getParams();
			$amountPaid = $this->_request->getParam('amount');
			$fullPaid = $this->_request->getParam('pay_in_full');
			$method = $this->_request->getParam('method','none');
			$date = $this->_request->getParam('date');

			if (!$amountPaid || $method == 'none' || !$date) {
				$this->_redirect('profile/quote/?type='.$this->leadType.'&id='.$this->leadId.'&lId='.$this->quoteId.'&error=ADD_PAYMENT_INC_FRM');
			}
			$dbData = array();
			$dbData['details'] = '';
			if ($method == 'Credit Card') {
				$cc_num = $this->_request->getParam('cc_number','');
				$cc_month = $this->_request->getParam('cc_month','');
				$cc_year = $this->_request->getParam('cc_year','');
				if ($cc_num) $dbData['details'] = $cc_num;
				if ($cc_month && $cc_year) $dbData['details'].= ' '.$cc_month.'/'.$cc_year;
			} else if ($method == 'Check') {
				$dbData['details'] = $this->_request->getParam('check_number','') ? $this->_request->getParam('check_number') : '';
			} else if ($method == 'PayPal') {
				$dbData['details'] = $this->_request->getParam('paypal_acc', '');
			}
			
			$dbData['user_id'] = $this->leadId;
			$dbData['user_type'] = $this->leadType;
			$dbData['quote_id'] = $this->quoteId;
			$dbData['amount'] = $amountPaid;
			$dbData['method'] = $method;
			$dbData['date'] = $date;

			if (!$db->insert('payments',$dbData)) {
				$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=ADD_PAYMENT_FAIL');
			}
			
			// update the paid amount and the total amount in the quotes table financials
			$finacial = $db->fetchOne("SELECT financial FROM quotes WHERE id = {$this->quoteId}");
			$details = $db->fetchOne("SELECT details FROM quotes WHERE id = {$this->quoteId}");
			if (!empty($finacial)) {
				$finacialArr = Zend_Json::decode($finacial, true);
				if ($fullPaid) {
					$fpDiscount = $finacialArr['total_price'] - $dbData['amount'];
					$finacialArr['total_paid'] = $dbData['amount'];
					$finacialArr['total_price'] = $dbData['amount'];
					$finacialArr['total_discount'] = $finacialArr['total_gross'] - $finacialArr['total_price'];
					$finacialArr['discounts'][] = array('value' => $fpDiscount, 'desc' => 'Discount for full payment');
					$finacialArr['pay_in_full'] = true;
				} else {
					$finacialArr['total_paid'] += $dbData['amount'];
					$finacialArr['pay_in_full'] = false;
				}
				if (!empty($details)) {
					$detailsArr = Zend_Json::decode($details, true);
					if (!empty($detailsArr) && count($detailsArr)) {
						$detailsArr['total_amount'] = $finacialArr['total_price'];
						$detailsArr['paid_amount'] = $finacialArr['total_paid'];
					}
					$details = Zend_Json::encode($detailsArr);
				}
				$finacial = Zend_Json::encode($finacialArr);
				$db->update('quotes', array('details' => $details, 'financial' => $finacial), "id = {$this->quoteId}");
			}
			

			if ($this->_request->getParam('from','') == 'quote') {
				$this->_redirect('profile/quote/?type='.$this->leadType.'&id='.$this->leadId.'&lId='.$this->quoteId);
			}

			$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&message=ADD_PAYMENT_SUCC');
		}

    	$this->view->leadType = $this->leadType;
    	$this->view->leadId = $this->leadId;
    }

    /**
     * Send Email
     *
     */
    protected function _doEmailContact() {
    	$contactInfo = array();
    	$contactInfo['emailSender'] = ($this->_request->getParam('email_sender',null)) ? $this->_request->getParam('email_sender',null) : SENDER_EMAIL_ADDRESS;
    	$contactInfo['emailAddres'] = $this->_request->getParam('email_address',null);
    	$contactInfo['emailSubject'] = $this->_request->getParam('email_subject',null);
    	$contactInfo['emailMessage'] = $this->_request->getParam('email_body',null);

    	if (!$contactInfo['emailAddres'] || !$contactInfo['emailSubject'] || !$contactInfo['emailMessage']) {
    		// we do redirect to cancel the POST data
    		$this->_redirect('profile/contact/?type='.$this->leadType.'&id='.$this->leadId.'&contactIncompleteEmailData=1');
    	}
    	// get the email address and name for the user who sends the email
		$contactInfo['senderAddress'] = Zend_Auth::getInstance()->getStorage()->read()->email;
		$contactInfo['senderName'] = Zend_Auth::getInstance()->getStorage()->read()->name;

		$mail = new Zend_Mail();
		$mail->setBodyText('You need an HTML compliant email client to see this message')
			 ->setBodyHtml($contactInfo['emailMessage'])
		     ->setFrom($contactInfo['emailSender'], SENDER_NAME)
		     ->addTo($contactInfo['emailAddres'])
		     ->setSubject($contactInfo['emailSubject']);

		if (!$mail->send()) {
			return false;
		}

		// update the communication log for this user
		$this->_updateCommunicationLog('Email',$contactInfo['emailMessage']);

		return true;
    }
}
