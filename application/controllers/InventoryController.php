<?php


class InventoryController extends Zend_Controller_Action
{
	const ADD_EQUIPMENT_SUCC = 'The item was succesfully added to the system.';
	const DEL_EQUIPMENT_SUCC = 'The item was succesfully removed from the system.';
	const DEL_EQUIPMENT_FAIL = 'The item was not removed from the system. Please try again.';
	const CHANGE_STATUS_FAIL = 'The item could not be changed. Please try again.';
	const CHANGE_STATUS_SUCC = 'The item was succesfully changed.';

	function preDispatch()
	{
		$auth = Zend_Auth::getInstance();
		if (!$auth->hasIdentity()) {
			$this->_redirect('auth/login');
		}
		if ($auth->getStorage()->read()->admin == 0) {
			$this->_redirect('index');
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
								'width' => '500px',
								'style' => 'margin:auto',
								'class' => 'tblStyle1'
							),
			'caption' => 'Inventory Items',
			'headers' => array(
								array('Name','45%'),
								array('Type','45%'),
								array('Action','10%')
							),
			'alteredFields' => array (
								'1' => 'php:ucfirst("%field%")',
								'2' => '<a href="'.$this->view->url(array('controller' => 'inventory', 'action' => 'add')).'/?id=%field%" style="text-decoration:none;border:0;"><img src="'.SITE_ROOT.'/images/icons/edit_small.png" style="border:0" /></a>
									    <a href="'.$this->view->url(array('controller' => 'inventory', 'action' => 'delete')).'?id=%field%" style="text-decoration:none;border:0; margin-left: 10px;" onclick="return doDeleteConfirm()"><img src="'.SITE_ROOT.'/images/icons/delete_small.png" style="border:0" /></a>'
							)
			);

		// pagination used variables
		$resultOnPage = $this->_request->getParam('results',15);
		$pageNo = $this->_request->getParam('page',1);

		// get the new leads from the database so they'll fit our table configuration
		$db = Zend_Registry::get('db');
		$db->setFetchMode(Zend_Db::FETCH_NUM);

		// build sql query
		$select = $db->select()
					 ->calcFoundRows(true)
					 ->from(array('t1' => 'equipments'),
					 		array("name",
					 			  "type",
					 			  "id"
					 		))
					 ->where('id > 0')
					 ->order('type')
					 ->order('name')
					 ->limitPage($pageNo,$resultOnPage);

		$this->view->tableData = $select->query()->fetchAll();

		# get the total number of rows
		$totalRows = $db->fetchOne('SELECT FOUND_ROWS()');

		// build pagination
		$this->view->baseLink = $this->view->url(array('controller' => 'inventory'));
		$this->view->baseLink.= '?';

		$this->view->paginationConfig = array(
					'base_url' => $this->view->baseLink,
					'num_items' => $totalRows,
					'per_page' => $resultOnPage,
					'page_no' => $pageNo,
					'class' => 'paginationDiv'
				);

		$this->view->perPageLinksClass = 'perPageLinksInventory';
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
			    		'allowEmpty' => false
		    		),
		    		'type' => array(
			    		'allowEmpty' => false
		    		),
		    		'dealers' => array(
			    		'allowEmpty' => false
		    		)
	    		);

    		$itemData = new Zend_Filter_Input(array('*'=>'StringTrim'),$validators,$this->_request->getPost());

    		if ($itemData->hasInvalid() || $itemData->hasMissing()) { // output errors messages
    			$errorMessages = $itemData->getMessages();
    			foreach ($errorMessages as $field => $error) {
    				if ($field == 'name')
    					$this->view->errors[] = 'You need to enter an name';
    				else
    					$this->view->errors[] = array_pop($error);
    			}
    		}
    		else {
				# user level
				$userIsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;

    			// prepare the user info for insertion into the database
    			$dbData = $itemData->getUnescaped();
    			unset($dbData['button']);

    			// add the user to the database or update his info
    			$itemId = $this->_request->getParam('itemId');
    			if (!$itemId) {
    				unset($dbData['itemId']);
	    			if (!$db->insert('equipments',$dbData)) {
	    				$this->view->errors[] = 'The item could not be saved into the database';
	    			}
    			}
    			else {
    				unset($dbData['itemId']);
	    			if (!$db->update('equipments',$dbData,'id = '.$itemId)) {
		    				$this->view->errors[] = 'The item informations could not be updated';
		    		}
    			}

    			$this->_helper->viewRenderer->setNoRender();
    			$this->_redirect('inventory?message=ADD_EQUIPMENT_SUCC');
    		}
    	}

    	// use marketData to autocomplete fields
    	$itemId = $this->_request->getParam('id');
    	if ($itemId) {
    		$this->view->itemData = $db->fetchRow('SELECT * FROM equipments WHERE id = '.$itemId);
    	} else {
    		$this->view->itemData = $this->_request->getPost();
    	}
    }


    /**
     * Delete user
     */
	public function deleteAction() {
		$itemId = $this->_request->getParam('id',null);
		if (!$itemId) {
			$this->_redirect('inventory?error=DEL_EQUIPMENT_FAIL');
		}
		$db = Zend_Registry::get('db');
		if (!$db->delete('equipments','id = '.$itemId)) {
			$this->_redirect('inventory?error=DEL_EQUIPMENT_FAIL');
		}
		$this->_redirect('inventory?message=DEL_EQUIPMENT_SUCC');
	}
}
