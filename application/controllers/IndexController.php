<?php

class IndexController extends Zend_Controller_Action
{

	protected $_newleadsModel;
	protected $_leadsModel;

	function preDispatch()
	{
		$auth = Zend_Auth::getInstance();
		if (!$auth->hasIdentity()) {
			$this->_redirect('auth/login');
		}
	}

    public function indexAction()
    {
    	$auth = Zend_Auth::getInstance();
		if ($auth->getStorage()->read()->admin == 0) {
			$this->_redirect('leads/followups');
		}

		// new leads table config
		$this->view->newLeadsTableConfig = array (
			'attributes' => array(
								'width' => '90%',
								'style' => 'margin:auto',
								'class' => 'tblStyle1'
							),
			'caption' => 'Last 5 New Contacts',
			'headers' => array(
								array('Name','20%'),
								array('Email Address','23%'),
								array('Market','18%'),
								array('Add Date','12%'),
								array('Sales Person','16%'),
								array('Accept','6%'),
								array('Action','5%')
							),
			'alteredFields' => array (
								'3' => 'php:date("m/d/Y",%field%)',
								'5' => '<a href="'.$this->view->url(array('controller' => 'newleads', 'action' => 'confirm')).'?id=%field%" style="text-decoration:none;border:0;"><img src="'.SITE_ROOT.'/images/icons/ok_small.png" style="border:0" /></a>',
								'6' => '<a href="'.$this->view->url(array('controller' => 'profile'),'default',true).'/?type=new_leads&id=%field%" style="text-decoration:none;border:0;margin-right:15px;"><img src="'.SITE_ROOT.'/images/icons/profile_small.png" style="border:0" alt="Profile" /></a>
										<a href="'.$this->view->url(array('controller' => 'newleads', 'action' => 'stock'),'default',true).'?id=%field%" style="text-decoration:none;border:0;margin-right:10px;"><img src="'.SITE_ROOT.'/images/icons/contact_small.png" style="border:0" alt="Send Stock Email" /></a>
										<a href="'.$this->view->url(array('controller' => 'newleads', 'action' => 'delete')).'?id=%field%" style="text-decoration:none;border:0;" onclick="return doDeleteConfirm()"><img src="'.SITE_ROOT.'/images/icons/delete_small.png" style="border:0" /></a>'
							)
			);

		$newLeadsModel = $this->_getNewleadsModel();
		$newLeadsModel->fetchPage(1,5);
		$this->view->totalnewLeads = $newLeadsModel->fetchTotalRows();
		$startNewleads = ($this->view->totalnewLeads < 5) ? 1 : $this->view->totalnewLeads/5;
		$this->view->newLeads = $newLeadsModel->fetchPage($startNewleads,5);

		////////////////////////////////////////////

		// leads table config
		$this->view->leadsTableConfig = array (
			'attributes' => array(
								'width' => '90%',
								'style' => 'margin:auto',
								'class' => 'tblStyle1'
							),
			'caption' => 'Last 5 Follow Up Reminders',
			'headers' => array(
								array('Name','23%'),
								array('Email Address','24%'),
								array('Market','21%'),
								array('Add Date','15%'),
								array('Last Contact','12%'),
								array('Action','5%')
							),
			'alteredFields' => array (
								'3' => 'php:date("m/d/Y",%field%)',
								'4' => 'php:date("m/d/Y",%field%)',
								'5' => '<a href="'.$this->view->url(array('controller' => 'profile', 'action' => 'contact'),'default',true).'?type=leads&id=%field%" style="text-decoration:none;border:0;"><img src="'.SITE_ROOT.'/images/icons/email_small.png" style="border:0" title="Contact" /></a>
										<a href="'.$this->view->url(array('controller' => 'leads', 'action' => 'delete')).'?id=%field%" style="text-decoration:none;border:0; margin-left:15px;" onclick="return doDeleteConfirm()"><img src="'.SITE_ROOT.'/images/icons/delete_small.png" style="border:0" /></a>'
							)
			);

		$leadsModel = $this->_getLeadsModel();
		$leadsModel->fetchFollowUps(1,5);
		$this->view->totalFollowups = $leadsModel->fetchTotalRows();
		$start = ($this->view->totalFollowups < 5) ? 1 : $this->view->totalFollowups/5;
		$this->view->leadsFollowups = $leadsModel->fetchFollowUps($start,5);

		$db = Zend_Registry::get('db');
		$this->view->totalConfirmations = $db->fetchOne('SELECT COUNT(*) FROM (SELECT id FROM booked WHERE approval != 1 UNION SELECT id FROM leads WHERE approval != 1 UNION SELECT id FROM graduates WHERE approval != 1) AS u');
    }

    protected function _getNewleadsModel()
    {
        if (null === $this->_newleadsModel) {
            require_once APPLICATION_PATH . '/models/Newleads.php';
            $this->_newleadsModel = new Model_Newleads();
        }
        return $this->_newleadsModel;
    }

    protected function _getLeadsModel()
    {
        if (null === $this->_leadsModel) {
            require_once APPLICATION_PATH . '/models/Leads.php';
            $this->_leadsModel = new Model_Leads();
        }
        return $this->_leadsModel;
    }
}
