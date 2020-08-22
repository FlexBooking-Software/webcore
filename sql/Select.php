<?php

class SqlSelectSettings {
  protected $_order;
  protected $_filter = array();
  protected $_filterJoins = array();
  protected $_columnsMask = false;
  protected $_selectParams = array();

  public function __construct(&$params=null) {
    if (isset($params)) {
      if (isset($params['order'])) {
        $this->_order =& $params['order'];
      }
      if (isset($params['filter'])) {
        $this->_filter =& $params['filter'];
      }
      if (isset($params['filterJoins'])) {
        $this->_filterJoins =& $params['filterJoins'];
      }
      if (isset($params['columnsMask'])) {
        $this->_columnsMask =& $params['columnsMask'];
      }
      if (isset($params['selectParams'])) {
        $this->_selectParams =& $params['selectParams'];
      }
    }
  }

  public function &getOrder() {
    return $this->_order;
  }

  public function &getFilter() {
    return $this->_filter;
  }

  public function &getFilterJoins() {
    return $this->_filterJoins;
  }

  public function &getColumnsMask() {
    return $this->_columnsMask;
  }

  public function setColumnsMask($columnsMask) {
    $this->_columnsMask = $columnsMask;
  }

  public function setOrder($order) {
    $this->_order = $order;
  }
  
  public function &getSelectParams(){
    return $this->_selectParams;
  }
  
  public function addColumn($col){
    if (!is_array($this->_columnsMask))$this->_columnsMask = array();
    if (!in_array($col, $this->_columnsMask)){
      $this->_columnsMask[] = $col;
    }
  }
  
  public function mergeFilter($val){
    if (!is_array($val))return ;
    $fil = &$this->_filter;
    if (!is_array($fil))$fil = array();
    $this->_filter = array_merge($fil, $val);
  } 

  public function mergeFilterJoins($val){
    if (!is_array($val))return ;
    $fil = &$this->_filterJoins;
    if (!is_array($fil))$fil = array();
    $this->_filterJoins = array_merge($fil, $val);
  } 
}

class SqlSelect {
  public $settings;
  public $columnsMask = false;
  public $columns = array();
  public $joins = array();
  public $statements = array();
  public $foreignKeys = array();
  public $outerColumns = array();
  public $groupStatements = array();
  public $havingStatements = array();
  public $orderStatements = array();
  public $distinct = false;
  public $_withRollup = false;
  protected $_fromTables = array();
  protected $_joinTables = array();
  protected $_renderedSql = null;

  public function __construct($settings=null) {
    $this->settings = $settings;
    $this->_initSqlSelect();
  }

  protected function _initSqlSelect() {
  }

  public function toString($force=false) {
    if ($force||!isset($this->_renderedSql)) {
      $this->_renderSql();
    }
    return $this->_renderedSql;
  }

  protected function _renderSql() {
    $this->_initColumnsMask(); 
    $this->_initOrder();
    $this->_initFilters();
    $this->_initTables(); 
    $this->_applyForeignKeys();
    $where = $this->_getSelectWhere();
    $from = $this->_getSelectTables();
    $having = $this->_getSelectHaving();
    $order = $this->_getSelectOrder();
    $group = $this->_getSelectGroup();
    $this->_renderedSql = 
      'SELECT'. $this->_getSelectDistinct() .' '. 
      $this->_getSelectColumns() .
      ($from ? ' FROM '. $from : '') .
      $this->_getSelectJoins() .
      ($where ? ' WHERE '. $where : '') .
      ($group ? ' GROUP BY '. $group : '') .
      ($having ? ' HAVING '. $having : '') .
      ($order ? ' ORDER BY '. $order : '');

  }

  protected function _initColumnsMask() {
    if ($this->settings instanceof SqlSelectSettings) {
      if (is_array($this->settings->getColumnsMask())) {
        $this->columnsMask =& $this->settings->getColumnsMask();
      }
    }
            
    if (!is_array($this->columnsMask)) {
      $this->columnsMask = array_keys($this->columns);
    }
  }

  protected function _initTables() {
    $required = array();
    $statements = array();
    foreach ($this->columnsMask as $columnId) {	
      $statements[]= $this->columns[$columnId];
    }
    $statements = array_merge($statements, $this->statements, $this->groupStatements, $this->havingStatements, $this->orderStatements);

    foreach ($statements as $col) $this->_testColumn($required, $col);

    $joinTables = array();
    foreach ($this->joins as $join) {
      $joinTables[] = $join->table->toString();
    }
    foreach ($required as $tableId => $table) {
      if (in_array($tableId, $joinTables)) {
        $this->_joinTables[$tableId] = $table;
      } else {
        $this->_fromTables[$tableId] = $table;
      }
    }
  }

  protected function _initOrder() {
    if ($this->settings instanceof SqlSelectSettings) {
      $order = $this->settings->getOrder();
      if (isset($order)) {
        foreach ($order as $one) {
          if ($one['direction'] != 'desc') {
            $statement = new SqlStatementAsc($this->columns[$one['source']]);
          } else {
            $statement = new SqlStatementDesc($this->columns[$one['source']]);
          }
          $this->addOrder($statement);
          unset ($statement);
        }
      }
    }
  }

  public function getNeededColumns(){
    if (is_object($this->settings)){
      $neededColumns = $this->settings->getColumnsMask();
      if (!is_array($neededColumns)){$neededColumns = array();}
      foreach ($this->settings->getFilter() as $oneFilter){
        foreach (anyToArray($oneFilter['source']) as $oneSource){
          $neededColumns[] = $oneSource;
        }
      }
      foreach ($this->settings->getFilterJoins() as $tableFilter)
        foreach ($tableFilter as $oneFilter){
          foreach (anyToArray($oneFilter['source']) as $oneSource){
            $neededColumns[] = $oneSource;
          }
        }
    } else throw new Exception('settings->columnsMask neni objekt');

    array_unique($neededColumns);
    return $neededColumns;
  }

  protected function _createStatementFromSettings($settings) {
    switch ($settings['type']) {
      case 'quad':
        $statement = new SqlStatementQuad(
            $this->columns[$settings['source'][0]],
            $this->columns[$settings['source'][1]],
            $this->columns[$settings['source'][2]],
            $this->columns[$settings['source'][3]],
            $settings['function']);
        break;
      case 'tri':
        $statement = new SqlStatementTri(
            $this->columns[$settings['source'][0]],
            $this->columns[$settings['source'][1]],
            $this->columns[$settings['source'][2]],
            $settings['function']);
        break;
      case 'bi':
        $statement = new SqlStatementBi(
            $this->columns[$settings['source'][0]],
            $this->columns[$settings['source'][1]],
            $settings['function']);
        break;
      case 'mono':
      default:
        $statement = new SqlStatementMono(
            $this->columns[$settings['source']],
            $settings['function']);
        break;
    }
    return $statement;
  }

  protected function _initFilters($filter = null, $filterJoins = null) {
    if ($this->settings instanceof SqlSelectSettings) {
      if(is_null($filter)) $filter = $this->settings->getFilter();
      if (isset($filter)) {
        foreach ($filter as $settings) {
          $statement = $this->_createStatementFromSettings($settings);
          if ($statement->getNeedGroup()) {
            $this->addHaving($statement);
          } else {
            $this->addStatement($statement);
          }
          unset ($statement);
        }
      }
      if(is_null($filterJoins)) $filterJoins = $this->settings->getFilterJoins();
      if (isset($filterJoins)) {
        foreach ($filterJoins as $tableId => $settingsArray) {
          foreach ($settingsArray as $settings) {
            $statement = $this->_createStatementFromSettings($settings);
            $this->joins[$tableId]->addStatement($statement);
            unset($statement);
          }
        }
      }
    }
  }

  protected function _testColumn(&$required, $column) {
    if (($column instanceof SqlColumn) && $column->table ) { // je to SqlColumn s tabulkou?
      if ($column->isOuter) { // je z venkovniho selectu
        $this->outerColumns[] = $column;
      } else {
        $tableId = $column->table->toString();
        if (!isset($required[$tableId]) ) { // je to nepridana tabulka
          $required[$tableId] = $column->table;

          foreach ($this->joins as $join) { // najit pripadny join
            if ($join->table->toString() != $tableId) { continue; }
              foreach($join->statements as $cols)$this->_testColumn($required, $cols);
            break;
          }
        }
      }
    } 
    if ($column instanceof SqlStatement){
      $column->replaceAliases($this);
      foreach ($column->getStatements() as $stats){
        $this->_testColumn($required, $stats);
      }      
    }

    if (is_array($column)){
      foreach ($column as $col){
        $this->_testColumn($required, $col);
      }
    }
    if (isset($column->column)&&(($column->column instanceof SqlStatementMono)||($column->column instanceof SqlColumn))){$this->_testColumn($required, $column->column);}

  }

  protected function _getSelectColumns() {
    $ret = '';
    foreach ($this->columnsMask as $columnId) {
      $column =& $this->columns[$columnId];
      $ret .= ($ret ? ', ' : '') . $column->toString('wAlias');
      unset ($column);
    }
    return $ret;
  }

  protected function _getSelectTables() {
    $ret = '';
    foreach ($this->_fromTables as $table) {
      $ret .= ($ret ? ', ' : '') . $table->toString(true);
    }
    return $ret;
  }

  protected function _getSelectWhere() {
    $ret = '';
    $usedStatements = array();
    foreach ($this->statements as $statement) {
      $sql = anyToString($statement);
      if (in_array($sql, $usedStatements)) { continue; }
      $ret .= ($ret ? ' AND ' : '') . $sql;
      $usedStatements[] = $sql;
    }
    return $ret;
  }

  protected function _getSelectJoins() {
    $ret = '';
    foreach ($this->joins as $join) {
      if (!isset($this->_joinTables[$join->table->toString()])) { continue; }
      $ret .= $join->type ? ' '. strtoupper($join->type) : '';
      $ret .= ' JOIN '. $join->table->toString(true);
      $sql = '';
      foreach ($join->statements as $statement) {
        $sql .= ($sql ? ' AND' : ' ON') .' '. $statement->toString();
      }
      $ret .= $sql;
    }

    return $ret;
  }

  protected function _getSelectGroup() {
    $needed = false;
    $ret = '';
    foreach ($this->groupStatements as $columnId) {
	if(!$columnId instanceof SqlColumn) $columnId = $this->columns[$columnId];
        $ret .= ($ret ? ', ' : '') . $columnId->toString('woAlias');
    }
    foreach ($this->columnsMask as $columnId) {
      $column =& $this->columns[$columnId];
      if ($column->needGroup) {
        $needed = true;
      } else {
        $ret .= ($ret ? ', ' : '') . $column->toString('oAlias');
      }
      unset ($column);
    }
    if ($ret && $this->_withRollup) { $ret .= ' WITH ROLLUP'; }
    return $needed ? $ret : '';
  }

  protected function _getSelectHaving() {
    $ret = '';
    foreach ($this->havingStatements as $statement) {
      $ret .= ($ret ? ' AND ' : '') . $statement->toString();
    }
    return $ret;
  }

  protected function _getSelectOrder() {
    $ret = '';
    foreach ($this->orderStatements as $statement) {
      $orderColumns = $statement->getStatements();
      if (is_subclass_of($this, 'SqlUnion')) {
        $getAlias = true;
      } else {
        $getAlias = $this->_testOrder($statement);//priposteji aliasy
        foreach ($orderColumns as $orderColumn) {
          if ($orderColumn instanceof SqlColumn){
            $alias = $orderColumn->columnAlias;
            if ($alias && (!in_array($alias, $this->columnsMask))) {
              $getAlias = false; break;
            }// Pokud neni v masce, nesmi pouzit alias sloupce
          }
        }
      }
      $ret .= ($ret ? ', ' : '') . $statement->toString($getAlias ? 'oAlias' : 'woAlias');
    }
    return $ret;
  }

  protected function _testOrder($statement){
    $O = Array(',' => '',
               'asc' => '',
               'desc' => '',
               '%s' => '');
    $fce = isset($statement->function) ? $statement->function : '';
    $fce = trim(strtr(strtolower($fce), $O));
    return empty($fce);
  }

  protected function _getSelectDistinct() {
    return $this->distinct ? ' DISTINCT' : '';
  }

  protected function _applyForeignKeys() {
    $fromTables = array();
    foreach ($this->_fromTables as $table) {
      $fromTables[] = $table->toString();
    }

    foreach ($this->foreignKeys as $key) {
      foreach ($key->tables as $table) {
        if (!in_array($table->toString(), $fromTables)) { continue 2; }
      }
      foreach ($key->statements as $statement) {
        $this->addStatement($statement);
      }
    }
  }

  public function setColumnsMask($columns = array()) {
    $realColumns = array();
    foreach ($columns as $columnId) {
      if (in_array($columnId, array_keys($this->columns))) {
        $realColumns[] = $columnId;
      }
    }
    $this->columnsMask = $realColumns;
  }

  public function addToColumnsMask($columns = array()) {
    if (!is_array($columns)) $columns = array($columns);
    $realColumns = array();
    foreach ($columns as $columnId) {
      if (in_array($columnId, array_keys($this->columns))) {
        $realColumns[] = $columnId;
      }
    }

    if (!$this->columnsMask) $this->columnsMask = array();

    $this->columnsMask = array_merge($this->columnsMask, $realColumns);
  }


  public function addColumn($columns) {
    $columns =& anyToArray($columns);
    foreach ($columns as $index => $column) {
      $columnId = $column->getId();
      if (!is_string($columnId)) { die ('Musi mit identifikator'); }
      $this->columns[$columnId] = $columns[$index];
    }
  }

  public function addStatement($statement) {
    $this->statements[] = $statement;
  }

  public function addForeignKey($foreignKeys) {
    $foreignKeys =& anyToArray($foreignKeys);
    foreach ($foreignKeys as $index => $foreignKey) {
      $this->foreignKeys[] = $foreignKeys[$index];
    }
  }

  public function addJoin($joins) {
    $joins =& anyToArray($joins);
    foreach ($joins as $index => $join) {
      $tableId = $join->table->toString();
      $this->joins[$tableId] = $joins[$index];
    }
  }

  public function setHaving($having) {
    $this->havingStatements =& anyToArray($having);
  }

  public function addHaving($having) {
    $this->havingStatements[] = $having;
  }

  public function setOrder($order) {
    $this->orderStatements =& anyToArray($order);
  }

  public function addOrder($order) {
    $this->orderStatements[] = $order;
  }

  public function addGroup($group) {
    $this->groupStatements[] = $group;
  }

  public function setDistinct($distinct) {
    $this->distinct = $distinct ? true : false;
  }

  public function setWithRollup($withRollup) {
    $this->_withRollup = $withRollup;
  }
  
  public function aliasToColumn($column){
    if (!is_string($column)) return $column;
    $table='';
    if (strpos($column, ".")){
      list($table, $col) = explode(".", $column);
    }
    foreach ($this->columns as $val){
      if ($val->getId() == $column) return $val;
      if ($table && (anyToString($val->table) == $table) && !is_object($val->column) && ($val->column == $col)) return $val;
    }
    return $column;
  }
}

class SqlTable {
  public $database;
  public $table;
  public $tableAlias = false;

  public function __construct($table, $tableAlias=false, $database=null) {
    $this->database = $database;
    $this->table = $table;
    $this->tableAlias = $tableAlias;
  }

  public function getTableName() { return (isset($this->database) ? $this->database .'.' : '') . $this->table; }
  public function getTableAlias() { return $this->tableAlias; }

  public function toString($withAlias=false) {
    $ret = $this->tableAlias ? $this->getTableAlias() : $this->getTableName();
    if ($withAlias && $this->tableAlias) {
      $ret = (is_object($this->table) ? '('. $this->table->toString() .')' : $this->getTableName()) .' AS '. $this->getTableAlias();
    }
    return $ret;
  }
}

class SqlColumn {
  public $table;
  public $column;
  public $columnAlias = false;
  public $needGroup = false;
  public $isConstant = false;
  public $isOuter = false;

  public function __construct($table, $column, $columnAlias=false, $needGroup=false, $isConstant=false, $isOuter=false) {
    $this->table = $table;
    $this->column = $column;
    $this->columnAlias = $columnAlias;
    $this->needGroup = $needGroup;
    $this->isConstant = $isConstant;
    $this->isOuter = $isOuter;
  }

  public function getId() { return $this->columnAlias ? $this->columnAlias : $this->column; }
  public function getColumnName() { return is_object($this->column) ? $this->columns->getColumnName() : $this->column; }
  public function getColumnAlias() { return $this->columnAlias; }

  public function toString($mode='woAlias') {
    $table = anyToString($this->table);
    if ($mode == 'oAlias') {
      if ($this->columnAlias) {
        return $this->getColumnAlias();
      }
    }
    if (is_object($this->column)) {
      $ret = '('. $this->column->toString() .')';
    } else {
      $name = $this->getColumnName();
      $ret = $table .  ($table ? '.' : '') . ($this->isConstant ? "'".$name."'" : $name);
    }
    if (($mode == 'wAlias') && $this->columnAlias) {
      $ret .= ' AS '. $this->getColumnAlias();
    }
    return $ret;
  }
}

class SqlJoin {
  public $type;
  public $table;
  public $statements = array();

  public function __construct($type, $table, $statements) {
    $this->type = $type;
    $this->table = $table;
    $this->statements =& anyToArray($statements);
  }

  public function addStatement($statement) {
    $this->statements[] = $statement;
  }
}

class SqlForeignKey {
  public $tables = array();
  public $statements = array();

  public function __construct($tables, $statements) {
    $this->tables = $tables;
    $this->statements =& anyToArray($statements);
  }

}

class SqlStatement {
  public $statement;
  public $function = false;
  
  public function __construct($statement) {
    $this->statement = $statement;
  }

  public function toString($mode=false) {
    return $this->statement;
  }

  public function & _prepareValue($value) {
    $app = Application::get();
    if (!is_object($value)) {
      $value = $app->db->escapeString($value);
    }
    return $value;
  }

  public function getStatements(){
    return array($this->statement);
  }
  
  public function replaceAliases($sqlSelect){
  }

  public function getNeedGroup() {
    return $this->statement instanceof SqlColumn ? $this->statement->needGroup : false;
  }
}

class SqlStatementMono extends SqlStatement {
  public $mono1;
  public $function = false;
  public $toStringParam = 'woAlias';

  public function __construct($mono1, $function=false, $toStringParam=null) {
    $this->mono1 = $mono1;
    $this->function = $function;
    if (isset($toStringParam)) {
      $this->toStringParam = $toStringParam;
    }
  }

  public function toString($mode=false) {
    if ($this->function) {
      $ret = sprintf($this->function, 
          anyToString($this->_prepareValue($this->mono1), false, $mode ? $mode : $this->toStringParam));
    } else {
      $ret = anyToString($this->mono1, !is_object($this->mono1), $mode ? $mode : $this->toStringParam);
    }
    return $ret;
  }

  public function getStatements(){
    return !is_array($this->mono1) ? array($this->mono1) : $this->mono1;
  }

  public function replaceAliases($sqlSelect){
    $this->mono1 = $sqlSelect->aliasToColumn($this->mono1);
  }

  public function getNeedGroup() {
    return $this->mono1 instanceof SqlColumn ? $this->mono1->needGroup : false;
  }
}

class SqlStatementBi extends SqlStatementMono {
  public $mono2;
  public function __construct($mono1, $mono2, $function, $toStringParam=null) {
    parent::__construct($mono1, $function, $toStringParam);
    $this->mono2 = $mono2;
  }

  public function toString($mode=false) {
    $ret = sprintf($this->function, 
        anyToString($this->_prepareValue($this->mono1), !is_object($this->mono1), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono2), !is_object($this->mono2), $mode ? $mode : $this->toStringParam));
    return $ret;
  }
  
  public function getStatements(){
    return array($this->mono1, $this->mono2);
  }

  public function replaceAliases($sqlSelect){
    parent::replaceAliases($sqlSelect);
    $this->mono2 = $sqlSelect->aliasToColumn($this->mono2);
  }

  public function getNeedGroup() {
    return ($this->mono2 instanceof SqlColumn ? $this->mono2->needGroup : false) || parent::getNeedGroup();
  }
}

class SqlStatementTri extends SqlStatementBi {
  public $mono3;

  public function __construct($mono1, $mono2, $mono3, $function, $toStringParam=null) {
    parent::__construct($mono1, $mono2, $function, $toStringParam);
    $this->mono3 = $mono3;
  }

  public function toString($mode=false) {
    $ret = sprintf($this->function, 
        anyToString($this->_prepareValue($this->mono1), !is_object($this->mono1), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono2), !is_object($this->mono2), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono3), !is_object($this->mono3), $mode ? $mode : $this->toStringParam));
    return $ret;
  }
  
  public function getStatements(){
    return array($this->mono1, $this->mono2, $this->mono3);
  }

  public function replaceAliases($sqlSelect){
    parent::replaceAliases($sqlSelect);
    $this->mono3 = $sqlSelect->aliasToColumn($this->mono3);
  }

  public function getNeedGroup() {
    return ($this->mono3 instanceof SqlColumn ? $this->mono3->needGroup : false) || parent::getNeedGroup();
  }
}

class SqlStatementQuad extends SqlStatementTri {
  public $mono4;

  public function __construct($mono1, $mono2, $mono3, $mono4, $function, $toStringParam=null) {
    parent::__construct($mono1, $mono2, $mono3, $function, $toStringParam);
    $this->mono4 = $mono4;
  }

  public function toString($mode=false) {
    $ret = sprintf($this->function, 
        anyToString($this->_prepareValue($this->mono1), !is_object($this->mono1), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono2), !is_object($this->mono2), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono3), !is_object($this->mono3), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono4), !is_object($this->mono4), $mode ? $mode : $this->toStringParam));
    return $ret;
  }

  public function getStatements(){
    return array($this->mono1, $this->mono2, $this->mono3, $this->mono4);
  }
  
  public function replaceAliases($sqlSelect){
    parent::replaceAliases($sqlSelect);
    $this->mono4 = $sqlSelect->aliasToColumn($this->mono4);
  }


  public function getNeedGroup() {
    return ($this->mono4 instanceof SqlColumn ? $this->mono4->needGroup : false) || parent::getNeedGroup();
  }
}

class SqlStatementPenta extends SqlStatementQuad {
  public $mono5;

  public function __construct($mono1, $mono2, $mono3, $mono4, $mono5, $function, $toStringParam=null) {
    parent::__construct($mono1, $mono2, $mono3, $mono4, $function, $toStringParam);
    $this->mono5 = $mono5;
  }

  public function toString($mode=false) {
    $ret = sprintf($this->function, 
        anyToString($this->_prepareValue($this->mono1), !is_object($this->mono1), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono2), !is_object($this->mono2), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono3), !is_object($this->mono3), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono4), !is_object($this->mono4), $mode ? $mode : $this->toStringParam),
        anyToString($this->_prepareValue($this->mono5), !is_object($this->mono5), $mode ? $mode : $this->toStringParam));
    return $ret;
  }
  
  public function getStatements(){
    return array($this->mono1, $this->mono2, $this->mono3, $this->mono4, $this->mono5);
  }
  
  public function replaceAliases($sqlSelect){
    parent::replaceAliases($sqlSelect);
    $this->mono5 = $sqlSelect->aliasToColumn($this->mono5);
  }

  public function getNeedGroup() {
    return ($this->mono5 instanceof SqlColumn ? $this->mono5->needGroup : false) || parent::getNeedGroup();
  }
}

class SqlStatementHexa extends SqlStatementPenta {
  public $mono6;

  public function __construct($mono1, $mono2, $mono3, $mono4, $mono5, $mono6, $function, $toStringParam=null) {
    parent::__construct($mono1, $mono2, $mono3, $mono4, $mono5, $function, $toStringParam);
    $this->mono6 = $mono6;
  }

  public function toString($mode=false) {
    $ret = sprintf($this->function, 
        anyToString($this->_prepareValue($this->mono1), !is_object($this->mono1), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono2), !is_object($this->mono2), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono3), !is_object($this->mono3), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono4), !is_object($this->mono4), $mode ? $mode : $this->toStringParam),
        anyToString($this->_prepareValue($this->mono5), !is_object($this->mono5), $mode ? $mode : $this->toStringParam),
        anyToString($this->_prepareValue($this->mono6), !is_object($this->mono6), $mode ? $mode : $this->toStringParam));
    return $ret;
  }

  public function getStatements(){
    return array($this->mono1, $this->mono2, $this->mono3, $this->mono4, $this->mono5, $this->mono6);
  }

  public function replaceAliases($sqlSelect){
    parent::replaceAliases($sqlSelect);
    $this->mono6 = $sqlSelect->aliasToColumn($this->mono6);
  }
  
  public function getNeedGroup() {
    return ($this->mono6 instanceof SqlColumn ? $this->mono6->needGroup : false) || parent::getNeedGroup();
  }
}

class SqlStatementHepta extends SqlStatementHexa {
  public $mono7;

  public function __construct($mono1, $mono2, $mono3, $mono4, $mono5, $mono6, $mono7, $function, $toStringParam=null) {
    parent::__construct($mono1, $mono2, $mono3, $mono4, $mono5, $mono6, $function, $toStringParam);
    $this->mono7 = $mono7;
  }

  public function toString($mode=false) {
    $ret = sprintf($this->function, 
        anyToString($this->_prepareValue($this->mono1), !is_object($this->mono1), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono2), !is_object($this->mono2), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono3), !is_object($this->mono3), $mode ? $mode : $this->toStringParam), 
        anyToString($this->_prepareValue($this->mono4), !is_object($this->mono4), $mode ? $mode : $this->toStringParam),
        anyToString($this->_prepareValue($this->mono5), !is_object($this->mono5), $mode ? $mode : $this->toStringParam),
        anyToString($this->_prepareValue($this->mono6), !is_object($this->mono6), $mode ? $mode : $this->toStringParam),
        anyToString($this->_prepareValue($this->mono7), !is_object($this->mono7), $mode ? $mode : $this->toStringParam));
    return $ret;
  }

  public function getStatements(){
    return array($this->mono1, $this->mono2, $this->mono3, $this->mono4, $this->mono5, $this->mono6, $this->mono7);
  }

  public function replaceAliases($sqlSelect){
    parent::replaceAliases($sqlSelect);
    $this->mono7 = $sqlSelect->aliasToColumn($this->mono7);
  }
  
  public function getNeedGroup() {
    return ($this->mono7 instanceof SqlColumn ? $this->mono7->needGroup : false) || parent::getNeedGroup();
  }
}

class SqlStatementAsc extends SqlStatementMono {
  public $toStringParam = 'oAlias';
  public $function = '%s asc';
  
  public function __construct($mono1) {
    parent::__construct($mono1, $this->function);
  }

}

class SqlStatementDesc extends SqlStatementMono {
  public $toStringParam = 'oAlias';
  public $function = '%s desc';
  
  public function __construct($mono1) {
    parent::__construct($mono1, $this->function);
  }
}

class SqlStatementIn extends SqlStatementBi {
  public $function = '%s IN (%s)';

  public function __construct($mono1, $mono2, $toStringParam=null) {
    $mono2 = (array) $mono2;
    parent::__construct($mono1, $mono2, $this->function, $toStringParam);
  }

  public function toString($mode=false) {
    $res = array();

    foreach ( $this->mono2 AS $v ) {
      $res[] = anyToString($this->_prepareValue($v), !is_object($v), $mode ? $mode : $this->toStringParam);
    }
    $str = implode( ",", $res );

    $ret = sprintf($this->function, 
        anyToString($this->_prepareValue($this->mono1), !is_object($this->mono1), $mode ? $mode : $this->toStringParam), 
        $str
    );
    return $ret;
  }
}

class SqlStatementNotIn extends SqlStatementIn {
  public $function = '%s NOT IN (%s)';
}

class MySqlSelect extends SqlSelect {
  protected $_limit = false;
  protected $_forUpdate = false;

  public function setLimit($limit) {
    $this->_limit =& anyToArray($limit);
  }

  public function setForUpdate($value) {
    $this->_forUpdate = $value;
  }

  protected function _getSelectLimit() {
    $ret = '';
    $lim = is_array($this->_limit) ? $this->_limit : array($this->_limit);
    foreach ($lim as $part) {
      if (is_int($part)){
        $ret .= ($ret ? ', ' : '') . $part;
      }
    }
    return $ret;
  }
  
  protected function _renderSql(){
    parent::_renderSql();

    if ($this->_forUpdate) $this->_renderedSql .= ' FOR UPDATE';

    $limit = $this->_getSelectLimit();
    $this->_renderedSql .= ($limit ? ' LIMIT '.$limit : '');
  }
}

class PgSqlTable extends SqlTable {
  
  public function getTableName() { return (isset($this->database) ? $this->database .'.' : '') . '"' . $this->table . '"'; }
  public function getTableAlias() { return '"' . $this->tableAlias . '"'; }
}

class PgSqlColumn extends SqlColumn {

  public function getColumnName() { return is_object($this->column) ? $this->getColumnName() : '"' . $this->column . '"'; }
  public function getColumnAlias() { return '"' . $this->columnAlias . '"'; }
}

class PgSqlSelect extends MySqlSelect {
  
}

?>
