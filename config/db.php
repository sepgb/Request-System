<?php

if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    $DB_HOST = '127.0.0.1';
    $DB_NAME = 'library_request_system';
    $DB_USER = 'root';
    $DB_PASS = '';
} else {
    $DB_HOST = 'sql102.infinityfree.com';
    $DB_NAME = 'if0_42855828_docrequest';
    $DB_USER = 'if0_42855828';
    $DB_PASS = 'agUsYfUSZAQR';
}

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    die('Database connection failed. Please try again later.');
}

$conn->set_charset('utf8mb4');
