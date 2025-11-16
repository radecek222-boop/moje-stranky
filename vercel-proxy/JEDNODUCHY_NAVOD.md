# 🎯 SUPER JEDNODUCHÝ NÁVOD - Bez příkazů, jen klikání!

## ✅ Co budete potřebovat:
- GitHub účet (který už máte na `radecek222-boop`)
- 10 minut času
- Jen klikání v prohlížeči, žádné příkazy!

---

## Krok 1: Zaregistrujte se na Vercel (ZDARMA)

1. Otevřete: **https://vercel.com/signup**
2. Klikněte na **"Continue with GitHub"**
3. Přihlaste se svým GitHub účtem `radecek222-boop`
4. Povolte Vercel přístup (klikněte "Authorize")

✅ Hotovo! Nyní jste v Vercel dashboardu.

---

## Krok 2: Importujte projekt

1. Klikněte na tlačítko **"Add New..."** → **"Project"**
2. Pokud není `moje-stranky` v seznamu:
   - Klikněte **"Adjust GitHub App Permissions"**
   - Povolte přístup k repository `moje-stranky`
   - Vraťte se zpět
3. Najděte **`radecek222-boop/moje-stranky`** a klikněte **"Import"**

---

## Krok 3: Nastavte projekt (DŮLEŽITÉ!)

### A) Root Directory
- Najděte pole **"Root Directory"**
- Klikněte **"Edit"**
- Napište: `vercel-proxy`
- Klikněte **"Continue"**

### B) Environment Variables (Proměnné prostředí)
- Klikněte na **"Environment Variables"** (rozbalit)
- Do pole **"Key"** napište: `GEOAPIFY_API_KEY`
- Do pole **"Value"** zkopírujte: `ea590e7e6d3640f9a63ec5a9fb1ff002`
- Zaškrtněte **"Production"**
- Klikněte **"Add"**

### C) Ostatní nastavení
- **Framework Preset:** ponechte "Other"
- **Build Command:** ponechte prázdné
- **Output Directory:** ponechte prázdné
- **Install Command:** ponechte prázdné

---

## Krok 4: Spusťte deployment

1. Klikněte na velké modré tlačítko **"Deploy"**
2. Počkejte cca 1-2 minuty (uvidíte progress bar)
3. Až uvidíte **"Congratulations!"** s konfetami 🎉 - je to hotovo!

---

## Krok 5: Zkopírujte URL

1. Uvidíte něco jako:
   ```
   https://moje-stranky-xxxxxxxx.vercel.app
   ```
2. **Zkopírujte tuto celou URL** (budeme ji potřebovat)

---

## Krok 6: Otestujte že to funguje

Otevřete v prohlížeči (NAHRAĎTE `xxxxxxxx` vaší skutečnou URL):
```
https://moje-stranky-xxxxxxxx.vercel.app/api/geocode?action=autocomplete&text=Praha&type=city&limit=5
```

**Měli byste vidět JSON data s městy** (ne chybu!).

Pokud vidíte JSON → **FUNGUJE TO!** ✅

---

## Krok 7: Pošlete mi URL

**Napište mi zde v chatu:**
```
Mám URL: https://moje-stranky-xxxxxxxx.vercel.app
```

A já **automaticky:**
1. Upravím kód na webu aby používal tuto URL
2. Otestuji že autocomplete funguje
3. Všechno commitnu a pushnu

**Vy už nemusíte dělat NIC dalšího!** 🎉

---

## ❓ Pomoc při problémech

### "Repository moje-stranky není v seznamu"
→ Klikněte "Adjust GitHub App Permissions" a povolte přístup

### "Deployment failed"
→ Zkontrolujte že:
- Root Directory je nastaveno na `vercel-proxy`
- Environment Variable má správně `GEOAPIFY_API_KEY`

### "API klíč nefunguje"
→ Zkontrolujte že jste správně zkopírovali: `ea590e7e6d3640f9a63ec5a9fb1ff002`

### "Nevím kde kliknout"
→ Pošlete screenshot a já vám řeknu přesně kam

---

## 💡 Co se stane po deployi?

Vaše Vercel Edge Function bude:
- ✅ Běžet na cloudu (mimo váš hosting)
- ✅ Obcházet firewall omezení
- ✅ Poskytovat autocomplete data pro našeptávač
- ✅ ZDARMA pro 3000+ requestů/den
- ✅ Rychlá (50-150ms z ČR)

**Autocomplete na novareklamace.php BUDE FUNGOVAT!** 🚀

---

## 📞 Potřebujete pomoc?

Napište mi:
- Screenshot kde jste
- Co vidíte
- Kde nevíte jak dál

**Provedeme to spolu krok za krokem!**
