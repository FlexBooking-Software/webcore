<?php

class Language {

  private $_nameSessionVar = '__language__';
  private $_nameUrlVar = 'lang';
  private $_language;
  private $_defaultLanguage = 'cz';
  private $_accept = array('cz');

  public function __construct($params=array()) {
    $app = Application::get();
    if (isset($params['nameSessionVar'])) { $this->_nameSessionVar = $params['nameSessionVar']; }
    if (isset($params['nameUrlVar'])) { $this->_nameUrlVar = $params['nameUrlVar']; }
    if (isset($params['defaultLanguage'])) { $this->_defaultLanguage = $params['defaultLanguage']; }
    if (is_array($params['accept'])) { $this->_accept = $params['accept']; }
    $this->_language =& $app->session->getPtr($this->_nameSessionVar);

    $this->_updateLanguage();
  }

  protected function _updateLanguage() {
    $app = Application::get();
    $language = $app->request->getParams($this->_nameUrlVar);
    if (in_array($language, $this->_accept)) {
      $this->_language = $language;
    }
    if (!$this->_language) { $this->_language = $this->_defaultLanguage; }
  }

  public function getLanguage() { return $this->_language; }
  public function getDefaultLanguage() { return $this->_defaultLanguage; }

  public function setLanguage($language) {
    $app = Application::get();
    
    if (in_array($language, $this->_accept)) {
      $this->_language = $language;
      $app->textStorage->refresh();
    } else {
      $app->messages->addMessage('error', 'Language to be set, `'.$language.'`, is not accepted!');
    }
  }

  public function getAccept() { return $this->_accept; }
}

?>
