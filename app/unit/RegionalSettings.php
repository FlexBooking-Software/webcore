<?php

class RegionalSettings {
  protected $_numberDelimiterDecimal = ',';
  protected $_numberDelimiterThousand = ' ';
  protected $_numberMinusSign = '-';
  protected $_numberIntegralPlaces = 10;
  protected $_numberDecimalPlaces = 0;
  
  public function __construct($params=array()) {
    if (isset($params['numberDelimiterDecimal'])) { $this->_numberDelimiterDecimal = $params['numberDelimiterDecimal']; }
    if (isset($params['numberDelimiterThousand'])) { $this->_numberDelimiterThousand = $params['numberDelimiterThousand']; }
    if (isset($params['numberMinusSign'])) { $this->_numberMinusSign = $params['numberMinusSign']; }
    if (isset($params['numberIntegralPlaces'])) { $this->_numberIntegralPlaces = $params['numberIntegralPlaces']; }
    if (isset($params['numberDecimalPlaces'])) { $this->_numberDecimalPlaces = $params['numberDecimalPlaces']; }
  }

  public function getDecimalPlaces() { return $this->_numberDecimalPlaces; }

  private function _getDefaultYear($day, $month) {
    $year = date('Y-m-d') > sprintf('%04d-%02d-%0d', date('Y'), $month, $day) ? date('Y') + 1 : date('Y');
    return $year;
  }

  public function checkNumber($number) {
    $ret = is_numeric($number);
    return $ret;
  }

  public function checkHumanNumber($number, $integralPlaces, $decimalPlaces=0) {
    $ret = false;
    if ($decimalPlaces) {
      $numberFormat = sprintf('/^[ ]*[+-]{0,1}[ ]*[0-9]*(%s[0-9]{3})*(%s[0-9]{1,%d}){0,1}[ ]*$/',
          $this->_numberDelimiterThousand, $this->_numberDelimiterDecimal, $decimalPlaces);
    } else {
      $numberFormat = sprintf('/^[ ]*[+-]{0,1}[ ]*[0-9]*(%s[0-9]{3})*[ ]*$/',
          $this->_numberDelimiterThousand);
    }
    $count = $this->pregMatch($numberFormat, $number);
    if ($count) {
      $parts = explode($this->_numberDelimiterDecimal, $number);
      $integralPart = str_replace(array(' ','+','-',$this->_numberDelimiterThousand), '', $parts[0]);
      $ret = strlen($integralPart) <= $integralPlaces;
    }
    return $ret;
  }

  public function convertNumberToHuman($number, $decimalPlaces=null, $delimiterThousand=null) {
    if (is_null($number)||($number === '')) {
      $ret = '';
    } else {
      $toNbsp = false;
      $toMinus = false;
      $thousand = $delimiterThousand?$delimiterThousand:$this->_numberDelimiterThousand;
      $minus = $this->_numberMinusSign;
      if ($thousand == '&nbsp;') {
        $toNbsp = true;
        $thousand = ' ';
      }

      if ($number < 0) {
        $toMinus = true;
        $number *= -1;
      }

      if ($decimalPlaces === null) {
        $decimalPlaces = $this->_numberDecimalPlaces;
      }

      if ($decimalPlaces === 'auto') {
        $splitedNumber = explode ('.', floatval($number));
        $decimalPlaces = isset($splitedNumber[1]) ? strlen($splitedNumber[1]) : 0;
      }

      $ret = number_format($number, $decimalPlaces, $this->_numberDelimiterDecimal, $thousand);

      if ($toNbsp) { 
        $ret = str_replace(' ', '&nbsp;', $ret);
      }
      if ($toMinus) {
        $ret = $minus . $ret;
      }
    }

    return $ret;
  }

  public function convertHumanToNumber($number, $integralPlaces, $decimalPlaces=0) {
    if (is_null($number)||($number === '')) {
      $ret = '';
    } else {
      if (!$this->checkHumanNumber($number, $integralPlaces, $decimalPlaces)) {
        throw new ExceptionUserTextStorage('error.regionalSettings_convertHumanToNumberNotNumber');
      }
      $parts = explode($this->_numberDelimiterDecimal, $number);
      $integralPart = str_replace(array(' ',$this->_numberDelimiterThousand), '', $parts[0]);

      $ret = sprintf('%s', $integralPart);
      if (isset($parts[1])) {
        $ret .= '.'.sprintf('%0'.strlen($parts[1]).'d', $parts[1]);
      }
    }
    return $ret;
  }

  public function checkDateTime($datetime) {
    $ret = false;
    $arr = explode(' ', $datetime);
    $ret = (count($arr)==2) && $this->checkDate($arr[0]) && $this->checkTime($arr[1]);

    return $ret;
  }

  public function checkHumanDateTime($datetime, $formatDate='d.m.y', $formatTime='h:m', $delimiter=' ', $reverse=false) {
    $ret = false;
    $parts = explode($delimiter,trim($datetime));
    $ret = count($parts)==2;
    if ($ret) {
      if ($reverse) {
        $humanTime = $parts[0];
        $humanDate = $parts[1];
      } else {
        $humanDate = $parts[0];
        $humanTime = $parts[1];
      }
      $ret = $this->checkHumanDate($humanDate, $formatDate)&&$this->checkHumanTime($humanTime, $formatTime);
    }
    return $ret;
  }

  public function convertDateTimeToHuman($datetime, $formatDate='d.m.y', $formatTime='h:m', $reverse=false) {
    if (is_null($datetime)||($datetime === '')) {
      $ret = '';
    } else {
      list($date, $time) = explode(' ',$datetime);
      $humanDate = $this->convertDateToHuman($date, $formatDate);
      $humanTime = $this->convertTimeToHuman($time, $formatTime);
      $ret = trim($reverse ? "$humanTime $humanDate" : "$humanDate $humanTime");
    }
    return $ret;
  }

  public function convertHumanToDateTime($datetime, $formatDate='d.m.y', $formatTime='h:m', $delimiter=' ', $reverse=false) {
    if (is_null($datetime)||($datetime === '')) {
      $ret = '';
    } else {
      if (!$this->checkHumanDateTime($datetime, $formatDate, $formatTime, $delimiter, $reverse)) {
        throw new ExceptionUserTextStorage('error.regionalSettings_convertHumanToDateTimeNotDateTime');
      }
      $parts = explode($delimiter,trim($datetime));
      if ($reverse) {
        $humanTime = $parts[0];
        $humanDate = $parts[1];
      } else {
        $humanDate = $parts[0];
        $humanTime = $parts[1];
      }
      $date = $this->convertHumanToDate($humanDate, $formatDate);
      $time = $this->convertHumanToTime($humanTime, $formatTime);
      $ret = "$date $time";
    }

    return $ret;
  }

  public function checkDate($date) {
    $app = Application::get();
    $ret = false;
    $count = $this->pregMatch('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/', $date);
    if ($count) {
      list($year, $month, $day) = explode('-', $date);
      $ret = checkdate($month, $day, $year);
    }
    return $ret;
  }

  public function checkHumanDate($date, $format='d.m.y') {
    $app = Application::get();
    $ret = false;
    $reg = array(
        'd.m.y' => '/^[ ]*[0-9]{1,2}\.[ ]*[0-9]{1,2}\.[ ]*[0-9]{2,4}[ ]*$/',
        'd.m.'  => '/^[ ]*[0-9]{1,2}\.[ ]*[0-9]{1,2}\.[ ]*$/',
        );
    if (!in_array($format,array_keys($reg))) throw new ExceptionUserTextStorage('error.regionalSettings_checkHumanDateUnsupportedFormat');
    $count = $this->pregMatch($reg[$format], $date);
    if ($count) {
      $parts = explode('.', $date);
      switch ($format) {
        case 'd.m.y':
          $month = $parts[1]; $day = $parts[0]; $year = $parts[2];
          break;
        case 'd.m.':
          $month = $parts[1]; $day = $parts[0]; $year = $this->_getDefaultYear($day, $month);
          break;
      }
      $ret = checkdate(intval($month), intval($day), intval($year));
    }
    return $ret;
  }

  public function convertDateToHuman($date, $format='D.M.Y') {
    if (is_null($date)||($date==='')) {
      $ret = '';
    } else {
      if (!$this->checkDate($date)) {
        throw new ExceptionUserTextStorage('error.regionalSettings_convertDateToHumanNotDate');
      }
      list ($year, $month, $day) = explode('-', $date);
      switch ($format) {
        case 'y-m-d':
          $ret = "$year-$month-$day";
          break;
        case 'D.M.Y':
          $ret = "$day.$month.$year";
          break;
        case 'D.M.':
          $ret = "$day.$month.";
          break;
        case 'd.m.Y':
          $day = $day*1;
          $month = $month*1;
          $ret = "$day.$month.$year";
          break;
        case 'd.m.y':
          $day = $day*1;
          $month = $month*1;
          $ret = "$day.$month.$year";
          break;
        case 'd.m.':
          $day = $day*1;
          $month = $month*1;
          $ret = "$day.$month.";
          break;
        default:
          throw new ExceptionUserTextStorage('error.regionalSettings_convertDateToHumanUnknownFormat');
      }
    }
    return $ret;
  }

  public function convertHumanToDate($date, $format='d.m.y') {
    if (is_null($date)||($date === '')) {
      $ret = null;
    } else {
      if (!$this->checkHumanDate($date, $format)) {
        throw new ExceptionUserTextStorage('error.regionalSettings_convertHumanToDateNotDate');
      }
      switch (strtolower($format)) {
        case 'y-m-d':
          $parts = explode('-', $date);
          $day = $parts[2]; $month = $parts[1]; $year = $parts[0];
          break;
        case 'd.m.y':
          $parts = explode('.', $date);
          $day = $parts[0]; $month = $parts[1]; $year = $parts[2];
          break;
        case 'd.m.':
          $parts = explode('.', $date);
          $day = $parts[0]; $month = $parts[1]; $year = $this->_getDefaultYear($day, $month);
          break;
      }
      $ret = sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    return $ret;
  }

  public function checkTime($time) {
    $app = Application::get();
    $ret = false;
    $count = $this->pregMatch('/^[ ]*[0-9]{1,2}\:[ ]*[0-9]{1,2}\:[ ]*[0-9]{1,2}[ ]*$/', $time);
    if ($count) {
      list($hours, $mins, $secs) = explode(':', $time);
      $ret =
        (intval($secs)>=0)&&(intval($secs)<60)&&
        (intval($mins)>=0)&&(intval($mins)<60)&&
        (intval($hours)>=0);
    }
    return $ret;
  }

  /**
   * varianty: h:m:s, h:m, m:s 
   * param real added to restrict time: 
   *    true - only within interval 0-23.59
   *    24H  - only within interval 0-24.00
   **/
  public function checkHumanTime($time, $format='h:m:s', $real=false) {
    $app = Application::get();
    $ret = false;
    $reg = array(
          'h:m:s' => '/^[ ]*[0-9]{1,2}\:[ ]*[0-9]{1,2}\:[ ]*[0-9]{1,2}[ ]*$/',
          'h:m' => '/^[ ]*[0-9]{1,2}\:[ ]*[0-9]{1,2}[ ]*$/',
          'm:s' => '/^[ ]*[0-9]{1,2}\:[ ]*[0-9]{1,2}[ ]*$/',
          );
    if (!in_array($format,array_keys($reg))) throw new ExceptionUserTextStorage('error.regionalSettings_checkHumanTimeUnsupportedFormat');
    $count = $this->pregMatch($reg[$format], $time);
    if ($count) {
      $parts = explode(':', $time);
      switch ($format) {
        case 'h:m:s':
                $hours = $parts[0]; $mins = $parts[1]; $secs = $parts[2];
                break; 
        case 'h:m':
                $hours = $parts[0]; $mins = $parts[1]; $secs = '00';
                break; 
        case 'm:s':
                $hours = '00'; $mins = $parts[0]; $secs = $parts[1];
                break; 
      }
			if ($real) {
                           if ($real===true) $ret = (intval($secs)>=0)&&(intval($secs)<60)&&(intval($mins)>=0)&&(intval($mins)<60)&&(intval($hours)>=0)&&(intval($hours)<24);
                           elseif (!strcmp($real,'24H')) $ret = ((intval($secs)>=0)&&(intval($secs)<60)&&(intval($mins)>=0)&&(intval($mins)<60)&&(intval($hours)>=0)&&(intval($hours)<24))||
                                                                (($secs==0)&&($mins==0)&&($hours==24));
			}
			else { $ret = (intval($secs)>=0)&&(intval($secs)<60)&&(intval($mins)>=0)&&(intval($mins)<60)&&(intval($hours)>=0); }
    }
    return $ret;
  }

  public function convertHumanToTime($time, $format='h:m:s', $conversion=false) {
    if (is_null($time)||($time === '')) {
      $ret = '';
    } else {
      if (!$this->checkHumanTime($time, $format)) {
        throw new ExceptionUserTextStorage('error.regionalSettings_convertHumanToTimeNotTime');
      }
      $parts = explode(':', $time);
      if (!strcmp($conversion,'24H')&&(in_array($format,array('h:m:s','h:m')))) {
        if (($parts[0]==24)&&($parts[1]==0)&&(!isset($parts[2])||($parts[2]==0))) {
          $parts[0] = 23;
          $parts[1] = 59;
        }
      }
      switch ($format) {
        case 'h:m:s':
                $ret = sprintf('%02d:%02d:%02d', $parts[0], $parts[1], $parts[2]);
                break;
        case 'h:m':
                $ret = sprintf('%02d:%02d:00', $parts[0], $parts[1]);
                break;
        case 'm:s':
                $ret = sprintf('00:%02d:%02d', $parts[0], $parts[1]);
                break;
      }
    }
    return $ret;
  }

  public function convertTimeToHuman($time, $format='h:m:s') {
    if (is_null($time)||($time === '')) {
      $ret = '';
    } else {
      if (!$this->checkTime($time)) {
        throw new ExceptionUserTextStorage('error.regionalSettings_convertTimeToHumanNotTime'. $time);
      }
      list($hours, $mins, $secs) = explode(':', $time);
      switch ($format) {
        case 'h:m:s':
                $ret = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
                break;
        case 'h:m':
                $ret = sprintf('%02d:%02d', $hours, $mins);
                break;
        case 'm:s':
                $ret = sprintf('%02d:%02d', $mins, $secs);
                break;
        default:
                $ret = '';
                break;
      }
    }
    return $ret;
  }

  public function increaseDate($date, $days=1, $months=0, $years=0) {
    $ret = false;
    if ($this->checkDate($date)) {
      list ($year, $month, $day) = explode('-', $date);
      $ret = date('Y-m-d', mktime(0, 0, 0, $month + $months, $day + $days, $year + $years));
    } elseif ($this->checkHumanDate($date)) {
      list ($day, $month, $year) = explode('.', $date);
      $ret = date('Y-m-d', mktime(0, 0, 0, $month + $months, $day + $days, $year + $years));
      $ret = $this->convertDateToHuman($ret);
    }
    return $ret;
  }

  public function decreaseDate($date, $days=1, $months=0, $years=0) {
    return $this->increaseDate($date, -1 * $days, -1 * $months, -1 * $years);
  }

  public function increaseTime($time, $hours=1, $mins=0, $secs=0) {
    $ret = false;
    if ($this->checkTime($time)) {
      list ($hour, $min, $sec) = explode(':', $time);
      $ret = date('H:i:s', mktime($hour + $hours, $min + $mins, $sec + $secs, '1', '1', '2000'));
    }
    return $ret;
  }

  public function decreaseTime($time, $hours=1, $mins=0, $secs=0) {
    return $this->increaseTime($time, -1 * $hours, -1 * $mins, -1 * $secs);
  }

  public function addTime($time, $addTime) {
    $ret = false;
    if ($this->checkTime($addTime)) {
      list ($hours, $mins, $secs) = explode(':', $addTime);
      $ret = $this->increaseTime($time, $hours, $mins, $secs);
    }
    return $ret;
  }

  public function increaseDateTime($datetime, $days=1, $months=0, $years=0, $hours=0, $mins=0, $secs=0) {
    $ret = false;
    if ($this->checkDateTime($datetime)) {
      list ($date, $time) = explode(' ', $datetime);
      list ($year, $month, $day) = explode('-', $date);
      list ($hour, $min, $sec) = explode(':', $time);
      $ret = date('Y-m-d H:i:s', mktime($hour + $hours, $min + $mins, $sec + $secs, $month + $months, $day + $days, $year + $years));
    }
    return $ret;
  }

  public function decreaseDateTime($datetime, $days=1, $months=0, $years=0, $hours=0, $mins=0, $secs=0) {
    return $this->increaseDateTime($datetime, -1 * $days, -1 * $months, -1 * $years, -1 * $hours, -1 * $mins, -1 * $secs);
  }

  public function isDateInterval($fromDate, $toDate) {
    while (!$ret = false) {
      if (!$this->checkDate($fromDate)) { break; }
      if (!$this->checkDate($toDate)) { break; }
      if ($toDate < $fromDate) { break; }
      $ret = true;
      break;
    }
    return $ret;  
  }
 
  public function isInterval($from, $to) {
    while (!$ret = false) {
      if (!$this->checkDate($from)&&!$this->checkDateTime($from)&&!$this->checkTime($from)) { break; }
      if (!$this->checkDate($to)&&!$this->checkDateTime($to)&&!$this->checkTime($to)) { break; }
      if ($to < $from) { break; }
      $ret = true;
      break;
    }
    return $ret;
  }

  public function checkIntervalIntersect($from1, $to1, $from2, $to2) {
    while (!$ret = false) {
      if (!$this->isInterval($from1,$to1)) { break; }
      if (!$this->isInterval($from2,$to2)) { break; }
      if (($to1<=$from2)||($to2<=$from1)) { break; }
      $ret = true;
      break;
    }
    return $ret;
  }
  
  public function pregMatch($pattern, $string, &$matches = null, $flags = false, $offset = false){
    $app = Application::get();
    if ($app->getCharset() == 'utf-8') $pattern .= 'u';
    if ($offset === false){
      return preg_match($pattern, $string, $matches, $flags);
    } else {
      return preg_match($pattern, $string, $matches, $flags, $offset);
    }
  }
  
  // kontroluje telefonni cislo
  // nepovinna mezinarodni predpona +NNN a nepovinne mezery mezi trojicemi cisel
  public function checkPhoneNumber($number, $international=false) {
    if ($international) $regExp = "/^(\+|00)\d{3} ?\d{3} ?\d{3} ?\d{3}$/";
    else $regExp = "/^((\+|00)\d{3})? ?\d{3} ?\d{3} ?\d{3}$/";
    
    return preg_match($regExp, $number);
  }

  // kontroluje emailovou adresu
  public function checkEmail($email) {
    $regExp = "/^[a-zA-Z0-9-_\+]+(\.[a-zA-Z0-9-_\+]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,6})$/";
    
    return preg_match($regExp, $email);
  }

  public function getDayOfWeek($date,$lower=true) {
    if (!is_integer($date)) $date = strtotime($date);
    $ret = date('D',$date);
    if ($lower) $ret = strtolower($ret);

    return $ret;
  }

  public function getDayNumOfWeek($date) {
    if (!is_integer($date)) $date = strtotime($date);
    $ret = date('N',$date);

    return $ret;
  }
  
  public function convertTimeStampToLocale($timestamp, $format) {
    if (!$timestamp||!$format) return '';
    
    $app = Application::get();
    $format = str_replace(array('d','j','D','l','m','M','F','Y','y','H','h','i','s','n'), array('%d','%e','%a','%A','%m','%h','%B','%Y','%y','%H','%I','%M','%S','%l'), $format);
    
    if (!strcmp($app->language->getLanguage(),'cz')) {
      $orig = setlocale(LC_TIME,0); 
      setlocale(LC_TIME, 'cs_CZ');
      setlocale(LC_TIME, 'cs_CZ.UTF-8');
    }
    $ret = @strftime($format, $timestamp);
    
    if (isset($orig)) setlocale(LC_TIME,$orig);
    
    return $ret;
  }

  public function calculateDateTimeDifference($datetime1, $datetime2, $diffunit='min') {
    $one = new DateTime($datetime1);
    $second = new DateTime($datetime2);
    $interval = $one->diff($second);
    if (!strcmp($diffunit,'hour')) {
      $intervalStruct = explode(' ', $interval->format('%a %h'));
      $difference = $intervalStruct[0]*24+$intervalStruct[1];
    } elseif (!strcmp($diffunit,'min')) {
      $intervalStruct = explode(' ', $interval->format('%a %h %i'));
      $difference = $intervalStruct[0]*24*60+$intervalStruct[1]*60+$intervalStruct[2];
    } elseif (!strcmp($diffunit,'sec')) {
      $intervalStruct = explode(' ', $interval->format('%a %h %i %s'));
      $difference = $intervalStruct[0]*24*60*60+$intervalStruct[1]*60*60+$intervalStruct[2]*60+$intervalStruct[3];
    } elseif (!strcmp($diffunit,'min:sec')) {
      $intervalStruct = explode(' ', $interval->format('%a %h %i %s'));
      $difference = sprintf('%02d:%02d', $intervalStruct[0]*24*60+$intervalStruct[1]*60+$intervalStruct[2], $intervalStruct[3]);
    } else {
      throw new ExceptionUser('RegionalSettings::calculateDateTimeDifference - invalid diffunit!');
    }

    return $difference;
  }
}

?>
