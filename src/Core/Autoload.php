<?php
spl_autoload_register(function ($class) {
  $prefixes = [
    'Core\\' => __DIR__ . '/',
    'Controllers\\' => dirname(__DIR__) . '/Controllers/',
    'Models\\' => dirname(__DIR__) . '/Models/',
    'Services\\' => dirname(__DIR__) . '/Services/',
  ];
  foreach ($prefixes as $prefix => $baseDir) {
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) continue;
    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\','/',$relative) . '.php';
    if (file_exists($file)) require $file;
  }
});
