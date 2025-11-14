#!/bin/bash
# Download PHPMailer Script
# Stáhne PHPMailer pokud není nainstalovaný

VENDOR_DIR="../vendor"
PHPMAILER_DIR="$VENDOR_DIR/phpmailer"

if [ -d "$PHPMAILER_DIR" ]; then
    echo "✓ PHPMailer je již nainstalovaný"
    exit 0
fi

echo "📥 Stahuji PHPMailer..."

mkdir -p "$VENDOR_DIR"
cd "$VENDOR_DIR"

curl -sL https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.tar.gz -o phpmailer.tar.gz

if [ $? -ne 0 ]; then
    echo "❌ Chyba při stahování PHPMailer"
    exit 1
fi

tar -xzf phpmailer.tar.gz
mv PHPMailer-6.9.1 phpmailer
rm phpmailer.tar.gz

if [ -d "phpmailer" ]; then
    echo "✅ PHPMailer byl úspěšně nainstalován"
    exit 0
else
    echo "❌ Chyba při instalaci PHPMailer"
    exit 1
fi
