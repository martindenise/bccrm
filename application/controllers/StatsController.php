<?php


class StatsController extends Zend_Controller_Action
{
	const INVALID_PRINT = 'Invalid print/export request. Please try again.';
	const FORM_INCOMPLETE = 'Incomplete request information. Please try again.';
	const INVALID_PREV_DATE = 'The previous period dates you entereded are more recent than the period dates you entered.';

	public $schools = array();
	public $salesPersons = array();

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

		$this->salesPersons = $db->fetchPairs('SELECT id,name FROM users WHERE id > 0');
		$this->salesPersons['select'] = '(select)';
	}

    public function indexAction()
    {
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}

		$this->view->schools = $this->schools;
		$this->view->salesPersons = $this->salesPersons;
		$this->view->userIsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;

		if ($this->_request->isPost()) {
			$fromDate = $this->_request->getParam('from_date');
			$toDate = $this->_request->getParam('to_date');
			$searchBy = $this->_request->getParam('search_by');

			// params not complete
			if (!$fromDate || !$toDate || !$searchBy) {
				$this->_redirect('stats/?error=FORM_INCOMPLETE');
			}
			if ($searchBy == 'school' && ($this->_request->getParam('campus_id','select') == 'select')) {
				$this->_redirect('stats/?error=FORM_INCOMPLETE');
			}
			if ($searchBy == 'sales_person' && ($this->_request->getParam('sales_person_id','select') == 'select')) {
				$this->_redirect('stats/?error=FORM_INCOMPLETE');
			}


			$db = Zend_Registry::get('db');


			if ($searchBy == 'school') {
				$searchById = $this->_request->getParam('campus_id');
				$searchByValue = $db->fetchOne('SELECT name FROM campuses WHERE id = '.$this->_request->getParam('campus_id'));
			}
			else {
				$searchById = $this->_request->getParam('sales_person_id');
				$searchByValue = $db->fetchOne('SELECT name FROM users WHERE id = '.$this->_request->getParam('sales_person_id'));
			}


			# DO STATS
			// leads to enrollments ratio
			$this->view->assign('leadsToEnrollmentsRatio',$this->_leadsToEnrollmentsRatio($fromDate,$toDate,$searchBy,$searchById));
			// Average number of lead contact attempts (total of SMS, email, phone)
			$this->view->assign('avgContactAtt',$this->_avgContactAtt($fromDate,$toDate,$searchBy,$searchById));
			// Average time to enroll a lead
			$this->view->assign('avgTimeToEnroll',$this->_avgTimeToEnroll($fromDate,$toDate,$searchBy,$searchById));
			// Average initial response time (new leads)
			$this->view->assign('avgInitialResponseTime',$this->_avgInitialResponseTime($fromDate,$toDate,$searchBy,$searchById));

			$this->view->assign('fromDate',$fromDate);
			$this->view->assign('toDate',$toDate);
			$this->view->assign('searchBy',($searchBy == 'school') ? 'Market' : 'Representative');
			$this->view->assign('searchByValue',$searchByValue);

			// encode sql for export/print
			$this->view->exportData = base64_encode(
										Zend_Json::encode(
											array('fromDate' => $fromDate,
												'toDate' => $toDate,
												'searchBy' => $this->view->searchBy,
												'searchByValue' => $this->view->searchByValue,
												'leadsToEnrollmentsRatio' => $this->view->leadsToEnrollmentsRatio,
												'avgContactAtt' => $this->view->avgContactAtt,
												'avgTimeToEnroll' => $this->view->avgTimeToEnroll,
												'avgInitialResponseTime' => $this->view->avgInitialResponseTime
												)
										)
									);

			// prepare the output
			$this->view->output = $this->view->render('stats/stats_results.phtml');
		}
		else {
			$this->view->output = $this->view->render('stats/stats_form.phtml');
		}
    }

	/**
	 * Export/print stats
	 *
	 */
	public function exportstatsAction() {
		$action = $this->_request->getParam('type');
		$statsData = $this->_request->getParam('data');

		if (!$action || !$statsData) {
			$this->_redirect('stats/?error=INVALID_PRINT');
		}

		$statsData = Zend_Json::decode(base64_decode($statsData));

		// total results
		$outputHtml = '<html>';
		if (count($statsData) > 0) {
			// add auto-print on page load
			if ($action == 'print') {
				$outputHtml .= '<body onload="window.print()">';
			}
			else {
				$outputHtml.= '<body>';
			}

			// build the table
			$outputHtml.= '
				<br /><br /><br />

				<table width="400" border="1" style="margin: auto;">
				  <thead>
				  <tr>
				    <th colspan="2">Stats</th>
				    </tr>
				  </thead>
				  <tbody>
				  <tr>
				    <th width="200" scope="row"><div align="right"><strong>Period: </strong></div></th>
				    <td width="200"><div align="left">' . ($statsData['fromDate']) . ' to ' . ($statsData['toDate']) . '</div></td>
				  </tr>
				  <tr>
				    <th scope="row" style="border-bottom: 1px solid white;"><div align="right"><strong>
				      ' . ($statsData['searchBy']) . '
				      :</strong></div></th>
				    <td style="border-bottom: 1px solid #8997a0;"><div align="left">
				      ' . ($statsData['searchByValue']) . '
				    </div></td>
				  </tr>
				  <tr>
				    <th scope="row"><div align="right"><strong>Leads to Booked Ratio:</strong></div></th>
				    <td><div align="left">' . ($statsData['leadsToEnrollmentsRatio']) . '</div></td>
				  </tr>
				  <tr>
				    <th scope="row"><div align="right"><strong>Average contact attempts:</strong></div></th>
				    <td><div align="left">' . ($statsData['avgContactAtt']) . '</div></td>
				  </tr>
				  <tr>
				    <th scope="row"><div align="right">Average time to enroll a lead :</div></th>
				    <td><div align="left">' . ($statsData['avgTimeToEnroll']) . '</div></td>
				  </tr>
				  <tr>
				    <th scope="row"><div align="right">Average initial response time</div></th>
				    <td><div align="left">' . ($statsData['avgInitialResponseTime']) . '</div></td>
				  </tr>
				  </tbody>
				</table></body></html>';

			$this->_helper->layout()->disableLayout(true);
			$this->_helper->viewRenderer->setNoRender(true);
			if ($action == 'print') {
				echo $outputHtml;
			}
			else {
				// disable error reporting
				error_reporting(0);
				ini_set('display_errors',0);

				// disable zend autoload
				Zend_Loader::registerAutoload('Zend_Loader',false);

				require(SITE_PATH.'/library/html2pdf/html2fpdf.php');

				$pdf=new HTML2FPDF('P','mm','A4');
				$pdf->AddPage();
				$pdf->WriteHTML($outputHtml);
				$pdf->Output("stats_".str_replace('/','_',str_replace(' ','_',($statsData['fromDate']) . ' to ' . ($statsData['toDate']))).".pdf","D");
			}
		}
	}

    /**
     * Action for ranks
     *
     */
    public function ranksAction()
    {
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}

		$this->view->schools = $this->schools;
		$this->view->salesPersons = $this->salesPersons;
		$this->view->userIsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;

		if ($this->_request->isPost()) {
			$fromDate = $this->_request->getParam('from_date');
			$toDate = $this->_request->getParam('to_date');
			$searchBy = $this->_request->getParam('search_by');
			$rankBy = $this->_request->getParam('rank_by');

			// params not complete
			if (!$fromDate || !$toDate || !$searchBy || !$rankBy) {
				$this->_redirect('stats/ranks/?error=FORM_INCOMPLETE');
			}

			$dbData = $this->_getDbData();
			$this->view->ranksData = !empty($dbData[0]) ? $dbData[0] : array();
			$this->view->ranksDataPrev = !empty($dbData[1]) ? $dbData[1] : array();

			$this->view->assign('fromDate',$fromDate);
			$this->view->assign('toDate',$toDate);
			$this->view->assign('searchBy',($searchBy == 'school') ? 'Market' : 'Representative');
			$this->view->assign('rankBy',($rankBy == 'enrolls') ? 'No. of Bookings' : 'Sales $');

			// prepare the output
			if (count($this->view->ranksDataPrev)) {
				$this->view->assign('fromDate2',$this->_request->getParam('from_date2'));
				$this->view->assign('toDate2',$this->_request->getParam('to_date2'));
				$this->view->output = $this->view->render('stats/ranks_results_compare.phtml');

				// print content
				preg_match('#\<table.*\>(.+?)\<\/table\>#s',$this->view->output,$matches);
				$this->view->printContent = $matches[0];
				$this->view->printContent = preg_replace('/\<table (width=".*?")(.*?)\>/','<table $1 style="margin:auto" border="1">',$this->view->printContent,1);
				$this->view->printContent = preg_replace('/\<caption\>.*?\<\/caption\>/','',$this->view->printContent,1);
				$this->view->printContent = '<div style="margin:auto; width:350px; text-align:center; font-size:20px;">'
											. '<strong>Rank by ' . $this->view->rankBy . ' / ' . $this->view->searchBy . '</strong></div><br />'
											. $this->view->printContent;
			}
			else {
				$this->view->output = $this->view->render('stats/ranks_results.phtml');

				// print content
				preg_match('#\<table.*\>(.+?)\<\/table\>#s',$this->view->output,$matches);
				$this->view->printContent = $matches[0];
				$this->view->printContent = preg_replace('/\<table (width=".*?")(.*?)\>/','<table $1 style="margin:auto" border="1">',$this->view->printContent,1);
				$this->view->printContent = preg_replace('/\<caption\>.*?\<\/caption\>/','',$this->view->printContent,1);
				$this->view->printContent = '<div style="margin:auto; width:250px; text-align:center; font-size:20px;"><strong>Sales Rank</strong></div><br />'
											. $this->view->printContent;
			}
		}
		else {
			$this->view->output = $this->view->render('stats/ranks_form.phtml');
		}

    }

    public function adminAction()
    {
		$db = Zend_Registry::get('db');
		$sqlSalesLastDays = 'SELECT SUM(t1.total_amount) AS value,t2.name FROM booked AS t1
								JOIN users AS t2 ON t2.id=t1.sales_person_id
								WHERE DATE(enrolled_date) >= \''.date("Y-m-d",time()-604800).'\' AND t1.approval = 1 GROUP BY t2.name';
		$rs = $db->fetchAll($sqlSalesLastDays);

		$sqlCampusSales   = 'SELECT SUM(t1.total_amount) AS value,t2.name FROM booked AS t1
								JOIN campuses AS t2 ON t2.id=t1.campus_id
								WHERE DATE(enrolled_date) >= \''.date("Y-m-d",time()-604800).'\' AND t1.approval = 1 GROUP BY t2.name';
		$rs2 = $db->fetchAll($sqlCampusSales);

		$this->view->bestSalesP = (!empty($rs[0])) ? $rs[0]['name'].' / $'.$rs[0]['value'] : 'N/A';
		$this->view->worstSalesP = (!empty($rs[count($rs)-1])) ? $rs[count($rs)-1]['name'].' / $'.$rs[count($rs)-1]['value'] : 'N/A';

		$this->view->schoolsSales = (is_array($rs2)) ? $rs2 : array() ;
    }

    /**
     * Builds the rank tables needed in ranksAction
     *
     */

	protected function _getDbData() {
		$fromDatePrev = $this->_request->getParam('from_date2');
		$toDatePrev = $this->_request->getParam('to_date2');

		// previous period params check
		if ($this->_request->getParam('comparation')) {
			if (!$fromDatePrev || !$toDatePrev) {
				$this->_redirect('stats/ranks/?error=FORM_INCOMPLETE');
			}
		}

		$fromDate = $this->_request->getParam('from_date');
		$toDate = $this->_request->getParam('to_date');
		$searchBy = $this->_request->getParam('search_by');
		$rankBy = $this->_request->getParam('rank_by');

		// check periods
		if (strtotime($fromDatePrev) > strtotime($fromDate))
			$this->_redirect('stats/ranks/?error=INVALID_PREV_DATE');
		if (strtotime($toDatePrev) > strtotime($toDate))
			$this->_redirect('stats/ranks/?error=INVALID_PREV_DATE');

		$db = Zend_Registry::get('db');

		// build the selects
		$select = $db->select();
		if ($searchBy == 'school') { // we search by school
			if ($rankBy == 'enrolls') { // by number of enrollments
				$select->from(array('t1' => 'booked'), array('COUNT(*) AS value','t2.name'));
				$select->join(array('t2' => 'campuses'),'t2.id = t1.campus_id',array());
			}
			else { // by sales $
				$select->from(array('t1' => 'booked'), array('SUM(total_amount) AS value','t2.name'));
				$select->join(array('t2' => 'campuses'),'t2.id = t1.campus_id',array());
			}
			$select->group('campus_id');
		}
		elseif ($searchBy == 'sales_person') { // we search by sales person
			if ($rankBy == 'enrolls') { // by number of enrollments
				$select->from(array('t1' => 'booked'), array('COUNT(*) AS value','t2.name'));
				$select->join(array('t2' => 'users'),'t2.id = t1.sales_person_id',array());
			}
			else { // by sales $
				$select->from(array('t1' => 'booked'), array('SUM(total_amount) AS value','t2.name'));
				$select->join(array('t2' => 'users'),'t2.id = t1.sales_person_id',array());
			}
			$select->group('sales_person_id');
		}


		// add first time period
		$fromDateTmp = date("Y-m-d",strtotime($fromDate));
		$toDateTmp = date("Y-m-d",strtotime($toDate));

		$select->order('value DESC');
		$select->where('DATE(enrolled_date) BETWEEN "'.$fromDateTmp.'" AND "'.$toDateTmp.'"');
		//echo $select->__toString(); die();
		$rs = $select->query()->fetchAll();

		// if we have a previous period entered, duplicate select object and add the previuos
		// time period to it
		if ($this->_request->getParam('comparation')) {
			$fromDatePrevTmp = date("Y-m-d",strtotime($fromDatePrev));
			$toDatePrevTmp = date("Y-m-d",strtotime($toDatePrev));
			$select->reset('where');
			$select->where('DATE(enrolled_date) BETWEEN "'.$fromDatePrevTmp.'" AND "'.$toDatePrevTmp.'"');

			$rsPrev = $select->query()->fetchAll();
/*			echo '<br>qq<br>';
			echo $select->__toString();
			print_r($rsPrev);
			die();*/
		}

		if (!empty($rsPrev))
			return array($rs,$rsPrev);
		return array($rs);
	}

    /**
     * Calculate the leads to enrollments ratio for a specific location or sales person in a specific period
     *
     * @param string $from
     * @param string $to
     * @param string $searchBy
     * @param string $searchById
     */
    protected function _leadsToEnrollmentsRatio($fromDate, $toDate, $searchBy, $searchById)
    {
   		$where = '';
    	if ($searchBy == 'school') {
    		$where = 'campus_id = '.$searchById;
    	}
    	else {
    		$where = 'sales_person_id = '.$searchById;
    	}

    	$sqlLeads = 'SELECT COUNT(*) FROM leads WHERE '.$where;
    	$sqlEnrollments = 'SELECT COUNT(*) FROM booked WHERE '.$where;

		$fromDateTmp = date("Y-m-d",strtotime($fromDate));
		$toDateTmp = date("Y-m-d",strtotime($toDate));

		$sqlLeads.= ' AND DATE(toLead_date) BETWEEN  "'.$fromDateTmp.'" AND "'.$toDateTmp.'"';
		$sqlEnrollments.= ' AND DATE(enrolled_date) BETWEEN  "'.$fromDateTmp.'" AND "'.$toDateTmp.'"';

		$db = Zend_Registry::get('db');

		$noLeads = $db->fetchOne($sqlLeads);
		$noEnrollments = $db->fetchOne($sqlEnrollments);

		if ($noLeads < 1) {
			return 'No leads added during this period';
		}
		if ($noEnrollments < 1) {
			return 'No bookings added during this period';
		}

		$p = round(($noEnrollments*100) / ($noLeads+$noEnrollments),2);
		return $p.'%';
    }

    /**
     * Average number of lead contact attempts (total of SMS, email, phone)
     *
     * @param string $fromDate
     * @param string $toDate
     * @param string $searchBy
     * @param string $searchById
     */

    protected function _avgContactAtt($fromDate, $toDate, $searchBy, $searchById)
    {
    	$db = Zend_Registry::get('db');
    	$totalContactAttempts = 0;
    	$totalContactedLeads = 0;

		$sqlLogs = 'SELECT user_id,user_type,log FROM communication WHERE log != \'\' AND user_type = \'leads\'';
		$rows = $db->fetchAll($sqlLogs);

		if (!$rows) return 'No communication';

		# Define the check function we will use to check if a log entry belongs to a user or a school we want
		if ($searchBy == 'school') {
			function checkEntry($id,$spId,$userId,$userType) {
				global $db;
				return $db->fetchOne('SELECT id FROM '.$userType.' WHERE id = '.$userId.' AND campus_id = '.$id);
			}
		}
		else {
			function checkEntry($id,$spId,$userId,$userType) {
				return ($id == $spId);
			}
		}

		# Prepare the date period
		$fromTS = strtotime($fromDate);
		$toTS = (strtotime($toDate)+86399);

		foreach ($rows as $log) {
			$log['log'] = Zend_Json::decode($log['log']);
			if (is_array($log['log'])) {
				$tmp = $totalContactedLeads;
				foreach ($log['log'] as $entry) {
					// if the entry is in our time period
					if ($entry['date_time'] > $fromTS && $entry['date_time'] < $toTS) {
						// increment total leads contacted in this time period
						if ($totalContactedLeads - $tmp < 1) $totalContactedLeads++;
						// check to see if the entry is for our sales person or school
						if (checkEntry($searchById,$entry['sales_person'],$log['user_id'],$log['user_type'])) {
							$totalContactAttempts++;
						}
					}
				}
			}
		}

		if (1 > $totalContactedLeads) {
			return 'No leads contacted during this period';
		}

		if (1 > $totalContactAttempts) {
			return 'No contact attempts during this period';
		}

		return round($totalContactAttempts/$totalContactedLeads,4).' attempts/lead';
    }

    /**
     * Average time to enroll a lead
     *
     * @param string $fromDate
     * @param string $toDate
     * @param string $searchBy
     * @param string $searchById
     */

    protected function _avgTimeToEnroll($fromDate, $toDate, $searchBy, $searchById)
    {
		$db = Zend_Registry::get('db');

		$where = '';
    	if ($searchBy == 'school') {
    		$where = 'campus_id = '.$searchById;
    	}
    	else {
    		$where = 'sales_person_id = '.$searchById;
    	}

		$fromDateTmp = date("Y-m-d",strtotime($fromDate));
		$toDateTmp = date("Y-m-d",strtotime($toDate));

		$where.= ' AND DATE(enrolled_date) BETWEEN  "'.$fromDateTmp.'" AND "'.$toDateTmp.'"';
		$where.= ' AND toLead_date != 0';

		$sqlEnrollments = 'SELECT toLead_date,enrolled_date FROM booked WHERE '.$where;
		$rs = $db->fetchAll($sqlEnrollments);

		if (!$rs) return 'No bookings';

		$totalValidEnrollments = 0;
		$timeDifference = 0;

		foreach ($rs as $row) {
			$tmp = strtotime($row['enrolled_date']) - strtotime($row['toLead_date']);
			if ($tmp > 0) {
				$timeDifference+= $tmp;
				$totalValidEnrollments++;
			}
		}

		if (!$timeDifference || !$totalValidEnrollments)
			return 'N/A';

		return $this->strTime($timeDifference / $totalValidEnrollments);
    }

    /**
     * Average initial response time (new leads)
     *
     * @param string $fromDate
     * @param string $toDate
     * @param string $searchBy
     * @param string $searchById
     */


    protected function _avgInitialResponseTime($fromDate, $toDate, $searchBy, $searchById)
    {
		$db = Zend_Registry::get('db');
		$validUsers = array();

		$sqlLogs = 'SELECT user_id,user_type,log FROM communication WHERE log != \'\' AND user_type = \'leads\'';
		$rows = $db->fetchAll($sqlLogs);

		if (!$rows) return 'No communication';

		# Define the check function we will use to check if a log entry belongs to a user or a school we want
		// I commented this out because the proper checkEntry function is declared in _avgContactAtt()
		/*if ($searchBy == 'school') {
			function checkEntry($id,$spId,$userId,$userType) {
				global $db;
				return $db->fetchOne('SELECT id FROM '.$userType.' WHERE id = '.$userId.' AND campus_id = '.$id);
			}
		}
		else {
			function checkEntry($id,$spId,$userId,$userType) {
				return ($id == $spId);
			}
		}*/

		# Prepare the date period
		$fromTS = strtotime($fromDate);
		$toTS = (strtotime($toDate)+86399);

		# Extract the first contact dates for the corresponding leads
		foreach ($rows as $log) {
			$log['log'] = Zend_Json::decode($log['log']);
			if (is_array($log['log'])) {
				$entry = $log['log'][0];
				// if the entry is in our time period
				if ($entry['date_time'] > $fromTS && $entry['date_time'] < $toTS) {
					// check to see if the entry is for our sales person or school
					if (checkEntry($searchById,$entry['sales_person'],$log['user_id'],$log['user_type'])) {
						$validUsers[] = array('id' => $log['user_id'], 'timestamp' => $entry['date_time']);
					}
				}
			}
		}

		# Find out when the lead was added and compute the average time
		if (!$validUsers) return 'N/A';

		$totalValidEnrollments = 0;
		$timeDifference = 0;

		foreach ($validUsers as $row) {
			$dateAdded = $db->fetchOne('SELECT date_added FROM leads WHERE id = '.$row['id']);
			if ($dateAdded) {
				$tmp = $row['timestamp'] - $dateAdded;
				if ($tmp > 0) {
					$timeDifference+= $tmp;
					$totalValidEnrollments++;
				}
			}
		}

		if (!$timeDifference || !$totalValidEnrollments)
			return 'N/A';

		return $this->strTime($timeDifference / $totalValidEnrollments);
    }

    static function strTime($s)
    {
    	$d = intval($s/86400);
    	$s -= $d*86400;

    	$h = intval($s/3600);
    	$s -= $h*3600;

    	$m = intval($s/60);
    	$s -= $m*60;

    	if ($d) $str = $d . 'd ';
    	if ($h) $str .= $h . 'h ';
    	if ($m) $str .= $m . 'm ';

    	return $str;
	}
}