<?php


class EmailsController extends Zend_Controller_Action
{
	const CHANGE_SUCC = 'The email was succesfully saved.';
	const CHANGE_FAIL = 'The email could not be modified. Please try again.';
	const ADD_SUCC = 'The email was succesfully added to the database.';
	const ADD_FAIL = 'The email could not be added. Please try again.';

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
		$db = Zend_Registry::get('db');

		if ($this->_request->isPost()) {
			$emailData = $this->_request->getPost();
			unset($emailData['button']);

			if (!$db->update('emails',$emailData,'id = '.$this->_request->getParam('id'))) {
				$this->view->errorMessage = self::CHANGE_FAIL;
			}
			else {
				$this->view->succesMessage = self::CHANGE_SUCC;
			}
		}

		// get emails
		$this->view->Emails = $db->fetchPairs('SELECT id,subject FROM emails WHERE campus_id=0');
		$this->view->Emails[0] = '(select)';

		$emailId = $this->_request->getParam('id');
		if (!empty($emailId)) {
			// build sql query
			$select = $db->select()
						 ->calcFoundRows(true)
						 ->from(array('t1' => 'emails'),
						 		array("id",
						 			  "subject",
						 			  "body"
						 		))
						 ->where('t1.id = '.$emailId);


			$this->view->emailData = $select->query()->fetchAll();
			$this->view->emailData = $this->view->emailData[0];
			$this->view->id = $emailId;
		}

		//echo '<pre>'; print_r($this->view->tableData); die();
	}

	public function addAction()
	{
		$db = Zend_Registry::get('db');

		if ($this->_request->isPost()) {
			$emailData = $this->_request->getPost();
			unset($emailData['button']);
			$emailData['campus_id'] = 0;

			if (!$db->insert('emails',$emailData)) {
				$this->view->errorMessage = self::ADD_FAIL;
			}
			else {
				$this->view->succesMessage = self::ADD_SUCC;
			}
		}

		// get emails
		$this->view->Emails = $db->fetchPairs('SELECT id,subject FROM emails WHERE campus_id=0');
		$this->view->Emails[0] = '(select)';
	}

    public function initialAction()
    {
		$db = Zend_Registry::get('db');

		if ($this->_request->isPost()) {
			$emailData = $this->_request->getPost();
			unset($emailData['button']);

			if (!$db->update('emails',$emailData,'campus_id = '.$this->_request->getParam('id'))) {
				$this->view->errorMessage = self::CHANGE_FAIL;
			}
			else {
				$this->view->succesMessage = self::CHANGE_SUCC;
			}
		}

		// get campuses
		$this->view->campuses = $db->fetchPairs('SELECT id,name FROM campuses');
		$this->view->campuses[0] = '(select)';

		$campusId = $this->_request->getParam('id');
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
			$this->view->id = $campusId;
		}

		//echo '<pre>'; print_r($this->view->tableData); die();

    }

    public function resultsAction()
    {

    }
}
