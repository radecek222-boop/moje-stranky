#!/bin/bash
echo "=== SYSTEMATICKÝ TEST CELÉ CESTY ==="
echo ""

echo "1. ✅ initPhotos - existuje?"
grep -n "initPhotos()" assets/js/novareklamace.js | head -3
grep -q "photoInput.*addEventListener.*change" assets/js/novareklamace.js && echo "   ✅ Event listener OK" || echo "   ❌ CHYBÍ"

echo ""
echo "2. ✅ renderPhotos - zobrazuje fotky?"
grep -n "renderPhotos()" assets/js/novareklamace.js | head -3
grep -q "photo-thumb" assets/js/novareklamace.js && echo "   ✅ Vytváří náhledy" || echo "   ❌ NEFUNGUJE"

echo ""
echo "3. ✅ submitForm - volá uploadPhotos?"
grep -n "uploadPhotos" assets/js/novareklamace.js | grep -v "async uploadPhotos"
grep -q "if (this.photos && this.photos.length > 0)" assets/js/novareklamace.js && echo "   ✅ Kontroluje photos" || echo "   ❌ CHYBÍ kontrola"

echo ""
echo "4. ✅ uploadPhotos - posílá data?"
sed -n '/async uploadPhotos/,/^  },$/p' assets/js/novareklamace.js | grep -n "append"

echo ""
echo "5. ✅ save_photos.php - zpracovává?"
grep -n "photo_count\|handleFormDataUpload" app/controllers/save_photos.php | head -5

echo ""
echo "=== CO DĚLAT ===" 
echo "Zkopíruj tento JS kód do konzole:"
cat << 'JSTEST'

// KOMPLETNÍ DEBUG TEST
WGS.photos = []; // reset
document.getElementById('photoInput').addEventListener('change', function(e) {
  console.log('📸 File input changed!', e.target.files.length, 'files');
}, {once: true});

// Klikni na tlačítko programově
console.log('🔍 Simuluji výběr fotky...');
const input = document.getElementById('photoInput');
console.log('Input element:', input);
console.log('Upload button:', document.getElementById('uploadPhotosBtn'));

// Zkontroluj že initPhotos běží
console.log('WGS.photos array:', WGS.photos);
console.log('WGS.initPhotos existuje?', typeof WGS.initPhotos);
JSTEST
