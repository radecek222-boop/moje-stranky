#!/usr/bin/env python3
import re
import sys

def minify_js(input_file, output_file):
    with open(input_file, 'r', encoding='utf-8') as f:
        content = f.read()

    # Odstranit jednořádkové komentáře (ale zachovat URLs)
    content = re.sub(r'(?<!:)//(?!/).*?$', '', content, flags=re.MULTILINE)

    # Odstranit víceřádkové komentáře
    content = re.sub(r'/\*.*?\*/', '', content, flags=re.DOTALL)

    # Odstranit prázdné řádky
    content = re.sub(r'\n\s*\n', '\n', content)

    # Odstranit leading/trailing whitespace z řádků
    lines = [line.strip() for line in content.split('\n') if line.strip()]

    # Spojit do jednoho řádku
    minified = ''.join(lines)

    with open(output_file, 'w', encoding='utf-8') as f:
        f.write(minified)

    print(f"✅ Minifikováno: {input_file} → {output_file}")

if __name__ == '__main__':
    if len(sys.argv) != 3:
        print("Usage: python3 minify.py input.js output.min.js")
        sys.exit(1)

    minify_js(sys.argv[1], sys.argv[2])
