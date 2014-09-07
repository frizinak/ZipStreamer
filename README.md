## Usage

```php

$output = new ThrottledHttp('download', 1024 * 1024 * 3); // Stream ~3 MB/s
$zip = new ZipStreamer($output);
foreach($images as $filePath){
  $zip->add('images/' . basename($filePath), $filePath); // No deflation
  $zip->send(); // starts reading from $filePath and streaming the zip.
}

foreach($httpTextFiles as $fileUri) {
  $zip->add('txts/' . basename($file), $fileUri, 9); // Max deflation level.
}

$fh = fopen('http://google.com', 'rb');
$zip->add('google.html', $fh, -1); // Default zlib delfation level.

$zip->flush(); // Sends any remaining files and finishes streaming the zip.

```
