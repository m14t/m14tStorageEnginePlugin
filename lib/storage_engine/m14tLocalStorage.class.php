<?php

/*
 * A m14tStorageEngine implementation for storing files to a local
 * filesystem.
 *
 */
class m14tLocalStorage implements m14tStorageEngineTemplate {

  protected
    $fp = false,
    $filename = null,
    $options = array();


  /*
   * Create a new Local Storage object
   *
   * Required Options:
   *
   *   base_path:  (string) a directory to store all files.
   *               It must end with a closing slash.
   *
   * Other Options:
   *   create_missing_directories:  (boolean) automatically create no-existant
   *               directories when attempting to save a file to a path
   *               that does not yet exist.
   *
   *   mkdir_mode: (integer) hexidecimal mode to use by default when creating
   *               directories.
   *
   */
  public function __construct($options = array()) {
    $default_options = array(
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


  /*
   * @see: file_exists_locally()
   *
   */
  public function file_exists($filename) {
    return $this->file_exists_locally($filename);
  }


  /*
   * Wrapper around php's file_exists() function
   *
   */
  protected function file_exists_locally($filename) {
    return file_exists($this->getOption('base_path') . $filename);
  }


  /*
   * Wrapper for fopen and gzopen
   *
   */
  protected function open($function, $filename, $mode, $use_include_path) {
    $this->preOpen($filename, $mode);
    $full_filename = $this->getFullFilename($filename);
    $this->fp = $function($full_filename, $mode, $use_include_path);
    if (!$this->fp) {
      throw new Exception("Cannot open file ($full_filename)");
    }

    return $this->fp;
  }


  /**
   * @returns array()
   */
  public function fgetcsv($length = 0, $delimiter = ',', $enclosure = '"', $escape = '\\') {
    return fgetcsv($this->fp, $length, $delimiter, $enclosure, $escape);
  }


  public function fgets($length = 0) {
    return fgets($this->fp, $length);
  }


  public function fopen($filename, $mode, $use_include_path = false) {
    return $this->open('fopen', $filename, $mode, $use_include_path);
  }


  public function ftell() {
    return ftell($this->fp);
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


  /*
   * @see: is_dir_locally()
   *
   */
  public function is_dir($filename) {
    return $this->is_dir_locally($filename);
  }


  /*
   * Wrapper around php's is_dir() function
   *
   */
  protected function is_dir_locally($filename) {
    return is_dir(
      $this->getOption('base_path') . $filename
    );
  }


  /*
   * @see: is_writable_locally()
   *
   */
  public function is_writable($filename) {
    return $this->is_writable_locally($filename);
  }


  /*
   * Wrapper around php's is_writable() function
   *
   */
  protected function is_writable_locally($filename) {
    return is_writable($this->getOption('base_path') . $filename);
  }


  /*
   * Wrapper around php's mkdir() function
   *
   */
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


  protected function handleDirDoesNotExistLocally($dir) {
    if ( $this->getOption('create_missing_directories') ) {
      if ( !$this->mkdir($dir, null, true) ) {
        throw new Exception("Unable to create directory '$dir'.");
      }
    } else {
      throw new Exception("The directory '$dir' does not exist.");
    }
  }


  /*
   * @todo:  handle cases of mode = a+
   * @todo:  better handle cases of modes w+ and a+
   *
   */
  protected function preOpen($filename, $mode, $set_filename = true) {

    if ( false !== $this->fp ) {
      throw new Exception('File pointer already open.');
    }

    $full_filename = $this->getFullFilename($filename);
    $dir = dirname($filename);

    //-- Make sure that the nessisary files and/or directories exist
    if ( false !== stripos($mode, 'w') ) {
      //-- opening in write mode
      if ( $this->file_exists_locally($filename) && !$this->is_writable_locally($filename) ) {
        //-- If the file exsits, we need to be able to write to it
        throw new Exception("The file $full_filename is not writable locally.");
      } elseif ( !$this->is_dir_locally($dir) ) {
        //-- Otherwise we need the directory to exist (and be writeable)
        $this->handleDirDoesNotExistLocally($dir);
      }
    } else {
      //-- opening in read mode
      if ( !$this->file_exists_locally($filename) ) {
        //-- the file must exist
        throw new Exception("The file $full_filename does not exist locally.");
      }
    }

    if ( true === $set_filename ) {
      $this->filename = $filename;
    }
  }


  public function rename($oldname, $newname) {

    $dir = dirname($newname);
    if ( !$this->is_dir($dir) ) {
      $this->handleDirDoesNotExistLocally($dir);
    }

    return rename(
      $this->getOption('base_path') . $oldname,
      $this->getOption('base_path') . $newname
    );
  }
  

  public function rewind() {
    return rewind($this->fp);
  }


  public function unlink($filename) {
    if ( ! $this->file_exists($filename) ) {
      //-- file is already removed
      throw new Exception("The file '$filename' does not exist.");
    }
    return unlink($this->getOption('base_path') . $filename);
  }


}
