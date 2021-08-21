<?php
class Model_Booked
{

    protected $_table;

    /**
     * Retrieve table object
     *
     * @return Model_Enrollments_Table
     */
    public function getTable()
    {
        if (null === $this->_table) {
            require_once APPLICATION_PATH . '/models/DbTable/Booked.php';
            $this->_table = new Model_DbTable_Booked;
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

    public function fetchTotalRows() {
		return $this->getTable()->fetchOne('SELECT FOUND_ROWS()');
    }
}
