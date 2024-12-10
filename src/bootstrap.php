#!/usr/bin/env php
<?php

use Server\CliProcessor;

require __DIR__ . "/../vendor/autoload.php";

$cli = new CliProcessor();

$cli->exec();


