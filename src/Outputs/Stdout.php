<?php
namespace ZipStreamer\Outputs;

class Stdout implements OutputInterface {

  public function output($string) {
    print $string;
  }

  public function flush() {

  }
}
