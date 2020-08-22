<?php

class DataSourceSettings {
  protected $_page = 1;
  protected $_onPage = -1;
  protected $_addIndexes = true;

  public function __construct(&$params=null) {
    if (isset($params['page'])) { $this->_page =& $params['page']; }
    if (isset($params['onPage'])) { $this->_onPage =& $params['onPage']; }
    if (isset($params['addIndexes'])) { $this->_addIndexes =& $params['addIndexes']; }
  }

  public function &getPage() { return $this->_page; }

  public function setPage($page) { $this->_page = $page; }

  public function &getOnPage() { return $this->_onPage; }

  public function &getAddIndexes() { return $this->_addIndexes; }
}

class DataSource {
  public $settings;
  public $currentData = false; 
  public $count = 0; 
  public $records = 0; 
  public $firstRecord = 1; 
  public $indexName = '__i';
  public $pageIndexName = '__iPage';
  private $_justReseted = false;

  public function __construct($settings=null) {
    if (isset($settings)) {
      $this->settings = $settings;
    } else {
      $this->settings = new DataSourceSettings;
    }
  }

  public function mergeFilterFromGrid($sett){ }

  protected function _mergeFilter($filter){ }

  public function reset($softReset = false) {
    if (!$this->_justReseted) {
      $this->_resetData();
      if ($this->_resetSource($softReset)) {
        if ($this->_preCondition()) {
          $this->nextData();
        }
      }
      $this->_justReseted = true;
    }
  }

  protected function _resetData() {
    $this->currentData = false;
    $this->count = 0;
    $this->firstRecord = 1;
    $this->records = 0; 
  }

  protected function _resetSource($softReset = false) { return true; }

  public function nextData() {
    $this->count++;
    $this->_justReseted = false;
    if (!$this->_postCondition()) {
      $this->currentData = false;
    }
    if (is_array($this->currentData) && $this->settings->getAddIndexes()) {
      $this->currentData[$this->indexName] = $this->firstRecord + $this->count - 1;
      $this->currentData[$this->pageIndexName] = $this->count;
    }
  }

  protected function _preCondition() {
    $page = $this->settings->getPage();
    $onPage = $this->settings->getOnPage();
    if ($page < 1) {
      $firstRecord = 1;
    } else {
      $firstRecord = (($page - 1) * $onPage) + 1;
    }

    if (($page > 1) && ($firstRecord > $this->records)){
      $page = 1;
      $this->settings->setPage($page);
    }
    if ($page > 1) {
      $this->firstRecord = $firstRecord;
    }

    return true;
  }

  protected function _postCondition() {
    $ret = true;
    $onPage = $this->settings->getOnPage();
    if (($onPage > -1) && ($onPage < $this->count)) {
      $ret = false;
    }
    return $ret;
  }
}

class SqlDataSource extends DataSource {
  public $sqlSelect;
  protected $_db;
  protected $_dbResult = false;

  public function __construct($settings, $sqlSelect)  {
    parent::__construct($settings);
    $this->sqlSelect = $sqlSelect;
    $this->_resetDb();
  }

  public function reset($softReset = false) {
    $this->_resetDb();
    parent::reset($softReset);
  }

	private function _resetDb() { $this->_db = Application::get()->db; }

  public function mergeFilterFromGrid($sett){ $this->_mergeFilter($sett->prepareSqlSelectFilter()); }

  protected function _mergeFilter($filter){ $this->sqlSelect->settings->mergeFilter($filter); }

  protected function _resetSource($softReset = false) {
		if(!$softReset || ($this->_dbResult==false)) { $this->_dbResult = $this->_db->doQuery($this->_getQuery());}
    else { $this->_db->seekRow($this->_dbResult, $this->firstRecord - 1);}
    if ($this->_dbResult) {
      $this->records = $this->_db->getRowsNumber($this->_dbResult);
    }
    return $this->_dbResult ? true : false;
  }

  public function nextData() {
    $this->currentData = $this->_db->fetchAssoc($this->_dbResult);
    parent::nextData();
  }

  protected function _getQuery() {
    $q = $this->sqlSelect->toString();
    return $q;
  }

  public function toString(){ $this->_getQuery(); }

  protected function _preCondition() {
    parent::_preCondition();
    if ($this->firstRecord > 1) {
      $this->_db->seekRow($this->_dbResult, $this->firstRecord - 1);
    }
    return true;
  }
}

class MySqlDataSource extends SqlDataSource {
  public $sqlCountSelect;
  
  public function __construct($settings, $sqlSelect)  {
    if (!method_exists($sqlSelect, 'setLimit')) die(sprintf('MySqlSelect (%s) needed for MySqlDataSource!', get_class($sqlSelect)));

    parent::__construct($settings, $sqlSelect);

    $this->sqlCountSelect = clone $this->sqlSelect;
  }

  protected function _preCondition() {
    $this->firstRecord = 1;
    return true;  
  }

  protected function _resetSource($softReset = false) {
    if(!$softReset || ($this->_dbResult==false)) { $this->_dbResult = $this->_db->doQuery($this->_getQuery());}
    else { $this->_db->seekRow($this->_dbResult, $this->firstRecord - 1);}
    if ($this->_dbResult) {
      $tempRes = $this->_db->doQuery($this->_getCountQuery());
      $tempRow = $this->_db->fetchAssoc($tempRes);
      $this->records = $tempRow['count'];
    }
    return $this->_dbResult ? true : false;
  }
  
  protected function _getQuery() {
    $page = $this->settings->getPage();
    $onPage = $this->settings->getOnPage();
    if ($page < 1) {
      $firstRecord = 0;
    } else {
      $firstRecord = (($page - 1) * $onPage);
    }

    $this->sqlSelect->setLimit(array($firstRecord, $onPage));
    $q = $this->sqlSelect->toString();
    return $q;
  }

  protected function _getCountQuery() {
    $s = $this->sqlCountSelect;
    $columnId = array_keys($s->columns);
    $s->addColumn(new SqlColumn(false, new SqlStatementMono($s->columns[$columnId[0]], 'COUNT(%s)'), 'count'));
    $s->settings->setColumnsMask(array('count'));

    $q = $s->toString();
    return $q;
  }

  public function nextData() {
    parent::nextData();
    
    $page = $this->settings->getPage();
    $onPage = $this->settings->getOnPage();
    if (is_array($this->currentData) && $this->settings->getAddIndexes()) {
      $this->currentData[$this->indexName] += ($page-1)*$onPage;
    }
  }
}

class ArrayDataSource extends DataSource{
  protected $_data;
  protected $_preparedData;

  public function __construct($settings, $array) {
    parent::__construct($settings);
    $this->_preparedData = $array;
  }

  protected function _resetSource($softReset = false) {
    $this->_data = $this->_preparedData;
    $this->records = count($this->_data);
    return true;
  }

  public function nextData() {
    if (isset($this->_data[$this->count])) {
      $this->currentData = $this->_data[$this->count];
    } else {
      $this->currentData = null;
    }
    parent::nextData();
  }

  protected function _preCondition() {
    parent::_preCondition();
    if ($this->firstRecord > 1) {
      $this->count += $this->firstRecord - 1;
    }
    return true;
  }

  protected function _postCondition() {
    $ret = true;
    $page = $this->settings->getPage();
    $onPage = $this->settings->getOnPage();
    if (($onPage > -1) && (($page * $onPage) < $this->count)) {
      $ret = false;
    }
    return $ret;
  }

  public function getData() { return $this->_preparedData; }
}

class HashDataSource extends ArrayDataSource {

  public function __construct($settings, $hash) {
    $array = array();
    foreach ($hash as $key => $value) {
      $array[] = array($key, $value);
    }
    parent::__construct($settings, $array);
  }
}

?>
