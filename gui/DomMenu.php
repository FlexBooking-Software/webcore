<?php

class GuiDomMenu extends GuiElement {
  protected $_id;
  protected $_contains = array();

  protected function _userParamsInit(&$params) {
    $this->_id = $params['id'];
  }
  
  protected function _userRender() {
    $stempl = '<div id="'. $this->_id .'" class="menu">';
    
    foreach ($this->_contains as $one) {
      $item_html = $one->render();
      
      $al = $one->getClassParentDiv();
      if (!Empty($al) )$al = ' class="'.$al.'"';
    
      if ($item_html) {
        $stempl .= '<div'.$al.'>'. $item_html .'</div>';
      }
    }
    $stempl .= '<div style="float: none;">&nbsp;</div></div>';
    $this->setTemplateString($stempl);
  }

  public function insertMenuItem($item) { $this->_contains[] = & $item; }
}

class GuiDomMenuItem extends GuiElement {
  protected $_label;
  protected $_action;
  protected $_params;
  protected $_onclick;
  protected $_contains = array();
  protected $_classParentDiv;
  protected $_class = false;

  protected function _userParamsInit(&$params) {
    $this->_label = ifsetor($params['label'], '');
    $this->_action = ifsetor($params['action'], '');
    $this->_classParentDiv = ifsetor($params['classParentDiv'], '');
    $this->_class = ifsetor($params['class'], '');
    $this->_onclick = ifsetor($params['onclick'], null);
    $this->_params = ifsetor($params['params'], array());
  }

  protected function _userRender() {
    $app = Application::get();
    
    $action = $this->getAction();
    $label = $this->getLabel();
    
    $url = $app->getWwwPath();
    $ses = '';
    if (!$app->session->getUseCookie()) {
      $ses = $app->session->getURL();
      if ($ses != '') {
        $ses = '&amp;'.$ses;
      }
    }
    $params = '';
    if (isset($this->_params)&&is_array($this->_params)) {
      foreach ($this->_params as $k => $v) {
        $params .= Application::get()->htmlspecialchars( '&'. urlencode($k) .'='. urlencode($v) );
      }
    }
    if ($action) {
      $href = ' href="'. $url .'?action='. $action . $params . $ses .'"';
    } else {
      $href = ' href=""';
      $this->_class = !empty($this->_class) ? $this->_class.' blind' : 'blind';
    }
    
    if (isset($this->_onclick)) $href .= sprintf(' onclick="%s"', $this->_onclick);
    elseif (!$action) $href .= ' onclick="return false;"';
    
    $stempl = sprintf('<a%s%s><span>%s</span></a>', $href, $this->_class?' class="'.$this->_class.'"':'', $label);
    $more = $this->_renderContains();

    $this->setTemplateString($stempl . $more . "\n");
  }

  public function insertMenuItem($item) { $this->_contains[] = $item; }

  public function getLabel() { return $this->_label; }

  public function getClassParentDiv() { return $this->_classParentDiv; }

  public function getAction() { return isset($this->_action) ? $this->_action : ''; }

  protected function _renderContains() {
    $more = '';
    foreach ($this->_contains as $one) {
      $more .= $one->render();
    }
    
    if ($more) {
      $more = '<div>'. $more .'<div class="winbug">&nbsp;</div></div>';
    }
    return $more;
  }
}

class GuiDomMenuItemTextStorage extends GuiDomMenuItem {

  public function getLabel() {
    return Application::get()->textStorage->getText($this->_label);
  }
}

?>
