<?php
namespace ZipStreamer\Outputs;

interface OutputInterface {
  public function output($string);
  public function flush();
}
