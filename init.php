<?php

// Enable output buffering to prevent "headers already sent" errors
ob_start();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}