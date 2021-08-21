<?php



class Model_DbTable_Leads extends Zend_Db_Table_Abstract
{
    /** Table name */
    protected $_name    = 'leads';
    protected $_db		= null;
    protected $_userSchools = array();

    function __construct()
    {
		$this->_db = Zend_Registry::get('db');
		// allowed schools for the user
		$allowedSchools = Zend_Auth::getInstance()->getStorage()->read()->allowed_campuses;
		if ('all' != $allowedSchools) {
			$this->_userSchools = explode(',',$allowedSchools);
		}
    }

    public function insert(array $data)
    {
        return parent::insert($data);
    }

    public function fetch($pageNo = 1, $resultOnPage = 5, $wheres = '', $fetchMode = Zend_Db::FETCH_NUM)
    {
		$this->_db->setFetchMode($fetchMode);

		$select = $this->_db->select()
					 ->calcFoundRows(true)
					 ->from(array('t1' => 'leads'),
					 		array("CONCAT_WS(' ',first_name,last_name)","email","t2.name","date_added","t3.name","next_followup","id"))
					 ->join(array('t2' => 'campuses'),
					 		't1.campus_id = t2.id',
					 		array())
					 ->join(array('t3' => 'users'),
					 		't1.sales_person_id = t3.id',
					 		array())
					 ->where('t1.approval = 1')
					 ->limitPage($pageNo,$resultOnPage);

		// add wheres
		if (!empty($wheres)) {
			$select->where($wheres);
		}
		// add allowed schools
		if (count($this->_userSchools)) {
			$first = 1;
			foreach ($this->_userSchools as $schoolId) {
				if ($first) {
					$select->where('t1.campus_id = ?',$schoolId);
					$first = 0;
				} else {
					$select->orWhere('t1.campus_id = ?',$schoolId);
				}
			}
		}

		return $select->query()->fetchAll();
    }

    public function fetchFollowUps($pageNo,$resultOnPage)
    {

		$sql = "SELECT SQL_CALC_FOUND_ROWS CONCAT_WS(' ',t1.first_name,t1.last_name),t1.email,t2.name,t1.date_added,t1.next_followup,t1.id
					FROM leads AS t1
					LEFT JOIN campuses AS t2 ON t2.id=t1.campus_id
					WHERE t1.approval = '1'
					AND ".time()."-t1.next_followup>".FOLLOW_UP_STEP."
					AND t1.sales_person_id = ".Zend_Auth::getInstance()->getStorage()->read()->id."
					LIMIT ".(($pageNo-1)*$resultOnPage).", ".$resultOnPage;
		
		$stmt = parent::getAdapter()->query($sql);
		return $stmt->fetchAll();
    }

    public function fetchOne($sql)
    {
		return parent::getAdapter()->fetchOne($sql);
    }
}
