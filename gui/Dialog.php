<?php

class GuiDialog extends GuiElement {

  protected function _userRender() {
    $dialog = $this->_app->dialog->get();

    if ($dialog) {
      $this->setTemplateString('<div id="confirmDialog">'.$dialog['template'].'</div>');
      $this->_app->document->addJavascript(sprintf('
            $(function() {            
              $("#confirmDialog").dialog({
                  %s%s
                  modal: true,
                  appendTo: "#%s",
                  close: function() { $(this).parent().appendTo("#%s"); }
              });
            });',
            isset($dialog['width'])?'width: '.$dialog['width'].',':'',
            isset($dialog['height'])?'height: '.$dialog['height'].',':'',
            $dialog['form'], $dialog['body']));
    }
  }
}

?>
