<?php


class MarketsController extends Zend_Controller_Action
{
	const ADD_MARKET_SUCC 		= 'The market was succesfully added to the system.';
	const DEL_MARKET_SUCC 		= 'The market was succesfully removed from the system.';
	const DEL_MARKET_FAIL 		= 'The market was not removed from the system. Please try again.';
	const CHANGE_STATUS_FAIL 	= 'The market could not be changed. Please try again.';
	const CHANGE_STATUS_SUCC 	= 'The market was succesfully changed.';
	const INVALID_REQUEST		= 'Invalid request';
	const DATE_RANGE_EXISTS 	= 'The date / date range or part of it already exists for this campus. Remove the old date / date range from the special prices list and try again.';
	const DATE_RANGE_EXISTS_BO 	= 'The date / date range or part of it already exists for this campus. Remove the old date / date range from the blacked out dates list and try again.';
	const INVALID_DATE_RANGE 	= 'The date range you  have selected is invalid. It seems like the end start is before the start date.';
	const ADD_PRICES_SUCC		= 'The special prices were successfully saved into the system.';
	const ADD_WEEKPRICES_SUCC	= 'The weekend prices were successfully saved into the system.';
	const UPDATE_PRICES_SUCC	= 'The prices were successfully updated.';
	const REM_PRICES_SUCC		= 'The promo / special prices period was successfully removed.';
	const REM_PRICES_FAIL		= 'An error occured while trying to remove the promo / special prices period. Please try again.';
	const ADD_BLACKOUT_SUCC		= 'The date/dates was/were successfully blacked out.';
	const REM_BLACKOUT_SUCC		= 'The blacked out date / date range was successfully removed.';
	const REM_BLACKOUT_FAIL		= 'An error occured while trying to remove the blacked out date / date range. Please try again.';

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
								'width' => '75%',
								'style' => 'margin:auto',
								'class' => 'tblStyle1'
							),
			'caption' => 'Markets',
			'headers' => array(
								array('Name','23%'),
								array('Contact Name','23%'),
								array('Email Address','23%'),
								array('Phone','23%'),
								array('Action','5%')
							),
			'alteredFields' => array (
								'4' => '<a href="'.$this->view->url(array('controller' => 'markets', 'action' => 'edit')).'/?id=%field%" style="text-decoration:none;border:0;"><img src="'.SITE_ROOT.'/images/icons/edit_small.png" style="border:0" /></a>
									    <a href="'.$this->view->url(array('controller' => 'markets', 'action' => 'delete')).'?id=%field%" style="text-decoration:none;border:0; margin-left: 10px;" onclick="return doDeleteConfirm()"><img src="'.SITE_ROOT.'/images/icons/delete_small.png" style="border:0" /></a>'
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
					 ->from(array('t1' => 'campuses'),
					 		array("name",
					 			  "contact_name",
					 			  "email",
					 			  "phone",
					 			  "id"
					 		))
					 ->where('id > 0')
					 ->limitPage($pageNo,$resultOnPage);

		$this->view->tableData = $select->query()->fetchAll();

		# get the total number of rows
		$totalRows = $db->fetchOne('SELECT FOUND_ROWS()');

		// build pagination
		$this->view->baseLink = $this->view->url(array('controller' => 'markets'));
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
			    		'allowEmpty' => false,
			    		'messages' => 'You need to enter a valid name'
		    		),
		    		'email' => array(
			    		'EmailAddress',
			    		'messages' => 'You need to enter a valid email address'
		    		),
		    		'campusId' => array(
		    			'allowEmpty' => true
		    		),
		    		'contact_name' => array(
			    		'allowEmpty' => true
		    		),
		    		'phone' => array(
			    		'allowEmpty' => false
		    		),
		    		'address' => array(
			    		'allowEmpty' => true
		    		),
		    		'city' => array(
			    		'allowEmpty' => true
		    		),
		    		'state' => array(
			    		'allowEmpty' => true
		    		),
		    		'zip' => array(
			    		'allowEmpty' => true
		    		),
		    		'full_pay_discount' => array(
			    		'allowEmpty' => true
		    		),
		    		'sales_tax' => array(
			    		'allowEmpty' => true
		    		)
	    		);

    		$marketData = new Zend_Filter_Input(array('*'=>'StringTrim'),$validators,$this->_request->getPost());

    		if ($marketData->hasInvalid() || $marketData->hasMissing()) { // output errors messages
    			$errorMessages = $marketData->getMessages();
    			foreach ($errorMessages as $field => $error) {
    				if ($field == 'name') {
    					$this->view->errors[] = 'You need to enter a name';
    				}
    				else
    					$this->view->errors[] = array_pop($error);
    			}
    		}
    		else {
				# user level
				$userIsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;

    			// prepare the user info for insertion into the database
    			$dbData = $marketData->getUnescaped();
    			unset($dbData['button']);
    			
    			if (empty($dbData['full_pay_discount'])) { $dbData['full_pay_discount'] = 0; }
    			if (empty($dbData['sales_tax'])) { $dbData['sales_tax'] = 0; }

    			// add the user to the database or update his info
    			$userId = $this->_request->getParam('campusId');
    			if (!$userId) {
    				unset($dbData['campusId']);
	    			if (!$db->insert('campuses',$dbData)) {
	    				$this->view->errors[] = 'The market could not be saved into the database';
	    			}
    			}
    			else {
    				unset($dbData['campusId']);
	    			if (!$db->update('campuses',$dbData,'id = '.$userId)) {
		    				$this->view->errors[] = 'The market informations could not be updated';
		    		}
    			}

    			$this->_helper->viewRenderer->setNoRender();
    			$this->_redirect('markets?message=ADD_MARKET_SUCC');
    		}
    	}

    	// use marketData to autocomplete fields
    	$marketId = $this->_request->getParam('id');
    	if ($marketId) {
    		$this->view->marketData = $db->fetchRow('SELECT * FROM campuses WHERE id = '.$marketId);
    	} else {
    		$this->view->marketData = $this->_request->getPost();
    	}
    }
    
    public function editAction()
    {
    	$this->view->errors = '';
    	
		// use marketData to autocomplete fields
    	$marketId = $this->_request->getParam('id');
		if (empty($marketId)) {
			$this->_redirect('markets/index?error=INVALID_REQUEST');
		}
		$this->view->marketId = $marketId;
    	
        # check if we have some messages trough get
		if ($this->_request->get('message',null)) {
			$this->view->succesMessage = constant('self::'.$this->_request->getParam('message'));
		}
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}
    	
    	$db = Zend_Registry::get('db');
    	
    	// check to see if we have to remove an price interval
    	$dateStart = $this->_request->getParam('date_start', null);
    	$dateEnd = $this->_request->getParam('date_end', null);
    	$dateStart = urldecode($dateStart);
    	$dateEnd = urldecode($dateEnd);
    	
    	// delete special prices period ?
    	if (!empty($dateStart) && !empty($dateEnd)) {
    		if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $dateStart) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $dateEnd)) {
    			$result = $db->delete('special-prices', "date_start LIKE '{$dateStart}' AND date_end LIKE '{$dateEnd}'");
    			if ($result) {
    				$this->_redirect('markets/edit?id=' . $marketId . '&message=REM_PRICES_SUCC');
    			}
    		}
    		$this->_redirect('markets/edit?id=' . $marketId . '&error=REM_PRICES_FAIL');
    	}
    	
    	// check to see if we have to remove an blacked out date
    	$boDateId = $this->_request->getParam('blackoutDateId', 0);
    	if (!empty($boDateId)) {
    		$result = $db->delete('blackout-dates', "id = {$boDateId}");
    		if ($result) {
    			$this->_redirect('markets/edit?id=' . $marketId . '&message=REM_BLACKOUT_SUCC');
    		}
    		$this->_redirect('markets/edit?id=' . $marketId . '&error=REM_BLACKOUT_FAIL');
    	}

    	if ($this->_request->isPost()) {
    		// validate the input
    		$validators = array(
		    		'name' => array(
			    		'messages' => 'You need to enter a valid name'
		    		),
		    		'email' => array(
			    		'EmailAddress',
			    		'messages' => 'You need to enter a valid email address'
		    		),
		    		'id' => array(
		    			'allowEmpty' => true
		    		),
		    		'contact_name' => array(
			    		'allowEmpty' => false
		    		),
		    		'phone' => array(
			    		'allowEmpty' => false
		    		),
		    		'address' => array(
			    		'allowEmpty' => true
		    		),
		    		'city' => array(
			    		'allowEmpty' => true
		    		),
		    		'state' => array(
			    		'allowEmpty' => true
		    		),
		    		'zip' => array(
			    		'allowEmpty' => true
		    		),
		    		'full_pay_discount' => array(
			    		'allowEmpty' => true
		    		),
		    		'sales_tax' => array(
			    		'allowEmpty' => true
		    		)
	    		);

    		$marketData = new Zend_Filter_Input(array('*'=>'StringTrim'),$validators,$this->_request->getPost());

    		if ($marketData->hasInvalid() || $marketData->hasMissing()) { // output errors messages
    			$errorMessages = $marketData->getMessages();
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
    			$dbData = $marketData->getUnescaped();
    			unset($dbData['button']);
    			
    			if (empty($dbData['full_pay_discount'])) { $dbData['full_pay_discount'] = 0; }
    			if (empty($dbData['sales_tax'])) { $dbData['sales_tax'] = 0; }

    			if (!$marketId) {
    				$this->_redirect('markets?error=CHANGE_STATUS_FAIL');
    			}
    			else {
    				unset($dbData['id']);
	    			if (!$db->update('campuses',$dbData,'id = '.$marketId)) {
		    				$this->_redirect('markets?error=CHANGE_STATUS_FAIL');
		    		}
    			}

    			$this->_helper->viewRenderer->setNoRender();
    			$this->_redirect('markets?message=CHANGE_STATUS_SUCC');
    		}
    	}

    	$this->view->marketData = $db->fetchRow('SELECT * FROM campuses WHERE id = '.$marketId);
    	
    	// fetch tables
    	$sql = "SELECT id, name FROM equipments WHERE type='table'";
    	$this->view->tables = $db->fetchPairs($sql);
    	// fetch services
    	$sql = "SELECT id, name FROM equipments WHERE type='service'";
    	$this->view->services = $db->fetchPairs($sql);
    	
    	$array = array();
    	
    	// fetch promos
    	$this->view->promos = array();
    	$sql = "SELECT t1.*, t3.name FROM `special-prices` AS t1 JOIN `campus-equipment` AS t2 ON t1.campus_equipment_id = t2.id JOIN equipments AS t3 ON t3.id = t2.equipment_id WHERE t1.campus_id = {$marketId} ORDER BY t1.date_start, t1.id";
    	$results = $db->fetchAll($sql);
    	if (!empty($results)) {
    		foreach ($results as $promo) {
    			$exists = self::my_array_search(array('date_start' => $promo['date_start'], 'date_end' => $promo['date_end']), $this->view->promos);
    			if (false === $exists) {
    				$periodPromos = array();
    				$periodPromos[] = $promo;
    				$this->view->promos[] = array('date_start' => $promo['date_start'], 'date_end' => $promo['date_end'], 'promos' => $periodPromos);
    			} else {
    				$this->view->promos[$exists]['promos'][] = $promo;
    			}
    		}
    	}
    	
    	// fetch weekend prices
    	$this->view->weekendPrices = array();
    	$sql = "SELECT t1.id as eq_id, t1.*, t2.* FROM `weekend-prices` AS t1 JOIN equipments AS t2 ON t1.equipment_id = t2.id WHERE t1.campus_id = {$marketId}";
    	$this->view->weekendPrices = $db->fetchAll($sql);

    	// fetch blacked out dates
    	$this->view->blackedOutDates = array();
    	$sql = "SELECT t1.* FROM `blackout-dates` AS t1 WHERE t1.campus_id = {$marketId}";
    	$this->view->blackedOutDates = $db->fetchAll($sql);
    }
    
    public function addInventoryAction()
    {
    	$this->_helper->layout()->disableLayout();
    	$this->_helper->viewRenderer->setNoRender();
    	
    	if ($this->_request->isPost()) {
    		$params = $this->_request->getParams();
    		if (!empty($params['type'])) {
    			if (!in_array($params['type'], array('table', 'service'))) {
    				echo 'Invalid item type';
    				exit;
    			}
    			
    			$dbData = array();
    			// same fields for both types
    			if (!isset($params['price'])) { echo 'You must enter the price w/o dealer'; exit; }
    			else if (!preg_match('/^[0-9.]+$/', $params['price'])) { echo 'Invalid price w/o dealer. Only numbers and . separator allowed'; exit; }
    			$dbData['price'] = MyLibs_Quote::myRound($params['price'], 2);
    			
    			if (!isset($params['extra_hour_price'])) { echo 'You must enter the extra hour price'; exit; }
    			elseif (!preg_match('/[0-9.]+/', $params['extra_hour_price'])) { echo 'Invalid extra hour price. Only numbers and . separator allowed'; exit; }
    			$dbData['extra_hour_price'] = MyLibs_Quote::myRound($params['extra_hour_price'], 2);
    			
    			if (!isset($params['inventory_total'])) { echo 'You must enter the total inventory'; exit; }
    			elseif (!preg_match('/^[0-9]+$/', $params['inventory_total'])) { echo 'Invalid total inventory. Only numbers allowed'; exit; }
    			$dbData['inventory_total'] = $params['inventory_total'];
    			$dbData['inventory'] = $params['inventory_total'];
    			
    			if ('table' == $params['type']) {
    				if (!isset($params['price_dealer'])) { echo 'You must enter the price w/ dealer'; exit; }
	    			elseif (!preg_match('/^[0-9.]+$/', $params['price_dealer'])) { echo 'Invalid price w/ dealer. Only numbers and . separator allowed'; exit; }
	    			$dbData['price_dealer'] = MyLibs_Quote::myRound($params['price_dealer'], 2);
    			}
    			
    			if (empty($params['id'])) { echo 'Invalid equipment item selected'; exit; }
    			$dbData['equipment_id'] = $params['id'];
    			
    			if (empty($params['marketId'])) { echo 'Invalid market selected'; exit; }
    			$dbData['campus_id'] = $params['marketId'];
    			
    			$db = Zend_Registry::get('db');
    			// check if it already exists
    			$exists = $db->fetchOne("SELECT id FROM `campus-equipment` WHERE campus_id = {$dbData['campus_id']} AND equipment_id = {$dbData['equipment_id']}");
    			if ($exists) {
    				echo 'The equipment is already added for this market. You have to remove it first.'; exit;
    			}
    			
    			if (!$db->insert('campus-equipment', $dbData)) {
    				echo 'Could not add the inventory.'; exit;
    			}
    			$relId = $db->lastInsertId();
    			// everything ok, add to the weekend prices too, exclude inventory
    			try { unset($dbData['inventory']); } catch(ErrorException $e) {}
    			try { unset($dbData['inventory_total']); } catch(ErrorException $e) {}
    			$dbData['campus_equipment_id'] = $relId;
    			$db->insert('weekend-prices', $dbData);
    			
    			echo 'OK'; exit;
    		}
    	} else {
    		echo 'No data sent';
    		exit;
    	}
    }
    
	public function addDiscountAction()
    {
    	$this->_helper->layout()->disableLayout();
    	$this->_helper->viewRenderer->setNoRender();
    	
    	if ($this->_request->isPost()) {
    		$params = $this->_request->getParams();

    			
    		$dbData = array();
    		// same fields for both types
    		if (empty($params['no_of_tables'])) { echo 'You must enter the number of tables'; exit; }
    		else if (!preg_match('/^[0-9]+$/', $params['no_of_tables'])) { echo 'Invalid number of tables. Only numbers allowed'; exit; }
    		$dbData['tables'] = $params['no_of_tables'];
    		
    		if (empty($params['percent'])) { echo 'You must enter the discount value'; exit; }
    		else if (!preg_match('/^[0-9]{1,2}$/', $params['percent'])) { echo 'Invalid discount value. Only numbers allowed. Must be smaller than 100'; exit; }
    		$dbData['percent'] = $params['percent'];
    		
    		if (empty($params['marketId'])) { echo 'Invalid market selected'; exit; }
    		$dbData['campus_id'] = $params['marketId'];
    		
    		$db = Zend_Registry::get('db');
    		if (!$db->insert('equipment-discount', $dbData)) {
    			echo 'Could not save the discount'; exit;
    		}
    		echo 'OK'; exit;
    	} else {
    		echo 'No data sent';
    		exit;
    	}
    }
    
	public function updateTotalInventoryAction()
    {
    	$this->_helper->layout()->disableLayout();
    	$this->_helper->viewRenderer->setNoRender();
    	
    	if ($this->_request->isPost()) {
    		$params = $this->_request->getParams();

    		$dbData = array();
    		    		
    		if (empty($params['eqId'])) { echo 'Invalid request'; exit; }
    		$campus_equipment_id = $params['eqId'];
    		
    		if (empty($params['inventory_total'])) { echo 'You must enter the total inventory'; exit; }
    		elseif (!preg_match('/^[0-9]+$/', $params['inventory_total'])) { echo 'Invalid total inventory. Only numbers allowed'; exit; }
    		$dbData['inventory_total'] = $params['inventory_total'];
    		
    		$db = Zend_Registry::get('db');
    		// first, get the current total inventory
    		$totalInventory = $db->fetchOne("SELECT inventory_total FROM `campus-equipment` WHERE id = {$campus_equipment_id}");
    		$totalInventory = intval($totalInventory);
    		if (!$db->update('campus-equipment', $dbData, "id = {$campus_equipment_id}")) {
    			echo 'Could not update the total inventory'; exit;
    		}
    		// update the stock
    		$newDbData['inventory'] = new Zend_Db_Expr('(inventory + (inventory_total - ' . $totalInventory . '))');
    		if (!$db->update('campus-equipment', $newDbData, "id = {$campus_equipment_id}")) {
    			// revert back to the old total inventory
    			$dbData['inventory_total'] = $totalInventory;
    			$db->update('campus-equipment', $dbData, "id = {$campus_equipment_id}");
    			echo 'Could not update the total inventory'; exit;
    		}
    		echo 'OK'; exit;
    	} else {
    		echo 'No data sent';
    		exit;
    	}
    }
    
    public function remInventoryAction()
    {
    	$this->_helper->layout()->disableLayout();
    	$this->_helper->viewRenderer->setNoRender();
    	
    	$relationId = $this->_request->getParam('id');
    	if (!empty($relationId)) {
    		$db = Zend_Registry::get('db');
    		//$sql = "DELETE FROM campus-equipment WHERE id = {$relationId}";
    		$db->delete('campus-equipment', 'id = ' . $relationId);
			// delete from special-prices table too
			$db->delete('special-prices', 'campus_equipment_id = ' . $relationId);
			// delete from weekend-prices too
			$db->delete('weekend-prices', 'campus_equipment_id = ' . $relationId);
    	}
    }
    
	public function remDiscountAction()
    {
    	$this->_helper->layout()->disableLayout();
    	$this->_helper->viewRenderer->setNoRender();
    	
    	$discountId = $this->_request->getParam('id');
    	if (!empty($discountId)) {
    		$db = Zend_Registry::get('db');
    		$db->delete('equipment-discount', 'discount_id = ' . $discountId);
    	}
    }
    
    public function addSpecialDayAction()
    {
    	$marketId = $this->_request->getParam('id');
    	
    	if (empty($marketId)) {
    		$this->_redirect('markets/index?error=INVALID_REQUEST');
    	}
    	$this->view->marketId = $marketId;
    	
    	# check if we have some messages trough get
		if ($this->_request->get('message',null)) {
			$this->view->succesMessage = constant('self::'.$this->_request->getParam('message'));
		}
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}
    	
    	$db = Zend_Registry::get('db');
    	
    	if ($this->_request->isPost()) {
    		$postPrices = $this->_request->getParams();
    		
    		if (empty($postPrices['date_start']) || empty($postPrices['date_end'])) {
    			$this->_redirect('markets/add-special-day?error=INVALID_REQUEST');
    		}
    		if (strtotime($postPrices['date_start']) > strtotime($postPrices['date_end'])) {
    			$this->_redirect('markets/add-special-day?error=INVALID_DATE_RANGE&id=' . $marketId);
    		}
    		
    		// check if date exists for this campus
    		if ($postPrices['date_start'] == $postPrices['date_end']) {
    			$sql = "SELECT id FROM `special-prices` WHERE campus_id = {$marketId} AND '{$postPrices['date_start']}' BETWEEN date_start AND date_end";
    		} else {
    			$sql = "SELECT id FROM `special-prices` WHERE campus_id = {$marketId} AND (('{$postPrices['date_start']}' BETWEEN date_start AND date_end) OR ('{$postPrices['date_end']}' BETWEEN date_start AND date_end))";
    		}
    		$result = $db->fetchAll($sql);
    		
    		if (!empty($result)) {
    			$this->_redirect('markets/add-special-day?error=DATE_RANGE_EXISTS&id=' . $marketId);
    		}
    		
    		if (!empty($postPrices['info']) && is_array($postPrices['info'])) {
    			foreach($postPrices['info'] as $itemId => $item) {
					$insertData = array();
					$insertData['campus_equipment_id'] 	= $itemId;
					$insertData['campus_id'] 			= $marketId;
					$insertData['price'] 				= MyLibs_Quote::myRound($item['price'], 2);
					$insertData['price_dealer'] 		= MyLibs_Quote::myRound($item['price_dealer'], 2);
					$insertData['extra_hour_price'] 	= MyLibs_Quote::myRound($item['extra_hour_price'], 2);
					$insertData['date_start'] 			= $postPrices['date_start'];
					$insertData['date_end'] 			= $postPrices['date_end'];
					
					$db->insert('special-prices', $insertData);
    			}
    		}
    		
    		$this->_redirect('markets/edit?message=ADD_PRICES_SUCC&id=' . $marketId);
    	}
    	
    	// fetch all items in the inventory for this market
    	$sql = "SELECT t1.id as eq_id, t1.*, t2.* FROM `campus-equipment` AS t1 JOIN equipments AS t2 ON t1.equipment_id = t2.id WHERE t1.campus_id = {$marketId}";
    	$this->view->items = $db->fetchAll($sql);
    	
    	$this->view->marketId = $marketId;
    }
    
    public function addBlackoutDateAction()
    {
    	$marketId = $this->_request->getParam('id');
    	
    	if (empty($marketId)) {
    		$this->_redirect('markets/index?error=INVALID_REQUEST');
    	}
    	$this->view->marketId = $marketId;
    	
    	# check if we have some messages trough get
		if ($this->_request->get('message',null)) {
			$this->view->succesMessage = constant('self::'.$this->_request->getParam('message'));
		}
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}
    	
    	$db = Zend_Registry::get('db');
    	
    	if ($this->_request->isPost()) {
    		$postPrices = $this->_request->getParams();
    		
    		if (empty($postPrices['date_start']) || empty($postPrices['date_end'])) {
    			$this->_redirect('markets/add-blackout-date?error=INVALID_REQUEST');
    		}
    		if (strtotime($postPrices['date_start']) > strtotime($postPrices['date_end'])) {
    			$this->_redirect('markets/add-blackout-date?error=INVALID_DATE_RANGE&id=' . $marketId);
    		}
    		
    		// check if date exists for this campus
    		if ($postPrices['date_start'] == $postPrices['date_end']) {
    			$sql = "SELECT id FROM `blackout-dates` WHERE campus_id = {$marketId} AND '{$postPrices['date_start']}' BETWEEN date_start AND date_end";
    		} else {
    			$sql = "SELECT id FROM `blackout-dates` WHERE campus_id = {$marketId} AND (('{$postPrices['date_start']}' BETWEEN date_start AND date_end) OR ('{$postPrices['date_end']}' BETWEEN date_start AND date_end))";
    		}
    		$result = $db->fetchAll($sql);
    		
    		if (!empty($result)) {
    			$this->_redirect('markets/add-blackout-date?error=DATE_RANGE_EXISTS_BO&id=' . $marketId);
    		}
    		
			$insertData = array();
			$insertData['campus_id'] 			= $marketId;
			$insertData['date_start'] 			= $postPrices['date_start'];
			$insertData['date_end'] 			= $postPrices['date_end'];
			
			$db->insert('blackout-dates', $insertData);
    		
    		$this->_redirect('markets/edit?message=ADD_BLACKOUT_SUCC&id=' . $marketId);
    	}
    	
    	$this->view->marketId = $marketId;
    }
    
	public function editPricesAction()
    {
    	$marketId = $this->_request->getParam('id');
    	
    	if (empty($marketId)) {
    		$this->_redirect('markets/index?error=INVALID_REQUEST');
    	}
    	$this->view->marketId = $marketId;
    	
    	# check if we have some messages trough get
		if ($this->_request->get('message',null)) {
			$this->view->succesMessage = constant('self::'.$this->_request->getParam('message'));
		}
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}
    	
    	$db = Zend_Registry::get('db');
    	
    	if ($this->_request->isPost()) {
    		$postPrices = $this->_request->getParams();
    		
    		if (!empty($postPrices['info']) && is_array($postPrices['info'])) {
    			foreach($postPrices['info'] as $itemId => $item) {
					$insertData = array();
					$insertData['id'] 					= $itemId;
					$insertData['price'] 				= MyLibs_Quote::myRound($item['price'], 2);
					$insertData['price_dealer'] 		= MyLibs_Quote::myRound($item['price_dealer'], 2);
					$insertData['extra_hour_price'] 	= MyLibs_Quote::myRound($item['extra_hour_price'], 2);
					
					$db->update('campus-equipment', $insertData, 'id = ' . $itemId);
    			}
    		}
    		
    		$this->_redirect('markets/edit?message=UPDATE_PRICES_SUCC&id=' . $marketId);
    	}
    	
    	// fetch all items in the inventory for this market
    	$sql = "SELECT t1.id as pr_id, t1.*, t2.* FROM `campus-equipment` AS t1 JOIN equipments AS t2 ON t1.equipment_id = t2.id WHERE t1.campus_id = {$marketId}";
    	$this->view->items = $db->fetchAll($sql);
    	
    	$this->view->marketId = $marketId;
    }
    
    public function setWeekendPricesAction()
    {
    	$marketId = $this->_request->getParam('id');
    	
    	if (empty($marketId)) {
    		$this->_redirect('markets/index?error=INVALID_REQUEST');
    	}
    	$this->view->marketId = $marketId;
    	
    	# check if we have some messages trough get
		if ($this->_request->get('message',null)) {
			$this->view->succesMessage = constant('self::'.$this->_request->getParam('message'));
		}
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}
    	
    	$db = Zend_Registry::get('db');
    	
    	if ($this->_request->isPost()) {
    		$postPrices = $this->_request->getParams();
    		
    		if (!empty($postPrices['info']) && is_array($postPrices['info'])) {
    			foreach($postPrices['info'] as $itemId => $item) {
					$insertData = array();
					$insertData['campus_equipment_id'] 	= $itemId;
					$insertData['campus_id'] 			= $marketId;
					$insertData['price'] 				= MyLibs_Quote::myRound($item['price'], 2);
					$insertData['price_dealer'] 		= MyLibs_Quote::myRound($item['price_dealer'], 2);
					$insertData['extra_hour_price'] 	= MyLibs_Quote::myRound($item['extra_hour_price'], 2);
					
					$db->update('weekend-prices', $insertData, 'id = ' . $itemId);
    			}
    		}
    		
    		$this->_redirect('markets/edit?message=ADD_WEEKPRICES_SUCC&id=' . $marketId);
    	}
    	
    	// fetch all items in the inventory for this market
    	$sql = "SELECT t1.id as pr_id, t1.*, t2.* FROM `weekend-prices` AS t1 JOIN equipments AS t2 ON t1.equipment_id = t2.id WHERE t1.campus_id = {$marketId}";
    	$this->view->items = $db->fetchAll($sql);
    	
    	$this->view->marketId = $marketId;
    }
    
    public function getInventoryAction()
    {
    	$this->_helper->layout()->disableLayout();
    	
    	$marketId = $this->_request->getParam('id', null);
    	
    	if (!empty($marketId)) {
    		$db = Zend_Registry::get('db');
    		
    		$sql = "SELECT t1.id as eq_id, t1.*, t2.* FROM `campus-equipment` AS t1 JOIN equipments AS t2 ON t1.equipment_id = t2.id WHERE t1.campus_id = {$marketId}";
    		$rows = $db->fetchAll($sql);
    		
    		if (!empty($rows)) {
    			$this->view->inventory = $rows;
    		}
    	}
    }
    
	public function getDiscountsAction()
    {
    	$this->_helper->layout()->disableLayout();
    	
    	$marketId = $this->_request->getParam('id', null);
    	
    	if (!empty($marketId)) {
    		$db = Zend_Registry::get('db');
    		
    		$sql = "SELECT * FROM `equipment-discount` WHERE campus_id = {$marketId} ORDER BY `tables` ASC";
    		$rows = $db->fetchAll($sql);
    		
    		if (!empty($rows)) {
    			$this->view->discounts = $rows;
    		}
    	}
    }


    /**
     * Delete user
     */
	public function deleteAction() {
		$leadId = $this->_request->getParam('id',null);
		if (!$leadId) {
			$this->_redirect('markets?error=DEL_MARKET_FAIL');
		}
		$db = Zend_Registry::get('db');
		if (!$db->delete('campuses','id = '.$leadId)) {
			$this->_redirect('markets?error=DEL_MARKET_FAIL');
		}
		$this->_redirect('markets?message=DEL_MARKET_SUCC');
	}
	
	static public function my_array_search($needle, $haystack) {
		if (empty($needle) || empty($haystack)) {
			return false;
		}
		
		foreach ($haystack as $key => $value) {
			$exists = 0;
			foreach ($needle as $nkey => $nvalue) {
				if (!empty($value[$nkey]) && $value[$nkey] == $nvalue) {
					$exists = 1;
				} else {
					$exists = 0;
				}
			}
			if ($exists) return $key;
		}
		
		return false;
	}
}
