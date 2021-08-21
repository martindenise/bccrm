<?php
class Model_Leads
{

    protected $_table;

    /**
     * Retrieve table object
     *
     * @return Model_Leads_Table
     */
    public function getTable()
    {
        if (null === $this->_table) {
            require_once APPLICATION_PATH . '/models/DbTable/Leads.php';
            $this->_table = new Model_DbTable_Leads;
        }
        return $this->_table;
    }

    public function fetch($pageNo = 1, $resultOnPage = 5, $wheres = array(), $fetchMode = Zend_Db::FETCH_NUM)
    {
    	if (count($wheres)) {
    		$wheres = implode(' AND ',$wheres);
    	}

    	return $this->getTable()->fetch($pageNo,$resultOnPage,$wheres,$fetchMode);
    }

    public function fetchFollowUps($pageNo,$resultOnPage)
    {
        return $this->getTable()->fetchFollowUps($pageNo,$resultOnPage);
    }

    public function fetchTotalRows() {
		return $this->getTable()->fetchOne('SELECT FOUND_ROWS()');
    }
}
