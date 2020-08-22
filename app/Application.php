<?php

spl_autoload_register(function ($class) {
  $location = Application::get()->getAutoloadClassLocation($class);
  if ($location !== false) {
    require_once $location;
  }
});

class Application {
  private $_debug;
  private $_phpDebug;
  private $_modRewrite = false;
  private $_modRewriteAction = array();
  private $_useSEO = false;
  private $_defaultAction;
  private $_action;
  private $_actionPrefixLength = 1;
  protected $_modules = array();
  protected $_autoloadClasses = array();
  private $_abortAction;
  private $_runMode = 'www';
  private $_protocol;
  private $_charset = 'ISO-8859-2';
  protected $_cachePage = false;
  protected $_nameSessionVar = '__app_';
  protected $_soapLogFile;

  public $request;
  public $response;
  public $session;
  public $messages;
  public $dialog;
  public $history;
  public $db;
  public $auth;
  public $regionalSettings;
  public $language;
  public $textStorage;
  //public $timer;

  public function __construct($params=array()) {
    $this->_phpDebug = E_STRICT | E_ALL; 

    if (function_exists('date_default_timezone_set')) {
      date_default_timezone_set('Europe/Prague');
    }

    $GLOBALS['application'] =& $this;
    $this->_initParams($params);
    if ($this->_debug) {
      ini_set('display_errors','1');
    } else {
      ini_set('display_errors','0');
    }
    $this->setPhpDebug($this->_phpDebug);
    $this->_protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http';

    try {
      $this->_initComponents();
    } catch (Exception $e) {
      echo $e->getMessage();
      die;
    }
  }

  protected function _initComponents() {
    $this->_initAutoloadClasses();
    //$this->_initTimer();
    $this->_initCharset();
    $this->_initRequest();
    $this->_initSession();
    $this->_initMessages();
    $this->_initDialog();
    $message = sprintf('%s:Application: running session: %s', get_class($this), $this->session->getId());
    $this->messages->addMessage('message', $message, 25);
    $this->_initModules();
    $this->_initResponse();
    $this->_initHistory();
    $this->_initDb();
    $this->_initLanguage();
    $this->_initTextstorage();
    $this->_initAuth();
    $this->_initRegionalSettings();
  }

  public static function &get() {
    return $GLOBALS['application'];
  }

  protected function _initParams($params) {
    if (isset($params['debug'])) { $this->_debug = $params['debug']; }
    if (isset($params['phpDebug'])) { $this->_phpDebug = $params['phpDebug']; }
    if (isset($params['modRewrite'])) { $this->_modRewrite = $params['modRewrite']; }
    if (isset($params['modRewriteAction'])) { $this->_modRewriteAction = $params['modRewriteAction']; }
    if (isset($params['useSEO'])) { $this->_useSEO = $params['useSEO']; }
    if (isset($params['defaultAction'])) { $this->_defaultAction = $params['defaultAction']; }
    if (isset($params['actionPrefixLength'])) { $this->_actionPrefixLength = $params['actionPrefixLength']; }
    if (isset($params['charset'])) { $this->setCharset($params['charset']); }
    if (isset($params['soapLogFile'])) { $this->setSoapLogFile($params['soapLogFile']); }
  }
  
  //protected function _initTimer($params=array()) { $this->timer = new Timer($params); }
  
  protected function _initSession($params=array()) { $this->session = new Session($params); }

  public function switchSession($sessionId) {
    $this->session->close();
    unset($this->session);
    $this->_initSession(array('sessionId' => $sessionId));
  }

  protected function _initRequest($params=array()) { $this->request = new Request($params); }

  protected function _initMessages($params=array()) { $this->messages = new Messages; }
  
  protected function _initDialog($params=array()) { $this->dialog = new Dialog; }

  protected function _initCharset() { }

  protected function _initModules($params=array()) {
    $path = dirname(__FILE__);
    $defaults = array(
        'eBack' => $path .'/action/eBack.php',
        'eGrid' => $path .'/action/eGrid.php');
    $this->addModules($defaults);
    $this->addModules($params);
  }

  protected function _initResponse($params=array()) { $this->response = new Response($params); }

  protected function _initHistory($params=array()) { $this->history = new History($params); }

  protected function _getCreateDbParams() { return array(); }
  protected function _createDb($params) { $this->db = new MysqlDb($params); }

  protected function _initDb() {
    $params = $this->_getCreateDbParams();
    $this->_createDb($params);
    if (isset($params['database'])) {
      $res = $this->db->connect();
      if ($res) { $res = $this->db->useDatabase(); }
    }
  }

  protected function _getCreateAuthParams() { return array(); }

  protected function _createAuth($params) { $this->auth = new Auth($params); }

  protected function _initAuth() {
    $params = $this->_getCreateAuthParams();
    $this->_createAuth($params);
  }

  protected function _initRegionalSettings($params=array()) { $this->regionalSettings = new RegionalSettings($params); }

  protected function _initLanguage($params=array()) { $this->language = new Language($params); }

  protected function _getCreateTextStorageParams() { return array(); }
  protected function _createTextStorage($params) { $this->textStorage = new TextStorage($params); }
  protected function _initTextStorage($params=array()) { $params = $this->_getCreateTextStorageParams(); $this->_createTextStorage($params);
  }

  protected function _initAutoloadClasses($params=array()) {
    $path = dirname(__FILE__);
    $defaults = array(
      'DocumentModule'              => $path .'/module/DocumentModule.php',
      'BackModule'                  => $path .'/module/BackModule.php',
      'GridModule'                  => $path .'/module/GridModule.php',
      'SoapServerPseudoModule'      => $path .'/module/SoapModule.php',
      'SoapServerModule'            => $path .'/module/SoapModule.php',

      'GuiMessages'                 => $path .'/../gui/Message.php',
      'GuiDialog'                   => $path .'/../gui/Dialog.php',
      'GuiTextButton'               => $path .'/../gui/Input.php',
      'GuiImgButton'                => $path .'/../gui/Input.php',
      'GuiInputButton'              => $path .'/../gui/Input.php',
      'GuiHashSelect'               => $path .'/../gui/Input.php',
      'GuiDataSourceSelect'         => $path .'/../gui/Input.php',
      'GuiGrid'                     => $path .'/../gui/Grid.php',
      'GuiGridVertical'             => $path .'/../gui/Grid.php',
      'GuiGridCellRenderer'         => $path .'/../gui/Grid.php',
      'GuiGridCellHeaderOrder'      => $path .'/../gui/Grid.php',
      'GuiGridCellAction'           => $path .'/../gui/Grid.php',
      'GuiForm'                     => $path .'/../gui/Form.php',
      'GuiFormItem'                 => $path .'/../gui/Form.php',
      'GuiFormInput'                => $path .'/../gui/Form.php',
      'GuiFormInputDate'            => $path .'/../gui/Form.php',
      'GuiFormButton'               => $path .'/../gui/Form.php',
      'GuiFormSelect'               => $path .'/../gui/Form.php',
      'GuiFormTextarea'             => $path .'/../gui/Form.php',
      'GuiDomMenu'                  => $path .'/../gui/DomMenu.php',
      'GuiDomMenuItem'              => $path .'/../gui/DomMenu.php',
      'GuiDomMenuItemTextStorage'   => $path .'/../gui/DomMenu.php',

      'GuiWebGrid'                  => $path .'/../gui/WebGrid.php',
      'WebGridSettings'             => $path .'/../gui/WebGrid.php',
      'GuiGridCellTextStorage'      => $path .'/../gui/WebGrid.php',
      'GuiGridCellYesNo'            => $path .'/../gui/WebGrid.php',
      'GuiGridCellTime'             => $path .'/../gui/WebGrid.php',
      'GuiGridCellDate'             => $path .'/../gui/WebGrid.php',
      'GuiGridCellDateTime'         => $path .'/../gui/WebGrid.php',
      'GuiGridCellNumber'           => $path .'/../gui/WebGrid.php',
      'GuiGridCellCut'              => $path .'/../gui/WebGrid.php',
      'GuiGridCellSpaceToNbsp'      => $path .'/../gui/WebGrid.php',

      'GridSettings'                => $path .'/../useful/GridSettings.php',
      'DataSourceSettings'          => $path .'/../useful/DataSource.php',
      'SqlDataSource'               => $path .'/../useful/DataSource.php',
      'ArrayDataSource'             => $path .'/../useful/DataSource.php',
      'HashDataSource'              => $path .'/../useful/DataSource.php',
      'Validator'                   => $path .'/../useful/Validator.php',
      'PHPMailer'                   => $path .'/../useful/Mailer.php',
      'SMTP'                        => $path .'/../useful/Smtp.php',
      'CURL'                        => $path .'/../useful/Curl.php',
      'CSOBGateway'                 => $path .'/../useful/CSOBGateway.php',
      'COMGATEGateway'              => $path .'/../useful/COMGATEGateway.php',
      'GPWebpayGateway'             => $path .'/../useful/GPWebpayGateway.php',
      'Deminimis'                   => $path .'/../useful/Deminimis.php',
    );

    $this->addAutoloadClasses($defaults);
    $this->addAutoloadClasses($params);
  }

  public function getAutoloadClassLocation($className) {
    $location = isset($this->_autoloadClasses[$className]) ? $this->_autoloadClasses[$className] : false;
    return $location;
  }

  final public function testAction($action) { return $this->_testAction($action); }
  protected function _testAction($action) { return $action; }

  public function getDebug() { return $this->_debug; }
  public function setDebug($debug) { return $this->_debug = $debug; }

  public function setPhpDebug($level) {
    $this->_phpDebug = $level;
    error_reporting($level);
  }

  public function getModRewrite() { return $this->_modRewrite; }
  public function setModRewrite($modRewrite) { $this->_modRewrite = $modRewrite; }

  public function getModRewriteReplacement() {
    $ret = array();
    foreach ($this->_modRewriteAction as $action=>$replacement) {
      $ret[] = array('pattern' => sprintf('index.php\?action=%s(&id=([a-zA-Z0-9_-]+))+', $action), 'replacement' => sprintf('%s/${2}.html', $replacement));
    }
    $ret[] = array('pattern' => 'index.php\?action=(\w+)(&id=([a-zA-Z0-9_-]+))+', 'replacement' => '${1}/${3}.html');
    $ret[] = array('pattern' => 'index.php\?action=(\w+)', 'replacement' => '${1}/');

    return $ret;
  }

  public function getUseSEO() { return $this->_useSEO; }
  public function setUseSEO($useSEO) { $this->_useSEO = $useSEO; }

  public function formatSEO($name) {
    $name = removeDiakritics($name, $this->_charset);
    $name = preg_replace("/[^a-zA-Z0-9\s-_]/", '', $name);
    $name = strtolower(str_replace(' ','-',$name));

    return $name;
  }

  public function addModules($modules) { $this->_modules = array_merge($this->_modules, $modules); }

  public function getModuleLocation($action) {
    $ret = false;
    if (isset($this->_modules[$action])) {
      $ret = $this->_modules[$action];
    }
    return $ret;
  }

  public function addAutoloadClasses($classes) { $this->_autoloadClasses = array_merge($this->_autoloadClasses, $classes); }

  protected function _applyCorrectionsOnAction($action) {
    $action = substr($action, 7);
    return $action;
  }

  public function getRunMode() { return $this->_runMode; }

  public function setActionPrefixLength($length) { $this->_actionPrefixLength = $length; }
  public function getActionPrefixLength() { return $this->_actionPrefixLength; }

  public function setSoapLogFile($file) { $this->_soapLogFile = $file; }
  public function getSoapLogFile() { return $this->_soapLogFile; }

  public function getActionFromRequest() {
    $action = false;

    foreach ($this->request->getParams() as $name => $value) {
      if (substr($name, 0, 7) == 'action_') {
        $action = $this->_applyCorrectionsOnAction($name);

        $questionMark = strpos($action, '?');
        if ($questionMark !== false) {
          $originalAction = $action;
          $action = substr($action, 0, $questionMark);

          $paramsPost = $this->request->getParams(null, 'post');
          if (!is_array($paramsPost)) { $paramsPost = array(); }

          $paramsString = urldecode(substr($originalAction, $questionMark + 1));
          foreach (explode('&', $paramsString) as $one) {
            list ($name, $value) = explode('=', $one);

            $a = strpos($name, '[');
            $b = strpos($name, ']');
            if (($a !== false) && ($b !== false) && ($a < $b)) {
              $originalName = $name;
              $name = substr($name, 0, $a);
              if (!isset($paramsPost[$name]) || !is_array($paramsPost[$name])) {
                $paramsPost[$name] = array();
              }

              $key = substr($originalName, $a + 1, $b - $a - 1);
              if ($key) {
                $paramsPost[$name][$key] = $value;
              } else {
                $paramsPost[$name][] = $value;
              }
            } else {
              $paramsPost[$name] = $value;
            }
          }
          $this->request->registerSource('post', $paramsPost);
        }
      }
    }
    if (!$action && $this->request->isSetParam('action')) {
      $action = $this->request->getParams('action');
    }

    return $action;
  }

  public function getAction() {
    $action = false;
    if (!isset($this->_action)) {
      $action = $this->getActionFromRequest();

      if ($action && !isset($this->_modules[$action])) {
        $message = sprintf('%s:run: unknown action (%s)', get_class($this), $action);
        $this->messages->addMessage('error', $message);
        $action = false;
      }
      if (!$action) {
        $action = $this->getDefaultAction();
      }
      $this->_action = $this->_testAction($action);
    }
    return $this->_action;
  }

  public function getProtocol() { return $this->_protocol; }
  public function setProtocol($protocol) {
    if ($this->_protocol !== $protocol) {
      $this->_protocol = $protocol;
      if ($this->document instanceof DocumentModule) {
        $this->document->setAbortRender(true);
      }
      $this->setProtocol('https');
      $this->history->setBackwards(1);
      $this->setAbortAction('eBack');
    }
  }

  public function getMemoryUsage() {
    $app = Application::get();
    $ret = $app->regionalSettings->convertNumberToHuman(memory_get_usage()/1024, 0);
    return $ret;
  }

  public function getCompleteUrl() { return sprintf('%s://%s%s', $this->getProtocol(), $this->getHost(), $this->getRequest()); }
  public function getUrl() { return sprintf('%s://%s%s', $this->getProtocol(), $this->getHost(), $this->getWwwPath()); }
  public function getHost() { return ifsetor($_SERVER['HTTP_HOST'],'localhost'); }
  public function getServerName() { return $_SERVER['SERVER_NAME']; }
  public function getWwwPath() { return $_SERVER['SCRIPT_NAME']; }
  public function getRequest() { return $_SERVER['REQUEST_URI']; }
  public function getDirName() { return dirname($this->getWwwPath()); }
  public function getRemoteAddress() {
    $ret = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'localhost';
    return $ret;
  }

  public function getBaseName() { return basename($this->getWwwPath()); }

  public function getBaseDir() {
    $dir = dirname($this->getWwwPath());
    if ($dir !== '/') { $dir .= '/'; }
    return $dir;
  }

  public function getAbortAction() { return $this->_abortAction; }
  public function setAbortAction($action) { $this->_abortAction = $action; }
  public function getDefaultAction() { return $this->_defaultAction; }

  public function getCharset() { return $this->_charset; }
  public function setCharset($charset) {
    $this->_charset = strtoupper($charset);

    // neexistuje pro windows-1250
    if ($this->_charset == 'WINDOWS-1250') $charset = 'windows-1251';
    if (function_exists('mb_internal_encoding')) {
      mb_internal_encoding($charset);
    }
  }
  
  public function htmlspecialchars($val) {
    $ret = htmlspecialchars($val, ENT_QUOTES, !strcmp($this->_charset,'ISO-8859-2')?'ISO-8859-1':$this->_charset);
    
    return $ret;
  }

  public function setCachePage($orly = false) { $this->_cachePage = ($orly == true); }
  public function getCachePage() { return $this->_cachePage; }

  public function run() {
    $runMode = $this->request->getParams('runMode');
    if ($runMode) {
      $this->_runMode = $runMode;
    }

    switch ($this->getRunMode()) {
      case 'soap':
        $this->_soapRun();
        break;
      default:
        $this->_wwwRun();
    }
  }

  protected function _wwwRun() {
    $action = $this->getAction();

    do {
      try {
        $this->setAbortAction(false);

        $message = sprintf('%s:run: running module %s', get_class($this), $action);
        $this->messages->addMessage('message', $message, 25);
        //error_log($message);

        if (!isset($this->_modules[$action])) { throw new Exception; }
        require_once $this->_modules[$action];
        $moduleClass = 'Module'. substr($action,$this->_actionPrefixLength);
        $module = new $moduleClass();
        $module->run();
        
      } catch (ExceptionUserGui $e) {
        $this->messages->addMessage('userError', $e->printMessage());
        $this->_wwwRunSolveException($module, $e);
      } catch (ExceptionUserTextStorage $e) {
        $this->messages->addMessage('userError', $e->printMessage());
        $this->_wwwRunSolveException($module, $e);
      } catch (ExceptionUser $e) {
        $this->messages->addMessage('userError', $e->printMessage());
        $this->_wwwRunSolveException($module, $e);
      } catch (Exception $e) {
        $message = $this->textStorage->getText('error.unknown_error') . ($this->getDebug() ? (': '. $e->getMessage()) : '');
        $this->messages->addMessage('error', $message, 100);
        $this->_wwwRunSolveException($module, $e);
      }
    } while ($action = $this->getAbortAction());

    if ($module instanceof ViewModule) {
      $this->messages->reset();
      $this->dialog->reset();
    }
  }

  protected function _soapRun() {
    $soapServerEncoding = 'iso-8859-2';
    $this->soapServer = new SoapServer(null, array(
          'encoding' => $soapServerEncoding,
          'uri'      => '' ));
    $this->soapServer->setClass('SoapServerPseudoModule');
    try {
      $this->soapServer->handle();
    } catch (Exception $e) {
      $message = iconv($soapServerEncoding, 'utf-8', $e->getMessage());
      $this->soapServer->fault($e->getCode(), $message);
    }
  }

  protected function _wwwRunSolveException($module, $exception) {
    if ($module instanceof ExecModule) {
      $this->history->setBackwards(1);
    } 
    $this->db->shutdownTransaction();

    if ($module instanceof SoapServerModule) {
      adump($exception);
      adump($this->messages);
    } else {
      $this->setAbortAction('eBack');
    }
  }
}

?>
