<?php

class AuthController extends Zend_Controller_Action
{

	function init()
	{
		$this->initView();
		$this->view->baseUrl = $this->_request->getBaseUrl();
	}

    public function indexAction()
    {
		// redirect to /
		$this->_redirect('/');
    }
         
    public function loginAction()
    {
    	// disable layout
    	$this->_helper->layout()->disableLayout();
    	
    	// errors
    	$this->view->errors = '';
    	   
    	if ($this->_request->isPost()) {
			// collect the data from the user
			Zend_Loader::loadClass('Zend_Filter_StripTags');
			
			$f = new Zend_Filter_StripTags();
			$username = $f->filter($this->_request->getPost('username'));
			$password = $f->filter($this->_request->getPost('password'));
			
			if (empty($username)) {
				$this->view->errors = 'Please provide a username';
			} else {
				// setup Zend_Auth adapter for a database table
				Zend_Loader::loadClass('Zend_Auth_Adapter_DbTable');
				$db = Zend_Registry::get('db');
				$authAdapter = new Zend_Auth_Adapter_DbTable(
					$db,
					'users',
					'username',
					'password',
					'? AND active = 1'
					);
/*				$authAdapter->setTableName('users');
				$authAdapter->setIdentityColumn('username');
				$authAdapter->setCredentialColumn('password');*/
				
				// Set the input credential values to authenticate against
				$authAdapter->setIdentity($username);
				$authAdapter->setCredential($password);
				
				// do the authentication
				$auth = Zend_Auth::getInstance();
				$result = $auth->authenticate($authAdapter);
				
				if ($result->isValid()) {
					$data = $authAdapter->getResultRowObject(null,'password');
					$auth->getStorage()->write($data);
					$this->_redirect('/');
				} else {
					$this->view->errors = 'Login failed';
				}
			}
		}
    }
    
    public function logoutAction()
	{
		Zend_Auth::getInstance()->clearIdentity();
		$this->_redirect('/');
    }
}
