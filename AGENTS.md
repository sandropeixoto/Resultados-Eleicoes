# AGENTS.md - Data Warehouse Eleitoral Rules

## System Architecture
Stack: Native PHP (no frameworks/composer), Vanilla JS (ES6+), Vanilla CSS, Dual DB (Remote MySQL srv24.prodns.com.br + Local SQLite db/eleicoes_fallback.sqlite fallback).
Root: public/ (server docroot).
Single Source Table: `resultados_votacao`. NEVER use or create `election_records`.

## Critical Mirror Rule
ANY edit to `public/api/<file>.php` MUST be replicated in root `api/<file>.php`. Adjust relative require paths (`__DIR__ . '/../../config/...'` vs `__DIR__ . '/../config/...'`).

## Core Commands
- Dev Server: `php -d upload_max_filesize=100M -d post_max_size=100M -d memory_limit=512M -S localhost:8080 -t public`
- Lint Single File: `php -l public/api/dashboard.php`
- Lint All PHP (PowerShell): `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`
- Seed DB: `php generate_sample.php`
- Test Dashboard: `curl.exe -i -s "http://localhost:8080/api/dashboard.php?ano=2014&partido=PT&q=DILMA"`
- Test Autocomplete: `curl.exe -i -s "http://localhost:8080/api/candidates.php?q=DILMA&limit=10"`
- Test Compare: `curl.exe -i -s "http://localhost:8080/api/compare.php?cand_a=ID1&cand_b=ID2"`
- Test CSV Import: `curl.exe -i -s -F "csv_file=@exemplo.csv" http://localhost:8080/api/import.php`

## PHP Rules
1. Always use `<?php` opening tags.
2. UTF-8 Headers: `header('Content-Type: application/json; charset=utf-8');`. Convert Latin-1 via `mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1')`. Use `mb_strtolower($val, 'UTF-8')`.
3. NEVER use `$val ?: $default` on numeric/text fields where `"0"` is valid. Use `$val !== '' ? $val : $default`.
4. SQL compatibility: Strict mode (`ONLY_FULL_GROUP_BY`) & SQLite compatible. Include non-aggregated SELECT columns in GROUP BY.
5. MySQL batch upsert: Use `ON DUPLICATE KEY UPDATE col = VALUES(col)` for MySQL 5.7+ compatibility.
6. Batch imports: Accumulate buffers (e.g. 500 rows) into multi-row INSERT statements.
7. Candidate IDs: Use `generateElectionId($uf, $muni, $ano, $turno, $cargo, $cand, $rowIndex)`. Slugs transliterated via `transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $muni)`.
8. Cache: Call `Cache::clear()` on any CSV import or database seed.

## JS Rules (ES6+ Vanilla)
1. No build step/transpilers. Use `async/await` with `fetch()`. Wrap in `try/catch`.
2. TomSelect: ALWAYS destroy prior instances (`if (el.tomselect) el.tomselect.destroy();`) before re-initializing.
3. Chart.js: Destroy global chart instances (`chart.destroy()`) before creating replacement charts.
4. DOM Rendering: NEVER loop `innerHTML +=` ($O(N^2)$). Use `.map().join('')` or `DocumentFragment`.
5. Inputs: 300ms debounce on search inputs calling APIs.

## CSS Rules
- Style location: `public/css/style.css`. Dark theme `#0b0f19`, JetBrains Mono / Inter typography.

## Pre-commit Verification
1. Run `php -l` on all modified files.
2. Confirm `public/api/` and `api/` endpoints are 100% synchronized.
3. Verify local dev server runs clean and curl tests return 200 OK.
