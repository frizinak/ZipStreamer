<?php

namespace ZipStreamer\Outputs;

class ThrottledHttp extends Http implements OutputInterface {

  protected $firstCall = 0;
  protected $dataSent = 0;

  protected $speed;
  protected $maxSleep;

  function __construct($bytesPerSecond = 4194304, $maxSleep = 0.5) {
    $this->speed = $bytesPerSecond;
    $this->maxSleep = $maxSleep;
  }

  public function output($string) {
    if (!$this->firstCall) $this->firstCall = time();
    $diff = time() - $this->firstCall;
    $speed = $this->dataSent / ($diff < 1 ? 1 : $diff);
    if ($speed > $this->speed) {
      $sleep = $speed / $this->speed;
      usleep(($sleep > $this->maxSleep ? $this->maxSleep : $sleep) * 1000000);
    }
    $this->dataSent += strlen($string);
    parent::output($string);
  }
}
