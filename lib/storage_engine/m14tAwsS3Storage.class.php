<?php

class m14tAwsS3Storage extends m14tLocalStorage {

  protected
    $s3 = null;


  /*
   * Create a new Local Storage object
   *
   * Options:
   *   acl:        AmazonS3::ACL_PRIVATE, AmazonS3::ACL_PUBLIC,
   *               AmazonS3::ACL_OPEN, AmazonS3::ACL_AUTH_READ,
   *               AmazonS3::ACL_OWNER_READ,
   *               AmazonS3::ACL_OWNER_FULL_CONTROL
   *   base_path:  (string) a directory to store all files.
   *   key:        (string) the AWS S3 Access Key
   *   secret:     (string) a directory to store all files.
   *   storage:    AmazonS3::STORAGE_STANDARD, AmazonS3::STORAGE_REDUCED
   *
   */
  public function __construct($options = array()) {
    $default_options = array(
      'acl' => AmazonS3::ACL_PUBLIC,
      'storage' => AmazonS3::STORAGE_STANDARD,
      'base_path' => sys_get_temp_dir().'/'.__CLASS__.'/',
      'key' => sfConfig::get('amazon_s3_awsAccessKey', false),
      'secret' => sfConfig::get('amazon_s3_awsSecretKey', false),
      'bucket' => sfConfig::get('amazon_s3_thumb_bucket', false),
    );

    $ret = parent::__construct(array_merge($default_options, $options));

    $this->s3 = new AmazonS3(array(
      'key' => $this->getOption('key'),
      'secret' => $this->getOption('secret'),
    ));

    return $ret;
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

        //-- upload the file
        $response = $this->s3->create_object(
          $this->getOption('bucket'),
          $this->filename,
          array(
            'acl' => $this->getOption('acl'),
            'fileUpload' => $this->getFullFilename(),
            'storage' => $this->getOption('storage'),
          )
        );

        if ( !$response->isOK() ) {
          throw new Exception(sprintf(
            'Unable to upload %s::%s => (%s) %s',
            $this->getOption('bucket'),
            $this->filename,
            $response->body->Code,
            $response->body->Message
          ));
        }

        //-- Remove the local copy
        parent::unlink($this->filename);

        $this->fp = false;
        $this->filename = null;
      }
    }
    return $ret;
  }


  /*
   * Check if the file exists on S3
   *
   */
  public function file_exists($filename, $bucket = null) {
    if ( null === $bucket ) {
      $bucket = $this->getOption('bucket');
    }
    //-- Compate with true, because if the file doesn't exist, we
    //   might get a 403 error instead of a 404, which will make
    //   this function return null, even though the docs say boolean
    return (true === $this->s3->if_object_exists($bucket, $filename));
  }


  /*
   * S3 uses the whole path as a key name, and so there is no real
   * concept of directories.
   *
   */
  public function is_dir($filename) {
    return true;
  }


  /*
   * @todo fix this
   *
   */
  public function is_writable($filename) {
    return true;
  }


  /*
   * Sync the local cache with S3 and perform basic sanity checks.
   *
   */
  protected function preOpen($filename, $mode, $set_filename = true) {

    if ( false !== $this->fp ) {
      throw new Exception('File pointer already open.');
    }

    $full_filename = $this->getFullFilename($filename);
    $dir = dirname($filename);

    //-- Sync the local system with S3
    if ( parent::file_exists($filename) ) {
      //-- The file exists locally
    } else {
      if ( $this->file_exists($filename) ) {
        //-- The file exits on S3
        //   Lets fetch the file and then open it.
        if ( !parent::is_dir($dir) ) {
          //-- We need the directory to exist (and be writeable)
          parent::handleDirDoesNotExist($dir);
        }
        $this->s3->get_object(
          $this->getOption('bucket'),
          $filename,
          array(
            'fileDownload' => $full_filename
          )
        );
      }
    }

    //-- Make sure that the nessisary files and/or directories exist
    if ( false !== stripos($mode, 'w') ) {
      //-- opening in write mode
      if ( parent::file_exists($filename) && !parent::is_writable($filename) ) {
        //-- If the file exsits, we need to be able to write to it
        throw new Exception("The file $full_filename is not writable locally.");
      } elseif ( !parent::is_dir($dir) ) {
        //-- Otherwise we need the directory to exist (and be writeable)
        parent::handleDirDoesNotExist($dir);
      }
    } else {
      //-- opening in read mode
      if ( !parent::file_exists($filename) ) {
        //-- the file must exist
        throw new Exception("The file $full_filename does not exist locally.");
      }
    }

    if ( true === $set_filename ) {
      $this->filename = $filename;
    }
  }


  /*
   * Rename an S3 object.  Performs a full Copy + Delete.
   *
   */
  public function rename($oldname, $newname) {
    //-- Copy to the new location
    $response = $this->s3->copy_object(
      array( // Source
        'bucket'   => $bucket,
        'filename' => $oldname,
      ),
      array( // Destination
        'bucket'   => $bucket,
        'filename' => $newname
      )
    );

    if ( true !== $response->isOK() ) {
      throw new Exception("Unable to copy $oldname to $newname");
    } else {
      //-- Remove the old file
      $this->unlink($oldname);
    }

    return true;
  }


  /*
   * Delete an S3 object.
   *
   */
  public function unlink($filename) {

    if ( !$this->file_exists($filename) ) {
      //-- file is already removed
      throw new Exception("The file '$filename' does not exist.");
    }

    $response = $this->s3->delete_object(
      $this->getOption('bucket'),
      $filename
    );

    if ( true !== $response->isOK() ) {
      throw new Exception("Unable to remove $filename");
    }

    return true;
  }


}
