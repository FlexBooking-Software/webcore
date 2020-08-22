<?php

class History {
  private $_sessionPrefix = '__History_';
  private $_ignoreSuffix = array();
  private $_maxDepth = 20;
  private $_backwards = 2;

  public function __construct($params = array()) {
    $app = Application::get();

    if (isset($params['ignoreSuffix'])) { $this->_ignoreSuffix = $params['ignoreSuffix']; }
    if (isset($params['maxDepth'])) { $this->_maxDepth = $params['maxDepth']; }
    $backwards = intval($app->request->getParams('backwards'));
    if ($backwards > 0) {
      $this->setBackwards($backwards);
    }
    
  }

  public function setBackwards($backwards) { $this->_backwards = $backwards; }

  public function insertActual($data=null, $frame='') {
    $app = Application::get();

    if (empty($frame)) $frame = $app->request->getParams('frame');
    if (empty($frame)) $frame= ' ';
    $app->textStorage->setText(array('key' => '%frame%', 'value' => $frame));

    $history = $app->session->get($this->_sessionPrefix. $frame);
    if (!is_array($history)) { $history = array(); }
    $running = $this->_debordelizeHash(isset($data) ? $data : $app->request->getParams(null, array('get','post')));

    $deep = count($history);
    if (!$deep || ($running != $history[$deep-1])) {
      $history[$deep] = $running;
    }
    $rezerva = $this->_maxDepth - count($history);
    if ($rezerva < 0) {
      $newHistory = array();
      for ($i = 0; $i < $this->_maxDepth; $i++){
        $newHistory[$i] = $history[$i-$rezerva];
        if (empty($newHistory[$i]))error_log(__FILE__." History.insertActual() -> obsahuje prazdny index ".$i);
      }
      $history = $newHistory;
    }

    $app->session->set($this->_sessionPrefix. $frame, $history);
  }

  public function getBackwards($backwards=null, $frame='', $update=true) {
    $app = Application::get();

    $default = array( 'action' => $app->getDefaultAction() );

    if (empty($frame)) $frame = $app->request->getParams('frame');
    if (empty($frame)) $frame = ' ';
    
    if (is_null($backwards)) { $backwards = $this->_backwards; }
    $history    = $app->session->get($this->_sessionPrefix. $frame);

    if ( !is_array($history) || (count($history) < $backwards) ) { return $default; }

    $running = $history[count($history)-$backwards];
    if ($update) {
      for ($i=0; $i < $backwards; $i++) {
        unset($history[count($history)-1]);
      }
      $app->session->set($this->_sessionPrefix. $frame, $history);
    }
    if (!is_array($running)) { return $default; }

    return $running;
  }

  protected function _debordelizeHash($hash) {
    $app = Application::get();
    unset($hash[$app->session->getName()]);
    $new = array();
    foreach ($hash as $k => $v) {
      foreach ($this->_ignoreSuffix as $bad) {
        if ( (strlen($bad) <= strlen($k)) && ($bad == substr($k, -1 * strlen($bad))) ) {
          continue 2;
        }
      }
      $new[$k] = $v;
    }
    return $new;
  }
}

?>
