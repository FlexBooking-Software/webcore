<?php

class TextStorage {
  protected $_language;
  protected $_resources;
  protected $_autoPrefix = '__';
  protected $_params = array();

  public function __construct($params = array()) {
    if (isset($params['autoPrefix'])) { $this->auto_prefix = $params['autoPrefix']; }
    
    $this->_params = $params;
    $this->_setLanguage($params);
    $this->_loadResources();
  }

  public function getResource($lang, $search=null) {
    $ret = array();

    $app = Application::get();

    if ($search&&!is_array($search)) $search = array($search);

    $sources = ifsetor($this->_params[$lang],'');
    if (!is_array($sources)) { $sources = array($sources); }
    foreach ($sources as $source) {
      $file = @fopen($source, 'r');
      if (!$file) $app->messages->addMessage('error', 'Resources ('. $source .') not defined for selected language ('. $this->_language .')!');
      else {
        while (!feof($file)) {
          $line = fgets($file, 4096);
          if ($parsed = $this->parseTSLine($line)) {
            $ret = array_merge($ret, $parsed);
          }
        }
        fclose($file);
      }
    }

    if ($search) {
      foreach ($ret as $key=>$value) {
        $found = false;
        foreach ($search as $s) {
          if (strpos($key,$s)!==false) {
            $found = true;
            break;
          }
        }
        if (!$found) unset($ret[$key]);
      }
    }

    return $ret;
  }
  
  protected function _loadResources(){
    $this->_resources = $this->getResource($this->_language);
  }

  public function addResource($content) {
    $lines = explode("\n",$content);
    foreach ($lines as $line) {
      if ($parsed = $this->parseTSLine($line)) {
        $this->_resources = array_merge($this->_resources, $parsed);
      }
    }
  }
  
  protected function _setLanguage($params){
    if (isset($params['language'])) {
      $this->_language = $params['language'];
    }
    else {
      $this->_language = Application::get()->language->getLanguage();
    }
  }
  
  public function refresh($params=array()){
    $this->_setLanguage($params);
    $this->_loadResources();
  }
  
  public function parseTSLine($line) {
    $app = Application::get();
    $ret = array();

    $line = chop($line);
    if ((substr($line, 0, 2) == '//')||(empty($line))) { return false; }
    if (preg_match('/^([^ \t]+)[ \t]+([^ \t].*)$/'.(($app->getCharset() == 'utf-8') ? 'u' : ''), $line, $match)) {
      $ret[$match[1]] = trim($match[2]);
    } else {
      preg_match('/^([^ \t]+)[ \t]*/'.(($app->getCharset() == 'utf-8') ? 'u' : ''), $line, $match);
      $ret[$match[1]] = '';
    }

    return $ret;
  }
    
  public function getText($key) {
    if (isset($this->_resources[$key])) {
      return $this->_resources[$key];
    } else {
      return "Value not assigned ($key)!";
    }
  }

  public function setText($params) {
    $key = $params['key'];
    $value = $params['value'];
    $this->_resources[$key] = $value;
  }

  public function isKey($key) {
    return isset($this->_resources[$key]);
  }

  public function getTextArray($keys) {
    $ret = array();

    if (is_array($keys)) {
      foreach ($keys as $v) {
        $ret[] = $v ? $this->getText($v) : '';
      }
    }

    return ($ret);
  }

  public function getTextFamily($search) {
    $ret = array();

    foreach ($this->_resources as $key => $value) {
      if (strpos($key, $search) === 0) $ret[$key] = $value;
    }

    return $ret;
  }

  public function getAutoPrefix() { return $this->_autoPrefix; }
}

?>
