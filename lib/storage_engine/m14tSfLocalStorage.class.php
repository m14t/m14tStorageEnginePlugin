<?php

/*
 * A Symfony 1.4 depenent version of m14tLocalStorag
 *
 */
class m14tSfLocalStorage extends m14tLocalStorage {

  public function __construct($options = array()) {
    $default_options = array(
      'base_path' => sfConfig::get('sf_upload_dir').DIRECTORY_SEPARATOR,
    );
    $this->options = array_merge($default_options, $options);
  }

}
