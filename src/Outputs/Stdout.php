<?php
namespace ZipStreamer\Outputs;

use ZipStreamer\Outputs\OutputInterface;

class Stdout implements OutputInterface {

  public function output($string) {
    print $string;
  }

  public function flush() {

  }
}
