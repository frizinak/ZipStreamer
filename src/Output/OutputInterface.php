<?php
namespace ZipStreamer\Output;

interface OutputInterface {
  public function output($string);
  public function flush();
}
