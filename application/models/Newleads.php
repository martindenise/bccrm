<?php
class Model_Newleads
{
    /** Model_Table_Newleads */
    protected $_table;

    /**
     * Retrieve table object
     *
     * @return Model_Guestbook_Table
     */
    public function getTable()
    {
        if (null === $this->_table) {
            // since the dbTable is not a library item but an application item,
            // we must require it to use it
            require_once APPLICATION_PATH . '/models/DbTable/Newleads.php';
            $this->_table = new Model_DbTable_Newleads;
        }
        return $this->_table;
    }

    /**
     * Fetch all entries
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function fetchPage($pageNo,$resultOnPage)
    {
        // we are gonna return just an array of the data since
        // we are abstracting the datasource from the application,
        // at current, only our model will be aware of how to manipulate
        // the data source (dbTable).
        // This ALSO means that if you pass this model
        return $this->getTable()->fetchNewLeads($pageNo,$resultOnPage);
    }

    /**
     * Fetch an individual entry
     *
     * @param  int|string $id
     * @return null|Zend_Db_Table_Row_Abstract
     */
    public function fetchEntry($id)
    {
        $table = $this->getTable();
        $select = $table->select()->where('id = ?', $id);
        // see reasoning in fetchEntries() as to why we return only an array
        return $table->fetchRow($select)->toArray();
    }

    public function fetchTotalRows() {
		return $this->getTable()->fetchOne('SELECT FOUND_ROWS()');
    }
}
