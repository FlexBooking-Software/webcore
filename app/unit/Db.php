<?php

abstract class Db {
  protected $_persistent = true;
  protected $_server = 'localhost';
  protected $_port;
  protected $_user = 'nobody';
  protected $_password = '';
  protected $_database;
  protected $_connection;
  protected $_transactionCounter = 0;
  protected $_errorMessages = array();

  public function __construct($params=array()) { $this->_userParamsInit($params); }

  protected function _userParamsInit(&$params) {
    if (isset($params['persistent'])) { $this->_persistent = $params['persistent']; }
    if (isset($params['server'])) { $this->_server = $params['server']; }
    if (isset($params['port'])) { $this->_port = $params['port']; }
    if (isset($params['user'])) { $this->_user = $params['user']; }
    if (isset($params['password'])) { $this->_password = $params['password']; }
    if (isset($params['database'])) { $this->_database = $params['database']; }
    if (isset($params['errorMessages'])) { $this->_errorMessages = $params['errorMessages']; }
  }

  public function getUser() { return $this->_user; }

  public function getPassword() { return $this->_password; }
  
  public function getQueryTime() { return $this->_queryTime; }

  public function getDatabase() { return $this->_database; }
  public function setDatabase($database) { $this->_database = $database; }

  public function setErrorMessages($errorMessages) { $this->_errorMessages = $errorMessages; }

  abstract protected function _execConnect();

  public function connect() {
    $app = Application::get();
    $ret = $this->_execConnect();
    $message = sprintf('%s:connect: %s@%s:%s %s',
        get_class($this), $this->_user, $this->_server, $this->_port,
        $this->_persistent ? 'persistent' : 'not persistent');
    $app->messages->addMessage($ret ? 'message' : 'error', $message, $ret ? 100 : 0);
    return $ret;
  }

  abstract protected function _execDisconnect();

  public function disconnect() {
    $ret = $this->_execDisconnect();
    return $ret;
  }

  abstract protected function _execUseDatabase();

  public function useDatabase() {
    $app = Application::get();
    $ret = $this->_execUseDatabase();
    $message = sprintf('%s:useDatabase: %s', get_class($this), $this->_database);
    $app->messages->addMessage($ret ? 'message' : 'error', $message, $ret ? 100 : 0);
    return $ret;
  }

  abstract protected function _execDoQuery($query);

  abstract protected function _execGetError();

  abstract protected function _execGetErrorCode();

  public function doQuery($query) {
    $app = Application::get();

    //if ($app->timer->getLogDb()) { $app->timer->start('DB'); }
    $start = microtime(true);
    $result = $this->_execDoQuery($query);
    $time = microtime(true)-$start;
    //if ($app->timer->getLogDb()) $app->timer->stop('DB');

    $message = sprintf('%s:doQuery (%5f): %s', get_class($this), $time, $query);
    $app->messages->addMessage('message', $message, 50);
    if (!$result) {
      $message = $this->_getSqlError($query, $result);
      error_log(__FILE__.' SQL error:'.$message);
      throw new ExceptionUser($message);
    }

    return $result;
  }

  protected function _getSqlError($query, $result) {
    $app = Application::get();

    $message = '';
    $code = $this->_execGetErrorCode($result);

    if (isset($this->_errorMessages[$code])) {
      $message .= $app->textStorage->getText($this->_errorMessages[$code]);
    }

    if (!isset($this->_errorMessages[$code]) || $app->getDebug()) {
      $error = $this->_execGetError($result);
      $message .= sprintf('%s%s:doQuery: %s', $message ? ' ' : '', get_class($this), $error);
    }

    return $message;
  }

  abstract protected function _execFetchArray($result);

  public function fetchArray($result) {
    $ret = $this->_execFetchArray($result);
    return $ret;
  }

  abstract protected function _execFetchAssoc($result);

  public function fetchAssoc($result) {
    $ret = $this->_execFetchAssoc($result);
    return $ret;
  }

  abstract protected function _execFetchRow($result);

  public function fetchRow($result) {
    $ret = $this->_execFetchRow($result);
    return $ret;
  }

  abstract protected function _execFreeResult($result);

  public function freeResult($result) {
    $ret = $this->_execFreeResult($result);
    return $ret;
  }

  abstract protected function _execSeekRow($result, $number);

  public function seekRow($result, $number) {
    $ret = $this->_execSeekRow($result, $number);
    return $ret;
  }

  abstract protected function _execGetRowsNumber($result);

  public function getRowsNumber($result) {
    $ret = $this->_execGetRowsNumber($result);
    return $ret;
  }

  abstract protected function _execGetLastIdentity();

  public function getLastIdentity() {
    $ret = $this->_execGetLastIdentity();
    return $ret;
  }

  abstract public function escapeString($value);

  protected function _prepareName($name) {
    $name = $this->escapeString($name);
    
    return $name;
  }
  
  public function prepareName($name) { return $this->_prepareName($name); }

  protected function _prepareValue($value) {
    if (is_null($value)) {
      $value = 'NULL';
    } elseif (is_integer($value) || is_float($value)) {
      // neni treba nic
    } elseif ($value instanceof SqlStatement){
      $value = $value->toString();
    } else { 
      $value = "'". $this->escapeString($value) ."'";
    }
    return $value;
  }
  
  public function escapeSetting($value, $key = null){
    $value = $this->_prepareValue($value);
    if (isset($key)) $value = $key.(strtoupper($value) == 'NULL' ? ' IS NULL' : '='.$value);
    return $value;
  }

  public function insert($fields, $table) {
    $query = 'INSERT INTO '. $this->_prepareName($table);
    $columns = $values = '';

    foreach ($fields as $key => $value) {
      $columns .= ($columns !== '' ? ',' : '') . $this->_prepareName($key);
      $values .= ($values !== '' ? ',' : '') . $this->_prepareValue($value);
    }

    $query .= " ($columns) VALUES ($values)";
    $res = $this->doQuery($query);
    return $res ? true : false;
  }

  public function insertArray($fieldsArray, $table) {
    $query = 'INSERT INTO '. $this->_prepareName($table);
    $columns = $values = $valuOne = '';

    foreach ($fieldsArray[0] as $key => $value) {
      $columns .= ($columns !== '' ? ',' : '') . $this->_prepareName($key);
    }

    foreach ($fieldsArray as $fields) {

      $valuesOne = '';
      foreach ($fields as $key => $value) {
        $valuesOne .= ($valuesOne !== '' ? ',' : '') . $this->_prepareValue($value);
      }
      $values .= ($values !== '' ? ',' : '') . "($valuesOne)";
      
    }

    $query .= " ($columns) VALUES $values";
    $res = $this->doQuery($query);
    return $res ? true : false;
  }

  public function update($fields, $keys, $table) {
    $query = 'UPDATE '. $this->_prepareName($table) .' SET ';
    $values = $where = '';

    foreach ($fields as $key => $value) {
      $values .= ($values !== '' ? ',' : '') . $this->_prepareName($key) .'='. $this->_prepareValue($value);
    }
    foreach ($keys as $key => $value) {
      if (is_null($value)) { $where .= ($where !== '' ? ' AND ' : '') . $this->_prepareName($key) .' IS NULL'; }
      else { $where .= ($where !== '' ? ' AND ' : '') . $this->_prepareName($key) .'='. $this->_prepareValue($value); }
    }

    $query .= $values .' WHERE '. $where;
    $res = $this->doQuery($query);
    return $res ? true : false;
  }

  public function delete($keys, $table) {
    $query = 'DELETE FROM '. $this->escapeString($table);
    $where = '';

    foreach ($keys as $key => $value) {
      if (is_null($value)) { $where .= ($where !== '' ? ' AND ' : '') . $this->_prepareName($key) .' IS NULL'; }
      else { $where .= ($where !== '' ? ' AND ' : '') . $this->_prepareName($key) .'='. $this->_prepareValue($value); }
    }

    $query .= ' WHERE '. $where;
    $res = $this->doQuery($query);
    return $res ? true : false;
  }

  public function beginTransaction() {
    if (!$this->_transactionCounter) {
      $this->doQuery('begin');
    }
    $this->_transactionCounter++;
  }

  public function commitTransaction() {
    if (!$this->_transactionCounter) {
      throw new Exception(get_class($this) .'::commitTransaction(): transaction wasn\'t started');
    }
    $this->_transactionCounter--;
    if (!$this->_transactionCounter) {
      $this->doQuery('commit');
    }
  }

  public function rollbackTransaction() {
    if (!$this->_transactionCounter) {
      throw new Exception(get_class($this) .'::rollbackTransaction(): transaction wasn\'t started');
    }
    $this->doQuery('rollback');
    $this->_transactionCounter = 0;
  }

  public function shutdownTransaction() {
    if ($this->_transactionCounter) {
      $this->rollbackTransaction();
    }
  }

  public function getTransactionCounter() {
    return $this->_transactionCounter;
  }
}

class MysqlDb extends Db {
  protected $_encoding = null;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    if (isset($params['encoding'])) $this->_encoding = $params['encoding'];
  }

  protected function _execConnect() {
    $server = $this->_server;
    if ($this->_port) {
      $server .= ':'. $this->_port;
    }
    $function = $this->_persistent ? 'mysql_pconnect' : 'mysql_connect';
    $this->_connection = @$function($server, $this->_user, $this->_password);

    return $this->_connection ? true : false;
  }
  
  public function setEncoding($encoding=null) {
    $result = true;
    
    if ($encoding) $this->_encoding = $encoding;
    if ($this->_encoding) $result = $this->_execEncoding();
    
    return $result;
  }

  protected function _execEncoding() {
    $result = $this->doQuery(sprintf('SET CHARACTER SET %s', $this->_encoding));
    return $result;
  }

  protected function _execDisconnect() {
    $bool = mysql_close($this->_connection);
    return $bool;
  }

  protected function _execUseDatabase() {
    $bool = mysql_select_db($this->_database, $this->_connection);
    if ($bool&&$this->_encoding) $bool = $this->_execEncoding();

    return $bool;
  }

  protected function _execDoQuery($query) {
    $result = mysql_query($query, $this->_connection);
    return $result;
  }

  protected function _execGetError() {
    $string = mysql_error($this->_connection);
    return $string;
  }

  protected function _execGetErrorCode() {
    $code = mysql_errno($this->_connection);
    return $code;
  }

  protected function _execFetch($result, $type) {
    $row = mysql_fetch_array($result, $type);
    return $row;
  }

  protected function _execFetchArray($result) {
    return $this->_execFetch($result, MYSQL_BOTH);
  }

  protected function _execFetchAssoc($result) {
    return $this->_execFetch($result, MYSQL_ASSOC);
  }

  protected function _execFetchRow($result) {
    return $this->_execFetch($result, MYSQL_NUM);
  }

  protected function _execFreeResult($result) {
    $bool = mysql_free_result($result);
    return $bool;
  }

  protected function _execSeekRow($result, $number) {
    $bool= mysql_data_seek($result, $number);
    return $bool;
  }

  protected function _execGetRowsNumber($result) {
    $int = mysql_num_rows($result);
    return $int;
  }

  protected function _execGetLastIdentity() {
    $int = mysql_insert_id($this->_connection);
    return $int;
  }

  public function escapeString($string) {
    if (is_resource($this->_connection)) {
      $string = mysql_real_escape_string($string, $this->_connection);
    } else {
      $string = addslashes($string);
    }
    return $string;
  }

  public function reset($result) {
    mysql_data_seek($result, 0);
  }

  protected function _execIsConnected() {
    return mysql_ping($this->_connection);
  }

  public function isConnected() {
    return is_resource($this->_connection)&&$this->_execIsConnected();
  }

  public function reconnect() {
    $this->_execDisconnect();
    $this->_execConnect();
    $this->_execUseDatabase();
  }
}

class PgSqlDb extends Db {

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
  }

  protected function _execConnect() {
    $connectString = sprintf('host=%s port=%s dbname=%s user=%s password=%s', $this->_server, $this->_port, $this->_database, $this->_user, $this->_password);
    $this->_connection = @pg_connect($connectString);
    
    return $this->_connection ? true : false;
  }

  protected function _execDisconnect() {
    $bool = @pg_close($this->_connection);
    return $bool;
  }
  
  protected function _execUseDatabase() { return true; }

  protected function _execDoQuery($query) {
    $result = @pg_query($this->_connection, $query);
    return $result;
  }

  protected function _execGetError() {
    $string = @pg_last_error($this->_connection);
    return $string;
  }

  protected function _getSqlError($query, $result) {
    $message = '';
    
    $error = $this->_execGetError($result);
    $message .= sprintf('%s%s:doQuery: %s', $message ? ' ' : '', get_class($this), $error);
    
    return $message;
  }

  protected function _execGetErrorCode() { return false; }

  protected function _execFetch($result, $type) {
    $row = @pg_fetch_array($result, NULL, $type);
    return $row;
  }

  protected function _execFetchArray($result) {
    return $this->_execFetch($result, PGSQL_BOTH);
  }

  protected function _execFetchAssoc($result) {
    return $this->_execFetch($result, PGSQL_ASSOC);
  }

  protected function _execFetchRow($result) {
    return $this->_execFetch($result, PGSQL_NUM);
  }

  protected function _execFreeResult($result) {
    $bool = @pg_free_result($result);
    return $bool;
  }

  protected function _execSeekRow($result, $number) {
    $bool= @pg_result_seek($result, $number);
    return $bool;
  }

  protected function _execGetRowsNumber($result) {
    $int = @pg_num_rows($result);
    return $int;
  }

  protected function _execGetLastIdentity() { throw ExceptionUser('PgSql: call of getLastIdentity!'); }
  
  protected function _prepareName($name) {
    $name = '"' . $this->escapeString($name) . '"';
    
    return $name;
  }

  public function escapeString($string) {
    if (is_resource($this->_connection)) {
      $string = @pg_escape_string($this->_connection, $string);
    } else {
      $string = addslashes($string);
    }
    return $string;
  }
  
  public function escapeSetting($value, $key = null){
    $value = $this->_prepareValue($value);
    if (isset($key)) $value = '"'.$key.'"'.(strtoupper($value) == 'NULL' ? ' IS NULL' : '='.$value);
    return $value;
  }

  public function reset($result) {
    @pg_result_seek($result, 0);
  }
}

class MysqlIDb extends MysqlDb {
  protected $_socket = null;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    if (isset($params['socket'])) $this->_socket = $params['socket'];
  }

  protected function _execConnect() {
    $server = $this->_server;
    if ($this->_persistent) $server = 'p:'.$server;

    $this->_connection = mysqli_connect($server, $this->_user, $this->_password, '', $this->_port, $this->_socket);

    return $this->_connection ? true : false;
  }

  protected function _execDisconnect() {
    $bool = @mysqli_close($this->_connection);
    return $bool;
  }

  protected function _execUseDatabase() {
    $bool = mysqli_select_db($this->_connection, $this->_database);
    if ($bool&&$this->_encoding) $bool = $this->_execEncoding();

    return $bool;
  }

  protected function _execDoQuery($query) {
    $result = mysqli_query($this->_connection, $query);
    return $result;
  }

  protected function _execGetError() {
    $string = mysqli_error($this->_connection);
    return $string;
  }

  protected function _execGetErrorCode() {
    $code = mysqli_errno($this->_connection);
    return $code;
  }

  protected function _execFetch($result, $type) {
    $row = mysqli_fetch_array($result, $type);
    return $row;
  }

  protected function _execFetchArray($result) {
    return $this->_execFetch($result, MYSQLI_BOTH);
  }

  protected function _execFetchAssoc($result) {
    return $this->_execFetch($result, MYSQLI_ASSOC);
  }

  protected function _execFetchRow($result) {
    return $this->_execFetch($result, MYSQLI_NUM);
  }

  protected function _execFreeResult($result) {
    mysqli_free_result($result);
    return true;
  }

  protected function _execSeekRow($result, $number) {
    $bool= mysqli_data_seek($result, $number);
    return $bool;
  }

  protected function _execGetRowsNumber($result) {
    $int = mysqli_num_rows($result);
    return $int;
  }

  protected function _execGetLastIdentity() {
    $int = mysqli_insert_id($this->_connection);
    return $int;
  }

  public function escapeString($string) {
    if ($this->_connection) {
      $string = mysqli_real_escape_string($this->_connection, $string);
    } else {
      $string = addslashes($string);
    }
    return $string;
  }

  public function reset($result) {
    mysqli_data_seek($result, 0);
  }

  protected function _execIsConnected() {
    return mysqli_ping($this->_connection);
  }
}

?>
