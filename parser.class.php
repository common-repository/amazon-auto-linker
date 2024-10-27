<?php
class AAL_Parser
{
  private $_dictionary;
  private $_current_keyword;
  private $_current_url;


  function __construct($dictionary)
  {
    $this->_dictionary = $dictionary;
    $this->prepare_dictionary();
  }

  protected function prepare_dictionary()
  {
    //convert to array
    if (empty($this->_dictionary)) return false;
    $this->_dictionary = explode(PHP_EOL, $this->_dictionary);
  }

  public function parse($content)
  {
    $num_lines = count($this->_dictionary);
    $i = 0;
    while($i <= $num_lines ){
      $phrase = explode(',', $this->_dictionary[$i]);
      $this->_current_keyword = stripslashes($phrase[0]);
      $this->_current_url = $phrase[1];

      if (!empty($phrase[0]) AND !empty($phrase[1])) {
        $this->_current_keyword = preg_quote($this->_current_keyword);
        //$pattern = "~{$this->_current_keyword}(s|es|ies)*\b(?!(.(?<!\[[A-Za-z0-9_-]))*?\[/[A-Za-z0-9_-])(?!(.(?<!\<a))*?\</a)~is";
        $pattern = "~\b{$this->_current_keyword}\b(?!(.(?<!\[[A-Za-z0-9_-]))*?\[/[A-Za-z0-9_-])(?!(.(?<!\<a))*?\</a)~is";

        $content = preg_replace_callback($pattern, array($this, 'do_replace'), $content);
      }

      $i++;
    }
    return $content;
  }

  public function do_replace($matches){
    return $this->wrap_shortcode($matches[0]);
  }

  protected function wrap_shortcode($string)
  {
    return "[az url='".trim($this->_current_url)."']{$string}[/az]";
  }
}