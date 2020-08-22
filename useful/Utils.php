<?php

function adump($a=null,$text=false) {
  if ($text) {
    echo Application::get()->htmlspecialchars($text) .'<br />';
  }
  echo '<pre>';
  var_dump($a);
  echo '</pre><hr />';
}

function anyToString(&$any, $quote=true, $toStringParam=false) {
  if (is_object($any)) {
    $ret = $any->toString($toStringParam);
  } elseif (is_integer($any) || is_float($any) || is_double($any)) {
    $ret = $any;
  } elseif (is_bool($any)) {
    $ret = '';
  } else {
    $ret = $quote ? "'$any'" : $any;
  }
  return $ret;
}

function arrayToString($arr, $path='', $delimiter = '; '){
  $out = '';
  if (is_array($arr)){
    $index = 0;
    if (!count($arr)) $out .= $path.' = EMPTY'.$delimiter;
    foreach($arr as $key=>$val){
      $k = $key ? $key : $index;
      if (is_array($val)){ 
        $out .= arrayToString($val, $path.'['.$k.']');
      } elseif ($val instanceof Object) {
        $out .= $path.'['.$k.']='.get_class($val);
        $valValue = null;
        if (method_exists($val, 'print')){ 
          $valValue = $val->print();
        } elseif (method_exists($val, 'print')){ 
          $valValue = $val->print();
        } elseif (method_exists($val, 'printValue')){ 
          $valValue = $val->printValue();
        } elseif (method_exists($val, 'printMessage')){ 
          $valValue = $val->printMessage();
        } elseif (method_exists($val, '__toString')){ 
          $valValue = $val->__toString();
        }
        if (!is_null($valValue)){
          $out .= '('.$varValue.')';
        }
        $out .= $delimiter;
      } else {
        $out .= $path.'['.$k.']='.$val.$delimiter;
      }
      $index++;
    }
  } else {
    $out .= 'invalid Array - ';
    if ($arr instanceof Object) {
      $out .= get_class($arr);
    } elseif ($arr === true){
      $out .= 'TRUE';
    } elseif ($arr === false){
      $out .= 'FALSE';
    } elseif (is_null($arr)){
      $out .= 'NULL';
    } else {
      $out .= '*'.$arr.'*';
    }
    $out .= ' inserted';
  }
  return $out;
}

function &anyToArray(&$any) {
  if (!is_array($any)) {
    $array = array();
    $array[] =& $any;
    unset ($any);
    $any = $array;
  }
  return $any;
}

function concatElementAttributes($elementAttributes) {
  $ret = '';
  if (is_array($elementAttributes)) {
    foreach ($elementAttributes as $attribute => $value) {
      $ret .= ' '. Application::get()->htmlspecialchars($attribute) .'="'. Application::get()->htmlspecialchars($value) .'"';
    }
  }
  return $ret;
}

function ifsetor(&$var, $default=null) {
  return isset($var) ? $var : $default;
}

function parseNextActionFromRequest(& $nextAction, & $nextActionParams, $requestParamName='nextAction') {
  $string = Application::get()->request->getParams($requestParamName);
  parseNextActionFromString($nextAction, $nextActionParams, $string);
}

function parseNextActionFromString(& $nextAction, & $nextActionParams, $string) {
  $nA = explode('?', $string);
  $actionParams = array();
  if (is_array($nA)&&isset($nA[1])) {
    $paramsArray = explode('&',$nA[1]);
    foreach ($paramsArray as $p) {
      if ($p) {
        list($k,$v) = explode('=',$p);
        $actionParams[$k] = $v;
      }
    }
  }

  $nextAction = ifsetor($nA[0],null);
  $nextActionParams = $actionParams;
}

function randomString($len) {
  $pw = '';
  for($i=0;$i<$len;$i++) {
    switch(rand(1,5)) {
      case 1: $pw.=chr(rand(48,57));  break; //0-9
      case 2:
      case 3:
              $pw.=chr(rand(65,90));  break; //A-Z
      case 4:
      case 5: $pw.=chr(rand(97,122)); break; //a-z
    }
  }
  return $pw;
}

function generatePassword($length=9, $strength=0) {
  $vowels = 'aeuy';
  $consonants = 'bdghjmnpqrstvz';
  if ($strength & 1) {
    $consonants .= 'BDGHJLMNPQRSTVWXZ';
  }             
  if ($strength & 2) {
    $vowels .= "AEUY";  
  }                       
  if ($strength & 4) {      
    $consonants .= '23456789';    
  }                                 
  if ($strength & 8) {                
    $consonants .= '@#$%';                  
  }                                           

  $password = '';                               
  $alt = time() % 2;                              
  for ($i = 0; $i < $length; $i++) {                
    if ($alt == 1) {                                      
      $password .= $consonants[(rand() % strlen($consonants))];   
      $alt = 0;                                                         
    } else {                                                                
      $password .= $vowels[(rand() % strlen($vowels))];                             
      $alt = 1;                                                                           
    }                                                                                         
  }                                                                                             
  return $password;                                                                               
}

function spaceToNbsp($param) {
  return str_replace(array(' ','-'), array('&nbsp;','&#8209;'), $param);
}

/*
function getSubnetParams($subnet) {
  if (strpos($subnet,'/')===false) return false;

  $ip_arr = explode('/', $subnet);

  $dotcount = substr_count($ip_arr[0], ".");
  $padding = str_repeat(".0", 3 - $dotcount);
  $ip_arr[0] .= $padding;

  $bin = '';
  for($i=1;$i<=32;$i++) {
    $bin .= isset($ip_arr[1])&&($ip_arr[1] >= $i) ? '1' : '0';
  }
  $ip_arr[1] = bindec($bin);

  $ip = ip2long($ip_arr[0]);
  if (!$ip) return false;

  $nm = ip2long($ip_arr[1]);
  $nw = ($ip & $nm);
  $bc = $nw | (~$nm);

  $ret = array('ip_start'=>long2ip($nw + 1),'ip_end'=>long2ip($bc - 1));

  return $ret;
}

function isIpFromSubnet($ip, $subnet) {
  if (!$subnetParams = getSubnetParams($subnet)) return false;

  if ((ip2long($ip)>=ip2long($subnetParams['ip_start']))&&(ip2long($ip)<=ip2long($subnetParams['ip_end']))) 
    return true;
  else
    return false;
}*/

function isIpFromSubnet($ip, $subnet) {
  list($net, $netmask) = explode('/', $subnet);

  if ($netmask <= 0) return false; 

  $ip_binary_string = sprintf("%032b", ip2long($ip));
  $net_binary_string = sprintf("%032b", ip2long($net));

  return (substr_compare($ip_binary_string,$net_binary_string,0,$netmask) === 0);
} 

function removeDiakritics($string, $encoding=null) {
  $table = array(
      'Š'=>'S', 'š'=>'s', 'ß'=>'Ss','Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'Ć'=>'C', 'Ç'=>'C',
      'ć'=>'c', 'č'=>'c', 'ç'=>'c', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A',
      'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'È'=>'E', 'É'=>'E', 'Ě'=>'E',
      'Ê'=>'E', 'Ë'=>'E', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ě'=>'e', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
      'Ï'=>'I', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O',
      'Ö'=>'O', 'Ø'=>'O', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ð'=>'o', 'ő'=>'o',
      'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ů'=>'U', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ů'=>'u', 'ü'=>'u',
      'Ý'=>'Y', 'Þ'=>'B',
      'ñ'=>'n', 'Ý'=>'Y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'Ŕ'=>'R', 'Ř'=>'R', 'ŕ'=>'r', 'ř'=>'r', 'ť'=>'t',
      'Ť'=>'T', 'ň'=>'n', 'Ň'=>'n', 'Ď'=>'D', 'ď'=>'d', 'Ľ'=>'L', 'ľ'=>'l', 
      "'a"=>'a',"'e"=>'e',"'i"=>'i',"'o"=>'o',"'u"=>'u',"'y"=>'y',
      "'A"=>'A',"'E"=>'E',"'I"=>'I',"'O"=>'O',"'U"=>'U',"'Y"=>'Y',
      'ˇ'=>' ',"'"=>' ','´'=>' ','Â'=>'A',
      );

  if ($encoding) $string = iconv($encoding, 'utf-8', $string);
  $string = strtr($string, $table);
  if ($encoding) $string = iconv('utf-8', $encoding, $string);

  return $string;
}

function generateUuid($version = 4, $namespace = NULL, $name = NULL){
    switch ( intval($version) ){
        case 1:{
            $time = microtime(true) * 10000000 + 0x01b21dd213814000;
            $time = sprintf("%F", $time);
            preg_match("/^\d+/", $time, $time);
            $time = base_convert($time[0], 10, 16);
            $time = pack("H*", str_pad($time, 16, "0", STR_PAD_LEFT));
            $uuid = $time[4] . $time[5] . $time[6] . $time[7] . $time[2] . $time[3] . $time[0] . $time[1];
            $rand = "";
            for ( $i = 0 ; $i < 2 ; $i++ ) {
                $rand .= chr(mt_rand(0, 255));
            }
            $uuid = $uuid . $rand;
            $uuid[8] = chr(ord($uuid[8]) & 63 | 128);
            $uuid[6] = chr(ord($uuid[6]) & 15 | 16);
            if ( !function_exists('exec') ){
                $rand = "";
                for ( $i = 0 ; $i < 6 ; $i++ ) {
                    $rand .= chr(mt_rand(0, 255));
                }
                $rand = pack("C", ord($rand) | 1);
                $uuid = $uuid . $rand;
            }else{
                exec('/sbin/ifconfig | grep HWadd', $output);
                $output = isset($output[0]) ? $output[0] : NULL;
                if ( empty($output) ){
                    $rand = "";
                    for ( $i = 0 ; $i < 6 ; $i++ ) {
                        $rand .= chr(mt_rand(0, 255));
                    }
                    $rand = pack("C", ord($rand) | 1);
                    $uuid = $uuid . $rand;
                }else{
                    preg_match("/([0-9A-F]{2}[:-]){5}([0-9A-F]{2})/i", $output, $output);
                    $output = isset($output[0]) ? $output[0] : NULL;
                    if ( empty($output) ){
                        $rand = "";
                        for ( $i = 0 ; $i < 6 ; $i++ ) {
                            $rand .= chr(mt_rand(0, 255));
                        }
                        $rand = pack("C", ord($rand) | 1);
                        $uuid = $uuid . $rand;
                    }else{
                        $output = mb_strlen($output, 'UTF-8') == 6 ? $output : preg_replace('/^urn:uuid:/is', '', $output);
                        $output = preg_replace('/[^a-f0-9]/is', '', $output);
                        $output = mb_strlen($output, 'UTF-8') !== 12 ? NULL : pack("H*", $output);
                        $uuid = $uuid . $output;
                    }
                }
            }
            $uuid = bin2hex($uuid);
            return sprintf('%08s-%04s-%04x-%04x-%12s', mb_substr($uuid, 0, 8, 'UTF-8'), mb_substr($uuid, 8, 4, 'UTF-8'), (hexdec(mb_substr($uuid, 12, 4, 'UTF-8')) & 0x0fff) | 0x3000, (hexdec(mb_substr($uuid, 16, 4, 'UTF-8')) & 0x3fff) | 0x8000, mb_substr($uuid, 20, 12, 'UTF-8'));
        }break;
        case 2:{
            trigger_error("UUID v2 has not yet been implemented");
            return false;
        }break;
        case 3:{
            if ( empty($name)  ) {
                trigger_error("Invalid name");
                return false;
            }
            if ( empty($namespace) || preg_match('/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?' . '[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i', $namespace) !== 1 ) {
                trigger_error("Invalid namespace");
                return false;
            }
            $nhex = str_replace(array('-','{','}'), '', $namespace);
            $nstr = '';
            for( $i = 0 ; $i < mb_strlen($nhex, 'UTF-8') ; $i += 2 ) {
                $nstr .= chr(hexdec($nhex[$i] . $nhex[$i + 1]));
            }
            $hash = hash('md5', $nstr . $name);
            return sprintf('%08s-%04s-%04x-%04x-%12s', mb_substr($hash, 0, 8, 'UTF-8'), mb_substr($hash, 8, 4, 'UTF-8'), (hexdec(mb_substr($hash, 12, 4, 'UTF-8')) & 0x0fff) | 0x3000, (hexdec(mb_substr($hash, 16, 4, 'UTF-8')) & 0x3fff) | 0x8000, mb_substr($hash, 20, 12, 'UTF-8'));
        }break;
        case 4:{
            return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
        }break;
        case 5:{
            if ( empty($name)  ) {
                trigger_error("Invalid name");
                return false;
            }
            if ( empty($namespace) || preg_match('/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?' . '[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i', $namespace) !== 1 ) {
                trigger_error("Invalid namespace");
                return false;
            }
            $nhex = str_replace(array('-','{','}'), '', $namespace);
            $nstr = '';
            for( $i = 0 ; $i < mb_strlen($nhex, 'UTF-8') ; $i += 2 ) {
                $nstr .= chr(hexdec($nhex[$i] . $nhex[$i + 1]));
            }
            $hash = hash('sha1', $nstr . $name);
            return sprintf('%08s-%04s-%04x-%04x-%12s', mb_substr($hash, 0, 8, 'UTF-8'), mb_substr($hash, 8, 4, 'UTF-8'), (hexdec(mb_substr($hash, 12, 4, 'UTF-8')) & 0x0fff) | 0x5000, (hexdec(mb_substr($hash, 16, 4, 'UTF-8')) & 0x3fff) | 0x8000, mb_substr($hash, 20, 12, 'UTF-8'));
        }break;
        default:{
            trigger_error("Invalid UUID version");
            return false;
        }break;
    }
}

?>
