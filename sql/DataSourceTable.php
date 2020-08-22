<?php

class SqlDataSourceTable {
  protected $_db;
  protected $_name;
  protected $_dataSource;
  protected $_temporary = true;
  protected $_primaryKeyName = 'pk_id';
  protected $_indexes = array();
  protected $_integers = array();
  protected $_dates = array();
  protected $_tableType = 'MyISAM';
  protected $_example;
  protected $_dropTable = true;

  public function __construct($name, DataSource $dataSource, $temporary=null) {
    $this->_db = Application::get()->db;
    $this->_name = $name;
    $this->_dataSource = $dataSource;
    if (isset($temporary)) { $this->_temporary = $temporary; }
  }

  public function setIndexes($indexes) {
    $this->_indexes = $indexes;
  }

  public function setIntegers($integers) {
    $this->_integers = $integers;
  }

  public function setDates($dates) {
    $this->_dates = $dates;
  }

  public function setTableType($type) {
    $this->_tableType = $type;
  }

  public function setExample($example) {
    $this->_example = $example;
  }

  public function setDropTable($dropTable) {
    $this->_dropTable = $dropTable;
  }

  public function createTable() {
    $create = true;
    $fields = array();

    if (isset($this->_example)) {
      $this->_createTable($this->_example);
      $this->_createIndexes();
      $create = false;
    } 

    $this->_dataSource->reset();
    while ($row = $this->_dataSource->currentData) {

      if ($create) {
        $this->_createTable($row);
        $this->_createIndexes();
        $create = false;
      }

      $fields[] = $row;

      $this->_dataSource->nextData();
    }

    if (count($fields)) {
      $this->_db->insertArray($fields, $this->_name);
    }

  }

  protected function _createTable($data) {
    if ($this->_dropTable) {
      $q = sprintf('drop %s table if exists %s', 
          $this->_temporary ? ' temporary' : '',
          $this->_db->escapeString($this->_name));
      $this->_db->doQuery($q);
    }

    $qColumns = '';
    foreach ($data as $column => $value) {
      $qColumns .= sprintf(", %s %s",
          $this->_db->escapeString($column),
          $this->_getColumnType($column));
    }
    $q = sprintf('create %s table if not exists %s (%s bigint not null auto_increment%s, primary key (%s)) engine=%s',
        $this->_temporary ? ' temporary' : '',
        $this->_db->escapeString($this->_name),
        $this->_db->escapeString($this->_primaryKeyName),
        $qColumns,
        $this->_db->escapeString($this->_primaryKeyName),
        $this->_tableType);
    $this->_db->doQuery($q);
  }

  protected function _createIndexes() {

    foreach ($this->_indexes as $i => $index) {
      if (!is_array($index)) { $index = array($index); }

      $qColumns = '';
      foreach ($index as $column) {
        $qColumns .= ($qColumns ? ', ' : '') . $this->_db->escapeString($column);
      }
      $q = sprintf('create index index_%d on %s (%s)',
        $i,
        $this->_db->escapeString($this->_name),
        $qColumns);
      $this->_db->doQuery($q);
    }

  }

  private function _getColumnType($columnName) {
    if (in_array($columnName, $this->_integers)) {
      $ret = 'bigint';
    } elseif (in_array($columnName, $this->_dates)) {
      $ret = 'date';
    } else {
      $ret = 'varchar(255)';
    }
    return $ret;
  }

}

?>
