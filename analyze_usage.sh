#!/bin/bash

echo "🔍 ANALÝZA POUŽÍVANÝCH SOUBORŮ"
echo "================================"
echo ""

echo "1️⃣ HLAVNÍ STRÁNKY (určitě se používají):"
echo "   ✅ index.php - Hlavní stránka"
echo "   ✅ login.php - Přihlášení"
echo "   ✅ registration.php - Registrace"
echo "   ✅ seznam.php - Seznam reklamací"
echo "   ✅ novareklamace.php - Nová reklamace"
echo "   ✅ onas.php - O nás"
echo "   ✅ nasesluzby.php - Naše služby"
echo ""

echo "2️⃣ KONTROLA ODKAZŮ NA SOUBORY:"
echo ""

# Admin soubory
echo "📊 ADMIN soubory:"
echo "   admin.php - Zkontrolujeme odkazy:"
grep -l "admin.php" *.php *.html 2>/dev/null | head -3
if [ $? -ne 0 ]; then echo "   ⚠️  Nenalezen žádný odkaz!"; fi

echo ""
echo "   admin_api.php - Zkontrolujeme odkazy:"
grep -l "admin_api.php" *.php assets/js/*.js 2>/dev/null | head -3
if [ $? -ne 0 ]; then echo "   ⚠️  Nenalezen žádný odkaz!"; fi

echo ""
echo "   admin_key_manager.php - Zkontrolujeme odkazy:"
grep -l "admin_key_manager.php" *.php assets/js/*.js 2>/dev/null | head -3
if [ $? -ne 0 ]; then echo "   ⚠️  Nenalezen žádný odkaz!"; fi

echo ""

# Ostatní soubory
echo "📄 OSTATNÍ PHP soubory:"

echo ""
echo "   mimozarucniceny.php - Zkontrolujeme odkazy:"
grep -l "mimozarucniceny" *.php *.html assets/js/*.js 2>/dev/null | head -3
if [ $? -ne 0 ]; then echo "   ⚠️  Nenalezen žádný odkaz!"; fi

echo ""
echo "   photocustomer.php - Zkontrolujeme odkazy:"
grep -l "photocustomer" *.php *.html assets/js/*.js 2>/dev/null | head -3
if [ $? -ne 0 ]; then echo "   ⚠️  Nenalezen žádný odkaz!"; fi

echo ""
echo "   protokol.php - Zkontrolujeme odkazy:"
grep -l "protokol" *.php *.html assets/js/*.js 2>/dev/null | head -3
if [ $? -ne 0 ]; then echo "   ⚠️  Nenalezen žádný odkaz!"; fi

echo ""
echo "   psa-kalkulator.php - Zkontrolujeme odkazy:"
grep -l "psa-kalkulator\|psa_kalkulator" *.php *.html assets/js/*.js 2>/dev/null | head -3
if [ $? -ne 0 ]; then echo "   ⚠️  Nenalezen žádný odkaz!"; fi

echo ""
echo "   psa.php - Zkontrolujeme odkazy:"
grep -l "psa.php" *.php *.html assets/js/*.js 2>/dev/null | head -3
if [ $? -ne 0 ]; then echo "   ⚠️  Nenalezen žádný odkaz!"; fi

echo ""
echo "   analytics.php - Zkontrolujeme odkazy:"
grep -l "analytics" *.php *.html assets/js/*.js 2>/dev/null | head -3
if [ $? -ne 0 ]; then echo "   ⚠️  Nenalezen žádný odkaz!"; fi

echo ""
echo "   offline.php - Zkontrolujeme odkazy:"
grep -l "offline" *.php *.html 2>/dev/null | head -3
if [ $? -ne 0 ]; then echo "   ⚠️  Nenalezen žádný odkaz!"; fi

echo ""
echo ""
echo "3️⃣ PODEZŘELÉ SOUBORY V /app/controllers:"
echo ""
echo "   test.php - Testovací soubor (❌ SMAZAT)"
echo "   get_joke.php.old - Stará verze (❌ SMAZAT)"
echo "   load_errors.log - Log soubor (⚠️  kontrolovat)"

echo ""
echo ""
echo "4️⃣ DOPORUČENÍ:"
echo "=============="
echo ""
echo "❌ SMAZAT (nepoužívá se):"
echo "   - app/controllers/test.php"
echo "   - app/controllers/get_joke.php.old"
echo ""
echo "⚠️  ZKONTROLOVAT (možná nepoužívané):"
echo "   - analytics.php (pokud není v menu)"
echo "   - offline.php (pokud není service worker)"
echo "   - photocustomer.php (pokud není v reklamacích)"
echo "   - psa.php, psa-kalkulator.php (pokud není v menu)"
echo ""
echo "✅ PONECHAT (používají se):"
echo "   - Všechny hlavní stránky"
echo "   - admin.php a admin_*.php (správa)"
echo "   - protokol.php (generování PDF)"
echo "   - mimozarucniceny.php (mimo záruku)"
echo ""

