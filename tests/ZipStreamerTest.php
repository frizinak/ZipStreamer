<?php

namespace ZipStreamer;

use ZipStreamer\Tests\ZipStreamerTest;

/**
 * Mocks php time() calls in the ZipStreamer namespace.
 */
function time() {
  return ZipStreamerTest::mockTime();
}

namespace ZipStreamer\Tests;

use ZipStreamer\Output\File;
use ZipStreamer\ZipStreamer;

class ZipStreamerTest extends \PHPUnit_Framework_TestCase {

  const BLOCK_SIZE = 10240;
  protected $destination;

  protected static $time = 0;
  protected static $zipArchiveErrs = array();
  protected static $fileContents = array();

  public static function mockTime() {
    return self::$time;
  }

  public static function setUpBeforeClass() {
    $reflection = new \ReflectionClass('\ZipArchive');
    $constants = $reflection->getConstants();
    foreach ($constants as $constant => $value) {
      if (substr($constant, 0, 3) == 'ER_') self::$zipArchiveErrs[$value] = $constant;
    }
  }

  public function setUp() {
    $this->destination = tempnam(sys_get_temp_dir(), ZIPSTREAMER_TMPFILE_PREFIX);
    touch($this->destination);
    if (!is_writable($this->destination)) {
      throw new \RuntimeException(sprintf('%s is not writable', $this->destination));
    }
  }

  public function fileProvider() {
    $localUris = array();
    $remoteUris = array();
    $files = glob(WEB_SERVER_DOCROOT . '/*');
    foreach ($files as $i => $file) {
      if (!is_file($file)) continue;
      $localUris[] = $file;
      isset(self::$fileContents[$i]) || self::$fileContents[] = file_get_contents($file);
      $remoteUris[] = 'http://' . WEB_SERVER_HOST . ':' . WEB_SERVER_PORT . '/' . basename($file);
    }

    $data = array();
    $data[] = array($localUris, TRUE, time());
    $data[] = array($remoteUris, TRUE, -10000);
    $data[] = array(array_map(array($this, 'fropen'), $localUris), TRUE);
    $data[] = array(array_map(array($this, 'fropen'), $remoteUris), TRUE);

    $data[] = array($localUris, FALSE);
    $data[] = array($remoteUris, FALSE);
    $data[] = array(array_map(array($this, 'fropen'), $localUris), FALSE);
    $data[] = array(array_map(array($this, 'fropen'), $remoteUris), FALSE, time());
    return $data;
  }

  protected function getFileContents($dest) {
    $contents = array();
    foreach (self::$fileContents as $i => $content) {
      $contents[$dest . '/' . $i] = $content;
    }
    return $contents;
  }

  /**
   * @dataProvider    fileProvider
   */
  public function testZipIntegrityStore(array $files, $continuous, $time = 0) {
    self::$time = $time;

    $out = new File($this->destination, TRUE);
    $zip = new ZipStreamer($out, self::BLOCK_SIZE, $continuous);
    for ($i = 0; $i < count($files); $i++) {
      $zip->add('stored/' . $i, $files[$i]);
      $zip->send();
    }
    $zip->flush();
    $this->assertZipFileIntegrity($this->destination, $this->getFileContents('stored'));
  }

  /**
   * @dataProvider    fileProvider
   */
  public function testZipIntegritySoftDeflate(array $files, $continuous, $time = 0) {
    self::$time = $time;

    $out = new File($this->destination, TRUE);
    $zip = new ZipStreamer($out, self::BLOCK_SIZE, $continuous);
    for ($i = 0; $i < count($files); $i++) {
      $zip->add('dir/dir/soft-deflate/' . $i, $files[$i], 1);
      $zip->send();
    }
    $zip->flush();
    $this->assertZipFileIntegrity($this->destination, $this->getFileContents('dir/dir/soft-deflate'));
  }

  /**
   * @dataProvider    fileProvider
   */
  public function testZipIntegrityHardDeflate(array $files, $continuous, $time = 0) {
    self::$time = $time;

    $out = new File($this->destination, TRUE);
    $zip = new ZipStreamer($out, self::BLOCK_SIZE, $continuous);
    for ($i = 0; $i < count($files); $i++) {
      $zip->add('hard-deflate/' . $i, $files[$i], 9);
    }
    $zip->flush();
    $this->assertZipFileIntegrity($this->destination, $this->getFileContents('hard-deflate'));
  }

  /**
   * @group exceptions
   */
  public function testInvalidFiles() {
    $out = new File($this->destination, TRUE);
    $zip = new ZipStreamer($out, self::BLOCK_SIZE);
    $e = NULL;
    try {
      $zip->add('invalid/file', '/fake/should-not-exist');
    } catch (\Exception $e) {
      $this->assertRegExp('/file .*? is not readable/', $e->getMessage());
    }
    $this->assertInstanceOf('\RunTimeException', $e);

    $e = NULL;
    try {
      $zip->add('', '');
    } catch (\Exception $e) {
      $this->assertEquals('File destination should be a non-empty string', $e->getMessage());
    }
    $this->assertInstanceOf('\InvalidArgumentException', $e);

    $e = NULL;
    try {
      $zip->add('invalid/file', '');
    } catch (\Exception $e) {
      $this->assertEquals('path should be a non-empty string', $e->getMessage());
    }
    $this->assertInstanceOf('\InvalidArgumentException', $e);

    $e = NULL;
    try {
      $zip->add('invalid/file', array('abc'));
    } catch (\Exception $e) {
      $this->assertEquals('path should be a non-empty string', $e->getMessage());
    }
    $this->assertInstanceOf('\InvalidArgumentException', $e);

    $e = NULL;
    $file = tempnam(sys_get_temp_dir(), ZIPSTREAMER_TMPFILE_PREFIX);
    touch($file);
    try {
      $zip->add('invalid/file2', fopen($file, 'w'));
    } catch (\Exception $e) {
      $this->assertEquals('Resource should be readable', $e->getMessage());
    }
    $this->assertInstanceOf('\InvalidArgumentException', $e);
  }

  protected function assertZipFileIntegrity($zipFile, array $fileContents) {
    $phpZip = new \ZipArchive();
    $err = $phpZip->open($zipFile, \ZipArchive::CHECKCONS);
    $this->assertTrue($err, sprintf('Integrity check error: %s', isset(self::$zipArchiveErrs[$err]) ? self::$zipArchiveErrs[$err] : 'Unknown'));
    foreach ($fileContents as $dest => $content) {
      $this->assertEquals($content, $phpZip->getFromName($dest), $dest);
    }
  }

  protected function fropen($file) {
    return fopen($file, 'rb');
  }
}
