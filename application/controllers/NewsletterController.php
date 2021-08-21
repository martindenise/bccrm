<?php


class NewsletterController extends Zend_Controller_Action
{
	const NO_RECIPIENT = 'No emails found in the database';
	const NO_EMAIL_BODY = 'You need to enter an email message. You can not send an empty email or an email with only images in it.';

	protected $group = '';
	protected $sender = '';
	protected $subject = '';
	protected $body = '';

	function preDispatch()
	{
		$auth = Zend_Auth::getInstance();
		if (!$auth->hasIdentity()) {
			$this->_redirect('auth/login');
		}
	}

    public function indexAction()
    {
		# check for errors
		if ($this->_request->getParam('sendError')) {
			$this->view->errorMessage = constant('self::'.$this->_request->getParam('sendError'));
		}


		# form submited
		if ($this->_request->isPost()) {
			$this->group = $this->_request->getParam('group','all');
			$this->sender = ($this->_request->getParam('email_sender','') != '') ? $this->_request->getParam('email_sender') : SENDER_EMAIL_ADDRESS;
			$this->subject = ($this->_request->getParam('email_subject','') != '') ? $this->_request->getParam('email_subject') : NEWSLETTER_EMAIL_SUBJECT;
			$this->body = $this->_request->getParam('email_body','');

			if (strip_tags($this->body) == '') {
				$this->_redirect('newsletter?sendError=NO_EMAIL_BODY');
			}
			// db object
			$db = Zend_Registry::get('db');

			$result = $this->_sendNewsletter();
			if (!is_array($result))
				$this->_redirect('newsletter/?sendError=NO_RECIPIENT');
			else
				$this->view->succesMessage = 'From a total of '.$result['total'].' email addresses, '.$result['sent'].' have successfully received the email';
		}
		if (!$this->sender != '') $this->view->assign('sender',$this->sender);
		if (!$this->subject != '') $this->view->assign('sender',$this->subject);
		if (!$this->body != '') $this->view->assign('sender',$this->body);
    }

    /**
     * Send a newsletter
     *
     */

    protected function _sendNewsletter() {
    	$recipients = $this->_getRecipients();

    	if (!is_array($recipients))
    		return $recipients;

    	$sent = 0;
    	$total = count($recipients);

    	// build the mail object
    	$mail = new MyLibs_Mail();
    	// configure the mail object
		$mail->setBodyText('You need an HTML compliant email client to see this message')
			 ->setBodyHtml($this->body)
		     ->setFrom($this->sender, SENDER_NAME)
		     ->setSubject($this->subject);
		set_time_limit(0);
		foreach ($recipients as $lead) {
			$mail->addTo($lead['email']);
			if ($mail->send())
				$sent++;
			$mail->clearTo();
		}
		return array('sent' => $sent, 'total' => $total);
    }

    /**
     * Get email recipients based on the group type
     *
     */
    protected function _getRecipients() {
    	$sql = '';
    	if ($this->group == 'cold_leads') $where = 'cold_lead = 1';
    	elseif($this->group == 'dead_leads') $where = 'cold_lead = 2';
    	else $where = 'cold_lead = 0';

    	$leads = 'SELECT DISTINCT email FROM leads WHERE approval = 1 AND via_sms = \'No\' AND contact_method = \'Email\' AND '.$where;
    	$enrollments = 'SELECT DISTINCT email FROM booked WHERE approval = 1 AND via_sms = \'No\' AND contact_method = \'Email\' AND '.$where;
    	$graduates = 'SELECT DISTINCT email FROM graduates WHERE approval = 1 AND via_sms = \'No\' AND contact_method = \'Email\' AND '.$where;
    	$all = $leads.' UNION '.$enrollments.' UNION '.$graduates;

    	if (in_array($this->group,array('leads','booked'))) {
    		$sql = ${$this->group};
    	}
    	else {
    		$sql = $all;
    	}

    	$db = Zend_Registry::get('db');

    	$recipients = $db->fetchAll($sql);
    	if (!count($recipients))
    		return NO_RECIPIENTS;

		return $recipients;
    }
}
