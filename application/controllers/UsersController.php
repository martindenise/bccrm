<?php


class UsersController extends Zend_Controller_Action
{
	const ADD_USER_SUCC = 'The user was succesfully added to the system.';
	const DEL_USER_SUCC = 'The user was succesfully removed from the system.';
	const DEL_USER_FAIL = 'The user was not removed from the system. Please try again.';
	const CHANGE_STATUS_FAIL = 'The user could not be changed. Please try again.';
	const CHANGE_STATUS_SUCC = 'The user was succesfully changed.';

	function preDispatch()
	{
		$auth = Zend_Auth::getInstance();
		if (!$auth->hasIdentity()) {
			$this->_redirect('auth/login');
		}
		if ($auth->getStorage()->read()->admin == 0) {
			$this->_redirect('index');;
		}
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

		# build new leads table
		// configuration for the output table
		$this->view->tableConfig = array (
			'attributes' => array(
								'width' => '75%',
								'style' => 'margin:auto',
								'class' => 'tblStyle1'
							),
			'caption' => 'Users',
			'headers' => array(
								array('Name','20%'),
								array('Email Address','23%'),
								array('Username','18%'),
								array('Password','12%'),
								array('Admin','16%'),
								array('Status','6%'),
								array('Action','5%')
							),
			'alteredFields' => array (
								'6' => '<a href="'.$this->view->url(array('controller' => 'users', 'action' => 'add')).'/?id=%field%" style="text-decoration:none;border:0;"><img src="'.SITE_ROOT.'/images/icons/edit_small.png" style="border:0" /></a>
									    <a href="'.$this->view->url(array('controller' => 'users', 'action' => 'delete')).'?id=%field%" style="text-decoration:none;border:0; margin-left: 10px;" onclick="return doDeleteConfirm()"><img src="'.SITE_ROOT.'/images/icons/delete_small.png" style="border:0" /></a>'
							)
			);

		// pagination used variables
		$resultOnPage = $this->_request->getParam('results',DEFAULT_RESULTS_PER_PAGE);
		$pageNo = $this->_request->getParam('page',1);

		// get the new leads from the database so they'll fit our table configuration
		$db = Zend_Registry::get('db');
		$db->setFetchMode(Zend_Db::FETCH_NUM);

		// build sql query
		$select = $db->select()
					 ->calcFoundRows(true)
					 ->from(array('t1' => 'users'),
					 		array("name",
					 			  "email",
					 			  "username",
					 			  "password",
					 			  "(CASE admin WHEN 1 THEN 'Yes' WHEN 0 THEN 'No' END)",
					 			  "active",
					 			  "id"
					 		))
					 ->where('id > 0')
					->order('id ASC')
					 ->limitPage($pageNo,$resultOnPage);

		$this->view->tableData = $select->query()->fetchAll();

		# map the array with a defined function to change active from 1 or 0 to image
		$this->view->tableData = array_map(array('UsersController','_mapActive'),$this->view->tableData);

		# get the total number of rows
		$totalRows = $db->fetchOne('SELECT FOUND_ROWS()');

		// build pagination
		$this->view->baseLink = $this->view->url(array('controller' => 'users'));
		$this->view->baseLink.= '?';

		$this->view->paginationConfig = array(
					'base_url' => $this->view->baseLink,
					'num_items' => $totalRows,
					'per_page' => $resultOnPage,
					'page_no' => $pageNo,
					'class' => 'paginationDiv'
				);

		$this->view->perPageLinksClass = 'perPageLinksUsers';
    }

    /**
     * Add user
     *
     */
    public function addAction()
    {
    	// add user
    	$this->view->errors = '';
    	$db = Zend_Registry::get('db');

    	if ($this->_request->isPost()) {
    		// validate the input
    		$validators = array(
		    		'name' => array(
			    		'Alpha',
			    		'messages' => 'You need to enter a valid name'
		    		),
		    		'email' => array(
			    		'EmailAddress',
			    		'messages' => 'You need to enter a valid email address'
		    		),
		    		'userId' => array(
		    			'allowEmpty' => true
		    		),
		    		'username' => array(
			    		'allowEmpty' => false
		    		),
		    		'password' => array(
			    		'allowEmpty' => false
		    		),
		    		'admin' => array(
			    		'allowEmpty' => false
		    		),
		    		'active' => array(
			    		'allowEmpty' => false
		    		),
		    		'allowed_campuses' => array(
		    			'allowEmpty' => true
		    		)
	    		);

    		$userData = new Zend_Filter_Input(array('*'=>'StringTrim'),$validators,$this->_request->getPost());

    		if ($userData->hasInvalid() || $userData->hasMissing()) { // output errors messages
    			$errorMessages = $userData->getMessages();
    			foreach ($errorMessages as $field => $error) {
    				if ($field == 'name')
    					$this->view->errors[] = 'You need to enter a name';
    				else
    					$this->view->errors[] = array_pop($error);
    			}
    		}
    		else {
				# user level
				$userIsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;

    			// prepare the user info for insertion into the database
    			$dbData = $userData->getUnescaped();
    			$dbData['allowed_campuses'] = implode(',',$dbData['allowed_campuses']);
    			unset($dbData['button']);

    			// add the user to the database or update his info
    			$userId = $this->_request->getParam('userId');
    			if (!$userId) {
    				unset($dbData['userId']);
	    			if (!$db->insert('users',$dbData)) {
	    				$this->view->errors[] = 'The user could not be saved into the database';
	    			}
    			}
    			else {
    				unset($dbData['userId']);
	    			if (!$db->update('users',$dbData,'id = '.$userId)) {
		    				$this->view->errors[] = 'The user informations could not be updated';
		    		}
    			}

    			$this->_helper->viewRenderer->setNoRender();
    			$this->_redirect('users?message=ADD_USER_SUCC');
    		}
    	}

    	// use userData to autocomplete fields
    	$userId = $this->_request->getParam('id');
    	if ($userId) {
    		$this->view->userData = $db->fetchRow('SELECT * FROM users WHERE id = '.$userId);
    	} else {
    		$this->view->userData = $this->_request->getPost();
    	}
		//echo '<pre>'; print_r($this->view->userData); echo '</pre>'; die();
		// only show campuses for which user is allowed to search
		$where = '';
		if (!empty($this->view->userData['allowed_campuses']) && 'all' != $this->view->userData['allowed_campuses']) {
			$where = ' WHERE id='.str_replace(',',' OR id=',$this->view->userData['allowed_campuses']);
		}

		$this->view->allowedSchools = $db->fetchPairs('SELECT id,id FROM campuses'.$where);
		$this->view->schools = $db->fetchPairs('SELECT id,name FROM campuses');
    }


    /**
     * Delete user
     */
	public function deleteAction() {
		$leadId = $this->_request->getParam('id',null);
		if (!$leadId) {
			$this->_redirect('users?error=DEL_USER_FAIL');
		}
		$db = Zend_Registry::get('db');
		if (!$db->delete('users','id = '.$leadId)) {
			$this->_redirect('users?error=DEL_USER_FAIL');
		}
		$this->_redirect('users?message=DEL_USER_SUCC');
	}

	/**
	 * Change status for user
	 *
	 */
	public function changestatusAction() {
		$this->_helper->viewRenderer->setNoRender();

		$userId = $this->_request->getParam('id',null);
		$to = $this->_request->getParam('to',null);
		if (!$userId || !in_array($to,array("0","1")))
			$this->_redirect('users/?error=CHANGE_STATUS_FAIL');

		$db = Zend_Registry::get('db');

		if (!$db->update('users',array('active' => $to),'id = '.$userId))
			$this->_redirect('users/?error=CHANGE_STATUS_FAIL');

		$this->_redirect('users/?message=CHANGE_STATUS_SUCC');
	}


	function _mapActive($val) {
		if (isset($val[5])) {
			if ($val[5] == 0)
				$val[5] = '<a href="'.$this->view->url(array('controller' => 'users', 'action' => 'changestatus')).'?id='.$val[6].'&to=1" style="border: 0pt none ; text-decoration: none;"><img src="'.SITE_ROOT.'/images/icons/lock_closed.png" style="border:0" alt="Not Active" title="Not Active" onmouseover="this.style.cursor=\'pointer\'" /></a>';
			else
			$val[5] = '<a href="'.$this->view->url(array('controller' => 'users', 'action' => 'changestatus')).'?id='.$val[6].'&to=0" style="border: 0pt none ; text-decoration: none;"><img src="'.SITE_ROOT.'/images/icons/lock_opened.png" style="border:0" alt="Active" title="Active" onmouseover="this.style.cursor=\'pointer\'" /></a>';
		}
		return $val;
	}
}
