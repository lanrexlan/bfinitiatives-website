<?php
session_start();
echo "<pre>";
echo "Session data:\n";
print_r($_SESSION);
echo "\nSession ID: " . session_id() . "\n";
echo "\nSession Name: " . session_name() . "\n";
echo "\nSession Save Path: " . session_save_path() . "\n";
echo "\nPHP Version: " . phpversion() . "\n";
echo "</pre>";