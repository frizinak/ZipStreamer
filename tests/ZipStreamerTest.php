<?php
namespace ZipStreamer\Tests;

use Symfony\Component\Yaml\Exception\RuntimeException;
use ZipStreamer\Outputs\File;
use ZipStreamer\ZipStreamer;

class ZipStreamerTest extends \PHPUnit_Framework_TestCase {

  const BLOCK_SIZE = 102400;
  protected $destination;
  protected $output;
  protected $zipArchiveErrs = array();

  public function setUp() {
    $this->destination = tempnam(sys_get_temp_dir(), 'zip-steamer');
    touch($this->destination);
    if (!is_writable($this->destination)) {
      throw new \RuntimeException(sprintf('%s is not writable', $this->destination));
    }

    $reflection = new \ReflectionClass('\ZipArchive');
    $constants = $reflection->getConstants();
    foreach ($constants as $constant => $value) {
      if (substr($constant, 0, 3) == 'ER_') $this->zipArchiveErrs[$value] = $constant;
    }
  }

  public function fileProvider() {
    $remoteUris = array('http://lorempixel.com/100/100/', 'http://lorempixel.com/50/50/', 'http://www.randomtext.me/download/txt/gibberish/p-14/20-2000');

    $data = array();
    $data[] = array($remoteUris, TRUE);
    $data[] = array(array_map(array($this, 'fropen'), $remoteUris), TRUE);
    $data[] = array($remoteUris, FALSE);
    $data[] = array(array_map(array($this, 'fropen'), $remoteUris), FALSE);

    $localUris = array_map(array($this, 'fetch'), $remoteUris);
    $data[] = array($localUris, TRUE);
    $data[] = array($localUris, FALSE);

    return $data;
  }

  /**
   * @dataProvider    fileProvider
   */
  public function testZipIntegrityStore(array $files, $continuous) {
    $out = new File($this->destination, TRUE);
    $zip = new ZipStreamer($out, self::BLOCK_SIZE, $continuous);
    for ($i = 0; $i < count($files); $i++) {
      $zip->add('stored/' . $i, $files[$i]);
      $zip->send();
    }
    $zip->flush();
    $this->assertZipFileIntegrity($this->destination);
  }

  /**
   * @dataProvider    fileProvider
   */
  public function testZipIntegritySoftDeflate(array $files, $continuous) {
    $out = new File($this->destination, TRUE);
    $zip = new ZipStreamer($out, self::BLOCK_SIZE, $continuous);
    for ($i = 0; $i < count($files); $i++) {
      $zip->add('soft-deflate/' . $i, $files[$i], 1);
      $zip->send();
    }
    $zip->flush();
    $this->assertZipFileIntegrity($this->destination);
  }

  /**
   * @dataProvider    fileProvider
   */
  public function testZipIntegrityHardDeflate(array $files, $continuous) {
    $out = new File($this->destination, TRUE);
    $zip = new ZipStreamer($out, self::BLOCK_SIZE, $continuous);
    for ($i = 0; $i < count($files); $i++) {
      $zip->add('hard-deflate/' . $i, $files[$i], 9);
      $zip->send();
    }
    $zip->flush();
    $this->assertZipFileIntegrity($this->destination);
  }

  private function assertZipFileIntegrity($zipFile) {
    $phpZip = new \ZipArchive();
    $err = $phpZip->open($this->destination, \ZipArchive::CHECKCONS);
    $this->assertTrue($err, sprintf('Integrity check error: %s', isset($this->zipArchiveErrs[$err]) ? $this->zipArchiveErrs[$err] : 'Unknown'));
  }

  private function fropen($file) {
    return fopen($file, 'rb');
  }

  private function fetch($uri) {
    $dest = tempnam(sys_get_temp_dir(), 'zip-steamer-asset');
    touch($dest);
    if (($data = file_get_contents($uri)) === FALSE) {
      throw new RuntimeException(sprintf('Could not fetch %s', $uri));
    }
    if (file_put_contents($dest, $data) === FALSE) {
      throw new RuntimeException(sprintf('Could not write %s to %s', $uri, $dest));
    }

    return $dest;
  }

}
