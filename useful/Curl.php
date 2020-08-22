<?php

class CURL {
  private $_params = array();

  public function __construct($params=false) {
    if (is_array($params)) {
      if (isset($params['header'])) $this->_params['header'] = $params['header'];
      if (isset($params['callback'])) $this->_params['callback'] = $params['callback'];
      if (isset($params['username'])) $this->_params['username'] = $params['username'];
      if (isset($params['password'])) $this->_params['password'] = $params['password'];
      if (isset($params['certificateFile'])) $this->_params['certificateFile'] = $params['certificateFile'];
      if (isset($params['certificatePassword'])) $this->_params['certificatePassword'] = $params['certificatePassword'];
      if (isset($params['keyFile'])) $this->_params['keyFile'] = $params['keyFile'];
      if (isset($params['keyPassword'])) $this->_params['keyPassword'] = $params['keyPassword'];
      if (isset($params['verifyPeer'])) $this->_params['verifyPeer'] = $params['verifyPeer'];
      if (isset($params['verifyHost'])) $this->_params['verifyHost'] = $params['verifyHost'];
      if (isset($params['uploadFile'])) $this->_params['uploadFile'] = $params['uploadFile'];
    }
  }

  public function setHeader($header) {
    $this->_params['header'] = $header;
  }

  public function setCallback($func_name) {
    $this->_params['callback'] = $func_name;
  }

  public function setAuthParams($params) {
    if (isset($params['username'])) $this->_params['username'] = $params['username'];
    if (isset($params['password'])) $this->_params['password'] = $params['password'];
  }

  public function setSSLParams($params) {
    if (isset($params['certificateFile'])) $this->_params['certificateFile'] = $params['certificateFile'];
    if (isset($params['certificatePassword'])) $this->_params['certificatePassword'] = $params['certificatePassword'];
    if (isset($params['keyFile'])) $this->_params['keyFile'] = $params['keyFile'];
    if (isset($params['keyPassword'])) $this->_params['keyPassword'] = $params['keyPassword'];
    if (isset($params['verifyPeer'])) $this->_params['verifyPeer'] = $params['verifyPeer'];
    if (isset($params['verifyHost'])) $this->_params['verifyHost'] = $params['verifyHost'];
  }

  public function setUploadFile($file) {
    $this->_params['uploadFile'] = $file;
  }

  private function _doRequest($method, $url, $vars) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, ifsetor($_SERVER['HTTP_USER_AGENT'],'Mozilla'));
    
    if (isset($this->_params['header'])) curl_setopt($ch, CURLOPT_HEADER, $this->_params['header']);

    // UPLOAD FILE
    if (isset($this->_params['uploadFile'])) {
      $handle = fopen($this->_params['uploadFile'], 'r');
      curl_setopt($ch, CURLOPT_UPLOAD, 1);
      curl_setopt($ch, CURLOPT_PUT, 1);
      curl_setopt($ch, CURLOPT_INFILE, $handle);
      curl_setopt($ch, CURLOPT_INFILESIZE, filesize($this->_params['uploadFile']));
    }

    // VARS
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if (in_array($method,array('POST','PUT'))&&count($vars)) {
      $postVars = '';
      if (is_array($vars)) {
        foreach ($vars as $key=>$value) {
          if ($postVars) $postVars .= '&';
          $postVars .= sprintf('%s=%s', $key, urlencode($value));
        }
      } else {
        $postVars = $vars;
      }
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $postVars);
    }

    // custom request
    if (!in_array($method, array('GET','POST'))) {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }
    
    // AUTH options
    if (isset($this->_params['username'])&&isset($this->_params['password'])) {
      curl_setopt($ch, CURLOPT_USERPWD, sprintf('%s:%s', $this->_params['username'], $this->_params['password']));
    }

    // SSL options
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    if (isset($this->_params['certificateFile'])) curl_setopt($ch, CURLOPT_SSLCERT, $this->_params['certificateFile']);
    if (isset($this->_params['certificatePassword'])) curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->_params['certificatePassword']);
    if (isset($this->_params['keyFile'])) curl_setopt($ch, CURLOPT_SSLKEY, $this->_params['keyFile']);
    if (isset($this->_params['keyPassword'])) curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $this->_params['keyPassword']);
    if (isset($this->_params['verifyPeer'])) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->_params['verifyPeer']);
    if (isset($this->_params['verifyHost'])) curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->_params['verifyHost']);

    $data = curl_exec($ch);
    if ($data) {
      if (isset($this->_params['header'])&&$this->_params['header']) {
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($data, 0, $header_size);
        $data = substr($data, $header_size);
      }
      if (isset($this->_params['callback'])) {
        $callback = $this->_params['callback'];
        $data = call_user_func($callback, $data);
      }
      if (isset($header)) $ret = array('header'=>$header,'body'=>$data);
      else $ret = $data;
    } else {
      $ret = curl_error($ch);
    }
    curl_close($ch);

    return $ret;
  }

  public function get($url) {
    return $this->_doRequest('GET', $url, null);
  }

  public function post($url, $vars=null) {
    return $this->_doRequest('POST', $url, $vars);
  }

  public function put($url, $vars=null) {
    return $this->_doRequest('PUT', $url, $vars);
  }
  
  public function delete($url) {
    return $this->_doRequest('DELETE', $url, null);
  }
}

?>
