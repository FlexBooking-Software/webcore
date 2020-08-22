<?php

class Response {
  public $_action;
  public $_params = array();
  public $_target;
  private $_targetSource = '__hashSource';
  private $_separator = '&';
   
  public function setAction($action) { $this->_action = $action; }

  public function addParams($params) { $this->_params = array_merge($this->_params, $params); }
  public function setParams($params) { $this->_params = $params; }

  public function getAllParams() {
    $app = Application::get();

    $ret = $this->_params;
    if ($this->_action) {
      $ret['action'] = urlencode($this->_action);
    }

    if (!$app->session->getUseCookie()) {
      $ret[$app->session->getName()] = $app->session->getId();
    }
    return $ret;
  }

  public function setTarget($target) { $this->_target = $target; }

  public function getTargetSource(){ return $this->_targetSource; }

  public function popTargetSource(){
    $hash = '';
    $hs = $this->getTargetSource();
    if (isset($this->_params[$hs]) && $this->_params[$hs]){
      $hash = $this->_params[$hs];
      unset($this->_params[$hs]);
    }
    return $hash;
  }

  protected function _modRewriteToUrl($url) {
    $replacement = Application::get()->getModRewriteReplacement();

    foreach ($replacement as $r) {
      $pattern = sprintf('/%s$/i', $r['pattern']);
      $url = preg_replace($pattern, $r['replacement'], $url);
    }
  
    return $url;
  }

  public function toUrl() {
    $app = Application::get();
    $prefix = $app->getProtocol() .'://';
    $serverPath = $prefix . $app->getHost() . $app->getWwwPath();

    $params = '';
    $useTarget = false;
    if ($this->_action) {
      $params .= 'action='. urlencode($this->_action);
      if (substr($this->_action, 0, 1) == "v") $useTarget = $this->popTargetSource(); 
    } else $useTarget = $this->popTargetSource();
    if (!$app->session->getUseCookie()) {
      $this->_params[$app->session->getName()] = $app->session->getId();
    }
    
    if (!$this->_target && $useTarget) $this->setTarget($useTarget);
    
    if ($moreParams = $this->getUrlParams($this->_params)) {
      $params .= (($params)?$this->_separator:'').$moreParams;
    }
    if ($params) {
      $params = '?'. $params;
    }
    $target = $this->_target ? '#' . $this->_target : '';
    $url = sprintf('%s%s%s', $serverPath, $params, $target);

    if ($app->getModRewrite()) $url = $this->_modRewriteToUrl($url);
    
    return $url;
  }

  public function getUrlParams($values, $name='', $separator=null) {
    if (is_null($separator)) $separator = $this->_separator;
    $out = $this->_recursiveVal($values,$name,$separator);
    if (strpos($out,$separator)==0) $out = substr($out,strlen($separator));
    return $out;
  }
 
  private function _recursiveVal($pole, $name='', $separator=null){
    if (is_null($separator)) $separator = $this->_separator;
    $out = '';
    if (is_array($pole)){
      foreach ($pole as $key => $val){
        $key = urlencode($key);
        if (!empty($name)) $key = "[$key]";
        $out .= $this->_recursiveVal($val,$name.$key,$separator);
      }
    }
    else $out .= $separator.$name."=".urlencode($pole);
    return $out;
  }
}

?>
