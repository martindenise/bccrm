<?php

class Zend_View_Helper_RankTables extends Zend_View_Helper_Abstract
{
    public $view;

    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }

    public function RankTables() {
		return $view->render('stats/bla.phtml');
    }

}

?>