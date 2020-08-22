<?php

class Validator {
  static private $_sessionPrefix = '__Validator_';
  protected $_name;
  protected $_vars = array();
  protected $_varsSources;

  protected function __construct($name) {
    $this->_name = $name;
    $this->_insert();
  }

  public function destroy() {
    $session = Application::get()->session;
    $session->set(self::$_sessionPrefix . get_class($this) . $this->_name, null);
  }

  public function getName() {
    return $this->_name;
  }

  static public function get($name, $class, $new=false) {
    $session = Application::get()->session;
    
    $validator = $session->getPtr(self::$_sessionPrefix . $class. $name);
    if ($new) { 
      unset ($validator);
      $validator = null;
    }
    if (!$validator instanceof Validator) {
      $validator = new $class($name);
      $session->setPtr(self::$_sessionPrefix .$class. $name, $validator);
    }
    return $validator;
  }

  public function saveToSession() {
    $session = Application::get()->session;
    $session->setPtr(self::$_sessionPrefix .get_class($this). $this->_name, $this);
  }

  public function addValidatorVar($validatorVar) {
    if (!$validatorVar instanceof ValidatorVar) { throw new Exception('Argument isn\'t instance of ValidatorVar.'); }
    $this->_vars[$validatorVar->getName()] = $validatorVar;
  }

  public function getValues() {
    $ret = array();
    foreach (array_keys($this->_vars) as $varName) {
      $ret[$varName] = $this->_vars[$varName]->getValue();
    }
    return $ret;
  }

  public function sizeOf(){
    $size = 0;
    foreach (array_keys($this->_vars) as $varName) {
      $val = $this->_vars[$varName]->getValue();
      $size += $this->getSize($val);
    }
    return $size;
  }
  
  public function getSize($val){
    $size = 0;
    if (is_array($val)){
      foreach ($val as $h) $size += $this->getSize($h);
    } elseif (is_string($val)){
      $size += strlen($val);
    } elseif (!$val){
      $size += 1;
    } else {
      $size += 1;
    }
    return $size;
  }

  public function getLastValues() {
    $ret = array();
    foreach (array_keys($this->_vars) as $varName) {
      $var = $this->getVar($varName);
      if ($var->getOnLastInit()) {
        $ret[$varName] = $var->getValue();
      }
    }
    return $ret;
  }

  public function &getVar($varName) {
    if (!isset($this->_vars[$varName])) {
      throw new Exception(get_class($this) .'::getVar: undefined ValidatorVar '.$varName);
    }
    return $this->_vars[$varName];
  }

  public function isVar($varName) {
    return isset($this->_vars[$varName]);
  }

  public function getVarValue($varName) {
    return $this->getVar($varName)->getValue();
  }

  public function getLastVarValue($varName) {
    $var = $this->getVar($varName);
    return $var->getOnLastInit() ? $var->getValue() : $var->getType()->getDefault();
  }
  
  
  public function validateValues($preserve=array(),$forcedRequired=null) {
    foreach (array_keys($this->_vars) as $varName) {
      if (!in_array($varName, $preserve)) {
        $this->_vars[$varName]->validateValue($forcedRequired);
      }
    }
  }

  public function validateLastValues($preserve=array()) {
    foreach (array_keys($this->_vars) as $varName) {
      $var = $this->getVar($varName);
      if (!in_array($varName, $preserve) && $var->getOnLastInit()) {
        $var->validateValue();
      }
    }
  }

  public function initValues() {
    $ret = true;
    foreach (array_keys($this->_vars) as $varName) {
      if (isset($this->_varsSources)) {
        $this->_vars[$varName]->setSources($this->_varsSources);
      }
      $this->_vars[$varName]->initValue();
    }
    //adump($this->_vars);die;
    return $ret;
  }

  protected function _insert() { }

  public function setValues($values) {
    foreach ($values as $key => $value) {
      if (isset($this->_vars[$key])) {
        $this->_vars[$key]->setValue($value);
      }
    }
  }

  public function setVarsSources($sources) {
    $this->_varsSources = $sources;
  }
}

class ValidatorVar {
  protected $_onLastInit = false;
  protected $_name;
  protected $_label;
  protected $_sources;
  protected $_value;
  protected $_required;
  protected $_type;
  protected $_requireMessage;
  protected $_validateMessage;

  public function __construct($name, $required=false, $type=null, $validateMessage=null, $requireMessage=null) {
    $this->_name = $name;
    $this->_label = $name;
    $this->_required = $required;
    $this->_type = ifsetor($type, new ValidatorType);
    $this->_validateMessage = $validateMessage;
    $this->_requireMessage = $requireMessage;
    $this->setSources(Application::get()->request->getDefaultSources());
  }

  public function getName() {
    return $this->_name;
  }

  public function setLabel($label) {
    $this->_label = $label;
  }

  public function setSources($sources) {
    $this->_sources = $sources;
  }

  public function getValue() {
    return $this->_value;
  }

  public function setValue($value) {
    $this->_value = $value;
  }

  public function getOnLastInit() {
    return $this->_onLastInit;
  }

  public function setOnLastInit($bool) {
    $this->_onLastInit = $bool;
  }

  public function getType() {
    return $this->_type;
  }

  public function initValue() {
    $app = Application::get();
    if ($app->request->isSetParam($this->_name, $this->_sources)) {
      $this->_value = trim($app->request->getParams($this->_name, $this->_sources));
      $this->setOnLastInit(true);
    } else {
      if (!isset($this->_value)) {
        $this->_value = $this->_type->getDefault();
      }
      $this->setOnLastInit(false);
    }
  }

  public function validateValue($forcedRequired=null) {
    if ($forcedRequired!==null) $required = $forcedRequired;
    else $required = $this->_required;

    $app = Application::get();

    $value = $this->getValue();
    if ($required && $this->_isEmpty($value)) {
      if (!$this->_requireMessage) {
        $params = array('label' => $this->_label);
        $replacement = sprintf($app->textStorage->getText('error.validator_missingValue'), $this->_label);
        throw new ExceptionUserGui('error.validator_missingValue', $params, $replacement);
      } else {
        throw new ExceptionUser($this->_requireMessage);
      }
    }
    if (($required || !$this->_isEmpty($value)) && !$this->_type->validate($value)) {
      if (!$this->_validateMessage) {
        $params = array('label' => $this->_label);
        $replacement = sprintf($app->textStorage->getText('error.validator_invalidValue'), $this->_label);
        throw new ExceptionUserGui('error.validator_invalidValue', $params, $replacement);
      } else {
        throw new ExceptionUser($this->_validateMessage);
      }
    }
  }

  public function countValues() {
    $value = $this->getValue();
    $ret = $this->_isEmpty($value) ? 0 : 1;
    return $ret;
  }

  protected function _isEmpty($value) {
    return (is_null($value) || $value === '');
  }
}

class ValidatorVarArray extends ValidatorVar {

  public function __construct($name, $required=false, $type=null, $validateMessage=null, $requireMessage=null) {
    parent::__construct($name, $required, $type, $validateMessage, $requireMessage);
    $this->_value = array();
  }

  public function initValue() {
    $app = Application::get();
    if ($app->request->isSetParam($this->_name, $this->_sources)) {
      $value = $app->request->getParams($this->_name, $this->_sources);
      $this->_value = is_array($value) ? $value : array();
      $this->setOnLastInit(true);
    } else {
      if (!isset($this->_value)) {
        $this->_value = array();
      }
      $this->setOnLastInit(false);
    }
  }

  public function validateValue($forcedRequired=false) {
    if ($forcedRequired) $required = $forcedRequired;
    else $required = $this->_required;

    $app = Application::get();

    $values = $this->getValue();
    $count = $this->countValues();
    
    if ( (($required === 'any') && !$count) 
        || (is_integer($required) && ($required != $count)) ) {
      if (!$this->_requireMessage) {
        $params = array('label' => $this->_label);
        $replacement = sprintf($app->textStorage->getText('error.validator_missingValue'), $this->_label);
        throw new ExceptionUserGui('error.validator_missingValue', $params, $replacement);
      } else {
        throw new ExceptionUser($this->_requireMessage);
      }
    }

    if ($required === 'all') {
      foreach ($values as $value) {
        if ($this->_isEmpty($value)) {
          if (!$this->_requireMessage) {
            $params = array('label' => $this->_label);
            $replacement = sprintf($app->textStorage->getText('error.validator_missingValue'), $this->_label);
            throw new ExceptionUserGui('error.validator_missingValue', $params, $replacement);
          } else {
            throw new ExceptionUser($this->_requireMessage);
          }
        }
      }
    }

    if (!is_array($values)) adump($this);

    foreach ($values as $value) {
      if (!$this->_type->validate($value)) {
        if (!$this->_validateMessage) {
          $params = array('label' => $this->_label);
          $replacement = sprintf($app->textStorage->getText('error.validator_invalidValue'), $this->_label);
          throw new ExceptionUserGui('error.validator_invalidValue', $params, $replacement);
        } else {
          throw new ExceptionUser($this->_validateMessage);
        }
      }
    }

  }

  public function countValues() {
    $values = $this->getValue();
    $ret = 0;
    foreach ($values as $one) {
      $ret += $this->_isEmpty($one) ? 0 : 1;
    }
    return $ret;
  }

}

class ValidatorType {
  protected $_default = null;

  public function getDefault() {
    return $this->_default;
  }

  public function validate($value) {
    return true;
  }
}

class ValidatorTypeString extends ValidatorType {
  protected $_maxLength;
  protected $_minLength;

  public function __construct($maxLength=255, $minLength=0) {
    $this->_maxLength = $maxLength;
    $this->_minLength = $minLength;
    $this->_default = '';
  }

  public function validate($value) {
    $ret = ((strlen($value) <= $this->_maxLength)
      && (strlen($value) >= $this->_minLength) );
    return $ret;
  }
}

class ValidatorTypeDate extends ValidatorType {
  protected $_format;

  public function __construct($format = 'd.m.y') {
    $this->_format = $format;
  }
  
  public function validate($value) {
    $ret = true;
    if ($value !== '') {
      $ret = Application::get()->regionalSettings->checkHumanDate($value, $this->_format);
    }
    return $ret;
  }
}

class ValidatorTypeEmail extends ValidatorType {
  private $_atom = '[-a-z0-9!#$%&\'*+/=?^_`{|}~]';
  private $_domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';

  public function validate($value) {
    $ret = true;
    if ($value !== '') {
      //$ret = eregi("^".$this->_atom."+(\\.".$this->_atom."+)*@(".$this->_domain."?\\.)+".$this->_domain."\$", $value);
      $ret = Application::get()->regionalSettings->checkEmail($value); 
    }
    return $ret;
  }
}

class ValidatorTypeEmailList extends ValidatorTypeEmail {
  private $_separator;
  
  public function __construct($separator = ',') {
    $this->_separator = $separator;
  }
  
  public function validate($value) {
    $ret = true;
    if ($value !== '') {
      $value = str_replace(' ','',$value);
      $values = explode($this->_separator, $value);
      foreach ($values as $val) { 
        $ret = Application::get()->regionalSettings->checkEmail($val);
        
        if (!$ret) break;
      }
    }
    return $ret;
  }
}

class ValidatorTypeTime extends ValidatorType {
  protected $_format;
  protected $_real;

  public function __construct($format = 'h:m:s', $real = false) {
    $this->_format = $format;
    $this->_real = $real;
  }
  
  public function validate($value) {
    $ret = true;
    if ($value !== '') {
      $ret = Application::get()->regionalSettings->checkHumanTime($value, $this->_format, $this->_real);
    }
    return $ret;
  }
}

class ValidatorTypeDateTime extends ValidatorType {
  protected $_formatDate;
  protected $_formatTime;
  protected $_delimiter;
  protected $_reverse;

  public function __construct($formatDate = 'd.m.y', $formatTime = 'h:m', $delimiter = ' ', $reverse = false) {
    $this->_formatDate = $formatDate;
    $this->_formatTime = $formatTime;
    $this->_delimiter = $delimiter;
    $this->_reverse = $reverse;
  }
  
  public function validate($value) {
    $ret = true;
    if ($value !== '') {
      $ret = Application::get()->regionalSettings->checkHumanDateTime($value, $this->_formatDate, $this->_formatTime, $this->_delimiter, $this->_reverse);
    }
    return $ret;
  }
}

class ValidatorTypeInteger extends ValidatorType {
  protected $_minValue;
  protected $_maxvalue;

  public function __construct($maxValue = null, $minValue = 0) {
    $this->_maxValue = $maxValue;
    $this->_minValue = $minValue;
  }

  public function validate($value) {
    $ret = false;

    if (is_numeric($value)) {
      $ret = true;

      $i = intval($value);
      if (isset($this->_minValue)) $ret = $i >= $this->_minValue;
      if ($ret&&isset($this->_maxValue)) $ret = $i <= $this->_maxValue;
    }

    return $ret;
  }
}

class ValidatorTypeNumber extends ValidatorType {
  protected $_integralPlaces;
  protected $_decimalPlaces;

  public function __construct($integralPlaces=10, $decimalPlaces=0) {
    $this->_integralPlaces = $integralPlaces;
    $this->_decimalPlaces = $decimalPlaces;
  }

  public function validate($value) {
    $ret = true;
    if ($value !== '') {
      $ret = Application::get()->regionalSettings->checkHumanNumber($value, $this->_integralPlaces, $this->_decimalPlaces);
    }
    return $ret;
  }
}

class ValidatorTypePhoneNumber extends ValidatorType {

  public function validate($value) {
    $ret = true;
    if ($value !== '') {
      $ret = Application::get()->regionalSettings->checkPhoneNumber($value);
    }
    return $ret;
  }
}

?>
