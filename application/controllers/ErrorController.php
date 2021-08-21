<?php
// application/controllers/ErrorController.php

/**
 * ErrorController
 */
class ErrorController extends Zend_Controller_Action
{
	const NO_LEAD_TYPE = 'No user type specified';
	const NO_LEAD_ID = 'No user id specified';
	const NO_QUOTE_ID = 'No quote id specified';
	const EXPORT_STARTS_QUOTES = 'Could not export the quotes for these starts. Please try again.';

	function preDispatch()
	{
		$auth = Zend_Auth::getInstance();
		if (!$auth->hasIdentity()) {
			$this->_redirect('auth/login');
		}
	}

    public function errorAction()
    {
    	// Disable layout
    	$this->_helper->layout()->disableLayout();

        // Ensure the default view suffix is used so we always return good
        // content
        $this->_helper->viewRenderer->setViewSuffix('phtml');

        // Grab the error object from the request
        $errors = $this->_getParam('error_handler');

        // $errors will be an object set as a parameter of the request object,
        // type is a property
        switch ($errors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:

                // 404 error -- controller or action not found
                $this->getResponse()->setHttpResponseCode(404);
                $this->view->message = 'Page not found';
                break;
            default:
                // application error
                $this->getResponse()->setHttpResponseCode(500);
                $this->view->message = 'Application error';
                break;
        }

        // pass the environment to the view script so we can conditionally
        // display more/less information
        $this->view->env       = $this->getInvokeArg('env');

        // pass the actual exception object to the view
        $this->view->exception = $errors->exception;

        // pass the request to the view
        $this->view->request   = $errors->request;
    }

    public function showAction() {
    	$errName = $this->_request->getParams();
    	$errName = array_keys($errName);
    	$errName = $errName[count($errName)-1];

    	$this->view->errorMessage = constant('self::'.$errName);
    	$this->view->errorMessage.= '<br /><a href="javascript:history.back()">Go Back</a>';
    }
}
