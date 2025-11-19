# 🔧 Návod: Oprava Write Permissions

**Diagnostika zjistila, že 5 složek nemá správná oprávnění pro zápis.**

Toto způsobuje:
- ❌ **Nelze logovat chyby** → nevidíte PHP errors
- ❌ **Nelze nahrávat fotky** → photocustomer nefunguje
- ❌ **Nelze ukládat protokoly** → protokol.php nefunguje
- ❌ **Nelze ukládat dočasné soubory** → některé operace selhávají

---

## ⚠️ SLOŽKY VYŽADUJÍCÍ OPRAVU

```
❌ logs/
❌ uploads/
❌ temp/
❌ uploads/photos/
❌ uploads/protokoly/
```

---

## 🛠️ ŘEŠENÍ: 3 ZPŮSOBY

### **Způsob 1: Přes FTP Klient (FileZilla, WinSCP)**

1. Připojte se k vašemu hostingu přes FTP
2. Najděte root složku webu (`/www/wgs-service.cz/`)
3. Pro každou složku:
   - **Klikněte pravým tlačítkem** na složku
   - Vyberte **"File permissions"** nebo **"Změnit práva"**
   - Nastavte hodnotu: **`755`** nebo **`775`**
   - ✅ Zaškrtněte **"Rekurzivně do podsložek"**
   - Klikněte **OK**

**Vizuální nastavení v FileZilla:**
```
Číselná hodnota: 755
nebo
☑ Read    ☑ Write    ☑ Execute  (Owner)
☑ Read    ☐ Write    ☑ Execute  (Group)
☑ Read    ☐ Write    ☑ Execute  (Public)
```

**Alternativně hodnota 775 (bezpečnější):**
```
Číselná hodnota: 775
nebo
☑ Read    ☑ Write    ☑ Execute  (Owner)
☑ Read    ☑ Write    ☑ Execute  (Group)
☑ Read    ☐ Write    ☑ Execute  (Public)
```

---

### **Způsob 2: Přes Hosting Control Panel (cPanel/Plesk)**

1. Přihlaste se do vašeho hosting panelu
2. Otevřete **File Manager** (Správce souborů)
3. Najděte složky:
   ```
   /www/wgs-service.cz/logs
   /www/wgs-service.cz/uploads
   /www/wgs-service.cz/temp
   /www/wgs-service.cz/uploads/photos
   /www/wgs-service.cz/uploads/protokoly
   ```
4. Pro každou složku:
   - Vyberte složku (klikněte na ni)
   - Klikněte na **"Permissions"** nebo **"Change Permissions"** v horní liště
   - Nastavte: **755** nebo **775**
   - Zaškrtněte **"Change permissions recursively"**
   - Klikněte **"Change Permissions"**

---

### **Způsob 3: Přes SSH (pokud máte přístup)**

```bash
# Připojte se přes SSH
ssh uzivatel@wgs-service.cz

# Přejděte do root složky
cd /www/wgs-service.cz/

# Nastavte oprávnění
chmod 755 logs
chmod 755 uploads
chmod 755 temp
chmod 755 uploads/photos
chmod 755 uploads/protokoly

# Nebo vše najednou rekurzivně
chmod -R 755 logs uploads temp
```

**Alternativa s 775 (dává group write permissions):**
```bash
chmod 775 logs uploads temp
chmod -R 775 uploads/photos uploads/protokoly
```

---

## ✅ OVĚŘENÍ

Po nastavení permissions:

1. **Otevřete Admin Panel:** https://www.wgs-service.cz/admin.php
2. **Klikněte na kartu "Console"**
3. **Spusťte diagnostiku** (tlačítko "Spustit diagnostiku")
4. **Zkontrolujte sekci "6. OPRÁVNĚNÍ SOUBORŮ"**

Mělo by zobrazit:
```
✅ Všechny testované složky jsou writable
```

---

## 🔍 CO ZNAMENAJÍ HODNOTY?

| Hodnota | Význam | Kdy použít |
|---------|--------|------------|
| **755** | Owner: rwx, Group: r-x, Public: r-x | Standardní, bezpečné |
| **775** | Owner: rwx, Group: rwx, Public: r-x | Když web běží pod jiným uživatelem než FTP |
| **777** | Všichni mohou číst/psát/spouštět | ⚠️ NEBEZPEČNÉ - NIKDY NEPOUŽÍVAT! |

**Doporučení:** Zkuste nejprve **755**. Pokud stále nefunguje, použijte **775**.

---

## ❓ ČASTÉ PROBLÉMY

### **Problém:** "Permission denied" i po nastavení 755
**Řešení:** Použijte **775** místo 755, nebo kontaktujte hosting support.

### **Problém:** "Složka neexistuje"
**Řešení:** Vytvořte chybějící složky ručně:
```bash
mkdir -p logs uploads temp uploads/photos uploads/protokoly
chmod 755 logs uploads temp
chmod -R 755 uploads
```

### **Problém:** "Změny se neprojeví"
**Řešení:**
1. Zkontrolujte, že jste změnili permissions **rekurzivně** (včetně podsložek)
2. Vyprázdněte cache prohlížeče (Ctrl+Shift+R)
3. Zkuste restartovat PHP-FPM v hostingu (pokud máte možnost)

---

## 📞 POTŘEBUJETE POMOC?

Pokud problémy přetrvávají:

1. **Zkontrolujte error logy** v hosting panelu
2. **Kontaktujte svého hosting providera** - řekněte jim:
   > "Potřebuji nastavit write permissions na složky logs, uploads a temp v mé webové aplikaci. Aktuálně web nemůže zapisovat do těchto složek."

3. **Pošlete jim tento seznam složek:**
   ```
   /www/wgs-service.cz/logs
   /www/wgs-service.cz/uploads
   /www/wgs-service.cz/temp
   /www/wgs-service.cz/uploads/photos
   /www/wgs-service.cz/uploads/protokoly
   ```

---

**Po opravě permissions bude WGS fungovat správně:**
- ✅ Logy se budou zapisovat
- ✅ Fotky půjdou nahrávat
- ✅ Protokoly se uloží
- ✅ Temp soubory budou fungovat
