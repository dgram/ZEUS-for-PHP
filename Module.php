<?php

if (version_compare(PHP_VERSION, '7.0.0', '<') && !class_exists("Throwable")) {
    eval("class Throwable extends Exception {}");
}

require __DIR__ . '/src/Zeus/Module.php';