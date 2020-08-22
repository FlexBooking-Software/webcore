<?php

class GridModule extends ExecModule {

  protected function _getSettings($gridclass, $gridname) { return new $gridclass($gridname); }

  protected function _userRun() {
    $gridname = $this->_app->request->getParams('gridname');
    $gridclass = $this->_app->request->getParams('gridclass');
    if (is_null($gridclass)) { $gridclass = 'GridSettings'; }

    $settings = $this->_getSettings($gridclass, $gridname);
    $settings->updateSettings();
    $settings->saveSettings();

    $responseParams = array('backwards' => 1);
    if ($frame = $this->_app->request->getParams('frame')) {
      $responseParams['frame'] = $frame;
    }
    $par = $this->_app->request->getParams('params');
    if (is_array($par)){
      foreach ($par as $key => $val)
        $responseParams[$key] = $val;
    }
    $this->_app->response->addParams($responseParams);

    $next = $this->_app->request->isSetParam('nextAction');
    $next = ($next)?$this->_app->request->getParams('nextAction'):'eBack';

    return $next;
  }
}

?>
