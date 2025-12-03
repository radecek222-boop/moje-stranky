<?php
/**
 * Diagnostika HTTP requestu
 * Zobrazuje co PHP skutecne vidi
 */
header('Content-Type: application/json');

echo json_encode([
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'UNDEFINED',
    'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'UNDEFINED',
    'CONTENT_LENGTH' => $_SERVER['CONTENT_LENGTH'] ?? 'UNDEFINED',
    'POST' => $_POST,
    'GET' => $_GET,
    'php_input' => file_get_contents('php://input'),
    'php_input_length' => strlen(file_get_contents('php://input')),
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'UNDEFINED',
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'UNDEFINED'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
