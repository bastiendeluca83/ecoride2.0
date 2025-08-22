<?php
echo "<pre>";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? '??') . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? '??') . "\n";
$target = dirname(__DIR__) . "/user/register.php";
echo "\nExists real /user/register.php? " . (file_exists($target) ? "YES" : "NO") . " => $target\n";
