/**
 * lucide-vue-next-ийн бүх icon-ыг зурах өгөгдөл болгож public/icons/lucide.json
 * файлд бэлдэнэ. Ингэснээр icon сонгогч бүх icon-ыг харуулж чадах атлаа
 * JS багц томрохгүй — хэрэгтэй үед л сүлжээгээр татагдана.
 */
import { readdirSync, readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const iconsDir = join(root, 'node_modules/lucide-vue-next/dist/esm/icons');
const outFile = join(root, 'public/icons/lucide.json');

const icons = {};

for (const file of readdirSync(iconsDir)) {
    if (! file.endsWith('.js') || file.endsWith('.map')) continue;

    const source = readFileSync(join(iconsDir, file), 'utf8');
    const match = source.match(/createLucideIcon\(\s*"([^"]+)"\s*,\s*(\[[\s\S]*?\])\s*\);/);

    if (! match) continue;

    const [, name, literal] = match;

    // Build-time дээр өөрсдийн node_modules-ийн эх кодыг уншиж байгаа тул аюулгүй
    const nodes = new Function(`return ${literal}`)();

    // key нь зөвхөн framework-ийн дотоод хэрэгцээ — хасаж хэмжээг багасгана
    icons[name] = nodes.map(([tag, attrs]) => {
        const { key, ...rest } = attrs;

        return [tag, rest];
    });
}

mkdirSync(dirname(outFile), { recursive: true });
writeFileSync(outFile, JSON.stringify(icons));

const kb = (Buffer.byteLength(JSON.stringify(icons)) / 1024).toFixed(0);
console.log(`lucide.json: ${Object.keys(icons).length} icon, ${kb} KB`);
