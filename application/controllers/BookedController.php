<?php

class BookedController extends Zend_Controller_Action
{
	const ADD_ENROLL_SUCC = 'The booking was succesfully added to the system. It will appear in the bookings list after the administrator approves it.';
	const DEL_ENROLL_SUCC = 'A request for removal was succesfully sent. The booking will be removed from the system once the administratot approves it.';
	const DEL_ENROLL_FAIL = 'The booking was not removed from the system. Please try again.';
	const CHANGE_STATUS = 'A request for status change from Lead to Booking has been sent. The new booking will appear in the bookings list after the administrator approves it.';

	protected $_model;

	function init() {

	}

	function preDispatch()
	{
		$auth = Zend_Auth::getInstance();
		if (!$auth->hasIdentity()) {
			$this->_redirect('auth/login');
		}

		$this->_getModel();
	}

    public function indexAction()
    {
		# check if we have some messages trough get
		if ($this->_request->get('message',null)) {
			$this->view->succesMessage = constant('self::'.$this->_request->getParam('message'));
		}
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}

		 $forBaseLink = '';

		# build enrollments table
		// configuration for the output table
		$userIsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;

		$tableCaption = 'Booked';
		if ($this->_request->__isset('cold')) { $tableCaption = 'Booked Cold Leads'; $forBaseLink = 'cold&'; }
		if ($this->_request->__isset('dead')) { $tableCaption = 'Booked Dead Leads'; $forBaseLink = 'dead&'; }
		if ($this->_request->__isset('due')) { $tableCaption = 'Due Payment'; $forBaseLink = 'due&'; }
		if ($this->_request->__isset('full')) { $tableCaption = 'Full Paid'; $forBaseLink = 'full&'; }

		$this->view->tableConfig = array (
			'attributes' => array(
								'width' => '90%',
								'style' => 'margin:auto',
								'class' => 'tblStyle1'
							),
			'caption' => $tableCaption,
			'headers' => array(
								array('Name','20%'),
								array('Email Address','21%'),
								array('Market','18%'),
								array('Event Date','12%'),
								array('City','16%'),
								array('Total amount','8%'),
								array('Action','5%')
							),
			'alteredFields' => array (
								'5' => 'php:"$"."%field%";',
								'6' => '<a href="'.$this->view->url(array('controller' => 'profile'),'default',true).'/?type=booked&id=%field%" style="text-decoration:none;border:0;margin-right:15px;"><img src="'.SITE_ROOT.'/images/icons/profile_small.png" style="border:0" alt="Profile" /></a>'
										. ((!$userIsAdmin)
											? '<a href="#" style="text-decoration:none;border:0;" onclick="showDeleteReason(\''.$this->view->url(array('controller' => 'booked', 'action' => 'delete')).'\',\'%field%\')"><img src="'.SITE_ROOT.'/images/icons/delete_small.png" style="border:0" /></a>'
											: '<a href="'.$this->view->url(array('controller' => 'booked', 'action' => 'delete')).'?id=%field%" style="text-decoration:none;border:0;" onclick="return doCancelConfirm()"><img src="'.SITE_ROOT.'/images/icons/delete_small.png" style="border:0" /></a>'
										)
							)
			);

		// pagination used variables
		$resultOnPage = $this->_request->getParam('results',DEFAULT_RESULTS_PER_PAGE);
		$pageNo = $this->_request->getParam('page',1);

		/****************************
		 * UPDATE 13.04.2009
		 * Limit access to enrollments only from schools selected for this user
		*****************************/
		// SQL wheres
		$wheres = array();
		if ($this->_request->__isset('my')) $wheres[] = 't1.sales_person_id = '.Zend_Auth::getInstance()->getStorage()->read()->id;
		if ($this->_request->__isset('cold')) $wheres[] = 't1.cold_lead = 1';
		elseif ($this->_request->__isset('dead')) $wheres[] = 't1.cold_lead = 2';
		else $wheres[] = 't1.cold_lead = 0';

		if ($this->_request->__isset('due')) $wheres[] = 't1.total_amount > (SELECT COALESCE((SUM(amount)),0) FROM payments WHERE user_id = t1.id AND user_type=\'booked\')';
		if ($this->_request->__isset('full')) $wheres[] = 't1.total_amount <= (SELECT COALESCE((SUM(amount)),0) FROM payments WHERE user_id = t1.id AND user_type=\'booked\')';

		# leads data
		$this->view->tableData = $this->_model->fetch($pageNo,$resultOnPage,$wheres);
		# get the total number of rows
		$totalRows = $this->_model->fetchTotalRows();
		// END UPDATE

		// build pagination
		$this->view->baseLink = $this->view->url(array('controller' => 'booked'));
		$this->view->baseLink.= '?';
		$this->view->baseLink.= $forBaseLink;

		$this->view->paginationConfig = array(
					'base_url' => $this->view->baseLink,
					'num_items' => $totalRows,
					'per_page' => $resultOnPage,
					'page_no' => $pageNo,
					'class' => 'paginationDiv'
				);

		$this->view->perPageLinksClass = 'perPageLinks';
    }


    public function addAction()
    {
    	// add lead
    	$this->view->errors = '';
    	$this->view->formData = array();
    	$db = Zend_Registry::get('db');

    	if ($this->_request->isPost()) {
    		// validate the input
    		$validators = array(
		    		'*' => array(
			    		'allowEmpty' => true
		    		),
		    		'first_name' => array(
			    		'Alpha',
			    		'messages' => 'You need to enter a valid name'
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
			    		'messages' => 'You need to enter a valid email address'
		    		),
		    		'campus_id' => array(
			    		'Digits',
			    		'messages' => 'Invalid market'
		    		),
					'total_amount' => array()
	    		);

    		$enrollmentData = new Zend_Filter_Input(array('*'=>'StringTrim'),$validators,$this->_request->getPost());

    		if ($enrollmentData->hasInvalid() || $enrollmentData->hasMissing()) { // output errors messages
    			// repopulate the form
    			$this->view->formData = $this->_request->getPost();

    			$errorMessages = $enrollmentData->getMessages();
    			foreach ($errorMessages as $field => $error) {
    				if ($field == 'first_name')
    					$this->view->errors[] = 'You need to enter a name';
    				else
    					$this->view->errors[] = array_pop($error);
    			}
    		}
    		else {
				# user level
				$userIsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;

    			// prepare the enrollment info for insertion into the database
    			$dbData = $enrollmentData->getUnescaped();

    			$dbData['date_added'] = time();
    			// get the user id who added the lead from the session
    			$dbData['sales_person_id'] = Zend_Auth::getInstance()->getStorage()->read()->id;
    			$dbData['approval'] = empty($userIsAdmin) ? 'system' : '1';
    			$outputMode = !empty($dbData['output']) ? $dbData['output'] : 'n';
    			unset($dbData['button']);
    			unset($dbData['output']);

    			// add the enrollment to the database
    			if (!$db->insert('booked',$dbData)) {
    				$this->view->errors[] = 'The booking could not be saved into the database';
    			}
    			else {
    				$lastInsertId = $db->lastInsertId();
					$db->insert('communication',array(
									'user_id' => $lastInsertId,
									'user_type' => 'booking',
									'log' => '')
								);

    				$this->_helper->viewRenderer->setNoRender();
					if ($outputMode == 'n') {
    					$this->_redirect('booked/?message=ADD_ENROLL_SUCC');
					}
					else {
						$this->_redirect('profile/quote/?type=booked&id='.$lastInsertId.'&message=ADD_ENROLL_SUCC');
					}
    			}
    		}
    	}


    	// read the campuses from the database for select options
    	$this->view->campuses = $db->fetchPairs('SELECT id,name FROM campuses');
    	$this->view->campuses['select']= '(select)';
    }

    /**
     * Delete an enrollment
     */
	public function deleteAction() {
		# user level
		$userIsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;
		$removalReason = $this->_request->getParam('reason','');
		$leadId = $this->_request->getParam('id',null);

		if (!$leadId) {
			$this->_redirect('booked?error=DEL_ENROLL_FAIL');
		}
		$db = Zend_Registry::get('db');

		# if user is waiting for approval
		if ($db->fetchOne('SELECT approval FROM booked WHERE id = '.$leadId) != '1') {
			$this->_redirect('profile/?type=booked&id='.$leadId.'&error=APPROVAL_NO_DELETE');
		}

		# if user is not admin
		if (!$userIsAdmin) {
			$db->update('booked',array('approval' => 'remove|'.$removalReason),'id = '.$leadId);
		}
		else {
			if (!$db->delete('booked','id = '.$leadId)) {
				$this->_redirect('booked?error=DEL_ENROLL_FAIL');
			}
			$db->delete('communication',array('user_id = '.$leadId, 'user_type = \'booked\''));
		}
		$this->_redirect('booked?message=DEL_ENROLL_SUCC');
	}


	protected function _getModel()
    {
        if (null === $this->_model) {
            require_once APPLICATION_PATH . '/models/Booked.php';
            $this->_model = new Model_Booked();
        }
        return $this->_model;
    }
}