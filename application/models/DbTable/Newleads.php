<?php


/**
 * This is the DbTable class for the new_leads table.
 */
class Model_DbTable_Newleads extends Zend_Db_Table_Abstract
{
    /** Table name */
    protected $_name    = 'new_leads';

    public function insert(array $data)
    {
        return parent::insert($data);
    }

    public function fetchNewleads($pageNo,$resultOnPage) {
		$db = Zend_Registry::get('db');
		$db->setFetchMode(Zend_Db::FETCH_NUM);

		// build sql query
		$select = $db->select()
					 ->calcFoundRows(true)
					 ->from(array('t1' => 'new_leads'),
					 		array("CONCAT_WS(' ',first_name,last_name)",
					 			  "email",
					 			  "t2.name",
					 			  "date_added",
					 			  "t3.name",
					 			  "id",
					 			  "id"
					 		))
					 ->join(array('t2' => 'campuses'),
					 		't1.campus_id = t2.id',
					 		array())
					 ->join(array('t3' => 'users'),
					 		't1.sales_person_id = t3.id',
					 		array())
					 ->order(array('date_added DESC'))
					 ->limitPage($pageNo,$resultOnPage);
		
		return $select->query()->fetchAll();
    }

    public function fetchOne($sql)
    {
		return parent::getAdapter()->fetchOne($sql);
    }
}
