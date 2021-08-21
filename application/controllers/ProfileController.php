<?php

class ProfileController extends Zend_Controller_Action
{
	const ADD_ENROLL_SUCC 			= 'The booking was succesfully added to the system. It will appear in the bookings list after the operator approves it.';
	const TO_COLDLEAD_SUCC 			= 'The lead was succesfully marked as Cold Lead.';
	const TO_COLDLEAD_FAIL 			= 'The lead could not be marked as Cold Lead.';
	const TO_DEADLEAD_SUCC 			= 'The lead was succesfully marked as Dead Lead.';
	const TO_DEADLEAD_FAIL 			= 'The lead could not be marked as Dead Lead.';
	const CHANGE_TYPE_SUCC 			= 'The lead type was succesfully changed.';
	const CHANGE_TYPE_FAIL 			= 'The lead type could not be changed. Please try again.';
	const CONTACT_SUCC	   			= 'The contact action was succesfully finished and/or logged.';
	const EDIT_PROFILE_SUCC			= 'The lead\'s profile was succesfully updated.';
	const EDIT_PROFILE_FAIL 		= 'The lead\'s profile could not be updated. Please try again.';
	const NO_NEWLEADS_EDIT			= 'A new lead\'s profile can\'t be modified. Change it to lead and try again.';
	const ADD_PAYMENT_SUCC  		= 'The payment entry was succesfully added.';
	const ADD_PAYMENT_FAIL 			= 'An error occured while trying to add the payment entry. Please try again.';
	const DEL_PAYMENT_SUCC  		= 'The payment entry was succesfully deleted.';
	const DEL_PAYMENT_FAIL  		= 'An error occured while trying to delete the payment entry. Please try again.';
	const ADD_PAYMENT_INC_FRM 		= 'Incomplete payment details. Please try again.';
	const NO_NEWLEADS_QUOTE 		= 'A quote can\'t be generated for new contacts.';
	const NO_LEADS_QUOTE 			= 'A quote can\'t be generated for leads.';
	const APPROVAL_NO_EDIT			= 'This booking is waiting for a confirmation. You can not change this profile. You can only export quote or add payment.';
	const APPROVAL_NO_DELETE		= 'This booking is waiting for a confirmation. You can not delete it. You can only export quote or add payment.';
	const QUOTE_SENT 				= 'The quote was succesfully sent to the user.';
	const QUOTE_SAVED 				= 'The quote was succesfully saved.';
	const SET_REMINDER_FAIL 		= 'The reminder could not be set. You can only add a reminder for leads. Or maybe the request was incomplete. Please try again.';
	const SET_REMINDER_SUCCESS 		= 'The new reminder was succesfully saved for this lead.';
	const EVENT_DATE_BLACKED_OUT 	= 'The event date is blacked out for this campus. Please choose another date.';

	public $leadType = '';
	public $leadId = '';

	function preDispatch()
	{
		$auth = Zend_Auth::getInstance();
		if (!$auth->hasIdentity()) {
			$this->_redirect('auth/login');
		}
	}

    public function indexAction()
    {
		$this->leadType = $this->_request->getParam('type',null);
		$this->leadId = $this->_request->getParam('id',null);

		if (!$this->leadType) {
			$this->_redirect('error/show/?NO_LEAD_TYPE');
		}
		if (!$this->leadId) {
			$this->_redirect('error/show/?NO_LEAD_ID');
		}

		# check if we have some messages trough get
		if ($this->_request->get('message',null)) {
			$this->view->succesMessage = constant('self::'.$this->_request->getParam('message'));
		}
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}

		$db = Zend_Registry::get('db');

		# check to see if we have to update the reminder field
		if ($this->_request->isPost()) {
			$reminderDate = $this->_request->getParam('reminderDate');
			if (!empty($reminderDate) && $this->leadType == 'leads') {
				list($m,$d,$y) = explode('/',$reminderDate);
				$nextReminder = mktime(date('H'),date('i'),date('s'),$m,$d,$y);

				if (!$db->update($this->leadType,array('next_followup' => $nextReminder),'id = ' . $this->leadId)) {
					$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=SET_REMINDER_FAIL');
				}
				else {
					$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&message=SET_REMINDER_SUCCESS');
				}
			}
			else {
				$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=SET_REMINDER_FAIL');
			}
		}

		$select = $db->select()
					 ->from(array('t1' => $this->leadType))
					 ->join(array('t2' => 'campuses'),
					 		't1.campus_id = t2.id',
					 		array('campus' => 't2.name'))
					 ->join(array('t3' => 'users'),
					 		't1.sales_person_id = t3.id',
					 		array('sales_person' => 'name'))
					 ->joinLeft(array('t4' => 'communication'),
					 		't1.id = t4.user_id AND t4.user_type = \''.$this->leadType.'\'',
					 		array('log'))
					 ->where('t1.id = '.$this->leadId);

		$this->view->leadData = $select->query()->fetch();

		// replace the sales person id with the sales person name for each contact made
		if (!$this->view->leadData['log']) $this->view->leadData['log'] = '';
		$this->view->leadData['log'] = Zend_Json::decode($this->view->leadData['log']);
		if (count($this->view->leadData['log']) && is_array($this->view->leadData['log'])) {
			foreach ($this->view->leadData['log'] as $key => $val) {
				$this->view->leadData['log'][$key]['sales_person'] = $db->fetchOne('SELECT name FROM users WHERE id = '.$this->view->leadData['log'][$key]['sales_person']);
			}
		}
		
		// get the quotes info
		$selectQ = $db->select()
					 ->from('quotes', array('event_date',
					 							 'event_location',
					 							 'created',
					 							 'id', 'id as qid'))
					 ->where('user_id = '.$this->leadId);
		$this->view->leadData['quotes'] = $selectQ->query()->fetchAll();
		//echo '<pre>'; print_r($this->view->leadData['quotes']);exit;
		// get the payments info
		$select = $db->select()
					 ->from('payments',array('date',
					 						 'method',
					 						 'details',
					 						 'amount',
					 						 'id'))
					 ->where('user_id = '.$this->leadId)
					 ->where('user_type = \''.$this->leadType.'\'');
		$this->view->leadData['payments'] = $select->query()->fetchAll();

		// add the total paid amount to the lead's data
		$select->reset(Zend_Db_Select::COLUMNS );
		$select->reset(Zend_Db_Select::FROM );
		$select->from('payments',array('paid_amount' => 'SUM(amount)'));

		$rs = $select->query()->fetch();
		$this->view->leadData['paid_amount'] = !empty($rs['paid_amount']) ? $rs['paid_amount'] : '';
		
		if (empty($this->view->leadData['ev_day_cname'])) {
			$this->view->leadData['ev_day_cname'] = '';
		}
    if (empty($this->view->leadData['ev_day_cno'])) {
			$this->view->leadData['ev_day_cno'] = '';
		}

		$this->view->leadType = $this->leadType;
		$this->view->leadId = $this->leadId;
    }

    /**
     * Edit lead page
     *
     */
    public function editAction() {
		$this->leadType = $this->_request->getParam('type',null);
		$this->leadId = $this->_request->getParam('id',null);

		if (!$this->leadType) {
			$this->_redirect('error/show/?NO_LEAD_TYPE');
		}
		if (!$this->leadId) {
			$this->_redirect('error/show/?NO_LEAD_ID');
		}
		
		// UPDATE: edit available for new leads too
    	//if ($this->leadType == 'new_leads') {
    	//	$this->_redirect('profile/?type=new_leads&id='.$this->leadId.'&error=NO_NEWLEADS_EDIT');
    	//}

		$db = Zend_Registry::get('db');

		// if the form was submitted
		if ($this->_request->isPost()) {
			$postData = $this->_request->getPost();
			if (isset($postData['amount_due'])) unset($postData['amount_due']);
			unset($postData['button']);

			if (!$db->update($this->leadType,$postData,'id = '.$this->leadId))
				$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=EDIT_PROFILE_FAIL');

			$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&message=EDIT_PROFILE_SUCC');
		}

		// Read lead info
		$select = $db->select()
					 ->from(array('t1' => $this->leadType))
					 ->where('t1.id = '.$this->leadId);

		$this->view->leadData = $select->query()->fetch();
		//echo '<pre>'; print_r($this->view->leadData); die();

		// UPDATE: edit available for new leads too
		// if the enrollment is waiting for a confirmation
		if ($this->leadType != 'new_leads' && $this->view->leadData['approval'] != '1') {
			$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=APPROVAL_NO_EDIT');
		}

    	// read the campuses from the database for select options
    	$this->view->campuses = $db->fetchPairs('SELECT id,name FROM campuses');
    	$this->view->selectedCampus = $this->view->leadData['campus_id'];

    	// read the sales persons from the database for select options
    	$this->view->sPersons = $db->fetchPairs('SELECT id,name FROM users WHERE id > 0');
    	$this->view->selectedSPerson = $this->view->leadData['sales_person_id'];

    	$this->view->leadType = $this->leadType;
    	$this->view->leadId = $this->leadId;

    	$this->view->IsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;

    	// render the form
		$this->view->editForm = $this->view->render('profile/edit/'.$this->leadType.'.phtml');
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
    	if ($this->leadType == 'new_leads') {
			$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=NO_NEWLEADS_QUOTE');
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
										'created' => $quoteCreated, 'active' => '1'), 'id = '.$leadsheetId);
				}
				else {
					$db->insert('quotes',array('user_id' => $this->leadId, 'user_type' => $this->leadType, 'details' => Zend_Json::encode($leadsheetData), 'event_date' => $eventStartDate, 'event_location' => $eventLocation, 'created' => $quoteCreated, 'active' => '1'));
					$leadsheetId = $db->lastInsertId();
				}
				/*if ($this->leadType != 'new_leads' && $this->leadType != 'leads') {
					$db->update($this->leadType,array('total_amount' => $leadsheetData['total_amount']),'id = '.$this->leadId);
				}*/
				
				// if we only need to save it
				if ('X' == $outputMode) {
					$this->_redirect('profile/?type=' . $this->leadType . '&id=' . $this->leadId . '&message=QUOTE_SAVED');
				}
				
				error_reporting(1);
				ini_set('display_errors',1);
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
    
    public function deleteQuoteAction()
    {
    	$this->_helper->layout()->disableLayout();
    	$this->_helper->viewRenderer->setNoRender();
    	
    	$quoteId = $this->_request->getParam('lId', 0);
    	$userType = $this->_request->getParam('type', 0);
    	$userId = $this->_request->getParam('id', 0);
    	if (empty($userType)) {
			$this->_redirect('error/show/?NO_LEAD_TYPE');
    	}
    	if (empty($userId)) {
			$this->_redirect('error/show/?NO_LEAD_ID');
    	}
    	if (empty($quoteId)) {
			$this->_redirect('error/show/?NO_QUOTE_ID');
    	}
    	
    	$db = Zend_Registry::get('db');
    	$db->delete('quotes', "id = {$quoteId}");
    	
    	$this->_redirect('profile/?type='. $userType . '&id=' . $userId);
    }
    
 	public function updateEventDateAction()
    {
    	$this->_helper->layout()->disableLayout();
    	$this->_helper->viewRenderer->setNoRender();
    	
    	$quoteId = $this->_request->getParam('qid', 0);
    	$eventDate = $this->_request->getParam('event_date', 0);
    	if (empty($quoteId) || empty($eventDate)) {
    		echo 'error'; exit;
    	}
    	
    	$db = Zend_Registry::get('db');
    	$db->update('quotes', array('event_date' => $eventDate), 'id = ' . $quoteId);
    }
    
    public function updateTotalPriceAction()
    {
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
		header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
		header("Cache-Control: no-cache, must-revalidate" );
		header("Pragma: no-cache" );
    	
    	$this->_helper->layout()->disableLayout();
    	$this->_helper->viewRenderer->setNoRender();
    	
    	$quoteId = $this->_request->getParam('qid', 0);
    	if (empty($quoteId)) {
    		echo 'error'; exit;
    	}
    	$force = $this->_request->getParam('force', false);
    	$force = ($force == "false") ? false : true;
    	
    	$quoteObj = new MyLibs_Quote($quoteId);
		$prices = $quoteObj->getQuotePrices($force);
		if (empty($prices)) {
			echo 'error'; exit;
		}
		if (isset($prices[0]) && $prices[0] == 'fail') {
			echo 'error:'.$prices[1]; exit;
		}
		if (!isset($prices['total_price'])) {
			echo 'error'; exit;
		}
		
		echo $prices['total_price'];
    }

    /**
     * Window with contact details
     *
     */
    public function contactpopupAction()
    {
    	$this->_helper->layout()->disableLayout();

    	$this->leadId = $this->_request->getParam('id',null);
    	$this->leadType = $this->_request->getParam('type',null);
    	$contactItemId = $this->_request->getParam('itemId',null);

    	if (!$this->leadId || !$this->leadType || !$contactItemId) {
    		$this->view->error = true;
    	}
    	else {
    		$db = Zend_Registry::get('db');
    		$logString = $db->fetchOne('SELECT log FROM communication WHERE user_id = '.$this->leadId.' AND user_type = \''.$this->leadType.'\'');
    		if (!$logString) {
    			$this->view->error = true;
    		}
    		else {
    			$logArray = Zend_Json::decode($logString);
    			if (!is_array($logArray)) {
    				$this->view->error = true;
    			}
    			else {
    				$this->view->contactItem = $logArray[$contactItemId-1];
    			}
    		}
    	}
    }

    /**
     * Change the status for a lead
     *
     */
    public function changetypeAction()
    {
    	// disable view rendering
    	//$this->_helper->viewRenderer->setNoRender();
    	// check for lead type and lead id
		$this->leadType = $this->_request->getParam('type',null);
		$this->leadId = $this->_request->getParam('id',null);

		if (!$this->leadType) {
			$this->_redirect('error/show/?NO_LEAD_TYPE');
		}
		if (!$this->leadId) {
			$this->_redirect('error/show/?NO_LEAD_ID');
		}

		$db = Zend_Registry::get('db');

		// if we have post, we need to change lead
		if ($this->_request->isPost()) {
			if ($this->leadType == 'leads') {
				$leadData = $db->fetchRow('SELECT * FROM leads WHERE id = '.$this->leadId);
				if (!$leadData) {
					$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=CHANGE_TYPE_FAIL');
				}

				//$leadData['total_amount'] = $this->_request->getParam('total_amount','');
				$leadData['ev_day_cname'] = $this->_request->getParam('ev_day_cname','');
				$leadData['ev_day_cno'] = $this->_request->getParam('ev_day_cno','');
				$leadData['event_date'] = $this->_request->getParam('event_date','');
				$leadData['approval'] = 'change';
				unset($leadData['id']);
				unset($leadData['next_followup']);

				// move the lead to enrollments table
				if (!$db->insert('booked',$leadData)) {
					$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=CHANGE_TYPE_FAIL');
				}
				$newLeadId = $db->lastInsertId();
				// delete the lead from leads table
				$db->delete('leads','id = '.$this->leadId);

				// update data in the communication table
				$db->update('communication',array('user_id' => $newLeadId, 'user_type' => 'booked'),'user_id = '.$this->leadId.' AND user_type = \'leads\'');
				// update data in the payments table
				$db->update('payments',array('user_id' => $newLeadId, 'user_type' => 'booked'),'user_id = '.$this->leadId.' AND user_type = \'leads\'');
				// update data in the quotes table
				$db->update('quotes',array('user_id' => $newLeadId, 'user_type' => 'booked'),'user_id = '.$this->leadId.' AND user_type = \'leads\'');

				$this->_redirect('booked?message=CHANGE_STATUS');
			}
		}

		// cancel cold or dead lead
    	if ($this->_request->__isset('nocold')) {
    		if (!$db->update($this->leadType,array('cold_lead' => '0'),'id = '.$this->leadId))
    			$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=CHANGE_TYPE_FAIL');
    		else
    			$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&message=CHANGE_TYPE_SUCC');
    	}

		// mark as cold lead
    	if ($this->_request->__isset('tocold')) {
    		if (!$db->update($this->leadType,array('cold_lead' => '1'),'id = '.$this->leadId))
    			$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=TO_COLDLEAD_FAIL');
    		else
    			$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&message=TO_COLDLEAD_SUCC');
    	}

    	// mark as dead lead
    	if ($this->_request->__isset('todead')) {
    		if (!$db->update($this->leadType,array('cold_lead' => '2'),'id = '.$this->leadId))
    			$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&error=TO_DEADLEAD_FAIL');
    		else
    			$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&message=TO_DEADLEAD_SUCC');
    	}

    	// display a form for type change
    	$this->view->leadType = $this->leadType;
    	$this->view->leadId = $this->leadId;

    	// get the event date
    	// fetch event date if quote exists
    	$evDate = $db->fetchOne('SELECT event_date FROM quotes WHERE user_id = ' . $this->leadId . ' LIMIT 1');
    	$this->view->formData = array();
    	$this->view->formData['event_date'] = !empty($evDate) ? $evDate : '';
    }

    /**
     * Contact a lead
     *
     */
    public function contactAction() {
    	// check for lead type and lead id
		$this->leadType = $this->_request->getParam('type',null);
		$this->leadId = $this->_request->getParam('id',null);

		if (!$this->leadType) {
			$this->_redirect('error/show/?NO_LEAD_TYPE');
		}
		if (!$this->leadId) {
			$this->_redirect('error/show/?NO_LEAD_ID');
		}

		# sending did not went well
		if ($this->_request->get('contactFailed',null)) {
			$this->view->errorMessage = 'An error occured while processing your request. Please try again.';
		}
		if ($this->_request->get('contactIncompleteEmailData',null)) {
			$this->view->errorMessage = 'Incomplete email details provided. Please try again.';
		}
		if ($this->_request->get('contactIncompleteSmsData',null)) {
			$this->view->errorMessage = 'Incomplete details provided for sendind an SMS. Please try again.';
		}
		if ($this->_request->get('notEnoughCredits',null)) {
			$this->view->errorMessage = 'Not enough credits on the Clickatell. Please add some credits and try again.';
		}
		if ($this->_request->get('invalidSmsLogin',null)) {
			$this->view->errorMessage = 'Clickatell account credentials are incorrect. Please update them and try again.';
		}

		$db = Zend_Registry::get('db');

		if ($this->_request->isPost()) {
			$contactType = $this->_request->getParam('ctype','none');
			if (($contactType == 'none') || !in_array($contactType,array('Phone','Email'))) {
				// we do redirect to cancel the POST data
				$this->_redirect('profile/contact/?type='.$this->leadType.'&id='.$this->leadId.'&contactFailed=1');
			}
			# if we have to send email
			if ($contactType == 'Email') {
				$status = $this->_doEmailContact();
			}
			else {
				$phoneContactMethod = $this->_request->getParam('phone_type','null');
				if (!$phoneContactMethod || !in_array($phoneContactMethod,array('Phone Call','SMS'))) {
					// we do redirect to cancel the POST data
					$this->_redirect('profile/contact/?type='.$this->leadType.'&id='.$this->leadId.'&contactFailed=1');
				}
				$status = $this->_doPhoneContact($phoneContactMethod);
			}

			if (!$status) {
				$this->_redirect('profile/contact/?type='.$this->leadType.'&id='.$this->leadId.'&contactFailed=1');
			}

			// UPDATE FOLLOWUP
			if ($this->leadType == 'leads') {
				$db->update('leads',array('next_followup' => time() + FOLLOW_UP_STEP),'id = '.$this->leadId);
			}

			$this->_redirect('profile/?type='.$this->leadType.'&id='.$this->leadId.'&message=CONTACT_SUCC');
		}

		$this->view->leadData = $db->fetchRow('SELECT * FROM '.$this->leadType.' WHERE id = '.$this->leadId);
		//echo '<pre>'; print_r($this->view->leadData); die();
    	$this->view->leadType = $this->leadType;
    	$this->view->leadId = $this->leadId;

	$campusData = $db->fetchRow("SELECT name, email, contact_name FROM campuses
				     WHERE id = (SELECT campus_id FROM {$this->leadType}
                                                 WHERE id = {$this->leadId})");
	$this->view->senderName = $campusData['contact_name'] ? $campusData['contact_name'] : SENDER_NAME;
	$this->view->senderAddress = $campusData['email'] ? $campusData['email'] : SENDER_EMAIL_ADDRESS;

		// get emails
		$this->view->Emails = $db->fetchPairs('SELECT id,subject FROM emails WHERE campus_id=0');
		$this->view->Emails[0] = '(select)';
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
     * Output stock email details
     */
    public function loadstockemailAction()
    {
    	// disable layout
    	$this->_helper->layout()->disableLayout();

    	$EmailData = array('subject' => '',
    						'body' => '');

    	$EmailID = $this->_request->getParam('id');
    	if (!empty($EmailID)) {
    		$db = Zend_Registry::get('db');
			// get email
			$EmailData = $db->fetchRow('SELECT subject,body FROM emails WHERE id='.$EmailID);
    	}

    	$this->view->EmailData = Zend_Json::encode($EmailData);
    }
    
    /**
     * Display form & add equipment to the equote
     */
    public function quoteAddEquipmentAction()
    {
    	$this->_helper->layout()->disableLayout();
    	
    	$quoteId = $this->_request->getParam('quoteId', null);
    	
    	if (empty($quoteId)) {
    		echo 'Invalid request: no quote selected'; exit;
    	}
    	
    	$db = Zend_Registry::get('db');
    	
    	if ($this->_request->isPost()) {
    		$params = $this->_request->getParams();
    		if (!empty($params['type'])) {
    			if (!in_array($params['type'], array('table', 'service'))) {
    				echo 'Invalid equipment type';
    				exit;
    			}
    			
    			$dbData = array();
    			// same fields for both types
    			if (empty($params['quantity'])) { echo 'You must enter the quantity'; exit; }
    			elseif (!preg_match('/^[0-9]+$/', $params['quantity'])) { echo 'Invalid quantity. Only numbers allowed'; exit; }
    			$dbData['quantity'] = $params['quantity'];
    			
    			if ('table' == $params['type']) {
    				if (empty($params['w_dealer'])) { echo 'You must choose with/without dealer'; exit; }
    				elseif (!in_array($params['w_dealer'], array('yes', 'no'))) { echo 'Invalid dealer option. Only "yes" or "no" allowed'; exit; }
    				$dbData['extra'] = $params['w_dealer'];
    				
    				if (empty($params['pricet'])) { echo 'You must enter the table price'; exit; }
	    			else if (!preg_match('/^[0-9.]+$/', $params['pricet'])) { echo 'Invalid price. Only numbers and . separator allowed'; exit; }
	    			$dbData['price'] = MyLibs_Quote::myRound($params['pricet'], 2);
	    			
	    			if (empty($params['extra_hour_price'])) { $params['extra_hour_price'] = 0; }
	    			elseif (!preg_match('/[0-9.]+/', $params['extra_hour_price'])) { echo 'Invalid extra hour price. Only numbers and . separator allowed'; exit; }
	    			$dbData['extra_hour_price'] = MyLibs_Quote::myRound($params['extra_hour_price'], 2);
    			}
    			if ('service' == $params['type']) {
	    			if (empty($params['hours'])) { $params['hours'] = 0; }
	    			if (!preg_match('/^[0-9.]+$/', $params['hours'])) { echo 'Invalid additional hours value. Only numbers allowed'; exit; }
	    			$dbData['extra'] = $params['hours'];
	    			
	    			if (empty($params['prices'])) { echo 'You must enter the service price'; exit; }
	    			else if (!preg_match('/^[0-9.]+$/', $params['prices'])) { echo 'Invalid price. Only numbers and . separator allowed'; exit; }
	    			$dbData['price'] = MyLibs_Quote::myRound($params['prices'], 2);
    			}
    			
    			if (empty($params['id'])) { echo 'Invalid equipment item selected'; exit; }
    			$dbData['equipment_id'] = $params['id'];
    			
    			// fetch the old equipments
    			$currEquip = $db->fetchOne("SELECT equipment FROM quotes WHERE id = {$quoteId}");
    			if (empty($currEquip)) {
    				$currEquipArr = array();
    			} else {
    				$currEquipArr = Zend_Json::decode($currEquip, true);
    			}
    			$currEquipArr[] = $dbData;
    			$currEquipArr = array_values($currEquipArr);
    			$newEquip = Zend_Json::encode($currEquipArr);
    			$insertData = array('equipment' => $newEquip);
    			
    			if (!$db->update('quotes', $insertData, "id = {$quoteId}")) {
    				echo 'Could not add the equipment'; exit;
    			}
    			echo 'OK'; exit;
    		} else {
    			echo 'Invalid equipment type';
    			exit;
    		}
    	}
    	
    	// get the market id from the quote info
    	$sql = "SELECT details FROM quotes WHERE id = {$quoteId}";
    	$details = $db->fetchOne($sql);
    	if (empty($details)) {
    		echo 'Invalid request: no quote info'; exit;
    	}
    	$detailsArr = Zend_Json::decode($details, true);
    	if (!count($detailsArr)) {
    		echo 'Invalid request: no quote info'; exit;
    	}
    	$campusId = $detailsArr['campus_id'];
   		if (empty($campusId)) {
    		echo 'Invalid request: no quote info'; exit;
    	}
    	// fetch tables
    	$sql = "SELECT id, name FROM equipments WHERE type='table' AND id IN (SELECT equipment_id FROM `campus-equipment` WHERE campus_id = {$campusId})";
    	$this->view->tables = $db->fetchPairs($sql);
    	// fetch services
    	$sql = "SELECT id, name FROM equipments WHERE type='service' AND id IN (SELECT equipment_id FROM `campus-equipment` WHERE campus_id = {$campusId})";
    	$this->view->services = $db->fetchPairs($sql);
    	
    	$this->view->quoteId = $quoteId;
    }
    
    /**
     * Output the equipment added to a certain quote
     */
    public function quoteGetEquipmentAction()
    {
    	$this->_helper->layout()->disableLayout();
    	
    	$this->view->quoteId = $this->_request->getParam('id');
    	$this->view->equipments = array();
    	if (!empty($this->view->quoteId)) {
    		$db = Zend_Registry::get('db');
    		$equipment = $db->fetchOne("SELECT equipment FROM quotes WHERE id = {$this->view->quoteId}");
    		if (!empty($equipment)) {
    			$equipment = Zend_Json::decode($equipment, true);
    			if (count($equipment)) {
    				foreach ($equipment as $key => $step) {
    					$eqName = $db->fetchOne("SELECT name FROM equipments WHERE id = {$step['equipment_id']}");
    					$tmpArr = array();
    					$tmpArr['key'] = $key;
    					$tmpArr['name'] = $eqName;
    					$tmpArr['quantity'] = $step['quantity'];
    					$tmpArr['extra'] = $step['extra'];
    					
    					if ($tmpArr['extra'] === 'yes') {
    						$tmpArr['extra'] = 'W/ dealer';
    					} elseif ($tmpArr['extra'] === 'no') {
    						$tmpArr['extra'] = 'W/O dealer';
    					} else {
    						$tmpArr['extra'] .= ' hours';
    					}
    					
    					$this->view->equipments[] = $tmpArr;
    				}
    			}
    		}
    	}
    }
    
    /**
     * Get equipment price(s)
     */
    public function getEquipmentPriceAction()
    {
    	$this->_helper->layout()->disableLayout();
    	$this->_helper->viewRenderer->setNoRender();
    	
    	$quoteId = $this->_request->getParam('quoteId', 0);
    	$eqId = $this->_request->getParam('id', 0);
    	$wDealer = $this->_request->getParam('w_dealer', 'no');
    	
    	if (empty($quoteId) || empty($eqId)) {
    		echo Zend_Json::encode(array('fail', 'Invalid request. Could not get equipment price(s)')); exit;
    	}
    	
    	$db = Zend_Registry::get('db');
    	
    	$qDetails = $db->fetchOne("SELECT details FROM quotes WHERE id = {$quoteId}");
    	if (!empty($qDetails)) {
    		$qDetailsArr = Zend_Json::decode($qDetails, true);
    		if (count($qDetailsArr)) {
    			if (!empty($qDetailsArr['campus_id'])) {
    				$campusId = $qDetailsArr['campus_id'];
    				// get the prices
    				// get the event date
    				$eventDate = $db->fetchOne("SELECT event_date FROM quotes WHERE id = {$quoteId}");
    				if (empty($eventDate)) {
    					echo Zend_Json::encode(array('fail', 'Could not get equipment price(s). You must choose the event date first.')); exit;
    				}
    				
	    			// search and see if the event date is in the special-prices list
					$mysqlEventDate = date('Y-m-d', strtotime($eventDate));
					$eqPrices = $db->fetchRow("SELECT t1.*, t2.equipment_id FROM `special-prices` t1 JOIN `campus-equipment` t2 ON t1.`campus_equipment_id` = t2.id WHERE t1.campus_id = {$campusId} AND t2.equipment_id = {$eqId} AND '{$mysqlEventDate}' BETWEEN date_start AND date_end");
					
					// get normal prices
					$dName = date('D', strtotime($mysqlEventDate));
					if ('Sat' == $dName || 'Sun' == $dName) {
						$normalPrices = $db->fetchRow("SELECT * FROM `weekend-prices` WHERE campus_id = {$campusId} AND equipment_id = {$eqId}");
					} else {
						$normalPrices = $db->fetchRow("SELECT * FROM `campus-equipment` WHERE campus_id = {$campusId} AND equipment_id = {$eqId}");
					}
					if (empty($normalPrices)) {
						echo Zend_Json::encode(array('fail', 'prices internal error')); exit;
					}
					
					if (empty($eqPrices)) { // no special prices
						$eqPrices = $normalPrices;
					}
    				
					if ('no' == $wDealer) {
						$outPrices = array($eqPrices['price'], $eqPrices['extra_hour_price']);
					} else {
						$outPrices = array($eqPrices['price_dealer'], $eqPrices['extra_hour_price']);
					}
    				
    				echo Zend_Json::encode($outPrices); exit;
    				
    			} else {
    				echo Zend_Json::encode(array('fail', 'Could not get equipment price(s). You must choose the market first.')); exit;
    			}
    		}
    	}
    }
    
    /**
     * Remove equipment from quote
     */
    public function quoteRemEquipmentAction()
    {
    	$this->_helper->layout()->disableLayout();
    	$this->_helper->viewRenderer->setNoRender();
    	
    	$quoteId = $this->_request->getParam('qId');
    	$eqKey = $this->_request->getParam('key', -1);
    	
    	if (empty($quoteId) || $eqKey == -1) {
    		echo 'Invalid request'; exit;
    	}
    	
    	$db = Zend_Registry::get('db');
    	$equipment = $db->fetchOne("SELECT equipment FROM quotes WHERE id = {$quoteId}");
    	if (!empty($equipment)) {
    		$equipmentArr = Zend_Json::decode($equipment, true);
    		if (count($equipmentArr)) {
    			if (!empty($equipmentArr[$eqKey])) {
    				unset($equipmentArr[$eqKey]);
    				$equipmentArr = array_values($equipmentArr);
    				
    				$newEquipment = Zend_Json::encode($equipmentArr);
    				$db->update('quotes', array('equipment' => $newEquipment), "id = {$quoteId}");
    			}
    		}
    	}
    }

    /**
     * Send Email
     *
     */
    protected function _doEmailContact() {
    	$contactInfo = array();
    	$contactInfo['emailSender'] = ($this->_request->getParam('email_sender',null)) ? $this->_request->getParam('email_sender',null) : SENDER_EMAIL_ADDRESS;
    	$contactInfo['emailSenderName'] = ($this->_request->getParam('email_sender_name',null)) ? $this->_request->getParam('email_sender_name',null) : SENDER_NAME;
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
		     ->setFrom($contactInfo['emailSender'], $contactInfo['emailSenderName'])
		     ->addTo($contactInfo['emailAddres'])
		     ->setSubject($contactInfo['emailSubject']);

		if (!$mail->send()) {
			return false;
		}

		// update the communication log for this user
		$this->_updateCommunicationLog('Email',$contactInfo['emailMessage']);

		return true;
    }

    /**
     * Send SMS or record a phone call or phone call attempt
     *
     */
    protected function _doPhoneContact($method) {
    	$contactInfo = array();

    	if ($method == 'Phone Call') { // JUST SAVE THE PHONE CALL RECORD
    		if ('Yes' == $this->_request->getParam('attempt','No'))
				return $this->_updateCommunicationLog('Phone Call Attempt',$this->_request->getParam('attempt_comments','None'));
			else
				return $this->_updateCommunicationLog('Phone Call',$this->_request->getParam('attempt_comments','None'));
    	}
    	else { // SEND SMS
    		$mobileNumber = $this->_request->getParam('mobile_number',null);
    		$smsMessage = $this->_request->getParam('sms_message',null);

	    	if (!$mobileNumber || !$smsMessage) {
	    		// we do redirect to cancel the POST data
	    		$this->_redirect('profile/contact/?type='.$this->leadType.'&id='.$this->leadId.'&contactIncompleteSmsData=1');
	    	}

    		require SITE_PATH.'/library/Sms/sms.class.php';
    		$smsObj = new sms(SMS_APP_ID);
    		// auth with clickatell
    		$r = $smsObj->auth(SMS_USER,SMS_PASSWORD);
    		if (strcmp($r, "OK") != 0) {
    			$this->_redirect('profile/contact/?type='.$this->leadType.'&id='.$this->leadId.'&invalidSmsLogin=1');
    		}
    		// check balance
    		if ($smsObj->getbalance() < 5) {
    			$this->_redirect('profile/contact/?type='.$this->leadType.'&id='.$this->leadId.'&notEnoughCredits=1');
    		}
    		// send text message
    		$r = $smsObj->send($mobileNumber,$smsMessage);
    		if (strcmp($r, "OK") != 0) {
    			$this->_redirect('profile/contact/?type='.$this->leadType.'&id='.$this->leadId.'&contactFailed=1');
    		}
    		// update communication log
    		$this->_updateCommunicationLog('SMS',$smsMessage);

    		return true;
    	}
    }

    /**
     * Update communication log after succesfully sent/saved a contact
     *
     */
    protected function _updateCommunicationLog($commType, $message) {
 		$db = Zend_Registry::get('db');

		$commDataStr = $db->fetchRow('SELECT log FROM communication WHERE user_type = \''.$this->leadType.'\' AND user_id = '.$this->leadId);
		if ($commDataStr == 0) {
			$newCommDataEntry[0] = array('date_time' => time(), 'sales_person' => Zend_Auth::getInstance()->getStorage()->read()->id, 'type' => $commType, 'id' => 1, 'message' => $message);
			return $db->insert('communication',array(
									'user_id' => $this->leadId,
									'user_type' => $this->leadType,
									'log' => Zend_Json::encode($newCommDataEntry)
									)
						);
		}

		$commDataStr = $commDataStr['log'];

		$commDataArr = Zend_Json::decode($commDataStr);
		$commDataArr = (is_array($commDataArr)) ? $commDataArr : array();

		$newCommDataEntry[] = array('date_time' => time(), 'sales_person' => Zend_Auth::getInstance()->getStorage()->read()->id, 'type' => $commType, 'id' => count($commDataArr)+1, 'message' => $message);
		$commDataArr[] = $newCommDataEntry[0];
		$commDataStr = Zend_Json::encode($commDataArr);

		return $db->update('communication',array('log' => $commDataStr),'user_type = \''.$this->leadType.'\' AND user_id = '.$this->leadId);
    }
}
