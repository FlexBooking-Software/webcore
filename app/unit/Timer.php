<?php

class Timer {
  protected $_watch=array();
  protected $_logDb=false;
  protected $_logRender=false;
  
  public function __construct($params=array()) {
    if (isset($params['logDb'])) { $this->_logDb = $params['logDb']; }
    if (isset($params['logRender'])) { $this->_logRender = $params['logRender']; }
  }
  
  public function getLogDb() { return $this->_logDb; }
  public function getLogRender() { return $this->_logRender; }
  
  protected function _getWatch($name) {
    foreach ($this->_watch as $watch) {
      if ($watch->getName() == $name) return $watch;
    }
    
    $watch = new TimerWatch(array('name'=>$name));
    $this->_watch[] = $watch;
    return $watch;
  }
  
  public function start($name) { $this->_getWatch($name)->start(); }
  public function stop($name) { $this->_getWatch($name)->stop(); }
  public function reset($name) { $this->_getWatch($name)->reset(); }
  
  protected function _addMessage($watchOutput) {
    $message = sprintf('%s: %s', get_class($this), $watchOutput);
    Application::get()->messages->addMessage('message', $message, 50);
  }
  
  public function report($name=null) {
    if ($name) {
      $this->_addMessage($this->_getWatch($name)->toString());
    } else {
      foreach ($this->_watch as $watch) {
        $this->_addMessage($watch->toString());
      }
    }
  }
}

class TimerWatch {
  protected $_name = '__anonymous__';
  protected $_running;
  protected $_runCount;
  protected $_start;
  protected $_end;
  protected $_time;
  
  public function __construct($params=array()) {
    if (isset($params['name'])&&$params['name']) { $this->_name = $params['name']; }
    
    $this->_running = false;
    $this->_runCount = 0;
    $this->_time = 0;
  }
  
  public function getName() { return $this->_name; }
  
  public function start() {
    $this->_start = microtime(true);
    $this->_end = null;
    $this->_running = true;
  }
  
  public function stop() {
    if ($this->_running) {
      $this->_end = microtime(true);
      $this->_running = false;
      
      $difference = $this->_end - $this->_start;
      if ($difference<0) throw new ExceptionUser('TIMER fault.');
      
      $this->_time += $difference;
      $this->_runCount++;
    }
  }
  
  public function reset() {
    $this->_running = false;
    $this->_runCount = 0;
    $this->_start = null;
    $this->_end = null;
    $this->_time = 0;
  }
  
  public function toString() {
    $ret = sprintf('%s%s - %f sec, %d times', $this->_name, $this->_running?' (running)':'', $this->_time, $this->_runCount);
    
    return $ret;
  }
}

?>