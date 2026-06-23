<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

class test
{
};

$a = new test();

try {
    var_dump($a->check);
} catch (Throwable $throwable) {
    var_dump($throwable);
}