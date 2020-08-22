<?php

class CSOBGateway {
  private $_logFile;
  
  private $_language = 'CZ';
  private $_currency = 'CZK';
  private $_cartLabel = 'Vas nakup';
  
  private $_gatewayUrl;
  private $_gatewayKey;
  
  private $_closePayment = true;
  
  private $_merchantId;
  private $_merchantKey;
  private $_merchantKeyPassword;
  private $_merchantReturnMethod = 'POST';
  private $_merchantUrl;
  
  private $_paymentId;
  private $_paymentTimestamp;

  public function __construct($params=false) {
    if (is_array($params)) {
      if (isset($params['logFile'])) $this->_logFile = $params['logFile'];
      if (isset($params['language'])) $this->_language = $params['language'];
      if (isset($params['currency'])) $this->_currency = $params['currency'];
      if (isset($params['cartLabel'])) $this->_cartLabel = $params['cartLabel'];
      if (isset($params['gatewayUrl'])) $this->_gatewayUrl = $params['gatewayUrl'];
      if (isset($params['gatewayKey'])) $this->_gatewayKey = $params['gatewayKey'];
      if (isset($params['closePayment'])) $this->_closePayment = $params['closePayment'];
      if (isset($params['merchantId'])) $this->_merchantId = $params['merchantId'];
      if (isset($params['merchantKey'])) $this->_merchantKey = $params['merchantKey'];
      if (isset($params['merchantKeyPassword'])) $this->_merchantKeyPassword = $params['merchantKeyPassword'];
      if (isset($params['merchantReturnMethod'])) $this->_merchantReturnMethod = $params['merchantReturnMethod'];
      if (isset($params['merchantUrl'])) $this->_merchantUrl = $params['merchantUrl'];
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

  public function verifyResponse($response) {
    if (!isset($response['resultCode'])||is_null($response['resultCode'])) throw new ExceptionUser(get_class($this) . ': verify response, missing resultCode');
    
    $text = '';
    
    if (isset($response['payId'])&&!is_null($response['payId'])) $text .= $response['payId'] . '|';
    
    $text .= $response['dttm'] . '|' . $response['resultCode'] . '|' . $response['resultMessage'];

    if (isset($response['paymentStatus'])&&!is_null($response['paymentStatus'])) $text .= '|' . $response['paymentStatus'];
    if (isset($response['authCode'])&&!is_null($response['authCode'])) $text .= '|' . $response['authCode'];
    if (isset($response['merchantData'])&&!is_null($response['merchantData'])) $text .= '|' . $response['merchantData'];

    return $this->_verifyRaw($text, $response['signature']);
  }
  
  private function _verifyRaw($text, $signature) {
    $this->_createLogRecord(sprintf('Verifying data: %s', $text));
    
    $fp = fopen($this->_gatewayKey, 'r');
    if (!$fp) throw new ExceptionUser('Gateway key not found');
    $public = fread($fp, filesize($this->_gatewayKey));
    fclose($fp);
    
    $publicKeyId = openssl_get_publickey($public);
    $signature = base64_decode($signature);
    $res = openssl_verify($text, $signature, $publicKeyId);
    openssl_free_key($publicKeyId);
    
    return ($res != '1')?false:true;
  }
  
  public function signRequest($request) {
    $data2Sign = $request['merchantId'];
    if (isset($request['orderNo'])&&$request['orderNo']) $data2Sign .= '|' . $request['orderNo'];
    if (isset($request['dttm'])&&$request['dttm']) $data2Sign .= '|' . $request['dttm'];
    if (isset($request['payId'])&&$request['payId']) $data2Sign .= '|' . $request['payId'];
    if (isset($request['payOperation'])&&$request['payOperation']) $data2Sign .= '|' . $request['payOperation'];
    if (isset($request['payMethod'])&&$request['payMethod']) $data2Sign .= '|' . $request['payMethod'];
    if (isset($request['totalAmount'])&&$request['totalAmount']) $data2Sign .= '|' . $request['totalAmount'];
    if (isset($request['currency'])&&$request['currency']) $data2Sign .= '|'. $request['currency'];
    if (isset($request['closePayment'])&&$request['closePayment']) $data2Sign .= '|'. $request['closePayment'];
    if (isset($request['returnUrl'])&&$request['returnUrl']) $data2Sign .= '|' . $request['returnUrl'];
    if (isset($request['returnMethod'])&&$request['returnMethod']) $data2Sign .= '|'. $request['returnMethod'];
            
    if (isset($request['cart'])) {
      $cart2Sign = '|' . $request['cart'][0]['name'] . '|' . $request['cart'][0]['quantity'] . '|' . $request['cart'][0]['amount'] . '|'
            . $request['cart'][0]['description'];
    } else $cart2Sign = '';
    $data2Sign .= $cart2Sign;
            
    if (isset($request['description'])&&$request['description']) $data2Sign .= '|' . $request['description'];
    if (isset($request['merchantData'])&&$request['merchantData']) $data2Sign .= '|' . $request['merchantData'];
    if (isset($request['customerId'])&&$request['customerId']) $data2Sign .= '|' . $request['customerId'];
    if (isset($request['language'])) $data2Sign .= '|' . $request['language'];

    if ($data2Sign[strlen($data2Sign)-1] == '|') $data2Sign = substr($data2Sign, 0, strlen($data2Sign)-1);

    return $this->_signRaw($data2Sign);
  }
  
  /*public function signRequest($request) {
    $cart2Sign = $request["cart"][0]["name"] . "|" . $request["cart"][0]["quantity"] . "|" . $request["cart"][0]["amount"] . "|"
            . $request["cart"][0]["description"];

    $data2Sign = $request["merchantId"] . "|" .  $request["orderNo"] . "|" . $request["dttm"] . "|" . $request["payOperation"] . "|"
            . $request["payMethod"] . "|" . $request["totalAmount"] ."|". $request["currency"] ."|". $request["closePayment"]  . "|"
            . $request["returnUrl"] ."|". $request["returnMethod"] . "|" . $cart2Sign . "|" . $request["description"];

    if (isset($request['merchantData'])&&$request['merchantData']) $data2Sign .= "|" . $request['merchantData'];
    if (isset($request['customerId'])&&$request['customerId']) $data2Sign .= "|" . $request['customerId'];

    $data2Sign .= "|" . $request["language"];

    if ($data2Sign[strlen($data2Sign)-1] == '|') $data2Sign = substr($data2Sign, 0, strlen($data2Sign)-1);
    error_log($data2Sign);die;

    return $this->_signRaw($data2Sign);
  }*/
  
  private function _signRaw($text) {
    $fp = fopen($this->_merchantKey, 'r');
    if (!$fp) throw new ExceptionUser('Merchant key not found');
    $private = fread($fp, filesize($this->_merchantKey));
    fclose($fp);
    
    $privateKeyId = openssl_get_privatekey($private, $this->_merchantKeyPassword);
    openssl_sign($text, $signature, $privateKeyId);
    $signature = base64_encode($signature);
    openssl_free_key($privateKeyId);
    
    $this->_createLogRecord(sprintf('Signing data: %s -> %s', $text, $signature));
    
    return $signature;
  }
  
  public function ping($method='GET') {
    $this->_paymentTimestamp = (new DateTime)->format('YmdHis');
    
    $url = $this->_gatewayUrl . '/echo/';
    
    $data = array (
                    "merchantId"    => $this->_merchantId,
                    "dttm"          => $this->_paymentTimestamp,
                  );
    $data['signature'] = $this->signRequest($data);
    
    $this->_createLogRecord(sprintf('Echo (%s): %s', $method, var_export($data, true)));
    
    if (!strcmp($method,'GET')) {
      $url .= $this->_merchantId . '/' . $this->_paymentTimestamp . "/" . urlencode($data['signature']);
    
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    } else {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Accept: application/json;charset=UTF-8'));
    $result = curl_exec($ch);
    
    $this->_createLogRecord(sprintf('Echo result: %s', $result));

    if (curl_errno($ch)) throw new ExceptionUser(get_class($this) . ': echo failed, reason: ' . htmlspecialchars(curl_error($ch)));
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) throw new ExceptionUser(get_class($this) . ': echo, http response: ' . htmlspecialchars(curl_getinfo($ch, CURLINFO_HTTP_CODE)));

    curl_close($ch);
    
    $result_array = json_decode($result, true);
    if (!$this->verifyResponse($result_array)) throw new ExceptionUser(get_class($this). ': echo failed, unable to verify signature');

    if ($result_array['resultCode'] != '0') throw new ExceptionUser(get_class($this) . ': echo failed, reason: ' . htmlspecialchars($result_array['resultMessage']));

    return $result_array;
  }
  
  public function createPayment($reference, $amount, $description) {
    $this->_paymentTimestamp = (new DateTime)->format('YmdHis');
    $amount = $amount * 100;
    
    $data = array (
                    "merchantId"    => $this->_merchantId,
                    "orderNo"       => $reference,
                    "dttm"          => $this->_paymentTimestamp,
                    "payOperation"  => 'payment',
                    "payMethod"     => 'card',
                    "totalAmount"   => $amount,
                    "currency"      => $this->_currency,
                    "closePayment"  => $this->_closePayment?'true':'false',
                    "returnUrl"     => $this->_merchantUrl,
                    "returnMethod"  => $this->_merchantReturnMethod,
                    "cart"          => array(
                            0 => array(
                                    "name"          => $this->_cartLabel,
                                    "quantity"      => 1,
                                    "amount"        => $amount,
                                    "description"   => mb_substr(trim($description), 0, 37, 'utf-8') . '...',
                            ),
                          ),
                    "description"   => $description,
                    "merchantData"  => null,
                    'language'      => $this->_language,
                  );
    $data['signature'] = $this->signRequest($data);

    $this->_createLogRecord(sprintf('Creating payment: %s', var_export($data, true)));
    
    $ch = curl_init($this->_gatewayUrl . '/payment/init/');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Accept: application/json;charset=UTF-8'));
    $result = curl_exec($ch);
    
    $this->_createLogRecord(sprintf('Payment creation result: %s', $result));

    if (curl_errno($ch)) throw new ExceptionUser(get_class($this) . ': payment/init failed, reason: ' . htmlspecialchars(curl_error($ch)));
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) throw new ExceptionUser(get_class($this) . ': payment/init failed, http response: ' . htmlspecialchars(curl_getinfo($ch, CURLINFO_HTTP_CODE)));

    curl_close($ch);
    
    $result_array = json_decode($result, true);
    if (!$this->verifyResponse($result_array)) throw new ExceptionUser(get_class($this). ': payment/init failed, unable to verify signature');

    if ($result_array['resultCode'] != '0') throw new ExceptionUser(get_class($this) . ': payment/init failed, reason: ' . htmlspecialchars($result_array ['resultMessage']));
    
    $this->_paymentId = $result_array['payId'];
  }
  
  public function getPaymentProcessUrl() {
    $url = $this->_gatewayUrl . '/payment/process/';
    
    $text =  $this->_merchantId . "|" . $this->_paymentId . "|" . $this->_paymentTimestamp;
    $signature = $this->_signRaw($text);
  
    $url .= $this->_merchantId . '/' . $this->_paymentId . '/' . $this->_paymentTimestamp . "/" . urlencode($signature);
    
    $this->_createLogRecord(sprintf('Creating URL for payment: %s', $url));
    
    return $url;
  }
  
  public function getPaymentStatus($paymentId) {
    $this->_paymentTimestamp = (new DateTime)->format ( "YmdHis" );
    
    $url = $this->_gatewayUrl . '/payment/status/';
    
    $text =  $this->_merchantId . "|" . $paymentId . "|" . $this->_paymentTimestamp;
    $signature = $this->_signRaw($text);
  
    $url .= $this->_merchantId . '/' . $paymentId . '/' . $this->_paymentTimestamp . "/" . urlencode($signature);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Accept: application/json;charset=UTF-8'));
    $result = curl_exec($ch);
    
    $this->_createLogRecord(sprintf('Payment status result: %s', $result));

    if (curl_errno($ch)) throw new ExceptionUser(get_class($this) . ': payment/status failed, reason: ' . htmlspecialchars(curl_error($ch)));
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) throw new ExceptionUser(get_class($this) . ': payment/status failed, http response: ' . htmlspecialchars(curl_getinfo($ch, CURLINFO_HTTP_CODE)));

    curl_close($ch);
    
    $result_array = json_decode($result, true);
    if (!$this->verifyResponse($result_array)) throw new ExceptionUser(get_class($this). ': payment/status failed, unable to verify signature');

    if ($result_array['resultCode'] != '0') throw new ExceptionUser(get_class($this) . ': payment/status failed, reason: ' . htmlspecialchars($result_array['resultMessage']));
    
    $payment = array(
      'paymentId'     => $result_array['payId'],
      'authCode'      => ifsetor($result_array['authCode']),
      'payed'         => in_array(ifsetor($result_array['paymentStatus'],-1),array(4,7,8)),
      'resultMessage' => $result_array['resultMessage'],
      'paymentStatus' => $result_array['paymentStatus'],
      'notFinished'   => in_array($result_array['paymentStatus'], array(1,2)),
    );

    return $payment;
  }
  
  public function closePayment($response) {
    $this->_createLogRecord(sprintf('Payment close result: %s', var_export($response,true)));
    
    if (!$this->verifyResponse($response)) throw new ExceptionUser(get_class($this). ': payment/finish failed, unable to verify signature');
    
    if (!in_array($response['resultCode'], array('0','130'))) throw new ExceptionUser(get_class($this) . ': payment/finish failed, reason: ' . htmlspecialchars($response['resultMessage']));
    
    $payment = array('paymentId'=>$response['payId'],'authCode'=>ifsetor($response['authCode']),'payed'=>in_array(ifsetor($response['paymentStatus'],-1),array(4,7,8)));
    
    return $payment;
  }
  
  public function reversePayment($paymentId) {
    $this->_paymentTimestamp = (new DateTime)->format('YmdHis');
    
    $data = array (
                    "merchantId"    => $this->_merchantId,
                    "dttm"          => $this->_paymentTimestamp,
                    "payId"         => $paymentId,
                  );
    $text =  $this->_merchantId . "|" . $paymentId . "|" . $this->_paymentTimestamp;
    $data['signature'] = $this->_signRaw($text);

    $this->_createLogRecord(sprintf('Reversing payment: %s', var_export($data, true)));
    
    $ch = curl_init($this->_gatewayUrl . '/payment/reverse/');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Accept: application/json;charset=UTF-8'));
    $result = curl_exec($ch);
    
    $this->_createLogRecord(sprintf('Payment reverse result: %s', $result));

    if (curl_errno($ch)) throw new ExceptionUser(get_class($this) . ': payment/reverse failed, reason: ' . htmlspecialchars(curl_error($ch)));
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) throw new ExceptionUser(get_class($this) . ': payment/reverse failed, http response: ' . htmlspecialchars(curl_getinfo($ch, CURLINFO_HTTP_CODE)));

    curl_close($ch);
    
    $result_array = json_decode($result, true);
    if (!$this->verifyResponse($result_array)) throw new ExceptionUser(get_class($this). ': payment/reverse failed, unable to verify signature');

    if ($result_array['resultCode'] != '0') throw new ExceptionUser(get_class($this) . ': payment/reverse failed, reason: ' . htmlspecialchars($result_array ['resultMessage']));
  }
  
  public function refundPayment($paymentId) {
    $this->_paymentTimestamp = (new DateTime)->format('YmdHis');
    
    $data = array (
                    "merchantId"    => $this->_merchantId,
                    "dttm"          => $this->_paymentTimestamp,
                    "payId"         => $paymentId,
                  );
    $text =  $this->_merchantId . "|" . $paymentId . "|" . $this->_paymentTimestamp;
    $data['signature'] = $this->_signRaw($text);

    $this->_createLogRecord(sprintf('Refunding payment: %s', var_export($data, true)));
    
    $data = json_encode($data);
    $ch = curl_init($this->_gatewayUrl . '/payment/refund/');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: '.strlen($data),'Accept: application/json;charset=UTF-8'));
    $result = curl_exec($ch);
    
    $this->_createLogRecord(sprintf('Payment refund result: %s', $result));

    if (curl_errno($ch)) throw new ExceptionUser(get_class($this) . ': payment/refund failed, reason: ' . htmlspecialchars(curl_error($ch)));
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) throw new ExceptionUser(get_class($this) . ': payment/refund failed, http response: ' . htmlspecialchars(curl_getinfo($ch, CURLINFO_HTTP_CODE)));

    curl_close($ch);
    
    $result_array = json_decode($result, true);
    if (!$this->verifyResponse($result_array)) throw new ExceptionUser(get_class($this). ': payment/refund failed, unable to verify signature');

    if ($result_array['resultCode'] != '0') throw new ExceptionUser(get_class($this) . ': payment/refund failed, reason: ' . htmlspecialchars($result_array ['resultMessage']));
  }
}

?>
