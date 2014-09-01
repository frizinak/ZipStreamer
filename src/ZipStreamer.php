<?php
namespace ZipStreamer;

use ZipStreamer\Outputs\Http;
use ZipStreamer\Outputs\OutputInterface;

/**
 * Class ZipStream
 *
 * props to http://hg.pablotron.org/zipstream-php/
 *
 * rfc: http://www.pkware.com/documents/casestudies/APPNOTE.TXT
 *
 * zip format:
 *
 * foreach file
 * > file local header
 * > file body (optionally deflated)
 * > (optional) file data descriptor (if crc was not calculated in the local header)
 * end foreach
 *
 * foreach file
 * > file central header
 * end foreach
 * > EOF (End of central headers)
 *
 * @author Kobe Lipkens
 */
class ZipStreamer {

  const ZIP_VERSION = 20;
  protected $blockSize;
  protected $continuous;
  /**
   * @var File[]
   */
  protected $files = array();
  protected $dosStamp;

  protected $localOffset = 0;
  protected $centralLength = 0;

  protected $sent = 0;
  protected $locked = FALSE;

  /**
   * @var Http|OutputInterface
   */
  protected $outputter;

  /**
   * @param OutputInterface $output     The output interface, defaults to \ZipStreamer\Outputs\Http;
   * @param int             $blockSize  fread size. default 1 MB.
   *                                    - peak mem usage will be around 3x that,
   *                                    - unless the outputter buffers the data it receives.
   * @param bool            $continuous Only relevant for files that are to be stored (i.e not deflated).
   *                                    - TRUE: Skip local header crc & sizes but append them to the file (Data descriptor)
   *                                    -       Results in a smoother stream and less cpu usage, overall slightly slower (I think).
   *                                    - FALSE: Calculate crc before piping the file body.
   *                                    -        Adds 1 read of the file before any output has started.
   *
   */
  public function __construct(OutputInterface $output = NULL, $blockSize = 1048576, $continuous = TRUE) {
    $this->blockSize = $blockSize;
    $this->continuous = $continuous;
    $this->outputter = isset($output) ? $output : new Http();

    $d = getdate(time());
    if ($d['year'] < 1980) {
      $d = array('year' => 1980, 'mon' => 1, 'mday' => 1, 'hours' => 0, 'minutes' => 0, 'seconds' => 0);
    }
    $d['year'] -= 1980;
    $this->dosStamp = ($d['year'] << 25) | ($d['mon'] << 21) | ($d['mday'] << 16) |
                      ($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1);
  }

  /**
   * @param string $dest           Destination of the file inside the zip.
   * @param string $path           Original file location, should be readable.
   * @param int    $deflationLevel The zip deflate level.
   *                               -  -1 is zlib default (at the moment of writing: 6 @see http://php.net/manual/en/filters.compression.php)
   *                               -  0  no deflation => store
   *                               -  1 > 9 in increasing compression strength.
   *
   * @throws \RuntimeException when the file does not exist.
   */
  public function add($dest, $path, $deflationLevel = 0) {
    if (!file_exists($path)) {
      throw new \RuntimeException(sprintf('File %s does not exist', $path));
    }
    $this->files[] = new File($path, preg_replace('/^\//', '', $dest), $deflationLevel, $this->continuous);
  }

  /**
   * Sends the next file in the queue to the output interface.
   *
   * @returns TRUE if the file was sent, FALSE if the queue was empty.
   */
  public function send() {
    if (!isset($this->files[$this->sent])) {
      return FALSE;
    }

    $file = $this->files[$this->sent];
    // Add default properties and calculate deflatedSize & crc if not deflated and not continuous.
    $this->prepare($file);
    $local = $file->genLocal(self::ZIP_VERSION, $this->dosStamp);

    $this->output($local);
    $file->hasDeflation() ? $this->outputDeflated($file) : $this->outputRaw($file);

    $file->setLocalOffset($this->localOffset);
    $this->localOffset += strlen($local) + $file->getDeflatedSize();

    $file->genCentral(self::ZIP_VERSION, $this->dosStamp);
    $this->centralLength += strlen($file->getCentral());

    $this->sent++;
    return TRUE;
  }

  /**
   * Send any remaining files in the queue (@see send())
   * and output the central directory records (footer).
   */
  public function flush() {
    if ($this->locked) return;
    while ($this->send()) {
    }
    foreach ($this->files as $file) {
      $this->output($file->getCentral());
    }
    $this->output($this->getEOF());
    $this->locked = TRUE;
    $this->outputter->flush();
  }

  /**
   * @param File $file
   *
   * Prepare the data required for the file header.
   * size, deflatedSize, crc32, ...
   */
  protected function prepare(File $file) {
    $path = $file->getPath();
    $fstat = stat($path);
    $file->setSize($fstat['size']);
    if (!$file->isSmooth()) $file->setRawCrc(hash_file('crc32b', $path, TRUE));
    if (!$file->hasDeflation()) $file->setDeflatedSize($file->getSize());
  }

  /**
   * @param File $file
   *
   * Send the raw file to the output interface (chunked by blockSize).
   */
  protected function outputRaw(File $file) {
    $fh = fopen($file->getPath(), 'rb');
    $crc = $file->isSmooth() ? hash_init('crc32b') : FALSE;
    while (TRUE) {
      $data = fread($fh, $this->blockSize);
      if ($data === FALSE || strlen($data) == 0) break;
      $crc && hash_update($crc, $data);
      $this->output($data);
    }

    if ($crc) $file->setRawCrc(hash_final($crc, TRUE));
    fclose($fh);
  }

  /**
   * @param File $file
   *
   * Send the deflated file to the output interface (chunked by blockSize),
   * And append the Extended local headers.
   */
  protected function outputDeflated(File $file) {
    $crc = $file->isSmooth() ? hash_init('crc32b') : FALSE;

    $path = $file->getPath();
    $deflatedHandle = fopen($path, 'rb');

    $deflationFilter = stream_filter_append($deflatedHandle, 'zlib.deflate', STREAM_FILTER_READ, array('level' => $file->getDeflationLevel()));

    $rawHandle = $crc ? fopen($path, 'rb') : FALSE;

    $deflatedSize = 0;

    while (TRUE) {
      $deflated = fread($deflatedHandle, $this->blockSize);
      $raw = $rawHandle ? fread($rawHandle, $this->blockSize) : FALSE;

      $rawLen = $raw === FALSE ? 0 : strlen($raw);
      $deflatedLen = $deflated === FALSE ? 0 : strlen($deflated);

      if ($deflatedLen) {
        $deflatedSize += strlen($deflated);
        $this->output($deflated);
      }

      if ($rawLen) {
        hash_update($crc, $raw);
      }

      if (!$deflatedLen && !$rawLen) {
        break;
      }
    }

    stream_filter_remove($deflationFilter);
    fclose($deflatedHandle);
    $rawHandle && fclose($rawHandle);

    if ($crc) $file->setRawCrc(hash_final($crc, TRUE));

    $extra = pack(
      'VVVV',
      0x08074b50,
      $file->getCrc(),
      $deflatedSize,
      $file->getSize()
    );

    $deflatedSize += strlen($extra);
    $file->setDeflatedSize($deflatedSize);
    $this->output($extra);
  }

  protected function output($data) {
    $this->outputter->output($data);
  }

  /**
   * @return string
   *
   * Get the zip footer.
   */
  protected function getEOF() {
    $num = count($this->files);
    $eof = pack(
      'VvvvvVVv',
      0x06054b50,             // Signature
      0x00,                   // Number of this disk
      0x00,                   // Disk where central dir starts
      $num,                   // Number of central dirs on this disk
      $num,                   // Total central dirs
      $this->centralLength,   // Length of central dir.
      $this->localOffset,     // Offset to first centralHeader
      0                       // Comment length
    );

    return $eof;
  }

}
