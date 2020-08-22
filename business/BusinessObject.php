<?php

class BusinessObject {
  protected $_app;
  protected $_id;
  protected $_data;
  protected $_loaded;

  public function __construct($id=null) {
    $this->_app = Application::get();
    $this->_id = $id;

    $this->_data = null;
    $this->_loaded = false;
  }

  public function getId() { return $this->_id; }

  protected function _reset() {
    $this->_id = null;
    $this->_data = null;

    $this->_loaded = false;
  }

  public function reset() { $this->_reset(); }

  protected function _load() { }

  public function getData() {
    $this->_load();

    return $this->_data;
  }
}

?>
