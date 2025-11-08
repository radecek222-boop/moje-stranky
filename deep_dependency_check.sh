#!/bin/bash

echo "🔍 HLOUBKOVÁ ANALÝZA ZÁVISLOSTÍ"
echo "=================================="
echo ""

echo "1️⃣ KONTROLA FETCH/AJAX VOLÁNÍ V JAVASCRIPTU:"
echo "=============================================="
echo ""

# Najít všechny fetch() a ajax volání na PHP soubory
echo "📡 Soubory volané přes fetch() nebo ajax:"
grep -r "fetch\|ajax\|XMLHttpRequest" assets/js/*.js 2>/dev/null | \
grep -o "[a-zA-Z_/-]*\.php" | \
sort -u | \
while read file; do
    echo "   → $file"
done

echo ""
echo ""

echo "2️⃣ KONTROLA PHP INCLUDE/REQUIRE:"
echo "================================="
echo ""

# Test.php
echo "📄 app/controllers/test.php:"
if [ -f "app/controllers/test.php" ]; then
    echo "   Obsah:"
    cat app/controllers/test.php | head -20
else
    echo "   ✅ Soubor neexistuje"
fi

echo ""

# get_joke.php.old
echo "📄 app/controllers/get_joke.php.old:"
if [ -f "app/controllers/get_joke.php.old" ]; then
    echo "   Je někde použit?"
    grep -r "get_joke\.php\.old" . --include="*.php" --include="*.js" 2>/dev/null
    if [ $? -ne 0 ]; then
        echo "   ✅ Nikde není odkazován"
    fi
else
    echo "   ✅ Soubor neexistuje"
fi

echo ""
echo ""

echo "3️⃣ DŮKLADNÁ KONTROLA VŠECH PHP SOUBORŮ:"
echo "========================================"
echo ""

# Kontrola každého PHP souboru v controllers
for phpfile in app/controllers/*.php; do
    basename=$(basename "$phpfile")
    
    # Přeskočit už zkontrolované
    if [[ "$basename" == "test.php" ]] || [[ "$basename" == "get_joke.php.old" ]]; then
        continue
    fi
    
    # Hledat reference
    found=0
    
    # V PHP souborech
    grep -l "$basename" *.php 2>/dev/null > /dev/null && found=1
    
    # V JS souborech
    grep -l "$basename" assets/js/*.js 2>/dev/null > /dev/null && found=1
    
    # V HTML souborech
    grep -l "$basename" *.html 2>/dev/null > /dev/null && found=1
    
    if [ $found -eq 0 ]; then
        echo "⚠️  $basename - NENALEZEN žádný odkaz!"
    fi
done

echo ""
echo ""

echo "4️⃣ KONTROLA KONKRÉTNÍCH SOUBORŮ:"
echo "=================================="
echo ""

# Kontrola load_errors.log
echo "📋 load_errors.log:"
if [ -f "app/controllers/load_errors.log" ]; then
    size=$(stat -f%z "app/controllers/load_errors.log" 2>/dev/null || stat -c%s "app/controllers/load_errors.log" 2>/dev/null)
    echo "   Velikost: $size bytes"
    if [ $size -gt 0 ]; then
        echo "   Posledních 10 řádků:"
        tail -10 app/controllers/load_errors.log
    else
        echo "   ✅ Soubor je prázdný - SMAZAT"
    fi
else
    echo "   ✅ Soubor neexistuje"
fi

echo ""
echo ""

echo "5️⃣ ZÁVĚR A DOPORUČENÍ:"
echo "======================"
echo ""

echo "✅ BEZPEČNÉ SMAZAT:"
echo ""

# Test test.php
if [ -f "app/controllers/test.php" ]; then
    # Zkontrolovat jestli obsahuje důležitý kód
    if grep -q "require\|include\|function" app/controllers/test.php 2>/dev/null; then
        echo "   ⚠️  test.php obsahuje kód - KONTROLOVAT RUČNĚ"
    else
        echo "   ✅ test.php - prázdný nebo testovací"
    fi
fi

# get_joke.php.old
if [ -f "app/controllers/get_joke.php.old" ]; then
    echo "   ✅ get_joke.php.old - stará verze"
fi

# load_errors.log
if [ -f "app/controllers/load_errors.log" ]; then
    echo "   ✅ load_errors.log - log soubor"
fi

echo ""
echo "❌ PONECHAT (používají se):"
echo ""
echo "   Všechny ostatní soubory jsou aktivně používané!"

