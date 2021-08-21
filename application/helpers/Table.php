<?php

class Zend_View_Helper_Table extends Zend_View_Helper_Abstract
{
	protected $returnHtml = '';
	protected $tableAttributes = array();
    protected $tableCaption = '';
    protected $tableHeaders = array();
	protected $tableColumnsWidths = array();
	protected $alteredFields = array();
    protected $tableData = array(); // bidimensional array
    
    public function Table($data, $tableConfig = array()) {
    	$this->tableData = $data;
    	$this->tableCaption = (!empty($tableConfig['caption'])) ? $tableConfig['caption'] : array();
    	$this->tableAttributes = (!empty($tableConfig['attributes'])) ? $tableConfig['attributes'] : array();
     	$this->setHeaders((!empty($tableConfig['headers'])) ? $tableConfig['headers'] : array());
     	$this->alteredFields = (!empty($tableConfig['alteredFields'])) ? $tableConfig['alteredFields'] : array();
     	$this->tableColumnsWidths = (!empty($tableConfig['tableColumnsWidths'])) ? $tableConfig['tableColumnsWidths'] : array();
     	$this->tableColumnAlign = (!empty($tableConfig['tableColumnsAlign'])) ? $tableConfig['tableColumnsAlign'] : false;
     	
     	return $this->_buildTable();	
    }
       
    private function setHeaders($headers) {
    	if (count($headers)) {
    		foreach ($headers as $value) {
    			if (is_array($value)) {
    				$this->tableHeaders[] = $value[0];
    				$this->tableColumnsWidths[] = $value[1];
    			}
    			else {
    				$this->tableHeaders[] = $value;
    			}
    		}
    	}
    }
    
    private function _buildTable() {
    	$tableHtml = '';
    	$tableHtml.= '<table';
    	// add attributes
    	if (count($this->tableAttributes)) {
    		foreach ($this->tableAttributes as $attrName => $attrValue) {
    			$tableHtml.= ' '.$attrName.'="'.$attrValue.'"';
    		}
    	}
    	$tableHtml.= '>';
    	
    	// add caption if there is one
    	if (!empty($this->tableCaption)) {
    		$tableHtml.= '<caption>'.$this->tableCaption.'</caption>';
    	}

    	// add headers if there are any
    	if (count($this->tableHeaders)) {
    		$tableHtml.= '<thead><tr>';
    		foreach ($this->tableHeaders as $key => $headerTitle) {
    			$tableHtml.= '<th '.($this->tableColumnAlign ? 'style="text-align:'.$this->tableColumnAlign.'"' : '');
    			if (count($this->tableColumnsWidths)) {
    				$tableHtml.= ' width="'.$this->tableColumnsWidths[$key].'"';
    			}
    			$tableHtml.= '>';
    			$tableHtml.= $headerTitle;
    			$tableHtml.= '</th>';
    		}
    		$tableHtml.= '</tr></thead>';
    	}
    	
    	$tableHtml.= '<tbody>';
    	// add the data to the table
    	$colCount = (empty($this->tableHeaders)) ? count($this->tableData[0]) : count($this->tableHeaders);
    	if (count($this->tableData) && is_array($this->tableData)) { // if we have rows to display
    		foreach ($this->tableData as $rowNo => $rowData) {
    			// start building the row
    			$tableHtml.= '<tr>';
    			$i = 0;
    			foreach ($rowData as $key => $value) {//for ($i = 0; $i < $colCount; $i++) {
    				// check to see if we have to modify the field
    				if (isset($this->alteredFields[(string)$i])) {
    					// if it is php code
    					if (strpos($this->alteredFields[$i],'php:') !== false) {
    						eval('$rowData[$key] = '.str_replace('%field%',$rowData[$key],substr($this->alteredFields[$i],4)).';');
    					}
    					else {
    						$rowData[$key] = str_replace('%field%',$rowData[$key],$this->alteredFields[$i]);
    					}
    				}
    				$tableHtml.= '<td '.($this->tableColumnAlign ? 'style="text-align:'.$this->tableColumnAlign.'"' : '').'>'.$rowData[$key].'</td>';
    				$i++;
    				if ($i == $colCount) break;
    			}
    			$tableHtml.= '</tr>';
    		}
    	}
    	else { // if now show error message
    		$tableHtml.= '<tr><td colspan="'.$colCount.'" '.($this->tableColumnAlign ? 'style="text-align:'.$this->tableColumnAlign.'"' : '').'>No records.</td></tr>';
    	}
    	
    	$tableHtml.= '</tbody>';
    	$tableHtml.= '</table>';
    	
    	// reset the table properties
    	$this->tableAttributes = array();
    	$this->tableData = array();
    	$this->tableHeaders = array();
    	
    	return $tableHtml;
    }
}
?>