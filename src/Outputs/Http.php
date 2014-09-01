<?php

namespace ZipStreamer\Outputs;

class Http extends Stdout implements OutputInterface {

  protected $headersSent = FALSE;

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

//      header("Content-Type: application/zip");
      header("Content-Disposition: attachment; filename=zip.zip");
    }
    parent::output($string);
  }

}
