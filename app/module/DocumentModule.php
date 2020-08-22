<?php

class DocumentModule extends ViewModule {
  private $_xmlProlog = true;
  private $_doctype = 'strict';
  private $_doctypes = array(
      'x1.1' => array(
        'name' => 'DTD XHTML 1.1',
        'dtd' => 'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd'),
      'strict' => array(
        'name' => 'DTD XHTML 1.0 Strict',
        'dtd' => 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'),
      'transitional' => array(
        'name' => 'DTD XHTML 1.0 Transitional',
        'dtd' => 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'),
      'frameset' => array(
        'name' => 'DTD XHTML 1.0 Frameset',
        'dtd' => 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd'));
  private $_title = 'Document';
  private $_onload = '';
  private $_onResize = '';
  private $_css = array();
  private $_cssFile = array();
  private $_bodyClass = '';
  private $_javascript = array();
  private $_link = array();
  private $_meta = array();
  private $_base;
  private $_lang;
  private $_documentGui;
  private $_projectGui;
  private $_userGui;
  private $_actualGui;
  private $_abortRender = false;
  private $_convert = array('cz' => 'cs');
  protected $_javascriptFile = array();
  
  protected function _userRun() {
    $this->_app->document =& $this;
    $this->_documentGui = new GuiElement;
    $this->_projectGui = new GuiElement;
    $this->_userGui = new GuiElement;
    
    $this->_documentGui->insert($this->_projectGui);
    $this->_projectGui->insert($this->_userGui);

    $this->_loadConfig();

    $this->_actualGui =& $this->_userGui;
    $this->_userInsert();
    $this->_userGui->render();
    
    $this->_actualGui =& $this->_projectGui;
    $this->_projectInsert();
    $this->_projectGui->render();
      
    $this->_documentGui->setTemplateString($this->_getTemplate());

    $t = $this->_documentGui->render();
    
    if ($this->_abortRender) {
      $t = '';
    }
    
    if ($this->_browserRender) {
      header('Content-Type: text/html; charset='. $this->getCharset());
    }

    if (Application::get()->getModRewrite()) $t = $this->_modRewriteToTemplate($t);

    return $t;
  }

  protected function _modRewriteToTemplate($template) {
    $replacement = Application::get()->getModRewriteReplacement();

    foreach ($replacement as $r) {
      $pattern = sprintf('/%s\"/i', $r['pattern']);
      $replacement = sprintf('%s"', $r['replacement']);
      $template = preg_replace($pattern, $replacement, $template);
    }

    return $template;
  }

  protected function _getTemplate() {   
    $template = 
      $this->_insertDoctype() .
      '<html' . $this->_insertHtmlParams() . ">\n<head>\n" .
      $this->_insertMeta() . "\n" .
      $this->_insertBase() . "\n" .
      $this->_insertTitle() . "\n" .
      $this->_insertLink() . "\n" .
      $this->_insertJavascript() . "\n" .
      $this->_insertCss() . "\n" .
      "</head>\n<body id=\"body\"" . $this->_insertBodyClass() . $this->_insertOnload() . $this->_insertOnResize() . ">\n".
      '{children}' .
      $this->_insertMessages() .
      "\n</body>\n</html>\n";
      
    return $template;
  }

  protected function _loadConfig() {
    $lang = $this->_app->language->getLanguage();
    $this->_lang = isset($this->_convert[$lang]) ? $this->_convert[$lang] : $lang;
    
    $this->_addStartMeta();
  }
  
  protected function _addStartMeta() {    
    $this->addMeta( array(
      'http-equiv' => 'content-type',
      'content' => 'text/html; charset='. $this->getCharset() ));
  }

  public function getProjectGui() { return $this->_projectGui; }

  public function setAbortRender($abortRender) { $this->_abortRender = $abortRender; }

  protected function _projectInsert() { }
  protected function _userInsert() { }

  public function setBodyClass($cl=''){ $this->_bodyClass = $cl; }
  public function addBodyClass($cl=''){ $this->_bodyClass .= ($this->_bodyClass ? ' ' : '') . $cl; }
  protected function _insertBodyClass(){
    if (empty($this->_bodyClass))return '';    
    else return ' class="'.$this->_bodyClass.'"';
  }

  public function insert($gui, $key = 'children', $replace = false ) { $this->_actualGui->insert($gui, $key, $replace); }
  public function insertTemplateVar($key, $value, $htmlize=true) { $this->_actualGui->insertTemplateVar($key, $value, $htmlize); }

  public function setTemplateString($template) { $this->_actualGui->setTemplateString($template); }

  public function setTemplateFile($template,$replace = false) { $this->_actualGui->setTemplateFile($template,$replace); }

  public function setCharset($charset) { $this->_app->setCharset($charset); }
  public function getCharset(){ return $this->_app->getCharset(); }

  public function setXmlProlog($xmlProlog) { $this->_xmlProlog = $xmlProlog; }

  public function setDoctype($doctype) {
    if (isset($this->_doctypes[$doctype])) {
      $this->_doctype = $doctype;
    }
  }

  protected function _insertDoctype() {
    $doctype = '';
    if ($this->_xmlProlog) { $doctype .= sprintf('<?xml version="1.0" encoding="%s" ?>%s', $this->getCharset(), "\n"); }
    $doctype .= sprintf('<!DOCTYPE html PUBLIC "-//W3C//%s//EN" "%s">%s', $this->_doctypes[$this->_doctype]['name'], $this->_doctypes[$this->_doctype]['dtd'], "\n");
    return $doctype;
  }

  protected function _insertHtmlParams() {
    $lang = $this->_lang;
    $htmlParams = ' xmlns="http://www.w3.org/1999/xhtml"'.
      ' xml:lang="'. $lang .'"'.
      (($this->_doctype == 'x1.1') ? '' : ' lang="'. $lang .'"');
    return $htmlParams;
  }

  public function setTitle($title) { $this->_title = $title; }
  public function getTitle() { return $this->_title; }
  protected function _insertTitle() { return '<title>'.  $this->_title ."</title>\n"; }
  
  public function addCss($css) { $this->_css[] = $css; }
  protected function _insertCss() {
    $css = '';
    foreach($this->_css as $v) {
      $css .= $v ."\n";
    }
    if ($css) {
      $css = '<style type="text/css">'."\n". $css ."\n".'</style>'."\n";
    }
    return $css;
  }
  
  public function addCssFile($cssFile, $condition=null, $noDuplicity = false) { 
    $found = false;
    foreach ($this->_cssFile as $f) {
      if ($f['file'] == $cssFile) {
        $found = true;
        break;
      }
    }
    if (!$noDuplicity || !$found) {
      $this->_cssFile[] = array('file'=>$cssFile,'condition'=>$condition); 
    }
  }
  public function getCssFiles() { return $this->_cssFile; }
  public function setCssFiles($arr) { $this->_cssFile = $arr; }

  public function addJavascript($javascript, $toBegin=false) {
    if ($toBegin) array_unshift($this->_javascript, $javascript);
    else $this->_javascript[] = $javascript;
  }

  protected function _insertJavascript() {
    $javascript = '';
    foreach($this->_javascript as $v) {
      $javascript .= $v ."\n";
    }
    if ($javascript) {
      $javascript = '<script type="text/javascript">'."\n".'/* <![CDATA[ */'."\n" . 
        $javascript .
        "\n".'/* ]]> */'."\n".'</script>'."\n";
    }
    return $javascript;
  }
  
  public function addJavascriptFile($javascriptFile, $noDuplicity=false) {
    if (!$noDuplicity || (!in_array($javascriptFile, $this->_javascriptFile))) {
      $this->_javascriptFile[] = $javascriptFile;
    }
  }

  public function addJavascriptTemplateFile($javascriptFile, $replacement=array()) {
    $content = file_get_contents($javascriptFile);

    $gui = new GuiElement(array('template'=>$content));
    foreach ($replacement as $key=>$value) {
      $gui->insertTemplateVar($key,$value,false);
    }

    $this->addJavascript($gui->render());
  }
  
  public function getJavascriptFiles(){ return $this->_javascriptFile; }
  public function setJavascriptFiles($ary){ $this->_javascriptFile = $ary; }
   
  public function addJavascriptLibrary($name){ $this->addJavascript(file_get_contents(dirname(__FILE__).'/../../gui/js/Gui'.$name.'.js')); }

  public function addMeta($meta) {
    if (is_array($meta)) {
      $this->_meta[] = $meta;
    }
  }

  public function setBase($href=null, $target=null) {
    $this->_base = array();
    if (isset($href)) { $this->_base['href'] = $href; }
    if (isset($target)) { $this->_base['target'] = $target; }
  }
  
  public function addLink($link) {
    if (is_array($link)) {
      $this->_link[] = $link;
    }
  }

  protected function _insertMeta() {
    $meta = '';
    foreach ($this->_meta as $v_ar) {
      $params='';
      foreach ($v_ar as $k => $v) {
        $params .= ' '. $k .'="'. $v .'"';
      }
      $meta .= '<meta'. $params .' />'."\n";
    }
    return $meta;
  }

  protected function _insertBase() {
    $base = '';
    if (is_array($this->_base)) {
      $params = '';
      foreach ($this->_base as $k => $v) {
        $params .= ' '. Application::get()->htmlspecialchars($k) .'="'. Application::get()->htmlspecialchars($v) .'"';
      }
      $base = '<base'. $params .' />'."\n";
    }
    return $base;
  }

  protected function _insertLink() {
    $link = '';
    foreach ($this->_cssFile as $v) {
      $f = '<link rel="stylesheet" type="text/css" href="'. $v['file'] .'" />'."\n";
      if ($v['condition']) {
        $link .= sprintf("<!--[if %s]>\n%s<![endif]-->\n", $v['condition'], $f);
      } else {
        $link .= $f;
      }
    }
    foreach ($this->_javascriptFile as $v) {
      $link .= '<script type="text/javascript" src="'. $v .'"></script>'."\n";
    }
    foreach ($this->_link as $v_ar) {
      $params='';
      foreach ($v_ar as $k => $v) {
        $params .= ' '. $k .'="'. $v .'"';
      }
      $link .= '<link'. $params .' />'."\n";
    }
    return $link;
  }

  public function addOnload($onload) { $this->_onload .= $onload; }
  
  protected function _insertOnload() {
    $value = ($this->_onload != '') ? ' onload="'. $this->_onload .'"' : '';
    return $value;
  }

  public function addOnResize($onResize) { $this->_onResize .= $onResize; }

  protected function _insertOnResize() {
    $value = ($this->_onResize != '') ? ' onresize="'. $this->_onResize .'"' : '';
    return $value;
  }

  protected function _insertMessages() {
    $ret = '';
    if ($this->_app->getDebug()) {
      //$this->_app->timer->report();
      
      foreach ($this->_app->messages->getMessages() as $one) {
        $ret .= sprintf('%s:%d> %s<br />%s', Application::get()->htmlspecialchars($one['type']), $one['level'], Application::get()->htmlspecialchars($one['message']), "\n");
      }
    }
    if ($ret) { $ret = "\n<div class=\"debug\">\n$ret\n</div>\n"; }
    return $ret;
  }
}

?>
