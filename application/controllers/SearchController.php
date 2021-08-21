<?php

class SearchController extends Zend_Controller_Action
{
	const FORM_INCOMPLETE = 'Incomplete search request informations. Please try again.';
	const SEARCH_TERM_SHORT = 'Search terms should have at least 3 characters.';

	public $schools = array();

	function preDispatch()
	{
		$auth = Zend_Auth::getInstance();
		if (!$auth->hasIdentity()) {
			$this->_redirect('auth/login');
		}

		$db = Zend_Registry::get('db');

		// only show campuses for which user is allowed to search
		$where = '';
		if ('all' != Zend_Auth::getInstance()->getStorage()->read()->allowed_campuses) {
			$where = ' WHERE id='.str_replace(',',' OR id=',Zend_Auth::getInstance()->getStorage()->read()->allowed_campuses);
		}
		$this->schools = $db->fetchPairs('SELECT id,name FROM campuses'.$where);
		$this->schools['select'] = '(select)';
	}

    public function indexAction()
    {
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}

		$this->view->schools = $this->schools;
    }

    public function resultsAction()
    {
    	// if the search form was submited
		if ($this->_request->getParam('button')) {
			$campusId = $this->_request->getParam('campus_id','select');
			$firstName = $this->_request->getParam('first_name');
			$lastName = $this->_request->getParam('last_name');
			$emailAddress = $this->_request->getParam('email');
			$phoneNumber = $this->_request->getParam('phone');

			if ($this->_request->getParam('student_name')) {
				$tmp = explode(' ',$this->_request->getParam('student_name'));
				if (!empty($tmp[0])) $firstName = $tmp[0];
				if (!empty($tmp[1])) $lastName = $tmp[1];
			}

			// valid form ?
			if ($campusId == 'select' && !$firstName && !$lastName &&
				!$emailAddress && !$phoneNumber) {
					$this->_redirect('search?error=FORM_INCOMPLETE');
			}
			if ($firstName != '' && strlen($firstName) < 3) $this->_redirect('search?error=SEARCH_TERM_SHORT');
			if ($lastName != '' && strlen($lastName) < 3) $this->_redirect('search?error=SEARCH_TERM_SHORT');
			if ($emailAddress != '' && strlen($emailAddress) < 3) $this->_redirect('error=SEARCH_TERM_SHORT');
			if ($phoneNumber != '' && strlen($phoneNumber) < 3) $this->_redirect('error=SEARCH_TERM_SHORT');

			$db = Zend_Registry::get('db');

			// prepare the select statement
			# wheres
			$wheres = array();
			if ($campusId != 'select') $wheres[] = 'campus_id = '.$campusId;
			if ($firstName != '') $wheres[] = "first_name LIKE '%".$firstName."%'";
			if ($lastName != '') $wheres[] = "last_name LIKE '%".$lastName."%'";
			if ($emailAddress != '') $wheres[] = "t1.email LIKE '%".$emailAddress."%'";
			if ($phoneNumber != '') $wheres[] = "t1.phone LIKE '%".$phoneNumber."%'";
			// only leads with approved status ?
			// $wheres[] = 'approval = 1';
			$whereStmt = 'WHERE '.implode(' AND ',$wheres);

			# pagination used variables / limit
			$resultOnPage = $this->_request->getParam('results',DEFAULT_RESULTS_PER_PAGE);
			$pageNo = $this->_request->getParam('page',1);
			$limit = " LIMIT ".(($pageNo-1)*$resultOnPage).", ".$resultOnPage;

			$sqlLeads = "SELECT SQL_CALC_FOUND_ROWS CONCAT_WS(' ',t1.first_name,t1.last_name),t1.email,t2.name,t3.name,CONCAT_WS('|','leads',t1.id) as type FROM leads as t1
					JOIN campuses AS t2 ON t2.id=t1.campus_id
					JOIN users AS t3 ON t3.id=t1.sales_person_id ";
			$sqlLeads.= $whereStmt;

			$sqlEnrollments = "SELECT CONCAT_WS(' ',t1.first_name,t1.last_name),t1.email,t2.name,t3.name,CONCAT_WS('|','booked',t1.id) as type FROM booked as t1
					JOIN campuses AS t2 ON t2.id=t1.campus_id
					JOIN users AS t3 ON t3.id=t1.sales_person_id ";
			$sqlEnrollments.= $whereStmt;

			$sqlGraduates = "SELECT CONCAT_WS(' ',t1.first_name,t1.last_name),t1.email,t2.name,t3.name,CONCAT_WS('|','graduates',t1.id) as type FROM graduates as t1
					JOIN campuses AS t2 ON t2.id=t1.campus_id
					JOIN users AS t3 ON t3.id=t1.sales_person_id ";
			$sqlGraduates.= $whereStmt;

			$sqlNewLeads = "SELECT CONCAT_WS(' ',t1.first_name,t1.last_name),t1.email,t2.name,t3.name,CONCAT_WS('|','new_leads',t1.id) as type FROM new_leads as t1
					JOIN campuses AS t2 ON t2.id=t1.campus_id
					JOIN users AS t3 ON t3.id=t1.sales_person_id ";
			$sqlNewLeads.= $whereStmt;

			$sql = $sqlLeads.' UNION '.$sqlEnrollments.' UNION '.$sqlGraduates.' UNION '.$sqlNewLeads;
			$sql.= $limit;

			$db->setFetchMode(Zend_Db::FETCH_NUM);
			$this->view->searchResults = $db->fetchAll($sql);
			# map the array with a defined function to change action type from "remove|blabla" to "Removal ?(reason)"
			$this->view->searchResults = array_map(array('SearchController','_mapProfileLink'),$this->view->searchResults);

			// configuration for the output table
			$this->view->tableConfig = array (
				'attributes' => array(
									'width' => '80%',
									'style' => 'margin:auto',
									'class' => 'tblStyle1'
								),
				'caption' => 'Search results',
				'headers' => array(
									array('Name','20%'),
									array('Email Address','23%'),
									array('Market','18%'),
									array('Sales Person','16%'),
									array('Action','5%')
								),
				'alteredFields' => array (
									'4' => '<a href="'.$this->view->url(array('controller' => 'profile'),'default',true).'/?%field%" style="text-decoration:none;border:0;margin-right:15px;"><img src="'.SITE_ROOT.'/images/icons/profile_small.png" style="border:0" alt="Profile" /></a>',
								)
				);

				# get the total number of rows
				$totalRows = $db->fetchOne('SELECT FOUND_ROWS()');

				// build pagination
				$this->view->baseLink = $this->view->url(array('controller' => 'search', 'action' => 'results'));
				$this->view->baseLink.= '?'.$_SERVER['QUERY_STRING'].'&';

				$this->view->paginationConfig = array(
							'base_url' => $this->view->baseLink,
							'num_items' => $totalRows,
							'per_page' => $resultOnPage,
							'page_no' => $pageNo,
							'class' => 'paginationDiv'
						);

				$this->view->perPageLinksClass = 'perPageLinks';
		}
		else {
			$this->_redirect('search');
		}
    }

	static function _mapProfileLink($val) {
		$tmp = explode('|',$val[4]);
		$val[4] = 'type='.$tmp[0].'&id='.$tmp[1];
		return $val;
	}
}
