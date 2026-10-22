<?php
date_default_timezone_set('Asia/Kuala_Lumpur');

$host     = "localhost";
$user     = "root";
$password = "";
$database = "foodtime";

$connect = mysqli_connect($host, $user, $password, $database);

if (!$connect) {
    die("Fail connection: " . mysqli_connect_error());
}

mysqli_set_charset($connect, "utf8mb4");

mysqli_query($connect, "SET time_zone = '+08:00'");

define('MY_TIME', date('Y-m-d H:i:s'));