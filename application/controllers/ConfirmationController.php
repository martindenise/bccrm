<?php

class ConfirmationController extends Zend_Controller_Action
{
	const DEL_CONF_SUCC = 'The confirmation request was succesfully canceled.';
	const DEL_CONF_FAIL = 'The confirmation request could not be canceled. Please try again.';
	const CONF_SUCC = 'The action has been confirmed.';
	const CONF_FAIL = 'The action could not be confirmed. Please try again.';

	function init() {

	}

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

	public function indexAction() {
		# check if we have some messages trough get
		if ($this->_request->get('message',null)) {
			$this->view->succesMessage = constant('self::'.$this->_request->getParam('message'));
		}
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}
	}

    public function listAction()
    {
    	//echo $_SERVER['QUERY_STRING']; die();
    	$leadType = $this->_request->getParam('type',null);
    	//echo $leadType; die();
    	if (!$leadType) {
    		$this->_redirect('confirmation');
    	}
    	else {
			# check if we have some messages trough get
			if ($this->_request->get('message',null)) {
				$this->view->succesMessage = constant('self::'.$this->_request->getParam('message'));
			}
			if ($this->_request->get('error',null)) {
				$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
			}

			# build table
			// configuration for the output table
			$this->view->tableConfig = array (
				'attributes' => array(
									'width' => '90%',
									'style' => 'margin:auto',
									'class' => 'tblStyle1'
								),
				'caption' => $leadType.' awaiting confirmation',
				'headers' => array(
									array('Name','18%'),
									array('Email Address','21%'),
									array('Market','13%'),
									array('Add Date','10%'),
									array('Sales Person','13%'),
									array('Type','12%'),
									array('Action','13%')
								),
				'alteredFields' => array (
									'3' => 'php:date("m/d/Y",%field%)',
									'6' => '<a href="'.$this->view->url(array('controller' => 'profile'),'default',true).'/?type='.$leadType.'&id=%field%" style="text-decoration:none;border:0;"><img src="'.SITE_ROOT.'/images/icons/profile_small.png" style="border:0" alt="Profile" /></a>
											<a href="'.$this->view->url(array('controller' => 'confirmation', 'action' => 'accept')).'?id=%field%" style="text-decoration:none;border:0; margin-left:15px;"><img src="'.SITE_ROOT.'/images/icons/ok_small.png" style="border:0" alt="Accept" /></a>
											<a href="'.$this->view->url(array('controller' => 'confirmation', 'action' => 'cancel')).'?id=%field%" style="text-decoration:none;border:0; margin-left:15px;" onclick="return doCancelConfirm()"><img src="'.SITE_ROOT.'/images/icons/cancel_small.png" style="border:0" alt="Delete" /></a>'
								)
				);

			// pagination used variables
			$resultOnPage = $this->_request->getParam('results',DEFAULT_RESULTS_PER_PAGE);
			$pageNo = $this->_request->getParam('page',1);

			// get the new leads from the database so they'll fit our table configuration
			$db = Zend_Registry::get('db');
			$db->setFetchMode(Zend_Db::FETCH_NUM);

			$tableName = $leadType;

/*			$sql = "SELECT SQL_CALC_FOUND_ROWS CONCAT_WS(' ',t1.first_name,t1.last_name),t1.email,t2.name,t1.date_added,t3.name,(CASE WHEN t1.approval='system' THEN 'Added by user' WHEN t1.approval='site' THEN 'Added by site' WHEN t1.approval='change' THEN 'Changed Status' WHEN t1.approval='remove' THEN 'Removal' ELSE '' END),t1.id
						FROM ".$tableName." AS t1
						LEFT JOIN campuses AS t2 ON t2.id=t1.campus_id
						LEFT JOIN users AS t3 ON t3.id=t1.sales_person_id
						WHERE t1.approval != '1'
						LIMIT ".(($pageNo-1)*$resultOnPage).", ".$resultOnPage;
			//echo $sql; die();
			$this->view->tableData = $db->fetchAll($sql);*/

			// build sql query
			$select = $db->select()
						 ->calcFoundRows(true)
						 ->from(array('t1' => $tableName),
						 		array("CONCAT_WS(' ',first_name,last_name)",
						 			  "email",
						 			  "t2.name",
						 			  "date_added",
						 			  "t3.name",
						 			  "(CASE WHEN t1.approval='system' THEN 'Added by user' WHEN t1.approval='site' THEN 'Added by site' WHEN t1.approval='change' THEN 'Changed Status' WHEN t1.approval LIKE 'remove%' THEN t1.approval ELSE '' END)",
						 			  "id"
						 		))
						 ->join(array('t2' => 'campuses'),
						 		't1.campus_id = t2.id',
						 		array())
						 ->join(array('t3' => 'users'),
						 		't1.sales_person_id = t3.id',
						 		array())
						 ->where('t1.approval != 1')
						 ->limitPage($pageNo,$resultOnPage);

			$this->view->tableData = $select->query()->fetchAll();

			# map the array with a defined function to change action type from "remove|blabla" to "Removal ?(reason)"
			$this->view->tableData = array_map(array('ConfirmationController','_mapActionType'),$this->view->tableData);
			//echo '<pre>'; print_r($this->view->tableData); die();

			# get the total number of rows
			$totalRows = $db->fetchOne('SELECT FOUND_ROWS()');

			// build pagination
			$this->view->baseLink = $this->view->url(array('controller' => 'confirmation', 'action' => 'list'));
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
    }

    /**
     * Cancel confirmation
     */
	public function cancelAction() {
		$leadType = $this->_request->getParam('type',null);
		$leadId = $this->_request->getParam('id',null);

		if (!$leadType) {
			$this->_redirect('confirmation/?error=DEL_CONF_FAIL');
		}
		if (!$leadId) {
			$this->_redirect('confirmation/list/?error=DEL_CONF_FAIL');
		}

		$db = Zend_Registry::get('db');

		# if the action is changed status,
		$approvalAction = $db->fetchCol('SELECT approval,id FROM '.$leadType.' WHERE id = ?',$leadId);
		if ($approvalAction[0] == 'change') { // do change status rollback (cancel status change)
			//////////////////////////////////////
			$rollbacked = $this->_doStatusChangeRollback($leadType,$leadId);
			//////////////////////////////////////
			if (!$rollbacked)
				$this->_redirect('confirmation/list/'.$leadType.'/?error=DEL_CONF_FAIL');
		}
		else {
			if (!$db->update($leadType,array('approval' => '1'),'id = '.$leadId)) {
				$this->_redirect('confirmation/list/'.$leadType.'/?error=DEL_CONF_FAIL');
			}
		}
		$this->_redirect('confirmation/list/'.$leadType.'/?message=DEL_CONF_SUCC');
	}

    /**
     * Confirm action
     */
	public function acceptAction() {
		$leadType = $this->_request->getParam('type',null);
		$leadId = $this->_request->getParam('id',null);

		if (!$leadType) {
			$this->_redirect('confirmation/?error=CONF_FAIL');
		}
		if (!$leadId) {
			$this->_redirect('confirmation/list/?error=CONF_FAIL');
		}

		$db = Zend_Registry::get('db');

		# if the action was "Remove", delete the user, else set approval to 1
		$approvalAction = $db->fetchCol('SELECT approval,id FROM '.$leadType.' WHERE id = ?',$leadId);
		if ($approvalAction[0] == 'remove') {
			$db->delete($leadType,'id = '.$leadId);
			$db->delete('communication',array('user_id = '.$leadId, 'user_type = \''.substr($leadType,0,-1).'\''));
			$this->_redirect('confirmation/list/'.$leadType.'/?message=CONF_SUCC');
		}
		elseif ($approvalAction[0] == 'system' || $approvalAction[0] == 'site' || $approvalAction[0] == 'change') {
			if (!$db->update($leadType,array('approval' => '1'),'id = '.$leadId)) {
				$this->_redirect('confirmation/list/'.$leadType.'/?error=CONF_FAIL');
			}
		}

		$this->_redirect('confirmation/list/'.$leadType.'/?message=CONF_SUCC');
	}


	// Protected functions
	protected function _doStatusChangeRollback($leadType,$leadId) {
		$db = Zend_Registry::get('db');

		// first check to see if we changed the status to cold lead
		$isColdLead = $db->fetchOne('SELECT cold_lead FROM '.$leadType.' WHERE id = '.$leadId);
		if ($isColdLead) {
			if (!$db->update($leadType,array('approval' => '1', 'cold_lead' => 0),'id = '.$leadId))
				return false;
		}
		else {
			# read the lead info from the table where it is after changing
			$select = $db->select()
						 ->from($leadType)
						 ->where('id = '.$leadId);

			$leadData = $db->query($select)->fetch();

			# prepare the lead data for insertion back into the old table / user type
			unset($leadData['id']); // unset the current id
			$leadData['approval'] = '1';
			$tableName = '';
			switch ($leadType) {
				case 'booked': {
						$leadData['next_followup'] = time() + FOLLOW_UP_STEP;
						unset($leadData['total_amount']);
						unset($leadData['paid_amount']);
						unset($leadData['event_date']);
						unset($leadData['class_time']);
						unset($leadData['enrolled_date']);
						$tableName = 'leads';
					} break;

				case 'graduates': {
						unset($leadData['graduated']);
						unset($leadData['completion_date']);
						$tableName = 'booked';
				}

				case 'leads': break;

				default: break;
			}

			# insert the lead data into the old table / user type
			if (!$db->insert($tableName,$leadData))
				return false;
			$lastInsertId = $db->lastInsertId();
			# change info in the communication table
			$db->update('communication',array('user_type' => $tableName, 'user_id' => $lastInsertId),'user_id = '.$leadId.' AND user_type = \''.$leadType.'\'');
			# change info in the payments table
			$db->update('payments',array('user_type' => $tableName, 'user_id' => $lastInsertId),'user_id = '.$leadId.' AND user_type = \''.$leadType.'\'');

			# delete the rollbacked lead
			$db->delete($leadType,'id = '.$leadId);
		}

		return true;
	}



	static function _mapActionType($val) {
		if (isset($val[5]) && (strpos($val[5],'remove') !== false)) {
			$reason = (substr($val[5],7)) ? substr($val[5],7) : 'No reason specified';
			$val[5] = 'Removal';
			$val[5].= ' <img src="'.SITE_ROOT.'/images/icons/bulb_small.png" style="border:0" alt="Reason" title="'.$reason.'" onmouseover="this.style.cursor=\'pointer\'" />';
		}
		return $val;
	}
}