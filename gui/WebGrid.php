<?php

class WebGridSettings extends GridSettings {
  protected $_guiGridFilterReadOnly = false;
  protected $_guiGridFilterPrintAction = null;
  protected $_guiGridFilterPrintActionParams = array();

  protected function _initSettings() {
    parent::_initSettings();

    $this->addRow($rowHeader = new GridRow('header'));

    $rotates = array($rowData1 = new GridRow('data1'), $rowData2 = new GridRow('data2'));
    $this->addRow($rowData = new GridRowRotate('data', $rotates));

    $rowHeader->addElementAttribute('class','Header');
    $rowData1->addElementAttribute('class','Odd');
    $rowData2->addElementAttribute('class','Even');

    $this->addGuiGridTableAttribute('class', 'gridTable');
    $this->addGuiGridFilterDivAttribute('class', 'gridFilterForm');
    $this->addGuiGridPagerDivAttribute('class', 'gridPagerForm');
    $this->addGuiGridMultiactionDivAttribute('class', 'gridMultiactionForm');

    $this->setGuiGridPagerLabel(Application::get()->textStorage->getText('label.grid_pager'));
    $this->setGuiGridPagerButtonLabel(Application::get()->textStorage->getText('button.grid_pager'));
    $this->setGuiGridFilterButtonLabelSubmit(Application::get()->textStorage->getText('button.grid_filterSubmit'));
    $this->setGuiGridFilterButtonLabelReset(Application::get()->textStorage->getText('button.grid_filterReset'));
    $this->addGuiGridPagerDivPagingAttribute('class','pages');
  }

  public function setGuiGridFilterReadOnly($readOnly) { $this->_guiGridFilterReadOnly = $readOnly; }
  public function &getGuiGridFilterReadOnly() { return $this->_guiGridFilterReadOnly; }

  public function setGuiGridFilterPrintAction($action) { $this->_guiGridFilterPrintAction = $action; }
  public function &getGuiGridFilterPrintAction() { return $this->_guiGridFilterPrintAction; }

  public function setGuiGridFilterPrintActionParams($params) { $this->_guiGridFilterPrintActionParams = $params; }
  public function &getGuiGridFilterPrintActionParams() { return $this->_guiGridFilterPrintActionParams; }

  public function &getGuiGridFilterSettings() {
    $params = parent::getGuiGridFilterSettings();

    $params['readOnly'] =& $this->_guiGridFilterReadOnly;
    $params['resetPage'] = true;

    if ($this->_guiGridFilterPrintAction) {
      $app = Application::get();

      $params['printUrl'] = sprintf('%s?action=%s&%s', $app->getBaseName(), $this->_guiGridFilterPrintAction, $app->session->getTagForUrl(false));
      $this->_guiGridFilterPrintActionParams['gridClass'] = $this->_gridClass;
      $this->_guiGridFilterPrintActionParams['gridName'] = $this->_name;
      foreach ($this->_guiGridFilterPrintActionParams as $i=>$k) {
        $params['printUrl'] .= sprintf('&%s=%s', $i, $k);
      }
    }

    return $params;
  }
}

class GuiWebGrid extends GuiGrid {
  protected $_noDataLabel = '{__label.grid_noData}';
  protected $_showFilter = true;      
  protected $_showPager = true;   
  
  protected $_doubleClickAction = null;
  protected $_doubleClickColumn = null;
  protected $_doubleClickVarName = 'id';

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);

    if (isset($params['showFilter'])) $this->_showFilter = $params['showFilter'];
    if (isset($params['showPager'])) $this->_showPager = $params['showPager'];
    
    if (isset($params['doubleClickAction'])) $this->_doubleClickAction = $params['doubleClickAction'];
    if (isset($params['doubleClickColumn'])) $this->_doubleClickColumn = $params['doubleClickColumn'];
    if (isset($params['doubleClickVarName'])) $this->_doubleClickVarName = $params['doubleClickVarName'];
    
    if (isset($params['noDataLabel'])) $this->_noDataLabel = $params['noDataLabel'];

    if (!$this->_name) $this->_name = get_class($this);
  }

  public function setPrintVersion() {
    $this->_showPager = false;
    $this->_settings->setOnPage(null);
    
    $this->_settings->setGuiGridFilterReadOnly(true);
    
    $columnsMask = $this->_settings->getColumnsMask();
    foreach ($columnsMask as $i=>$k) {
      if ($k == 'action') unset($columnsMask[$i]);
    }
    $this->_settings->setColumnsMask($columnsMask);
  }

  public function getRowsNum() {
    $this->_dataSource->reset();
    return $this->_dataSource->records;
  }

  public function getCurrentRowData() {
    return $this->_dataSource->currentData;
  }
  
  public function exportData() {
    $this->_settings->setOnPage(null);
    
    $this->_initRow();
    
    return $this->_guiGridRow->exportData();
  }

  protected function _getEmptyTemplate() {
    return sprintf('
        {filter}
        <div class="gridTable">
        <div class="gridNoData">%s</div>
        </div>', $this->_noDataLabel);
  }

  protected function _getNonEmptyTemplate() {
    return sprintf('
        {filter}
        <div class="gridTable">
          {table}
        </div>
        {multiactionForm}
        {pager}
        <div class="cleaner"></div>');
  }

  protected function _userRender() {
    $this->_dataSource->reset();
    if ($this->_dataSource->records) {
      $this->setTemplateString('{children}'.$this->_getNonEmptyTemplate());
    } else {
      $this->setTemplateString('{children}'.$this->_getEmptyTemplate());
    }
    parent::_userRender();

    $this->_insertDoubleClickForm();
  }

  protected function _insertFilter() {
    if ($this->_showFilter) {
      $this->insert(new GuiWebGridFilter($this->_settings->getGuiGridFilterSettings()), 'filter');
    } else {
      $this->insertTemplateVar('filter', '');
    }
  }

  protected function _insertPager() {
    if ($this->_showPager) {
      $this->insert(new GuiWebGridPager($this->_settings->getGuiGridPagerSettings()), 'pager');
    } else {
      $this->insertTemplateVar('pager', '');
    }
  }

  protected function _createDoubleClickForm() {
    $gui = new GuiElement(array('template'=>sprintf('
          <form action="index.php" method="post" style="display:none;" >
            %s
            <input type="hidden" id="fi_%s_%s" name="%s" value="" />
            <input id="fi_%s_doubleClickSubmit" type="submit" name="action_%s" />
          </form>
          ', $this->_app->session->getTagForForm(), $this->_name, $this->_doubleClickVarName, $this->_doubleClickVarName, $this->_name, $this->_doubleClickAction)));
    return $gui;
  }

  protected function _insertDoubleClickForm() {
    if ($this->_doubleClickAction&&$this->_doubleClickColumn) {
      $gui = $this->_createDoubleClickForm();
      $this->insert($gui);
    }
  }

  protected function _getRowParams() {
    $params = parent::_getRowParams();
    
    if ($this->_doubleClickAction&&$this->_doubleClickColumn) {
      $params['doubleClickButton'] = sprintf('fi_%s_doubleClickSubmit', $this->_name);
      $params['doubleClickInput'] = sprintf('fi_%s_%s', $this->_name, $this->_doubleClickVarName);
      $params['doubleClickColumn'] = $this->_doubleClickColumn;
    }

    return $params;
  }

  protected function _initRow() {
    $params = $this->_getRowParams();

    $this->_guiGridRow = new GuiWebGridRow($params);
  }
}


class GuiWebGridRow extends GuiGridRow {
  protected $_doubleClickButton = null;
  protected $_doubleClickInput = null;
  protected $_doubleClickColumn = null;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);

    if (isset($params['doubleClickButton'])) $this->_doubleClickButton = $params['doubleClickButton'];
    if (isset($params['doubleClickInput'])) $this->_doubleClickInput = $params['doubleClickInput'];
    if (isset($params['doubleClickColumn'])) $this->_doubleClickColumn = $params['doubleClickColumn'];
  }

  protected function _preRenderRow($name, &$data, &$attributes) {
    $attributes['onmouseover'] = "addClass(this,'hOver');";
    $attributes['onmouseout'] = "removeClass(this,'hOver');";

    if ($this->_doubleClickButton&&$this->_doubleClickInput&&$this->_doubleClickColumn&&isset($data[$this->_doubleClickColumn])) {
      $attributes['ondblclick'] = sprintf('return mySubmit(\'%s\', \'%s\', \'%s\');', $this->_doubleClickButton, $this->_doubleClickInput, $data[$this->_doubleClickColumn]);
    }
  }
}

class GuiWebGridPager extends GuiGridPager {
  protected $_urlPrefix = '';
  protected $_pagesArround = 4;
  protected $_showForm = true;
  protected $_showLeftLeft = true;
  protected $_showLeft = true;
  protected $_showRightRight = true;
  protected $_showRight = true;
  protected $_pageLeftLeftImgSrc = 'img/pager_leftleft.gif';
  protected $_pageLeftImgSrc = 'img/pager_left.gif';
  protected $_pageRightImgSrc = 'img/pager_right.gif';
  protected $_pageRightRightImgSrc = 'img/pager_rightright.gif';

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);

    if (isset($params['urlPrefix'])) $this->_urlPrefix = $params['urlPrefix']; 
    if (isset($params['showForm'])) $this->_showForm = $params['showForm']; 
    if (isset($params['showLeftLeft'])) $this->_showLeftLeft = $params['showLeftLeft'];
    if (isset($params['showLeft'])) $this->_showLeft = $params['showLeft'];
    if (isset($params['showRight'])) $this->_showRight = $params['showRight'];
    if (isset($params['showRightRight'])) $this->_showRightRight = $params['showRightRight'];
    if (isset($params['pageLeftLeftImgSrc'])) $this->_pageLeftLeftImgSrc = $params['pageLeftLeftImgSrc'];
    if (isset($params['pageLeftImgSrc'])) $this->_pageLeftImgSrc = $params['pageLeftImgSrc'];
    if (isset($params['pageRightImgSrc'])) $this->_pageRightImgSrc = $params['pageRightImgSrc'];
    if (isset($params['pageRightRightImgSrc'])) $this->_pageRightRightImgSrc = $params['pageRightRightImgSrc'];
  }

  protected function _userRender() {
    $arrows = $this->_navigationArrows;
    $page = $this->_page;
    $pagesArround = floor($this->_pagesArround / 2);
    $lastPage = floor($this->_records / $this ->_onPage);
    if ($this->_records % $this ->_onPage > 0) $lastPage++;
    $pager = '';
    $session = $this->_app->session->getTagForUrl();
    if ($arrows){
      $class = ($page > 1) ? '' : ' nohref';
      if ($this->_showLeftLeft) {
        $pager .= '<a'.
          ($class ? '' : ' href="{urlPrefix}{%basefile%}?action='. $this->_exec .'&amp;gridname='. $this->_gridName .'&amp;gridclass='. $this->_gridClass .'&amp;page=1' . $session . '"').
          ' class="arrowFirst'. $class .'">'.($this->_pageLeftLeftImgSrc?sprintf('<img src="%s" />', $this->_pageLeftLeftImgSrc):'|&lt;').'</a>'."\n";
      }
      if ($this->_showLeft) {
        $pager .= '<a'.
          ($class ? '' : ' href="{urlPrefix}{%basefile%}?action='. $this->_exec .'&amp;gridname='. $this->_gridName .'&amp;gridclass='. $this->_gridClass .'&amp;page='. ($page - 1) . $session . '"').
          ' class="arrowPrevious'. $class .'">'.($this->_pageLeftImgSrc?sprintf('<img src="%s" />', $this->_pageLeftImgSrc):'&lt;').'</a>'."\n";
      }
    }

    if ($page > $pagesArround+1) {
      $pager .= '<span class="separator">&nbsp;</span>';
      $pager .= '<a href="{urlPrefix}{%basefile%}?action='. $this->_exec .'&amp;gridname='. $this->_gridName .'&amp;gridclass='. $this->_gridClass .'&amp;page=1' . $session .'">1</a>'."\n";
      if ($page > $pagesArround + 2) {
        $pager .= '<span class="separator">&nbsp;..&nbsp;</span>';
      }
    }

    for ($i = $page-2; $i <= $page+2; $i++) {
      if ($i < 1 || $i > $lastPage) continue;
      $pager .= '<span class="separator">&nbsp;</span>';
      if ($i == $page) {
        $link = '<span class="actualPage">'.$i.'</span>'."\n";
      } else {
        $link = '<a href="{urlPrefix}{%basefile%}?action='. $this->_exec .'&amp;gridname='. $this->_gridName .'&amp;gridclass='. $this->_gridClass .'&amp;page='. $i . $session .'">'. $i .'</a>'."\n";
      }
      $pager .= $link;
    }

    if ($page <= $lastPage-$pagesArround-1) {
      if ($page <= $lastPage-$pagesArround-2 ) {
        $pager .= '<span class="separator">&nbsp;..&nbsp;</span>';
      }
      $pager .= '<span class="separator">&nbsp;</span>';
      $pager .= '<a href="{urlPrefix}{%basefile%}?action='. $this->_exec .'&amp;gridname='. $this->_gridName .'&amp;gridclass='. $this->_gridClass .'&amp;page='. $lastPage . $session .'">'. $lastPage .'</a>'."\n";
    }

    if ($arrows){
      $class = ($page < $lastPage) ? '' : ' nohref';
      if ($this->_showRight) {
        $pager .= '<a'.
          ($class ? '' : ' href="{urlPrefix}{%basefile%}?action='. $this->_exec .'&amp;gridname='. $this->_gridName .'&amp;gridclass='. $this->_gridClass .'&amp;page='. ($page + 1) . $session . '"').
          ' class="arrowNext'. $class .'">'.($this->_pageRightImgSrc?sprintf('<img src="%s" />', $this->_pageRightImgSrc):'&gt;').'</a>'."\n";
      }
      if ($this->_showRightRight) {
        $pager .= '<a'.
          ($class ? '' : ' href="{urlPrefix}{%basefile%}?action='. $this->_exec .'&amp;gridname='. $this->_gridName .'&amp;gridclass='. $this->_gridClass .'&amp;page='. $lastPage . $session . '"').
          ' class="arrowLast'. $class .'">'.($this->_pageRightRightImgSrc?sprintf('<img src="%s" />', $this->_pageRightRightImgSrc):'&gt;|').'</a>'."\n";
      }
    }
    $t = $pager;
    $eaPagerDiv = concatElementAttributes($this->_pagerDivAttributes);
    $eaPagingDiv = concatElementAttributes($this->_pagingDivAttributes);
    $eaOnPageDiv = concatElementAttributes($this->_onPageDivAttributes);
    $t = "<div$eaPagerDiv><div$eaPagingDiv>\n$t</div>\n{form}</div>";
    $this->setTemplateString($t);
    $this->insertTemplateVar('urlPrefix', $this->_urlPrefix);

    if ($this->_showForm) {
      $form = new GuiElement(array('template'=> 
            "<form action=\"{urlPrefix}{%basefile%}\" method=\"post\"><div$eaOnPageDiv>\n".
            $this->_app->session->getTagForForm()."\n".
            "<input type=\"hidden\" name=\"gridname\" value=\"". $this->_gridName ."\" />\n".
            "<input type=\"hidden\" name=\"gridclass\" value=\"". $this->_gridClass ."\" />\n".
            "<input type=\"hidden\" name=\"action\" value=\"eGrid\" />\n".
            "&nbsp;&nbsp;&nbsp;&nbsp;".
            "{inputText}\n".
            "{inputSubmit}\n</div></form>"));

      $label = array();
      if (!is_null($this->_pagerLabel)) $label['label'] = $this->_pagerLabel;
      $form->insert(new GuiFormInput(array_merge($label,array(
                'showDiv' => false,
                'name' => 'onPage',
                'value' => $this->_onPage))), 'inputText');

      $form->insert(new GuiFormButton(array(
              'showDiv' => false,
              'action' => $this->_exec,
              'label' => $this->_buttonLabel)), 'inputSubmit');

      $form->insertTemplateVar('urlPrefix', $this->_urlPrefix);

      $this->insert($form, 'form');
    } else {
      $this->insertTemplateVar('form', '');
    }
  }
}

class GuiWebGridFilter extends GuiGridFilter {
  protected $_urlPrefix = '';
  protected $_readOnly = false;
  protected $_printUrl = null;
  protected $_resetPage = false;
  protected $_showButtonReset = true;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);

    if (isset($params['urlPrefix'])) $this->_urlPrefix = $params['urlPrefix']; 
    if (isset($params['readOnly'])) $this->_readOnly = $params['readOnly'];
    if (isset($params['printUrl'])) $this->_printUrl = $params['printUrl'];
    if (isset($params['resetPage'])) $this->_resetPage = $params['resetPage']; 
    if (isset($params['showButtonReset'])) $this->_showButtonReset = $params['showButtonReset']; 
  }

  protected function _prepareTemplate() {
    $eaFilterDiv = concatElementAttributes($this->_divAttributes);
    $resetPage = $this->_resetPage?'<input type="hidden" name="page" value="1" />':'';
    $t = 
      "<div$eaFilterDiv><form action=\"{urlPrefix}{%basefile%}\" method=\"post\"><div>\n".
      $this->_app->session->getTagForForm()."\n".
      '  <input type="hidden" name="action" value="'. $this->_exec ."\" />\n".
      '  <input type="hidden" name="gridname" value="'. $this->_gridName ."\" />\n".
      '  <input type="hidden" name="gridclass" value="'. $this->_gridClass ."\" />\n".
      $resetPage."\n".
      "  {children}\n".
      "  {button}\n".
      "</div></form></div>\n";

    return $t;
  }

  protected function _userRender() {
    $this->setTemplateString($this->_prepareTemplate());
    
    $this->insertTemplateVar('urlPrefix', $this->_urlPrefix);

    // zakazu zmenu vsech inputu filtru
    if ($this->_readOnly) {
      foreach ($this->_columns as $columnId => $column) {
        $this->_columns[$columnId]->addFilterParam('readonly','yes');
      }
    }

    $this->_renderColumns();

    if (!$this->_readOnly) {
      $this->insert($submit = new GuiFormButton(array(
              'label' => $this->_buttonLabelSubmit,
              'id' => sprintf('fb_%s_setButton', $this->_gridName),
              'name' => 'set')), 'button');

      if ($this->_showButtonReset) {
        $submit->insert( new GuiFormButton(array(
                'showDiv' => false,
                'label' => $this->_buttonLabelReset,
                'name' => 'filter[reset]')));
      }

      // zobrazim tlacitko print
      if ($this->_printUrl) {
        $submit->insert($submit = new GuiFormButton(array(
                'showDiv' => false,
                'label' => $this->_app->textStorage->getText('button.grid_print'),
                'onclick' => "window.open('$this->_printUrl');return false;",
                'name' => 'print')));
      }
    } else {
      $this->insertTemplateVar('button', '');
    }
  }
}

class GuiGridCellHeaderImgOrder extends GuiGridCellHeaderOrder {
  protected $_sortUpImgSrc = 'img/sort_up.gif';
  protected $_sortDownImgSrc = 'img/sort_down.gif';

  protected function _userParamsInit(&$params) {
    if (isset($params['sortUpImgSrc'])) $this->_sortUpImgSrc = $params['sortUpImgSrc'];
    if (isset($params['sortDownImgSrc'])) $this->_sortDownImgSrc = $params['sortDownImgSrc'];
  }

  protected function _userRender() {
    $session = $this->_app->session->getTagForUrl();
    list ($exec, $gridname, $gridclass, $column) = array( $this->_exec, $this->_gridName, $this->_gridClass, $this->_outputColumn);
    
    // zvyrazneni sloupce, podle ktereho se radi
    $settings = new $gridclass($gridname);
    if (!strcmp($column,$settings->getOrder())) $class = ' class="ordered"';
    else $class = '';

    $t = '<span'.$class.'>'.$this->_outputData . '&nbsp;' .
      '&nbsp;<a href="{%basefile%}?action='. $exec .'&amp;gridname='. $gridname .'&amp;gridclass='. $gridclass .'&amp;order='. $column .'&amp;orderDirection=asc'. $session . '"><img src="'.$this->_sortUpImgSrc.'"/></a>'.
      '<a href="{%basefile%}?action='. $exec .'&amp;gridname='. $gridname .'&amp;gridclass='. $gridclass .'&amp;order='. $column .'&amp;orderDirection=desc'. $session . '"><img src="'.$this->_sortDownImgSrc.'"/></a>'.
      '</span>';
    $this->setTemplateString($t);
  }
}

class GuiGridCellYesNo extends GuiGridCellRenderer {
  protected $_strictYN = true;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);

    if (isset($params['strictYN'])) $this->_strictYN = $params['strictYN'];
  }

  protected function _userRender() {
    $ts = $this->_app->textStorage;
    $this->setTemplateString('{content}{children}');
    if ($this->_strictYN&&($this->_outputData == 'Y')) {
      $key = 'yes';
    } elseif (!$this->_strictYN&&$this->_outputData) {
      $key = 'yes';
    } else {
      $key = 'no';
    }
    $this->insertTemplateVar('content', $ts->getText("label.$key"));
  }
}

class GuiGridCellTextStorage extends GuiGridCellRenderer {
  protected $_prefix = '';

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    if (isset($params['prefix'])) { $this->_prefix = $params['prefix']; }
  }
  
  protected function _userRender() {
    $ts = $this->_app->textStorage;
    $this->setTemplateString('{content}{children}');
    
    $key = $this->_outputData;
    if ($this->_prefix) $key = $this->_prefix . '.' . $key;
    if ($ts->isKey($key)) $out = $ts->getText($key);
    else $out = $this->_outputData;
    
    $this->insertTemplateVar('content', $out);
  }
}

class GuiGridCellTime extends GuiGridCellRenderer {
  protected $_format = 'h:m';

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    if (isset($params['format'])) { $this->_format = $params['format']; }
  }

  protected function _userRender() {
    $rs = Application::get()->regionalSettings;
    $this->setTemplateString('{content}{children}');
    try {
      $this->insertTemplateVar('content', $rs->convertTimeToHuman($this->_outputData, $this->_format));
    } catch (ExceptionUserTextStorage $e) {
      $this->insertTemplateVar('content', '');
      $this->_app->messages->addMessage('userError', sprintf('Invalid time format (%s) in column %s at line No. %s!', 
            $this->_outputData, $this->_outputColumn, ifsetor($this->_rowData['__i'],'unknown')));
    }
  }
}

class GuiGridCellDate extends GuiGridCellRenderer {
  protected $_format = 'd.m.y';

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    if (isset($params['format'])) { $this->_format = $params['format']; }
  }

  protected function _userRender() {
    $rs = $this->_app->regionalSettings;
    $this->setTemplateString('{content}{children}');
    try {
      $this->insertTemplateVar('content', $rs->convertDateToHuman($this->_outputData, $this->_format));
    } catch (ExceptionUserTextStorage $e) {
      $this->insertTemplateVar('content', '');
      $this->_app->messages->addMessage('userError', sprintf('Invalid date format (%s) in column %s at line No. %s!', 
            $this->_outputData, $this->_outputColumn, ifsetor($this->_rowData['__i'],'unknown')));
    }
  }
}

class GuiGridCellDateTime extends GuiGridCellRenderer {
  protected $_formatDate = 'd.m.y';
  protected $_formatTime = 'h:m';
  protected $_reverse = false;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    if (isset($params['formatDate'])) { $this->_formatDate = $params['formatDate']; }
    if (isset($params['formatTime'])) { $this->_formatTime = $params['formatTime']; }
    if (isset($params['reverse'])) { $this->_reverse = $params['reverse']; }
  }

  protected function _userRender() {
    $rs = Application::get()->regionalSettings;
    $this->setTemplateString('{content}{children}');
    try {
      $this->insertTemplateVar('content', $rs->convertDateTimeToHuman($this->_outputData, $this->_formatDate, $this->_formatTime, $this->_reverse));
    } catch (ExceptionUserTextStorage $e) {
      $this->insertTemplateVar('content', '');
      $this->_app->messages->addMessage('userError', sprintf('Invalid datetime format (%s) in column %s at line No. %s!', 
            $this->_outputData, $this->_outputColumn, ifsetor($this->_rowData['__i'],'unknown')));
    }
  }
}

class GuiGridCellNumber extends GuiGridCellRenderer {
  protected $_decimalPlaces = null;
  protected $_replaceSpaces = null;

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    if (isset($params['decimalPlaces'])) { $this->_decimalPlaces = $params['decimalPlaces']; }
    if (isset($params['replaceSpaces'])) { $this->_replaceSpaces = $params['replaceSpaces']; }
  }

  protected function _convertNumber($in=null) {
    if (!$in) $in = $this->_outputData;

    $rs = Application::get()->regionalSettings;
    try {
      $number = $rs->convertNumberToHuman($in, $this->_decimalPlaces);
      if ($this->_replaceSpaces) $number = str_replace(' ',$this->_replaceSpaces,$number);
    } catch (ExceptionUserTextStorage $e) {
      $number = '';
      $this->_app->messages->addMessage('userError', sprintf('Invalid number format (%s) in column %s at line No. %s!',
        $in, $this->_outputColumn, ifsetor($this->_rowData['__i'],'unknown')));
    }

    return $number;
  }

  protected function _userRender() {
    $number = $this->_convertNumber();
    $this->setTemplateString('{content}{children}');
    $this->insertTemplateVar('content', $number, false);
  }
}

class GuiGridCellCut extends GuiGridCellRenderer {
  protected $_charFrom = 0;
  protected $_charNum = 50;
  protected $_divClass;

  protected function _userParamsInit(&$params) {
    if (isset($params['charFrom'])) $this->_charFrom = $params['charFrom'];
    if (isset($params['charNum'])) $this->_charNum = $params['charNum'];
    if (isset($params['divClass'])) $this->_divClass = $params['divClass'];
  }

  protected function _userRender() {
    $l = strlen($this->_outputData);
    $this->setTemplateString('<div{class}{title}>{short}</div>');

    if ($this->_charFrom||($this->_charNum+$this->_charFrom)<$l) {
      $this->insertTemplateVar('title', sprintf(' title="%s"', $this->_outputData), false);
    } else {
      $this->insertTemplateVar('title', '');
    }

    if ($this->_divClass) {
      $this->insertTemplateVar('class', sprintf(' class="%s"', $this->_divClass), false);
    } else {
      $this->insertTemplateVar('class', '');
    }

    $this->insertTemplateVar('short', sprintf('%s %s %s',
          $this->_charFrom?'...':'',
          mb_substr($this->_outputData, $this->_charFrom, $this->_charNum),
          $l>$this->_charNum+$this->_charFrom?'...':''), false);
  }
}

class GuiGridCellSpaceToNbsp extends GuiGridCellRenderer {

  protected function _userRender() {
    $this->setTemplateString('{data}');
    $this->insertTemplateVar('data', str_replace(' ', '&nbsp;', $this->_outputData), false);
  }
}

?>
