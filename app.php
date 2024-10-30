#!/usr/bin/env php
<?php

use Symfony\Component\Filesystem\Filesystem;

foreach ([
           __DIR__ . '/../../autoload.php',
           __DIR__ . '/../vendor/autoload.php',
           __DIR__ . '/vendor/autoload.php',
         ] as $file) {
  if (file_exists($file)) {
    $class_loader = require_once $file;
    break;
  }
}

$START_DIR = getcwd() . '/';

//if (!file_exists($START_DIR . '/' . InitializeDirectory::DIRNAME)) {
//  if ((new InitializeDirectory())($START_DIR)) {
//    $this->output
//  }
//
//}
//
//$base = __DIR__ . '/../demo';
//$document_path = "$base/instructions.md";
//$state_path = "$base/.directio/state.yml";
//$filtered_doc_path = (new \AKlump\Directio\GetResultFilename(date_create('now', AKlump\LocalTimezone\LocalTimezone::get())))($document_path);
//$filtered_doc_path = "$base/.directio/$filtered_doc_path";


$application = new \Symfony\Component\Console\Application();
$application->add(new \AKlump\Directio\Command\InitializeCommand());
$application->add(new \AKlump\Directio\Command\UpdateCommand());
$application->add(new \AKlump\Directio\Command\NewCommand());
$application->run();
