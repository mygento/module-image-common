<?php
$header = <<<EOF
@author Mygento Team
@copyright 2026 Mygento (https://www.mygento.com)
@package Mygento_ImageCommon
EOF;

$finder = PhpCsFixer\Finder::create()
    ->in('.')
    ->name('*.phtml')
    ->ignoreVCSIgnored(true);

$config = new \Mygento\CS\Config\Module($header);
$config->setFinder($finder);
return $config;
