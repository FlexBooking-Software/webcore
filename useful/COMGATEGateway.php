<?php

class COMGATEGateway {
  private $_logFile;
  
  private $_language = 'cs';
  private $_currency = 'CZK';
  private $_country = 'CZ';
  
  private $_gatewayUrl;

  private $_merchantId;
  private $_secret;
  private $_test = true;

  private $_method = 'ALL';

  private $_paymentId;
  private $_paymentUrl;

  public function __construct($params=false) {
    if (is_array($params)) {
      if (isset($params['logFile'])) $this->_logFile = $params['logFile'];
      if (isset($params['language'])) $this->_language = $params['language'];
      if (isset($params['currency'])) $this->_currency = $params['currency'];
      if (isset($params['country'])) $this->_country = $params['country'];
      if (isset($params['cartLabel'])) $this->_cartLabel = $params['cartLabel'];
      if (isset($params['gatewayUrl'])) $this->_gatewayUrl = $params['gatewayUrl'];
      if (isset($params['merchantId'])) $this->_merchantId = $params['merchantId'];
      if (isset($params['secret'])) $this->_secret = $params['secret'];
      if (isset($params['test'])) $this->_test = $params['test'];
      if (isset($params['method'])) $this->_method = $params['method'];
    }
  }
  
  public function setPaymentId($paymentId) {
    $this->_paymentId = $paymentId;
    $this->_paymentTimestamp = (new DateTime)->format('YmdHis');
  }
  
  public function getPaymentId() { return $this->_paymentId; }
  
  private function _createLogRecord($msg) {
    if ($this->_logFile&&$msg) {
      $line = date('Y-m-d-H-i-s').': '.$msg."\n";
      file_put_contents($this->_logFile, $line, FILE_APPEND | LOCK_EX);
    }
  }

  public function log($msg) {
    $this->_createLogRecord($msg);
  }

  private function _encodeParams($params) {
    $data = '';

    foreach ($params as $key => $val) {
      $data .= ($data === '' ? '' : '&').urlencode($key).'='.urlencode($val);
    }

    return $data;
  }

  private function _decodeParams($data) {
    $encodedParams = explode('&', $data);

    $params = array();
    foreach ($encodedParams as $encodedParam) {
      $encodedPair = explode('=', $encodedParam);
      $paramName = urlencode($encodedPair[0]);
      $paramValue = (count($encodedPair) == 2 ? urldecode($encodedPair[1]) : '');
      $params[$paramName] = $paramValue;
    }

    return $params;
  }

  private function _getResponseParam($response, $paramName) {
    if (!isset($response[$paramName])) {
      throw new ExceptionUser(get_class($this) . ': missing response parameter '. $paramName);
    }

    return $response[$paramName];
  }
  
  public function createPayment($refId, $amount, $description, $email) {
    $amount = $amount * 100;
    
    $data = array (
      "merchant"      => $this->_merchantId,
      "secret"        => $this->_secret,
      "prepareOnly"   => 'true',
      "test"          => $this->_test?'true':'false',
      "country"       => $this->_country,
      "curr"          => $this->_currency,
      "method"        => $this->_method,
      'lang'          => $this->_language=='cz'?'cs':'en',
      "price"         => $amount,
      "label"         => $description,
      "refId"         => $refId,
      "email"         => $email,
    );

    $this->_createLogRecord(sprintf('Creating payment: %s', var_export($data, true)));

    #error_log($this->_gatewayUrl);
    #error_log($this->_encodeParams($data));
    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $this->_gatewayUrl . '/create');
    curl_setopt($c, CURLOPT_POST, 1);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_HEADER, 0);
    curl_setopt($c, CURLOPT_POSTFIELDS, $this->_encodeParams($data));
    $result = curl_exec($c);
    #error_log($result);
    
    $this->_createLogRecord(sprintf('Payment creation result: %s', $result));

    if (curl_errno($c)) throw new ExceptionUser(get_class($this) . ': create payment failed, reason: ' . htmlspecialchars(curl_error($c)));

    curl_close($c);
    
    $result_array = $this->_decodeParams($result);
    $code = $this->_getResponseParam($result_array, 'code');
    $message = $this->_getResponseParam($result_array, 'message');
    if (($code != '0')||($message != 'OK')) throw new ExceptionUser(get_class($this) . ': create payment failed, reason: ' . htmlspecialchars($message));
    
    $this->_paymentId = $this->_getResponseParam($result_array, 'transId');
    $this->_paymentUrl = $this->_getResponseParam($result_array, 'redirect');
  }

  public function getPaymentProcessUrl() {
    return $this->_paymentUrl;
  }
  
  public function getPaymentStatus($paymentId) {
    $data = array (
      "merchant"      => $this->_merchantId,
      "secret"        => $this->_secret,
      "transId"       => $paymentId,
    );

    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $this->_gatewayUrl . '/status');
    curl_setopt($c, CURLOPT_POST, 1);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_HEADER, 0);
    curl_setopt($c, CURLOPT_POSTFIELDS, $this->_encodeParams($data));
    $result = curl_exec($c);
    
    $this->_createLogRecord(sprintf('Payment status result: %s', $result));

    if (curl_errno($c)) throw new ExceptionUser(get_class($this) . ': payment status failed, reason: ' . htmlspecialchars(curl_error($c)));

    curl_close($c);
    
    $result_array = $this->_decodeParams($result);
    $code = $this->_getResponseParam($result_array, 'code');
    $message = $this->_getResponseParam($result_array, 'message');
    if (($code != '0')||($message != 'OK')) throw new ExceptionUser(get_class($this) . ': payment status failed, reason: ' . htmlspecialchars($message));

    $payment = array(
      'paymentId'     => $result_array['transId'],
      'method'        => $result_array['method'],
      'payed'         => in_array(ifsetor($result_array['status'],-1),array('PAID','AUTHORIZED')),
      'paymentStatus' => $result_array['status'],
      'notFinished'   => in_array(ifsetor($result_array['status'],-1), array('PENDING')),
    );

    return $payment;
  }

  public function refundPayment($paymentId, $amount) {
    $amount = $amount * 100;

    $data = array (
      "merchant"      => $this->_merchantId,
      "secret"        => $this->_secret,
      "test"          => $this->_test?'true':'false',
      "transId"       => $paymentId,
      "curr"          => $this->_currency,
      "amount"        => $amount,
    );

    $this->_createLogRecord(sprintf('Refunding payment: %s', var_export($data, true)));

    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $this->_gatewayUrl . '/refund');
    curl_setopt($c, CURLOPT_POST, 1);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_HEADER, 0);
    curl_setopt($c, CURLOPT_POSTFIELDS, $this->_encodeParams($data));
    $result = curl_exec($c);
    
    $this->_createLogRecord(sprintf('Payment refund result: %s', $result));

    if (curl_errno($c)) throw new ExceptionUser(get_class($this) . ': refund payment failed, reason: ' . htmlspecialchars(curl_error($c)));

    curl_close($c);

    $result_array = $this->_decodeParams($result);
    $code = $this->_getResponseParam($result_array, 'code');
    $message = $this->_getResponseParam($result_array, 'message');
    if (($code != '0')||($message != 'OK')) throw new ExceptionUser(get_class($this) . ': refund payment failed, reason: ' . htmlspecialchars($message));
  }
}

?>
