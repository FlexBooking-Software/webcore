<?php

abstract class GuiButton extends GuiElement {
  protected $_id;
  protected $_title;
  protected $_url;
  protected $_action; 
  protected $_actionParam;
  protected $_target;
  protected $_label;
  protected $_onclick;
  protected $_urlString = '';
  protected $_paramsString = '';
  protected $_class;
  protected $_computeInt;
  protected $_src;
  protected $_style;

  protected function _userParamsInit(&$params) {
    if (isset($params['id'])) { $this->_id = $params['id']; }
    if (isset($params['title'])) { $this->_title = $params['title']; }
    if (isset($params['url'])) { $this->_url = $params['url']; }
    if (isset($params['action'])) { $this->_action = $params['action']; }
    if (isset($params['actionParam'])) { $this->_actionParam = $params['actionParam']; }
    if (isset($params['target'])) { $this->_target = $params['target']; }
    if (isset($params['onclick'])) { $this->_onclick = $params['onclick']; }
    if (isset($params['label'])) { $this->_label = $params['label']; }
    if (isset($params['class'])) { $this->_class = $params['class']; }
    if (isset($params['src'])) { $this->_src = $params['src']; }
    if (isset($params['style'])) { $this->_style = $params['style']; }
    if (isset($params['accesskey'])) { $this->_accesskey = $params['accesskey']; }
    if (isset($params['bracketsSpan'])) { 
       $this->_templateBegin = '<span class="brackets">'.$this->_templateBegin.'</span>'; 
       $this->_templateEnd = '<span class="brackets">'.$this->_templateEnd.'</span>';
    }
  }

  protected function _userRender() {
    $this->_urlString = Application::get()->getBaseName();
    
    $params = array();
    if (isset($this->_actionParam)) {
      $actionParam = is_array($this->_actionParam) ? $this->_actionParam : array('action_param' => $this->_actionParam);
      $params = array_merge($params, $actionParam);
    }
    if (isset($this->_action)) {
      $params = array_merge(array('action' => $this->_action), $params);
    }

    $this->_paramsString = Application::get()->response->getUrlParams($params,null,'&');
    if (!Application::get()->session->getUseCookie()) {
      $this->_paramsString .= '&amp;{%sessname%}={%sessid%}';
    }

    $result = $this->_renderButton();
    $this->setTemplateString($result);
  }

  abstract protected function _renderButton();
}

class GuiImgButton extends GuiButton {
  private $_imgsrc; 
  private $_imgclass;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    if (isset($params['imgsrc'])) { $this->_imgsrc = $params['imgsrc']; }
    if (isset($params['imgclass'])) { $this->_imgclass = $params['imgclass']; }
  }

  protected function _renderButton() {
    $url = null;
    if (isset($this->_url)) { 
      $url = $this->_url;
    } elseif (isset ($this->_action)) {
      $url = $this->_urlString;
    }
    if (isset($url) && $this->_paramsString) {
      $url .= '?'. $this->_paramsString;
    }
    $result="<a".
      (isset($this->_id) ? ' id="'. $this->_id .'"' : '').
      (isset($url) ? ' href="'. $url . '"' : '').
      (isset($this->_class) ? ' class="'. $this->_class .'"' : '').
      (isset($this->_title) ? ' title="'. $this->_title .'"' : '').
      (isset($this->_target) ? ' target="'. $this->_target . '"' : '').
      (isset($this->_onclick) ? ' onclick="'. $this->_onclick . '"' : '').
      (isset($this->_accesskey) ? ' accesskey="'. $this->_accesskey . '"' : '').
      ">".
      "<img class=\"guiimgbutton"." ".$this->_imgclass."\" alt=\"" . $this->_label . "\" src=\"" . $this->_imgsrc . "\"  />".
      "</a>";

    return $result;
  }
}

class GuiTextButton extends GuiButton {
  protected $_templateBegin = '[';
  protected $_templateEnd = ']';
  protected $_htmlize = true;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    if (isset($params['templateBegin'])) { $this->_templateBegin = $params['templateBegin']; }
    if (isset($params['templateEnd'])) { $this->_templateEnd = $params['templateEnd']; }
    if (isset($params['htmlize'])) { $this->_htmlize = $params['htmlize']; }
  }

  protected function _renderButton() {
    $url = null;
    if (isset($this->_url)) { 
      $url = $this->_url;
    } elseif (isset ($this->_action)) {
      $url = $this->_urlString;
    }
    if (isset($url) && $this->_paramsString) {
      $url .= '?'. $this->_paramsString;
    }
    $result = $this->_templateBegin .'<a'.
      (isset($this->_id) ? ' id="'. $this->_id .'"' : '').
      (isset($this->_class) ? ' class="'. $this->_class .'"' : '').
      (isset($url) ? ' href="'. $url .'"' : '').
      (isset($this->_title) ? ' title="'. $this->_title .'"' : '').
      (isset($this->_target) ? ' target="'. $this->_target . '"' : '').
      (isset($this->_onclick) ? ' onclick="'. $this->_onclick . '"' : '').
      '>{label}</a>'. $this->_templateEnd;

    $this->insertTemplateVar('label', $this->_label, $this->_htmlize);

    return $result;
  }
}

class GuiInputButton extends GuiButton {
  protected $_type;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    $this->_type = ifsetor($params['type'], 'submit');
  }

  protected function _renderButton() {
    $t = '<input'.
      (isset($this->_id) ? ' id="{id}"' : '').
      ' type="{type}"'.
      (isset($this->_class) ? ' class="{class}"' : '').
      (isset($this->_action) ? ' name="action_{action}"' : (isset($this->_name) ? ' name="{name}"' : '')).
      (isset($this->_label) ? ' value="{label}"' : '').
      (isset($this->_title) ? ' title="{title}"' : '').
      (isset($this->_src) ? ' src="{src}"' : '').
      (isset($this->_style) ? ' style="{style}"' : '').
      (isset($this->_onclick) ? ' onclick="{onclick}"' : '').
      ' />';

    $this->insertTemplateVar('type', $this->_type);
    if (isset($this->_id)) { $this->insertTemplateVar('id', $this->_id); }
    if (isset($this->_title)) { $this->insertTemplateVar('title', $this->_title); }
    if (isset($this->_class)) { $this->insertTemplateVar('class', $this->_class); }
    if (isset($this->_action)) { $this->insertTemplateVar('action', $this->_action); }
    elseif (isset($this->_name)) { $this->insertTemplateVar('name', $this->_name); }
    if (isset($this->_label)) { $this->insertTemplateVar('label', $this->_label); }
    if (isset($this->_onclick)) { $this->insertTemplateVar('onclick', $this->_onclick); }
    if (isset($this->_src)) { $this->insertTemplateVar('src', $this->_src); }
    if (isset($this->_style)) { $this->insertTemplateVar('style', $this->_style); }
    
    if (is_array($this->_actionParam)) {
      $t .= $this->_recursiveVal($this->_actionParam);
    }
    return $t;
  }
  
  private function _recursiveVal($pole ,$predpona=''){
    $out = '';
    $c = &$this->_computeInt;
    if (is_array($pole)){
      foreach ($pole as $klic => $hodnota){
        if (!Empty($predpona))$klic="[$klic]";
        $out .=$this->_recursiveVal($hodnota,$predpona.$klic);
      }
    }
    else {
      $out.= "\n<input type=\"hidden\" name=\"{name$c}\" value=\"{value$c}\" />";
       $this->insertTemplateVar("name$c", $predpona);
       $this->insertTemplateVar("value$c",$pole);       
       $c++;
       }
    return $out;
  }
}

class GuiSelect extends GuiElement {
  protected $_value;
  protected $_elementAttributes = array();
  protected $_firstOption;
  protected $_blackList;
  protected $_whiteList;
  protected $_htmlize;
  protected $_useTextStorage;
  protected $_readonly;
  protected $_classOptions;
  protected $_insertSelectedVars;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    $this->_value = ifsetor($params['value'], '');
    $this->_firstOption = ifsetor($params['firstOption'], null);
    $this->_blackList = ifsetor($params['blackList'], null);
    $this->_whiteList = ifsetor($params['whiteList'], null);
    $this->_htmlize = ifsetor($params['htmlize'], true);
    $this->_useTextStorage = ifsetor($params['useTextStorage'], false);
    $this->_classOptions = ifsetor($params['classOptions'], array());
    $this->_insertSelectedVars = ifsetor($params['insertSelectedVars'], false);

    foreach ($params as $key => $value) {
      if (in_array($key, array('id','class','size','onfocus','onblur','onchange','onclick','tabindex'))) {
        $this->_elementAttributes[$key] = $value;
      } elseif (in_array($key, array('multiple','disabled'))) {
        if ($value) {
          $this->_elementAttributes[$key] = $key;
        }
      }
    }
    $this->_readonly = ifsetor($params['readonly'], false);

  }

  protected function _userRender() {
    $t = "<select name=\"{name}\"{elementAttributes}>\n{options}\n</select>";
    $this->setTemplateString($t);

    $this->insertTemplateVar('name', $this->_name);
    $this->insertTemplateVar('elementAttributes', concatElementAttributes($this->_elementAttributes), false);
    $this->insertTemplateVar('options', '');

    if (isset($this->_firstOption) && !$this->_readonly) {
      $this->insertTemplateVar('options', sprintf("<option value=\"\">%s</option>\n", $this->_firstOption), false);
    }

    $this->_insertOptions();
  }

  protected function _insertOptions() { }

}

class GuiHashSelect extends GuiSelect {
  protected $_hash; 

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    $this->_hash = ifsetor($params['hash'], array());
  }

  protected function _insertOptions() {
    if ($this->_readonly) {
      $res = $this->_makeOptions(array($this->_value => ifsetor($this->_hash[$this->_value], '')), 0, '');
    } else {
      $res = $this->_makeOptions($this->_hash, 0, '');
    }
    $this->insertTemplateVar('options', $res, false);
  }

  protected function _makeOptions($hash, $doOptGroup, $optGroupLabel) {
    $result = "";
    if ($doOptGroup) { $result .= "<optgroup label=\"$optGroupLabel\">\n"; }
    foreach($hash as $k => $v) {
      if ($v == "") { $v = $k; }
      if (is_array($v)) {
        $result .= $this->_makeOptions($v, 1, $k);
      } else { 
        if (is_array($this->_blackList) && in_array($k, $this->_blackList)) {
          continue;
        }

        if (is_array($this->_whiteList) && !in_array($k, $this->_whiteList)) {
          continue;
        }

        $selected = '';
        if (is_array($this->_value)) {
          if (in_array($k, $this->_value)) { $selected = ' selected="selected"'; }
        } else {
          if (!strcmp($k, $this->_value)) { $selected = ' selected="selected"'; }
        }

        if ($this->_useTextStorage) {
          $v = $this->_app->textStorage->getText($v);
        }
        if ($this->_htmlize) {
          $k = Application::get()->htmlspecialchars($k);
          $v = Application::get()->htmlspecialchars($v);
        }
        if (isset($this->_classOptions[$k])) {
          $class = ' class="'. $this->_classOptions[$k] .'"';
        } else {
          $class = '';
        }
        if ($this->_insertSelectedVars) {
          $selectedVars = '{selected'. (is_string($this->_insertSelectedVars) ? $this->_insertSelectedVars : '') . $k .'}';
        } else {
          $selectedVars = '';
        }
        $result .= "<option value=\"$k\"$selected$class$selectedVars>$v</option>\n";
      }
    }
    if ($doOptGroup) { $result .= "</optgroup>\n"; }
    return $result;
  }
}

class GuiDbSelect extends GuiHashSelect {
  protected $_sql; 

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    $this->_sql = $params['sql'];
  }

  protected function _insertOptions() {
    $db = $this->_app->db;

    $res = $db->doQuery($this->_sql);
    while ($row = $db->FetchRow($res)) {
      $this->_hash[$row[1]] = ($row[0] == '') ? $row[1] : $row[0];
    }

    parent::_insertOptions();
  }
}

class GuiDataSourceSelect extends GuiHashSelect {
  protected $_dataSource;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    $this->_dataSource = $params['dataSource'];
  }

  protected function _insertOptions() {
    $this->_dataSource->reset();
    $data =& $this->_dataSource->currentData;
    while (is_array($data)) {
      $this->_hash[array_shift($data)] = array_shift($data);
      $this->_dataSource->nextData();
    }
    parent::_insertOptions();
  }
}

?>
