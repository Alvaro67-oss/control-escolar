<?php
session_start();

if (!isset($_SESSION['plantel_id'])) {
    header("Location: login.php");
    exit();
}
