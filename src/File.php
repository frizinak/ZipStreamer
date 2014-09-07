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

  /**
   * @var bool
   */
  protected $remote = FALSE;

  /**
   * @var resource
   */
  protected $fh;

  public function __construct($pathOrResource, $dest, $deflationLevel = 0, $smooth = TRUE) {
    $this->setSmooth($smooth);

    if (empty($dest) || !is_string($dest)) {
      throw new \InvalidArgumentException('File destination should be a non-empty string');
    }
    $this->setDest($dest);

    if (is_resource($pathOrResource)) {
      $meta = stream_get_meta_data($pathOrResource);
      if (!isset($meta['mode']) || (!strstr($meta['mode'], 'r') && !strstr($meta['mode'], '+'))) {
        throw new \InvalidArgumentException('Resource should be readable');
      }
      $this->fh = $pathOrResource;
      $this->setSmooth(TRUE);
    }
    else {
      if (!is_string($pathOrResource) || ($pathOrResource = trim($pathOrResource)) == '') {
        throw new \InvalidArgumentException('path should be a non-empty string');
      }
      $this->setPath($pathOrResource);
    }

    $this->setDeflationLevel($deflationLevel);
  }

  public function genLocal($zipVersion, $dosStamp) {
    $this->prepareLocal();

    $crc = $deflatedSize = $size = 0x0000;
    $generalPurpose = $compressionMethod = 0x00;

    if ($this->isSmooth()) {
      $generalPurpose = 0x08;
    }
    else {
      $crc = $this->getCrc();
      $deflatedSize = $this->getDeflatedSize();
      $size = $this->getSize();
    }
    if ($this->hasDeflation()) {
      $compressionMethod = 0x08;
    }

    $this->local = pack(
      'VvvvVVVVvv',
      0x04034b50,             // Signature
      $zipVersion,            // Zip extract version
      $generalPurpose,        // General purpose bit flag
      $compressionMethod,     // Compression method
      $dosStamp,              // Modifications time + data
      $crc,                   // CRC32
      $deflatedSize,          // Compressed size
      $size,                  // Size
      $this->getDestLength(), // File name/path length (dest)
      0                       // Extra field length
    );

    $this->local .= $this->dest;
    return $this->local;
  }

  public function genCentral($zipVersion, $dosStamp) {
    $generalPurpose = 0x00;
    $compressionMethod = 0x00;
    if ($this->isSmooth()) {
      $generalPurpose = 0x08;
    }

    if ($this->hasDeflation()) {
      $compressionMethod = 0x08;
    }
    $this->central = pack(
      'VvvvvVVVVvvvvvVV',
      0x02014b50,               // Signature
      $zipVersion,              // Zip create version
      $zipVersion,              // Zip extract version
      $generalPurpose,          // General purpose bit flag
      $compressionMethod,       // Compression method
      $dosStamp,                // Modifications time + data
      $this->getCrc(),          // CRC32
      $this->getDeflatedSize(), // Compressed size
      $this->getSize(),         // Size
      $this->getDestLength(),   // File name/path length (dest)
      0,                        // Extra field length
      0,                        // Comment length
      0,                        // Disk number where file starts
      0,                        // Internal File attrs
      32,                       // External File attrs
      $this->getLocalOffset()   // Offset of this file
    );

    $this->central .= $this->dest;
    return $this->central;
  }

  public function genDataDescriptor() {
    return pack(
      'VVVV',
      0x08074b50,
      $this->getCrc(),
      $this->getDeflatedSize(),
      $this->getSize()
    );
  }

  public function getFilehandle() {
    if (!isset($this->fh)) {
      $this->fh = fopen($this->path, 'rb');
    }
    return $this->fh;
  }

  /**
   * Prepare the data required for the file header.
   * size, deflatedSize, crc32, ...
   */
  protected function prepareLocal() {
    // isSmooth: local header will have its general purpose bit set
    // => empty values in local header
    // => append data descriptor
    if ($this->isSmooth()) return;

    $size = filesize($this->path);
    $this->setSize($size);
    if (!$this->hasDeflation()) $this->setDeflatedSize($size);
    $this->setRawCrc(hash_file('crc32b', $this->path, TRUE));
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
    $this->setRemote(preg_match('/^https?:\/\//', $path));
    if (!$this->isRemote() && !is_readable($path)) {
      throw new \RuntimeException(sprintf('file %s is not readable', $path));
    }
    $this->path = trim($path);
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

  /**
   * @return boolean
   */
  public function isRemote() {
    return $this->remote;
  }

  /**
   * @param boolean $remote
   */
  public function setRemote($remote) {
    $this->remote = $remote;
    if ($remote) $this->setSmooth(TRUE);
  }

}
