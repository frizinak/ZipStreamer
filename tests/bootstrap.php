<?php

// Props: http://tech.vg.no/2013/07/19/using-phps-built-in-web-server-in-your-test-suites/
function startPhpFileServer($host, $port, $docroot) {
  $command = sprintf('php -S %s:%d -t %s >/dev/null 2>&1 & echo $!', $host, $port, $docroot);

  $output = array();
  exec($command, $output);
  $pid = (int) $output[0];
  echo sprintf('Web server started on %s:%d with PID %d', $host, $port, $pid) . PHP_EOL;

  // Wait for the server to start.
  $files = glob($docroot . '/*');
  $file = reset($files);
  if ($file === FALSE) {
    throw new \RuntimeException('Can not determine file server status');
  }
  while (($fh = @fopen('http://' . $host . ':' . $port . '/' . basename($file), 'r')) === FALSE) {
    usleep(10000);
  }
  fclose($fh);

  register_shutdown_function(function () use ($pid) {
    echo sprintf('Killing webserver process with ID %d', $pid) . PHP_EOL;
    shell_exec('kill ' . $pid);
  });
}

$loader = require __DIR__ . '/../vendor/autoload.php';
register_shutdown_function(function () {
  $files = glob(sys_get_temp_dir() . '/' . ZIPSTREAMER_TMPFILE_PREFIX . '*');
  array_map('unlink', $files);
});

startPhpFileServer(WEB_SERVER_HOST, WEB_SERVER_PORT, WEB_SERVER_DOCROOT);
