#!/bin/bash

echo "🔍 DIAGNOSTIKA DETAILU ZÁKAZNÍKA"
echo "================================="
echo ""

echo "1. Kontrola souborů:"
echo "==================="
ls -lh assets/js/seznam*.js

echo ""
echo "2. Kontrola načítání v seznam.php:"
echo "==================================="
grep -A 2 "seznam.js" seznam.php

echo ""
echo "3. Kontrola showDetail v seznam.js:"
echo "===================================="
grep -c "function showDetail" assets/js/seznam.js

echo ""
echo "4. Kontrola patch souboru:"
echo "=========================="
head -5 assets/js/seznam-delete-patch.js

echo ""
echo "✅ Diagnostika dokončena"
echo ""
echo "Pokud detail nefunguje, spusťte v browseru:"
echo "  F12 → Console → Podívejte se na chyby"
echo ""
echo "A pošlete mi co píše v console!"
