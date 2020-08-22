<?php

class GuiElement {
  public $parent = null;    
  protected $_app = null;
  protected $_name = null;
  private $_children = array('children' => array());   
  private $_template = '{children}';      
  private $_templateVars = array();  
  private $_isRendered = false;   
  private $_hasDefaultTemplate = true;

  public function __construct($params=array()) {
    $this->_app = Application::get();

    if (isset($params['name'])) $this->_name = $params['name'];

    if (isset($params['templateFile'])) {
      $this->setTemplateFile($params['templateFile']);
    }
    if (isset($params['template'])) {
      $this->setTemplateString($params['template']);
    }

    if (isset($params['vars'])) {
      foreach ($params['vars'] as $key => $value) {
        $this->insertTemplateVar($key, $value);
      }
    }

    $this->_userParamsInit($params);
  }

  protected function _userParamsInit(&$params) {  }

  public function clear() {
    $this->_isRendered = false;
    if (is_object($this->parent)) { 
      $this->parent->clear();
    }
  }

  public function getName() { return $this->_name; }

  public function hasDefaultTemplate() { return $this->_hasDefaultTemplate; }

  public function getTemplate() { return $this->_template; }

  public function setTemplateFile($file) {
    $template = file_get_contents($file);
    $this->setTemplateString($template);
  }

  public function setTemplateString($template) {
    $this->clear();
    $this->_hasDefaultTemplate = false;
    $this->_template = $template;
  }

  public function insert($object, $varName='children', $replace=false) {
    if (!$object) error_log(__FILE__.' Insertion of empty object to GuiElement'.$this->getTemplate());
    $object->parent = $this;
    if (!isset($this->_children[$varName])) {
      $this->_children[$varName] = array();
    }
    if ($replace && ($varName != 'children')) {
      $this->_children[$varName] = array();
    }
    $this->_children[$varName][] = $object;
    $this->clear();
  }

  public function insertTemplateVar($varName, $varValue, $htmlize=true) {
    $this->clear();
    if (!isset($this->_templateVars[$varName])) { 
      $this->_templateVars[$varName] = ''; 
    }
    if (is_array($varValue)){
      $this->_templateVars[$varName] = ''; 
    } else {
      $this->_templateVars[$varName] .= $htmlize ? Application::get()->htmlspecialchars($varValue) : $varValue;
    }
  }

  public function clearTemplateVar($varName) {
    $this->clear();

    $this->_templateVars[$varName] = '';
  }

  protected function _parse() {
    if (!$this->_app) {
      error_log(sprintf('Invalid inheritor of GuiElement! Bad contructor in class %s.', get_class($this)));
      return;
    }

    $temp = $this->_template;
    $out = "";
    $autoReplace = $this->_app->textStorage->getAutoPrefix();
    $arLen = strlen($autoReplace);

    while ($end = strpos($temp, '}')) {
      $begin = strrpos(substr($temp, 0, $end), '{');
      
      if ($begin === false) {
        $out .= substr($temp, 0, $end + 1);
      } else {
        $klic = substr($temp, $begin + 1, $end - $begin - 1);

        if (isset($this->_templateVars[$klic])) {
          $out .= substr($temp, 0, $begin);
          $out .= $this->_templateVars[$klic];
        } elseif ($autoReplace && substr($klic, 0, $arLen) == $autoReplace) {
          $out .= substr($temp, 0, $begin);
          $out .= $this->_app->textStorage->getText(substr($klic, $arLen));
        } elseif (isset($klic[0]) && ($klic[0] == '%')) {
          $out .= substr($temp, 0, $begin);
          $out .= $this->_getSystemVar($klic);
        } else {
          $out .= substr($temp, 0, $end + 1);
        }
      }

      $temp = substr($temp, $end+1);
    }
    
    $this->_template = $out . $temp;
  }

  protected function _getSystemVar($key) {
    switch ($key) {
      case '%url%': $ret = $this->_app->getUrl(); break;
      case '%basefile%': $ret = $this->_app->getBaseName(); break;
      case '%basedir%' : $ret = $this->_app->getBaseDir(); break;
      case '%action%': $ret = $this->_app->getAction(); break;
      case '%lang%': $ret = $this->_app->language->getLanguage(); break;
      case '%session%': $ret = $this->_app->session->getURL(); break;
      case '%sessid%': $ret = $this->_app->session->getId(); break;
      case '%sessname%': $ret = $this->_app->session->getName(); break;
      case '%sessionUrl%': $ret = $this->_app->session->getTagForUrl(); break;
      case '%sessionInput%': $ret = $this->_app->session->getTagForForm(); break;
      default: $ret = $key;
    }

    return $ret;
  }

  public function _renderChildren() {
    reset($this->_children);
    foreach ($this->_children as $key=>$tval) {
      $val = & $this->_children[$key];
      $buffer = "";
      reset($val);
      foreach ($val as $k=>$v) {
        $child = & $val[$k];
        $buffer .= $child->render();
      }
      $this->insertTemplateVar($key, $buffer, false);
    }
    return $buffer;
  }

  public function render() {
    //if ($this->_app->timer->getLogRender()) { $this->_app->timer->start('GUI render'); }
    if (!$this->_isRendered) {
      $this->_userRender();
      $this->_renderChildren();
      $this->_parse();
      $this->_isRendered = true;
    }
    //if ($this->_app->timer->getLogRender()) { $this->_app->timer->stop('GUI render'); }
    return $this->_template;
  }

  public function __toString() { return $this->render(); }

  protected function _userRender() { }
}

?>
