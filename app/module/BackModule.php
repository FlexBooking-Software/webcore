<?php

class BackModule extends ExecModule {

  protected function _getBackwardsParam() { return null; }

  protected function _userInsert() { } 

  protected function _userRun() {
    $running = $this->_app->history->getBackwards($this->_getBackwardsParam());

    if (isset($running['action'])) {
      $action = $running['action'];
      unset($running['action']);
    } else {
      $action = '';
    }

    $this->_app->response->addParams($running);
    $this->_userInsert();

    return $action;
  }
}

?>
