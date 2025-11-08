#!/bin/bash
# Přidej na začátek save_photos.php logging
sed -i '3a\
error_log("=== save_photos.php CALLED ===");\
error_log("POST data: " . print_r($_POST, true));\
error_log("FILES data: " . print_r($_FILES, true));' app/controllers/save_photos.php
