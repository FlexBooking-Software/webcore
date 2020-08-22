<?php

class Messages {
  private $_nameSessionVar = '__messages__';
  private $_messages;

  public function __construct() {
    $app =& Application::get();
    $this->_messages =& $app->session->getPtr($this->_nameSessionVar);
    if (!is_array($this->_messages)) {
      $this->_messages = array();
    }
  }

  public function addMessage($type, $message, $level=0) {
    $this->_messages[] = array(
        'type' => $type,
        'message' => $message,
        'level' => $level );
  }

  public function getMessages($types=false, $level=0) {
    if (!$types) {
      $ret = $this->_messages;
    } else {
      $ret = array();
      if (!is_array($types)) { $types = array($types); }
      foreach ($this->_messages as $one) {
        if (in_array($one['type'], $types) && ($one['level'] >= 0)) { $ret[] = $one; }
      }
    }
    return $ret;
  }

  public function reset() { $this->_messages = array(); }
}

?>
