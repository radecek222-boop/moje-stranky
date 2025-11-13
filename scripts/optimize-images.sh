#!/bin/bash
#
# WGS Service - Image Optimization Script
# Optimalizuje všechny velké obrázky v projektu
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
echo "WGS Service - Image Optimization"
echo "=========================================="
echo ""

# Kontrola dostupnosti nástrojů
check_tools() {
    local missing=0

    echo "Checking for optimization tools..."

    if ! command -v jpegoptim &> /dev/null; then
        echo -e "${YELLOW}⚠ jpegoptim not found${NC}"
        missing=$((missing + 1))
    else
        echo -e "${GREEN}✓ jpegoptim available${NC}"
    fi

    if ! command -v optipng &> /dev/null; then
        echo -e "${YELLOW}⚠ optipng not found${NC}"
        missing=$((missing + 1))
    else
        echo -e "${GREEN}✓ optipng available${NC}"
    fi

    if ! command -v pngquant &> /dev/null; then
        echo -e "${YELLOW}⚠ pngquant not found${NC}"
        missing=$((missing + 1))
    else
        echo -e "${GREEN}✓ pngquant available${NC}"
    fi

    if [ $missing -gt 0 ]; then
        echo ""
        echo -e "${RED}Some tools are missing. Please install them:${NC}"
        echo ""
        echo "Ubuntu/Debian:"
        echo "  sudo apt-get install jpegoptim optipng pngquant"
        echo ""
        echo "macOS:"
        echo "  brew install jpegoptim optipng pngquant"
        echo ""
        echo "CentOS/RHEL:"
        echo "  sudo yum install jpegoptim optipng pngquant"
        echo ""
        exit 1
    fi

    echo ""
}

# Optimalizace JPEG souborů
optimize_jpeg() {
    local file="$1"
    local size_before=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")

    echo -n "  Processing $(basename "$file")... "

    jpegoptim --max=85 --strip-all --preserve --quiet "$file"

    local size_after=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")
    local saved=$((size_before - size_after))
    local percent=$((saved * 100 / size_before))

    if [ $saved -gt 0 ]; then
        echo -e "${GREEN}saved $(numfmt --to=iec-i --suffix=B $saved) ($percent%)${NC}"
    else
        echo -e "${BLUE}already optimized${NC}"
    fi
}

# Optimalizace PNG souborů
optimize_png() {
    local file="$1"
    local size_before=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")

    echo -n "  Processing $(basename "$file")... "

    # Zkusit pngquant pro lepší kompresi (lossy, ale vysoká kvalita)
    if command -v pngquant &> /dev/null; then
        pngquant --quality=80-95 --force --ext=.png "$file" 2>/dev/null || true
    fi

    # Pak optipng pro lossless optimalizaci
    optipng -o5 -quiet -preserve "$file" 2>/dev/null || true

    local size_after=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")
    local saved=$((size_before - size_after))

    if [ $saved -gt 0 ]; then
        local percent=$((saved * 100 / size_before))
        echo -e "${GREEN}saved $(numfmt --to=iec-i --suffix=B $saved) ($percent%)${NC}"
    else
        echo -e "${BLUE}already optimized${NC}"
    fi
}

# Najít všechny velké obrázky (> 500KB)
find_large_images() {
    local threshold=512000  # 500KB v bytech

    echo "Finding images larger than 500KB..."
    echo ""

    # Najít JPEG soubory
    while IFS= read -r -d '' file; do
        local size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")
        if [ "$size" -gt "$threshold" ]; then
            echo -e "${YELLOW}Large JPEG:${NC} $file ($(numfmt --to=iec-i --suffix=B $size))"
            JPEG_FILES+=("$file")
        fi
    done < <(find "$PROJECT_ROOT/assets" "$PROJECT_ROOT/uploads" -type f \( -iname "*.jpg" -o -iname "*.jpeg" \) -print0 2>/dev/null)

    # Najít PNG soubory
    while IFS= read -r -d '' file; do
        local size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file")
        if [ "$size" -gt "$threshold" ]; then
            echo -e "${YELLOW}Large PNG:${NC} $file ($(numfmt --to=iec-i --suffix=B $size))"
            PNG_FILES+=("$file")
        fi
    done < <(find "$PROJECT_ROOT/assets" "$PROJECT_ROOT/uploads" -type f -iname "*.png" -print0 2>/dev/null)

    echo ""
}

# Main
main() {
    check_tools

    declare -a JPEG_FILES
    declare -a PNG_FILES

    find_large_images

    local total_files=$((${#JPEG_FILES[@]} + ${#PNG_FILES[@]}))

    if [ $total_files -eq 0 ]; then
        echo -e "${GREEN}No large images found (all < 500KB)${NC}"
        exit 0
    fi

    echo "Found $total_files large image(s) to optimize"
    echo ""

    read -p "Do you want to optimize these images? (y/n) " -n 1 -r
    echo ""

    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Cancelled."
        exit 0
    fi

    echo ""
    echo "Optimizing images..."
    echo ""

    # Optimalizovat JPEG
    if [ ${#JPEG_FILES[@]} -gt 0 ]; then
        echo -e "${BLUE}JPEG files:${NC}"
        for file in "${JPEG_FILES[@]}"; do
            optimize_jpeg "$file"
        done
        echo ""
    fi

    # Optimalizovat PNG
    if [ ${#PNG_FILES[@]} -gt 0 ]; then
        echo -e "${BLUE}PNG files:${NC}"
        for file in "${PNG_FILES[@]}"; do
            optimize_png "$file"
        done
        echo ""
    fi

    echo "=========================================="
    echo -e "${GREEN}Optimization completed!${NC}"
    echo "=========================================="
    echo ""
    echo "Recommendations:"
    echo "  - Review optimized images visually"
    echo "  - Consider serving WebP format for modern browsers"
    echo "  - Use responsive images (<img srcset>) for different screen sizes"
    echo "  - Enable browser caching in .htaccess"
    echo ""
}

main "$@"
