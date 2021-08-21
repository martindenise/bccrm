<?php

class Zend_View_Helper_CategoryMenu extends Zend_View_Helper_Abstract
{
	protected $menuHtml = '';

    public $view;

    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }

	public function CategoryMenu($menuPath)
    {
/*		$fhdl = fopen(APPLICATION_PATH . $menuPath,'r');
		while (!feof($fhdl))
			$this->menuHtml.= fgets($fhdl,1024);
		fclose($fhdl);*/

		$this->menuHtml = file_get_contents(APPLICATION_PATH . $menuPath);

		$this->menuHtml = str_replace('%APP_PATH%',SITE_ROOT,$this->menuHtml);

		if (strpos($this->menuHtml,'%LEAD_TYPE%'))
			$this->menuHtml = str_replace('%LEAD_TYPE%',$this->view->leadType,$this->menuHtml);
		if (strpos($this->menuHtml,'%LEAD_ID%'))
			$this->menuHtml = str_replace('%LEAD_ID%',$this->view->leadId,$this->menuHtml);
		if (strpos($this->menuHtml,'%MARKET_ID%')) {
			if (empty($this->view->marketId)) {
				$this->view->marketId = 0;
			}
			$this->menuHtml = str_replace('%MARKET_ID%',$this->view->marketId,$this->menuHtml);
		}
    	if (strpos($this->menuHtml,'%QUOTE_ID%')) {
			if (empty($this->view->quoteId)) {
				$this->view->quoteId = 0;
			}
			$this->menuHtml = str_replace('%QUOTE_ID%',$this->view->quoteId,$this->menuHtml);
		}

		return $this->menuHtml;
    }
}
?>