<?php

ini_set('log_errors', 'On');
ini_set('error_log', '/var/log/php_errors.log');

$servername = "localhost";
$username = "root";
$password = "uBNN-@aRU3g,5m";
$dbname = "users";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
