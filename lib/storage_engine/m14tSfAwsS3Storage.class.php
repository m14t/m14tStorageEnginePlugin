<?php

/*
 * This is the Symfony 1.4 depenent version of m14tAwsS3Storage
 *
 */
class m14tSfAwsS3Storage extends m14tAwsS3Storage {

  public function __construct($options = array()) {
    $default_options = array(
      'key' => sfConfig::get('app_amazon_s3_awsAccessKey', false),
      'secret' => sfConfig::get('app_amazon_s3_awsSecretKey', false),
      'bucket' => sfConfig::get('app_amazon_s3_thumb_bucket', false),
    );

    return parent::__construct(array_merge($default_options, $options));
  }


}
