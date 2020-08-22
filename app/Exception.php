<?php

class ExceptionUser extends Exception {

  public function printMessage() {
    $ret = $this->getMessage();
    return $ret;
  }
}

class ExceptionUserTextStorage extends ExceptionUser {
  
  public function printMessage() {
    $app = Application::get();
    $ret = $app->textStorage->getText($this->getMessage());
    return $ret;
  }

  public function getKey() { return $this->getMessage(); }
}

class ExceptionUserGui extends Exception {

  public function __construct($key, $params, $replacement=null) { 
    parent::__construct($this->_pack($key, $params, $replacement));
  }

  public function getParams() {
    $params = self::_unpack($this->getMessage());
    return $params['params'];
  }

  public function getKey() {
    $params = self::_unpack($this->getMessage());
    return $params['key'];
  }

  public function getReplacement() {
    $params = self::_unpack($this->getMessage());
    return $params['replacement'];
  }

  static public function getException($full) {
    $params = self::_unpack($full);
    $e = new ExceptionUserGui($params['key'], $params['params'], $params['replacement']);
    return $e;
  }

  public function printMessage() {
    $gui = new GuiElement(array(
          'template' => $this->_getGuiTemplate(),
          'vars' => $this->getParams()));
    $ret = $gui->render();
    return $ret;
  }

  protected function _getGuiTemplate() {
    $app = Application::get();
    $key = $this->getKey();
    if ($app->textStorage->isKey($key)) {
      $t = $app->textStorage->getText($key);
    } else {
      $t = $this->getReplacement();
      if (!$t) { $t = $key; }
    }
    return $t;
  }

  protected function _pack($key, $params, $replacement) {
    return serialize(array($key, $params, $replacement));
  }

  static protected function _unpack($full) {
    list ($key, $params, $replacement) = unserialize($full);
    $ret = array(
        'key' => $key,
        'params' => $params,
        'replacement' => $replacement);
    return $ret;
  }
}

?>
