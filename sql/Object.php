<?php

class SqlObject {
  protected $_table = null;
  protected $_tableId = null;
  protected $_identity = true;
  protected $_db;
  protected $_id = null;
  protected $_data = null;
  protected $_loaded = false;

  public function __construct($id=null) {
    if (!isset($this->_table)) {
      die('Abstract object.');
    }
    $this->_db = Application::get()->db;
    $this->setId($id);
  }

  public function reset() {
    $this->setId(null);
  }

  public function getId() {
    $id = $this->_id;
    if (is_array($id) && (count($id)==1) ) {
      $id = array_shift($id);
    }
    return $id;
  }

  public function setId($id) {
    if (!is_null($id) && !is_array($id)) {
      $tableId = $this->_tableId?$this->_tableId:$this->_table.'_id';
      $id = array($tableId => $id);
    }
    $this->_id = $id;
    $this->setData($data=null);
    $this->_loaded = false;
  }

  public function getData($onlyData=false) {
    if (!$this->_loaded) {

      $sql = $this->_getSelectSql();
      $res = $this->_db->doQuery($sql);
      $row = $this->_db->fetchAssoc($res);

      $this->_clearHash($row);
      $this->setData($row);
      $this->_loaded = true;

    }
    if (!is_array($this->_data)) {
      $ret = null;
    } elseif ($onlyData) {
      $ret = $this->_data;
    } else {
      $ret = array_merge($this->_data,$this->_id);
    }
    return $ret;
  }

  private function _getSelectSql() {
    $q = '';
    
    if (!is_array($this->_id)) die('Invalid initialization of instance '.get_class($this).' with ID '.var_export($this->_id,true).'!'); 

    foreach ($this->_id as $key => $value) {
      $q .= ($q ? ' AND ' : '') . $this->_db->escapeSetting($value, $key);      
    }
    $q = 'SELECT * FROM '. $this->_db->prepareName($this->_table) .(empty($q) ? '' : (' WHERE '. $q));
    return $q;
  }

  private function &_clearHash(&$data) {
    foreach (array_keys($this->_id) as $key) {
      unset ($data[$key]);
    }
    return $data;
  }

  public function setData($data) {
    $this->_data =& $data;
  }

  public function save() {
    $je_null = false;
    if (is_array($this->_id)){
      $je_null = true;
      foreach ($this->_id as $val){ if (!is_null($val)) $je_null = false; }
    }
    if (is_null($this->_id) || $je_null) {
      $res = $this->_db->insert($this->_data, $this->_table, $this->_identity);
      $this->setId($this->_db->getLastIdentity());
    } else {
      $res = $this->_db->update($this->_data, $this->_id, $this->_table);
    }
    
    $this->_loaded = false;
  }

  public function delete() {
    while ($error = true) {

      if (!is_array($this->_id)) { break; }

      $this->getData(); 
      
      $res = $this->_preDelete();
      if (!$res) { break; }
      
      $res = $this->_db->delete($this->_id, $this->_table);

      $res = $this->_postDelete();
      if (!$res) { break; }

      $error = false;
      $this->reset();
      break;
    }
    return $error;
  }

  protected function _preDelete($ret=true) { return $ret; }

  protected function _postDelete($ret=true) { return $ret; }
}

class PgSqlObject extends SqlObject {
  protected $_indentitySequence = null;
  
  protected function _getLastIdentity() {
    $ret = null;
    if ($this->_indentitySequence) {
      $query = sprintf('SELECT CURRVAL(\'"%s"\')', $this->_indentitySequence);
      if (($res=$this->_db->doQuery($query))&&($row=$this->_db->fetchAssoc($res))) {
        $ret = $row['currval'];
      }
    }
    
    return $ret;
  }
  
  public function save() {
    $je_null = false;
    if (is_array($this->_id)){
      $je_null = true;
      foreach ($this->_id as $val){ if (!is_null($val)) $je_null = false; }
    }
    if (is_null($this->_id) || $je_null) {
      $res = $this->_db->insert($this->_data, $this->_table, $this->_identity);
      $this->setId($this->_getLastIdentity());
    } else {
      $res = $this->_db->update($this->_data, $this->_id, $this->_table);
    }
    
    $this->_loaded = false;
  }
}

?>
