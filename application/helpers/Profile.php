<?php

class Zend_View_Helper_Profile extends Zend_View_Helper_Abstract
{
	const TABLES_PATH = '/views/scripts/profile/tables/';

	public $_tableBasicDetailsFilename 	= 'basicDetails.phtml';
	public $_tableLeadDetailsFilename 	= 'leadDetails.phtml';

	// TABLES HTML
	protected $_tableBasicDetails  		= '';
	protected $_tableLeadDetails   		= '';
	protected $_tableQuotes 			= '';
	protected $_tablePayments 			= '';
	protected $_tableCommunication 		= '';
	protected $_profileContainerStart 	= '<div id="profileContainer">';
	protected $_profileContainerEnd 	= '</div>';
	protected $_leadData 		   		= array();
	protected $_leadType 		   		= '';
	protected $_payments				= array();
	protected $_contactLog				= array();

    public $view;

    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }

	public function Profile($leadData = false, $leadType = false, $_config = array('basic' => true, 'lead' => true, 'quotes' => true, 'payments' => true, 'communication' => true ))
    {
		if (empty($leadData) || !is_array($leadData) || empty($leadType))
			return '';

		// process lead data
		foreach ($leadData as $key => $value) {
			$this->_leadData['{'.$key.'}'] = $value;
		}
		$this->_leadType = $leadType;
		$this->_contactLog = $this->_leadData['{log}'];
		unset($this->_leadData['{log}']);
		$this->_payments = $this->_leadData['{payments}'];
		unset($this->_leadData['{payments}']);
		$this->_quotes = $this->_leadData['{quotes}'];
		unset($this->_leadData['{quotes}']);

		$html = '';
		$html.= $this->_profileContainerStart;

		if ($_config['basic']) {
			$html.= $this->buildBasicDetailsTable();
			$html.= '<br />';
		}
		if ($_config['lead']) {
			$html.= $this->buildLeadDetailsTable();
			$html.= '<br />';
		}
    	if ($_config['quotes']) {
			$html.= $this->buildQuotesTable();
			$html.= '<br />';
		}
		if ($_config['payments']) {
			$html.= $this->buildPaymentsTable();
			$html.= '<br />';
		}
		if ($_config['communication']) {
			$html.= $this->buildCommunicationTable();
			$html.= '<br />';
		}

		$html.= $this->_profileContainerEnd;

		return $html;
    }

    public function buildBasicDetailsTable()
    {
    	$this->_tableBasicDetails = file_get_contents(APPLICATION_PATH . self::TABLES_PATH . $this->_tableBasicDetailsFilename);
    	$this->_tableBasicDetails = str_replace(array_keys($this->_leadData),array_values($this->_leadData),$this->_tableBasicDetails);

    	return $this->_tableBasicDetails;
    }

    public function buildLeadDetailsTable()
    {
		$this->_tableLeadDetails = file_get_contents(APPLICATION_PATH . self::TABLES_PATH . $this->_tableLeadDetailsFilename);

		// prepare the data for the table
		$this->_leadData['{date_added}'] = !empty($this->_leadData['{date_added}']) ? date("m/d/Y",$this->_leadData['{date_added}']) : 'Not available';
		$this->_leadData['{next_followup}'] = !empty($this->_leadData['{next_followup}']) ? date("m/d/Y",$this->_leadData['{next_followup}']) : 'Only for leads';
		$this->_leadData['{total_amount}'] = !empty($this->_leadData['{total_amount}']) ? '$'.$this->_leadData['{total_amount}'] : 'None';
		$this->_leadData['{paid_amount}'] = !empty($this->_leadData['{paid_amount}']) ? '$'.$this->_leadData['{paid_amount}'] : 'None';
		$this->_leadData['{event_date}'] = !empty($this->_leadData['{event_date}']) ? $this->_leadData['{event_date}'] : 'Not available';
		$this->_leadData['{class_time}'] = !empty($this->_leadData['{class_time}']) ? $this->_leadData['{class_time}'] : 'Not available';
		$this->_leadData['{graduated}'] = !empty($this->_leadData['{graduated}']) ? $this->_leadData['{graduated}'] : 'Not available';
		if ($this->_leadData['{graduated}'] == "Yes" && !empty($this->_leadData['{completion_date}']))
			$this->_leadData['{graduated}'].= ', on '.$this->_leadData['{completion_date}'];

		// html for the lead type
		$leadHtml = '';
		if (empty($this->_leadData['{cold_lead}'])) {
			switch ($this->_leadType) {
				case 'new_leads': {
						$leadHtml = 'New Lead';
						$userIsAdmin = Zend_Auth::getInstance()->getStorage()->read()->admin;
						if ($userIsAdmin) {
							$leadHtml.= '<br /><a href="'.$this->view->url(array('controller'=>'newleads','action'=>'confirm')).'?id='.$this->_leadData['{id}'].'">Promote to Lead</a>';
						}
					} break;

				case 'leads': {
						$leadHtml = 'Lead<br />';
						$leadHtml.= '<a href="'.$this->view->url(array('controller'=>'profile','action'=>'changetype')).'/?type='.$this->_leadType.'&id='.$this->_leadData['{id}'].'">Change to Booked</a>';
					} break;

				case 'booked': {
						$leadHtml = 'Booked<br />';
					} break;

				default: break;
			}
		}
		else {
			if ($this->_leadType == 'new_leads') $this->_leadType = 'New Contact';
			if ($this->_leadData['{cold_lead}'] == '1') $leadHtml.= ucfirst(substr($this->_leadType,0)).' - Cold Lead<br />';
			else $leadHtml.= ucfirst(substr($this->_leadType,0)).' - Dead Lead<br />';

			$leadHtml.= '(change back to <a href="'.$this->view->url(array('controller'=>'profile','action'=>'changetype')).'/?nocold&type='.$this->_leadType.'&id='.$this->_leadData['{id}'].'">'.substr($this->_leadType,0).'</a>)';

/*			$selectValues = array();
			$selectAttributes = array('id' => 'change_lead', 'style' => 'font-size: 11px;');*/
			//$leadHtml.= $this->view->formSelect('change_lead',,);
		}
		$this->_leadData['{lead_html}'] = $leadHtml;

		// make the replaces
		$this->_tableLeadDetails = str_replace(array_keys($this->_leadData),array_values($this->_leadData),$this->_tableLeadDetails);

		return $this->_tableLeadDetails;
    }

    public function buildPaymentsTable()
    {
		//$this->_tableCommunication = file_get_contents(APPLICATION_PATH . self::TABLES_PATH . $this->_tableCommunicationFilename);

		$tableConfig = array (
			'attributes' => array(
								'width' => '90%',
								'style' => 'margin:auto;',
								'class' => 'tblStyle2',
								'border' => '0',
							),
			'caption' => 'Payment',
			'headers' => array(
								array('Date','20%'),
								array('Method','20%'),
								array('Details','35%'),
								array('Amount','10%'),
								array('Delete','15%')
							),
			'alteredFields' => array (
								'3' => 'php:"$"."%field%"',
								'4' => '<a href="'
										. $this->view->url(array('controller'=>'profile','action'=>'payment'))
										. '/?do=delete&type=' . $this->_leadType
										. '&id='.$this->_leadData['{id}']
										. '&pid=%field%'
										. '">Delete</a>'
							),
			'tableColumnsAlign' => 'center'
			);

		$this->_tablePayments = $this->view->table($this->_payments,$tableConfig);

    	return $this->_tablePayments;
    }

    public function buildCommunicationTable()
    {
		//$this->_tableCommunication = file_get_contents(APPLICATION_PATH . self::TABLES_PATH . $this->_tableCommunicationFilename);

		$tableConfig = array (
			'attributes' => array(
								'width' => '90%',
								'style' => 'margin:auto;',
								'class' => 'tblStyle2',
								'border' => '0',
							),
			'caption' => 'Communication',
			'headers' => array(
								array('Time &amp; Date','30%'),
								array('Sales Person','30%'),
								array('Type','20%'),
								array('View Details','20%')
							),
			'alteredFields' => array (
								'0' => 'php:date("H:i \o\\\n m/d/Y",%field%)',
								'3' => '<a href="javascript:contactPopup(\'%field%\')">Details</a>'
							),
			'tableColumnsAlign' => 'center'
			);

		$this->_tableCommunication = $this->view->table($this->_contactLog,$tableConfig);

    	return $this->_tableCommunication;
    }
    
    public function buildQuotesTable()
    {
		$tableConfig = array (
			'attributes' => array(
								'width' => '90%',
								'style' => 'margin:auto;',
								'class' => 'tblStyle2',
								'border' => '0',
							),
			'caption' => 'Quotes',
			'headers' => array(
								array('Event Date','30%'),
								array('Event Location','30%'),
								array('Quote Date','20%'),
								array('View','10%'),
								array('Delete','10%')
							),
			'alteredFields' => array (
								'2' => 'php:date("m/d/Y",%field%)',
								'3' => '<a href="'
										. $this->view->url(array('controller'=>'profile','action'=>'quote'))
										. '/?type=' . $this->_leadType
										. '&id='.$this->_leadData['{id}']
										. '&lId=%field%'
										. '">View</a>',
								'4' => '<a href="'
										. $this->view->url(array('controller'=>'profile','action'=>'delete-quote'))
										. '/?type=' . $this->_leadType
										. '&id='.$this->_leadData['{id}']
										. '&lId=%field%'
										. '" class="deleteQuote">Delete</a>'
							),
			'tableColumnsAlign' => 'center'
			);

		$this->_tableCommunication = $this->view->table($this->_quotes,$tableConfig);

    	return $this->_tableCommunication;
    }
}
?>