# phpdot/sheets

Streaming, low-memory XLSX reader and writer for PHP 8.3+. Read and write spreadsheets of **any size in constant memory** — the engine streams each row straight to and from a `zip://` part, so a million-row export costs the same few megabytes as a hundred-row one.

The entire API is **one injectable service and a chain of immutable builders**: inject `Sheets`, call `write()` or `read()`, and decorate the workbook, sheets, rows and cells it hands back. Charts, images, conditional formatting and data validation are always on — no `use()` ceremony, no enum imports, just `$sheet->addChart('bar')`. Reading untrusted uploads is guarded against zip bombs by default. Coroutine-safe under Swoole, `declare(strict_types=1)` throughout, PHPStan level 10.

## Table of Contents

- [Install](#install)
- [Quick Start](#quick-start)
- [Why phpdot/sheets](#why-phpdotsheets)
- [Architecture](#architecture)
- [Writing](#writing)
- [Rows and Cells](#rows-and-cells)
- [Styling](#styling)
- [Layout and Page Setup](#layout-and-page-setup)
- [Datasets](#datasets)
- [Charts](#charts)
- [Images](#images)
- [Conditional Formatting](#conditional-formatting)
- [Data Validation](#data-validation)
- [Reading](#reading)
- [Security](#security)
- [DI Wiring](#di-wiring)
- [Development](#development)
- [License](#license)

## Install

```bash
composer require phpdot/sheets
```

| Requirement | Version |
|---|---|
| PHP | >= 8.3 |
| ext-zip | required |
| ext-mbstring | required |
| ext-xmlreader | required |
| ext-simplexml | required |

## Quick Start

```php
use PHPdot\Sheets\Sheets;

$sheets = new Sheets(); // or inject it — see "DI Wiring"

// ── Write ───────────────────────────────────────────────
$xlsx  = $sheets->write('report.xlsx');
$sheet = $xlsx->addSheet('Sales');

$sheet->header(['Product', 'Units', 'Revenue']);
$sheet->addRow(['Widget', 120, 3600.50]);
$sheet->addRow(['Gadget',  80, 2400.00]);

$xlsx->save();

// ── Read ────────────────────────────────────────────────
$in = $sheets->read('report.xlsx');

foreach ($in->sheet('Sales')->records() as $row) {
    echo "{$row['Product']}: {$row['Revenue']}\n";
}
```

Everything hangs off the object you injected — **`PHPdot\Sheets\Sheets` is the only class you import.**

## Why phpdot/sheets

- **Constant memory, any size.** Rows stream to and from the `zip://` part one at a time. A 1,000,000-row export and a 100-row export use the same handful of megabytes — there is no in-memory document model.
- **One service, everything chained.** Inject `Sheets` and the whole API unfolds from `write()`/`read()`: each `add*()` returns the child you decorate (`addSheet` → sheet, `addRow` → row, `addCell` → cell, `addChart` → chart). No hundred namespaces.
- **Strings, not imports.** Pick types by value — `addChart('bar')`, `align('center')`, `border('thin')`, `format('currency_usd')`. Enums power it underneath, but you never import one.
- **Cells choose their own type.** `addCell('00123')->asText()` keeps the leading zeros; `addCell('=SUM(B:B)')->asFormula()`; dates, bools and inline strings each have their selector. Plain scalar rows just infer.
- **Immutable builders.** Styles and feature builders clone on every call, so a configured `$header` style is a safe, reusable template — and PHPStan flags a builder you forgot to use.
- **Safe with untrusted files.** The reader streams over `zip://`, never extracts to disk (no zip-slip), disables XML external entities (no XXE), and refuses decompression bombs by default.
- **Strict.** `declare(strict_types=1)` throughout, PHPStan level 10 with strict rules, zero ignored errors. Coroutine-safe under Swoole.

## Architecture

```
src/
├── Sheets.php                #[Singleton] #[Binds] — inject this; the only entry point
├── SheetsInterface.php       the contract to depend on
├── Spreadsheet.php           low-level engine entry (advanced; you won't need it)
├── Builder/                  the fluent API you chain through
│   ├── Workbook.php          properties · styles · sheets · save()
│   ├── Sheet.php             rows · layout · charts · images · formatting · validation · datasets
│   ├── Row.php · Cell.php     a decoratable row · a typed cell
│   ├── Dataset.php           fill() / iterate() over arrays, generators, cursors
│   ├── Chart.php · Image.php · Condition.php · FillRule.php · Rule.php   feature builders
│   └── ReadWorkbook.php · ReadSheet.php · ReadDataset.php                the read side
└── Engine/                   the streaming XLSX engine — you rarely touch this
    ├── Xlsx/                 Writer · Reader · zip packaging
    ├── Model/                cells · styles · options · enums · contracts
    ├── Feature/              charts · images · conditional formatting · data validation
    └── Support/              column refs · Excel dates · exceptions
```

Flow: `Sheets::write()` hands you a `Builder\Workbook`; as you add rows, `Engine\Xlsx\Writer` streams each one straight into the `zip://` part. `Sheets::read()` hands you a `Builder\ReadWorkbook`; `Engine\Xlsx\Reader` streams rows back through `XMLReader`. The Builder layer is a thin, immutable façade — the Engine does the work, and you almost never name it.

## Writing

A workbook is created with `write()`, carries optional document properties, opens sheets with `addSheet()`, and is finalized with `save()`.

```php
$xlsx = $sheets->write('report.xlsx')
    ->creator('phpdot')
    ->title('Q2 Sales');

$sheet = $xlsx->addSheet('Sales');

$sheet->header(['Product', 'Units', 'Revenue']);   // a header row (optionally styled)
$sheet->addRow(['Widget', 120, 3600.50]);          // fast path: a plain array of scalars

$xlsx->save();
```

| `Workbook` | Returns | |
|---|---|---|
| `creator` · `title` · `subject` · `keywords` · `description` · `category` | `self` | Document properties (set before the first sheet). |
| `style()` | `Style` | A fresh style to chain on — see [Styling](#styling). |
| `name(string $name, string $formula)` | `self` | Define a workbook-level named range. |
| `addSheet(string $name)` | `Sheet` | Open a new sheet (name validated eagerly). |
| `save()` | `void` | Finalize features, write trailers, zip the file. |

Sheet names are validated when you add them (≤31 chars, no `:\/?*[]`, case-insensitively unique), so a bad name throws right away rather than at `save()`. Pass `write($path, sharedStrings: true)` to deduplicate repeated strings into a shared table.

## Rows and Cells

`addRow([...scalars])` is the fast path — zero objects per row. It returns a `Row` you can decorate, and `addCell()` appends cells that pick their own type, format and style.

```php
// Fast path — a plain row of scalars (string, int, float, bool, null, DateTimeInterface):
$sheet->addRow(['Alice', 30, true]);

// Decorate the row, or append typed cells:
$row = $sheet->addRow(['Total']);
$row->addCell('=SUM(C2:C9)')->asFormula();
$row->addCell(0.2)->format('percent');
$row->height(22);

// A cell picks its own type — keep leading zeros, force a date, etc.:
$row = $sheet->addRow([]);
$row->addCell('00123')->asText();
$row->addCell('2024-06-01')->asDate()->format('date');
```

| `Cell` type selector | Result |
|---|---|
| `asText()` | Force text (preserves leading zeros, long digit strings). |
| `asNumber()` · `asBool()` | Numeric / boolean cell. |
| `asDate()` | Date cell (pair with `->format('date')`). |
| `asFormula()` | Formula cell — `=SUM(...)`; the `{row}` token expands to the current row number. |
| `asError()` · `asInline()` | Error literal / inline string. |

A cell also takes `->style(...)` and `->format(...)`; a row takes `->style(...)`, `->height(float)`, `->hide()`. `currentRow()` gives the next row number, and `cellRef('D1')` / `colRef('C', 2, 9)` produce sheet-qualified absolute references for formulas and charts.

## Styling

Styles come from `$xlsx->style()` and are immutable — every verb returns a new style, so build a base and branch from it.

```php
$base   = $xlsx->style()->fontName('Inter');
$header = $base->bold()->fontColor('FFFFFF')->background('2563EB')->align('center');
$money  = $xlsx->style()->numberFormat('currency_usd')->align('right');

$sheet->header(['Product', 'Units', 'Revenue'], $header);   // style the whole header row

$row = $sheet->addRow(['Widget', 120]);
$row->addCell(3600.50)->style($money);                      // style one cell
```

| `Style` (from `$xlsx->style()`) | |
|---|---|
| `bold()` · `italic()` · `underline()` | font weight / style |
| `fontSize(float)` · `fontName(string)` | font |
| `fontColor($hex)` · `background($hex)` | colors — hex string (`'2563EB'`) or a `Color` |
| `numberFormat($preset\|$code)` | e.g. `'currency_usd'`, `'percent'`, or a raw `'0.00'` |
| `align('center')` · `valign('top')` | `left·center·right·fill·justify` / `top·center·bottom` |
| `wrap()` | wrap text |
| `border('thin', $hex?)` | `thin·medium·thick·dashed·dotted·double` |

Number-format presets: `integer`, `decimal`, `currency_usd`, `currency_eur`, `accounting`, `percent`, `scientific`, `date`, `date_us`, `date_time`, `time` — or pass any raw Excel format code.

## Layout and Page Setup

```php
$sheet->freezeRows(1)->freezeColumns(1);   // freeze panes
$sheet->autoSize();                        // auto-fit column widths
$sheet->widths([1 => 32, 3 => 14]);        // column index => width
$sheet->merge('A1:C1');
$sheet->withoutGridlines();
$sheet->tabColor('2563EB');
$sheet->filter('A1:E1');                   // auto-filter

// Cell extras
$sheet->link('A5', 'https://phpdot.com', 'Visit phpdot');
$sheet->comment('B2', 'Needs review', 'omar');

// Print / page setup
$sheet->landscape()->fitToWidth(1);
$sheet->printArea('A1:E50');
$sheet->repeatRows(1);                      // repeat the header on every printed page
$sheet->pageHeader('Q2 Sales')->pageFooter('Confidential');

// Protection
$sheet->protect('secret');
```

These return `$sheet`, so they chain. `widths` is `array<int, float>` (1-based column index → width).

## Datasets

The everyday case — turn an array, generator or database cursor into rows. `fill()` is the zero-config one-liner; `iterate()` returns a builder for columns, casts, formats and a styled header. Either way the source is **streamed in O(1) memory**.

```php
// Zero-config: header from the array keys, one row per item.
$sheet->fill($users);

// Configurable — stream any iterable in constant memory:
$sheet->iterate($db->cursor('SELECT id, name, email, created_at, active FROM users'))
    ->columns([
        'id'         => 'ID',
        'name'       => 'Name',
        'email'      => 'Email',
        'created_at' => 'Joined',
        'active'     => 'Status',
    ])
    ->cast('created_at', fn ($v) => new DateTimeImmutable($v))
    ->cast('active', fn ($v) => $v ? 'Active' : 'Disabled')
    ->format(['created_at' => 'date'])
    ->headerStyle($header)
    ->write();
```

| `Dataset` (from `iterate()`) | |
|---|---|
| `columns(array $map)` | Select and order columns; `key => label` builds the header. |
| `cast($field, callable)` | Transform one field's value per row. |
| `format(array $map)` | Number format per column (`field => preset\|code`). |
| `map(callable)` | Transform the whole row array. |
| `headerStyle(Style)` | Style the header row. |
| `startAt(string $cell)` | Anchor the table (default `A1`). |
| `write()` | Stream it out. |

`fill($rows)` is exactly `iterate($rows)->write()`.

## Charts

```php
$sheet->addChart('bar')
    ->title('Revenue by Product')
    ->labels('Sales!$A$2:$A$5')
    ->series('Sales!$C$2:$C$5', name: 'Revenue', color: '2563EB')
    ->legend('r')                 // r · l · t · b
    ->at('E2', [480, 300]);       // anchor cell, [width, height] in px
```

`addChart()` registers the chart on the sheet and returns it to decorate — nothing to re-add. Types: `bar`, `barHorizontal`, `line`, `pie`, `area`, `doughnut`, `scatter`. Add multiple `series()`, set `xTitle()`/`yTitle()`, `dataLabels(...)`, and `stacked()` / `stacked100()`.

## Images

```php
$sheet->addImage('logo.png')->at('A1', [120, 40]);
$sheet->addImage($pngBytes, 'png')->at('E1');   // path or raw binary
```

## Conditional Formatting

```php
// Compare against a value, then fill:
$sheet->highlight('C2:C100')->greaterThan(1000)
      ->fill($xlsx->style()->background('DCFCE7'));

// Rule-based:
$sheet->duplicates('A2:A100')->fill($xlsx->style()->background('FEE2E2'));
$sheet->uniques('A2:A100')->fill($ok);
$sheet->expression('A2:A100', '=$B2>$C2')->fill($warn);

// Visual scales (one call each):
$sheet->dataBars('D2:D100', '60A5FA');
$sheet->colorScale('E2:E100', 'F87171', '4ADE80');   // from → to (+ optional mid)
$sheet->iconSet('F2:F100', '3arrows');
```

`highlight()` returns a comparison builder (`greaterThan`, `lessThan`, `between`, `equal`, …) terminated by `fill(Style)`. `duplicates()` / `uniques()` / `expression()` return a fill rule. `dataBars()` / `colorScale()` / `iconSet()` apply directly.

## Data Validation

```php
// Dropdowns:
$sheet->dropdown('B2:B100', ['Low', 'Medium', 'High']);
$sheet->dropdownFrom('C2:C100', 'Lists!$A$1:$A$10');

// Constraints, with optional prompt / error popups:
$sheet->validate('D2:D100')->wholeNumber()->between(1, 100)
      ->error('Out of range', 'Enter a whole number from 1 to 100.');

$sheet->validate('E2:E100')->date()->after('2024-01-01')
      ->prompt('Start date', 'Pick a date in 2024 or later.');
```

`validate()` chains a type (`wholeNumber`, `decimal`, `date`, `time`, `textLength`, `custom`), a constraint (`between`, `equal`, `greaterThan`, `after`, …), and optional `prompt()` / `error()` / `required()`.

## Reading

```php
$in = $sheets->read('report.xlsx');

$in->sheetNames();                 // ['Sales', ...]
$sheet = $in->sheet('Sales');      // by name or index

// Raw values, streamed row by row:
foreach ($sheet->values() as $rowNumber => $values) {
    [$product, $units, $revenue] = $values;
}

// Header-keyed records (first row supplies the keys):
foreach ($sheet->records() as $record) {
    echo $record['Product'];
}

// Typed cells with predicates — no enum imports:
foreach ($sheet->rows() as $cells) {
    foreach ($cells as $cell) {
        $cell->value;            // int|float|string|bool|null
        $cell->type();           // 'string' 'number' 'date' 'bool' 'formula' 'inline' 'error'
        if ($cell->isDate()) {
            $when = $cell->toDateTime();   // ?DateTimeImmutable
        }
    }
}
```

| `ReadSheet` | Returns |
|---|---|
| `values()` | `iterable` — rows of raw scalar values |
| `rows()` | `iterable` — rows of typed `Cell` objects |
| `records()` | `Generator` — header-keyed associative arrays |
| `iterate()` | `ReadDataset` — `columns()` / `cast()` / `map()` / `records()` |
| `mergedCells()` · `widths()` · `links()` · `comments()` · `formulas()` | sheet metadata |
| `styleOf(Cell)` | the `?Style` of a read cell |

Reading streams too — iterate a million-row sheet without loading it into memory.

## Security

Reading a file you did not create is hostile-input territory, and the reader is built for it — by default, no configuration:

```php
$in = $sheets->read($uploadedPath);   // throws ReadException on a zip bomb
```

| Threat | Handling |
|---|---|
| XXE / SSRF | XML external entities disabled (`LIBXML_NONET`, never `LIBXML_NOENT`). |
| Zip-slip | Never extracts to disk — entries are read by exact name over `zip://`. |
| Zip bomb | Every part is ratio-checked from the central directory **before a byte is decompressed**; whole-read parts (styles, rels) are size-capped; the shared-string table is budgeted. |

A bomb is told apart from a legitimately huge file by **compression ratio, not size**: real spreadsheet XML compresses to ≤~50:1, so the default 200:1 gate trips only on an attack while a million-row export passes untouched. The limits live on `ReadOptions` and can be raised or disabled for files you fully trust.

## DI Wiring

```php
use PHPdot\Sheets\SheetsInterface;

final class ReportController
{
    public function __construct(private SheetsInterface $sheets) {}

    public function export(string $path): void
    {
        $xlsx  = $this->sheets->write($path);
        $sheet = $xlsx->addSheet('Report');
        // ...
        $xlsx->save();
    }
}
```

`Sheets` is `#[Singleton]` and `#[Binds(SheetsInterface::class)]` — it autowires with nothing to register; depend on `SheetsInterface` or the concrete `Sheets`. It is stateless (each `write()` / `read()` returns a fresh per-operation object), so the single shared instance is **coroutine-safe** under Swoole.

## Development

```bash
composer test      # PHPUnit
composer analyse   # PHPStan level 10, strict rules
composer cs-check  # php-cs-fixer dry run
composer check     # all three
```

## License

MIT — see [LICENSE](LICENSE).
