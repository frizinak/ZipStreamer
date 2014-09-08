<?php
namespace ZipStreamer\Output;

class Stdout implements OutputInterface {

  public function output($string) {
    print $string;
  }

  public function flush() {

  }
}
