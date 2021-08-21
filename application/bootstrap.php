<?php
// application/bootstrap.php
//
// Step 1: APPLICATION CONSTANTS - Set the constants to use in this application.
// These constants are accessible throughout the application, even in ini
// files. We optionally set APPLICATION_PATH here in case our entry point
// isn't index.php (e.g., if required from our test suite or a script).
defined('APPLICATION_PATH')
    or define('APPLICATION_PATH', dirname(__FILE__));

defined('APPLICATION_ENVIRONMENT')
    or define('APPLICATION_ENVIRONMENT', 'development');
    
defined('SITE_ROOT')
	or define('SITE_ROOT', substr($_SERVER['SCRIPT_NAME'],0,strrpos($_SERVER['SCRIPT_NAME'],'/')));
defined('SITE_PATH')
	or define('SITE_PATH', substr(APPLICATION_PATH,0,strrpos(APPLICATION_PATH,'application')));

require '_config.php';

// Step 2: FRONT CONTROLLER - Get the front controller.
// The Zend_Front_Controller class implements the Singleton pattern, which is a
// design pattern used to ensure there is only one instance of
// Zend_Front_Controller created on each request.
$frontController = Zend_Controller_Front::getInstance();

// Step 3: CONTROLLER DIRECTORY SETUP - Point the front controller to your action
// controller directory.
$frontController->setControllerDirectory(APPLICATION_PATH . '/controllers');

// Application subfolder
//echo str_replace('\\', '/', substr(dirname(__FILE__), strlen(realpath($_SERVER["DOCUMENT_ROOT"]))));
//echo substr($_SERVER['SCRIPT_NAME'],0,strrpos($_SERVER['SCRIPT_NAME'],'/'));
$frontController->setBaseUrl(SITE_ROOT);

// Step 4: APPLICATION ENVIRONMENT - Set the current environment.
// Set a variable in the front controller indicating the current environment --
// commonly one of development, staging, testing, production, but wholly
// dependent on your organization's and/or site's needs.
$frontController->setParam('env', APPLICATION_ENVIRONMENT);

// LAYOUT SETUP - Setup the layout component
// The Zend_Layout component implements a composite (or two-step-view) pattern
// With this call we are telling the component where to find the layouts scripts.
Zend_Layout::startMvc(APPLICATION_PATH . '/layouts/scripts');


// VIEW SETUP - Initialize properties of the view object
// The Zend_View component is used for rendering views. Here, we grab a "global"
// view instance from the layout object, and specify the doctype we wish to
// use. In this case, XHTML1 Strict.
$view = Zend_Layout::getMvcInstance()->getView();
$view->addHelperPath(APPLICATION_PATH . '/helpers');
$view->doctype('XHTML1_STRICT');

// CLEANUP - remove items from global scope
// This will clear all our local boostrap variables from the global scope of
// this script (and any scripts that called bootstrap). This will enforce
// object retrieval through the applications's registry.
unset($view);

// Step 5: CLEANUP - Remove items from global scope.
// This will clear all our local boostrap variables from the global scope of
// this script (and any scripts that called bootstrap).  This will enforce
// object retrieval through the applications's registry.
unset($frontController);
