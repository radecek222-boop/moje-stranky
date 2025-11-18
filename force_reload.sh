#!/bin/bash
# Force reload photocustomer.php - OKAMŽITÝ RESTART

echo "=== FORCE RELOAD PHOTOCUSTOMER.PHP ==="
echo ""

# Touch soubor
touch /home/user/moje-stranky/photocustomer.php
echo "✅ Touch photocustomer.php: OK"

# Reload PHP-FPM
if command -v systemctl &> /dev/null; then
    echo "🔄 Reloaduji PHP-FPM..."

    # Zkus různé názvy PHP-FPM služeb
    if systemctl list-units --type=service | grep -q "php8.4-fpm"; then
        sudo systemctl reload php8.4-fpm 2>/dev/null && echo "✅ php8.4-fpm reloadován" || echo "⚠️ Nelze reloadovat php8.4-fpm"
    elif systemctl list-units --type=service | grep -q "php-fpm"; then
        sudo systemctl reload php-fpm 2>/dev/null && echo "✅ php-fpm reloadován" || echo "⚠️ Nelze reloadovat php-fpm"
    else
        echo "⚠️ PHP-FPM služba nenalezena"
    fi
else
    echo "⚠️ systemctl není dostupný"
fi

echo ""
echo "=== HOTOVO ==="
echo "Technik může zkusit otevřít photocustomer.php znovu!"
