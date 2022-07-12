<?php

use App\Main;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$project = new Main();
$message = $project->index();
echo $message;

