<?php

namespace ZipStreamer\Outputs;

use ZipStreamer\Outputs\OutputInterface;

class ThrottledHttp extends Http implements OutputInterface {

  protected $firstCall = 0;
  protected $dataSent = 0;

  protected $speed;
  protected $maxSleep;

  function __construct($filename = 'download', $bytesPerSecond = 4194304, $maxSleep = 0.5) {
    parent::__construct($filename);
    $this->speed = $bytesPerSecond;
    $this->maxSleep = $maxSleep;
  }

  public function output($string) {
    if (!$this->firstCall) $this->firstCall = time();
    $diff = time() - $this->firstCall;
    $speed = $this->dataSent / max($diff, 1);
    if ($speed > $this->speed) {
      $sleep = $speed / $this->speed;
      usleep(min($this->maxSleep, $sleep) * 1000000);
    }
    $this->dataSent += strlen($string);
    parent::output($string);
  }
}
