<?php

class GuiGrid extends GuiElement {
  protected $_id;
  protected $_settings;
  protected $_defaultTemplate = "{filter}\n{table}\n{multiactionForm}\n{pager}\n{columnsChanger}";
  protected $_dataSource;
  protected $_guiGridRow;
  protected $_outputOnEmptyResult = false;
  
  protected $_multiAction = false;
  public $_multiActionColumn = null;
  protected $_multiActionVarName = 'id';

  public function __construct($params=array()) {
    if (!isset($params['template'])) $params['template'] = $this->_defaultTemplate;
    parent::__construct($params);
  }

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);

    if (isset($params['id'])) $this->_id = $params['id'];
    if (isset($params['settings'])) $this->_settings = $params['settings'];
    if (isset($params['dataSource'])) $this->_dataSource = $params['dataSource'];
    
    if (isset($params['multiAction'])) {
      if (isset($params['multiAction']['action'])) {
        $this->_multiAction = array();
        
        foreach ($params['multiAction']['action'] as $index=>$value) {
          $newItem = array('action'=>$value,'label'=>ifsetor($params['multiAction']['label'][$index]),'onclick'=>ifsetor($params['multiAction']['onclick'][$index]));
          if (isset($params['multiAction']['id'][$index])) $newItem['id'] = $params['multiAction']['id'][$index];
          if (isset($params['multiAction']['class'][$index])) $newItem['class'] = $params['multiAction']['class'][$index];
          if (isset($params['multiAction']['html'][$index])) $newItem['html'] = $params['multiAction']['html'][$index];

          $this->_multiAction[] = $newItem;
        }
        
        if (isset($params['multiAction']['varName'])) $this->_multiActionVarName = $params['multiAction']['varName'];
        if (isset($params['multiAction']['column'])) $this->_multiActionColumn = $params['multiAction']['column'];
      }
    }

    $this->_settings->setGridClass(get_class($this));
  }

  protected function _userRender() {
    $this->_initRow();
    $this->_insertTable();
    $this->_insertPager();
    $this->_insertColumnsChanger();
    $this->_insertFilter();
    $this->_insertMultiactionForm();
  }

  protected function _insertTable() {
    $gui = $this->_createTable();
    $this->insert($gui,'table');
  }
  
  protected function _getRowParams() {
    $params = array('dataSource'=>$this->_dataSource,'outputOnEmptyResult'=>$this->_outputOnEmptyResult);
    
    if ($this->_id) $params['gridId'] = $this->_id;
    if ($this->_multiAction) {
      $params['multiAction'] = $this->_multiAction;
      $params['multiActionColumn'] = $this->_multiActionColumn;
      $params['multiActionVarName'] = $this->_multiActionVarName;
    }
    
    $params = array_merge($params, $this->_settings->getGuiGridRowSettings());

    return $params;
  }

  protected function _initRow() {
    $params = $this->_getRowParams();
    
    $this->_guiGridRow = new GuiGridRow($params);
  }

  protected function _insertPager() { $this->insert(new GuiGridPager($this->_settings->getGuiGridPagerSettings()), 'pager'); }

  protected function _insertColumnsChanger() { $this->insert(new GuiGridColumnsChanger($this->_settings->getGuiGridColumnsChangerSettings()), 'columnsChanger'); }

  protected function _insertFilter() { $this->insert(new GuiGridFilter($this->_settings->getGuiGridFilterSettings()), 'filter'); }
  
  protected function _insertMultiactionForm() {
    $eaDiv = concatElementAttributes($this->_settings->getGuiGridMultiactionDivAttributes());
    
    if ($this->_multiAction) {
      $form = new GuiElement(array('template'=>
          "<div style=\"display:none;\" id=\"fi_multiaction_form_".$this->_id."\" $eaDiv><form action=\"{%basefile%}\" method=\"post\">\n<div>\n".
          "{%sessionInput%}".
          "<input type=\"hidden\" name=\"{varName}\" id=\"fi_multiaction_input_".$this->_id."\" value=\"\" />".
          "{submit}\n</div></form></div>"));
      
      foreach ($this->_multiAction as $action) {
        if (isset($action['html'])&&$action['html']) {
          if (is_object($action['html'])) $form->insert($action['html'],'submit');
          else $form->insertTemplateVar('submit',$action['html'],false);
        }

        $submitPar = array(
              'showDiv'   => false,
              'action'    => $action['action'],
              'label'     => $action['label'],
              'onclick'   => sprintf("gridMultiActionSubmit('%s');", $this->_id),
              );
        if (isset($action['class'])&&$action['class']) $submitPar['classInput'] = $action['class'];
        if (isset($action['id'])&&$action['id']) $submitPar['id'] = $action['id'];
        if (isset($action['onclick'])&&$action['onclick']) $submitPar['onclick'] .= $action['onclick'];
        
        $form->insert(new GuiFormButton($submitPar), 'submit');
      }
      $form->insertTemplateVar('varName', $this->_multiActionVarName);

      $this->insert($form, 'multiactionForm');
    } else $this->insertTemplateVar('multiactionForm', '');
  }

  public function getSettings() { return $this->_settings; }

  protected function _createTable() {
    if ($this->_id) $this->_settings->addGuiGridTableAttribute('id', $this->_id);
    
    $table = new GuiGridTable($this->_settings->getGuiGridTableSettings());
    $table->insert($this->_guiGridRow, 'guiGridRows');
    
    return $table;
  }

  public function setOutputOnResult($bool) { $this->_outputOnEmptyResult = $bool; }
}

class GuiGridVertical extends GuiGrid {
  protected $_guiGridTemplate = "{filter}\n{table}\n{columnsChanger}";
  protected $_outputOnEmptyResult = true;

  protected function _userRender() {
    $this->_initRow();
    $this->_insertTable();
    $this->_insertColumnsChanger();
    $this->_insertFilter();
  }

  protected function _initRow() {
    $params = $this->_getRowParams();
    
    $this->_guiGridRow = new GuiGridRowVertical($params);
  }
}

class GuiGridTableSettings { }

class GuiGridTable extends GuiElement {
  protected $_elementAttributes = array();

  protected function _userParamsInit(&$params) { if (isset($params['elementAttributes'])) { $this->_elementAttributes = $params['elementAttributes']; } }

  protected function _userRender() {
    $this->setTemplateString("<table{tableAttributes}>\n{guiGridRows}</table>\n");
    $this->insertTemplateVar('tableAttributes', concatElementAttributes($this->_elementAttributes), false);
  }
}

class GuiGridRow extends GuiElement {
  protected $_page;
  protected $_records;
  protected $_dataSource;
  protected $_footerData = false;
  protected $_rows = array();
  protected $_columns = array();
  protected $_columnsMask = array();
  protected $_params = array();
  protected $_outputOnEmptyResult = false; 
  protected $_multiAction = false;
  protected $_multiActionColumn = null;
  protected $_multiActionVarName = 'id';
  protected $_gridId = null;
  
  public function __construct($params=array()) {
    $this->_params = $params;
    parent::__construct($params);
  }

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);

    $this->_dataSource = $params['dataSource'];
    $this->_page =& $params['page'];
    $this->_records =& $params['records'];

    if (isset($params['rows'])) $this->_rows =& $params['rows']; 
    if (isset($params['columns'])) $this->_columns =& $params['columns'];
    if (isset($params['columnsMask'])) $this->_columnsMask =& $params['columnsMask']; 
    if (isset($params['footerData'])) $this->_footerData = $params['footerData'];
    if (isset($params['outputOnEmptyResult'])) $this->_outputOnEmptyResult = $params['outputOnEmptyResult'];
    if (isset($params['multiAction'])) $this->_multiAction = $params['multiAction'];
    if (isset($params['multiActionColumn'])) $this->_multiActionColumn = $params['multiActionColumn'];
    if (isset($params['multiActionVarName'])) $this->_multiActionVarName = $params['multiActionVarName'];
    if (isset($params['gridId'])) $this->_gridId = $params['gridId'];
  }
  
    
  public function exportData() {
    $output = '';
    
    $exportColumnsMask = $this->_columnsMask;
    foreach ($exportColumnsMask as $index=>$columnName) {
      if (!strcmp($columnName,'action')) unset($exportColumnsMask[$index]);
    }
    
    try {
      $this->_dataSource->reset();
    } catch (Exception $e) {
      $t .= $this->_renderDataSourceError($e);
    }
    
    $data =& $this->_dataSource->currentData;
    while (is_array($data)) {
      $row = '';
      foreach ($exportColumnsMask as $columnName) {
        if ($row) $row .= ';';
        $row .= $this->_getCell($columnName, $data);
      }
      $row .= "\n";
      
      $output .= $row;
      $this->_dataSource->nextData();
    }
    
    return $output;
  }

  protected function _userRender() {
    while ($error = true) {
      
      $t = $this->_renderHeader();

      try {
        $this->_dataSource->reset();
      } catch (Exception $e) {
        $t .= $this->_renderDataSourceError($e);
      }
      
      $this->_records = $this->_dataSource->records;
      $this->_page = $this->_dataSource->settings->getPage();
      
      $data =& $this->_dataSource->currentData;
      while (is_array($data)) {
        $tRow = $this->_renderRow('data', $data);
        foreach ($this->_columnsMask as $columnName) {
          $tRow .= $this->_renderCell($columnName, $data);
        }
        $tRow .= "</tr>\n";
        
        $t .= $tRow;
        $this->_dataSource->nextData();
      }
      if ($this->_footerData){
        $t .= $this->_renderFooter();
      }
      $error = false;
      break;
    }

    $this->setTemplateString($t);
  }

  protected function _renderDataSourceError($e) {
    switch (get_class($e)) {
      case 'ExceptionUser':
        $message = $e->getMessage();
        break;
      case 'ExceptionUserTextStorage':
        $message = $this->_app->textStorage->getText($e->getMessage());
        break;
      default:
        $message = 'Error retreiving data!';
    }
    $tempData = array();
    return $this->_renderRow('error', $tempData) ."<td colspan=\"". count($this->_columnsMask) ."\">$message</td></tr>\n";
  }

  protected function _renderHeader() {
    $tempData = array();
    $ret = $this->_renderRow('header', $tempData);
    foreach ($this->_columnsMask as $columnName) {
      $column = clone $this->_columns[$columnName];
      
      if (!strcmp($columnName, $this->_multiActionColumn)) {
        $output = $this->_getMultiActionHeaderCheckbox();
      } elseif ($column->headerGuiElement instanceof GuiElement) {
        if (($column->headerGuiElement instanceof GuiGridCellRenderer) && isset($this->_params['exec'])) $column->headerGuiElement->setExecAction($this->_params['exec']);
        $output = $column->headerGuiElement->render();
      } else {
        $output = $column->getLabel();
      }
      $ret .= '<th'. concatElementAttributes($column->getElementAttributes()) .'>'. $output .'</th>';
    }
    $ret .= "</tr>\n";
    return $ret;
  }
  
  protected function _getMultiActionHeaderCheckbox() {
    if (!$this->_gridId) $ret = 'Missing ID for grid!';
    else {
      $ret = sprintf('<input type="checkbox" id="fi_multiaction_clickall_%s" class="inputCheckbox" onclick="gridMultiActionClickAll(\'%s\');"/>', 
                        $this->_gridId, $this->_gridId);
    
      Application::get()->document->addJavascript(sprintf("
              if (typeof window.gridMultiActionDisplayForm === 'undefined') {
                gridMultiActionDisplayForm = function(gridId) {
                  var grid = document.getElementById(gridId);
                  if (grid) {
                    var form = document.getElementById('fi_multiaction_form_'+gridId);
                    var checkbox = grid.getElementsByTagName('input');
                    var checked = false;
                    
                    for (i=0;i<checkbox.length;i++) {
                      if (checkbox[i].checked) {
                        checked = true;
                        break
                      }
                    }
                    
                    if (checked) form.style.display = 'block';
                    else form.style.display = 'none';
                  }
                }
              }
              
              if (typeof window.gridMultiActionClickAll === 'undefined') {
                gridMultiActionClickAll = function(gridId) {
                  var grid = document.getElementById(gridId);
                  if (grid) {
                    var newValue = document.getElementById('fi_multiaction_clickall_'+gridId).checked;
                    var checkbox = grid.getElementsByTagName('input');
                    
                    for (i=0;i<checkbox.length;i++) {
                      if (checkbox[i].hasAttribute('meaning')&&(checkbox[i].getAttribute('meaning')=='multiaction')) {
                        checkbox[i].checked = newValue;
                      }
                    }
                    
                    gridMultiActionDisplayForm(gridId);
                  }
                }
              }
              
              if (typeof window.gridMultiActionSubmit === 'undefined') {
                gridMultiActionSubmit = function(gridId) {
                  var grid = document.getElementById(gridId);
                  if (grid) {
                    var varName = document.getElementById('fi_multiaction_input_'+gridId);
                    var checkbox = grid.getElementsByTagName('input');
                    var value = '';
                    
                    for (i=0;i<checkbox.length;i++) {
                      if (checkbox[i].hasAttribute('meaning')&&(checkbox[i].getAttribute('meaning')=='multiaction')&&checkbox[i].checked) {
                        if (value) value += ',';
                        value += checkbox[i].value;
                      }
                    }
                    
                    varName.value = value;
                  }
                }
              }"));
      Application::get()->document->addOnLoad(sprintf('gridMultiActionDisplayForm(\'%s\');', $this->_gridId));
    }

    return $ret;
  }
  
  protected function _getMultiActionCellCheckbox($output) {
    $requestVal = $this->_app->request->getParams($this->_multiActionVarName);
    $ret = sprintf('<input type="checkbox" meaning="multiaction" class="inputCheckbox" name="%s[]" value="%s" %sonclick="gridMultiActionDisplayForm(\'%s\');"/>',
                   $this->_multiActionVarName, $output, is_array($requestVal)&&in_array($output,$requestVal)?'checked="yes"':'', $this->_gridId);
    
    return $ret;
  }

  protected function _renderFooter() {
    $tempData = array();
    $ret = $this->_renderRow('footer', $tempData);
    foreach ($this->_columnsMask as $columnName) {
      $column = clone $this->_columns[$columnName];
      $output = '';
      if ($column->footerGuiElement instanceof GuiElement) {
        $output = $column->footerGuiElement->render();
      } elseif($column->getFooterDataLabel()) {
        $output = $this->_footerData[$column->getFooterDataSource()][$column->getFooterDataLabel()];        
        $output = $column->printFooterValue($output);      
      } 
      $ret .= '<th'. concatElementAttributes($column->getFooterElementAttributes()) .'>'. $output .'</th>';
    }
    $ret .= "</tr>\n";
    return $ret;
  }
  
  protected function _getCell($columnName, &$data) {
    $column = $this->_columns[$columnName];
    if (is_array($column->getSource())){
      $src = $column->getSource();
      reset($src); 
      $src = current($src);
      $output = isset($data[$src]) ? $data[$src] : null;      
    } else {
      $output = isset($data[$column->getSource()]) ? $data[$column->getSource()] : null;
    }
    $output = $column->printValue($output);
    
    if (!strcmp($columnName, $this->_multiActionColumn)) {
      $output = $this->_getMultiActionCellCheckbox($output);
    } elseif (count($column->guiElements)) {
      $guiOutput = '';
      foreach ($column->guiElements as $guiO) {
        $gui = clone $guiO;
        if ($gui instanceof GuiGridCellRenderer) {
          $gui->setOutputData($output);
          $gui->setRowData($data);
        }
        $guiOutput .= $gui->render();
      }
      $output = $guiOutput;
    }
    
    return $output;
  }

  protected function _renderCell($columnName, &$data) {
    $column = $this->_columns[$columnName];
    
    $output = $this->_getCell($columnName, $data);
    
    $ret = '<td'. concatElementAttributes($column->getElementAttributes()/*($data)*/) .'>'. $output .'</td>';
    return $ret;
  }

  protected function _renderRow($name, &$data) {
    $attributes = isset($this->_rows[$name]) ? $this->_rows[$name]->getElementAttributes() : array();
    $this->_preRenderRow($name, $data, $attributes);
    return '<tr'. concatElementAttributes($attributes) .'>';
  }

  protected function _preRenderRow($name, &$data, &$attributes) { }
}

class GuiGridRowVertical extends GuiGridRow {

  protected function _userRender() {
    while ($error = true) {
      $t ='';
      $this->_records = $this->_dataSource->records;
      $this->_page = $this->_dataSource->settings->getPage();
      $j = 0;
      $n = count($this->_columnsMask);
      while ($n>0){
        $tRow = $this->_renderRow('data', $data);
        $tRow .= $this->_renderHeaderCell($this->_columnsMask[$j]);
        try {
          $this->_dataSource->reset(true);
        } catch (Exception $e) {
          $t .= $this->_renderDataSourceError($e);
        }
        if ($this->_dataSource->records==0 && $this->_outputOnEmptyResult) {
          $data = '';
          $tRow .= $this->_renderCell($this->_columnsMask[$j], $data);
        }
        else {
          for($i=0;$i<($this->_dataSource->records);$i++) {
            $data =& $this->_dataSource->currentData;
            $tRow .= $this->_renderCell($this->_columnsMask[$j], $data);
            $this->_dataSource->nextData();
          }
        }
        $tRow .= "</tr>\n";
        $t .= $tRow;
        $n--;$j++;
      }
      $error = false;
      break;
    }
    $this->setTemplateString($t);
  }

  protected function _renderHeaderCell($columnName) {
    $column = clone $this->_columns[$columnName];
    if ($column->headerGuiElement instanceof GuiElement) {
      if (($column->headerGuiElement instanceof GuiGridCellRenderer) && isset($this->_params['exec'])) $column->headerGuiElement->setExecAction($this->_params['exec']);
      $output = $column->headerGuiElement->render();
    } else {
      $output = $column->getLabel();
    }
    $output = '<th'. concatElementAttributes($column->getElementAttributes()) .'>'. $output .'</th>';
    return $output;
  }
}

class GuiGridPager extends GuiElement {
  protected $_gridName;
  protected $_gridClass;
  protected $_exec;
  protected $_records;
  protected $_page = 1;
  protected $_onPage = 20;
  protected $_maxPages = 11;
  protected $_pagesBefore;
  protected $_pagerDivAttributes = array();
  protected $_pagingDivAttributes = array();
  protected $_onPageDivAttributes = array();
  protected $_buttonLabel = ' ';
  protected $_pagerLabel = null;
  protected $_navigationArrows = true;
  
  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    $this->_gridName =& $params['gridName'];
    $this->_gridClass =& $params['gridClass'];
    $this->_exec =& $params['exec'];
    $this->_records =& $params['records'];
    if (isset($params['page'])) { $this->_page =& $params['page']; }
    if (isset($params['onPage'])) { $this->_onPage =& $params['onPage']; }
    if (isset($params['maxPages'])) { $this->_maxPages =& $params['maxPages']; }
    if (isset($params['pagesBefore'])) { 
        $this->_pagesBefore =& $params['pagesBefore']; 
    } else {
        $this->_pagesBefore = floor(($this->_maxPages - 1) / 2); 
    }
    if (isset($params['pagerDivAttributes'])) { $this->_pagerDivAttributes =& $params['pagerDivAttributes']; }
    if (isset($params['pagingDivAttributes'])) { $this->_pagingDivAttributes =& $params['pagingDivAttributes']; }
    if (isset($params['onPageDivAttributes'])) { $this->_onPageDivAttributes =& $params['onPageDivAttributes']; }
    if (isset($params['buttonLabel'])) { $this->_buttonLabel = $params['buttonLabel']; }
    if (isset($params['navigationArrows'])) { $this->_navigationArrows = $params['navigationArrows']; }
    if (isset($params['pagerLabel'])) { $this->_pagerLabel = $params['pagerLabel']; }
  }

  protected function _userRender() {
    $arrows = $this->_navigationArrows;
    $maxPages = $this->_maxPages;
    $before = $this->_pagesBefore;
    $page = $this->_page;
    $lastPage = floor($this->_records / $this ->_onPage);
    if ($this->_records % $this ->_onPage > 0) $lastPage++;
    
    $min = max(1, $page - $before);
    $max = min($lastPage, ($min - 1 + $maxPages));
    $min = max(1, ($max + 1 - $maxPages));
    
    $pager = '';
    $session = $this->_app->session->getUseCookie() ? '' : '&amp;'. $this->_app->session->getUrl();
    if ($arrows){
         $class = ($page > 1) ? '' : ' nohref';
         $pager .= '<a'.
	    ($class ? '' : ' href="{%basefile%}?action='. $this->_exec .'&amp;gridname='. $this->_gridName .'&amp;gridclass='. $this->_gridClass .'&amp;page=1' . $session . '"').
	    ' class="arrowFirst'. $class .'">|&lt;&lt;</a>'."\n";
         $pager .= '<a'.
	    ($class ? '' : ' href="{%basefile%}?action='. $this->_exec .'&amp;gridname='. $this->_gridName .'&amp;gridclass='. $this->_gridClass .'&amp;page='. ($page - 1) . $session . '"').
	    ' class="arrowPrevious'. $class .'">|&lt;</a>'."\n";
    }
    for ($i = $min; $i <= $max; $i++) {
      if ($i == $page) {
        $link = '<span class="actualPage">'.$i.'</span>'."\n";
      } else {
        $link = '<a href="{%basefile%}?action='. $this->_exec .'&amp;gridname='. $this->_gridName .'&amp;gridclass='. $this->_gridClass .'&amp;page='. $i . $session .'">'. $i .'</a>'."\n";
      }
      $pager .= $link;
    }
    if ($arrows){
         $class = ($page < $lastPage) ? '' : ' nohref';
         $pager .= '<a'.
	    ($class ? '' : ' href="{%basefile%}?action='. $this->_exec .'&amp;gridname='. $this->_gridName .'&amp;gridclass='. $this->_gridClass .'&amp;page='. ($page + 1) . $session . '"').
	    ' class="arrowNext'. $class .'">&gt;|</a>'."\n";
         $pager .= '<a'.
	    ($class ? '' : ' href="{%basefile%}?action='. $this->_exec .'&amp;gridname='. $this->_gridName .'&amp;gridclass='. $this->_gridClass .'&amp;page='. $lastPage . $session . '"').
	    ' class="arrowLast'. $class .'">&gt;&gt;|</a>'."\n";
    }
    $t = $pager;
    $eaPagerDiv = concatElementAttributes($this->_pagerDivAttributes);
    $eaPagingDiv = concatElementAttributes($this->_pagingDivAttributes);
    $eaOnPageDiv = concatElementAttributes($this->_onPageDivAttributes);
    $t = "<div$eaPagerDiv><div$eaPagingDiv>\n$t</div>\n{form}</div>";
    $this->setTemplateString($t);
    
    $form = new GuiElement(array('template'=>
          "<form action=\"{%basefile%}\" method=\"post\"><div$eaOnPageDiv>\n".
          ($this->_app->session->getUseCookie() ? "" : "<input type=\"hidden\" name=\"{%sessname%}\" value=\"{%sessid%}\" />\n").
          "<input type=\"hidden\" name=\"gridname\" value=\"". $this->_gridName ."\" />\n".
          "<input type=\"hidden\" name=\"gridclass\" value=\"". $this->_gridClass ."\" />\n".
          "<input type=\"hidden\" name=\"action\" value=\"".$this->_exec."\" />\n".
          "{inputText}\n".
          "{inputSubmit}\n</div></form>"));
    $label = array();
    if (!is_null($this->_pagerLabel)) {
      $label['label'] = $this->_pagerLabel;
    }
    $form->insert(new GuiFormInput(array_merge($label,array(
              'showDiv' => false,
              'name' => 'onPage',
              'value' => $this->_onPage))), 'inputText');

    $form->insert(new GuiFormButton(array(
            'showDiv' => false,
            'action' => $this->_exec,
            'label' => $this->_buttonLabel)), 'inputSubmit');

    $this->insert($form, 'form');
  }
}  

class GuiGridColumnsChanger extends GuiElement {
  protected $_gridName;
  protected $_gridClass;
  protected $_exec;
  protected $_columns = array();
  protected $_columnsMask = array();
  protected $_columnsRestricted = array();
  protected $_buttonLabel = ' ';

  protected function _userParamsInit(&$params) {
    $this->_gridName =& $params['gridName'];
    $this->_gridClass =& $params['gridClass'];
    $this->_exec =& $params['exec'];
    if (isset($params['columns'])) { $this->_columns =& $params['columns']; }
    if (isset($params['columnsMask'])) { $this->_columnsMask =& $params['columnsMask']; }
    if (isset($params['columnsRestricted'])) { $this->_columnsRestricted =& $params['columnsRestricted']; }
    if (isset($params['buttonLabel'])) { $this->_buttonLabel = $params['buttonLabel']; }
  }

  protected function _userRender() {
    $t = "<div class='gridColumnsChanger'><form action=\"{%basefile%}\" method=\"post\"><div>\n".
      (Application::get()->session->getUseCookie() ? "" : "<input type=\"hidden\" name=\"{%sessname%}\" value=\"{%sessid%}\" />\n").
      "<input type=\"hidden\" name=\"gridname\" value=\"". $this->_gridName ."\" />\n".
      "<input type=\"hidden\" name=\"gridclass\" value=\"". $this->_gridClass ."\" />\n".
      "{children}\n".
      "<input type=\"submit\" name=\"action_". $this->_exec ."\" value=\"". $this->_buttonLabel ."\" />\n</div></form></div>";

    $hash = array();

    foreach ($this->_columns as $columnIndex => $column) {
      if(!in_array($columnIndex,$this->_columnsRestricted)) $hash[$columnIndex] = $column->getLabel();
    }

    $columnsCount = count($this->_columnsMask);
    for ($i = 0; $i <= $columnsCount; $i++) {
      $this->insert( new GuiHashSelect( array(
        'name' => 'columnsMask[]',
        'hash' => $hash,
        'value' => isset($this->_columnsMask[$i]) ? $this->_columnsMask[$i] : null,
        'firstOption' => '-----Vyberte-----' )));
    }
    $this->setTemplateString($t);
  }
}

class GuiGridFilter extends GuiElement {
  protected $_gridName;
  protected $_gridClass;
  protected $_exec;
  protected $_columns = array();
  protected $_filter = array();
  protected $_divAttributes = array();
  protected $_buttonLabelSubmit = ' ';
  protected $_buttonLabelReset = 'x';

  protected function _userParamsInit(&$params) {
    $this->_gridName =& $params['gridName'];
    $this->_gridClass =& $params['gridClass'];
    $this->_exec =& $params['exec'];
    if (isset($params['columns'])) { $this->_columns =& $params['columns']; }
    if (isset($params['filter'])) { $this->_filter =& $params['filter']; }
    if (isset($params['divAttributes'])) { $this->_divAttributes =& $params['divAttributes']; }
    if (isset($params['buttonLabelSubmit'])) { $this->_buttonLabelSubmit = $params['buttonLabelSubmit']; }
    if (isset($params['buttonLabelReset'])) { $this->_buttonLabelReset = $params['buttonLabelReset']; }
  }

  protected function _renderColumns() {
    foreach ($this->_columns as $column) {
      $gui = $column->getColumnFilter()->getGui($this->_gridName);
      if ($gui instanceof GuiElement)
        $this->insert($gui);
    }
  }

  protected function _userRender() {
    $eaFilterDiv = concatElementAttributes($this->_divAttributes);
    $t = "<div$eaFilterDiv><form action=\"{%basefile%}\" method=\"post\"><div>\n".
      (Application::get()->session->getUseCookie() ? "" : "<input type=\"hidden\" name=\"{%sessname%}\" value=\"{%sessid%}\" />\n").
      '<input type="hidden" name="action" value="'. $this->_exec ."\" />\n".
      '<input type="hidden" name="gridname" value="'. $this->_gridName ."\" />\n".
      '<input type="hidden" name="gridclass" value="'. $this->_gridClass ."\" />\n".
      "{children}\n".
      "{button}\n".
      "</div></form></div>";
    $this->setTemplateString($t);

    $this->_renderColumns();

    $this->insert($submit = new GuiFormButton(array(
            'label' => $this->_buttonLabelSubmit,
            #'labelHtmlize' => $column->getLabelHtmlize(),
            'name' => 'set')), 'button');
	    
    $submit->insert( new GuiFormButton(array(
      'showDiv' => false,
      'label' => $this->_buttonLabelReset,
      #'labelHtmlize' => $column->getLabelHtmlize(),
      'name' => 'filter[reset]')));
  }
}

class GuiGridCellRenderer extends GuiElement {
  protected $_gridName;
  protected $_gridClass;
  protected $_outputColumn;
  protected $_outputSource;
  protected $_outputData;
  protected $_rowData;
  protected $_exec = 'eGrid';

  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    if (isset($params['exec'])) { $this->_exec; }
  }

  public function setExecAction($val) { $this->_exec = $val; }
  public function setGridName($name) { $this->_gridName = $name; }
  public function setGridClass($class) { $this->_gridClass = $class; }
  public function setOutputColumn($column) { $this->_outputColumn = $column; }
  public function setOutputSource($source) { $this->_outputSource = $source; }
  public function setOutputData($data) { $this->_outputData = $data; }
  public function setRowData(&$rowData) { $this->_rowData =& $rowData; }
}

class GuiGridCellHeaderOrder extends GuiGridCellRenderer {

  protected function _userRender() {
    $session = $this->_app->session->getTagForUrl();
    list ($exec, $gridname, $gridclass, $column) = array( $this->_exec, $this->_gridName, $this->_gridClass, $this->_outputColumn);
    
    $t = '<span>'. $this->_outputData .
      '<a href="{%basefile%}?action='. $exec .'&amp;gridname='. $gridname .'&amp;gridclass='. $gridclass .'&amp;order='. $column .'&amp;orderDirection=asc'. $session .'">/\</a>'.
      '<a href="{%basefile%}?action='. $exec .'&amp;gridname='. $gridname .'&amp;gridclass='. $gridclass .'&amp;order='. $column .'&amp;orderDirection=desc'. $session .'">\/</a>'.
      '</span>';
    $this->setTemplateString($t);
  }
}

class GuiGridCellAction extends GuiGridCellRenderer {
  protected $_varName;
  protected $_class;
  protected $_label;
  protected $_labelHtmlize = true;
  protected $_imgsrc;
  protected $_imgclass;
  protected $_title;
  protected $_action;
  protected $_url;
  protected $_target;
  protected $_onclick;
  protected $_dynamics = array();
  protected $_conditions = array();
  protected $_restrictions = array();
  protected $_constants = array();
  protected $_elementAttributes = array();
  
  protected function _userParamsInit(&$params) {
    parent::_userParamsInit($params);
    if (isset($params['class'])) { $this->_class = $params['class']; }
    if (isset($params['action'])) { $this->_action = $params['action']; }
    if (isset($params['url'])) { $this->_url = $params['url']; }
    if (isset($params['target'])) { $this->_target = $params['target']; }
    if (isset($params['label'])) { $this->_label = $params['label']; }
    if (isset($params['labelHtmlize'])) { $this->_labelHtmlize = $params['labelHtmlize']; }
    if (isset($params['imgsrc'])) { $this->_imgsrc = $params['imgsrc']; }
    if (isset($params['imgclass'])) { $this->_imgclass = $params['imgclass']; }
    if (isset($params['title'])) { $this->_title = $params['title']; }
    if (isset($params['varName'])) { $this->_varName = $params['varName']; }
    if (isset($params['onclick'])) { $this->_onclick = $params['onclick']; }
    $this->_dynamics = ifsetor($params['dynamics'], array());
    $this->_conditions = ifsetor($params['conditions'], array());
    $this->_restrictions = ifsetor($params['restrictions'], array());
    $this->_constants = ifsetor($params['constants'], array());
    $this->_elementAttributes = ifsetor($params['elementAttributes'], array());
  }

  protected function _userRender() {
    if ($this->_testData()) {
      
      $buttonParams = array(
          'label' => $this->_label,
          'htmlize' => $this->_labelHtmlize,
          'imgsrc' => $this->_imgsrc,
          'imgclass' => $this->_imgclass,
          'action' => $this->_action,
          'url' => $this->_url,
          'actionParam' => array());
      
      $varName = $this->_varName ? $this->_varName : ifsetor($this->outputSource);
      if ($varName) $buttonParams['actionParam'] = array( $varName => $this->_outputData );
      
      if ($this->_class) $buttonParams['class'] = $this->_class;

      foreach ($this->_dynamics as $index => $source) {
        if (is_int($index)) {
          $index = $source;
        }
        $buttonParams['actionParam'][$index] = isset($this->_rowData[$source]) ? $this->_rowData[$source] : null;
      }

      foreach ($this->_constants as $key => $value) {
        $buttonParams['actionParam'][$key] = $value;
      }

      if ($this->_onclick) {
        $gui = new GuiElement();
        $gui->setTemplateString($this->_onclick);
        foreach ($this->_rowData as $source => $value) {
          $gui->insertTemplateVar($source, $value);
        }
        $buttonParams['onclick'] = $gui->render();
      }
      
      if ($this->_title) {
        $gui = new GuiElement();
        $gui->setTemplateString($this->_title);
        foreach ($this->_rowData as $source => $value) {
          $gui->insertTemplateVar($source, $value);
        }
        $buttonParams['title'] = $gui->render();
      }

      if ($this->_target) {
        $buttonParams['target'] = $this->_target;
      }

      $this->setTemplateString('<span'. concatElementAttributes($this->_elementAttributes) .'>{children}</span>');

      if ($this->_imgsrc) { $this->insert(new GuiImgButton($buttonParams)); }
      else { $this->insert(new GuiTextButton($buttonParams)); }
    }
  }

  protected function _testData() {
    while ($error = true) {
      foreach ($this->_conditions as $source => $value) {
        if (!is_array($value)) { $value = array($value); } 
        if (!in_array($this->_rowData[$source], $value)) { break 2; }
      }
      foreach ($this->_restrictions as $source => $value) {
        if (!is_array($value)) { $value = array($value); }
        if (in_array($this->_rowData[$source], $value)) { break 2; }
      }
      $error = false;
      break;
    }
    return !$error;
  }

  public function getDataSources(){
    $scr = array();
    foreach ($this->_conditions as $val => $q) $scr[$val] = 1;
    foreach ($this->_restrictions as $val => $q) $scr[$val] = 1;
    foreach ($this->_dynamics as $val) $scr[$val] = 1;
    $source = array();
    foreach ($scr as $key => $val)$source[] = $key;
    return $source;
  }

  public function addConstant($key, $value) { $this->_constants[$key] = $value; }
  public function setConstants($constants=array()) { $this->_constants = $constants; }

  public function addCondition($source, $value) { $this->_conditions[$source] = $value; }
  public function setConditions($conditions=array()) { $this->_conditions = $conditions; }
  
  public function addRestriction($source, $value) { $this->_restrictions[$source] = $value; }
  public function setRestrictions($restrictions=array()) { $this->_restrictions = $restrictions; }

  public function setOnclick($onclick) { $this->_onclick = $onclick; }

  public function addElementAttribute($attribute, $value) { $this->_elementAttributes[$attribute] = $value; }
  public function setElementAttribute($attributes) { $this->_elementAttributes = $attributes; }
}

?>
