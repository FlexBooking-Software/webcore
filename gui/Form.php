<?php

class GuiForm extends GuiElement {
  protected $_formElementAttributes = array('action' => '{%basefile%}', 'method' => 'post');
  protected $_divElementAttributes = array();
  protected $_hiddens = array();

  protected function _userParamsInit(&$params) {
    foreach ($params as $key => $value) {
      if (in_array($key, array('name','method','action','onsubmit','onreset','enctype'))) {
        $this->_formElementAttributes[$key] = $value;
      } else {
        switch ($key) {
          case 'formId': $this->_formElementAttributes['id'] = $value; break;
          case 'formClass': $this->_formElementAttributes['class'] = $value; break;
          case 'divId': $this->_divElementAttributes['id'] = $value; break;
          case 'divClass': $this->_divElementAttributes['class'] = $value; break;
        }
      }
    }
  }

  public function addHidden($key, $value) { $this->_hiddens[$key] = $value; }

  public function setFormElementAttribute($name, $value) { $this->_formElementAttributes[$name] = $value; }

  protected function _userRender() {
    $t = "<form". concatElementAttributes($this->_formElementAttributes) .">".
      "<div". concatElementAttributes($this->_divElementAttributes) .">\n".
      "{GuiForm_hiddens}".
      $this->getTemplate() ."\n".
      "</div></form>\n";
    $this->setTemplateString($t);

    if (!count($this->_hiddens)) {
      $this->insertTemplateVar('GuiForm_hiddens','');
    } else {
      foreach ($this->_hiddens as $key => $value) {
        $params = array(
            'template' => "<input type=\"hidden\" name=\"{key}\" value=\"{value}\" />\n",
            'vars' => array(
              'key' => $key,
              'value' => $value));
        $this->insert(new GuiElement($params), 'GuiForm_hiddens');
      }
    }
  }
}

class GuiFormItem extends GuiElement {
  protected $_id;
  protected $_title;
  protected $_label;
  protected $_labelHtmlize = true;
  protected $_value;
  protected $_classDiv;
  protected $_classLabel;
  protected $_showDiv = true;
  protected $_middle;
  protected $_middleHtmlize = true;
  protected $_item;
  protected $_onfocus;
  protected $_onblur;
  protected $_onchange;
  protected $_onclick;
  protected $_readonly;
  protected $_disabled;
  protected $_tabindex;
  protected $_labelSuffix = '<span>:</span>';
  protected $_externalDivHtml;
  protected $_insertHidden = false;
  protected $_src;
  protected $_style;
  
  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    $this->_classDiv = ifsetor($params['classDiv'], $this->_getDefaultClassDiv());
    
    if (isset($params['id'])) { $this->_id = $params['id']; }
    if (isset($params['title'])) { $this->_title = $params['title']; }
    if (isset($params['label'])) { $this->_label = $params['label']; }
    if (isset($params['labelHtmlize'])) { $this->_labelHtmlize = $params['labelHtmlize']; }
    if (isset($params['value'])) { $this->_value = $params['value']; }
    if (isset($params['showDiv'])) { $this->_showDiv = $params['showDiv']; }
    if (isset($params['classLabel'])) { $this->_classLabel = $params['classLabel']; }
    
    if (isset($params['middle'])) { $this->_middle = $params['middle']; }
    if (isset($params['middleHtmlize'])) { $this->_middleHtmlize = $params['middleHtmlize']; }
    
    if (isset($params['onfocus'])) { $this->_onfocus = $params['onfocus']; }
    if (isset($params['onblur'])) { $this->_onblur = $params['onblur']; }
    if (isset($params['onchange'])) { $this->_onchange = $params['onchange']; }
    if (isset($params['onclick'])) { $this->_onclick = $params['onclick']; }
    if (isset($params['readonly'])) { $this->_readonly = $params['readonly']; }
    if (isset($params['disabled'])) { $this->_disabled = $params['disabled']; }
    if (isset($params['tabindex'])) { $this->_tabindex = $params['tabindex']; }
    if (isset($params['labelSuffix'])) { $this->_labelSuffix = $params['labelSuffix']; }

    if (isset($params['externalDivHtml'])) { $this->_externalDivHtml = $params['externalDivHtml']; }
    if (isset($params['insertHidden'])) { $this->_insertHidden = $params['insertHidden']; }

    if (isset($params['src'])) { $this->_src = $params['src']; }
    if (isset($params['style'])) { $this->_style = $params['style']; }

    $this->insert($this->_item = new GuiElement);
  }

  protected function _getDefaultClassDiv() { return 'formItem'; }

  protected function _prepareDefaultTemplate() {
    if ($this->hasDefaultTemplate()) {
      $t = '';
      if (isset($this->_label)) {
        if (isset($this->_id) && get_class($this) != "GuiFormItem") { 
          $t .= ' for="{id}"';
        }
        if (isset($this->_classLabel)) {
          $t .= ' class="{classLabel}"';
        }
        $t = "<label".$t.">{label}". $this->_labelSuffix ."</label> ";
        $this->insertTemplateVar('label', $this->_label, $this->_labelHtmlize);
      } 

      if ($this->_insertHidden !== false && isset($this->_name)) {
        $t .= '<input type="hidden" name="{name}" value="{val}" />';
        if ($this->_insertHidden === true) {
          $val = '';
        } else {
          $val = $this->_insertHidden;
        }
        $this->insertTemplateVar('name', $this->_name);
        $this->insertTemplateVar('val', $val);
      }

      $t = ($t ? $t."\n" : '') .'{children}';
      if ($this->_showDiv) {
        $t = '<div '.
          (isset($this->_id) ? 'id="{id}Div" ' : '').
          '{externalDiv} class="{classDiv}">'.
          (!isset($this->_label) ? "\n" : '') . 
          $t .'</div>';
        $this->insertTemplateVar('classDiv', $this->_classDiv);

        $this->insertTemplateVar('externalDiv', ifsetor($this->_externalDivHtml, ''), false);
      }

      if (isset($this->_id)) {
        $this->insertTemplateVar('id', $this->_id);
      }
      if (isset($this->_classLabel)) {
        $this->insertTemplateVar('classLabel', $this->_classLabel);
      }
      
      $this->setTemplateString($t);
    } 
  }

  protected function _userRender() {
    $this->_prepareDefaultTemplate();
    
    if ($this->_middle) $this->_item->insertTemplateVar('children', $this->_middle, $this->_middleHtmlize);
    
    $gui = $this->_getItem();
    $this->_item->insert($gui);
  }

  protected function _getItem() { return new GuiElement; }
  
  public function addClassDiv($classDiv) { $this->_classDiv.=' '.$classDiv; }
}

class GuiFormInput extends GuiFormItem {
  protected $_classInput;
  protected $_type;
  protected $_checked;
  protected $_maxlength;
  protected $_externalInputHtml;
  
  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    $this->_type = ifsetor($params['type'], $this->_getDefaultType());
    $this->_classInput = ifsetor($params['classInput'], $this->_getDefaultClassInput());

		if (isset($params['maxlength'])) { $this->_maxlength = $params['maxlength']; }
    if (isset($params['checked'])) { $this->_checked = $params['checked']; }
    if (isset($params['externalInputHtml'])) { $this->_externalInputHtml = $params['externalInputHtml']; }
  }

  protected function _getDefaultType() { return 'text'; }

  protected function _getDefaultClassInput() {
    $ret = 'input'. strtoupper($this->_type[0]) . substr($this->_type,1);
    return $ret;
  }

  public function addClassInput($classInput) { $this->_classInput.=' '.$classInput; }
  
  protected function _getItem() {
    $name = $this->getName();
    $t = '<input'.
      (isset($this->_id) ? ' id="{id}"' : '').
      ' type="{type}"'.
      (isset($this->_classInput) ? ' class="{class}"' : '').
      ($name ? ' name="{name}"' : '').
      (isset($this->_value) ? ' value="{value}"' : '').
      (isset($this->_title) ? ' title="{title}"' : '').
      (isset($this->_maxlength) ? ' maxlength="{maxlength}"' : '').
      (isset($this->_onfocus) ? ' onfocus="{onfocus}"' : '').
      (isset($this->_onblur) ? ' onblur="{onblur}"' : '').
      (isset($this->_onchange) ? ' onchange="{onchange}"' : '').
      (isset($this->_onclick) ? ' onclick="{onclick}"' : '').
      (isset($this->_tabindex) ? ' tabindex="{tabindex}"' : '').
      ((isset($this->_readonly) && $this->_readonly) ? ' readonly="readonly"' : '').
      ((isset($this->_disabled) && $this->_disabled) ? ' disabled="disabled"' : '').
      ((isset($this->_checked) && $this->_checked) ? ' checked="checked"' : '').
      '{external} />';

    $external =  isset($this->_externalInputHtml) ? $this->_externalInputHtml : '';

    $input = new GuiElement(array('template' => $t));

    $input->insertTemplateVar('type', $this->_type);
    $input->insertTemplateVar('external', $external);
    if (isset($this->_id)) { $input->insertTemplateVar('id', $this->_id); }
    if (isset($this->_title)) { $input->insertTemplateVar('title', $this->_title); }
    if (isset($this->_maxlength)) { $input->insertTemplateVar('maxlength', $this->_maxlength); }
    if (isset($this->_classInput)) { $input->insertTemplateVar('class', $this->_classInput); }
    if ($name) { $input->insertTemplateVar('name', $name); }
    $input->insertTemplateVar('name2', $name);
    if (isset($this->_value)) { $input->insertTemplateVar('value', $this->_value); }
    if (isset($this->_onfocus)) { $input->insertTemplateVar('onfocus', $this->_onfocus); }
    if (isset($this->_onblur)) { $input->insertTemplateVar('onblur', $this->_onblur); }
    if (isset($this->_onchange)) { $input->insertTemplateVar('onchange', $this->_onchange); }
    if (isset($this->_onclick)) { $input->insertTemplateVar('onclick', $this->_onclick); }
    if (isset($this->_tabindex)) { $input->insertTemplateVar('tabindex', $this->_tabindex); }

    return $input;
  }
}

class GuiFormInputDate extends GuiFormInput {
  protected $_formName;
  protected $_jsFile = 'CalendarPopup.js';
  protected $_cssFile = 'CalendarPopup.css';
  protected $_jsVarName = 'calendar';
  protected $_dateFormat = 'dd.MM.yyyy';
  protected $_weekStartDay;
  protected $_todayLabel = 'Today';
  protected $_monthLabels = '"January","February","March","April","May","June","Jule","August","September","October","November","December"';
  protected $_dayLabels = '"Sun","Mon","Tue","Wed","Thu","Fri","Sat"';
  protected $_calendarDivName;
  protected $_calendarIcon;
  protected $_calendarText = 'select';
  protected $_jsAction;
  protected $_otherCalendars;

  protected function _userParamsInit(&$params) {
    if (isset($params['formName'])) { $this->_formName = $params['formName']; }

    if (isset($params['jsFile'])) { $this->_jsFile = $params['jsFile']; }
    if (isset($params['jsVarName'])) { $this->_jsVarName = $params['jsVarName']; }
    if (isset($params['cssFile'])) { $this->_cssFile = $params['cssFile']; }
    
    if (isset($params['dateFormat'])) { $this->_dateFormat = $params['dateFormat']; }
    if (isset($params['weekStartDay'])) { $this->_weekStartDay = $params['weekStartDay']; }
    if (isset($params['todayLabel'])) { $this->_todayLabel = $params['todayLabel']; }
    if (isset($params['monthLabels'])) { $this->_monthLabels = $params['monthLabels']; }
    if (isset($params['dayLabels'])) { $this->_dayLabels = $params['dayLabels']; }
    
    if (isset($params['calendarDivName'])) { $this->_calendarDivName = $params['calendarDivName']; }
    if (isset($params['calendarIcon'])) { $this->_calendarIcon = $params['calendarIcon']; }
    if (isset($params['calendarText'])) { $this->_calendarText = $params['calendarText']; }

    if (isset($params['otherCalendars'])) { $this->_otherCalendars = is_array($params['otherCalendars'])?$params['otherCalendars']:array($params['otherCalendars']); }

    $this->_jsAction = sprintf('%s.select(document.getElementsByName(\'%s\')[0],\'%s\',\'%s\'); return false;', $this->_jsVarName, $this->_name,
        $this->_name.'Icon', $this->_dateFormat);

    // kalendar se objevi i na focus do inputu, jenom je potreba rucne schovat ostatni kalendare 
    $hide = '';
    if (is_array($this->_otherCalendars)) {
      foreach ($this->_otherCalendars as $cal) {
        $hide .= sprintf("if (typeof(%s)=='object') %s.hideCalendar(); ", $cal, $cal);
      }
    }
    $params['onclick'] = $hide.$this->_jsAction;
    
    parent::_userParamsInit($params);
  }

  protected function _getItem() {
    $gui = parent::_getItem();

    $ret = new GuiElement;
    $ret->setTemplateString(sprintf('
        {input}
        <a href="#" name="%s" id="%s" class="calendarIcon" 
          onclick="%s">
          {icon}
        </a>
        {div}', $this->_name.'Icon', $this->_name.'Icon', $this->_readonly?'return false;':$this->_jsAction));
    $ret->insert($gui, 'input');
   
    // ikonka nebo text HREF 
    if ($this->_calendarIcon) {
      $ret->insertTemplateVar('icon', '<img src="'.$this->_calendarIcon.'" />', false);
    } else {
      $ret->insertTemplateVar('icon', $this->_calendarText);
    }
    
    // kdyz je calendar v DIVu
    if ($this->_calendarDivName) {
      $ret->insertTemplateVar('div', 
         '<div id="'.$this->_calendarDivName.'" 
             style="z-index:1;position:fixed;visibility:hidden;background-color:white;">
          </div>', false);
    } else {
      $ret->insertTemplateVar('div', '');
    }

    $document = Application::get()->document;
    $document->addJavascriptFile($this->_jsFile, true);
    $document->addCssFile($this->_cssFile, null, true);
    $document->addJavascript(sprintf('var %s = new CalendarPopup%s;', $this->_jsVarName, 
          ($this->_calendarDivName?'("'.$this->_calendarDivName.'")':'')));

    if ($this->_weekStartDay) { $document->addJavascript($this->_jsVarName.'.setWeekStartDay('.$this->_weekStartDay.');'); }
    if ($this->_todayLabel) { $document->addJavascript($this->_jsVarName.'.setTodayText("'.$this->_todayLabel.'");'); }
    if ($this->_dayLabels) { $document->addJavascript($this->_jsVarName.'.setDayHeaders('.$this->_dayLabels.');'); }
    if ($this->_monthLabels) { $document->addJavascript($this->_jsVarName.'.setMonthNames('.$this->_monthLabels.');'); }

    return $ret;
  }
}

class GuiFormButton extends GuiFormInput {
  protected $_action;
  protected $_actionParam;
  protected $_templateBegin;
  protected $_templateEnd;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    if (isset($params['action'])) { $this->_action = $params['action']; }
    if (isset($params['actionParam'])) { $this->_actionParam = $params['actionParam']; }
    if (isset($params['templateBegin'])) { $this->_templateBegin = $params['templateBegin']; }
    if (isset($params['templateEnd'])) { $this->_templateEnd = $params['templateEnd']; }
  }

  protected function _getDefaultType() { return 'submit'; }

  protected function _getDefaultClassDiv() { return 'formButton'; }

  protected function _prepareDefaultTemplate() {
    if ($this->hasDefaultTemplate()) {
      $t = '{children}';
      
      if ($this->_showDiv) {
        $t = '<div '.
        (isset($this->_id) ? 'id="{id}Div" ' : '').
        'class="{classDiv}">'."\n". $t .'</div>';
        $this->insertTemplateVar('classDiv', $this->_classDiv);
      }

      if (isset($this->_id)) {
        $this->insertTemplateVar('id', $this->_id);
      }

      $this->setTemplateString($t);
    } 
  }
 
  protected function _getItem() {
    $params = array();
    $params['type'] = $this->_type;
    if (isset($this->_id)) { $params['id'] = $this->_id; }
    if (isset($this->_title)) { $params['title'] = $this->_title; }
    $params['class'] = $this->_classInput;
    if (isset($this->_label)) { $params['label'] = $this->_label; }
    if (isset($this->_action)) { $params['action'] = $this->_action; }
    elseif (isset($this->_name)) { $params['name'] = $this->_name; }
    if (isset($this->_onclick)) { $params['onclick'] = $this->_onclick; }
    if (isset($this->_src)) { $params['src'] = $this->_src; }
    if (isset($this->_style)) { $params['style'] = $this->_style; }
    if (isset($this->_actionParam)) { $params['actionParam'] = $this->_actionParam; }
    if ($params['type'] == 'text') {
      if (isset($this->_templateBegin)) { $params['templateBegin'] = $this->_templateBegin; }
      if (isset($this->_templateEnd)) { $params['templateEnd'] = $this->_templateEnd; }
      $ret = new GuiTextButton($params);
    } else {
      $ret = new GuiInputButton($params);
    }
    return $ret;
  }
}

class GuiFormSelect extends GuiFormItem {
  protected $_dataSource;
  protected $_selectElementAttributes;
  protected $_firstOption;
  protected $_blackList;
  protected $_whiteList;
  protected $_htmlize;
  protected $_multiple;
  protected $_size;
  protected $_useTextStorage;
  protected $_classInput;
  protected $_classOptions;
  protected $_insertSelectedVars;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    $this->_dataSource = $params['dataSource'];
    if (isset($params['selectElementAttributes'])) { $this->_selectElementAttributes = $params['selectElementAttributes']; }
    if (isset($params['firstOption'])) { $this->_firstOption = $params['firstOption']; }
    if (isset($params['blackList'])) { $this->_blackList = $params['blackList']; }
    if (isset($params['whiteList'])) { $this->_whiteList = $params['whiteList']; }
    if (isset($params['htmlize'])) { $this->_htmlize = $params['htmlize']; }
    if (isset($params['multiple'])) { $this->_multiple = $params['multiple']; }
    if (isset($params['size'])) { $this->_size = $params['size']; }
    if (isset($params['useTextStorage'])) { $this->_useTextStorage = $params['useTextStorage']; }
    if (isset($params['classInput'])) { $this->_classInput = $params['classInput']; }
    if (isset($params['classOptions'])) { $this->_classOptions = $params['classOptions']; }
    if (isset($params['insertSelectedVars'])) { $this->_insertSelectedVars = $params['insertSelectedVars']; }
  }

  protected function _getItem() {
    $params = array();

    $params['dataSource'] = $this->_dataSource;

    if (isset($this->_id)) { $params['id'] = $this->_id; }
    if (isset($this->_name)) { $params['name'] = $this->_name; }
    if (isset($this->_value)) { $params['value'] = $this->_value; }
    if (isset($this->_onfocus)) { $params['onfocus'] = $this->_onfocus; }
    if (isset($this->_onblur)) { $params['onblur'] = $this->_onblur; }
    if (isset($this->_onchange)) { $params['onchange'] = $this->_onchange; }
    if (isset($this->_onclick)) { $params['onclick'] = $this->_onclick; }
    if (isset($this->_tabindex)) { $params['tabindex'] = $this->_tabindex; }
    if (isset($this->_readonly)) { $params['readonly'] = $this->_readonly; }
    if (isset($this->_disabled)) { $params['disabled'] = $this->_disabled; }
    if (isset($this->_classInput)) { $params['class'] = $this->_classInput; }
    if (isset($this->_selectElementAttributes)) {
      foreach ($this->_selectElementAttributes as $key => $value) {
        $params[$key] = $value;
      }
    }
    if (isset($this->_firstOption)) { $params['firstOption'] = $this->_firstOption; }
    if (isset($this->_blackList)) { $params['blackList'] = $this->_blackList; }
    if (isset($this->_whiteList)) { $params['whiteList'] = $this->_whiteList; }
    if (isset($this->_htmlize)) { $params['htmlize'] = $this->_htmlize; }
    if (isset($this->_multiple)) { $params['multiple'] = $this->_multiple; }
    if (isset($this->_size)) { $params['size'] = $this->_size; }
    if (isset($this->_useTextStorage)) { $params['useTextStorage'] = $this->_useTextStorage; }
    if (isset($this->_classOptions)) { $params['classOptions'] = $this->_classOptions; }
    if (isset($this->_insertSelectedVars)) { $params['insertSelectedVars'] = $this->_insertSelectedVars; }

    $select = new GuiDataSourceSelect($params);
    return $select;
  }

  public function addClassInput($classInput) { $this->_classInput .= ' ' . $classInput; }
}

class GuiFormTextarea extends GuiFormItem {
  protected $_rows;
  protected $_cols;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    $this->_rows = $params['rows'];
    $this->_cols = $params['cols'];
  }

  protected function _getItem() {
    $name = $this->getName();

    $t = '<textarea'.
      (isset($this->_id) ? ' id="{id}"' : '').
      (isset($this->_classInput) ? ' class="{class}"' : '').
      ($name ? ' name="{name}"' : '').
      ' rows="{rows}" cols="{cols}"'.
      (isset($this->_onfocus) ? ' onfocus="{onfocus}"' : '').
      (isset($this->_onblur) ? ' onblur="{onblur}"' : '').
      (isset($this->_onchange) ? ' onchange="{onchange}"' : '').
      (isset($this->_onclick) ? ' onclick="{onclick}"' : '').
      (isset($this->_tabindex) ? ' tabindex="{tabindex}"' : '').
      ((isset($this->_readonly) && $this->_readonly) ? ' readonly="readonly"' : '').
      ((isset($this->_disabled) && $this->_disabled) ? ' disabled="disabled"' : '').
      '>'.
      (isset($this->_value) ? '{value}' : '').
      '</textarea>';

    $external =  isset($this->_externalInputHtml) ? $this->_externalInputHtml : '';

    $textarea = new GuiElement(array('template' => $t));

    $textarea->insertTemplateVar('rows', $this->_rows);
    $textarea->insertTemplateVar('cols', $this->_cols);
    if (isset($this->_id)) { $textarea->insertTemplateVar('id', $this->_id); }
    if (isset($this->_classInput)) { $textarea->insertTemplateVar('class', $this->_classInput); }
    if ($name) { $textarea->insertTemplateVar('name', $name); }
    if (isset($this->_value)) { $textarea->insertTemplateVar('value', $this->_value); }
    if (isset($this->_onfocus)) { $textarea->insertTemplateVar('onfocus', $this->_onfocus); }
    if (isset($this->_onblur)) { $textarea->insertTemplateVar('onblur', $this->_onblur); }
    if (isset($this->_onchange)) { $textarea->insertTemplateVar('onchange', $this->_onchange); }
    if (isset($this->_onclick)) { $textarea->insertTemplateVar('onclick', $this->_onclick); }
    if (isset($this->_tabindex)) { $textarea->insertTemplateVar('tabindex', $this->_tabindex); }

    return $textarea;
  }
}

?>
