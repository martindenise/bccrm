<?php



/**
 * Generate leadsheets
 *
 */
class MyLibs_Quote
{
	const TYPICAL_PERIOD				= 3; // hours

	protected $_QUOTE_TEMPLATE_DIR		= '';
	protected $_SENDER_EMAIL_ADDRESS        = '';
	protected $_SENDER_NAME                 = '';
	protected $_EMAIL_SUBJECT               = '';
	protected $_EMAIL_BODY                  = '';
	protected $_EMAIL_BCC                   = 'stronthonel@aol.com';
	protected $_quoteId		 			= array();
	protected $_outputMode 				= '';
	protected $_pdf						= null;
	protected $_db						= null;
	protected $_data					= array();
	protected $_qData					= array();
	protected $_equipment				= array();
	protected $_eventDate				= '';
	protected $_eventLoc				= '';
	protected $_schools					= array();
	protected $_financial				= array();
	protected $_byUserId				= false;
	protected $_leadEmail 				= '';

	/**
	 * Class constructor
	 *
	 * @param mixed $leadsheets Leadsheet ID's
	 * @param string $outputMode Download/File/Inline
	 * @return MyLibs_Leadsheet
	 */
	function MyLibs_Quote($quoteId,$outputMode = 'D')
	{
		if (empty($quoteId)) {
			return false;
		}
		else {
			$this->_quoteId = $quoteId;
		}

		$this->_outputMode = $outputMode;
		$this->_db = Zend_Registry::get('db');
		
		// get the quote info; return false if it doesn't exists
		$quoteData = $this->_db->fetchRow("SELECT * FROM quotes WHERE id = {$this->_quoteId}");
		if (empty($quoteData) || !count($quoteData)) {
			return false;
		}

		$this->_data		= Zend_Json::decode($quoteData['details'], true);
		$this->_equipment 	= Zend_Json::decode($quoteData['equipment'], true);
		$this->_eventDate	= $quoteData['event_date'];
		$this->_financial 	= $this->getQuotePrices(false);
		$this->_eventLoc	= $quoteData['event_location'];
		$this->_leadEmail	= $this->_data['email'];

		$campus_id = $this->_data['campus_id'];
		$campusData = $this->_db->fetchRow("SELECT name, email, contact_name FROM campuses WHERE id = {$campus_id}");
		if (empty($campusData) || !count($campusData)) {
			return false;
		}
		$this->_QUOTE_TEMPLATE_DIR = SITE_PATH.'_pdfs' . DIRECTORY_SEPARATOR . $campusData['name'] . DIRECTORY_SEPARATOR;
		$this->_SENDER_EMAIL_ADDRESS = $campusData['email'];
		$this->_SENDER_NAME = $campusData['contact_name'];
		$this->_EMAIL_SUBJECT = 'Quote for your casino event in ' . $campusData['name'];
		$this->_EMAIL_BODY = $this->_data['email_body'];

	}

	public function getQuotePrices($force = true, $eventDate = null)
	{
		$quoteFinancial = $this->_db->fetchOne("SELECT financial FROM quotes WHERE id = {$this->_quoteId}");
		if (!empty($quoteFinancial)) {
			$this->_financial = Zend_Json::decode($quoteFinancial, true);
		}
		if (!empty($this->_financial) && !$force) {
				return $this->_financial;
		}
		
		$return = array('total_gross' => 0,
						'total_gross_tables' => 0,
						'total_gross_services' => 0,
						'total_price' => 0,
						'total_discount' => 0,
						'total_paid' => 0,
						'special_price' => false,
						'pay_in_full' => false,
						'sales_tax' => 0,
						'sales_tax_val' => 0,
						'event_date' => '',
						'paid_date' => '',
						'extra_hours' => 0,
						'no_of_hours' => 0,
						'discounts' => array()
						);
		
		// check if the event date is set
		//if (empty($this->_eventDate) && empty($eventDate)) {
		//	return array('fail', 'event date not set');
		//}
		
		if (!empty($eventDate)) {
			$this->_eventDate = $eventDate;
		}
		$return['event_date'] = $this->_eventDate;
		
		// get the extra hours number
		$noOfHours = self::TYPICAL_PERIOD;
		if (empty($this->_data['time_from_hour']) || empty($this->_data['time_from_time']) ||
				empty($this->_data['time_to_hour']) || empty($this->_data['time_to_time'])) {
			$extraHours = 0;
		} else {
			if ($this->_data['time_from_hour'] == '00:00') {
				$this->_data['time_from_hour'] = '12:00';
			}
			if ($this->_data['time_to_hour'] == '00:00') {
				$this->_data['time_to_hour'] = '12:00';
			}
			
			$fromTS = strtotime($this->_data['time_from_hour'] . ' ' . $this->_data['time_from_time'] . ' ' . $this->_eventDate);
			$toTS = strtotime($this->_data['time_to_hour'] . ' ' . $this->_data['time_to_time'] . ' ' . $this->_eventDate);
			$diff = $toTS-$fromTS;
			
			if ($diff <= 0) {
				//$extraHours = 0;
				$diff = 86400 + $diff; // one day minus the difference
			}
			$noOfHours = round($diff/60/60, 1);
			if ($noOfHours > self::TYPICAL_PERIOD) {
				$extraHours = $noOfHours - self::TYPICAL_PERIOD;
			} else {
				$extraHours = 0;
			}
		}
		
		$return['no_of_hours'] = $noOfHours;
		$return['extra_hours'] = $extraHours;

		$return['no_of_hours'] = $this->_data['no_of_hours'];
		$return['extra_hours'] = 0;
		$return['manual_total'] = $this->_data['manual_total'];
						
		// first, get the prices for all the equipments
		if (empty($this->_data['campus_id'])) {
			return array('fail', 'market not selected');
		}
		
		// get the sales tax for the campus
		$sTax = $this->_db->fetchOne("SELECT sales_tax FROM campuses WHERE id = {$this->_data['campus_id']}");
		if (!empty($sTax)) {
			$return['sales_tax'] = $sTax;
		}
		
		// search and see if the event date is in the special-prices list
		$specialPrice = true;
		$mysqlEventDate = date('Y-m-d', strtotime($this->_eventDate));
		$eqPrices = $this->_db->fetchAll("SELECT t1.*, t2.equipment_id FROM `special-prices` t1 JOIN `campus-equipment` t2 ON t1.`campus_equipment_id` = t2.id WHERE t1.campus_id = {$this->_data['campus_id']} AND '{$mysqlEventDate}' BETWEEN date_start AND date_end");
		
		// get normal prices
		$isWeekend = false;
		$dName = date('D', strtotime($mysqlEventDate));
		if ('Sat' == $dName || 'Sun' == $dName) {
			$normalPrices = $this->_db->fetchAll("SELECT * FROM `weekend-prices` WHERE campus_id = {$this->_data['campus_id']}");
		} else {
			$normalPrices = $this->_db->fetchAll("SELECT * FROM `campus-equipment` WHERE campus_id = {$this->_data['campus_id']}");
		}
		if (empty($normalPrices)) {
			return array('fail', 'prices internal error 02');
		}
		
		if (empty($eqPrices)) { // no special prices
			$specialPrice = false;
			// check if the day is in the weekend
			$eqPrices = array();
			$eqPrices = $normalPrices;
		} else {
			// check to see if indeed special prices are lower than normal prices
			// if yes, we need to show this to the user
			// also, we need a compatibility measure to be done, which sets the equipment id for the special prices, as they don't have it
			$tmpEqPrices = $eqPrices;
			usort($tmpEqPrices, array('self', 'sortByCampusEqId'));
			$tmpNormalPrices = $normalPrices;
			usort($tmpNormalPrices, array('self', 'sortByRelationId'));
			for ($i = 0; $i < count($tmpEqPrices); $i++) {
				if ($tmpEqPrices[$i]['campus_equipment_id'] == $tmpNormalPrices[$i]['id']) {
					if (($tmpEqPrices[$i]['price'] != 0) && $tmpEqPrices[$i]['price'] >= $tmpNormalPrices[$i]['price']) {
						$specialPrice = false;
					}
					if (($tmpEqPrices[$i]['price_dealer'] != 0) && $tmpEqPrices[$i]['price_dealer'] >= $tmpNormalPrices[$i]['price_dealer']) {
						$specialPrice = false;
					}
				}
			}
		}
		//echo '<pre>'; print_r($eqPrices); echo '</pre>';
		//dumpvar($eqPrices);
		if (!empty($this->_equipment) && count($this->_equipment)) {
			// check tables before calculating the price
			$inventoryStatus = $this->checkInventory();
			if (!$inventoryStatus) {
				return array('fail', 'equipment not in stock');
			}
			
			$totalTables = 0;
			foreach ($this->_equipment as &$equipment) {
				$stepPrice = 0;
				if (!empty($equipment['equipment_id'])) {
					$key = self::my_array_search(array('equipment_id' => $equipment['equipment_id']), $eqPrices);
					if (!isset($key)) {
						return array('fail', 'equipment internal error');
					}
					if (!empty($equipment['extra']) && in_array($equipment['extra'], array('yes', 'no'))) { // equipment
						if ('yes' === $equipment['extra']) { // price with dealer
							if (empty($eqPrices[$key]['price_dealer'])) {
								return $return;
							}
							
							// if we have the price specially set for this  item, let's use it
							if (!empty($equipment['price'])) {
								$stepPrice = $equipment['price'];
							} else {
								$equipment['price'] = $eqPrices[$key]['price_dealer'];
								$stepPrice = $eqPrices[$key]['price_dealer'];
							}
							// check if we have extra hours
							if (!empty($extraHours)) {
								// if we have the extra hour price specially set for this  item, let's use it
								if (!empty($equipment['extra_hour_price'])) {
									$stepPrice += $extraHours * $equipment['extra_hour_price'];
								} else {
									$equipment['extra_hour_price'] = $eqPrices[$key]['extra_hour_price'];
									$stepPrice += $extraHours * $eqPrices[$key]['extra_hour_price'];
								}
							}
							$return['total_gross_tables'] += $equipment['quantity'] * $stepPrice;
						} else { // price w/o dealer
							// if we have the price specially set for this  item, let's use it
							if (!empty($equipment['price'])) {
								$stepPrice = $equipment['price'];
							} else {
								$equipment['price'] = $eqPrices[$key]['price'];
								$stepPrice = $eqPrices[$key]['price'];
							}
							// check if we have extra hours
							if (!empty($extraHours)) {
								// if we have the extra hour price specially set for this  item, let's use it
								if (!empty($equipment['extra_hour_price'])) {
									$stepPrice += $extraHours * $equipment['extra_hour_price'];
								} else {
									$equipment['extra_hour_price'] = $eqPrices[$key]['extra_hour_price'];
									$stepPrice += $extraHours * $eqPrices[$key]['extra_hour_price'];
								}
							}
							$return['total_gross_tables'] += $equipment['quantity'] * $stepPrice;
						}
						$totalTables += $equipment['quantity'];
					} else { // service
						 // the number of hours for this service
						 $serviceHours = $equipment['extra'];
						 // if we have the price specially set for this  item, let's use it
						if (!empty($equipment['price'])) {
							$stepPrice = $equipment['price'] * $serviceHours;
						} else {
							$equipment['price'] = $eqPrices[$key]['price'];
						 	$stepPrice = $eqPrices[$key]['price'] * $serviceHours;
						}
						$return['total_gross_services'] += $equipment['quantity'] * $stepPrice;
					}
					$return['total_gross'] += $equipment['quantity'] * $stepPrice;
					$return['total_price'] = $return['total_gross'];
				} else {
					return array('fail', 'equipment internal error');
				}
			}
			
			$return['total_gross_tables'] = self::myRound($return['total_gross_tables'], 2);
			$return['total_gross_services'] = self::myRound($return['total_gross_services'], 2);
			$return['total_gross'] = self::myRound($return['total_gross'], 2);
					
			// apply discounts if any
			// multi-table discounts
			if (!empty($totalTables)) {
				$mtDiscount = $this->_db->fetchOne("SELECT percent FROM `equipment-discount` WHERE campus_id = {$this->_data['campus_id']} AND tables <= {$totalTables} ORDER BY tables DESC LIMIT 1");
				if (!empty($mtDiscount)) {
					$tablesDiscountVal = self::myRound(($mtDiscount * $return['total_gross_tables']) / 100, 2);
					$return['total_discount'] += $tablesDiscountVal;
					$return['total_discount'] = self::myRound($return['total_discount'], 2);
					// apply only to tables gross
					$return['total_gross_tables'] = self::myRound($return['total_gross_tables'] - $tablesDiscountVal, 2);
					//$return['total_gross'] = self::myRound($return['total_gross'] - $tablesDiscountVal, 2);
					$return['total_price'] = self::myRound($return['total_gross'] - $return['total_discount'], 2);
					$return['discounts'][] = array('value' => self::myRound($mtDiscount, 2),
													'title' => 'Multi Table Discount',
													'desc' => 'Discount of ' . $mtDiscount . '% ($' . $tablesDiscountVal . ') for ' . $totalTables . ' tables');
				}
			}
			
			// we need to apply sales tax ?
			if (isset($return['sales_tax']) && $return['sales_tax'] > 0) {
				$return['sales_tax'] = intval($return['sales_tax']);
				$sTaxFloat = $return['sales_tax'] / 100;
				// apply sales tax to the price after the discounts
				$return['sales_tax_val'] = self::myRound($return['total_price'] * $sTaxFloat, 2);
				$return['total_price'] = self::myRound($return['total_price'] + $return['sales_tax_val'], 2);
				$return['total_gross']+= self::myRound($return['sales_tax_val'], 2);
				//$return['total_price'] = self::myRound($return['total_price'] - $return['sales_tax_val'], 2);
			}
			
			$this->_data['total_amount'] = $return['total_price'];
			$return['special_price'] = $specialPrice;
			// get user total payments for this quote
			$totalPaid = $this->_db->fetchOne("SELECT SUM(amount) FROM payments WHERE quote_id = {$this->_quoteId}");
			$totalPayments = $this->_db->fetchOne("SELECT COUNT(amount) FROM payments WHERE quote_id = {$this->_quoteId}");
			$return['total_paid'] = !empty($totalPaid) ? self::myRound($totalPaid, 2) : 0.00;
			// get last payment date
			$lastPaymentDate = $this->_db->fetchOne("SELECT date FROM payments WHERE quote_id = {$this->_quoteId} ORDER BY id DESC LIMIT 1");
			$return['paid_date'] = !empty($lastPaymentDate) ? $lastPaymentDate : ' ';
			
			// paid in full ? apply discount
			// get discount for this market
			$pfDiscount = $this->_db->fetchOne('SELECT full_pay_discount FROM campuses WHERE id = ' . $this->_data['campus_id']);
			if (!empty($pfDiscount)) {
				$priceWDiscount = self::myRound($return['total_price'] * (1.00 - (intval($pfDiscount) / 100)), 2);
				// if discounted price equals with the amount paid
				// and there is only 1 payment
				if (("$priceWDiscount" == self::myRound($return['total_paid'], 2)) && $totalPayments == 1) { // the user paid in full
					$discountVal = self::myRound($return['total_price'] - $priceWDiscount, 2);
					$return['total_discount']+= $discountVal;
					$return['total_price'] = self::myRound($return['total_gross'] - $return['total_discount'], 2);
					$return['pay_in_full'] = true;
					$return['discounts'][] = array('value' => $discountVal,
													'title' => 'Pay In Full Discount',
													'desc' => 'Discount of ' . $pfDiscount . '% ($' . $discountVal . ') for full payment');
				}
			}
			
			$this->_data['paid_amount'] = $return['total_paid'];
		}
		
		// update the db entry
		// update the equipments in the quote too
		// required if the equipments didn't had the prices set in the $this->_equipment array
		if ($return['manual_total'] > 0) { $return['total_price'] = $return['manual_total']; }
		$this->_financial = $return;
		$this->_db->update('quotes', array('details' => Zend_Json::encode($this->_data),
											'equipment' => Zend_Json::encode($this->_equipment),
												'financial' => Zend_Json::encode($this->_financial)), "id = {$this->_quoteId}");
		
		//echo '<pre>'; print_r($this->_financial); echo '</pre>';
		return $this->_financial;
	}
	
	public function checkInventory()
	{
		if (empty($this->_equipment)) {
			return false;
		}
		if (empty($this->_eventDate)) {
			return false;
		}
		// first, get the prices for all the equipments
		if (empty($this->_data['campus_id'])) {
			return false;
		}
		
		// fetch all quote equipments for that event date
		$allEquipments = $this->_db->fetchCol("SELECT equipment FROM quotes WHERE event_date LIKE '{$this->_eventDate}' AND user_type = 'booked'");
		if (!empty($allEquipments) && count($allEquipments)) { // ok, let's count
			// find out the quantity for each equipment type
			$totalQtys = array();
			foreach ($allEquipments as $qEquipments) {
				if (!empty($qEquipments)) {
					$qEquipmentsArr = Zend_Json::decode($qEquipments, true);
					if (is_array($qEquipmentsArr) && count($qEquipmentsArr)) {
						foreach ($qEquipmentsArr as $qEquipment) {
							if (empty($totalQtys[$qEquipment['equipment_id']])) {
								$totalQtys[$qEquipment['equipment_id']] = 0;
							}
							$totalQtys[$qEquipment['equipment_id']] += $qEquipment['quantity'];
						}
					}
				}
			}
			
			// now, let's check against the inventory
			foreach ($totalQtys as $eqId => $qty) {
				$eqInventory = $this->_db->fetchOne("SELECT inventory_total FROM `campus-equipment` WHERE campus_id = {$this->_data['campus_id']} AND equipment_id = {$eqId}");
				if ($eqInventory < $qty) {
					return false;
				}
			}
		} else { // it's simple, it's the only quote for this date, let's see if the desired tables exceed the school inventory
			foreach ($this->_equipment as $equipment) {
				$eqInventory = $this->_db->fetchOne("SELECT inventory_total FROM `campus-equipment` WHERE campus_id = {$this->_data['campus_id']} AND equipment_id = {$equipment['equipment_id']}");
				if ($eqInventory < $equipment['quantity']) {
					return false;
				}
			}
		}
		
		return true;
	}

	public function buildPDF()
	{
		//echo '<script type="text/javascript">alert("sa am grija sa vad daca se aplica paid in full discount")</script>';
		if (!count($this->_quoteId)) {
			return 'Invalid quote';
		}
		
		error_reporting(1);
		ini_set('display_errors', 1);
		
		$this->_dataToQuoteData();
		
		$fName = 'quote.pdf';
		
		// fetch payments too
		if (0 < $this->_financial['sales_tax']) {
			$fName = 'quote_wSTax.pdf';
		}
		
		// payment due?
		// event date in the past?
		$evDateTs = strtotime($this->_eventDate);
		$nowDateTs = strtotime(date('m/d/Y'));
		if ($nowDateTs >= $evDateTs) {
			$passed = 1;
		} else {
			$passed = 0;
		}
		
		if ($passed) {
			$fName = 'receipt_due.pdf';
			if (0 < $this->_financial['sales_tax']) {
				$fName = 'receipt_due_wSTax.pdf';
			}
		}
		
		// full paid ?
		if ((string)$this->_financial['total_paid'] == (string)$this->_financial['total_price']) {
			$fName = 'receipt_full.pdf';
			if (0 < $this->_financial['sales_tax']) {
				$fName = 'receipt_full_wSTax.pdf';
			}
		}
		
		if (!file_exists($this->_QUOTE_TEMPLATE_DIR . $fName)) {
			return 'The quote template file is missing';
		}
		
		if (empty($this->_data['campus_id'])) {
			return 'Market not set';
		}
		
		// fetch the market contact name
		$coordName = $this->_db->fetchOne("SELECT contact_name FROM campuses WHERE id = {$this->_data['campus_id']}");
		if (empty($coordName)) { $coordName = ' '; }
	
		// disable zend autoload
		Zend_Loader::registerAutoload('Zend_Loader',false);
		

		require SITE_PATH.'/library/FPDI/fpdi.php';

		$this->_pdf =& new FPDI('P','mm','A4');
		
		$this->_pdf->setSourceFile($this->_QUOTE_TEMPLATE_DIR . $fName);
		$this->_pdf->AddFont ( 'OCRAExtended', '', 'ocraext.php' );
		$this->_pdf->AddFont ( 'MyriadPro-Regular', '', 'myriadRe.php' );
		$this->_pdf->AddFont ( 'MyriadPro-Bold', '', 'myriadB.php' );
				
		// new pdf page
		$tplidx = $this->_pdf->importPage(1);
		$this->_pdf->addPage();
		$this->_pdf->useTemplate($tplidx);
		//echo '<pre>'; print_r($leadInfo); die();

		$this->_pdf->SetFont('Helvetica');
		$this->_pdf->SetFontSize(9);
		$this->_pdf->SetTextColor(0);
		$this->_pdf->SetXY(182.6,34.3);
		
		// Quote date
		$this->_pdf->CellFit(37,0,date('n/j/y'),0,0,'L',0,'',0,0,0);
		// Name
		$this->_pdf->SetXY(28.7,43);
		$this->_pdf->CellFit(37,0,$this->_qData['name'],0,0,'L',0,'',0,0,0);
		// Company
		$this->_pdf->SetXY(90,43);
		$this->_pdf->CellFit(48,0,$this->_qData['company'],0,0,'L',0,'',0,0,0);
		// Phone
		$this->_pdf->SetXY(154,43);
		$this->_pdf->CellFit(37,0,$this->_qData['phone'],0,0,'L',0,'',0,0,0);
		// Fax
		$this->_pdf->SetXY(28.7,52);
		$this->_pdf->CellFit(37,0,$this->_qData['fax'],0,0,'L',0,'',0,0,0);
		// Number of hours
		$this->_pdf->SetXY(90,52);
		$this->_pdf->CellFit(37,0,$this->_qData['event_duration'],0,0,'L',0,'',0,0,0);
		// Event date
		$this->_pdf->SetXY(154,52);
		$this->_pdf->CellFit(37,0,$this->_qData['event_date'],0,0,'L',0,'',0,0,0);
		// Event time
		//$this->_pdf->SetXY(28.7,61);
		//$this->_pdf->CellFit(37,0,$this->_qData['event_time'],0,0,'L',0,'',0,0,0);
		// Location
		$this->_pdf->SetXY(90,61);
		$this->_pdf->CellFit(100,0,$this->_qData['location'],0,0,'L',0,'',0,0,0);
		// Coord
		$this->_pdf->SetXY(28.7,70);
		$this->_pdf->CellFit(37,0, $coordName,0,0,'L',0,'',0,0,0);
		// Email
		$this->_pdf->SetXY(90,70);
		$this->_pdf->CellFit(100,0,$this->_qData['email'],0,0,'L',0,'',0,0,0);
		
		// Write financials
		$this->_writeFinancial();
		
		// Write equipments
		$this->_writeEquipments();
		
		// Write discounts and other info
	}

	public function OutputPDF($filename) {
		$save = 0;
		$this->_outputMode = $this->_outputMode ? $this->_outputMode : 'D';

		if (2 == strlen($this->_outputMode)) {
			$this->_outputMode = substr($this->_outputMode,0,1);
			$save = 1;
		}
		if (('S' != $this->_outputMode) && ('X' != $this->_outputMode)) {
			$this->_pdf->SetDisplayMode('real');
			$this->_pdf->Output($filename.'.pdf',$this->_outputMode);
		}
		if ('S' == $this->_outputMode || 1 == $save) {
			if (!empty($this->_leadEmail)) {
				// enable zend autoload
				Zend_Loader::registerAutoload('Zend_Loader',true);

				$mail = new Zend_Mail();
		    	// configure the mail object
				$mail->setBodyText($this->_EMAIL_BODY)
				     ->setFrom($this->_SENDER_EMAIL_ADDRESS, $this->_SENDER_NAME)
				     ->addBcc($this->_EMAIL_BCC)
				     ->setSubject($this->_EMAIL_SUBJECT)
				     ->createAttachment($this->_pdf->Output('','S'),'application/pdf',
				     Zend_Mime::DISPOSITION_ATTACHMENT,Zend_Mime::ENCODING_BASE64,'My_quote.pdf');
				$mail->addTo($this->_leadEmail);
				$mail->send();
			}
		}
	}
	
	protected function _writeFinancial()
	{
		$ret = $this->getQuotePrices();
		if (is_array($ret) && !empty($ret[0]) && $ret[0] == 'fail') {
			return false;
		}
		//dumpvar($this->_financial);
		if (empty($this->_financial['sales_tax'])) {
			$this->_pdf->SetFont('Arial','B');
			$this->_pdf->SetFontSize(18);
			$this->_pdf->SetXY(89,195);
			$this->_pdf->CellFit(42,0,'$ ' . self::myRound($this->_financial['total_price'],2),0,0,'C',0,0,0,0);
		} else {
		 	// Add subtotal
			$this->_pdf->SetFont('Arial','B',10);
			$this->_pdf->SetXY(41.6,191.5);
			$this->_pdf->CellFit(23,0,'$ ' . self::myRound((string)$this->_financial['total_price'] - (string)$this->_financial['sales_tax_val'],2),0,0,'C',0,0,0,0);
	
			// Add tax total
			$this->_pdf->SetFont('Arial','B',10);
			$this->_pdf->SetXY(91,191.5);
			$this->_pdf->CellFit(17,0,'$ ' . $this->_financial['sales_tax_val'],0,0,'C',0,0,0,0);
	
			// Add tax percent
			$this->_pdf->SetFont('Arial','B',10);
			$this->_pdf->SetXY(110,191.5);
			$this->_pdf->CellFit(11,0,'(' . $this->_financial['sales_tax'] .'%)',0,0,'C',0,0,0,0);
	
			// Add total quote sum
			$this->_pdf->SetFont('Arial','B',11);
			$this->_pdf->SetXY(144,191.5);
			$this->_pdf->CellFit(23,0,'$ ' . $this->_financial['total_price'] ,0,0,'C',0,0,0,0);
		}
		
		# Payments
		$this->_pdf->SetFont('Arial','B');
		$this->_pdf->SetFontSize(10);
		$this->_pdf->SetXY(23.5,220.5);
		$this->_pdf->CellFit(17.7,0,'$ ' . $this->_financial['total_paid'],0,0,'C',0,0,0,0);
		// Date
		$this->_pdf->SetFont('Arial');
		$this->_pdf->SetFontSize(10);
		$this->_pdf->SetXY(23.7,230);
		$this->_pdf->CellFit(19,0, $this->_financial['paid_date'],0,0,'C',0,0,0,0);
		
		# Due info
		$this->_pdf->SetFont('Arial','B');
		$this->_pdf->SetFontSize(10);
		$this->_pdf->SetXY(23.5,245.7);
		$this->_pdf->CellFit(17.7,0,'$ '. self::myRound((string)$this->_financial['total_price'] - (string)$this->_financial['total_paid'], 2) ,0,0,'C',0,0,0,0);
		// Date
/*		$this->_pdf->SetFont('Arial');
		$this->_pdf->SetFontSize(10);
		$this->_pdf->SetXY(23.7,255.4);
		$this->_pdf->CellFit(19,0,$userData['due_date'],0,0,'C',0,0,0,0);*/
		
		# Add payments details
		
		if (!empty($this->_qData['payments']) && count($this->_qData['payments'])) {
			$lastPaymentId = count($this->_qData['payments'])-1;
			$lastPayment = $this->_qData['payments'][$lastPaymentId];
			
			$diffMethods = array('cash', 'check', 'paypal');
			if (in_array(strtolower($lastPayment['method']), $diffMethods)) {
				// add big white rectangle
				$this->_pdf->SetLineWidth(0);
				$this->_pdf->SetFillColor(255);
				$this->_pdf->Rect(53, 227, 150, 32,'F');
				$this->_pdf->Rect(68, 219, 128, 7,'F');
				$this->_pdf->SetLineWidth(0.2);
				
				// write the type
				$this->_pdf->SetFont('Arial','');
				$this->_pdf->SetFontSize(11);
				$this->_pdf->SetXY(68, 222);
				$this->_pdf->CellFit(20, 0, ucfirst($lastPayment['method']), 0, 0, 'L', 0, 0, 0, 0);
				
				if ('check' == strtolower($lastPayment['method'])) {
					// write the check number box
					$this->_pdf->SetFont('MyriadPro-Bold');
					$this->_pdf->SetFontSize(10);
					$this->_pdf->SetXY(54, 235);
					$this->_pdf->CellFit(20, 0, 'CHECK #', 0, 0, 'L', 0, 0, 0, 0);
					
					// box
					$this->_pdf->SetLineWidth(0.176);
					$this->_pdf->SetDrawColor(0);
					$this->_pdf->SetFillColor(255);
					$this->_pdf->Rect(81, 232, 97, 6, 'D');
					// check no
					$this->_pdf->SetFont('MyriadPro-Regular');
					$this->_pdf->SetFont('Arial','');
					$this->_pdf->SetFontSize(11);
					$this->_pdf->SetXY(83, 235);
					$this->_pdf->CellFit(50,0,$lastPayment['details'],0,0,'L',0,0,0,0);
				} elseif ('paypal' == strtolower($lastPayment['method'])) {
					$this->_pdf->SetFont('MyriadPro-Bold');
					$this->_pdf->SetFontSize(10);
					$this->_pdf->SetXY(54, 235);
					$this->_pdf->CellFit(30, 0, 'PAYPAL ACCOUNT', 0, 0, 'L', 0, 0, 0, 0);
					
					$this->_pdf->SetFont('Arial','');
					$this->_pdf->SetFontSize(11);
					$this->_pdf->SetXY(86, 235);
					$this->_pdf->CellFit(100,0,$lastPayment['details'],0,0,'L',0,0,0,0);
				}
			} else { // cc payment
				$parts = explode(' ', $lastPayment['details']);
				
				if (count($parts) >= 2) {
					if (strlen($parts[0]) > 4) {
						$parts[0] = substr($parts[0],-4);
						$parts[0] = str_pad($parts[0], 16, '*', STR_PAD_LEFT);
					}

					$this->_pdf->SetFont('Arial','');
					$this->_pdf->SetFontSize(11);
					
					// Add CC digits
					$this->_pdf->SetXY(84,233.5);
					$this->_pdf->CellFit(36,0,$parts[0],0,0,'C',0,0,0,0);
			
					// Add CC expiration date
					$this->_pdf->SetXY(77.5,246.4);
					$this->_pdf->CellFit(16,0,$parts[1],0,0,'C',0,0,0,0);
				}
			}
		}
		
		# Add special price note if it's special prices
		if (!empty($this->_financial['special_price'])) {
			$this->_pdf->SetFont('MyriadPro-Bold');
			$this->_pdf->SetFontSize(9);
			$this->_pdf->SetXY(30, 207.6);
			$textLine = 'The pricing quoted is special and time sensitive. Book early to take advantage of the special prices.';
			$this->_pdf->CellFit(154,0,$textLine,0,0,'C',0,0,0,0);
		}
		
		# Add the discounts if any
		if (!empty($this->_financial['discounts']) && count($this->_financial['discounts']) >= 1) {
			$startYTitle = 84;
			$startYText = 90;
			foreach ($this->_financial['discounts'] as $discount) {
				$discountTitle = $discount['title'];
				$discountText = $discount['desc'];
				$discountText = !empty($discountText) ? $discountText : ' ';
				
				$this->_pdf->SetFont('MyriadPro-Bold');
				$this->_pdf->SetFontSize(10);
				$this->_pdf->SetTextColor(254, 137, 42);
				$this->_pdf->SetXY(133, $startYTitle);
				$this->_pdf->CellFit(35,0,$discountTitle,0,0,'L',0,0,0,0);
				$this->_pdf->SetTextColor(0);
				
				$this->_pdf->SetFont('MyriadPro-Regular');
				$this->_pdf->SetXY(133, $startYText);
				$this->_pdf->CellFit(70,0,$discountText,0,0,'L',0,0,0,0);
				
				$startYTitle += 14;
				$startYText += 14;
			}
		} else { // show the tables info
			$this->_pdf->Image($this->_QUOTE_TEMPLATE_DIR . 'tables.jpg', 140, 80, 60.091, 26.360997);
		}
	}
	
	protected function _writeEquipments()
	{
		if (empty($this->_equipment)) {
			return false;
		}
		
		// prepare equipments
		$eqToWrite = array();
		foreach ($this->_equipment as $equipment) {
			// get eq data
			$eqId = $equipment['equipment_id'];
			if (!empty($eqToWrite[$eqId])) {
				$eqToWrite[$eqId]['quantity'] += $equipment['quantity'];
			} else {
				$eqData = $this->_db->fetchRow("SELECT name, type, dealers FROM equipments WHERE id = {$eqId}");
				if (!empty($eqData)) {
					$eqToWrite[$eqId] = $eqData;
					$eqToWrite[$eqId]['quantity'] = $equipment['quantity'];
					$eqToWrite[$eqId]['extra_hour_price'] = !empty($equipment['extra_hour_price']) ? $equipment['extra_hour_price'] : $equipment['price'];
				}
			}
			$eqToWrite[$eqId]['extra'] = $equipment['extra'];
		}

		# Add equipment tables
		$i = $j =  1;
		$tablesStartYPos = 128;
		$additionalStartYPos = 128;
	
		$this->_pdf->SetFont('Arial','');
		$this->_pdf->SetFontSize(10);
		if (count($eqToWrite)) {
			foreach ($eqToWrite as $equipment) {
				if (($equipment['type'] == 'table') && $i <= 5) {
					// Table Name
					$this->_pdf->SetXY(12,$tablesStartYPos);
					$this->_pdf->CellFit(56,0,$equipment['name'],0,0,'C',0,0,0,0);
					// Quantity
					$equipment['quantity'] = !empty($equipment['quantity']) ? $equipment['quantity'] : '0';
					$this->_pdf->SetXY(74.5,$tablesStartYPos);
					$this->_pdf->CellFit(16,0,$equipment['quantity'],0,0,'C',0,0,0,0);

					// Dealers
					$equipment['dealers'] = !empty($equipment['dealers']) ? $equipment['dealers'] : '1';
					// check to see if the table selected is without dealer
					if (!empty($equipment['extra']) && $equipment['extra'] == 'no') { $equipment['dealers'] = '0'; }
					$totalDealers = $equipment['quantity'] * $equipment['dealers'];
					$this->_pdf->SetXY(94.5,$tablesStartYPos);
					$this->_pdf->CellFit(17,0, $totalDealers,0,0,'C',0,0,0,0);
					
					// extra hours? write the details
					if (false) {
					if (!empty($this->_financial['extra_hours']) && $this->_financial['extra_hours'] > 0) {
						$text = 'Extra hour price: $';
						$text.= !empty($equipment['extra_hour_price']) ? $equipment['extra_hour_price'] : 'N/A';
						$text.= '      Number of extra hours: ' . $this->_financial['extra_hours'];
						$this->_pdf->SetFontSize(6);
						$this->_pdf->SetXY(12, $tablesStartYPos+3.2);
						$this->_pdf->CellFit(56,0,$text,0,0,'C',0,0,0,0);
						$this->_pdf->SetFontSize(10);
					}
					}
					
					// Go to next row
					$tablesStartYPos += 9.7;
					$i++;
				}
				elseif ($j <= 5) {
					// Table Name
					$this->_pdf->SetXY(122.5,$additionalStartYPos);
					$this->_pdf->CellFit(56,0,$equipment['name'],0,0,'C',0,0,0,0);
					// Quantity
					$equipment['quantity'] = !empty($equipment['quantity']) ? $equipment['quantity'] : '0';
					$this->_pdf->SetXY(185.3,$additionalStartYPos);
					$this->_pdf->CellFit(17,0,$equipment['quantity'],0,0,'C',0,0,0,0);

					$additionalStartYPos += 9.7;
					$j++;
				}
			}
		}
		
		return true;
	}
	
	protected function _dataToQuoteData()
	{
		if (empty($this->_data)) {
			return null;
		}
		
		// Prepare data for PDF quote
		$this->_qData['name'] = ($this->_data['first_name'] != '') ? $this->_data['first_name'] : ' ';
		if (!empty($this->_data['last_name'])) {
			$this->_qData['name'] .= ($this->_data['last_name'] != '') ? ' ' . $this->_data['last_name'] : '';
		}
		$this->_qData['company'] = ($this->_data['company'] != '') ? $this->_data['company'] : ' ';
		$this->_qData['phone'] = ($this->_data['home_phone'] != '') ? $this->_data['home_phone'] : ' ';
		$this->_qData['fax'] = ($this->_data['fax'] != '') ? $this->_data['fax'] : ' ';
		$this->_qData['event_date'] = (!empty($this->_eventDate)) ? $this->_eventDate : ' ';
		$this->_qData['location'] = (!empty($this->_eventLoc)) ? $this->_eventLoc : ' ';
		$this->_qData['event_duration'] = ' ';
		$this->_qData['email'] = $this->_data['email'];
		if (!empty($this->_data['time_from_hour']) && !empty($this->_data['time_from_time']) && !empty($this->_data['time_to_hour']) && !empty($this->_data['time_to_time'])) {
			$this->_qData['event_time'] = $this->_data['time_from_hour'] . ' ' . $this->_data['time_from_time'] . ' to ' . $this->_data['time_to_hour'] . ' ' . $this->_data['time_to_time'];
			
			$fromTS = strtotime($this->_data['time_from_hour'] . ' ' . $this->_data['time_from_time']);
			$toTS = strtotime($this->_data['time_to_hour'] . ' ' . $this->_data['time_to_time']);
			$diff = $toTS - $fromTS;
			if ($diff <= 0) {
				//$this->_qData['event_duration'] = '00:00';
				$diff = (24*60*60) - ($diff*-1);
			}
			//$this->_qData['event_duration'] = date('h:i', round($diff/60/60, 1));
			$this->_qData['event_duration'] = intval($diff/3600) . ':' . intval($diff%3600/60);
			$this->_qData['event_duration'] = $this->_data['no_of_hours'];
		}
		
		$this->_qData['payments'] = $this->_getPayments();
		usort($this->_qData['payments'],array('MyLibs_Quote','sortPaymentsByDate'));
		$this->_qData['payments'] = array_map(array('MyLibs_Quote','roundAndCheckEmpty'),$this->_qData['payments']);
	}

	/**
	 * Write the schools to the PDF page
	 *
	 */
	protected function _outputSchools($campusId)
	{
		$xpos = 57;
		$ypos = 20; //18
		$this->_pdf->SetFont('Arial','B',12);
		foreach ($this->_schools as $school) {
			if ($campusId == $school['id']) {
				if (!empty($school['details']['address'])) {
					$school['details']['address'] = strtoupper($school['details']['address']);

					// Output school address
					$this->_pdf->SetXY($xpos,$ypos);
					$this->_pdf->CellFit(100,0,$school['details']['address'],0,0,'C',0,'',0,0);

					// Output school city, state, zip
					$school['details']['address2'] = !empty($school['details']['address2']) ? $school['details']['address2'] : ' ';
					$school['details']['address2'] = strtoupper($school['details']['address2']);

					$ypos += 5;
					$this->_pdf->SetXY($xpos,$ypos);
					$this->_pdf->CellFit(100,0,$school['details']['address2'],0,0,'C',0,'',0,0);

					// Output phone number
					$school['details']['phone'] = !empty($school['details']['phone']) ? $school['details']['phone'] : ' ';
					$school['details']['phone'] = strtoupper($school['details']['phone']);

					$ypos += 5;
					$this->_pdf->SetXY($xpos,$ypos);
					$this->_pdf->CellFit(100,0,$school['details']['phone'],0,0,'C',0,'',0,0);
				}
			}
		}
	}

	/**
	 * Write student info to PDF
	 *
	 * @param array $info
	 */
	protected function _outputBasicInfo($info)
	{
		$this->_pdf->SetFont('ArialNarrow','',13);
		// Name
		$this->_pdf->SetXY(34,46.5);
		$this->_pdf->CellFit(53.7, 5.3, $info['name'], 0, 0, 'C', false, '', 1, 0);
		// Email
		//$this->_pdf->SetXY(126,54);
		//$this->_pdf->CellFit(70,5,$info['email'],0,0,'L',0,'',0,0);
		// write source
		$text = 'WEB';
		$this->_pdf->SetXY(179,46.5);
		$this->_pdf->CellFit(18, 5.3, $text, 0, 0, 'C', false, '', 1, 0);
		// Address
		$this->_pdf->SetXY(46.5,55.3);
		$this->_pdf->CellFit(67.5, 5.3,$info['address'],0, 0, 'C', false, '', 1, 0);
		// City
		$this->_pdf->SetXY(124,55.3);
		$this->_pdf->CellFit(35, 5.3,$info['city'], 0, 0, 'C', false, '', 1, 0);
		// State
		$this->_pdf->SetXY(170.8,55.3);
		$this->_pdf->CellFit(6.5, 5.3,$info['state'],0, 0, 'C', false, '', 1, 0);
		// Zip
		$this->_pdf->SetXY(184.6,55.3);
		$this->_pdf->CellFit(13, 5.3,$info['zip'],0, 0, 'C', false, '', 1, 0);
		// Home Phone
		$this->_pdf->SetXY(35,64);
		$this->_pdf->CellFit(43.3, 5.3,$info['home_phone'], 0, 0, 'C', false, '', 1, 0);
		// Other Phone
		$this->_pdf->SetXY(99,64);
		$this->_pdf->CellFit(43, 5.3,$info['mobile_phone'],0, 0, 'C', false, '', 1, 0);
	}


	protected function _outputCourseInfo($info)
	{
		$this->_pdf->SetFont('ArialNarrowSecBold','',12);
		// Start Date
		$this->_pdf->SetXY(38.6,89.7);
		$this->_pdf->CellFit(25, 5.3,$info['start_date'], 0, 0, 'C', false, '', 1, 0);

		// Completion date
		if (!empty($info['completion_date'])) {
			$this->_pdf->SetXY(123.3,89.7);
			$this->_pdf->CellFit(25, 5.3,$info['completion_date'],0, 0, 'C', false, '', 1, 0);
		}

		// Class Time
		if (!empty($info['class_time'])) {
			$this->_pdf->SetXY(172.5,89.7);
			$this->_pdf->CellFit(25, 5.3,$info['class_time'],0, 0, 'C', false, '', 1, 0);
		}
		
		$text = 'Bartending Course';
		$this->_pdf->SetXY(146.5,72.5);
		$this->_pdf->CellFit(50.7, 5.3, $text, 0, 0, 'C', false, '', 1, 0);

		// M-F
		//$this->_pdf->SetXY(100.5,88.3);
		//$this->_pdf->CellFit(28,0,$info['mfType'],0,0,'L',0,'',0,0);

		// Start Date Bottom
		//$this->_pdf->SetFontSize(12);
		//$this->_pdf->SetXY(32,276);
		//$this->_pdf->CellFit(50,0,$info['start_date'],0,0,'C',0,'',0,0);
		// Graduation Date Bottom
		//$this->_pdf->SetFontSize(12);
		//$this->_pdf->SetXY(117,276);
		//$this->_pdf->CellFit(50,0,$info['completion_date'],0,0,'C',0,'',0,0);

	}

	protected function _outputFeesInfo($info)
	{
		$this->_pdf->SetFont('ArialNarrowSecBold','',13);
		// Tuition
		$this->_pdf->SetXY(181.2, 117);
		$this->_pdf->CellFit(12, 5.3, $info['tuition'],0, 0, 'R', false, '', 1, 0);
		// Application Fee
		$this->_pdf->SetXY(181.2, 102.4);
		$this->_pdf->CellFit(12, 5.3,$info['app_fee'],0, 0, 'R', false, '', 1, 0);
		// Extras Fee
		$this->_pdf->SetXY(181.2, 132);
		$this->_pdf->CellFit(12, 5.3,$info['extras'], 0, 0, 'R', false, '', 1, 0);
		// Total Cost
		$this->_pdf->SetXY(181.2, 141);
		$this->_pdf->CellFit(12, 5.3,$info['total_amount'],0, 0, 'R', false, '', 1, 0);
		
/*		$amountpaid = !empty($student_info['amountpaid']) ? my_round($student_info['amountpaid'], 2) : '0.00';

		// write balance due
*/
	}

	protected function _outputPayments($payments, $info)
	{
		$this->_pdf->SetFont('ArialNarrowSecBold','',13);
/*		$amountpaid = '0.00';
		// If no payment
		if (count($payments)) {
		 	// Add last three payments
			foreach ($payments as $payment) {
				$amountpaid += $payment['amount'];
			}
		}
		$amountpaid = self::roundAndCheckEmpty($amountpaid, 2);
		$this->_pdf->SetXY(181.2, 163);
		$this->_pdf->CellFit(12, 5.3, $amountpaid, 0, 0, 'R', false, '', 1, 0);
		
		if (($info['total_amount']-$amountpaid) == 25.00) {
			$balancedue = '0.00';
		} else {
			$balancedue = self::roundAndCheckEmpty(($info['tuition'] + $info['app_fee'] + $info['extras'])-$amountpaid,2);
		}
		$this->_pdf->SetXY(181.2, 191.5);
		$this->_pdf->CellFit(12, 5.3, $balancedue, 0, 0, 'R', false, '', 1, 0);*/
	}

	protected function _outputBalanceInfo($info)
	{
		// Total Amount paid below payments
		$this->_pdf->SetFont('ArialNarrowSecBold','',13);
		$this->_pdf->SetXY(181.2, 163);
		$this->_pdf->CellFit(12, 5.3,$info['paid_amount'],0, 0, 'R', false, '', 1, 0);

		// Due balance
		$this->_pdf->SetXY(181.2, 191.5);
		$this->_pdf->CellFit(12, 5.3,$info['due'],0, 0, 'R', false, '', 1, 0);
	}

	protected function _outputNotes($info)
	{
		$this->_pdf->SetFont('Times','',12);
		$this->_pdf->SetXY(7,150);
		$this->_pdf->MultiCell(196,5,$info,0,'L',0,'');
	}

	protected function _outputHeardAbout($info)
	{
		if (!empty($info)) {
			// draw rect to cover the actual form
			$this->_pdf->SetLineWidth(0);
			$this->_pdf->SetFillColor(255);
			$this->_pdf->Rect(10,245,69,16,'F');
			$this->_pdf->SetLineWidth(0.2);
			// output the text
			$this->_pdf->SetFont('Times','',11);
			$this->_pdf->SetXY(25,250);
			$this->_pdf->CellFit(50,0,$info,0,0,'L',0,'',0,0);
		}
	}

	protected function _outputScoresInfo($info)
	{
		// Written score
		$this->_pdf->SetXY(156,246.7);
		$this->_pdf->CellFit(30,0,$info['written'],0,0,'L',0,'',0,0);
		// Practical score
		$this->_pdf->SetXY(156,251.7);
		$this->_pdf->CellFit(30,0,$info['practical'],0,0,'L',0,'',0,0);
	}

	protected function _getLeadInfoByLeadsheetId($lsID)
	{
		// check to see if the leadsheet exists
		if (!$this->_db->fetchOne('SELECT id FROM quotes WHERE id = '.$lsID)) {
			return false;
		}

		// fetch lead id and lead type
		$leadId = $this->_db->fetchOne('SELECT user_id FROM quotes WHERE id = '.$lsID);
		$leadType = $this->_db->fetchOne('SELECT user_type FROM quotes WHERE id = '.$lsID);
		if (!$leadId || !$leadType) {
			return false;
		}

		// fetch leadsheet info
		$leadsheetInfoDb = $this->_db->fetchOne('SELECT details FROM quotes WHERE id = '.$lsID);
		if (empty($leadsheetInfoDb)) {
			return false;
		}

		$leadsheetInfoDb = Zend_Json::decode($leadsheetInfoDb);
		if (!is_array($leadsheetInfoDb)) {
			return false;
		}

		$return = array();

		# Quote Number
		$return['quote_no']	= 'No. '.(10000+$lsID);

		# Campus Id
		$return['campus_id']	= $leadsheetInfoDb['campus_id'];

		# Student Info
		$return['student_info'] = array();
		$return['student_info']['name'] 		= !empty($leadsheetInfoDb['first_name']) ? $leadsheetInfoDb['first_name'] : ' ';
		$return['student_info']['name']		   .= !empty($leadsheetInfoDb['last_name']) ? ' '.$leadsheetInfoDb['last_name'] : ' ';
		$return['student_info']['email'] 		= !empty($leadsheetInfoDb['email']) ? $leadsheetInfoDb['email'] : ' ';
		$return['student_info']['address'] 		= !empty($leadsheetInfoDb['address']) ? $leadsheetInfoDb['address'] : ' ';
		$return['student_info']['city'] 		= !empty($leadsheetInfoDb['city']) ? $leadsheetInfoDb['city'] : ' ';
		$return['student_info']['state'] 		= !empty($leadsheetInfoDb['state']) ? $leadsheetInfoDb['state'] : ' ';
		$return['student_info']['zip'] 			= !empty($leadsheetInfoDb['zip']) ? $leadsheetInfoDb['zip'] : ' ';
		$return['student_info']['home_phone'] 	= !empty($leadsheetInfoDb['home_phone']) ? $leadsheetInfoDb['home_phone'] : ' ';
		$return['student_info']['mobile_phone'] = !empty($leadsheetInfoDb['mobile_phone']) ? $leadsheetInfoDb['mobile_phone'] : ' ';

		# Course Info
		$return['course_info'] = array();
		$return['course_info']['start_date'] 		= !empty($leadsheetInfoDb['start_date']) ? $leadsheetInfoDb['start_date'] : ' ';
		$return['course_info']['completion_date'] 	= !empty($leadsheetInfoDb['completion_date']) ? $leadsheetInfoDb['completion_date'] : ' ';
		if (!empty($leadsheetInfoDb['class_info'])) $tmp = explode(' - ',$leadsheetInfoDb['class_info']);
		$return['course_info']['mfType'] 			= !empty($tmp[0]) ? $tmp[0] : ' ';
		$return['course_info']['class_time'] 		= !empty($tmp[1]) ? $tmp[1] : ' ';

		# Tuition & Fees
		$return['fees'] = array();
		$return['fees']['tuition']	= !empty($leadsheetInfoDb['tuition']) ? MyLibs_Quote::myRound($leadsheetInfoDb['tuition'],2) : '0.00';
		$return['fees']['app_fee']	= !empty($leadsheetInfoDb['app_fee']) ? MyLibs_Quote::myRound($leadsheetInfoDb['app_fee'],2) : '0.00';
		$return['fees']['extras']	= !empty($leadsheetInfoDb['extras']) ? MyLibs_Quote::myRound($leadsheetInfoDb['extras'],2) : '0.00';
		$return['fees']['total_amount']	= MyLibs_Quote::myRound($return['fees']['tuition'] + $return['fees']['app_fee'] + $return['fees']['extras'],2);
		if ($return['fees']['total_amount'] == 0.00) {
			$return['fees']['total_amount'] = !empty($leadsheetInfoDb['total_amount']) ? MyLibs_Quote::myRound($leadsheetInfoDb['total_amount'],2) : '0.00';
		}

		# Payments
		$return['payments'] = $this->_getPayments($leadId,$leadType);
		usort($return['payments'],array('MyLibs_Quote','sortPaymentsByDate'));
		$return['payments'] = array_map(array('MyLibs_Quote','roundAndCheckEmpty'),$return['payments']);

		# Balance Info
		$return['balance'] = array();
		if (!empty($leadsheetInfoDb['paid_amount'])) {
			$return['balance']['paid_amount'] = MyLibs_Quote::myRound($leadsheetInfoDb['paid_amount'],2);
		}
		else {
			$return['balance']['paid_amount'] = MyLibs_Quote::myRound(
							$this->_db->fetchOne('SELECT COALESCE((SUM(amount)),0) FROM payments WHERE user_id = '.$leadId.' AND user_type=\''.$leadType.'\''),2);
		}
		$return['balance']['due'] = MyLibs_Quote::myRound($return['fees']['total_amount'] - $return['balance']['paid_amount'],2);

		# Notes
		$return['notes'] = !empty($leadsheetInfoDb['comments'])
							? $leadsheetInfoDb['comments']
							: '____________________________________________________________________________________ __________________________________________________________________________________________';
		$return['notes'] = 'Notes: '.$return['notes'];

		# Heard about school
		$return['hear_about'] = !empty($leadsheetInfoDb['hear_about']) ? $leadsheetInfoDb['hear_about'] : '';

		# Scores
		$return['scores'] = array();
		$return['scores']['written'] = !empty($leadsheetInfoDb['score_written']) ? $leadsheetInfoDb['score_written'] : '_________';
		$return['scores']['practical'] = !empty($leadsheetInfoDb['score_practical']) ? $leadsheetInfoDb['score_practical'] : '_________';

		return $return;
	}


	protected function _getLeadInfoByUserId($userID)
	{
		// check to see if the leadsheet exists
		if ($lsID = $this->_db->fetchOne('SELECT id FROM quotes WHERE user_id = '.$userID.' AND user_type = \'enrollments\'')) {
			return $this->_getLeadInfoByLeadsheetId($lsID);
		}

		// fetch lead info
		$leadInfoDb = $this->_db->fetchRow('SELECT * FROM enrollments WHERE id = '.$userID);
		if (empty($leadInfoDb)) {
			return false;
		}

		if (!is_array($leadInfoDb)) {
			return false;
		}

		// new fields
		$leadInfoDb['class_info'] = '';
		$leadInfoDb['score_written'] = '';
		$leadInfoDb['score_practical'] = '';
		$leadInfoDb['tuition'] = '0';
		$leadInfoDb['app_fee'] = '0';
		$leadInfoDb['extras'] = '0';
		$leadInfoDb['total_amount'] = MyLibs_Quote::myRound($leadInfoDb['paid_amount'],2);
		// unset unnecessary fields
		unset($leadInfoDb['id']);
		unset($leadInfoDb['via_sms']);
		unset($leadInfoDb['best_contact_time']);
		unset($leadInfoDb['contact_method']);
		unset($leadInfoDb['plan_enrolling']);
		unset($leadInfoDb['paid_amount']);
		unset($leadInfoDb['class_time']);
		unset($leadInfoDb['cold_lead']);
		unset($leadInfoDb['approval']);
		unset($leadInfoDb['toLead_date']);
		unset($leadInfoDb['enrolled_date']);

		// total paid amount
		$leadInfoDb['paid_amount'] = MyLibs_Quote::myRound($this->_db->fetchOne('SELECT COALESCE((SUM(amount)),0) FROM payments WHERE user_id = '.$userID.' AND user_type=\'enrollments\''),2);

		Zend_Loader::registerAutoload('Zend_Loader',true);
		$this->_db->insert('quotes',array('user_id' => $userID, 'user_type' => 'enrollments', 'details' => Zend_Json::encode($leadInfoDb)));
		Zend_Loader::registerAutoload('Zend_Loader',false);
		$lsID = $this->_db->lastInsertId();

		return $this->_getLeadInfoByLeadsheetId($lsID);
	}

	/**
	 * Get the schools from the database and set them
	 *
	 */
	protected function _setSchools()
	{
		$schoolsDb = $this->_db->fetchAll('SELECT * FROM campuses');

		if (!empty($schoolsDb)) {
			$i = 0;
			foreach ($schoolsDb as $school) {
				$schoolString = array();
				if (!empty($school['name'])) $schoolString['name'] = $school['name'];
				if (!empty($school['address'])) $schoolString['address'] = $school['address'];
				if (!empty($school['city'])) $schoolString['address2'] = $school['city'];
				if (!empty($school['state'])) $schoolString['address2'] .= ', ' . $school['state'];
				if (!empty($school['zip'])) $schoolString['address2'] .= ' ' . $school['zip'];
				if (!empty($school['phone'])) $schoolString['phone'] = $school['phone'];

				//$schoolString = implode(' • ',$schoolString);
				$this->_schools[$i]['details'] = $schoolString;
				$this->_schools[$i]['id'] = $school['id'];
				$i++;
			}
		}
	}

	/**
	 * Get the payments from the database
	 *
	 * @param int $leadId
	 * @param string $leadType
	 * @return unknown
	 */
	protected function _getPayments()
	{
		$payments = $this->_db->fetchAll('SELECT method,date,amount,details FROM payments WHERE quote_id = '.$this->_quoteId.' ORDER BY id DESC LIMIT 3');
		if ($payments != 0)
			return $payments;
		return array();
	}

	static function sortPaymentsByDate($a,$b) {
		$bla = strtotime($a['date']);
		$ble = strtotime($b['date']);
		if (!$a || !$b) return 0;
		if ($a = $b) return 0;
		if ($a < $b) return -1;
		else return 1;
	}
	static function roundAndCheckEmpty($val) {
		foreach ($val as $key => $field) {
			if ($field == '') $val[$key] = ' ';
		}
		//$val['amount'] = "$ ".MyLibs_Quote::myRound($val['amount'],2);
		$val['amount'] = self::myRound($val['amount'],2);
		return $val;
	}

	static function myRound($value, $precision=0)
	{
		// if only 0's, return 0
		if (preg_match('/^[0.]*?$/', $value)) {
			return 0;
		}
		
	    $temp = explode('.',$value);
	    if (!isset($temp[1])) $temp[1]='';
	    
		if ($precision == 0) {
			return trim($temp[0], '');
		}
	    
	    if (strlen($temp[1]) > $precision) {
	    	$value = substr($value,0,strlen($value)-(strlen($temp[1]) - $precision));

	    	return $value;
	    }
	    
	    $i = $precision-strlen($temp[1]);
	    if ($i == $precision && $precision) $value.= '.';

	    while ($i > 0) {$value.='0'; $i--;}

	    return (string)$value;
	}
	
	static public function my_array_search($needle, $haystack)
	{
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
	
	static public function sortByCampusEqId($a, $b)
	{
		if (is_array($a) && !is_array($b)) {
			return -1;
		} elseif(!is_array($a) && is_array($b)) {
			return 1;
		} else {
			if ($a['campus_equipment_id'] > $b['campus_equipment_id']) {
				return 1;
			} elseif ($a['campus_equipment_id'] < $b['campus_equipment_id']) {
				return -1;
			} else {
				return 0;
			}
		}
	}
	
	static public function sortByRelationId($a, $b)
	{
		if (is_array($a) && !is_array($b)) {
			return -1;
		} elseif(!is_array($a) && is_array($b)) {
			return 1;
		} else {
			if ($a['id'] > $b['id']) {
				return 1;
			} elseif ($a['id'] < $b['id']) {
				return -1;
			} else {
				return 0;
			}
		}
	}
}
