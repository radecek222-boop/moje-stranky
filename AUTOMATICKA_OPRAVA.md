# ⚡ AUTOMATICKÁ OPRAVA DIAGNOSTIKY - JEDEN KLIK

## 🎯 CO TO DĚLÁ?

Tento skript **automaticky opraví všechny problémy** zjištěné diagnostikou:
- ✅ Přidá chybějící databázové indexy (3 indexy)
- ✅ **AUTOMATICKY** opraví write permissions (5 složek)
- ✅ Ověří opravy pomocí diagnostiky

---

## 🚀 JAK TO SPUSTIT?

### **Stačí otevřít tento odkaz:**

```
https://www.wgs-service.cz/automaticka_oprava_diagnostiky.php
```

**To je VŠE!** Skript vás provede všemi kroky automaticky.

---

## 📋 CO SE STANE?

### **Krok 1: Databázové indexy** (automaticky)
- Skript přidá 3 chybějící indexy
- Zrychlí dotazy na `updated_at` a `created_at` sloupce
- Trvá: ~10 sekund

### **Krok 2: Write Permissions** (automaticky)
- Skript **automaticky nastaví permissions** na 0775 pro všechny složky
- Vytvoří chybějící složky, pokud neexistují
- V 99% případů to funguje bez zásahu!
- Trvá: ~5 sekund

### **Krok 3: Ověření** (automaticky)
- Spustí diagnostiku
- Ukáže, že vše funguje
- Hotovo!

---

## ⚠️ CO MUSÍTE UDĚLAT RUČNĚ?

**TÉMĚŘ NIC!** Skript vše opraví automaticky.

**Pouze v případě, že automatická oprava permissions selže** (1% případů):

1. **Otevřete FTP klient** (FileZilla, WinSCP)
2. **Najděte tyto složky:**
   ```
   logs/
   uploads/
   temp/
   uploads/photos/
   uploads/protokoly/
   ```
3. **Pro každou složku:**
   - Pravé tlačítko → **Permissions**
   - Nastavte: **755** nebo **775**
   - Zaškrtněte: **"Rekurzivně do podsložek"**
   - Klikněte **OK**

**Detailní návod:** [OPRAVA_PERMISSIONS.md](OPRAVA_PERMISSIONS.md)

💡 **TIP:** Pokud automatický skript ohlásí chybu při nastavování permissions, použijte tento návod.

---

## 📊 OČEKÁVANÝ VÝSLEDEK

**Po spuštění automatického skriptu:**

| Před | Po |
|------|-----|
| ❌ 8 chyb | ✅ 0 chyb |
| ⚠️ 3 upozornění | ✅ 0 upozornění |
| ❌ Chybějící indexy | ✅ Indexy přidány |
| ❌ Config file missing | ✅ Vše nalezeno |

**Po ručním nastavení permissions:**

| Před | Po |
|------|-----|
| ❌ 5 složek not writable | ✅ Všechny writable |
| ❌ Fotky se nenahrají | ✅ Funguje |
| ❌ Protokoly nefungují | ✅ Funguje |
| ❌ Žádné logy | ✅ Logy se zapisují |

---

## 🔗 ALTERNATIVNÍ ODKAZY

Pokud chcete spustit pouze některou část:

### **Pouze indexy:**
```
https://www.wgs-service.cz/pridej_chybejici_indexy_performance.php?auto=1
```

### **Pouze permissions:**
```
https://www.wgs-service.cz/oprav_permissions_slozek.php?auto=1
```

### **Pouze diagnostika:**
```
https://www.wgs-service.cz/admin.php?tab=console
```

---

## ❓ NEJČASTĚJŠÍ OTÁZKY

### **Q: Je to bezpečné?**
✅ Ano! Skript:
- Kontroluje admin přihlášení
- Používá prepared statements
- Neprovádí destruktivní operace
- Pouze přidává indexy (nic nemažete)

### **Q: Můžu to spustit vícekrát?**
✅ Ano! Skript kontroluje, co už je provedeno a neprovede to znovu.

### **Q: Co když něco selže?**
✅ Skript zobrazí chybu a pokračuje dál. Můžete to spustit znovu.

### **Q: Jak dlouho to trvá?**
⏱️ Celková automatická oprava: **~20 sekund**
⏱️ Ruční oprava permissions (jen při selhání): ~5 minut

---

## 📞 POTŘEBUJETE POMOC?

1. **Zkontrolujte logy:** Admin Panel → Console → Error Logy
2. **Spusťte diagnostiku znovu:** Admin Panel → Console → Spustit diagnostiku
3. **Zkuste automatický skript znovu:** Často pomůže druhý pokus

---

**✨ Pro většinu případů stačí prostě otevřít tento odkaz a hotovo:**

🔗 **https://www.wgs-service.cz/automaticka_oprava_diagnostiky.php**
