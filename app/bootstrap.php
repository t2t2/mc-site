<?php
require_once __DIR__ . '/../vendor/autoload.php';

$configuration = new Bolt\Configuration\Composer(dirname(__DIR__));
$configuration->setPath('web', 'public');
$configuration->setPath('files', 'public/files');
$configuration->setPath('themebase', 'theme');
$configuration->getVerifier()->disableApacheChecks();
$configuration->verify();
$app = new Bolt\Application(['resources' => $configuration]);
$app->initialize();

// Bind site specific code
$app->register(new \Mindcrack\Site\SiteServiceProvider());

return $app;