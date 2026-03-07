#!/usr/bin/env node
/**
 * build.js - Minifikace JS a CSS souborů pomocí esbuild
 *
 * Spuštění: node scripts/build.js
 * nebo:     npm run build
 */

import { build } from 'esbuild';
import { readdirSync } from 'fs';
import { join, basename } from 'path';

const JS_DIR = 'assets/js';
const CSS_DIR = 'assets/css';

// Najde všechny zdrojové .js soubory (ne .min.js)
function najdiZdrojoveJs(adresar) {
  return readdirSync(adresar)
    .filter(f => f.endsWith('.js') && !f.endsWith('.min.js'))
    .map(f => join(adresar, f));
}

// Najde všechny zdrojové .css soubory (ne .min.css)
function najdiZdrojoveCss(adresar) {
  return readdirSync(adresar)
    .filter(f => f.endsWith('.css') && !f.endsWith('.min.css'))
    .map(f => join(adresar, f));
}

async function buildJs() {
  const soubory = najdiZdrojoveJs(JS_DIR);
  console.log(`[JS] Minifikuji ${soubory.length} souborů...`);

  await build({
    entryPoints: soubory,
    bundle: false,        // Nespojovat - každý soubor zůstává samostatný
    minify: true,
    sourcemap: true,
    outdir: JS_DIR,
    entryNames: '[name].min',
    logLevel: 'warning',
    target: ['es2020'],   // es2020+ kvůli BigInt literálům (97n, 98n) v QR platbách
  });

  console.log(`[JS] Hotovo: ${soubory.length} souborů → .min.js`);
}

async function buildCss() {
  const soubory = najdiZdrojoveCss(CSS_DIR);
  // Přeskočit speciální CSS soubory
  const vynechat = ['poppins-font.css'];
  const kMinifikovat = soubory.filter(f => !vynechat.includes(basename(f)));

  console.log(`[CSS] Minifikuji ${kMinifikovat.length} souborů...`);

  await build({
    entryPoints: kMinifikovat,
    bundle: false,
    minify: true,
    outdir: CSS_DIR,
    entryNames: '[name].min',
    logLevel: 'warning',
  });

  console.log(`[CSS] Hotovo: ${kMinifikovat.length} souborů → .min.css`);
}

async function hlavni() {
  const zacatek = Date.now();

  try {
    await Promise.all([buildJs(), buildCss()]);
    const cas = ((Date.now() - zacatek) / 1000).toFixed(1);
    console.log(`\nBuild dokončen za ${cas}s`);
  } catch (chyba) {
    console.error('Chyba při buildu:', chyba.message);
    process.exit(1);
  }
}

hlavni();
