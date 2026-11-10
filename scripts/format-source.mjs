#!/usr/bin/env node
/**
 * TTE-Ticketing source formatter.
 * Pure inspection by default; --write explicitly changes source files.
 * PHP/Blade/JS/CSS: Prettier. Standalone .sql: SQL Formatter.
 * Never walks vendor, storage, generated assets or patch backups.
 */
import { promises as fs } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as prettier from 'prettier';

const project = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const excludedDirectories = new Set([
    '.git',
    '.ams-v16-backup',
    '.tte-patch-backups',
    '.format-backups',
    '.idea',
    '.vscode',
    'node_modules',
    'vendor',
    'storage',
    'coverage',
    '.next',
    '.nuxt',
    'dist',
    'build',
]);
const excludedPaths = new Set(['bootstrap/cache', 'public/build', 'public/storage']);
const selectors = new Set(['php', 'blade', 'js', 'css', 'sql']);
const args = process.argv.slice(2);
const write = args.includes('--write');
const check = args.includes('--check') || !write;
const selectedIndex = args.indexOf('--only');
const selected = selectedIndex >= 0 ? args[selectedIndex + 1] : null;
const sqlArg = args.find((arg) => arg.startsWith('--sql-dialect='));
const sqlDialect = sqlArg ? sqlArg.split('=')[1] : 'sqlite';
const supportedDialects = new Set(['sqlite', 'mysql', 'mariadb', 'postgresql', 'sql']);

if (
    (write && args.includes('--check')) ||
    (selected && !selectors.has(selected)) ||
    (selectedIndex >= 0 && !selected) ||
    !supportedDialects.has(sqlDialect) ||
    args.some(
        (arg, i) =>
            !['--write', '--check', '--only'].includes(arg) &&
            !arg.startsWith('--sql-dialect=') &&
            !(i === selectedIndex + 1),
    )
) {
    console.error(
        'Penggunaan: node scripts/format-source.mjs [--check | --write] [--only php|blade|js|css|sql] [--sql-dialect=sqlite|mysql|mariadb|postgresql|sql]',
    );
    process.exit(2);
}

const category = (name) => {
    if (name.endsWith('.blade.php')) return 'blade';
    if (name.endsWith('.php')) return 'php';
    if (/\.(?:mjs|cjs|js)$/u.test(name)) return 'js';
    if (name.endsWith('.css')) return 'css';
    if (name.endsWith('.sql')) return 'sql';
    return null;
};

const files = [];
async function visit(dir = project) {
    for (const entry of await fs.readdir(dir, { withFileTypes: true })) {
        if (entry.isSymbolicLink()) continue;
        const full = path.join(dir, entry.name);
        const relative = path.relative(project, full).split(path.sep).join('/');
        if (entry.isDirectory()) {
            if (excludedDirectories.has(entry.name) || excludedPaths.has(relative)) continue;
            await visit(full);
        } else if (entry.isFile() && category(entry.name)) {
            if (/\.min\.(?:js|css)$/u.test(entry.name)) continue;
            if (selected && selected !== category(entry.name)) continue;
            files.push({ full, relative, language: category(entry.name) });
        }
    }
}

// Keep no-attribute inline HTML opening tags on one line.
// Prettier Blade can emit `<strong\n  >` for a tag containing dynamic Blade text.
// Limit normalization to opening tags with ZERO attributes, and leave literal
// JS/CSS, HTML comments, and Blade comments untouched.
const inlineOpeningTag = /<(strong|em|b|i|span|small|code|mark)[ \t]*\r?\n[ \t]*>/gi;
const bladeProtectedBlock = /(<script\b[^>]*>[\s\S]*?<\/script\s*>|<style\b[^>]*>[\s\S]*?<\/style\s*>|<textarea\b[^>]*>[\s\S]*?<\/textarea\s*>|\{\{--[\s\S]*?--\}\}|<!--[\s\S]*?-->)/gi;
function compactBladeInlineOpeningTags(source) {
    return source.split(bladeProtectedBlock).map((part, index) =>
        index % 2 === 1 ? part : part.replace(inlineOpeningTag, '<$1>')
    ).join('');
}

let sqlFormatter;
async function formatFile(file, original) {
    if (file.language === 'sql') {
        if (!sqlFormatter) {
            try {
                sqlFormatter = (await import('sql-formatter')).format;
            } catch {
                throw new Error(
                    'sql-formatter belum terpasang. Jalankan npm install --ignore-scripts.',
                );
            }
        }
        const output = sqlFormatter(original, {
            language: sqlDialect,
            tabWidth: 4,
            keywordCase: 'upper',
            linesBetweenQueries: 2,
        });
        return output.replace(/\s*$/u, '') + '\n';
    }
    const config = await prettier.resolveConfig(file.full, { editorconfig: true });
    const formatted = await prettier.format(original, { ...config, filepath: file.full });
    return file.language === 'blade' ? compactBladeInlineOpeningTags(formatted) : formatted;
}

async function main() {
    await visit();
    files.sort((a, b) => a.relative.localeCompare(b.relative));
    const changes = [];
    const errors = [];
    for (const file of files) {
        try {
            const before = await fs.readFile(file.full, 'utf8');
            const after = await formatFile(file, before);
            if (before !== after) changes.push({ ...file, after });
        } catch (error) {
            errors.push(`${file.relative}: ${error?.message || error}`);
        }
    }
    console.log(
        `Diperiksa: ${files.length} berkas; perlu format: ${changes.length}; gagal diparse: ${errors.length}`,
    );
    for (const file of changes) console.log(`  ${write ? 'FORMAT' : 'DIFF'} ${file.relative}`);
    for (const error of errors) console.error('  ERROR ' + error);
    if (errors.length) {
        console.error('Tidak ada berkas yang diubah karena ditemukan error parsing/formatting.');
        process.exitCode = 2;
        return;
    }
    if (check) {
        if (changes.length) {
            console.log(
                'Gunakan npm run format untuk menerapkan perubahan setelah meninjau scope.',
            );
            process.exitCode = 1;
        }
        return;
    }
    // Keep a recoverable source copy of every changed file before a mass write.
    // This folder is excluded by the formatter and should not be committed.
    const backup = path.join(
        project,
        '.format-backups',
        new Date().toISOString().replace(/[:.]/gu, '-'),
    );
    await fs.mkdir(backup, { recursive: true });
    for (const file of changes) {
        const original = path.join(project, file.relative);
        const copy = path.join(backup, file.relative);
        await fs.mkdir(path.dirname(copy), { recursive: true });
        await fs.copyFile(original, copy);
    }
    const written = [];
    try {
        for (const file of changes) {
            await fs.writeFile(file.full, file.after, 'utf8');
            written.push(file);
        }
    } catch (error) {
        for (const file of written.reverse()) {
            await fs.copyFile(path.join(backup, file.relative), file.full);
        }
        throw error;
    }
    console.log(`Selesai. Backup asli: ${path.relative(project, backup)}`);
    console.log(
        'Periksa git diff, php -l untuk PHP, dan jalankan php artisan test pada lingkungan pengujian.',
    );
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 2;
});
