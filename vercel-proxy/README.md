# WGS Geocode Proxy - Vercel Edge Function

Cloudová proxy pro Geoapify API, umožňuje autocomplete a geocoding bez přímého přístupu k api.geoapify.com.

## ⚠️ DŮLEŽITÉ: Omezení hostingu

Hosting server **blokuje přístup k vercel.com**, takže deployment nelze spustit přímo ze serveru.

**➡️ Použijte alternativní metody v [DEPLOYMENT_GUIDE.md](./DEPLOYMENT_GUIDE.md)**

Doporučené řešení:
1. Deploy z lokálního počítače (nejrychlejší)
2. Deploy přes Vercel web dashboard
3. Automatický deploy z GitHub (nejlepší dlouhodobě)

---

## 🚀 Rychlý Deploy (pouze z lokálního PC)

### 1. Instalace Vercel CLI

```bash
npm install -g vercel
```

### 2. Přihlášení

```bash
vercel login
```

### 3. Deploy

```bash
cd vercel-proxy
vercel --prod
```

### 4. Nastavení API klíče

Po prvním deployi:

```bash
vercel env add GEOAPIFY_API_KEY production
```

Vložte váš Geoapify API klíč: `ea590e7e6d3640f9a63ec5a9fb1ff002`

### 5. Znovu deploy s environment variable

```bash
vercel --prod
```

## 📝 Použití

Po deployi dostanete URL, například: `https://wgs-proxy.vercel.app`

### Autocomplete endpoint:

```
GET https://wgs-proxy.vercel.app/api/geocode?action=autocomplete&text=Praha&type=city&country=CZ&limit=5
```

### Geocoding endpoint:

```
GET https://wgs-proxy.vercel.app/api/geocode?action=search&text=Praha%201
```

## 🔧 Konfigurace WGS Service

Po úspěšném deployi aktualizujte `assets/js/wgs-map.js`:

```javascript
// Změnit:
const response = await fetch(`api/geocode_proxy.php?${params.toString()}`);

// Na:
const PROXY_URL = 'https://wgs-proxy.vercel.app/api/geocode';
const response = await fetch(`${PROXY_URL}?${params.toString()}`);
```

## 📊 Limity

- **Free tier:** 100GB bandwidth/měsíc
- **Requests:** Neomezené
- **Regions:** Frankfurt (fra1) - nejblíže ČR
- **Response time:** ~50-150ms

## 🔒 Bezpečnost

- CORS povoleno pouze pro GET/OPTIONS
- Rate limiting na Vercel platformě
- API klíč uložen jako environment variable (nikdy v kódu)
- SSL/TLS automaticky

## 📦 Struktura

```
vercel-proxy/
├── api/
│   └── geocode.js       # Edge function
├── vercel.json          # Vercel konfigurace
├── package.json
└── README.md
```

## 🐛 Debugging

Zobrazit logy:

```bash
vercel logs wgs-proxy --follow
```

Test lokálně:

```bash
vercel dev
```

Pak: http://localhost:3000/api/geocode?action=autocomplete&text=Praha

## 💰 Cena

**ZDARMA** pro běžné použití (3,000+ requestů/den je v rámci free tier).
