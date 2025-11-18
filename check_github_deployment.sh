#!/bin/bash
# GitHub Deployment Diagnostic
# Ověří že GitHub je správně nastaven pro deployment na wgs-service.cz

echo "=== 🔍 GitHub Deployment Diagnostic ==="
echo ""

# 1. Git remote
echo "1️⃣ Git Remote:"
git remote -v | grep origin
echo ""

# 2. Current branch
echo "2️⃣ Aktuální branch:"
git branch --show-current
echo ""

# 3. Uncommitted changes
echo "3️⃣ Necommitnuté změny:"
if [ -z "$(git status --porcelain)" ]; then
    echo "✅ Žádné necommitnuté změny"
else
    echo "⚠️ Máte necommitnuté změny:"
    git status --short
fi
echo ""

# 4. Commits ahead of main
echo "4️⃣ Commits před main:"
git fetch origin main 2>/dev/null
AHEAD=$(git rev-list --count origin/main..HEAD 2>/dev/null || echo "0")
echo "📊 Vaše branch je $AHEAD commits před origin/main"
echo ""

# 5. GitHub workflow file
echo "5️⃣ GitHub Actions workflow:"
if [ -f .github/workflows/deploy.yml ]; then
    echo "✅ deploy.yml existuje"
    echo "Deployuje z větví:"
    grep -A 2 "branches:" .github/workflows/deploy.yml | grep -v "^--$"
    echo ""
    echo "Deploy target:"
    grep "remote_path:" .github/workflows/deploy.yml
else
    echo "❌ deploy.yml nenalezen"
fi
echo ""

# 6. Required secrets
echo "6️⃣ Požadované GitHub Secrets:"
echo "Pro deployment potřebujete nastavit tyto secrets v GitHub repo:"
echo "   - FTP_HOST (server.wgs-service.cz nebo IP)"
echo "   - FTP_USERNAME (SSH/SFTP username)"
echo "   - FTP_PASSWORD (SSH/SFTP password)"
echo ""
echo "Zkontrolujte na: https://github.com/radecek222-boop/moje-stranky/settings/secrets/actions"
echo ""

# 7. Recent GitHub Actions runs
echo "7️⃣ Poslední GitHub Actions:"
echo "Zkontrolujte na: https://github.com/radecek222-boop/moje-stranky/actions"
echo ""

# 8. Production server path
echo "8️⃣ Produkční server path:"
echo "Podle workflow deployne do: /wgs-service.cz/www/"
echo "Skutečná produkční cesta: /home/www/wgs-service.cz/www/wgs-service.cz/www/"
echo ""
echo "⚠️ POZOR: Cesty se NESHODUJÍ!"
echo "   Workflow: /wgs-service.cz/www/"
echo "   Skutečné: /home/www/wgs-service.cz/www/wgs-service.cz/www/"
echo ""

# 9. Recommended action
echo "9️⃣ Doporučený postup:"
echo "   1. Mergněte vaši feature branch do main"
echo "   2. Push do main spustí GitHub Actions"
echo "   3. Ověřte že deployment proběhl na wgs-service.cz"
echo ""

echo "=== ✅ Diagnostic Complete ==="
