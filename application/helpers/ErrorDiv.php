<?php

class Zend_View_Helper_ErrorDiv extends Zend_View_Helper_Abstract
{
	protected $returnHtml = '';
	protected $divHtml = '<div class="errorDiv"><img src="%APP_PATH%/images/icons/error_big.png" style="margin-right: 40px;vertical-align:middle;" /> The following error(s) have occured: %ERRORS_DIV% </div>';
	protected $errorsHtml = '<div style="padding-left: 60px; margin:0"> %ERRORS% </div>';
    
	public function ErrorDiv($errors)
    {
    	$errorsHtml = '';
    	if (is_array($errors)) {
			foreach ($errors as $error)
				$errorsHtml .= '-> '.$error.'<br />';
    	}
    	else {
    		$errorsHtml .= $errors.'<br />';
    	}
			
    	$this->errorsHtml = str_replace('%ERRORS%',$errorsHtml,$this->errorsHtml);
    	
		$this->returnHtml = str_replace('%ERRORS_DIV%',$this->errorsHtml,$this->divHtml);
		$this->returnHtml = str_replace('%APP_PATH%',SITE_ROOT,$this->returnHtml);
		
		return $this->returnHtml;
    }
}
?>