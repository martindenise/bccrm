<?php

class LeadsController extends Zend_Controller_Action
{
	const ADD_LEAD_SUCC = 'The lead was succesfully added to the system. It will appear in the leads list once the operator approves it.';
	const DEL_LEAD_SUCC = 'A request for removal was succesfully sent. The lead will be removed from the system once the operator approves it.';
	const DEL_LEAD_FAIL = 'The lead was not removed from the system. Please try again.';

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

		# build new leads table
		// configuration for the output table
		$userIsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;

		$tableCaption = 'Leads';
		if ($this->_request->__isset('my')) { $tableCaption = 'My Leads'; $forBaseLink = 'my&'; }
		if ($this->_request->__isset('cold')) { $tableCaption = 'Cold Leads'; $forBaseLink = 'cold&'; }
		if ($this->_request->__isset('dead')) { $tableCaption = 'Dead Leads'; $forBaseLink = 'dead&'; }

		$this->view->tableConfig = array (
			'attributes' => array(
								'width' => '90%',
								'style' => 'margin:auto',
								'class' => 'tblStyle1'
							),
			'caption' => $tableCaption,
			'headers' => array(
								array('Name','20%'),
								array('Email Address','20%'),
								array('Market','17%'),
								array('Add Date','12%'),
								array('Sales Person','16%'),
								array('Next Follow-up','8%'),
								array('Action','7%')
							),
			'alteredFields' => array (
								'3' => 'php:date("m/d/Y",%field%)',
								'5' => 'php:date("m/d/Y",%field%)',
								'6' => '<a href="'.$this->view->url(array('controller' => 'profile'),'default',true).'/?type=leads&id=%field%" style="text-decoration:none;border:0;margin-right:10px;"><img src="'.SITE_ROOT.'/images/icons/profile_small.png" style="border:0" alt="Profile" /></a>'
									   . '<a href="'.$this->view->url(array('controller' => 'profile', 'action' => 'contact'),'default',true).'?type=leads&id=%field%" style="text-decoration:none;border:0;margin-right:10px;"><img src="'.SITE_ROOT.'/images/icons/email_small.png" style="border:0" alt="Contact" /></a>'
									   . ((!$userIsAdmin)
											? '<a href="#" style="text-decoration:none;border:0;" onclick="showDeleteReason(\''.$this->view->url(array('controller' => 'leads', 'action' => 'delete')).'\',\'%field%\')"><img src="'.SITE_ROOT.'/images/icons/delete_small.png" style="border:0" /></a>'
											: '<a href="'.$this->view->url(array('controller' => 'leads', 'action' => 'delete')).'?id=%field%" style="text-decoration:none;border:0;" onclick="return doDeleteConfirm()"><img src="'.SITE_ROOT.'/images/icons/delete_small.png" style="border:0" /></a>')
							)
			);

		// pagination used variables
		$resultOnPage = $this->_request->getParam('results',DEFAULT_RESULTS_PER_PAGE);
		$pageNo = $this->_request->getParam('page',1);

		// get the new leads from the database so they'll fit our table configuration
/*		$db = Zend_Registry::get('db');
		$db->setFetchMode(Zend_Db::FETCH_NUM);*/

		// build sql query
/*		$select = $db->select()
					 ->calcFoundRows(true)
					 ->from(array('t1' => 'leads'),
					 		array("CONCAT_WS(' ',first_name,last_name)","email","t2.name","date_added","t3.name","next_followup","id"))
					 ->join(array('t2' => 'campuses'),
					 		't1.campus_id = t2.id',
					 		array())
					 ->join(array('t3' => 'users'),
					 		't1.sales_person_id = t3.id',
					 		array())
					 ->where('t1.approval = 1')
					 ->limitPage($pageNo,$resultOnPage);*/

		//if ($this->_request->__isset('my')) $select->where('t1.sales_person_id = '.Zend_Auth::getInstance()->getStorage()->read()->id);
		//if ($this->_request->__isset('cold')) $select->where('t1.cold_lead = 1');
		//elseif ($this->_request->__isset('dead')) $select->where('t1.cold_lead = 2');
		//else $select->where('t1.cold_lead = 0');
		//$this->view->tableData = $select->query()->fetchAll();
		# get the total number of rows
		//$totalRows = $db->fetchOne('SELECT FOUND_ROWS()');

		/****************************
		 * UPDATE 13.04.2009
		 * Limit access to leads only from schools selected for this user
		*****************************/
		// SQL wheres
		$wheres = array();
		if ($this->_request->__isset('my')) $wheres[] = 't1.sales_person_id = '.Zend_Auth::getInstance()->getStorage()->read()->id;
		if ($this->_request->__isset('cold')) $wheres[] = 't1.cold_lead = 1';
		elseif ($this->_request->__isset('dead')) $wheres[] = 't1.cold_lead = 2';
		else $wheres[] = 't1.cold_lead = 0';

		# leads data
		$this->view->tableData = $this->_model->fetch($pageNo,$resultOnPage,$wheres);
		# get the total number of rows
		$totalRows = $this->_model->fetchTotalRows();
		// END UPDATE

		// build pagination
		$this->view->baseLink = $this->view->url(array('controller' => 'leads'));
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
		//$this->_helper->viewRenderer->setNoRender(true);
    	$leadType = $this->_request->getParam('type',null);
    	if (!$leadType) {
    		$this->_redirect('leads');
    	}
    	else {
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
						)
				);

				$leadData = new Zend_Filter_Input(array('*'=>'StringTrim'),$validators,$this->_request->getPost());

				if ($leadData->hasInvalid() || $leadData->hasMissing()) { // output errors messages
	    			// repopulate the form
	    			$this->view->formData = $this->_request->getPost();
					$errorMessages = $leadData->getMessages();
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

					// prepare the lead info for insertion into the database
					$dbData = $leadData->getUnescaped();
					$dbData['date_added'] = time();
					// get the user id who added the lead from the session
					$dbData['sales_person_id'] = Zend_Auth::getInstance()->getStorage()->read()->id;
					$dbData['approval'] = empty($userIsAdmin) ? 'system' : '1';

					unset($dbData['button']);

					switch ($leadType) {
						// new lead
						case 'newlead': {
								$tableName = 'new_leads';
								unset($dbData['approval']);
							} break;
						case 'lead': {
								$tableName = 'leads';
								$commSql = true;
								$dbData['next_followup'] = time() + FOLLOW_UP_STEP;
							} break;

					}

					// add the lead to the database
					if (!$db->insert($tableName,$dbData)) {
						$this->view->errors[] = 'The lead could not be saved into the database';
					}
					else {
						if ($commSql) {
							$db->insert('communication',array(
											'user_id' => $db->lastInsertId(),
											'user_type' => 'leads',
											'log' => '')
										);
						}
						// redirect to the index page
						$this->_helper->viewRenderer->setNoRender();
						$this->_redirect('leads?message=ADD_LEAD_SUCC');
					}
				}
			}


			// read the campuses from the database for select options
			$this->view->campuses = $db->fetchPairs('SELECT id,name FROM campuses');
			$this->view->campuses['select']= '(select)';

			// render the view corresponding to our lead type
			$this->view->addLeadForm = $this->view->render('leads/'.$leadType.'add.phtml');
    	}
    }

    /**
     * Delete a lead
     */
	public function deleteAction() {
		# user level
		$userIsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;
		$removalReason = $this->_request->getParam('reason','');
		$leadId = $this->_request->getParam('id',null);

		if (!$leadId) {
			$this->_redirect('leads?error=DEL_LEAD_FAIL');
		}

		$db = Zend_Registry::get('db');

		# if user is not admin
		if (!$userIsAdmin) {
			$db->update('leads',array('approval' => 'remove|'.$removalReason),'id = '.$leadId);
		}
		else {
			if (!$db->delete('leads','id = '.$leadId)) {
				$this->_redirect('leads?error=DEL_LEAD_FAIL');
			}
			$db->delete('communication',array('user_id = '.$leadId, 'user_type = \'lead\''));
		}
		$this->_redirect('leads?message=DEL_LEAD_SUCC');
	}


    public function followupsAction()
    {
		# build new leads table
		// configuration for the output table
		$this->view->tableConfig = array (
			'attributes' => array(
								'width' => '90%',
								'style' => 'margin:auto',
								'class' => 'tblStyle1'
							),
			'caption' => 'Follow Up Reminders',
			'headers' => array(
								array('Name','23%'),
								array('Email Address','24%'),
								array('Market','21%'),
								array('Add Date','15%'),
								array('Last Contact','12%'),
								array('Action','5%')
							),
			'alteredFields' => array (
								'3' => 'php:date("m/d/Y",%field%)',
								'4' => 'php:date("m/d/Y",%field%)',
								'5' => '<a href="'.$this->view->url(array('controller' => 'profile', 'action' => 'contact'),'default',true).'?type=leads&id=%field%" style="text-decoration:none;border:0;"><img src="'.SITE_ROOT.'/images/icons/email_small.png" style="border:0" title="Contact" /></a>
										<a href="'.$this->view->url(array('controller' => 'leads', 'action' => 'delete')).'?id=%field%" style="text-decoration:none;border:0; margin-left:15px;" onclick="return doDeleteConfirm()"><img src="'.SITE_ROOT.'/images/icons/delete_small.png" style="border:0" /></a>'
							)
			);

		// pagination used variables
		$resultOnPage = $this->_request->getParam('results',DEFAULT_RESULTS_PER_PAGE);
		$pageNo = $this->_request->getParam('page',1);

		// get the new leads from the database so they'll fit our table configuration
		$this->view->tableData = $this->_model->fetchFollowUps($pageNo,$resultOnPage);

		# get the total number of rows
		$totalRows = $this->_model->fetchTotalRows();

		// build pagination
		$this->view->baseLink = $this->view->url(array('controller' => 'leads', 'action' => 'followups'));
		$this->view->baseLink.= '?';

		$this->view->paginationConfig = array(
					'base_url' => $this->view->baseLink,
					'num_items' => $totalRows,
					'per_page' => $resultOnPage,
					'page_no' => $pageNo,
					'class' => 'paginationDiv'
				);

		$this->view->perPageLinksClass = 'perPageLinks';
    }

    protected function _getModel()
    {
        if (null === $this->_model) {
            require_once APPLICATION_PATH . '/models/Leads.php';
            $this->_model = new Model_Leads();
        }
        return $this->_model;
    }
}
