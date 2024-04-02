<?php

ini_set('log_errors', 'On');
ini_set('error_log', '/var/log/php_errors.log');

require 'db.php';

$sql = "SELECT * FROM users WHERE won = 1";
if (isset($conn)) $result = $conn->query($sql);
else return;

if ($result->num_rows > 0) {
    echo "$result->num_rows results: <br>";
    while ($row = $result->fetch_assoc()) {
        echo "Name: " . $row["name"] . " > " . "<a href='" . "https://vk.me/" . $row["vk_id"] . "' target='_blank'>" . "https://vk.me/" . $row["vk_id"] . "</a><br>";
    }
    echo "Places: $result->num_rows/35";
} else {
    echo "0 results.";
}
