<?php

class SqlUnion extends SqlSelect {

  protected $_selects;

  public function __construct($settings=array()) {
    unset($this->columns);
    parent::__construct($settings);
  }

  public function addSelect($select, $alias=false) {

    if (is_string($select)) {
      $class = new $select($this->settings);
    } elseif (is_object($select)) {
      $class = $select;
    }

    if (!is_subclass_of($class, 'SqlSelect')) throw new Exception('SqlUnion: invalid class');

    if ($alias) {
      $this->_selects[$alias] = $class;
    } else {
      $this->_selects[] = $class;
    }
  }

  public function addSelects($selects=array()) {
    foreach ($selects as $key => $one) {
      $this->addSelect($one, $key);
    }
  }

  public function addStatement($statement) {
    foreach ($this->_selects as $one) {
      $one->addStatement($statement);
    }
  }

  public function __get($what) {
    if ($what == 'columns') {
      return reset($this->_selects)->$what;
    } else {
      error_log(__FILE__.' SqlUnion undefined property, write it yourself in __get()');
    }
  }

  public function __set($what, $how) {
    if ($what == 'columns') {
      reset($this->_selects)->$what = $how;
    } else {
      error_log(__FILE__.' SqlUnion undefined property, write it yourself in __get()');
    }
  }

  public function toString($mode=false) {
    $this->_initOrder();
    $order = $this->_getSelectOrder();
    $tmpOrder = $this->settings->getOrder();
    $this->settings->setOrder(array());

    $res = array();
    foreach ($this->_selects as $one) {
      $res[] = ' ( '.$one->toString().' ) ';
    }

    $this->settings->setOrder($tmpOrder);

    $query = join(' UNION ', $res);
    $query .= $order ? ' ORDER BY '.$order : '';
    return $query;
  }

}

?>
