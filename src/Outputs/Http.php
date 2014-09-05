<?php

namespace ZipStreamer\Outputs;

class Http extends Stdout implements OutputInterface {

  protected $headersSent = FALSE;
  protected $fn;

  public function __construct($filename = 'download') {
    $this->fn = $filename;
  }

  public function output($string) {
    if (!$this->headersSent) {
      $this->headersSent = TRUE;

      header("Pragma: public");
      header("Expires: 0");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      header("Cache-Control: public");
      header("Content-Description: File Transfer");
      header("Content-type: application/octet-stream");
      header("Content-Transfer-Encoding: binary");

      header(sprintf("Content-Disposition: attachment; filename=%s.zip", $this->fn));
    }
    parent::output($string);
  }

}
