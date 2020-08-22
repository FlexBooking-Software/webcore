<?php

class Module {
  protected $_app;

  public function __construct() { $this->_app = Application::get(); }
  
  protected function _userRun() { }

  public function run() { $this->_userRun(); }
}

class ViewModule extends Module {
  protected $_canBeGzipped = true;
  protected $_browserRender = true;
  
  public function run() {
    if ($this->_browserRender) {
      $this->_insertIntoHistory();
  
      if ($this->_app->getCachePage()) {
        file_put_contents('/tmp/rct-cache', arrayToString($this->_app->request->getParams()));
        
        $offset = 60 * 60 * 24 * 3; 
        $ExpSr = "Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT"; 
        header("Cache-Control: must-revalidate");
        header($ExpSr); 
      } else {
        header('Cache-control: private, no-cache, no-store, must-revalidate');
        header('Expires: Thu, 01 Dec 1994 16:00:00 GMT');
        header('Pragma: no-cache');
      }
  
      if ($this->_canBeGzipped && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) { 
        #ob_start(null, 0, PHP_OUTPUT_HANDLER_STDFLAGS ^ PHP_OUTPUT_HANDLER_REMOVABLE);
        ob_start("ob_gzhandler");
      }
    }
    
    if (isset($_REQUEST['ao3OutputCharset']) && (substr($_REQUEST['ao3OutputCharset'],0,1)!='_')) {
      $out = mb_convert_encoding($this->_userRun(), $_REQUEST['ao3OutputCharset'], $this->_app->getCharset());
    } else {
      $out = $this->_userRun();
    }
    
    if ($this->_browserRender) {
      echo $out;
    } else {
      return $out;
    }
  }

  public function setBrowserRender($browserRender) { $this->_browserRender = $browserRender; }
  
  protected function _insertIntoHistory() { $this->_app->history->insertActual(); }
}

class ExecModule extends Module {
  private $_httpResponse = 303;
  protected $_maxUrlLength = 2000;

  public function run() {
    header('Cache-control: private, no-cache, no-store, must-revalidate');
    header('Expires: Thu, 01 Dec 1994 16:00:00 GMT');
    header('Pragma: no-cache');

    $action = $this->_userRun();
    if (!is_null($action)) {
      $this->_app->response->setAction($action);
      if (!$this->_app->session->getUseCookie()) {
        $this->_app->response->addParams(array($this->_app->session->getName() => $this->_app->session->getId() ));
      }

      $url = $this->_app->response->toUrl();

      if (strlen($url) > $this->_maxUrlLength) {
        $key = '_sessionget_'. rand();
        $this->_app->session->set($key, $this->_app->response->getAllParams());
        $this->_app->response->setAction(null);
        $this->_app->response->setParams(array('_sessionget_' => $key));
        $url = $this->_app->response->toUrl();
      }

      $header ='Location: '. $url;

      header($header, true, $this->_getHTTPResponse());
    }
  }

  private function _setHTTPResponse($response) { $this->_httpResponse = $response; }
  private function _getHTTPResponse() { return $this->_httpResponse; }
}

?>
