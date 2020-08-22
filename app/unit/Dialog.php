<?php

class Dialog {
  private $_nameSessionVar = '__dialog__';
  private $_dialog;

  public function __construct() {
    $app =& Application::get();
    $this->_dialog =& $app->session->getPtr($this->_nameSessionVar);
  }

  public function set($dialog) {
    $this->_dialog = $dialog;
    
    // default hodnoty
    if (!isset($this->_dialog['form'])) $this->_dialog['form'] = 'form';
    if (!isset($this->_dialog['body'])) $this->_dialog['body'] = 'body';
  }

  public function get() {
    return $this->_dialog;
  }

  public function reset() { $this->_dialog = null; }
}

?>
