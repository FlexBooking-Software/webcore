<?php

class Deminimis {
  private $_logFile;
  
  private $_language = 'CZ';
  private $_currency = 'CZK';
  
  private $_gatewayUrl;
  private $_apiUrl;
  private $_apiKey;
  
  private $_paymentId;

  public function __construct($params=false) {
    if (is_array($params)) {
      if (isset($params['logFile'])) $this->_logFile = $params['logFile'];
      if (isset($params['language'])) $this->_language = $params['language'];
      if (isset($params['currency'])) $this->_currency = $params['currency'];
      if (isset($params['gatewayUrl'])) $this->_gatewayUrl = $params['gatewayUrl'];
      if (isset($params['apiUrl'])) $this->_apiUrl = $params['apiUrl'];
      if (isset($params['apiKey'])) $this->_apiKey = $params['apiKey'];
    }
  }

  public function getPaymentId() { return $this->_paymentId; }
  
  private function _createLogRecord($msg) {
    if ($this->_logFile&&$msg) {
      $line = date('Y-m-d-H-i-s').': '.$msg."\n";
      file_put_contents($this->_logFile, $line, FILE_APPEND | LOCK_EX);
    }
  }

  public function getPaymentGatewayUrl() { return $this->_gatewayUrl; }
  
  public function createPayment($reference, $amount, $description) {
    $amount = $amount * 100;
    
    $data = array (
      "companyId"     => $reference,
      "requestDate"   => date('Y-m-d\TH:i:s'),
      "currency"      => $this->_currency,
      "amount"        => $amount,
      "description"   => $description
    );

    $this->_createLogRecord(sprintf('Creating payment: %s', var_export($data, true)));
    
    $ch = curl_init($this->_apiUrl . '/api/support-request');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $header = array('Content-Type: application/json','Accept: application/json;charset=UTF-8');
    if ($this->_apiKey) $header[] = sprintf('x-auth-token: %s', $this->_apiKey);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

    $result = curl_exec($ch);
    
    $this->_createLogRecord(sprintf('Payment creation result: %s', $result));

    if (curl_errno($ch)) throw new ExceptionUser(get_class($this) . ': api/support-request failed, reason: ' . htmlspecialchars(curl_error($ch)));
    #if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) throw new ExceptionUser(get_class($this) . ': api/support-request failed, http response: ' . htmlspecialchars(curl_getinfo($ch, CURLINFO_HTTP_CODE)));

    curl_close($ch);
    
    $result_array = json_decode($result, true);
    if (isset($result_array['errors'])&&count($result_array['errors'])) throw new ExceptionUser(get_class($this) . ': api/support-request/status failed, reason: ' . htmlspecialchars($result_array['errors'][0]['message']));
    if (!isset($result_array['requestId'])) throw new ExceptionUser(get_class($this) . ': api/support-request failed, reason: no ID');
    
    $this->_paymentId = $result_array['requestId'];

    return $this->_paymentId;
  }
  
  public function getPaymentStatus($paymentId) {
    $ch = curl_init($this->_apiUrl . '/api/support-request/' . $paymentId);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $header = array('Content-Type: application/json','Accept: application/json;charset=UTF-8');
    if ($this->_apiKey) $header[] = sprintf('x-auth-token: %s', $this->_apiKey);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

    $result = curl_exec($ch);
    
    $this->_createLogRecord(sprintf('Payment status result: %s', $result));

    if (curl_errno($ch)) throw new ExceptionUser(get_class($this) . ': api/support-request/status failed, reason: ' . htmlspecialchars(curl_error($ch)));
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) throw new ExceptionUser(get_class($this) . ': api/support-request/status failed, http response: ' . htmlspecialchars(curl_getinfo($ch, CURLINFO_HTTP_CODE)));

    curl_close($ch);
    
    $result_array = json_decode($result, true);
    if (isset($result_array['errors'])&&count($result_array['errors'])) throw new ExceptionUser(get_class($this) . ': api/support-request/status failed, reason: ' . htmlspecialchars($result_array['errors'][0]['message']));
    
    $payment = array(
      'paymentId'     => $result_array['requestId'],
      'payed'         => !strcmp($result_array['state'],'approved'),
      'resultMessage' => $result_array['state'],
      'paymentStatus' => $result_array['state'],
      'notFinished'   => !in_array($result_array['state'], array('approved','denied')),
    );

    return $payment;
  }
  
  public function reversePayment($paymentId) {
    $data = array(
      "requestId"     => $paymentId,
      "reason"   => "7",
    );

    $this->_createLogRecord(sprintf('Reversing payment: %s', var_export($data, true)));
    
    $ch = curl_init($this->_apiUrl . '/api/support-request');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $header = array('Content-Type: application/json','Accept: application/json;charset=UTF-8');
    if ($this->_apiKey) $header[] = sprintf('x-auth-token: %s', $this->_apiKey);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

    $result = curl_exec($ch);
    
    $this->_createLogRecord(sprintf('Payment reverse result: %s', $result));

    if (curl_errno($ch)) throw new ExceptionUser(get_class($this) . ': /api/support-request/reverse failed, reason: ' . htmlspecialchars(curl_error($ch)));
    #if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) throw new ExceptionUser(get_class($this) . ': /api/support-request/reverse failed, http response: ' . htmlspecialchars(curl_getinfo($ch, CURLINFO_HTTP_CODE)));

    curl_close($ch);

    $result_array = json_decode($result, true);
    if (isset($result_array['errors'])&&count($result_array['errors'])) throw new ExceptionUser(get_class($this) . ': api/support-request/status failed, reason: ' . htmlspecialchars($result_array['errors'][0]['message']));
  }
}

?>
