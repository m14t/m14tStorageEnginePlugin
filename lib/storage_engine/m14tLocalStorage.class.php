<?php

class m14tLocalStorage implements m14tStorageEngineTemplate {

  protected
    $fp = false,
    $filename = null,
    $options = array();


  /*
   * Create a new Local Storage object
   *
   * Options:
   *   base_path:  (string) a directory to store all files.
   *
   */
  public function __construct($options = array()) {
    $default_options = array(
      'base_path' => sfConfig::get('sf_upload_dir').'/',
      'create_missing_directories' => true,
      'mkdir_mode' => 0777,
    );
    $this->options = array_merge($default_options, $options);
  }


  /*
   * Wrapper for fclose and gzclose
   *
   */
  protected function close($function) {
    $ret = true;
    if ( false !== $this->fp ) {
      $ret = $function($this->fp);
      if ( true === $ret ) {
        $this->fp = false;
        $this->filename = null;
      }
    }
    return $ret;
  }


  public function fclose() {
    return $this->close('fclose');
  }


  public function file_exists($filename) {
    return file_exists($this->getOption('base_path') . $filename);
  }


  /*
   * Wrapper for fopen and gzopen
   *
   */
  protected function open($function, $filename, $mode, $use_include_path) {
    $this->preOpen($filename, $mode);
    $this->fp = $function($this->getFullFilename($filename), $mode, $use_include_path);
    if (!$this->fp) {
      throw new Exception("Cannot open file ($filename)");
    }

    return $this->fp;
  }


  public function fopen($filename, $mode, $use_include_path = false) {
    return $this->open('fopen', $filename, $mode, $use_include_path);
  }


  public function fwrite($string) {
    if ( false === $this->fp ) {
      throw new Exception("No open file pointer.");
    }
    $len = strlen($string);
    for ($written = 0; $written < $len; $written += $fwrite) {
      $fwrite = fwrite($this->fp, substr($string, $written));
      if ( false === $fwrite ) {
        throw new Exception("Cannot write to file ($this->filename)");
      } elseif ( 0 === $fwrite ) {
        throw new Exception("Zero bytes writen to file ($this->filename)");
      }
    }
    return $written;
  }


  protected function getOption($option_key) {
    if ( array_key_exists($option_key, $this->options) ) {
      return $this->options[$option_key];
    }
    throw new Exception("No such option: $option_key");
  }


  protected function getFullFilename($filename = null) {
    if ( null === $filename ) {
      $filename = $this->filename;
    }
    return $this->getOption('base_path') . $filename;
  }


  public function gzclose() {
    return $this->close('gzclose');
  }


  public function gzeof() {
    return gzeof($this->fp);
  }


  public function gzgets($length) {
    return gzgets($this->fp, $length);
  }


  public function gzopen($filename, $mode , $use_include_path = 0) {
    return $this->open('gzopen', $filename, $mode, $use_include_path);
  }


  public function is_dir($filename) {
    return is_dir(
      $this->getOption('base_path') . $filename
    );
  }


  public function is_writable($filename) {
    return is_writable($this->getOption('base_path') . $filename);
  }


  public function mkdir($pathname, $mode = null, $recursive = false) {
    if ( null === $mode ) {
      $mode = $this->getOption('mkdir_mode');
    }
    return mkdir(
      $this->getOption('base_path') . $pathname,
      $mode,
      $recursive
    );
  }


  protected function handleDirDoesNotExist($dir) {
    if ( $this->getOption('create_missing_directories') ) {
      if ( !$this->mkdir($dir, null, true) ) {
        throw new Exception("Unable to create directory '$dir'.");
      }
    } else {
      throw new Exception("The directory '$dir' does not exist.");
    }
  }


  protected function preOpen($filename, $mode, $set_filename = true) {

    if ( false !== $this->fp ) {
      throw new Exception('File pointer already open.');
    }

    $full_filename = $this->getFullFilename($filename);
    $dir = dirname($filename);

    //-- Make sure that the nessisary files and/or directories exist
    if ( false !== stripos($mode, 'w') ) {
      //-- opening in write mode
      if ( $this->file_exists($filename) && !$this->is_writable($filename) ) {
        //-- If the file exsits, we need to be able to write to it
        throw new Exception("The file $full_filename is not writable.");
      } elseif ( !$this->is_dir($dir) ) {
        //-- Otherwise we need the directory to exist (and be writeable)
        $this->handleDirDoesNotExist($dir);
      }
    } else {
      //-- opening in read mode
      if ( !$this->file_exists($filename) ) {
        //-- the file must exist
        throw new Exception("The file $full_filename does not exist.");
      }
    }

    if ( true === $set_filename ) {
      $this->filename = $filename;
    }
  }


  public function rename($oldname, $newname) {

    $dir = dirname($newname);
    if ( !$this->is_dir($dir) ) {
      $this->handleDirDoesNotExist($dir);
    }

    return rename(
      $this->getOption('base_path') . $oldname,
      $this->getOption('base_path') . $newname
    );
  }


  public function unlink($filename) {
    if ( ! $this->file_exists($filename) ) {
      //-- file is already removed
      throw new Exception("The file '$full_filename' does not exist.");
    }
    return unlink($this->getOption('base_path') . $filename);
  }


}
