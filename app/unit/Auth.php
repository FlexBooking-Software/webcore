<?php

class Auth {
  protected $_nameSessionVar = '__auth_';
  protected $_userId;
  protected $_username;
  protected $_fullname;
  protected $_md5Password = false;

  public function __construct($params=array()) {
    $this->_loadAuth();
    if (isset($params['md5Password'])) { $this->_md5Password = $params['md5Password']; }
  }

  public function authenticate($params) {
    $ret = $this->_execAuthenticate($params);
    if (is_array($ret)) {
      $this->_saveAuth($ret);
    }
    return is_array($ret) ? true : false;
  }

  public function reset() {
    $this->_userId = null;
    $this->_username = null;
    $this->_fullname = null;
  }

  protected function _loadAuth() {
    $app = Application::get();
    $this->_userId =& $app->session->getPtr($this->_nameSessionVar .'userId');
    $this->_username =& $app->session->getPtr($this->_nameSessionVar .'username');
    $this->_fullname =& $app->session->getPtr($this->_nameSessionVar .'fullname');
  }

  protected function _saveAuth($params) {
    $this->_userId = $params['userId'];
    $this->_username = $params['username'];
    $this->_fullname = ifsetor($params['fullname']);
  }

  protected function _execAuthenticate($params) { return false; }

  public function getUserId() { return $this->_userId; }
  public function setUserId($userId) { $this->_userId = $userId; }

  public function getUsername() { return $this->_username; }
  public function setUsername($username) { $this->_username = $username; }
  
  public function getFullname() { return $this->_fullname; }
  public function setFullname($name) { $this->_fullname = $name; }

  public function setMd5Password($md5Password) { $this->_md5Password = $md5Password; }
  public function getMd5Password() { return $this->_md5Password; }

  public function haveRight($right) {
    $ret = $this->haveURight($right);
    if (!$ret) { $ret = $this->haveGRight($right); }
    return $ret;
  }

  public function haveURight($right) { return false; }
  public function haveGRight($right) { return false; }
  public function isInGroup($group) { return false; }
}

class DbAuth extends Auth {

  protected function _execAuthenticate($params) {
    $app = Application::get();
    $ret = false;

    $query = $this->_getExecAuthenticateSql($params);
    $result = $app->db->doQuery($query);

    if ($result && ($app->db->getRowsNumber($result) == 1)) {
      $ret = $app->db->fetchAssoc($result);
    } else {
      $this->reset();
    }
    return $ret;
  }

  protected function _getExecAuthenticateSql($params) {
    $query = sprintf('SELECT authuser_id AS userId, username AS username, CONCAT(firstname,\' \',surname) AS fullname FROM authuser WHERE username=\'%s\' AND password=\'%s\'',
        addslashes($params['username']),
        $this->getMd5Password() ? md5(addslashes($params['password'])) : addslashes($params['password']));
    return $query;
  }

  public function isInGroup($group) {
    $app = Application::get();
    $ret = false;
    $query = $this->_getIsInGroupSql($group);
    $res = $app->db->doQuery($query);
    if ($res && $app->db->getRowsNumber($res)) {
      $ret = true;
    }
    return $ret;
  }

  protected function _getIsInGroupSql($group) {
    $query = sprintf('
        SELECT g.authgroup_id 
        FROM authgroup g
        JOIN authuser_authgroup ug ON ug.authgroup=g.authgroup_id
        WHERE ug.authuser=%d and g.groupname=\'%s\'', 
        $this->getUserId(), addslashes($group));
    return $query;
  }

  public function haveURight($right) {
    $app = Application::get();
    $ret = false;
    $query = $this->_getHaveURightSql($right);
    $res = $app->db->doQuery($query);
    if ($res && $app->db->getRowsNumber($res)) {
      $ret = true;
    }	  
    return $ret;
  }

  protected function _getHaveURightSql($right) {
    $query = sprintf('
        SELECT ur.authright 
        FROM authright r
        JOIN authuser_authright ur ON ur.authright = r.authright_id
        WHERE ur.authuser=%d AND r.rightname=\'%s\'',
        $this->getUserId(), addslashes($right));
    return $query;
  }

  public function haveGRight($right) {
    $app = Application::get();
    $ret = false;
    $query = $this->_getHaveGRightSql($right);
    $res = $app->db->doQuery($query);
    if ($res && $app->db->getRowsNumber($res)) {
      $ret = true;
    }	  
    return $ret;
  }

  protected function _getHaveGRightSql($right) {
    $query = sprintf('
        SELECT gr.authright 
        FROM authuser_authgroup ug
        JOIN authgroup_authright gr ON gr.authgroup=ug.authgroup
        JOIN authright r ON r.authright_id=gr.authright
        WHERE ug.authuser=%d AND r.rightname=\'%s\'',
        $this->getUserId(), addslashes($right));
    return $query;
  }
}

?>
