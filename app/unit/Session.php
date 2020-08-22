<?php

class Session { 
  private $_std_prefix = '__session_'; 
  private $_std_name = 'sessid';        
  private $_name;                        
  private $_id;                           
  private $_ip_name;                       
  private $_ip;                             
  private $_hash_name;                       
  private $_hash;                             
  private $_exp_name;                          
  private $_exp;                                
  private $_first_id;                            
  private $_last_used;                            
  private $_last_used_name;
  private $_useCookie = false;                     
  private $_cookieValidPath = null;
  private $_savePath = false;                        
  private $_basePath = null;                          
  private $_allowChangeIp = false;
  private $_allowedIps = null;      
  private $_isNew = false;
  private $_skipCheckAge = false;
  private $_maxAge = 1200;                          
  private $_destroyExpired = true;
  private $_expired = false;
  private $_expired_name;
  private $_expired_action = null;
  private $_expired_action_name;
  private $_expired_action_params = null;
  private $_expired_action_params_name;

  public function __construct($params=array()) {
    ini_set('session.use_cookies', 0);
    $app = Application::get();

    $this->_init($params);
    $sessionId = null;
    if (isset($params['sessionId'])) {
      $sessionId = $params['sessionId'];
    }
    if (is_null($sessionId)) {
      if ($this->_useCookie) $sources[] = 'cookie';
      $sources[] = 'post';
      $sources[] = 'get';

      $sessionId = $app->request->getParams($this->getName(), $sources);
    }
    if (is_null($sessionId) || ($sessionId === '')) {
      $sessionId = $this->_newId();
    }
    session_id($sessionId);
    session_start();
    $this->_setCookie();
    if (!$this->_skipCheckAge) $this->_checkAge();
    $this->_checkIP();

    if (isset($params['useSOTP'])) {
      $data = $_SESSION;
      session_unset();
      session_destroy();
      session_id($this->_newId());
      session_start();
      $this->_setCookie();
      $_SESSION = $data;
    }

    $this->_id = session_id();
		
    if (!$this->_isRegistered( $this->_hash_name )) $this->_isNew = true;
    
    $this->_register($this->_hash_name);
    $this->_register($this->_exp_name);
    if (get_cfg_var('register_globals')) {
      $this->_hash =& $GLOBALS[$this->_hash_name];
      $this->_exp =& $GLOBALS[$this->_exp_name];
    } else {
      $this->_hash =& $_SESSION[$this->_hash_name];
      $this->_exp =& $_SESSION[$this->_exp_name];
    }
    if (!is_array($this->_hash)) $this->_hash = [];

    $this->_checkSessionGet();
  }
  
  private function _isRegistered($key) {
    return isset($_SESSION[$key]); 
  }

  private function _unregister($key) {
    unset($_SESSION[$key]);
  }

  private function _register() {
    $args = func_get_args();
    foreach ($args as $key){
      if (!$this->_isRegistered($key)) $_SESSION[$key] = '';
    } 
  }
  
	public function isNew() { return $this->_isNew; }

  protected function _checkSessionGet() {
    $app = Application::get();
    $sessionget = $app->request->getParams('_sessionget_');
    if ($sessionget) {
      $get = $this->get($sessionget);
      $app->request->registerSource('get', $get);
    }
  }

  public function getClone() {
    // Data aktualni session.
    $data = $_SESSION;
    $id = $this->getId();

    // Vytvoreni nove session.
    $clone_id = $this->_newId();
    session_id($clone_id);
    session_start();
    $_SESSION = $data;
    session_write_close();

    // Navrat na starou session.
    session_id($id);
    session_start();    

    $_SESSION = $data;

    return $clone_id; 
  }

  public function set($var_name, $var_value) {
    $v = $this->_expandVar($var_name);
    $i = $this->_expandIndex($var_name);
    if (isset($i)) {
      if (!is_array($this->_hash[$v])) $this->_hash[$v] = [];

      $this->_hash[$v][$i] = $var_value;
    } else {
      $this->_hash[$v] = $var_value;
    }
  }

  public function remove($var_name) {
    $v = $this->_expandVar($var_name);
    $i = $this->_expandIndex($var_name);
    if (isset($i)) {
      unset($this->_hash[$v][$i]);
    } else {
      unset($this->_hash[$v]);
    }
  }

  public function setPtr($var_name, & $var_value) {
    $v = $this->_expandVar($var_name);
    $i = $this->_expandIndex($var_name);
    if (isset($i)) {
      if (!is_array($this->_hash[$v])) $this->_hash[$v] = [];

      $this->_hash[$v][$i] = & $var_value;
    } else {
      $this->_hash[$v] = & $var_value;
    }

  }

  public function setFirstId($id) {
    $this->set('session_first_id',$id);
  }

  public function getFirstId() {
    return $this->get('session_first_id');
  }

  public function get($var_name) {
    $v = $this->_expandVar($var_name);
    $i = $this->_expandIndex($var_name);
    if (isset($i)) {
      $ret = isset($this->_hash[$v][$i]) ? $this->_hash[$v][$i] : false;
    } else {
      $ret = isset($this->_hash[$v]) ? $this->_hash[$v] : false;
    }
    return $ret;
  }

  public function &getPtr($varName) {
    $v = $this->_expandVar($varName);
    $i = $this->_expandIndex($varName);
    if (isset($i)) {
      if (!is_array($this->_hash[$v])) $this->_hash[$v] = [];
      if (!isset($this->_hash[$v][$i])) $this->_hash[$v][$i] = null;

      $ret = & $this->_hash[$v][$i];
    } else {
      if (!isset($this->_hash[$v])) $this->_hash[$v] = null;

      $ret = & $this->_hash[$v];
    }
    return $ret;
  }

  public function setExp($var_name, $exp_time = 0) {
    $this->_exp[$var_name] = array("time" => time(), "exp" => $exp_time);
  }

  public function unsetExp($var_name) {
    $this->setExp($var_name);
  }

  public function isExp($var_name) {
    $last = $this->_exp[$var_name]["time"];
    $exp = $this->_exp[$var_name]["exp"];
    if ($exp > 0) {
      if ($last + $exp < time()) return(true);
    }
    return(false);
  }

  public function getName() {
    return $this->_name;
  }

  public function destroy(){
    @session_destroy();
  }

  public function unsetSession() {
    @session_unset();
  }

  public function getId() {
    return $this->_id;
  }

  public function getUrl() {
    return $this->getName() .'='. $this->getId();
  }

  protected function _init($params) {
    $this->_name = isset($params['name']) ? $params['name'] : $this->_std_name;
    $this->_ip_name = (isset($params['prefix']) ? $params['prefix'] : $this->_std_prefix) . 'ip__';
    $this->_last_used_name = (isset($params['prefix']) ? $params['prefix'] : $this->_std_prefix) . 'last_used__';
    $this->_hash_name = (isset($params['prefix']) ? $params['prefix'] : $this->_std_prefix) . 'hash__';
    $this->_exp_name = (isset($params['prefix']) ? $params['prefix'] : $this->_std_prefix) . 'exp__';
    $this->_expired_name = (isset($params['prefix']) ? $params['prefix'] : $this->_std_prefix) . 'expired__';
    $this->_expired_action_name = (isset($params['prefix']) ? $params['prefix'] : $this->_std_prefix) . 'expired_action__';
    $this->_expired_action_params_name = (isset($params['prefix']) ? $params['prefix'] : $this->_std_prefix) . 'expired_action_params__';

    if (isset($params['savePath'])) { $this->_savePath = $params['savePath']; }
    if (isset($params['basePath'])) { $this->_basePath = $params['basePath']; }
    if (isset($params['skipCheckAge'])) { $this->_skipCheckAge = $params['skipCheckAge']; }
    if (isset($params['maxAge'])) { $this->_maxAge = $params['maxAge']; }
    if (isset($params['destroyExpired'])) { $this->_destroyExpired = $params['destroyExpired']; }
    if (isset($params['allowChangeIp'])) { $this->_allowChangeIp = $params['allowChangeIp']; }
    if (isset($params['allowedIps'])) { $this->_allowedIps = is_array($params['allowedIps'])?$params['allowedIps']:array($params['allowedIps']); }
    if (isset($params['useCookie'])) { $this->_useCookie = $params['useCookie']; }
    if (isset($params['cookieValidPath'])) { $this->_cookieValidPath = $params['cookieValidPath']; }

    if ($this->_savePath) {
      ini_set('session.save_path', $this->_savePath);
    }
    if ($this->_maxAge && $this->_destroyExpired && $this->_savePath) {
      ini_set('session.gc_maxlifetime', $this->_maxAge);
    }
  }

  protected function _checkAge() {
    $this->_register($this->_last_used_name);
    if (get_cfg_var('register_globals')) {
      $this->_last_used =& $GLOBALS[$this->_last_used_name];
    } else {
      $this->_last_used =& $_SESSION[$this->_last_used_name];
    }
    $this->_register($this->_expired_name);
    if (get_cfg_var('register_globals')) {
      $this->_expired =& $GLOBALS[$this->_expired_name];
    } else {
      $this->_expired =& $_SESSION[$this->_expired_name];
    }
    $this->_register($this->_expired_action_name);
    if (get_cfg_var('register_globals')) {
      $this->_expired_action =& $GLOBALS[$this->_expired_action_name];
    } else {
      $this->_expired_action =& $_SESSION[$this->_expired_action_name];
    }
    $this->_register($this->_expired_action_params_name);
    if (get_cfg_var('register_globals')) {
      $this->_expired_action_params =& $GLOBALS[$this->_expired_action_params_name];
    } else {
      $this->_expired_action_params =& $_SESSION[$this->_expired_action_params_name];
    }

    $now = time();
    $last = $this->_last_used?$this->_last_used:0;
    $age = $now - $last;
    if (($age > $this->_maxAge)) {
      if ($this->_destroyExpired) {
        session_id($this->_newId());
        $this->_setCookie();
        session_unset();
        $this->_reRegister();
      } elseif ($this->_last_used) {
        $this->_expired = true;
        
        if (!$this->_expired_action) {
          $app = Application::get();

          $this->_expired_action = $app->getActionFromRequest();
          $params = $app->request->getParams();
          if (count($params)) {
            unset($params['action']);
            unset($params['sessid']);
            $this->_expired_action_params = $params;
          }
        }
      }
    }

    if (!$this->_expired) $this->_last_used = $now;
  }

  public function getExpired() { return $this->_expired; }

  public function removeExpired() { 
    $this->_last_used = time();
    $this->_expired = false; 
    $this->_expired_action = null;
    $this->_expired_action_params = null;
  }

  public function getExpiredAction() { return $this->_expired_action; }
  public function getExpiredActionParams() { return $this->_expired_action_params; }

  private function _allowedIP($newIP) {
    $ret = true;

    if ($this->_ip&&($this->_ip!=$newIP)) {
      $ret = false;
      if ($this->_allowChangeIp) {
        if (!$this->_allowedIps) $ret = true;
        else {
          foreach ($this->_allowedIps as $IP) {
            if (($newIP==$IP)||isIpFromSubnet($newIP, $IP)) {
              $ret = true;
              break;
            }
          }
        }
      }
    }

    return $ret;
  }

  protected function _checkIP() {
    $app = Application::get();
    $error = false;
    $new = $app->getRemoteAddress();
    $this->_register($this->_ip_name);
    if (get_cfg_var('register_globals')) {
      $this->_ip =& $GLOBALS[$this->_ip_name];
    } else {
      $this->_ip =& $_SESSION[$this->_ip_name];
    }

    if (!$this->_allowedIP($new)) {
      $error = true;
    } else {
      $this->_ip = $new;
    }
    if ($error) {
      error_log(sprintf('SESSION: Client IP address was changed (%s -> %s)! Generating new one.', $this->_ip, $new));

      session_id($this->_newId());
      $this->_setCookie();
      session_unset();
      $this->_reRegister();
      $this->_ip = $new;
    }
    return($error);
  }

  protected function _reRegister() {
    $this->_register($this->_ip_name);
    $this->_register($this->_last_used_name);
    $this->_register($this->_expired_name);
    if (get_cfg_var('register_globals')) {
      $this->_ip =& $GLOBALS[$this->_ip_name];
      $this->_last_used =& $GLOBALS[$this->_last_used_name];
      $this->_expired =& $GLOBALS[$this->_expired_name];
    } else {
      $this->_ip =& $_SESSION[$this->_ip_name];
      $this->_last_used =& $_SESSION[$this->_last_used_name];
      $this->_expired =& $_SESSION[$this->_expired_name];
    }
  }

  protected function _expandVar($var_name) {
    $app = Application::get();
    if (preg_match("/^(.*)\[(.*)\]$/".(($app->getCharset() == 'utf-8') ? 'u' : ''), $var_name, $out)) {
      return($out[1]);
    } else {
      return($var_name);
    }
  }

  protected function _expandIndex($var_name) {
    $app = Application::get();
    if (preg_match("/^(.*)\[(.*)\]$/".(($app->getCharset() == 'utf-8') ? 'u' : ''), $var_name, $out)) {
      return($out[2]);
    } else {
      return;
    }
  }

  protected function _newId() {
    srand((double)microtime() * 1000000);
    return(md5(uniqid(rand())));
  }

  protected function _setCookie() {
    $app = Application::get();
    if ($this->getUseCookie()) {
      $app->request->setCookieVar(array(
            'name' => $this->_name,
            'expire' => 0,
            'path' => $this->_cookieValidPath?$this->_cookieValidPath:$this->_basePath, 
            'value' => session_id()));
    }
  }

  public function getUseCookie() { return $this->_useCookie; }
  public function setUseCookie($useCookie) { $this->_useCookie = $useCookie; }

  public function close() { session_write_close(); }

  public function getTagForUrl($amp=true) {
    $ret = '';
    if (!$this->getUseCookie()) {
      if ($amp) $ret = '&amp;';
      $ret .= sprintf('%s=%s', $this->getName(), $this->getId());
    }
    return $ret;
  }

  public function getTagForForm() {
    $ret = '';
    if (!$this->getUseCookie()) {
      $ret = sprintf('<input type="hidden" name="%s" value="%s" />', $this->getName(), $this->getId());
    }
    return $ret;
  }
}

?>
