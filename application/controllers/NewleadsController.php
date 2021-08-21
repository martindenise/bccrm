<?php

class NewleadsController extends Zend_Controller_Action
{
	const CONF_LEAD_SUCC = 'The new lead was succesfully added to the system as a lead.';
	const CONF_LEAD_FAIL = 'The new lead was not succesfully confirmed. Please try again.';
	const DEL_LEAD_SUCC  = 'The new lead was succesfully removed from the system.';
	const DEL_LEAD_FAIL  = 'The new lead was not removed from the system. Please try again.';
	const STOCK_EMAIL_SENT = 'The stock email has been sent.';
	const INVALID_LEAD	 = 'Invalid new lead.';
	
	protected $_model;

	function __call($method,$args)
	{
/*		if ('stockAction' == $method) {
			$newLeadId = $this->_request->getParam('id',null);
			if ($newLeadId) {
				$this->_sendStockEmail($newLeadId);
			}
			$this->_redirect('newleads/?message=STOCK_EMAIL_SENT');
		}*/
	}

	function preDispatch()
	{
		$auth = Zend_Auth::getInstance();
		if (!$auth->hasIdentity()) {
			$this->_redirect('auth/login');
		}
	}

	/**
	 * Index Action - show new leads table
	 *
	 */
    public function indexAction()
    {

		# check if we have some messages trough get
		if ($this->_request->get('message',null)) {
			$this->view->succesMessage = constant('self::'.$this->_request->getParam('message'));
		}
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}

		# build new leads table
		// configuration for the output table
		$this->view->tableConfig = array (
			'attributes' => array(
								'width' => '90%',
								'style' => 'margin:auto',
								'class' => 'tblStyle1'
							),
			'caption' => 'New Contacts',
			'headers' => array(
								array('Name','20%'),
								array('Email Address','23%'),
								array('Market','18%'),
								array('Add Date','12%'),
								array('Sales Person','16%'),
								array('Accept','6%'),
								array('Action','5%')
							),
			'alteredFields' => array (
								'3' => 'php:date("m/d/Y",%field%)',
								'5' => '<a href="'.$this->view->url(array('controller' => 'newleads', 'action' => 'confirm')).'?id=%field%" style="text-decoration:none;border:0;"><img src="'.SITE_ROOT.'/images/icons/ok_small.png" style="border:0" /></a>',
								'6' => '<a href="'.$this->view->url(array('controller' => 'profile'),'default',true).'/?type=new_leads&id=%field%" style="text-decoration:none;border:0;margin-right:10px;"><img src="'.SITE_ROOT.'/images/icons/profile_small.png" style="border:0" alt="Profile" /></a>
										<a href="'.$this->view->url(array('controller' => 'newleads', 'action' => 'stock'),'default',true).'?id=%field%" style="text-decoration:none;border:0;margin-right:10px;"><img src="'.SITE_ROOT.'/images/icons/contact_small.png" style="border:0" alt="Profile" /></a>
										<a href="'.$this->view->url(array('controller' => 'newleads', 'action' => 'delete')).'?id=%field%" style="text-decoration:none;border:0;" onclick="return doDeleteConfirm()"><img src="'.SITE_ROOT.'/images/icons/delete_small.png" style="border:0" /></a>'
							)
			);

		// pagination used variables
		$resultOnPage = $this->_request->getParam('results',DEFAULT_RESULTS_PER_PAGE);
		$pageNo = $this->_request->getParam('page',1);

		// get the new leads from the database so they'll fit our table configuration
		$model = $this->_getModel();
		$this->view->tableData = $model->fetchPage($pageNo,$resultOnPage);

		# get the total number of rows
		$totalRows = $model->fetchTotalRows();

		// build pagination
		$this->view->baseLink = $this->view->url(array('controller' => 'newleads'));
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


    /**
     * Confirm new lead
     */
    public function confirmAction() {
    	$db = Zend_Registry::get('db');
    	$this->view->errors = array();

    	$leadId = $this->_request->getParam('id',null);
		if (!$leadId) {
			$this->_redirect('newleads?error=CONF_LEAD_FAIL');
		}

		# is the form is submitted
		if ($this->_request->isPost()) {
			// validate the input
			$validators = array(
					'last_name' => array(
						'allowEmpty' => true
					),
					'first_name' => array(
						'Alpha',
						'messages' => 'You need to enter a valid name'
					),
					'sales_person_id' => array(
						'Digits',
						'messages' => 'Invalid sales person'
					)
				);

			$leadData = new Zend_Filter_Input(array('*'=>'StringTrim'),$validators,$this->_request->getPost());

			if ($leadData->hasInvalid() || $leadData->hasMissing()) { // output errors messages
				$errorMessages = $leadData->getMessages();
				foreach ($errorMessages as $field => $error) {
					if ($field == 'first_name')
						$this->view->errors[] = 'You need to enter a name';
					else
						$this->view->errors[] = array_pop($error);
				}
				unset($leadData);
			}
			else {
				//$dbData = $leadData->getUnescaped();
				$dbData = $db->fetchRow('SELECT * FROM new_leads WHERE id = '.$leadId);
				$dbData['first_name'] = $leadData->getEscaped('first_name');
				$dbData['last_name'] = $leadData->getEscaped('last_name');
				$dbData['sales_person_id'] = $leadData->getEscaped('sales_person_id');
				$dbData['next_followup'] = time() + FOLLOW_UP_STEP;
				$dbData['approval'] = '1';
				unset($dbData['id']);

				if (!$db->insert('leads',$dbData))
					$this->_redirect('newleads?error=CONF_LEAD_FAIL');
				else {
					$newLeadId = $db->lastInsertId();
					$db->delete('new_leads','id = '.$leadId);
					$db->insert('communication',array(
									'user_id' => $newLeadId,
									'user_type' => 'leads',
									'log' => '')
						);

					//$this->_sendStockEmail($newLeadId);
					$this->_redirect('newleads?message=CONF_LEAD_SUCC');
				}
			}
		}

		$leadData = $db->fetchRow('SELECT id,first_name,last_name,sales_person_id FROM new_leads WHERE id = '.$leadId);

		# read sales persons from database
		$this->view->leadData = $leadData;
		$this->view->sales_persons = $db->fetchPairs('SELECT id,name FROM users WHERE username != \'none\'');
		$this->view->sales_persons['select']= '(select)';
		$this->view->selected_sales_person = ($this->view->leadData['sales_person_id'] != 0) ? $this->view->leadData['sales_person_id'] : 'select';
    }

    /**
     * Delete a new lead
     */
	public function deleteAction() {
		$leadId = $this->_request->getParam('id',null);
		if (!$leadId) {
			$this->_redirect('newleads?error=DEL_LEAD_FAIL');
		}
		$db = Zend_Registry::get('db');
		if (!$db->delete('new_leads','id = '.$leadId)) {
			$this->_redirect('newleads?error=DEL_LEAD_FAIL');
		}
		$this->_redirect('newleads?message=DEL_LEAD_SUCC');
	}
	
	/**
	 * Personalize & Send a stock email
	 */
	public function stockAction()
	{
		$db = Zend_Registry::get('db');
		
		$leadId = $this->_request->getParam('id',null);
		if (!$leadId) {
			$this->_redirect('newleads?error=INVALID_LEAD');
		}
		
		// lead data
		$select = $db->select()
				     ->from(array('t1' => 'new_leads'),
				     		array('{first_name}' => 'first_name','{last_name}' => 'last_name', '{email_address}' => 'email'))
				     ->join(array('t2' => 'users'),
				     		't2.id = t1.sales_person_id',
				     		array('{sales_person_name}' => 'name',
				     			  '{sales_person_email}' => 'email'))
				     ->join(array('t3' => 'campuses'),
				     		't3.id = t1.campus_id',
				     		array('{campus_id}' => 'id','{market_name}' => 'name')
				     		)
				     ->where('t1.id = '.$leadId);
		$leadData = $select->query()->fetchAll();
		$leadData = $leadData[0];
		
		$campusId = $leadData['{campus_id}'];
		
		if ($this->_request->isPost()) {
			$emailData = $this->_request->getPost();
			unset($emailData['button']);

			//echo '<pre>'; print_r($emailData); exit;
			$this->_sendStockEmail($leadId, $emailData);
			
			// change the status for the user
			$dbData = $db->fetchRow('SELECT * FROM new_leads WHERE id = '.$leadId);
			$dbData['sales_person_id'] = Zend_Auth::getInstance()->getStorage()->read()->id;
			$dbData['next_followup'] = time() + FOLLOW_UP_STEP;
			$dbData['approval'] = '1';
			unset($dbData['id']);

			if (!$db->insert('leads',$dbData))
				$this->_redirect('newleads?error=CONF_LEAD_FAIL');
			else {
				$newLeadId = $db->lastInsertId();
				$db->delete('new_leads','id = '.$leadId);
				$db->insert('communication',array(
								'user_id' => $newLeadId,
								'user_type' => 'leads',
								'log' => '')
					);

				//$this->_sendStockEmail($newLeadId);
				$this->_redirect('newleads?message=CONF_LEAD_SUCC');
			}
			
			$this->_redirect('newleads/?message=STOCK_EMAIL_SENT');
		}

		if (!empty($campusId)) {
			// build sql query
			$select = $db->select()
						 ->calcFoundRows(true)
						 ->from(array('t1' => 'emails'),
						 		array("id",
						 			  "subject",
						 			  "body"
						 		))
						 ->join(array('t2' => 'campuses'),
						 		't2.id = t1.campus_id',
						 		array("name"))
						 ->where('t1.campus_id = '.$campusId);


			$this->view->emailData = $select->query()->fetchAll();
			$this->view->emailData = $this->view->emailData[0];
			$this->view->id = $leadId;
		}
	}

	/**
	 * Send the stock email
	 */
	protected function _sendStockEmail($leadId, $emailData) {
		if (!$leadId)
			return false;

		$db = Zend_Registry::get('db');
		// lead data
		$select = $db->select()
				     ->from(array('t1' => 'new_leads'),
				     		array('{first_name}' => 'first_name','{last_name}' => 'last_name', '{email_address}' => 'email'))
				     ->join(array('t2' => 'users'),
				     		't2.id = t1.sales_person_id',
				     		array('{sales_person_name}' => 'name',
				     			  '{sales_person_email}' => 'email'))
				     ->join(array('t3' => 'campuses'),
				     		't3.id = t1.campus_id',
				     		array('{campus_id}' => 'id','{market_name}' => 'name')
				     		)
				     ->where('t1.id = '.$leadId);
		$leadData = $select->query()->fetchAll();
		$leadData = $leadData[0];

		// email data
		$emailData['body'] = str_replace(array_keys($leadData),array_values($leadData),$emailData['body']);

		$mail = new Zend_Mail();
		$mail->setBodyText('You need an HTML compliant email client to see this message')
			 ->setBodyHtml($emailData['body'])
		     ->setFrom(SENDER_EMAIL_ADDRESS, SENDER_NAME)
		     ->addTo($leadData['{email_address}'])
		     ->setSubject($emailData['subject']);

		if (!$mail->send()) {
			return false;
		}
		return true;
	}


    protected function _getModel()
    {
        if (null === $this->_model) {
            require_once APPLICATION_PATH . '/models/Newleads.php';
            $this->_model = new Model_Newleads();
        }
        return $this->_model;
    }

}