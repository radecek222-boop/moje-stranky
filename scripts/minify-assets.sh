#!/bin/bash
#
# WGS Service - Asset Minification Script
# Minifikuje všechny JS a CSS soubory pro produkci
#

set -euo pipefail

# Barvy pro output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "=========================================="
echo "WGS Service - Asset Minification"
echo "=========================================="
echo ""

# Kontrola dostupnosti nástrojů
check_tools() {
    local missing=0

    echo "Checking for minification tools..."

    if ! command -v terser &> /dev/null; then
        echo -e "${YELLOW}⚠ terser not found${NC}"
        missing=$((missing + 1))
    else
        echo -e "${GREEN}✓ terser available ($(terser --version))${NC}"
    fi

    if ! command -v csso-cli &> /dev/null && ! command -v csso &> /dev/null; then
        echo -e "${YELLOW}⚠ csso not found${NC}"
        missing=$((missing + 1))
    else
        echo -e "${GREEN}✓ csso available${NC}"
    fi

    if [ $missing -gt 0 ]; then
        echo ""
        echo -e "${RED}Some tools are missing. Please install them:${NC}"
        echo ""
        echo "npm (recommended):"
        echo "  npm install -g terser csso-cli"
        echo ""
        echo "Alternative - yarn:"
        echo "  yarn global add terser csso-cli"
        echo ""
        exit 1
    fi

    echo ""
}

# Minifikace JS souboru
minify_js() {
    local file="$1"
    local dir=$(dirname "$file")
    local basename=$(basename "$file" .js)
    local minified="$dir/${basename}.min.js"

    # Pokud už je soubor minifikovaný, přeskočit
    if [[ "$file" == *.min.js ]]; then
        return 0
    fi

    # Pokud existuje .min.js verze a je novější než source, přeskočit
    if [ -f "$minified" ] && [ "$minified" -nt "$file" ]; then
        echo -e "  ${BLUE}$(basename "$file") - already minified${NC}"
        return 0
    fi

    echo -n "  $(basename "$file") -> $(basename "$minified")... "

    local size_before=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")

    # Minifikovat s terser
    if terser "$file" \
        --compress \
        --mangle \
        --output "$minified" \
        --source-map "filename='$(basename "$minified").map',url='$(basename "$minified").map'" \
        2>/dev/null; then

        local size_after=$(stat -f%z "$minified" 2>/dev/null || stat -c%s "$minified")
        local saved=$((size_before - size_after))
        local percent=$((saved * 100 / size_before))

        echo -e "${GREEN}saved $(numfmt --to=iec-i --suffix=B $saved) ($percent%)${NC}"
    else
        echo -e "${RED}failed${NC}"
        return 1
    fi
}

# Minifikace CSS souboru
minify_css() {
    local file="$1"
    local dir=$(dirname "$file")
    local basename=$(basename "$file" .css)
    local minified="$dir/${basename}.min.css"

    # Pokud už je soubor minifikovaný, přeskočit
    if [[ "$file" == *.min.css ]]; then
        return 0
    fi

    # Pokud existuje .min.css verze a je novější než source, přeskočit
    if [ -f "$minified" ] && [ "$minified" -nt "$file" ]; then
        echo -e "  ${BLUE}$(basename "$file") - already minified${NC}"
        return 0
    fi

    echo -n "  $(basename "$file") -> $(basename "$minified")... "

    local size_before=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")

    # Minifikovat s csso
    if command -v csso &> /dev/null; then
        csso -i "$file" -o "$minified" --source-map 2>/dev/null || csso "$file" -o "$minified" 2>/dev/null
    elif command -v csso-cli &> /dev/null; then
        csso-cli -i "$file" -o "$minified" --map file 2>/dev/null
    else
        echo -e "${RED}no minifier available${NC}"
        return 1
    fi

    local size_after=$(stat -f%z "$minified" 2>/dev/null || stat -c%s "$minified")
    local saved=$((size_before - size_after))
    local percent=$((saved * 100 / size_before))

    echo -e "${GREEN}saved $(numfmt --to=iec-i --suffix=B $saved) ($percent%)${NC}"
}

# Najít všechny JS/CSS soubory
find_assets() {
    echo "Finding JavaScript and CSS files..."
    echo ""

    # Najít JS soubory (kromě již minifikovaných)
    while IFS= read -r -d '' file; do
        if [[ ! "$file" == *.min.js ]]; then
            JS_FILES+=("$file")
        fi
    done < <(find "$PROJECT_ROOT/assets/js" -type f -name "*.js" -print0 2>/dev/null)

    # Najít CSS soubory (kromě již minifikovaných)
    while IFS= read -r -d '' file; do
        if [[ ! "$file" == *.min.css ]]; then
            CSS_FILES+=("$file")
        fi
    done < <(find "$PROJECT_ROOT/assets/css" -type f -name "*.css" -print0 2>/dev/null)

    echo "Found ${#JS_FILES[@]} JavaScript file(s)"
    echo "Found ${#CSS_FILES[@]} CSS file(s)"
    echo ""
}

# Update HTML references (volitelné)
update_references() {
    echo ""
    echo "To use minified assets in production, update your HTML files:"
    echo ""
    echo "  <script src=\"assets/js/script.js\"></script>"
    echo "  →"
    echo "  <script src=\"assets/js/script.min.js\"></script>"
    echo ""
    echo "Or use conditional loading based on environment:"
    echo ""
    echo "  <?php \$min = ENVIRONMENT === 'production' ? '.min' : ''; ?>"
    echo "  <script src=\"assets/js/script<?= \$min ?>.js\"></script>"
    echo ""
}

# Main
main() {
    check_tools

    declare -a JS_FILES
    declare -a CSS_FILES

    find_assets

    local total_files=$((${#JS_FILES[@]} + ${#CSS_FILES[@]}))

    if [ $total_files -eq 0 ]; then
        echo -e "${YELLOW}No assets found to minify${NC}"
        exit 0
    fi

    echo "Ready to minify $total_files file(s)"
    echo ""

    read -p "Do you want to proceed? (y/n) " -n 1 -r
    echo ""

    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Cancelled."
        exit 0
    fi

    echo ""
    echo "Minifying assets..."
    echo ""

    local js_success=0
    local js_failed=0

    # Minifikovat JS
    if [ ${#JS_FILES[@]} -gt 0 ]; then
        echo -e "${BLUE}JavaScript files:${NC}"
        for file in "${JS_FILES[@]}"; do
            if minify_js "$file"; then
                js_success=$((js_success + 1))
            else
                js_failed=$((js_failed + 1))
            fi
        done
        echo ""
    fi

    local css_success=0
    local css_failed=0

    # Minifikovat CSS
    if [ ${#CSS_FILES[@]} -gt 0 ]; then
        echo -e "${BLUE}CSS files:${NC}"
        for file in "${CSS_FILES[@]}"; do
            if minify_css "$file"; then
                css_success=$((css_success + 1))
            else
                css_failed=$((css_failed + 1))
            fi
        done
        echo ""
    fi

    echo "=========================================="
    echo -e "${GREEN}Minification completed!${NC}"
    echo "=========================================="
    echo ""
    echo "Summary:"
    echo "  JavaScript: $js_success successful, $js_failed failed"
    echo "  CSS: $css_success successful, $css_failed failed"
    echo ""

    update_references
}

main "$@"
