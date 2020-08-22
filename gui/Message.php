<?php

class GuiMessages extends GuiElement {
  protected $_errorInPopup = true;
  protected $_infoInPopup = false;
  protected $_errorTemplate = '<div class="error">%s</div>';
  protected $_infoTemplate = '<div class="info">%s</div>';
  protected $_popupTemplate = '<div class="popup">%s</div>';
  protected $_windowTemplate = '<div id="%s">%s%s</div>';
  protected $_closeButtonTemplate = '
          <div class="button">
            <input type="button" name="hide" value="{__button.close}" onclick="document.getElementById(\'%s\').style.display=\'none\';"/>
          </div>';

  protected function _userParamsInit(&$params) {
    if (isset($params['errorInPopup'])) $this->_errorInPopup = $params['errorInPopup'];
    if (isset($params['infoInPopup'])) $this->_infoInPopup = $params['infoInPopup'];
    if (isset($params['errorTemplate'])) $this->_errorTemplate = $params['errorTemplate'];
    if (isset($params['infoTemplate'])) $this->_infoTemplate = $params['infoTemplate'];
    if (isset($params['popupTemplate'])) $this->_popupTemplate = $params['popupTemplate'];
    if (isset($params['windowTemplate'])) $this->_windowTemplate = $params['windowTemplate'];
    if (isset($params['closeButtonTemplate'])) $this->_closeButtonTemplate = $params['closeButtonTemplate'];
  }

  protected function _userRender() {
    // errors
    $errorsHtml = ''; 
    $errors = $this->_app->messages->getMessages('userError');
    $errorHtml = '';
    foreach ($errors as $e) {
      $errorHtml .= sprintf($this->_errorTemplate, $e['message']);
    }
    if (count($errors)) {
      if ($this->_errorInPopup) $closeErrorButton = sprintf($this->_closeButtonTemplate, 'errorsList');
      $errorsHtml = sprintf($this->_windowTemplate, 'errorsList', $errorHtml, ifsetor($closeErrorButton));
    }

    // infos
    $infosHtml = ''; 
    $infos = $this->_app->messages->getMessages('userInfo');
    $infoHtml = '';
    foreach ($infos as $i) {
      $infoHtml .= sprintf($this->_infoTemplate, $i['message']);
    }
    if (count($infos)) {
      if ($this->_infoInPopup) $closeInfoButton = sprintf($this->_closeButtonTemplate, 'infosList');
      $infosHtml = sprintf($this->_windowTemplate, 'infosList', $infoHtml, ifsetor($closeInfoButton));
    }

    // popus
    $popupsHtml = '';
    $popups = $this->_app->messages->getMessages('userPopup');
    $popupHtml = '';
    foreach ($popups as $p) {
      $popupHtml .= sprintf($this->_popupTemplate, $p['message']);
    }
    if (count($popups)) {
      $closeButton = sprintf($this->_closeButtonTemplate, 'popupsList');
      $popupsHtml = sprintf($this->_windowTemplate, 'popupsList', $popupHtml, $closeButton);
    }

    $this->setTemplateString($infosHtml.$errorsHtml.$popupsHtml);
  }

}

?>
