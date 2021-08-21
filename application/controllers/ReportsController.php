<?php

class ReportsController extends Zend_Controller_Action
{
	const INVALID_PRINT = 'Invalid print/export request. Please try again.';
	const FORM_INCOMPLETE = 'Incomplete request information. Please try again.';
	const START_FORM_INCOMPLETE = 'You must choose at least a market and a date.';

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
		# check if we have some messages trough get
		if ($this->_request->get('message',null)) {
			$this->view->succesMessage = constant('self::'.$this->_request->getParam('message'));
		}
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}

		$this->view->schools = $this->schools;
		$this->view->salesPersons = $this->salesPersons;
		$this->view->userIsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;

		if ($this->_request->isPost()) {
			$fromDate = $this->_request->getParam('from_date');
			$toDate = $this->_request->getParam('to_date');
			$what = $this->_request->getParam('what');
			$searchBy = $this->_request->getParam('search_by');

			// params not complete
			if (!$fromDate || !$toDate || !$searchBy || !$what) {
				$this->_redirect('reports/?error=FORM_INCOMPLETE');
			}

			if ($searchBy == 'school' && ($this->_request->getParam('campus_id','select') == 'select')) {
				$this->_redirect('reports/?error=FORM_INCOMPLETE');
			}
			if ($searchBy == 'sales_person' && ($this->_request->getParam('sales_person_id','select') == 'select')) {
				$this->_redirect('reports/?error=FORM_INCOMPLETE');
			}

			$db = Zend_Registry::get('db');

			if ($what == 'sales') {
				list ($fromM,$fromD,$fromY) = explode('/',$fromDate);
				list ($toM,$toD,$toY) = explode('/',$toDate);
				$fromDateTmp = $fromY.'-'.$fromM.'-'.$fromD;
				$toDateTmp = $toY.'-'.$toM.'-'.$toD;

				$select = $db->select()
							 ->from('booked',array('total' => 'SUM(total_amount)'))
							 ->where('DATE(enrolled_date) BETWEEN  "'.$fromDateTmp.'" AND "'.$toDateTmp.'"')
							 ->where('approval = 1')
							 ->where('cold_lead = 0');
				if ($searchBy == 'school') {
					$select->where('campus_id = '.$this->_request->getParam('campus_id'));
					$searchByValue = $db->fetchOne('SELECT name FROM campuses WHERE id = '.$this->_request->getParam('campus_id'));
				}
				else {
					$select->where('sales_person_id = '.$this->_request->getParam('sales_person_id'));
					$searchByValue = $db->fetchOne('SELECT name FROM users WHERE id = '.$this->_request->getParam('sales_person_id'));
				}

				$sales = $select->query()->fetch();

				// get the total paid amount
				$oldSelectString = $select->__toString();
				$select->reset(Zend_Db_Select::COLUMNS );
				$select->reset(Zend_Db_Select::FROM );
				$select->from('booked',array('id'));
				$selectString = $select->__toString();
				unset($select);

				$select = $db->select()
							 ->from('payments',array('method','total_value' => 'SUM(amount)'))
							 ->where('user_id IN ('.$selectString.')')
							 ->where('user_type = \'booked\'')
							 ->group('method');
				$sales['paid'] = $db->fetchPairs($select->__toString());

				// prepare vars for output
				$this->view->assign('sales',$sales);
				$this->view->assign('fromDate',$fromDate);
				$this->view->assign('toDate',$toDate);
				$this->view->assign('searchBy',($searchBy == 'school') ? 'Market' : 'Representative');
				$this->view->assign('searchByValue',$searchByValue);

				// encode sql for export/print
				$this->view->printSql = base64_encode($oldSelectString.'||'.$select->__toString());
				$this->view->exportData = base64_encode(
											Zend_Json::encode(
												array('fromDate' => $fromDate,
													'toDate' => $toDate,
													'searchBy' => $this->view->searchBy,
													'searchByValue' => $this->view->searchByValue
													)
											)
										);

				$this->view->assign('output',$this->view->render('reports/index_sales.phtml'));

				//echo '<pre>'; print_r($this->view->sales); die();
			}
			else {
				if ($what == 'new_leads') {
					list ($fromM,$fromD,$fromY) = explode('/',$fromDate);
					list ($toM,$toD,$toY) = explode('/',$toDate);
					$select = $db->select()
								 ->from(array('t1' => 'new_leads'),array("CONCAT_WS(' ',first_name,last_name)",'email','date_added','aa' => 't2.name','t3.name','id'))
								 ->join(array('t2' => 'users'),'t2.id = t1.sales_person_id',array())
								 ->join(array('t3' => 'campuses'),'t3.id = t1.campus_id',array())
								 ->where('date_added > '.mktime(0,0,0,$fromM,$fromD,$fromY))
								 ->where('date_added < '.mktime(23,59,59,$toM,$toD,$toY))
								 ->where('cold_lead = 0');
					// table caption
					$blaaaaa = 'New Leads from '.$fromDate.' to '.$toDate;
					// user profile type
					$bleeeee = 'new_leads';
				}	elseif ($what == 'leads') {
					list ($fromM,$fromD,$fromY) = explode('/',$fromDate);
					list ($toM,$toD,$toY) = explode('/',$toDate);
					$select = $db->select()
								 ->from(array('t1' => 'leads'),array("CONCAT_WS(' ',first_name,last_name)",'email','date_added','aa' => 't2.name','t3.name','id'))
								 ->join(array('t2' => 'users'),'t2.id = t1.sales_person_id',array())
								 ->join(array('t3' => 'campuses'),'t3.id = t1.campus_id',array())
								 ->where('date_added > '.mktime(0,0,0,$fromM,$fromD,$fromY))
								 ->where('date_added < '.mktime(23,59,59,$toM,$toD,$toY))
								 ->where('cold_lead = 0');
					// table caption
					$blaaaaa = 'Leads from '.$fromDate.' to '.$toDate;
					// user profile type
					$bleeeee = 'leads';
				}
				elseif ($what == 'booked') {
					list ($fromM,$fromD,$fromY) = explode('/',$fromDate);
					list ($toM,$toD,$toY) = explode('/',$toDate);
					$fromDateTmp = $fromY.'-'.$fromM.'-'.$fromD;
					$toDateTmp = $toY.'-'.$toM.'-'.$toD;
					$select = $db->select()
								 ->from(array('t1' => 'booked'),array("CONCAT_WS(' ',first_name,last_name)",'email','date_added','aa' => 't2.name','t3.name','id'))
								 ->join(array('t2' => 'users'),'t2.id = t1.sales_person_id',array())
								 ->join(array('t3' => 'campuses'),'t3.id = t1.campus_id',array())
								 ->where('DATE(enrolled_date) BETWEEN  "'.$fromDateTmp.'" AND "'.$toDateTmp.'"')
								 //->where('DATE(enrolled_date) <= "'.$toDateTmp.'"')
								 ->where('approval = 1')
								 ->where('cold_lead = 0');
					// table caption
					$blaaaaa = 'Booked from '.$fromDate.' to '.$toDate;
					// user profile type
					$bleeeee = 'booked';
				}

				if ($searchBy == 'school')
					$select->where('campus_id = '.$this->_request->getParam('campus_id'));
				else
					$select->where('sales_person_id = '.$this->_request->getParam('sales_person_id'));

				//echo $select->__toString(); die();
				$this->view->tableConfig = array (
					'attributes' => array(
										'width' => '70%',
										'style' => 'margin:auto',
										'class' => 'tblStyle1'
									),
					'caption' => $blaaaaa,
					'headers' => array(
											array('Name','20%'),
											array('Email Address','23%'),
											array('Add Date','12%'),
											array('Sales Person','16%'),
											array('Market','16%'),
											array('Profile','6%'),
										),
					'alteredFields' => array (
										'2' => 'php:date("m/d/Y",%field%)',
										'5' => '<a href="'.$this->view->url(array('controller' => 'profile'),'default',true).'/?type='.$bleeeee.'&id=%field%" style="text-decoration:none;border:0;margin-right:15px;">View profile</a>'
									)
					);
				// encode sql for export/print
				$this->view->printSql = base64_encode($select->__toString());
				$this->view->tblCaption = base64_encode($blaaaaa);

				$this->view->results = $select->query()->fetchAll();

				$this->view->output = $this->view->render('reports/index_results.phtml');
			}
		}
		else {
			$this->view->output = $this->view->render('reports/index_form.phtml');
		}
    }

	public function startsAction() {
		if ($this->_request->get('error',null)) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('error'));
		}

		$this->view->schools = $this->schools;

		if ($this->_request->isPost()) {
			$schoolId = $this->_request->getParam('campus_id');
			$date = $this->_request->getParam('date');
			$time = $this->_request->getParam('time','none');
			$display = $this->_request->getParam('display','all');

			// params not complete
			if (!$schoolId || !$date || $schoolId == 'select') {
				$this->_redirect('reports/starts/?error=START_FORM_INCOMPLETE');
			}

			$db = Zend_Registry::get('db');
			$select = $db->select()
						 ->from(array('t1' => 'booked'),
						 		array("CONCAT_WS(' ',first_name,last_name)",
						 			  "email",
						 			  "class_time",
						 			  ($this->_request->__isset('show_due')) ? "(SELECT t1.total_amount-COALESCE((SUM(amount)),0) FROM payments WHERE user_id = t1.id AND user_type='booked')" : "t2.name",
						 			  "id"
						 		))
						 ->join(array('t2' => 'users'),
						 		't1.sales_person_id = t2.id',
						 		array())
						 ->where('campus_id = '.$schoolId)
						 ->where('event_date = \''.$date.'\'')
						 ->where('approval = 1')
						 ->where('cold_lead = 0');

			if ($time != 'none')
				$select->where('class_time = '.$time);

			// prepare the sql where statements for printing/exporting
			$this->view->printSql = base64_encode(Zend_Json::encode($select->getPart(Zend_Db_Select::WHERE)));
			//echo base64_decode(Zend_Json::decode($this->view->printSql)); die();

			$this->view->tableConfig = array (
				'attributes' => array(
									'width' => '60%',
									'style' => 'margin:auto',
									'class' => 'tblStyle1'
								),
				'caption' => 'Starts for '.$date,
				'headers' => array(
									array('Name','32%'),
									array('Email Address','32%'),
									array('Class time','6%'),
									array('Sales Person','20%'),
									array('Profile','10%')
								),
				'alteredFields' => array (
									'4' => '<a href="'.$this->view->url(array('controller' => 'profile'),'default',true).'/?type=booked&id=%field%" style="text-decoration:none;border:0;margin-right:15px;">View profile</a>'
								)
			);

			// if we need to show the due balance
			if ($this->_request->__isset('show_due')) {
				$this->view->tableConfig['headers'][3] = array('Amount due','20%');
				$this->view->printSql.= '&due=1';
			}
			// show only students with due balance !
			if ($this->_request->__isset('show_only_due')) {
				$select->where('t1.total_amount > (SELECT COALESCE((SUM(amount)),0) FROM payments WHERE user_id = t1.id AND user_type=\'booked\')');
				$this->view->printSql = base64_encode(Zend_Json::encode($select->getPart(Zend_Db_Select::WHERE)));
				$this->view->printSql.= '&due=1';
			}

			$this->view->results = $select->query()->fetchAll();
			//echo '<pre>'; print_r($this->view->results); die();
			$this->view->output = $this->view->render('reports/starts_results.phtml');
		}
		else {
			$this->view->output = $this->view->render('reports/starts_form.phtml');
		}
	}

	/**
	 * Export/print reports
	 *
	 */
	public function exportreportsAction() {
		$sql = $this->_request->getParam('sql');
		$action = $this->_request->getParam('type');
		$tblTitle = $this->_request->getParam('caption');

		if (!$sql || !$action || !$tblTitle) {
			$this->_redirect('reports/?error=INVALID_PRINT');
		}

		// get the info from the database
		$db = Zend_Registry::get('db');
		$db->setFetchMode(Zend_Db::FETCH_NUM);
		$sql = base64_decode($sql);

		$rs = $db->fetchAll($sql);

		// total results
		$totalRs = count($rs);
		$outputHtml = '<html>';
		if ($totalRs > 0) {
			// no. of pages
			$pagesNo = ceil($totalRs / 20);

			// add auto-print on page load
			if ($action == 'print') {
				$outputHtml .= '<body onLoad="window.print()">';
			}
			else {
				$outputHtml.= '<body>';
			}
			///////////////////
			//$outputHtml = '<body>';
			$outputHtml .= '
						<div style="margin:auto; ';
			if ($action == 'print')
				$outputHtml.= 'width: 1600px;';
			else
				$outputHtml.= 'width: 1000px;';
			$outputHtml.= 'text-align:center; font-size:20px;"><strong><font size="5">'.base64_decode($tblTitle).'</font></strong></div>
						<br />';
			for ($i = 0; $i < $pagesNo; $i++) {
				// add table definition and header
				$outputHtml.= '<table style="margin: auto; ';
				// add print page break only if it's not last table
				if ($i < $pagesNo-1) $outputHtml.= 'page-break-after:always;';
				$outputHtml.= '" border="1" class="" ';
				if ($action == 'print')
					$outputHtml.= 'width="1600">';
				else
					$outputHtml.= 'width="1000">';
				// add headers
				$outputHtml.= '<tr>
								  <th width="3%" align="center"><font size="5">No</font></th>
								  <th width="25%" align="center"><font size="5">Name</font></th>
								  <th width="11%" align="center"><font size="5">Date added</font></th>
								  <th width="21%" align="center"><font size="5">Market</font></th>
								  <th width="20%" align="center"><font size="5">Sales Person</font></th>
								  <th width="20%" align="center"><font size="5">Notes</font></th>
								</tr>';
				$tableRecords = ((($totalRs-($i*20)) > 20) ? 20 : $totalRs-($i*20));
				for ($j = 0; $j < $tableRecords; $j++) {
					$outputHtml .= '<tr>
									<td align="center"><font size="5">'.(($i*20+$j+1)).'</font></td>
									<td align="center"><font size="5">'.($rs[$i*20+$j][0] ? $rs[$i*20+$j][0] : '&nbsp;').'</font></td>
									<td align="center"><font size="5">'.($rs[$i*20+$j][2] ? date("m/d/Y",$rs[$i*20+$j][2]) : '&nbsp;').'</font></td>
									<td align="center"><font size="5">'.($rs[$i*20+$j][4] ? $rs[$i*20+$j][4] : '&nbsp;').'</font></td>
									<td align="center"><font size="5">'.($rs[$i*20+$j][3] ? $rs[$i*20+$j][3] : '&nbsp;').'</font></td>
									<td align="center">&nbsp;</td>
									</tr>';
				}
				$outputHtml.= '</table>';
				$outputHtml.= chr(10).chr(10);

				// add PDF page break only if it's not last table
				if (($action == 'pdf') && ($i < $pagesNo-1)) $outputHtml.= '<NEWPAGE>';
			}
			// end the output html
			$outputHtml.= '</body></html>';

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

				$pdf=new HTML2FPDF('L','mm','A4');
				$pdf->AddPage();
				//echo $outputHtml; die();
				$pdf->WriteHTML($outputHtml);
				$pdf->Output("reports_".str_replace('/','_',str_replace(' ','_',base64_decode($tblTitle))).".pdf","D");
			}
		}
	}


	/**
	 * Export/print sales report
	 *
	 */
	public function exportsalesAction() {
		$sql = $this->_request->getParam('sql');
		$action = $this->_request->getParam('type');
		$salesData = $this->_request->getParam('data');

		if (!$sql || !$action || !$salesData) {
			$this->_redirect('reports/?error=INVALID_PRINT');
		}

		$salesData = Zend_Json::decode(base64_decode($salesData));

		// get the info from the database
		$db = Zend_Registry::get('db');
		$sql = base64_decode($sql);
		$sql = explode('||',$sql);
		$sqlTotal = $sql[0];
		$sqlPaid = $sql[1];

		$rs = array();
		$rs['total'] = $db->fetchOne($sqlTotal);
		$rs['paid'] = $db->fetchPairs($sqlPaid);

		// total results
		$totalRs = count($rs);
		$outputHtml = '<html>';
		if ($totalRs > 0) {
			// add auto-print on page load
			if ($action == 'print') {
				$outputHtml .= '<script type="text/javascript">setTimeout(function(){window.close();},2000);</script><body onLoad="window.print()">';
			}
			else {
				$outputHtml.= '<body>';
			}

			// build the table
			$outputHtml.= '
				<br /><br /><br />
				<table width="300" border="1" style="margin: auto;">
				  <thead>
				  <tr>
				    <th colspan="2">Sales Report</th>
				    </tr>
				  </thead>
				  <tbody>
				  <tr>
				    <th width="150" scope="row"><div align="right"><strong>Period: </strong></div></th>
				    <td width="150"><div align="left">' . ($salesData['fromDate']) . ' to ' . ($salesData['toDate']) . '</div></td>
				  </tr>
				  <tr>
				    <th scope="row"><div align="right"><strong>' . ($salesData['searchBy']) . ':</strong></div></th>
				    <td><div align="left">' . ($salesData['searchByValue']) . '</div></td>
				  </tr>
				  <tr>
				    <th scope="row">&nbsp;</td>
				    <td>&nbsp;</td>
				  </tr>
				  <tr>
				    <th scope="row"><div align="right">Paid by Cash</div></th>
				    <td>$ ' . (!empty($rs['paid']['Cash']) ? $rs['paid']['Cash'] : 0) . '</td>
				  </tr>
				  <tr>
				    <th scope="row"><div align="right">Paid by Credit Card</div></th>
				    <td>$ ' . (!empty($rs['paid']['Credit Card']) ? $rs['paid']['Credit Card'] : 0) . '</td>
				  </tr>
				  <tr>
				    <th scope="row" style="border-bottom: 1px solid #fff;"><div align="right">Paid by Check</div></th>
				    <td style="border-bottom: 1px solid #8997a0;">$ ' . (!empty($rs['paid']['Check']) ? $rs['paid']['Check'] : 0) . '</td>
				  </tr>
				  <tr>
				    <th scope="row"><div align="right"><strong>Total Paid *</strong></div></th>
				    <td><div align="left">$ ' . array_sum($rs['paid']) . '</div></td>
				  </tr>
				  <tr>
				    <th scope="row"><div align="right">Total Sales **</div></th>
				    <td><div align="left"><strong>$ ' . (!empty($rs['total']) ? $rs['total'] : 0) . '
				    </strong></div></td>
				  </tr>

				  </tbody>
				</table>
				<br />
				<div>
				* Sum of all payments made by students who enrolled in that period and which are not marked for deletion or as cold leads.<br />
				** Sum of total amount of each student who enrolled in that period which are not marked for deletion or as cold leads.<br />
				</div>
				</body></html>';

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
				$pdf->Output("sales_".str_replace('/','_',str_replace(' ','_',($salesData['fromDate']) . ' to ' . ($salesData['toDate']))).".pdf","D");
			}
		}
	}


	/**
	 * Export/print starts
	 *
	 */
	public function exportstartsAction() {
		$sqlWhere = $this->_request->getParam('sqlWhere');
		$action = $this->_request->getParam('type');
		$due = $this->_request->getParam('due');

		// get the info from the database
		$db = Zend_Registry::get('db');
		$db->setFetchMode(Zend_Db::FETCH_NUM);
		$select = $db->select()
					->from(array('t1' => 'booked'),
						array("CONCAT_WS(' ',first_name,last_name)",
							"DATE_FORMAT(enrolled_date,'%m/%d/%Y')",
							"class_time",
							"t2.name",
							($due) ? "(SELECT t1.total_amount-COALESCE((SUM(amount)),0) FROM payments WHERE user_id = t1.id AND user_type='booked')" : "id"
							)
						)
					->join(array('t2' => 'users'),
						't1.sales_person_id = t2.id',
						array());
		if (!empty($sqlWhere)) {
			$sqlWhere = Zend_Json::decode(base64_decode($sqlWhere));
		}
		$select->where(implode(' ',$sqlWhere));

		$rs = $select->query()->fetchAll();

		// total results
		$totalRs = count($rs);
		$outputHtml = '<html>';
		if ($totalRs > 0) {
			// no. of pages
			$pagesNo = ceil($totalRs / 20);
			preg_match('/([0-9\/]+)/',$sqlWhere[1],$startsDate);
			// add auto-print on page load
			if ($action == 'print') {
				$outputHtml .= '<body onLoad="window.print()">';
			}
			else {
				$outputHtml.= '<body>';
			}
			$outputHtml .= '
						<div style="margin:auto; ';
			if ($action == 'print')
				$outputHtml.= 'width: 1600px;';
			else
				$outputHtml.= 'width: 1000px;';
			$outputHtml.= 'text-align:center; font-size:20px;"><strong><font size="5">Starts for '.$startsDate[1].'</font></strong></div>
						<br />';
			for ($i = 0; $i < $pagesNo; $i++) {
				// add table definition and header
				$outputHtml.= '<table style="margin: auto;';
				// add print page break only if it's not last table
				if ($i < $pagesNo-1) $outputHtml.= 'page-break-after:always;';
				$outputHtml.= '" border="1" class="" ';
				if ($action == 'print')
					$outputHtml.= 'width="1600">';
				else
					$outputHtml.= 'width="1000">';
				// add headers
				$outputHtml.= '<tr>
								  <th width="3%" align="center"><font size="5">No</font></th>
								  <th width="25%" align="center"><font size="5">Name</font></th>
								  <th width="18%" align="center"><font size="5">Date enrolled</font></th>
								  <th width="17%" align="center"><font size="5">Class time</font></th>
								  <th width="15%" align="center"><font size="5">Sales Person</font></th>
								  <th width="22%" align="center"><font size="5">Notes</font></th>
								</tr>';
				$tableRecords = ((($totalRs-($i*20)) > 20) ? 20 : $totalRs-($i*20));
				for ($j = 0; $j < $tableRecords; $j++) {
					$outputHtml .= '<tr>
									<td align="center"><font size="5">'.(($i*20+$j+1)).'</font></td>
									<td align="center"><font size="5">'.($rs[$i*20+$j][0] ? $rs[$i*20+$j][0] : '&nbsp;').'</font></td>
									<td align="center"><font size="5">'.($rs[$i*20+$j][1] ? $rs[$i*20+$j][1] : '&nbsp;').'</font></td>
									<td align="center"><font size="5">'.($rs[$i*20+$j][2] ? $rs[$i*20+$j][2] : '&nbsp;').'</font></td>
									<td align="center"><font size="5">'.($rs[$i*20+$j][3] ? $rs[$i*20+$j][3] : '&nbsp;').'</font></td>
									<td align="center"><font size="5">' . (($due) ? ('Amount Due: $' . $rs[$i*20+$j][4]) : '&nbsp;') . '</font></td>
									</tr>';
				}
				$outputHtml.= '</table>';
				$outputHtml.= chr(10).chr(10);

				// add PDF page break only if it's not last table
				if (($action == 'pdf') && ($i < $pagesNo-1)) $outputHtml.= '<NEWPAGE>';
			}
			// end the output html
			$outputHtml.= '</body></html>';

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

				$pdf=new HTML2FPDF('L','mm','A4');
				$pdf->AddPage();
				$pdf->WriteHTML($outputHtml);
				$pdf->Output("Starts_".str_replace('/','_',$startsDate[1]).".pdf","D");
			}
		}
	}

	/**
	 * Export/print leadsheets for all starts
	 *
	 */
	public function leadsheetsAction() {
		$sqlWhere = $this->_request->getParam('sqlWhere');
		$mode = $this->_request->getParam('type','D');

		if (!$sqlWhere) {
			$this->_redirect('error/show/?EXPORT_STARTS_QUOTES');
		}

		// get the info from the database
		$db = Zend_Registry::get('db');
		$sqlWhere = Zend_Json::decode(base64_decode($sqlWhere));

		$sql = 'SELECT id FROM booked AS t1 WHERE '.implode(' ',$sqlWhere);

		$leadsId = $db->fetchCol($sql);

		if (is_array($leadsId)) {
				error_reporting(0);
				ini_set('display_errors',0);
				$this->_helper->layout()->disableLayout();
				$this->_helper->viewRenderer->setNoRender(true);

				$leadsheetObj = new MyLibs_Quote($leadsId,$mode);
				$leadsheetObj->buildPDF();
				preg_match('/([0-9\/]+)/',$sqlWhere[1],$startsDate);
				$pdfFileName = "Starts_".str_replace('/','_',$startsDate[1]);
				$leadsheetObj->OutputPDF($pdfFileName);
		}
		else {
			$this->_redirect('error/show/?EXPORT_STARTS_QUOTES');
		}
	}
}