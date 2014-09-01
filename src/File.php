<?php
namespace ZipStreamer;

class File {

  /**
   * @var string
   */
  protected $dest;

  /**
   * @var string
   */
  protected $destLength;

  /**
   * @var string
   */
  protected $path;

  /**
   * @var int
   */
  protected $deflationLevel = 0;

  /**
   * @var bool
   */
  protected $smooth;

  /**
   * @var int
   */
  protected $localOffset = 0;

  /**
   * @var int
   */
  protected $size = 0;

  /**
   * @var int
   */
  protected $deflatedSize = 0;

  /**
   * @var string
   */
  protected $local;

  /**
   * @var string
   */
  protected $central;

  /**
   * @var string
   */
  protected $crc = 0;

  public function __construct($path, $dest, $deflationLevel = 0, $smooth = 1) {
    $this->setPath($path);
    $this->setDest($dest);
    $this->setSmooth($smooth);
    $this->setDeflationLevel($deflationLevel);
  }

  public function genLocal($zipVersion, $dosStamp) {
    $this->local = pack(
      'VvvvVVVVvv',
      0x04034b50,                                             // Signature
      $zipVersion,                                            // Zip extract version
      $this->isSmooth() ? 0x08 : 0x00,                        // General purpose bit flag
      $this->hasDeflation() ? 0x08 : 0x00,                    // Compression method
      $dosStamp,                                              // Modifications time + data
      $this->isSmooth() ? 0x0000 : $this->getCrc(),           // CRC32
      $this->isSmooth() ? 0x0000 : $this->getDeflatedSize(),  // Compressed size
      $this->isSmooth() ? 0x0000 : $this->getSize(),          // Size
      $this->getDestLength(),                                 // File name/path length (dest)
      0                                                       // Extra field length
    );

    $this->local .= $this->dest;
    return $this->local;
  }

  public function genCentral($zipVersion, $dosStamp) {
    $this->central = pack(
      'VvvvvVVVVvvvvvVV',
      0x02014b50,                           // Signature
      $zipVersion,                          // Zip create version
      $zipVersion,                          // Zip extract version
      $this->isSmooth() ? 0x08 : 0x00,      // General purpose bit flag
      $this->hasDeflation() ? 0x08 : 0x00,  // Compression method
      $dosStamp,                            // Modifications time + data
      $this->getCrc(),                      // CRC32
      $this->getDeflatedSize(),             // Compressed size
      $this->getSize(),                     // Size
      $this->getDestLength(),               // File name/path length (dest)
      0,                                    // Extra field length
      0,                                    // Comment length
      0,                                    // Disk number where file starts
      0,                                    // Internal File attrs
      32,                                   // External File attrs
      $this->getLocalOffset()               // Offset of this file
    );

    $this->central .= $this->dest;
    return $this->central;
  }

  /**
   * @return string
   */
  public function getCrc() {
    return $this->crc;
  }

  /**
   * @param string $crc
   */
  public function setCrc($crc) {
    $this->crc = $crc;
  }

  /**
   * @param int $rawCrc
   */
  public function setRawCrc($rawCrc) {
    $crc = unpack('N', $rawCrc);
    $this->crc = $crc[1];
  }

  /**
   * @return boolean
   */
  public function hasDeflation() {
    return $this->deflationLevel != 0;
  }

  /**
   * @return int
   */
  public function getDeflationLevel() {
    return $this->deflationLevel;
  }

  /**
   * @param boolean $level
   */
  public function setDeflationLevel($level) {
    $level = $level < -1 ? -1 : $level;
    $level = $level > 9 ? 9 : $level;
    ($level != 0) && $this->smooth = TRUE;
    $this->deflationLevel = $level;
  }

  /**
   * @return int
   */
  public function getDeflatedSize() {
    return $this->deflatedSize;
  }

  /**
   * @param int $deflatedSize
   */
  public function setDeflatedSize($deflatedSize) {
    $this->deflatedSize = $deflatedSize;
  }

  /**
   * @param int $size
   */
  public function addDeflatedSize($size) {
    $this->deflatedSize += $size;
  }

  /**
   * @return string
   */
  public function getDest() {
    return $this->dest;
  }

  /**
   * @param string $dest
   */
  public function setDest($dest) {
    $this->dest = $dest;
    $this->destLength = strlen($dest);
  }

  /**
   * @return string
   */
  public function getDestLength() {
    return $this->destLength;
  }

  /**
   * @return string
   */
  public function getLocal() {
    return $this->local;
  }

  /**
   * @return string
   */
  public function getCentral() {
    return $this->central;
  }

  /**
   * @param string $central
   */
  public function setCentral($central) {
    $this->central = $central . $this->dest;
  }

  /**
   * @return int
   */
  public function getLocalOffset() {
    return $this->localOffset;
  }

  /**
   * @param int $localOffset
   */
  public function setLocalOffset($localOffset) {
    $this->localOffset = $localOffset;
  }

  /**
   * @return string
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * @param string $path
   */
  public function setPath($path) {
    $this->path = $path;
  }

  /**
   * @return int
   */
  public function getSize() {
    return $this->size;
  }

  /**
   * @param int $size
   */
  public function setSize($size) {
    $this->size = $size;
  }

  /**
   * @return boolean
   */
  public function isSmooth() {
    return $this->smooth;
  }

  /**
   * @param boolean $smooth
   */
  public function setSmooth($smooth) {
    $this->smooth = $smooth;
  }

}
