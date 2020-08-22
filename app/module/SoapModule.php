<?php

class SoapServerPseudoModule {

  public function __call($action, $arguments) {
    $app = Application::get();

    if ($fName = $app->getSoapLogFile()) {
      $actionCalled = $action;
      $request = file_get_contents('php://input');

      $handle = fopen($fName, 'a');
      $prefix = sprintf("%d: %s SOAP REQUEST (%s) FROM: %s ********************\n", getmypid(), date('Y-m-d H:i:s'), $action, $_SERVER['REMOTE_ADDR']);
      fwrite($handle, $prefix.$request);
    }

    do {
      $app->setAbortAction(false);

      $message = sprintf('%s:run: running module %s', get_class($this), $action);
      $app->messages->addMessage('message', $message, 25);

      // pripravit modul
      $location = $app->getModuleLocation($action);
      if ($location === false) { throw new Exception('Unknown SOAP action.'); }
      require $location;
      $moduleClass = 'Module'. substr($action,$app->getActionPrefixLength());
      $module = new $moduleClass();                 

      // pripravit parametry se kteryma byla akce zavolana
      $params = array();
      $i = 0;
      foreach ($module->getParamNames() as $param) {
        if (isset($arguments[$i])) { $params[$param] = $arguments[$i]; }
        $i++;
      }

      foreach ($module->getParamDefaults() as $param => $value) {
        if (!isset($params[$param])) { $params[$param] = $value; }
      }

      // nastavit parametry akce do requestu
      $app->request->registerSource('soap', $params);
      $app->request->setDefaultSources('soap');

      $newAction = $app->testAction($action);

      if ($newAction === $action) {
        $ret = $module->run();
      } else {
        $app->setAbortAction($newAction);
      }

    } while ($action = $app->getAbortAction());

    $app->messages->reset();

    if ($fName) {
      $retString = var_export($ret, true);
      $prefix = sprintf("%d: %s SOAP RESPONSE (%s) TO: %s ********************\n", getmypid(), date('Y-m-d H:i:s'), $actionCalled, $_SERVER['REMOTE_ADDR']);
      fwrite($handle, $prefix.$retString."\n");

      fclose($handle);
    }

    return $ret;
  }
}

class SoapServerModule extends Module {
  protected $_paramNames = array();
  protected $_paramDefaults = array();

  public function getParamNames() { return $this->_paramNames; }
  public function getParamDefaults($key=null) { 
    $ret = $this->_paramDefaults;
    if (!is_null($key)) {
      $ret = isset($this->_paramDefaults[$key]) ? $this->_paramDefaults[$key] : null;
    }
    return $ret; 
  }

  public function run() {
    $ret = $this->_userRun();
    if (Application::get()->getRunMode() != 'soap') {
      $debug = '';
      if ($this->_app->getDebug()) {
        foreach ($this->_app->messages->getMessages() as $one) {
          $debug .= sprintf('%s:%d> %s<br />%s', Application::get()->htmlspecialchars($one['type']), $one['level'], Application::get()->htmlspecialchars($one['message']), "\n");
        }
      }
      echo $debug;

      echo '<hr />';
      adump($ret);
      die;
    } else {
      if ($this->_app->getDebug()) {
        $ret = array('returnValue'=>$ret, 'messages'=>$this->_app->messages->getMessages());
      }
    }
    return $ret;
  }
}

?>
