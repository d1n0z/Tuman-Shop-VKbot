<?php

require 'db.php';

$sql = "UPDATE users SET attempts_left=attempts_left + 2 WHERE won=0 AND attempts_left<=2";
if (isset($conn)) {
    $result = $conn->query($sql);
    $conn->commit();
}
else return;

var_dump($result);
