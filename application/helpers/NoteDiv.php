<?php

class Zend_View_Helper_NoteDiv extends Zend_View_Helper_Abstract
{
	protected $returnHtml = '';
	protected $divHtml = '<div class="noteDiv">
						<div style="margin: auto; width: 20px; float:left">
						<img src="%APP_PATH%/images/icons/note.png" style="margin-right: 40px;vertical-align:middle;" />
						</div>
						<div style="padding-left: 40px; margin:0"> %MESSAGE% </div>
						</div>';

	public function NoteDiv($message)
    {
		$this->returnHtml = str_replace('%MESSAGE%',$message,$this->divHtml);
		$this->returnHtml = str_replace('%APP_PATH%',SITE_ROOT,$this->returnHtml);

		return $this->returnHtml;
    }
}
?>