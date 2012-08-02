# m14tStorageEnginePlugin

A plug-able storage engine for php.


## Example:

### Class file:
````php
<?php

class MyCustomFile {

  protected
    $options = array(
      'storage_engine' => false,
      'storage_engine_class' => 'm14tAwsS3Storage',
      'storage_engine_options' => array(
        'acl' => AmazonS3::ACL_PRIVATE,
        'key' => '__YOUR_AMAZON_KEY_HERE__',
        'secret' => '__YOUR_AMAZON_SECRET_HERE__',
        'bucket' => '__YOUR_BUCKET_NAME_HERE__',
      ),
    );


  public function __construct($options = array()) {

    $this->options = array_merge_recursive($this->options, $options);

    return $this;
  }


  /*
   * Get the current storage engine.  Instantiate one if one does not currently exist
   *
   */
  protected function getStorageEngine() {
    if ( false === $this->options['storage_engine'] ) {
      $storage_engine_class = $this->options['storage_engine_class'];
      $storage_engine_options = $this->options['storage_engine_options'];
      $this->options['storage_engine'] = new $storage_engine_class($storage_engine_options);
    }
    return $this->options['storage_engine'];
  }

  public function writeNumbersToFile($filename) {
    $fp = $this->getStorageEngine()->fopen($filename, 'w');
    
    //-- You get a real file pointer
    fwrite($fp, '1');
    fwrite($fp, '23');
    
    //-- You don't need to pass the file pointer to the Storage Engine
    //   because right now it only supports one file being open at a time
    $this->getStorageEngine()->fclose();

    //-- You can append to the file too.
    $fp = $this->getStorageEngine()->fopen($filename, 'a');
    fwrite($fp, '4');
    fwrite($fp, '56');
    $this->getStorageEngine()->fclose($fp);
  }


}
````

### Implementation:
````php
<?php
$mcf = new MyCustomFile(array(
  'storage_engine_options' => array(
    'base_path' => 'test/',
  ),
));
$mcf->writeNumbersToFile('data.txt');
$mcf->writeNumbersToFile('data2.txt');
?>
````