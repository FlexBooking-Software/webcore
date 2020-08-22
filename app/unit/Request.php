<?php

class Request {
  private $_slashVars = false;
  private $_sources = array();
  private $_defaultSources = array('get','post','cookie');

  public function __construct($params=array()) {
    if (isset($params['slashVars'])) { $this->_slashVars = $params['slashVars']; }

    $this->_initSources();
    $this->_initVars();
  }

  public function registerSource($name, &$source) { $this->_sources[$name]['source'] =& $source; }

  protected function _initSources() {
    $app = Application::get();
    if (function_exists('mb_convert_variables') && isset($_REQUEST['ao3InputCharset']) && (substr($_REQUEST['ao3InputCharset'],0,1)!='_')) {
      $inputCharset = $_REQUEST['ao3InputCharset'];
      $_REQUEST['ao3InputCharset'] = '_'.$inputCharset;
      if (isset($_POST['ao3InputCharset'])) { $_POST['ao3InputCharset'] = '_'.$inputCharset; }
      if (isset($_GET['ao3InputCharset'])) { $_GET['ao3InputCharset'] = '_'.$inputCharset; }
      mb_convert_variables($app->getCharset(), $inputCharset, $_GET, $_POST, $_COOKIE);
    }
    $this->registerSource('get', $_GET);
    $this->registerSource('post', $_POST);
    $this->registerSource('cookie', $_COOKIE);
  }

  protected function _initVars() {
    if (!isset($GLOBALS['__Request_initVars__'])) {
      $magicOn = get_magic_quotes_gpc();
      if ($this->getSlashVars() && !$magicOn) {
        $this->_sources['get']['source'] = $this->_slash('add', $this->_sources['get']['source']);
        $this->_sources['post']['source'] = $this->_slash('add', $this->_sources['post']['source']);
        $this->_sources['cookie']['source'] = $this->_slash('add', $this->_sources['cookie']['source']);
      } elseif (!$this->getSlashVars() && $magicOn) {
        $this->_sources['get']['source'] = $this->_slash('strip', $this->_sources['get']['source']);
        $this->_sources['post']['source'] = $this->_slash('strip', $this->_sources['post']['source']);
        $this->_sources['cookie']['source'] = $this->_slash('strip', $this->_sources['cookie']['source']);
      }
      $GLOBALS['__Request_initVars__'] = true;
    }
  }

  public function getSlashVars() { return $this->_slashVars; }

  public function getDefaultSources() { return $this->_defaultSources; }

  public function setDefaultSources($sources) {
    if (!is_array($sources)) { $sources = array($sources); }
    $this->_defaultSources = $sources;
  }

  protected function _slash($type, $var) {
    if (is_array($var)) {
      foreach ($var as $key=> $value) {
        unset ($var[$key]);
        $var[$this->_slash($type, $key)] = $this->_slash($type, $value);
      }
    } elseif ($type == 'add') {
      $var = addslashes($var);
    } else {
      $var = stripslashes($var);
    }
    return $var;
  }

  public function getParams($vars=null, $search=false) {
    if ($search === false) { $search = $this->_defaultSources; }
    if (!is_array($search)) { $search = array($search); }
    if (is_null($vars)) {
      $ret = array();
      foreach (array_reverse($search) as $one) {
        $ret = array_merge($ret, $this->_sources[$one]['source']);
      }
    } elseif (!is_array($vars)) {
      $ret = null;
      foreach ($search as $one) {
        if (isset($this->_sources[$one]['source'][$vars])) {
          $ret = $this->_sources[$one]['source'][$vars];
          break;
        }
      }
    } else {
      $ret = array();
      foreach ($vars as $var) {
        $ret[$var] = null;
        foreach ($search as $one) {
          if (isset($this->_sources[$one]['source'][$var])) {
            $ret[$var] = $this->_sources[$one]['source'][$var];
            break;
          }
        }
      }
    }
    return $ret;
  }
  
  public function deleteParam($var){
    if (!isset($var))return false;
    foreach ($this->_defaultSources as $src){
       if (isset($this->_source[$src]['source'][$var]))
           unset($this->_source[$src]['source'][$var]);
    }
  }
  
  public function isSetParam($var, $search=false) {
    if ($search === false) { $search = $this->_defaultSources; }
    if (!is_array($search)) { $search = array($search); }
    $ret = false;
    foreach ($search as $one) {
      if (isset($this->_sources[$one]['source'][$var])) { 
        $ret = true;
        break;
      }
    }
    return $ret;
  }

  public function setCookieVar($params) {
    $name	= $params['name'];
    $value	= $params['value'];

    if (is_array($value)) {
      foreach ($value as $k => $v) {
        $name = trim($name);
        if (isset($params['domain'])) {
          setcookie($name ."[$k]", $v, $params['expire'], $params['path'], $params['domain']);
        } elseif (isset($params['path'])) {
          setcookie($name ."[$k]", $v, $params['expire'], $params['path']);
        } elseif (isset($params['expire'])) {
          setcookie($name ."[$k]", $v,$params['expire']);
        } else {
          setcookie($name ."[$k]", $v);
        }
      }
    } else {
      if (isset($params['domain'])) {
        setcookie($name, $value, $params['expire'], $params['path'], $params['domain']);
      } elseif (isset($params['path'])) {
        setcookie($name, $value, $params['expire'], $params['path']);
      } elseif (isset($params['expire'])) {
        setcookie($name, $value, $params['expire']);
      } else {
        setcookie($name, $value);
      }
    }
  }

  public function getFileInfo($name) {
    $ret = null;
    if (isset($_FILES[$name])) {
      $ret = $_FILES[$name];
    }
    return $ret;
  }
}

?>
