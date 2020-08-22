<?php

class GridSettings {
  protected $_name;
  protected $_gridClass;
  protected $_execAction = 'eGrid';
  protected $_rows = array();
  protected $_columns = array();
  protected $_columnsMask; 
  protected $_forcedSources = array();
  protected $_records;
  protected $_page = 1;
  protected $_onPage = 20;
  protected $_maxPages = 11;
  protected $_addIndexes = true;
  protected $_order;
  protected $_orderDirection;
  protected $_filter;
  protected $_footerDataSource;
  protected $_footerData = array();
  protected $_filterJoins = array();
  protected $_guiGridTableAttributes = array();
  protected $_urlPrefix = '';
  protected $_guiGridPagerPagesBefore;
  protected $_guiGridPagerArrows = true;
  protected $_guiGridPagerShowForm = true;
  protected $_guiGridPagerShowLeft = true;
  protected $_guiGridPagerShowLeftLeft = true;
  protected $_guiGridPagerShowRight = true;
  protected $_guiGridPagerShowRightRight = true;
  protected $_guiGridPagerLeftImgSrc = 'img/pager_left.gif';
  protected $_guiGridPagerLeftLeftImgSrc = 'img/pager_leftleft.gif';
  protected $_guiGridPagerRightImgSrc = 'img/pager_right.gif';
  protected $_guiGridPagerRightRightImgSrc = 'img/pager_rightright.gif';
  protected $_guiGridPagerDivAttributes = array();
  protected $_guiGridPagerDivPagingAttributes = array();
  protected $_guiGridPagerDivOnPageAttributes = array();
  protected $_guiGridPagerButtonLabel = ' ';
  protected $_guiGridPagerLabel = null;
  protected $_guiGridMultiactionDivAttributes = array();
  protected $_guiGridFilterDivAttributes = array();
  protected $_guiGridFilterShowButtonReset = true;
  protected $_guiGridFilterButtonLabelReset = 'x';
  protected $_guiGridColumnChangerButtonLabel = ' ';
  protected $_gridColumnsChangerRestrictedColumns = array();
  protected $_fakeSources = array('__i', '__iPage');
  protected $_sqlSelectParams = array();
  protected $_showGridNamePrefix = false;

  public function __construct($name) {
    $this->_name = $name;
    if (!is_array($this->_filter)) {
      $this->_filter = $this->getDefaultFilter();
    }
    
    $this->_initSettings();

    $this->_userInitSettings();

    $this->loadSettings();
  }

  static public function getFilterIsNull() { return GridColumnFilter::getFilterIsNull(); }
  static public function getFilterIsNotNull() { return GridColumnFilter::getFilterIsNotNull(); }
  static public function getFilterValuesSeparator() { return GridColumnFilter::getFilterValuesSeparator(); }

  public function setShowGridNamePrefix($bool) { $this->_showGridNamePrefix = $bool; }

  public function getSqlSelect($src){
    $src = $this->getSource();
    if (empty($src))return false;
    $grid = new $src($this->getSqlSelectSettings());
    return $grid;
  }

  public function getSqlDataSource($src){
    $grid = $this->getSqlSelect($src);
    $data = new SqlDataSource($this->getDataSourceSettings(),$grid);
    return $data;
  }

  protected function _initSettings() { }

  protected function _userInitSettings() { }

  public function &getName() { return $this->_name; }

  public function getFooterDataSource($key = '_DEFAULT'){ return $key ? $this->_footerDataSource[$key] : $this->_footerDataSource; }
  
  public function setFooterDataSource($vals){
    if (!is_array($vals))$vals = array('_DEFAULT' => $vals);
    foreach($vals as $val){
      if (!($val instanceof DataSource)){
        throw new ExceptionUser('GridSettings::footer have to be datasource');
      }
    }
    $this->_footerDataSource = $vals;
  }
  
  protected function _setFooterData(){
    if (!is_array($this->_footerDataSource)) return false;
    $data = array('ANY' => array());
    foreach($this->_footerDataSource as $key => $datasource){
      $sqlSource = $datasource;
      $sqlSource->mergeFilterFromGrid($this);
      $sqlSource->reset();
      $data[$key] = $sqlSource->currentData;
      foreach($sqlSource->currentData as $k => $v){
        $data['ANY'][$k] = $v;
      }
    }
    $this->_footerData = $data;
    return $data;
  }

  public function &getColumn($columnId) {
    if (!isset($this->_columns[$columnId])) {
      throw new ExceptionUser("GridSettings::getColumn: unknown column ($columnId)");
    }
    return $this->_columns[$columnId];
  }

  public function &getGridClass() { return $this->_gridClass; }
  public function setGridClass($class) { $this->_gridClass = $class; }

  public function &getColumnsMask() { return $this->_columnsMask; }
  public function setColumnsMask($columnsMask) {  $this->_columnsMask = $columnsMask; }

  public function getForcedSources() { return $this->_forcedSources; }
  public function setForcedSources($forcedSources) { $this->_forcedSources = $forcedSources; }

  public function setAddIndexes($addIndexes) { $this->_addIndexes = $addIndexes; }

  public function &getRows() { return $this->_rows; }

  public function addFilter($name, $value) { $this->_filter[$name] = $value; }
  public function &getFilter() { return $this->_filter; }
  public function setFilter($filter) { $this->_filter = $filter; }
  public function getDefaultFilter() { return array(); }
 
  public function addSqlSelectParam($name, $value) { $this->_sqlSelectParams[$name] = $value; }
  public function &getSqlSelectParams() { return $this->_sqlSelectParams; }
  public function setSqlSelectParams($params) { $this->_sqlSelectParams = $params; }
 
  public function setRowsOnPage($num = -1){
    if (is_int($num)) $this->_onPage = $num;
    if ($num <= 0) $this->_page = 1;
  }

  public function setOnPage($onpage) { $this->_onPage = $onpage; }

  public function setPage($page) { $this->_page = $page; }

  public function &getDataSourceSettings(){
    $params = array();
    $params['page'] =& $this->_page;
    $params['onPage'] =& $this->_onPage;
    $params['addIndexes'] =& $this->_addIndexes;
    $settings = new DataSourceSettings($params);
    return $settings;
  }
  
  public function &getSqlSelectSettings() {
    $params = array();
    if (isset($this->_order)) {
      $params['order'] =& $this->getColumn($this->_order)->getOrder();
    }
    if (isset($this->_filter)) {
      $params['filter'] =& $this->_prepareSqlSelectFilter();
    }
    if (isset($this->_filter)) {
      $params['filterJoins'] =& $this->_prepareSqlSelectFilterJoins();
    }
    if (isset($this->_sqlSelectParams)) {
      $params['selectParams'] =& $this->getSqlSelectParams();
    }

    $params['columnsMask'] = $this->_prepareSqlSelectColumnsMask();
    $settings = new SqlSelectSettings($params);
    return $settings;
  }

  protected function &_prepareSqlSelectFilter() {
    $app = Application::get();
    $ret = array();
    
    foreach ($this->_filter as $columnId => $value) {
      $colFilter = $this->getColumn($columnId)->getColumnFilter();
      if ($colFilter->getSqlJoin()) continue;
      $colFilter->setValue($value);
      $filter = $colFilter->getSqlFilter();
      if (count($filter)) {
        $ret[] = $filter;
      }
    }
    return $ret;
  }
  
  public function prepareSqlSelectFilter(){ return $this->_prepareSqlSelectFilter(); }
 
  protected function &_prepareSqlSelectFilterJoins() {
    $ret = array();
    foreach ($this->_filter as $columnId => $value) {
      $colFilter = $this->getColumn($columnId)->getColumnFilter();
      if (!$join = $colFilter->getSqlJoin()) continue;
      $colFilter->setValue($value);
      $filter = $colFilter->getSqlFilter();
      if (count($filter)) {
        if (!isset($ret[$join])) $ret[$join] = array();
        $ret[$join][] =& $filter;
      }
    }
    return $ret;
  }

  protected function _prepareSqlSelectColumnsMask() {
    $ret = $this->getForcedSources();
    foreach ($this->getColumnsMask() as $columnId) {
      $sourceDat = $this->getColumn($columnId)->getSource(true);
      if (!is_array($sourceDat))$sourceDat = array($sourceDat);
      foreach ($sourceDat as $source){
        if (!in_array($source, $this->_fakeSources)) {
          $ret[] = $source;
        }
      }
    }
    return array_unique($ret);
  }

  protected function _createColumnsMask() { $this->_columnsMask = array_keys($this->_columns); }

  public function updateSettings() {
    $app = Application::get();

    if ($app->request->isSetParam('page', array('get','post'))) {
      $this->_page = $app->request->getParams('page');
    }
    if ($app->request->isSetParam('onPage', array('get','post'))) {
      $this->_onPage = $app->request->getParams('onPage');
    }
    if ($app->request->isSetParam('order', array('get','post'))) {
      $this->_order = $app->request->getParams('order');
    }
    if ($app->request->isSetParam('orderDirection', array('get','post'))) {
      $this->_orderDirection[$this->_order] = $app->request->getParams('orderDirection');
    }
    if (is_array($columnsMask = $app->request->getParams('columnsMask'))) {
      $newColumnsMask = array();
      foreach ($columnsMask as $one) {
        if ($one != '') { 
          $newColumnsMask[] = $one;
        }
      }
      if (count($newColumnsMask)) {
        $this->_columnsMask = $newColumnsMask;
      }
    }
    if (is_array($filter = $app->request->getParams('filter'))) {
      if (isset($filter['reset'])) { $filter = $this->getDefaultFilter(); }
      $this->_filter = $filter;
    }
  }

  public function loadSettings() {
    $app = Application::get();
    $prefix = 'grid_'. $this->_name .'_';

    $page = intval($app->session->get($prefix .'page'));
    if ($page) { $this->_page = $page; }

    $onPage = intval($app->session->get($prefix .'onPage'));
    if ($onPage) { $this->_onPage = $onPage; }
    
    $order = $app->session->get($prefix .'order');
    if ($order != '') { $this->_order = $order; }

    $orderDirection = $app->session->get($prefix .'orderDirection');
    if (is_array($orderDirection)) { 
      $this->_orderDirection = $orderDirection; 
    }
    if (is_array($this->getOrderDirection())) { // schvalne za podminkou, muze byt nastaveno jako defaultni
      foreach ($this->getOrderDirection() as $columnId => $direction) {
        $this->getColumn($columnId)->setOrderDirection($direction);
      }
    }

    // columns mask se nastavuje vzdy v initSettings, neumoznujeme uzivateli menit pocet sloupcu
    // takze to neni potreba ukladat do session
    #$columnsMask = $app->session->get($prefix .'columnsMask');
    #if (is_array($columnsMask)) { 
    #  $this->_columnsMask = $columnsMask; 

    // pouze kdyz neni definovan columns mask zadny, udelam ho ze vsech sloupcu
    if (!is_array($this->_columnsMask)) { 
      $this->_createColumnsMask(); 
    }

    $filter = $app->session->get($prefix .'filter');
    if (is_array($filter)) { $this->_filter = $filter; }
  }

  public function saveSettings() {
    $app = Application::get();
    $prefix = 'grid_'. $this->getName() .'_';
    
    if (isset($this->_page)) {
      $app->session->set($prefix .'page', $this->_page);
    }
    if (isset($this->_onPage)) {
      $app->session->set($prefix .'onPage', $this->_onPage);
    }
    if (isset($this->_order)) {
      $app->session->set($prefix .'order', $this->_order);
    }
    
    // columns mask se nastavuje vzdy v initSettings, neumoznujeme uzivateli menit pocet sloupcu
    // takze to neni potreba ukladat do session
    #if (isset($this->_columnsMask) && count($this->_columnsMask)) {
    #  $app->session->set($prefix .'columnsMask', $this->_columnsMask);
    #}

    if (isset($this->_orderDirection)) {
      $app->session->set($prefix .'orderDirection', $this->_orderDirection);
    }

    if (isset($this->_filter)) {
      $app->session->set($prefix .'filter', $this->_filter);
    }
  }

  public function addRow($row) { $this->_rows[$row->getName()] = $row; }

  public function addColumn($column) {
    $columnName = $column->getName();
    $column->attachToGrid($this->_name);
    if ($this->_showGridNamePrefix) {$column->setShowGridNamePrefix(true);}
    else {$column->setShowGridNamePrefix(false);}
    $this->_columns[$columnName] = $column;
    if (isset($this->_orderDirection[$columnName])) {
      $column->setOrderDirection($this->_orderDirection[$columnName]);
    }
  }

  public function setOrder($columnId, $direction='asc') {
    $this->_order = $columnId;
    $this->_orderDirection = array( $columnId => $direction);
  }

  public function getOrder() { return $this->_order; }

  public function &getOrderDirection() { return $this->_orderDirection;  }

  public function getFirstValue($prvek){
    if (is_array($prvek)){
      reset($prvek);
      return $this->getFirstValue(current($prvek));
    } else return $prvek;
  }

  public function &getGuiGridTableAttributes() { return $this->_guiGridTableAttributes; }
  public function addGuiGridTableAttribute($attribute, $value) { $this->_guiGridTableAttributes[$attribute] = $value; }

  public function &getGuiGridPagerDivPagingAttributes() { return $this->_guiGridPagerDivPagingAttributes; }
  public function addGuiGridPagerDivPagingAttribute($attribute, $value) { $this->_guiGridPagerDivPagingAttributes[$attribute] = $value; }

  public function &getGuiGridPagerDivOnPageAttributes() { return $this->_guiGridPagerDivOnPageAttributes; }
  public function addGuiGridPagerDivOnPageAttribute($attribute, $value) { $this->_guiGridPagerDivOnPageAttributes[$attribute] = $value; }

  public function getGuiGridPagerPagesBefore() { return $this->_guiGridPagerPagesBefore; }
  public function setGuiGridPagerPagesBefore($set) { $this->_guiGridPagerPagesBegore = $set; }

  public function getGuiGridPagerArrows() { return $this->_guiGridPagerArrows; }
  public function setGuiGridPagerArrows($set) {  $this->_guiGridPagerArrows = $set; }

  public function &getGuiGridPagerShowForm() { return $this->_guiGridPagerShowForm; }
  public function setGuiGridPagerShowForm($set) {  $this->_guiGridPagerShowForm = $set; }

  public function &getGuiGridPagerShowLeft() { return $this->_guiGridPagerShowLeft; }
  public function setGuiGridPagerShowLeft($set) {  $this->_guiGridPagerShowLeft = $set; }

  public function &getGuiGridPagerShowLeftLeft() { return $this->_guiGridPagerShowLeftLeft; }
  public function setGuiGridPagerShowLeftLeft($set) {  $this->_guiGridPagerShowLeftLeft = $set; }
  
  public function &getGuiGridPagerShowRight() { return $this->_guiGridPagerShowRight; }
  public function setGuiGridPagerShowRight($set) {  $this->_guiGridPagerShowRight = $set; }

  public function &getGuiGridPagerShowRightRight() { return $this->_guiGridPagerShowRightRight; }
  public function setGuiGridPagerShowRightRight($set) {  $this->_guiGridPagerShowRightRight = $set; }

  public function &getGuiGridPagerLeftLeftImgSrc() { return $this->_guiGridPagerLeftLeftImgSrc; }
  public function setGuiGridPagerLeftLeftImgSrc($set) {  $this->_guiGridPagerLeftLeftImgSrc = $set; }

  public function &getGuiGridPagerLeftImgSrc() { return $this->_guiGridPagerLeftImgSrc; }
  public function setGuiGridPagerLeftImgSrc($set) { $this->_guiGridPagerLeftImgSrc = $set; }

  public function &getGuiGridPagerRightRightImgSrc() { return $this->_guiGridPagerRightRightImgSrc; }
  public function setGuiGridPagerRightRightImgSrc($set) {  $this->_guiGridPagerRightRightImgSrc = $set; }

  public function &getGuiGridPagerRightImgSrc() { return $this->_guiGridPagerRightImgSrc; }
  public function setGuiGridPagerRightImgSrc($set) {  $this->_guiGridPagerRightImgSrc = $set; }

  public function &getUrlPrefix() { return $this->_urlPrefix; }
  public function setUrlPrefix($set) {  $this->_urlPrefix = $set; }

  public function &getGuiGridPagerDivAttributes() { return $this->_guiGridPagerDivAttributes; }
  public function addGuiGridPagerDivAttribute($attribute, $value) { $this->_guiGridPagerDivAttributes[$attribute] = $value; }

  public function setGuiGridPagerButtonLabel($label) { $this->_guiGridPagerButtonLabel = $label; }
  public function setGuiGridPagerLabel($label) { $this->_guiGridPagerLabel = $label; }
  
  public function &getGuiGridFilterDivAttributes() { return $this->_guiGridFilterDivAttributes; }
  public function addGuiGridFilterDivAttribute($attribute, $value) { $this->_guiGridFilterDivAttributes[$attribute] = $value; }
  
  public function &getGuiGridMultiactionDivAttributes() { return $this->_guiGridMultiactionDivAttributes; }
  public function addGuiGridMultiactionDivAttribute($attribute, $value) { $this->_guiGridMultiactionDivAttributes[$attribute] = $value; }

  public function &getGuiGridFilterShowButtonReset() { return $this->_guiGridFilterShowButtonReset; }
  public function setGuiGridFilterShowButtonReset($set) {  $this->_guiGridFilterShowButtonReset = $set; }

  public function setGuiGridFilterButtonLabelSubmit($label) { $this->_guiGridFilterButtonLabelSubmit = $label; }
  public function setGuiGridFilterButtonLabelReset($label) { $this->_guiGridFilterButtonLabelReset = $label; }

  public function setGuiGridColumnChangerButtonLabel($label) { $this->_guiGridColumnChangerButtonLabel = $label; }

  public function getGuiGridTableSettings() {
    $params = array();
    $params['elementAttributes'] =& $this->_guiGridTableAttributes;
    return $params;
  }

  public function &getGuiGridRowSettings() {
    $params = array();
    $params['page'] =& $this->_page;
    $params['records'] =& $this->_records;
    $params['rows'] =& $this->_rows;
    $params['columns'] =& $this->_columns;
    $params['columnsMask'] =& $this->getColumnsMask();
    $params['exec'] =& $this->_execAction;
    $foot = false;
    foreach ($params['columnsMask'] as $columnName) {
      $column = $this->getColumn($columnName);
      if ($column->headerGuiElement instanceof GuiGridCellRenderer) {
        $column->headerGuiElement->setGridName($this->getName());
        $column->headerGuiElement->setGridClass(get_class($this));
        $column->headerGuiElement->setOutputColumn($column->getName());
        $column->headerGuiElement->setOutputData($column->getLabel());
      }
      if ($column->footerGuiElement instanceof GuiGridCellRenderer) {
        $foot = true;
        $column->footerGuiElement->setGridName($this->getName());
        $column->footerGuiElement->setGridClass(get_class($this));
        $column->footerGuiElement->setOutputColumn($column->getName());
        $column->footerGuiElement->setOutputData($column->getLabel());
      }
      if ($column->getFooterDataLabel()) $foot = true;
      foreach ($column->guiElements as $index => $one) {
        if ($one instanceof GuiGridCellRenderer) {
          $one->setGridName($this->getName());
          $one->setGridClass(get_class($this));
          $one->setOutputColumn($column->getName());
          $one->setOutputSource($this->getFirstValue($column->getSource()));
        }
      }
    }
    $this->_setFooterData();
    $params['footerData'] = $foot ? $this->_footerData : false;
    return $params;
  }

  public function &getGuiGridPagerSettings() {
    $params = array();
    $params['gridName'] = $this->_name;
    $params['gridClass'] = get_class($this);
    $params['exec'] =& $this->_execAction;
    $params['records'] =& $this->_records;
    $params['page'] =& $this->_page;
    $params['onPage'] =& $this->_onPage;
    $params['maxPages'] =& $this->_maxPages;
    $params['pagerDivAttributes'] =& $this->_guiGridPagerDivAttributes;
    $params['pagesBefore'] =& $this->_guiGridPagerPagesBefore;
    $params['pagingDivAttributes'] =& $this->_guiGridPagerDivPagingAttributes;
    $params['onPageDivAttributes'] =& $this->_guiGridPagerDivOnPageAttributes;
    $params['buttonLabel'] =& $this->_guiGridPagerButtonLabel;
    $params['pagerLabel'] =& $this->_guiGridPagerLabel;
    $params['showForm'] = $this->_guiGridPagerShowForm;
    $params['showLeft'] = $this->_guiGridPagerShowLeft;
    $params['showLeftLeft'] = $this->_guiGridPagerShowLeftLeft;
    $params['showRight'] = $this->_guiGridPagerShowRight;
    $params['showRightRight'] = $this->_guiGridPagerShowRightRight;
    $params['pageLeftImgSrc'] = $this->_guiGridPagerLeftImgSrc;
    $params['pageLeftLeftImgSrc'] = $this->_guiGridPagerLeftLeftImgSrc;
    $params['pageRightImgSrc'] = $this->_guiGridPagerRightImgSrc;
    $params['pageRightRightImgsrc'] = $this->_guiGridPagerRightRightImgSrc;
    $params['urlPrefix'] = $this->_urlPrefix;
    return $params;
  }

  public function &getGuiGridColumnsChangerSettings() {
    $params = array();
    $params['gridName'] = $this->_name;
    $params['gridClass'] = get_class($this);
    $params['exec'] =& $this->_execAction;
    $params['columns'] =& $this->_columns;
    $params['columnsMask'] =& $this->getColumnsMask();
    $params['columnsRestricted'] =& $this->getGridColumnsChangerRestrictedColumns();
    $params['buttonLabel'] =& $this->_guiGridColumnChangerButtonLabel;
    return $params;
  }

  public function &getGridColumnsChangerRestrictedColumns() { return $this->_gridColumnsChangerRestrictedColumns; }
  public function setGridColumnsChangerRestrictedColumns($array) { $this->_gridColumnsChangerRestrictedColumns = $array; }

  public function &getGuiGridFilterSettings() {
    $params = array();
    $params['gridName'] = $this->_name;
    $params['gridClass'] = get_class($this);
    $params['exec'] =& $this->_execAction;
    $params['columns'] =& $this->_columns;
    $params['filter'] =& $this->_filter;
    $params['divAttributes'] =& $this->_guiGridFilterDivAttributes;
    $params['buttonLabelSubmit'] =& $this->_guiGridFilterButtonLabelSubmit;
    $params['showButtonReset'] =& $this->_guiGridFilterShowButtonReset;
    $params['buttonLabelReset'] =& $this->_guiGridFilterButtonLabelReset;
    $params['urlPrefix'] = $this->_urlPrefix;
    return $params;
  }

  public function getFilterSessionValue($var) {
    $app = Application::get();
    $prefix = 'grid_'. $this->_name .'_';
    $filter = $app->session->get($prefix .'filter');
    if (isset($filter) && isset($filter[$var])) { return $filter[$var]; }
  }
}

class GridColumn {
  protected $_name;
  protected $_source;
  protected $_label;
  protected $_labelHtmlize=true;
  protected $_labelToNbsp=false;
  protected $_labelToBr=false;
  protected $_containToNbsp=false;
  protected $_containToBr=false;
  protected $_elementAttributes = array();
  protected $_elementFooterAttributes = array();
  protected $_orderAsc;
  protected $_orderDesc;
  protected $_orderDirection;
  protected $_searchType;
  protected $_footerDataLabel = false;
  protected $_footerDataSource = false;
  protected $_gridName = '';
  protected $_showGridNamePrefix = false;
  protected $_columnFilter;
  protected $_filterDataSource;
  protected $_filterParams = array();
  
  public $guiElements = array();
  public $headerGuiElement;
  public $footerGuiElement;
 
  public function __construct($name, $source, $label, $searchType='input') {
    $this->_name = $name;
    $this->_source = $source;
    $this->_label = $label;
    $this->_filterDataSource = new HashDataSource(new DataSourceSettings, array());
    $this->setSearchType($searchType);
    if (is_array($source)){
      reset($source);
      $source=current($source);
    }
    $this->_orderAsc = array( array('source' => $source, 'direction' => 'asc'));
    $this->_orderDesc = array( array('source' => $source, 'direction' => 'desc'));
  }

  public function attachToGrid($gridName) {
    $this->_gridName = $gridName;
    $this->getColumnFilter()->attachToGrid($gridName);
  }
  public function setShowGridNamePrefix($bool) {
    $this->_showGridNamePrefix = $bool;
    if($this->getColumnFilter() instanceof GridColumnFilter) {
      $this->getColumnFilter()->setShowGridNamePrefix($bool);
    }
  }
  public function setLabelToNbsp($change = true) { $this->_labelToNbsp = $change; }
  public function setLabelToBr($change = true) { $this->_labelToBr = $change; }
  public function setContainToNbsp($change = true) { $this->_containToNbsp = $change; }
  public function setContainToBr($change = true) { $this->_containToBr = $change; }

  public function printValue($value) { return $this->_convertToHtml($value, false); }

  public function getFooterDataLabel() { return $this->_footerDataLabel; }

  public function getFooterDataSource() { return $this->_footerDataSource; }

  public function printFooterValue($value) { return $this->printValue($value); }

  public function setFooterDataLabel($val, $source = 'ANY'){
    $this->_footerDataLabel = $val;
    $this->_footerDataSource = $source;
  }

  public function getName() { return $this->_name; }

  public function getSource($includeInnerElements = false) {
    $src = $this->_source;
    if ($includeInnerElements){
      if (!is_array($src))$src = array($src);
      foreach ($this->guiElements as $gui){
        if (!($gui instanceof GuiGridCellAction)) continue;
        $src = array_merge($src, $gui->getDataSources());
      } 
    }
    return $src;
  }

  public function getSearchType() { return $this->_searchType; }

  public function getColumnFilter() { return $this->_columnFilter; }

  public function setColumnFilter($obj=false) {
    if (!$obj){ $this->_columnFilter = null;
    } elseif($obj instanceof GridColumnFilter) {
      $this->_columnFilter = $obj;
    } else {
      throw new ExceptionUser("error: GridColumn: set column filter from not supported type");
    }
  }
  
  public function setSearchType($searchType) {
    $this->_searchType = $searchType;
    $filterClass = 'GridColumnFilter_'.$searchType;
    if (class_exists($filterClass)){
      $this->_columnFilter = new $filterClass($this);
    } elseif (!$searchType) {
      $this->_columnFilter = null;
    } else {
      throw new ExceptionUser("error: GridColumn: create unknow filter '$filterClass'");
    }
  }

  public function setOrderAsc($orderAsc) { $this->_orderAsc = $orderAsc; }
  public function setOrderDesc($orderDesc) { $this->_orderDesc = $orderDesc; }
  public function setOrderDirection($direction) { $this->_orderDirection = ($direction == 'desc') ? 'desc' : 'asc'; }

  public function &getOrder() {
    if ($this->_orderDirection == 'desc') {
      return $this->_orderDesc;
    } else {
      return $this->_orderAsc;
    }
  }

  public function &getFilterDataSource() { return $this->_filterDataSource; }
  public function setFilterDataSource($dataSource) { $this->_filterDataSource = $dataSource; }

  public function getFilterParams() { return $this->_filterParams; }
  public function setFilterParams($params) { $this->_filterParams = $params; }
  public function addFilterParam($key, $value) { $this->_filterParams[$key] = $value; }

  public function getLabel() { return $this->_convertToHtml($this->_label); }
  public function setLabel($value) { $this->_label = $value; }

  protected function _convertToHtml($val, $label = true){
    $LBr = is_bool($this->_labelToBr) ? Array ("\n" => "<br/>\n") : Array($this->_labelToBr => "<br/>\n");
    $CBr = is_bool($this->_containToBr) ? Array ("\n" => "<br/>\n") : Array($this->_containToBr => "<br/>\n");
    $LNbsp = is_bool($this->_labelToNbsp) ? Array (" " => "&nbsp;") : Array($this->_labelToNbsp => "&nbsp;");
    $CNbsp = is_bool($this->_containToNbsp) ? Array (" " => "&nbsp;") : Array($this->_containToNbsp => "&nbsp;");
    $BR = Array("<br/>"=>"<br />");
    if ($label){
      if ($this->_labelToBr) $val = strtr($val, $LBr);
      if ($this->_labelToNbsp) $val = strtr($val, $LNbsp);
    } else {
      if ($this->_containToBr) $val = strtr($val, $CBr);
      if ($this->_containToNbsp) $val = strtr($val, $CNbsp);
    }
    $val = strtr($val,$BR);
    return $val;
  }

  public function setLabelHtmlize($value) { $this->_labelHtmlize = $value; }
  public function getLabelHtmlize() { return $this->_labelHtmlize; }

  public function getElementAttributes(&$data=null) { return $this->_elementAttributes; }
  public function setElementAttributes($elementAttributes) { $this->_elementAttributes = $elementAttributes; }
  public function addElementAttribute($attribute, $value) { $this->_elementAttributes[$attribute] = $value; }

  public function getFooterElementAttributes(&$data=null) { return $this->_elementFooterAttributes; }
  public function setFooterElementAttributes($elementAttributes) { $this->_elementFooterAttributes = $elementAttributes; }
  public function addFooterElementAttribute($attribute, $value) { $this->_elementFooterAttributes[$attribute] = $value; }

  public function addGuiElement($guiElement) { $this->guiElements[] = $guiElement; }
  public function addHeaderGuiElement($guiElement = null) { $this->headerGuiElement = $guiElement; }
  public function addFooterGuiElement(&$guiElement) { $this->footerGuiElement =& $guiElement; }
}

class GridColumnString extends GridColumn {
  
  public function printValue($value) { return $this->_convertToHtml(Application::get()->htmlspecialchars($value)); }

}

class GridRow {
  protected $_name;
  protected $_elementAttributes = array();

  public function __construct($name) { $this->_name = $name; }

  public function getName() { return $this->_name; }

  public function getElementAttributes() { return $this->_elementAttributes; }
  public function setElementAttributes($elementAttributes) { $this->_elementAttributes = $elementAttributes; }
  public function addElementAttribute($attribute, $value) { $this->_elementAttributes[$attribute] = $value; }
}

class GridRowRotate extends GridRow {
  protected $_index = -1;
  protected $_indexMax;
  protected $_rows;

  public function __construct($name, &$rows) {
    parent::__construct($name);
    $this->_rows =& $rows;
    $this->_indexMax = count($rows);
  }

  protected function _next() {
    $this->_index++;
    if ($this->_index == $this->_indexMax) {
      $this->_index = 0;
    }
    $this->setElementAttributes($this->_rows[$this->_index]->getElementAttributes());
  }

  public function getElementAttributes() {
    $this->_next();
    return parent::getElementAttributes();
  }
}

abstract class GridColumnFilter{
  protected $_source;
  protected $_type = 'mono';
  protected $_function = false;
  protected $_visible = true;
  protected $_label = '';
  protected $_labelHtml = '';
  protected $_value = '';
  protected $_gridColumn = false;
  protected $_sqlJoin = false;
  protected $_toSql = true;
  protected $_defaultValue = false;
  protected $_useEmpty = false;
  protected $_useTextStorage = false;
  protected $_columnId = false;
  protected $_dataSource = false;
  protected $_searchType = false;
  protected $_classInput = '';
  protected $_classDiv = '';
  protected $_filterParams = array();
  protected $_showGridNamePrefix = false;
  protected $_gridName = '';
  private $_backUp = array();
  static protected $_filterIsNull = 'N*U*L*L';
  static protected $_filterIsNotNull = 'N*O*T*N*U*L*L';
  static protected $_filterValuesSeparator = '*|*';
  
  public function __construct($column = array()){
    $typ = get_class($this);
    $typ = substr($typ, strpos($typ, '_')+1);
    $this->_searchType = $typ;
    if ($this->setParamsByColumn($column)){
    } elseif (is_array($column)){
      $this->setParamsByHash($column);
    } else {
      throw new ExceptionUser('GridColumnFilter::Unexpected type of constructor param');
    }
    
    $this->_initParams();
  }
  
  protected function _initParams(){}
  
  public function setParamsByColumn($column=false){
    if (!($column instanceof GridColumn))return false;
    $this->_gridColumn = &$column;
    return true;
  }

  public function setParamsByHash($params=array()){
    if (is_array($params)){
      if (isset($params['gridColumn']))   $this->setParamsByColumn($params['gridColumn']);

      if (isset($params['visible']))      $this->_visible     = $params['visible'];
      if (isset($params['source']))       $this->_source      = $params['source'];
      if (isset($params['type']))         $this->_type        = $params['type'];
      if (isset($params['function']))     $this->_function    = $params['function'];
      if (isset($params['value']))        $this->_value       = $params['value'];
      if (isset($params['sqlJoin']))      $this->_sqlJoin     = $params['sqlJoin'];
      if (isset($params['label']))        $this->_label       = $params['label'];
      if (isset($params['labelHtml']))    $this->_labelHtml   = $params['labelHtml'];
      if (isset($params['columnId']))     $this->_columnId    = $params['columnId'];
      if (isset($params['dataSource']))   $this->_dataSource  = $params['dataSource'];
      if (isset($params['useTextStorage']))   $this->_useTextStorage  = $params['useTextStorage'];
      if (is_array($params['filterParams'])) $this->_filterParams = $params['filterParams'];
    }
    
  }
  
  protected abstract function _initSqlFilter();

  protected abstract function _getGuiElement();

  public function getGui($gridName=false){
    if (isset($gridName)) { $this->_gridName= $gridName; }
    if (!$this->_visible)return false;
    if (($this->getColumn())&&($this->_searchType != $this->_gridColumn->getSearchType()))return false;
    return $this->_getGuiElement();
  }
  
  public function getSqlFilter() {
    $this->_initSqlFilter();
    $filtr = array();
    if ($this->_toSql){
      $src = $this->getSource(); 
      switch ($this->getType()){
        case 'mono':
          if (is_array($src) && (count($src) == 1)){
            reset($src);
            $src = current($src);
          }
          $this->_checkSource($src,1);
        break;
        case 'bi':
          $this->_checkSource($src,2);
        break;
        case 'tri':
          $this->_checkSource($src,3);
        break;
        case 'quad':
          $this->_checkSource($src,4);
        break;
        default:
        break;
      }

      $filtr = array(
            'source' => $this->getSource(),
            'type' => $this->getType(),
            'function' => $this->getFunction());
    }      
    return $filtr;
  }
  
  protected function _checkSource($src, $expected){
    if ($expected > 1){
      if (!is_array($src)) throw new ExceptionUser (get_class($this).":: invalid type of source for filter type :".$this->getType());
      if (is_array($src) && (count($src) < $expected)) throw new ExceptionUser (get_class($this).":: not enought params(".count($src).") for filter type :".$this->getType());  
    }
  }

  public function addToSqlFilter($filterSource) {
    $filtr = $this->getSqlFilter();
    if (count($filtr)){
      if ($this->getSqlJoin()) $filterSource = &$filterSource[$this->getSqlJoin()];
      $filterSource[] = $filtr;
    }
  }

  static public function getFilterIsNull() { return self::$_filterIsNull; }
  static public function getFilterIsNotNull() { return self::$_filterIsNotNull; }
  static public function getFilterValuesSeparator() { return self::$_filterValuesSeparator; }
  
  public function getColumn() { return ($this->_gridColumn instanceof GridColumn) ? $this->_gridColumn : false; }

  public function getType() { return $this->_type; }
  public function setType($val = '') { $this->_type = $val; }

  public function getFunction() { return $this->_function; }
  public function setFunction($val = '') { $this->_function = $val; }

  public function getValue() { return $this->_value; }
  public function setValue($val = '') { $this->_value = $val; }

  public function getUseTextStorage() { return $this->_useTextStorage; }
  public function setUseTextStorage($val = false) { $this->_useTextStorage = $val; }

  public function getSqlJoin() { return $this->_sqlJoin; }
  public function setSqlJoin($val = '') { $this->_sqlJoin = $val; }

  public function getSource(){
    $source = $this->_source;
    return $source ? $source : ($this->getColumn() ? $this->_gridColumn->getSource() : '');
  }

  public function setSource($val = '') { $this->_source = $val; }

  public function getLabel() {
    $label = $this->_label;
    return $label ? $label : ($this->getColumn() ? $this->_gridColumn->getLabel() : '');
  }

  public function setLabel($val = '') { $this->_label = $val; }

  public function getLabelHtml() {
    $label = $this->_labelHtml;
    return $label ? $label : ($this->getColumn() ? $this->_gridColumn->getLabelHtmlize() : '');
  }

  public function setLabelHtml($val = '') { $this->_labelHtml = $val; }

  public function getColumnId() {
    $id = $this->_columnId;
    return $id ? $id : ($this->getColumn() ? $this->_gridColumn->getName() : '');
  }

  public function setColumnId($val = '') { $this->_columnId = $val; }

  public function getDataSource() {
    $data = $this->_dataSource;
    return $data ? $data : ($this->getColumn() ? $this->_gridColumn->getFilterDataSource() : new Datasource());
  }

  public function setDataSource($val = '') { $this->_dataSource = $val; }

  public function getFilterParams() {
    $params = $this->_filterParams;
    return (is_array($params) && (count($params) != 0)) ? $data : ($this->getColumn() ? $this->_gridColumn->getFilterParams() : array());
  }

  public function setFilterParams($val = '') { $this->_filterParams = $val; }

  public function setVisibility($val = true){ $this->_visible = $val; }
  
  public function setClassInput($className) {	$this->_classInput = $className; }
  
  public function setClassDiv($classDiv) { $this->_classDiv = $classDiv; }

  public function isSetValue($subid = false){
    $val = $this->_value;
    $res = (!isset($val) || (is_array($val) && (count($val) == 0)) || ($val === ''));
    if ($subid) $res = (!is_array($val) || !isset($val[$subid]) || ($val[$subid] === ''));
    $this->_toSql = !$res;
    return !$res; 
  }

  protected function _getListValues($values) {
    $app = Application::get();
    $listValues = '';
    foreach ((is_array($values) ? $values : explode(self::$_filterValuesSeparator, $values)) as $value) {
      $listValues .= ($listValues ? ',' : '') ."'". $app->db->escapeString($value) ."'";
    }
    return $listValues;
  }

  protected function _generateGuiInput($subid = false, $title = false, $class = null){
    $app = Application::get();
    $value = $this->getValue();
    if (is_array($value) && $subid && isset($value[$subid])) $value = $value[$subid];
    $id = $this->getColumnId();
    $label = $this->getLabel();
    if ($title) $label = $title;
    $gui = new GuiFormInput(array_merge(array(
          'id' => $this->_insertGridNamePrefix().$id.($subid ? '_'.$subid : ''),
          'label' => $label,
          'labelHtmlize' => $this->getLabelHtml(),
          'name' => 'filter['. $id .']'.($subid ? '['.$subid.']' : ''),
          'value' => $value), $this->getFilterParams()));
    if ($class) {
      $gui->addClassDiv($class);
    }
    if ($this->_classDiv) {
      $gui->addClassDiv($this->_classDiv);
    }
    if ($this->_classInput) {
      $gui->addClassInput($this->_classInput);
    }
    return $gui;
  }

  protected function _insertGridNamePrefix() {
    if($this->_showGridNamePrefix) {$ret=$this->_gridName.'_';}
    else {$ret='';}
    return $ret;
  }

  public function setShowGridNamePrefix($bool) { $this->_showGridNamePrefix = $bool; }

  public function attachToGrid($gridName) { $this->_gridName = $gridName; }

  protected function _generateGuiCheckbox(){
    $value = $this->getValue();
    $dataSource = $this->getDataSource();
    $dataSource->reset();
    $gui = new GuiFormInput(array_merge($this->getFilterParams(), array(
          'type' => 'checkbox',
          'id' => $this->_insertGridNamePrefix().$this->getColumnId(),
          'label' => $this->getLabel(),
          'labelHtmlize' => $this->getLabelHtml(),
          'name' => 'filter['. $this->getColumnId() .']',
          'checked' => $value,
          'value' =>  is_array($dataSource->currentData) ? array_shift($dataSource->currentData) : '')));
    return $gui;
  }

  protected function _generateGuiSelect(){
    $value = $this->getValue();
    $gui = new GuiFormSelect(array_merge($this->getFilterParams(), array(
          'dataSource' => $this->getDataSource(),
          'id' => $this->_insertGridNamePrefix().$this->getColumnId(),
          'label' => $this->getLabel(),
          'labelHtmlize' => $this->getLabelHtml(),
          'useTextStorage' => $this->getUseTextStorage(),
          'name' => 'filter['. $this->getColumnId() .']',
          'value' => $value)));
    return $gui;
  }
}

class GridColumnFilter_inputExact extends GridColumnFilter{
  
  protected function _initSqlFilter() {
    $app = Application::get();
    if ($this->isSetValue()){
      $value = $app->db->escapeString($this->getValue());
      $this->_function = "%s = '$value'";
    }
  }

  protected function _getGuiElement(){
    return $this->_generateGuiInput();
  }
}

class GridColumnFilter_none extends GridColumnFilter_inputExact{
  
  protected function _initParams(){ 
    $this->_visible = false;
  }
}

class GridColumnFilter_inputExactNull extends GridColumnFilter{

  protected function _initSqlFilter() {
    $app = Application::get();
    $value = $this->getValue();
    if ($this->isSetValue()) {
      if ($value === self::getFilterIsNull()){
          $this->_function = '%s is null';
      } elseif ($value === self::getFilterIsNotNull()){
          $this->_function = '%s is not null';
      } else {
          $this->_function = '%s = \''. $app->db->escapeString($value) .'\'';
      }
    }
  }  

  protected function _getGuiElement(){
    return $this->_generateGuiInput();
  }
}      

class GridColumnFilter_noneNull extends GridColumnFilter_inputExactNull{

  protected function _initParams(){ 
    $this->_visible = false;
  }
}
      
class GridColumnFilter_input extends GridColumnFilter {

  protected function _initSqlFilter() {
    $app = Application::get();
    if ($this->isSetValue()) {
      $value = $app->db->escapeString($this->getValue());
      $this->_function = "CAST(%s AS CHAR CHARACTER SET UTF8) COLLATE UTF8_UNICODE_CI LIKE '%%$value%%'";
    }
  }

  protected function _getGuiElement(){
    return $this->_generateGuiInput();
  }
}

class GridColumnFilter_inputCS extends GridColumnFilter {

  protected function _initSqlFilter() {
    $app = Application::get();
    if ($this->isSetValue()) {
      $value = $app->db->escapeString($this->getValue());
      $this->_function = "%s like '%%$value%%'";
    }
  }

  protected function _getGuiElement(){
    return $this->_generateGuiInput();
  }
}

class GridColumnFilter_inputDate extends GridColumnFilter{

  protected function _initSqlFilter() {
    $app = Application::get();
    if ($this->isSetValue()&&$app->regionalSettings->checkHumanDate($this->getValue())) {
      $value = $app->regionalSettings->convertHumanToDate($this->getValue());
      $value = $app->db->escapeString($value);
      $this->_function = "%s like '%%$value%%'";
    }
  }

  protected function _getGuiElement(){
    return $this->_generateGuiInput();
  }
}

class GridColumnFilter_inputDateCalendar extends GridColumnFilter_inputDate {
  
  protected function _generateGuiCalendarInput($subid = false, $title = false, $class = null){
    $app = Application::get();
    $value = $this->getValue();
    if (is_array($value) && $subid && isset($value[$subid])) $value = $value[$subid];
    $id = $this->getColumnId();
    $label = $this->getLabel();
    if ($title) $label = $title;
    $gui = new GuiFormInputDate(array_merge(array(
          'id'              => $this->_insertGridNamePrefix().$id.($subid ? '_'.$subid : ''),
          'name'            => 'filter['. $id .']'.($subid ? '['.$subid.']' : ''),
          'value'           => $value,
          'label'           => $label,
          'labelHtmlize'    => $this->getLabelHtml(),
          'jsVarName'       => 'calendarFilter'.$id .($subid ? '['.$subid.']' : ''),
          'calendarDivName' => 'calendarFilter'.$id .($subid ? '['.$subid.']' : ''),
          'calendarIcon'    => 'img/cal.gif',
          'dateFormat'      => 'dd.MM.yyyy',
          'weekStartDay'    => 1,
          'todayLabel'      => $app->textStorage->getText('label.calendar_today'),
          'dayLabels'       => $app->textStorage->getText('label.calendar_dayLabels'),
          'monthLabels'     => $app->textStorage->getText('label.calendar_monthLabels'),
          'useTextStorage'  => false), $this->getFilterParams()));
    if ($class) {
      $gui->addClassDiv($class);
    }
    if ($this->_classDiv) {
      $gui->addClassDiv($this->_classDiv);
    }
    if ($this->_classInput) {
      $gui->addClassInput($this->_classInput);
    }
    return $gui;
  }

  protected function _getGuiElement(){
    return $this->_generateGuiCalendarInput();
  }
}


class GridColumnFilter_checkbox extends GridColumnFilter{

  protected function _initSqlFilter() {
    $app = Application::get();
    $value = $this->getValue();
    if ($this->isSetValue()) {
      if (!$this->_function) {
        $this->_function = '%s in ('. $this->_getListValues($value) .')';
      }
    }
  }

  protected function _getGuiElement(){
    return $this->_generateGuiCheckbox();
  }
}

class GridColumnFilter_select extends GridColumnFilter_checkbox{
  protected function _getGuiElement(){
    return $this->_generateGuiSelect();
  }
}

class GridColumnFilter_selectLike extends GridColumnFilter_select{

  protected function _initSqlFilter() {
    $app = Application::get();
    if ($this->isSetValue()) {
      $value = $this->getValue();
      $this->_function = "%s like '%%". $value ."%%'";
      }
    }
}

class GridColumnFilter_checkboxNull extends GridColumnFilter{

  protected function _initSqlFilter() {
    $app = Application::get();
    if ($this->isSetValue()) {
      $value = $this->getValue();
      if ($value === self::$_filterIsNull) {
        $this->_function = '%s is null';
      } elseif ($value === self::$_filterIsNotNull) {
        $this->_function = '%s is not null';
      } else {
        $this->_function = '%s in ('. $this->_getListValues($value) .')';
      }
    }
  }

  protected function _getGuiElement(){
    return $this->_generateGuiCheckbox();
  }
}

class GridColumnFilter_selectNull extends GridColumnFilter_checkboxNull{

  protected function _getGuiElement(){
    return $this->_generateGuiSelect();
  }
}

class GridColumnFilter_between extends GridColumnFilter{

  protected function _initSqlFilter() {
    $app = Application::get();
    if ((!is_array($this->getSource()))||(count($this->getSource()) < 2)) throw new ExceptionUser("GridColumnFilter:: Wrong number of params");
    if ($this->isSetValue()){
      $value = $app->db->escapeString($this->getValue());
      $this->_function = "%s<='$value' AND %s>='$value'";
      $this->_type = "bi";
    }
  }

  protected function _getGuiElement(){
    return $this->_generateGuiInput();
  }
}

class GridColumnFilter_betweenDate extends GridColumnFilter{

  protected function _initSqlFilter() {
    $app = Application::get();
    if ((!is_array($this->getSource()))||(count($this->getSource()) < 2)) throw new ExceptionUser("GridColumnFilter:: Wrong number of params");
    if ($this->isSetValue()&&($app->regionalSettings->checkHumanDate($this->getValue()))){
      $value = $app->regionalSettings->convertHumanToDate($this->getValue());
      $this->_function = "ifnull(%s,'0000-00-00')<='$value' AND ifnull(%s,'9999-99-99')>='$value'";
      $this->_type = "bi";
    }
  }

  protected function _getGuiElement(){
    return $this->_generateGuiInput();
  }
}

class GridColumnFilter_interval extends GridColumnFilter{
  protected $_secondLabel = ' ';

  public function setSecondLabel($val){
    $this->_secondLabel = $val;
  }
  
  public function getSecondLabel(){
    return $this->_secondLabel;
  }  
  
  protected function _initSqlFilter() {
    $app = Application::get();
    $value = $this->getValue();
    $src = $this->getSource();    if (is_array($src) && (count($src)<=1)){
      reset($src);
      $src = current($src);
    }
    if (!is_array($src)){
      $src = array($src, $src);
    }
    $i = 0;
    reset($src);
    $function = '';
    $source = array();
    if ($this->isSetValue('begin')){
      $val = $app->db->escapeString($value['begin']);
      $function .= "%s>='$val'";
      $source[] = current($src);
      $i++;
    }
    next($src);
    if ($this->isSetValue('end')){
      $val = $app->db->escapeString($value['end']);
      $function .= ($function ? ' AND ' : '')."%s<='$val'";
      $source[] = current($src);
      $i++;
    }
    $this->_function = $function;  
    reset($source);
    $this->_source = $i>1 ? $source : current($source);  
    $this->_type = !$i ? '' : ($i==1 ? 'mono' : 'bi');
    $this->_toSql = $i>0;  
  }

  protected function _getGuiElement(){
    $value = $this->getValue();
    $this->_labelFromTs = true;
    $twice = new GuiElement(array('template'=>'{begin} {end}'));
    $twice->insert($this->_generateGuiInput('begin',null,'formItem formItemIntervalBegin'), 'begin');
    $twice->insert($this->_generateGuiInput('end', $this->getSecondLabel(),'formItem formItemIntervalEnd'), 'end');
    return $twice;
  }
}

class GridColumnFilter_intervalDate extends GridColumnFilter_interval{

  protected function _initSqlFilter() {
    $app = Application::get();
    $value = $this->getValue();
    $src = $this->getSource();
    if (is_array($src) && (count($src)<=1)){
      reset($src);
      $src = current($src);
    }
    if (!is_array($src)){
      $src = array($src, $src);
    }
    $i = 0;
    reset($src);
    $function = '';
    $source = array();
    if ($this->isSetValue('begin')&&($app->regionalSettings->checkHumanDate($value['begin']))){
      $val = $app->regionalSettings->convertHumanToDate($value['begin']);
      $function .= "%s>='$val'";
      $source[] = current($src);
      $i++;
    }
    next($src);
    if ($this->isSetValue('end')&&($app->regionalSettings->checkHumanDate($value['end']))){
      $val = $app->regionalSettings->convertHumanToDate($value['end']);
      $function .= ($function ? ' AND ' : ''). "%s<='$val'";
      $source[] = current($src);
      $i++;
    }
    $this->_function = $function;  
    reset($source);
    $this->_source = $i>1 ? $source : current($source);  
    $this->_type = !$i ? '' : ($i==1 ? 'mono' : 'bi');  
    $this->_toSql = $i>0;
  }

}

class GridColumnFilter_intervalDateWithNull extends GridColumnFilter_interval{

  protected function _initSqlFilter() {
    $app = Application::get();
    $value = $this->getValue();
    $src = $this->getSource();
    if (is_array($src) && (count($src)<=1)){
      reset($src);
      $src = current($src);
    }
    if (!is_array($src)){
      $src = array($src, $src);
    }
    $i = 0;
    reset($src);
    $function = '';
    $source = array();
    if ($this->isSetValue('begin')&&($app->regionalSettings->checkHumanDate($value['begin']))){
      $val = $app->regionalSettings->convertHumanToDate($value['begin']);
      $function .= "ifnull(%s,'9999-99-99')>='$val'";
      $source[] = current($src);
      $i++;
    }
    next($src);
    if ($this->isSetValue('end')&&($app->regionalSettings->checkHumanDate($value['end']))){
      $val = $app->regionalSettings->convertHumanToDate($value['end']);
      $function .= ($function ? ' AND ' : ''). "ifnull(%s,'0000-00-00')<='$val'";
      $source[] = current($src);
      $i++;
    }
    $this->_function = $function;  
    reset($source);
    $this->_source = $i>1 ? $source : current($source);  
    $this->_type = !$i ? '' : ($i==1 ? 'mono' : 'bi');  
    $this->_toSql = $i>0;
  }
}

class GridColumnFilter_selectMultiple extends GridColumnFilter_select{

  protected function _generateGuiSelect(){
    $value = $this->getValue();
    $gui = new GuiFormSelect(array_merge($this->getFilterParams(), array(
          'dataSource' => $this->getDataSource(),
          'id' => $this->getColumnId(),
          'label' => $this->getLabel(),
          'labelHtmlize' => $this->getLabelHtml(),
          'useTextStorage' => $this->getUseTextStorage(),
          'name' => 'filter['. $this->getColumnId() .'][]',
          'value' => $value,
          'multiple' => true)));
    return $gui;
  }
}

?>
