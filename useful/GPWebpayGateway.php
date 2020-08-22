<?php

class GPWebpayGateway {
  private $_logFile;

  private $_provider = '0110'; // GPwebpay (KB smartpay)
  
  private $_language = 'CZ';
  private $_currency = 203; // CZK
  
  private $_gatewayUrl;
  private $_gatewayUrlWS;
  private $_gatewayKey;
  
  private $_merchantId;
  private $_merchantKey;
  private $_merchantKeyPassword;
  private $_merchantUrl;

  public function __construct($params=false) {
    if (is_array($params)) {
      if (isset($params['logFile'])) $this->_logFile = $params['logFile'];
      if (isset($params['language'])) $this->_language = $params['language'];
      if (isset($params['currency'])) $this->_currency = $params['currency'];
      if (isset($params['gatewayUrlWS'])) $this->_gatewayUrlWS = $params['gatewayUrlWS'];
      if (isset($params['gatewayUrl'])) $this->_gatewayUrl = $params['gatewayUrl'];
      if (isset($params['gatewayKey'])) $this->_gatewayKey = $params['gatewayKey'];
      if (isset($params['merchantId'])) $this->_merchantId = $params['merchantId'];
      if (isset($params['merchantKey'])) $this->_merchantKey = $params['merchantKey'];
      if (isset($params['merchantKeyPassword'])) $this->_merchantKeyPassword = $params['merchantKeyPassword'];
      if (isset($params['merchantUrl'])) $this->_merchantUrl = $params['merchantUrl'];
    }
  }
  
  private function _createLogRecord($msg) {
    if ($this->_logFile&&$msg) {
      $line = date('Y-m-d-H-i-s').': '.$msg."\n";
      file_put_contents($this->_logFile, $line, FILE_APPEND | LOCK_EX);
    }
  }

  public function verifyResponse($response) {
    $text = $response['OPERATION'] . '|' . $response['ORDERNUMBER'] ;

    if (isset($response['PRCODE'])&&!is_null($response['PRCODE'])) $text .= '|' . $response['PRCODE'];
    if (isset($response['SRCODE'])&&!is_null($response['SRCODE'])) $text .= '|' . $response['SRCODE'];
    if (isset($response['RESULTTEXT'])&&!is_null($response['RESULTTEXT'])) $text .= '|' . $response['RESULTTEXT'];
    if (isset($response['TOKEN'])&&!is_null($response['TOKEN'])) $text .= '|' . $response['TOKEN'];
    if (isset($response['ACCODE'])&&!is_null($response['ACCODE'])) $text .= '|' . $response['ACCODE'];

    return $this->_verifyRaw($text, $response['DIGEST']);
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
    $data2Sign = $request['MERCHANTNUMBER'];
    if (isset($request['OPERATION'])&&$request['OPERATION']) $data2Sign .= '|' . $request['OPERATION'];
    if (isset($request['ORDERNUMBER'])&&$request['ORDERNUMBER']) $data2Sign .= '|' . $request['ORDERNUMBER'];
    if (isset($request['AMOUNT'])&&$request['AMOUNT']) $data2Sign .= '|' . $request['AMOUNT'];
    if (isset($request['CURRENCY'])&&$request['CURRENCY']) $data2Sign .= '|'. $request['CURRENCY'];
    if (isset($request['DEPOSITFLAG'])&&$request['DEPOSITFLAG']) $data2Sign .= '|'. $request['DEPOSITFLAG'];
    if (isset($request['URL'])&&$request['URL']) $data2Sign .= '|' . $request['URL'];
    if (isset($request['DESCRIPTION'])&&$request['DESCRIPTION']) $data2Sign .= '|' . $request['DESCRIPTION'];

    if ($data2Sign[strlen($data2Sign)-1] == '|') $data2Sign = substr($data2Sign, 0, strlen($data2Sign)-1);

    return $this->_signRaw($data2Sign);
  }

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

  public function createPayment($reference, $amount, $description) {
    $amount = $amount * 100;
    
    $data = array (
                    "MERCHANTNUMBER"    => $this->_merchantId,
                    "OPERATION"         => 'CREATE_ORDER',
                    "ORDERNUMBER"       => $reference,
                    "AMOUNT"            => $amount,
                    "CURRENCY"          => $this->_currency,
                    "DEPOSITFLAG"       => 1,
                    "URL"               => $this->_merchantUrl,
                    "DESCRIPTION"       => $description,
                    'LANG'              => $this->_language,
                  );
    $data['DIGEST'] = $this->signRequest($data);

    $this->_createLogRecord(sprintf('Creating payment URL: %s', var_export($data, true)));

    $url = $this->_gatewayUrl . '?' . http_build_query($data);
    return $url;
  }

  private function _getXMLResponse($response) {
    $responseStripped = str_ireplace(['SOAPENV:','SOAP:','NS3:','NS4:'], '', substr($response, strpos($response,'<?')));
    $responseDOM = simplexml_load_string($responseStripped);

    return $responseDOM;
  }
  
  public function getPaymentStatus($paymentId) {
    $messageId = $this->_merchantId . '+' . $paymentId . '+' . date('YmdHis');

    $rawData =  $messageId . '|' . $this->_provider . '|' . $this->_merchantId . '|' . $paymentId;
    $digest = $this->_signRaw($rawData);

    $xmlTemplate = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="http://gpe.cz/pay/pay-ws/proc/v1" xmlns:type="http://gpe.cz/pay/pay-ws/proc/v1/type">
   <soapenv:Header/>
   <soapenv:Body>
      <v1:getPaymentStatus>
         <v1:paymentStatusRequest>
            <type:messageId>%s</type:messageId>
            <type:provider>%s</type:provider>
            <type:merchantNumber>%s</type:merchantNumber>
            <type:paymentNumber>%s</type:paymentNumber>
            <type:signature>%s</type:signature>
        </v1:paymentStatusRequest>
      </v1:getPaymentStatus>
   </soapenv:Body>
</soapenv:Envelope>';
    $header[] = "Content-Type: text/xml";
    $xml = sprintf($xmlTemplate, $messageId, $this->_provider, $this->_merchantId, $paymentId, $digest);

    $this->_createLogRecord(sprintf('Payment status XML: %s', $xml));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->_gatewayUrlWS);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST,           1 );
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $xml);
    $result = curl_exec($ch);
    
    $this->_createLogRecord(sprintf('Payment status result: %s', $result));

    if (curl_errno($ch)) throw new ExceptionUser(get_class($this) . ': payment/status failed, reason: ' . htmlspecialchars(curl_error($ch)));

    curl_close($ch);

    $resultXML = $this->_getXMLResponse($result);
    if ($resultXML->Body->Fault) {
      $this->_createLogRecord(sprintf('Payment status error: %s (PRCODE=%s,SRCODE=%s)', $resultXML->Body->Fault->faultstring,
        $resultXML->Body->Fault->detail->serviceException->primaryReturnCode, $resultXML->Body->Fault->detail->serviceException->secondaryReturnCode));

      $payment = array(
        'paymentId'     => $paymentId,
        'payed'         => false,
        'paymentStatus' => -1,
        'resultMessage' => sprintf('(PRCODE=%s,SRCODE=%s)', $resultXML->Body->Fault->detail->serviceException->primaryReturnCode, $resultXML->Body->Fault->detail->serviceException->secondaryReturnCode),
        'notFinished'   => false,
      );
    } else {
      $responseData = $resultXML->Body->getPaymentStatusResponse->paymentStatusResponse;

      $responseRaw = $responseData->messageId . '|' . $responseData->state . '|' . $responseData->status . '|' .$responseData->subStatus;
      $responseDigest = $responseData->signature;
      if (!$this->_verifyRaw($responseRaw, $responseDigest)) throw new ExceptionUser(get_class($this). ': payment/status failed, unable to verify signature');

      $payment = array(
        'paymentId'     => $paymentId,
        'payed'         => in_array($responseData->state,array(4,7,8)),
        'paymentStatus' => sprintf('%s', $responseData->state),
        'resultMessage' => sprintf('%s (%s)', $responseData->status, $responseData->subStatus),
        'notFinished'   => in_array($responseData->state,array(1,2,3)),
      );
    }

    return $payment;
  }
  
  public function closePayment($response) {
    $this->_createLogRecord(sprintf('Payment close result: %s', var_export($response,true)));
    
    if (!$this->verifyResponse($response)) throw new ExceptionUser(get_class($this). ': payment/finish failed, unable to verify signature');
    
    $payment = array('paymentId'=>$response['ORDERNUMBER'],'authCode'=>ifsetor($response['ACCODE']),'payed'=>($response['PRCODE']==0)&&($response['SRCODE']==0));
    
    return $payment;
  }
  
  public function reversePaymentAuthorization($paymentId) {
    $messageId = $this->_merchantId . '+' . $paymentId . '+' . date('YmdHis');

    $rawData =  $messageId . '|' . $this->_provider . '|' . $this->_merchantId . '|' . $paymentId;
    $digest = $this->_signRaw($rawData);

    $xmlTemplate = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="http://gpe.cz/pay/pay-ws/proc/v1" xmlns:type="http://gpe.cz/pay/pay-ws/proc/v1/type">
   <soapenv:Header/>
   <soapenv:Body>
      <v1:processAuthorizationReverse>
         <v1:authorizationReverseRequest>
            <type:messageId>%s</type:messageId>
            <type:provider>%s</type:provider>
            <type:merchantNumber>%s</type:merchantNumber>
            <type:paymentNumber>%s</type:paymentNumber>
            <type:signature>%s</type:signature>
        </v1:authorizationReverseRequest>
      </v1:processAuthorizationReverse>
   </soapenv:Body>
</soapenv:Envelope>';
    $header[] = "Content-Type: text/xml";
    $xml = sprintf($xmlTemplate, $messageId, $this->_provider, $this->_merchantId, $paymentId, $digest);

    $this->_createLogRecord(sprintf('Payment reverse authorization XML: %s', $xml));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->_gatewayUrlWS);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST,           1 );
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $xml);
    $result = curl_exec($ch);

    $this->_createLogRecord(sprintf('Payment reverse authorization result: %s', $result));

    if (curl_errno($ch)) throw new ExceptionUser(get_class($this) . ': payment/reverse authorization failed, reason: ' . htmlspecialchars(curl_error($ch)));
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) throw new ExceptionUser(get_class($this) . ': payment/reverse authorization failed, http response: ' . htmlspecialchars(curl_getinfo($ch, CURLINFO_HTTP_CODE)));

    curl_close($ch);

    $resultXML = $this->_getXMLResponse($result);
  }

  public function reversePaymentCapture($paymentId) {
    $messageId = $this->_merchantId . '+' . $paymentId . '+' . date('YmdHis');
    $captureNumber = 1;

    $rawData =  $messageId . '|' . $this->_provider . '|' . $this->_merchantId . '|' . $paymentId . '|' . $captureNumber;
    $digest = $this->_signRaw($rawData);

    $xmlTemplate = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="http://gpe.cz/pay/pay-ws/proc/v1" xmlns:type="http://gpe.cz/pay/pay-ws/proc/v1/type">
   <soapenv:Header/>
   <soapenv:Body>
      <v1:processCaptureReverse>
         <v1:captureReverseRequest>
            <type:messageId>%s</type:messageId>
            <type:provider>%s</type:provider>
            <type:merchantNumber>%s</type:merchantNumber>
            <type:paymentNumber>%s</type:paymentNumber>
            <type:captureNumber>%s</type:captureNumber>
            <type:signature>%s</type:signature>
        </v1:captureReverseRequest>
      </v1:processCaptureReverse>
   </soapenv:Body>
</soapenv:Envelope>';
    $header[] = "Content-Type: text/xml";
    $xml = sprintf($xmlTemplate, $messageId, $this->_provider, $this->_merchantId, $paymentId, $captureNumber, $digest);

    $this->_createLogRecord(sprintf('Payment reverse capture XML: %s', $xml));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->_gatewayUrlWS);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST,           1 );
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $xml);
    $result = curl_exec($ch);

    $this->_createLogRecord(sprintf('Payment reverse capture result: %s', $result));

    if (curl_errno($ch)) throw new ExceptionUser(get_class($this) . ': payment/reverse capture failed, reason: ' . htmlspecialchars(curl_error($ch)));
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) throw new ExceptionUser(get_class($this) . ': payment/reverse capture failed, http response: ' . htmlspecialchars(curl_getinfo($ch, CURLINFO_HTTP_CODE)));

    curl_close($ch);

    $resultXML = $this->_getXMLResponse($result);
  }
  
  public function refundPayment($paymentId, $amount) {
    $messageId = $this->_merchantId . '+' . $paymentId . '+' . date('YmdHis');
    $amount = $amount*100;

    $rawData =  $messageId . '|' . $this->_provider . '|' . $this->_merchantId . '|' . $paymentId . '|' . $amount;
    $digest = $this->_signRaw($rawData);

    $xmlTemplate = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="http://gpe.cz/pay/pay-ws/proc/v1" xmlns:type="http://gpe.cz/pay/pay-ws/proc/v1/type">
   <soapenv:Header/>
   <soapenv:Body>
      <v1:processRefund>
         <v1:refundRequest>
           <type:messageId>%s</type:messageId>
            <type:provider>%s</type:provider>
            <type:merchantNumber>%s</type:merchantNumber>
            <type:paymentNumber>%s</type:paymentNumber>
            <type:amount>%s</type:amount>
            <type:signature>%s</type:signature>
         </v1:refundRequest>
      </v1:processRefund>
   </soapenv:Body>
</soapenv:Envelope>';
    $header[] = "Content-Type: text/xml";
    $xml = sprintf($xmlTemplate, $messageId, $this->_provider, $this->_merchantId, $paymentId, $amount, $digest);

    $this->_createLogRecord(sprintf('Payment refund XML: %s', $xml));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->_gatewayUrlWS);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST,           1 );
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $xml);
    $result = curl_exec($ch);

    $this->_createLogRecord(sprintf('Payment refund result: %s', $result));

    if (curl_errno($ch)) throw new ExceptionUser(get_class($this) . ': payment/refund failed, reason: ' . htmlspecialchars(curl_error($ch)));
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) throw new ExceptionUser(get_class($this) . ': payment/refund failed, http response: ' . htmlspecialchars(curl_getinfo($ch, CURLINFO_HTTP_CODE)));

    curl_close($ch);

    $resultXML = $this->_getXMLResponse($result);
  }
}

?>
