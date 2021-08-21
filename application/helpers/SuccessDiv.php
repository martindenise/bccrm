<?php

class Zend_View_Helper_SuccessDiv extends Zend_View_Helper_Abstract
{
	protected $returnHtml = '';
	protected $divHtml = '<div class="successDiv"><img src="%APP_PATH%/images/icons/ok_big.png" style="margin-right: 40px" /> %MESSAGE% </div>';
    
	public function SuccessDiv($message)
    {
		
		$this->returnHtml = str_replace('%MESSAGE%',$message,$this->divHtml);
		$this->returnHtml = str_replace('%APP_PATH%',SITE_ROOT,$this->returnHtml);
		
		return $this->returnHtml;
    }
}
?>