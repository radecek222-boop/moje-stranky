#!/bin/bash
# Přidej na začátek save_photos.php po session_start()
sed -i '/^session_start/a\
error_log("=== save_photos.php START ===");\
error_log("POST data: " . print_r($_POST, true));\
error_log("REQUEST_METHOD: " . $_SERVER["REQUEST_METHOD"]);' app/controllers/save_photos.php

# Přidej logging před ukládáním do DB
sed -i '/INSERT INTO wgs_photos/i\
                    error_log("💾 Ukládám fotku do DB: " . $filename);' app/controllers/save_photos.php

echo "✅ Debug logging přidán!"
