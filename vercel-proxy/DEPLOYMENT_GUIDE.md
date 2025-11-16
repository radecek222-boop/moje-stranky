# Vercel Deployment Guide - Alternativní metody

## Problém: Hosting blokuje přístup k Vercel

Server má stejné síťové omezení které blokuje Geoapify API, a také blokuje přístup k vercel.com pro autentizaci Vercel CLI.

**Chyba:**
```
Error: request to https://vercel.com/.well-known/openid-configuration failed
reason: getaddrinfo EAI_AGAIN vercel.com
```

## ✅ Řešení 1: Deploy z lokálního počítače (DOPORUČENO)

### Krok 1: Klonovat repo lokálně

```bash
# Na vašem lokálním počítači (ne na serveru):
git clone https://github.com/radecek222-boop/moje-stranky.git
cd moje-stranky
git checkout claude/fix-website-01AqfzdTxASWkEtbUHax8mvc
```

### Krok 2: Instalace Vercel CLI

```bash
npm install -g vercel
```

### Krok 3: Přihlášení k Vercel

```bash
vercel login
```

Otevře se prohlížeč pro autentizaci. Přihlaste se pomocí:
- GitHub účtu
- GitLab účtu
- Bitbucket účtu
- nebo emailu

### Krok 4: Deploy z lokálního PC

```bash
cd vercel-proxy
vercel --prod --yes
```

Vercel CLI se zeptá:
- **Set up and deploy?** → YES
- **Which scope?** → Vyberte svůj účet
- **Link to existing project?** → NO (pro první deploy)
- **Project name?** → wgs-geocode-proxy (nebo vlastní)
- **Directory?** → ./ (ponechat výchozí)

### Krok 5: Nastavení API klíče

```bash
vercel env add GEOAPIFY_API_KEY production
```

Zadejte hodnotu: `ea590e7e6d3640f9a63ec5a9fb1ff002`

### Krok 6: Znovu deploy s environment variable

```bash
vercel --prod --yes
```

### Krok 7: Poznamenejte si URL

Po úspěšném deployi dostanete URL, např:
```
✅ Production: https://wgs-geocode-proxy.vercel.app
```

**Tuto URL si uložte** - budeme ji potřebovat pro aktualizaci frontendu.

---

## ✅ Řešení 2: Deploy přes Vercel Web Dashboard

### Krok 1: Vytvořit Vercel účet

Jděte na https://vercel.com/signup a zaregistrujte se pomocí GitHub účtu.

### Krok 2: Připojit GitHub repository

1. V Vercel dashboard klikněte na **Add New Project**
2. Importujte GitHub repository `radecek222-boop/moje-stranky`
3. Nastavte:
   - **Root Directory:** `vercel-proxy`
   - **Framework Preset:** Other
   - **Build Command:** (ponechat prázdné)
   - **Output Directory:** (ponechat prázdné)

### Krok 3: Nastavit Environment Variable

V projektu nastavte:
- **Key:** `GEOAPIFY_API_KEY`
- **Value:** `ea590e7e6d3640f9a63ec5a9fb1ff002`
- **Environment:** Production

### Krok 4: Deploy

Klikněte na **Deploy** a počkejte na dokončení.

### Krok 5: Získat Production URL

Po úspěšném deployi zkopírujte production URL z dashboardu.

---

## ✅ Řešení 3: Automatický deploy z GitHub (NEJLEPŠÍ dlouhodobě)

### Krok 1: Připojit GitHub k Vercel

1. V Vercel dashboard: **Import Project** → **Import Git Repository**
2. Vyberte `radecek222-boop/moje-stranky`
3. Autorizujte Vercel přístup k repository

### Krok 2: Konfigurace

- **Root Directory:** `vercel-proxy`
- **Build Command:** (ponechat prázdné)
- **Environment Variables:**
  - `GEOAPIFY_API_KEY` = `ea590e7e6d3640f9a63ec5a9fb1ff002`

### Krok 3: Deploy Settings

- **Production Branch:** `main` nebo `claude/fix-website-01AqfzdTxASWkEtbUHax8mvc`
- **Auto Deploy:** Enabled (každý push spustí automatický deploy)

### Výhody:
- Každý git push automaticky deployuje novou verzi
- Žádné manuální nahrávání
- Git workflow je zachován
- Rollback na předchozí verze jedním kliknutím

---

## 📝 Po úspěšném deployi

Ať už použijete kteroukoliv metodu, **po deployi:**

1. **Otestujte API endpoint:**
   ```bash
   curl "https://VASE-VERCEL-URL/api/geocode?action=autocomplete&text=Praha&type=city&limit=5"
   ```

2. **Aktualizujte frontend** v `assets/js/wgs-map.js`:
   ```javascript
   // Změnit z:
   const response = await fetch(`api/geocode_proxy.php?${params.toString()}`);

   // Na:
   const PROXY_URL = 'https://VASE-VERCEL-URL/api/geocode';
   const response = await fetch(`${PROXY_URL}?${params.toString()}`);
   ```

3. **Commitněte a pushněte změny:**
   ```bash
   git add assets/js/wgs-map.js
   git commit -m "INTEGRATION: Připojení frontendu na Vercel proxy"
   git push origin claude/fix-website-01AqfzdTxASWkEtbUHax8mvc
   ```

4. **Otestujte autocomplete** na https://wgsservice.cz/novareklamace.php

---

## 🔧 Troubleshooting

### "Project not found" při `vercel --prod`
Zkuste nejdřív `vercel` (bez --prod), který vytvoří projekt, pak `vercel --prod`.

### "Invalid API key" v response
Zkontrolujte že environment variable je správně nastavená:
```bash
vercel env ls
```

### Autocomplete stále nefunguje
1. Zkontrolujte browser console (F12) pro chyby
2. Ověřte že frontend používá správnou Vercel URL
3. Zkontrolujte CORS headers: `curl -I https://VASE-URL/api/geocode?text=Praha`

---

## 📊 Monitoring

### Zobrazit logy:
```bash
vercel logs wgs-geocode-proxy --follow
```

### Statistiky použití:
V Vercel dashboard → Analytics → Usage

### Limity free tier:
- ✅ **Bandwidth:** 100GB/měsíc (3000+ requestů/den je OK)
- ✅ **Function executions:** Unlimited
- ✅ **Builds:** 6000 minut/měsíc
