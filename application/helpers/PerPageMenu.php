<?php

class Zend_View_Helper_PerPageMenu extends Zend_View_Helper_Abstract
{  
	public function PerPageMenu($baseLink,$class = '')
    {
		return '
		<div class="'.$class.'">
			<a href="'.$baseLink.'results=5">5</a> 
			<a href="'.$baseLink.'results=10">10</a> 
			<a href="'.$baseLink.'results=25">25</a> 
			<a href="'.$baseLink.'results=50">50</a> 
			results/page
		</div>';
    }
}
?>