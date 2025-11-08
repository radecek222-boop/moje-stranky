#!/bin/bash

echo "🔍 ANALÝZA TOKU DETAIL ZÁKAZNÍKA"
echo "================================="
echo ""

echo "1. Hledám funkci showCustomerDetail v seznam.js..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Najít showCustomerDetail
grep -n "function showCustomerDetail" assets/js/seznam.js

echo ""
echo "2. Ukázat začátek funkce (prvních 30 řádků):"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

sed -n '/function showCustomerDetail/,+30p' assets/js/seznam.js

echo ""
echo "3. Hledam 'const content' v showCustomerDetail:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

sed -n '/function showCustomerDetail/,/^}/p' assets/js/seznam.js | grep -n "const content"

echo ""
echo "4. Hledám ModalManager.show v showCustomerDetail:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

sed -n '/function showCustomerDetail/,/^}/p' assets/js/seznam.js | grep -n "ModalManager"

echo ""
echo "5. Ukázat konec const content (kde končí):"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

sed -n '/function showCustomerDetail/,/^}/p' assets/js/seznam.js | grep -B 5 -A 2 "ModalManager.show"

echo ""
echo "6. Kontroluji jestli tam už je něco o PDF:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

sed -n '/function showCustomerDetail/,/^}/p' assets/js/seznam.js | grep -i "pdf" || echo "❌ Žádná zmínka o PDF"

echo ""
echo "7. Hledám kde jsou tlačítka ZPĚT a ULOŽIT:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

sed -n '/function showCustomerDetail/,/^}/p' assets/js/seznam.js | grep -n "ZPĚT\|ULOŽIT"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📋 SHRNUTÍ:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Spočítat řádky funkce
START=$(grep -n "function showCustomerDetail" assets/js/seznam.js | cut -d: -f1)
END=$(sed -n "${START},\$p" assets/js/seznam.js | grep -n "^}" | head -1 | cut -d: -f1)
END=$((START + END - 1))

echo "showCustomerDetail je na řádcích: $START - $END"
echo ""
echo "Vytahuju celou funkci pro analýzu..."

sed -n "${START},${END}p" assets/js/seznam.js > /tmp/showCustomerDetail_full.txt

echo "✅ Uloženo do /tmp/showCustomerDetail_full.txt"
echo ""
echo "Zobrazuji strukturu:"
echo ""

grep -n "const content\|</div>\|ModalManager" /tmp/showCustomerDetail_full.txt | tail -20

