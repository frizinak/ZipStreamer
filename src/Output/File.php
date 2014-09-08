<?php

namespace ZipStreamer\Output;

class File implements OutputInterface {

  protected $destination;
  protected $overwrite;
  protected $fh;

  public function __construct($destination, $overwrite = FALSE) {
    $this->destination = $destination;
    $this->overwrite = $overwrite;
  }

  public function output($string) {
    if (!$this->fh) {
      if (!$this->overwrite && file_exists($this->destination)) {
        throw new \RuntimeException(sprintf('%s exists', $this->destination));
      }
      if (!($this->fh = fopen($this->destination, 'w'))) {
        throw new \RuntimeException(sprintf('Could not fopen %s', $this->destination));
      }
    }
    fwrite($this->fh, $string);
  }

  public function flush() {
    fclose($this->fh);
  }
}
