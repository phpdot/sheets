<?php

declare(strict_types=1);

/**
 * Streaming XLSX reader.
 *
 * Worksheets are resolved through `xl/_rels/workbook.xml.rels` (never by
 * positional file name), worksheet bodies are streamed via `XMLReader` over the
 * `zip://` wrapper (O(1) memory), and shared strings are indexed once up front
 * (no per-cell re-scan). Formula cells return their cached `<v>` value when
 * present, otherwise the formula expression itself.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Xlsx;

use PHPdot\Sheets\Engine\Model\Cell;
use PHPdot\Sheets\Engine\Model\CellType;
use PHPdot\Sheets\Engine\Model\ReaderInterface;
use PHPdot\Sheets\Engine\Model\ReadOptions;
use PHPdot\Sheets\Engine\Model\SheetInfo;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Support\ColumnRef;
use PHPdot\Sheets\Engine\Support\ExcelDate;
use PHPdot\Sheets\Engine\Support\ReadException;

final class Reader implements ReaderInterface
{
    private const RELATIONSHIPS_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private readonly string $path;
    private readonly bool $skipEmptyRows;
    private readonly int $maxCompressionRatio;
    private readonly int $ratioFloorBytes;
    private readonly int $maxWholeReadBytes;
    private readonly int $maxSharedStringBytes;
    private bool $closed = false;

    /**
     * @var list<string> Shared strings indexed by position.
     */

    private array $sharedStrings = [];

    /**
     * @var array<int, string> Sheet index => worksheet part path.
     */

    private array $sheetParts = [];

    /**
     * @var list<SheetInfo>
     */

    private array $sheetInfos = [];

    private ?string $stylesXml = null;

    /**
     * @var array<int, Style>|null Lazily parsed cell styles.
     */

    private ?array $styles = null;

    /**
     * @var array<int, bool>|null Lazily computed: cellXfs index => carries a date number format.
     */

    private ?array $dateStyles = null;

    /**
     * Whether the workbook uses the legacy Mac 1904 date system.
     */

    private bool $date1904 = false;

    /**
     * __construct.
     *
     * @param string $path
     * @param ?ReadOptions $options
     */
    public function __construct(string $path, ?ReadOptions $options = null)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new ReadException(sprintf('File not found or not readable: %s', $path));
        }

        $real = realpath($path);
        if ($real === false) {
            throw new ReadException(sprintf('Cannot resolve path: %s', $path));
        }

        $this->path = $real;
        $options ??= new ReadOptions();
        $this->skipEmptyRows = $options->skipEmptyRows;
        $this->maxCompressionRatio = $options->maxCompressionRatio;
        $this->ratioFloorBytes = $options->ratioFloorBytes;
        $this->maxWholeReadBytes = $options->maxWholeReadBytes;
        $this->maxSharedStringBytes = $options->maxSharedStringBytes;

        $zip = new \ZipArchive();
        if ($zip->open($this->path) !== true) {
            throw new ReadException(sprintf('Not a valid XLSX archive: %s', $path));
        }

        try {
            $this->guardArchive($zip);
            $this->loadSheets($zip);
            $this->loadSharedStrings($zip);
            $this->stylesXml = $this->readWholeOrNull($zip, 'xl/styles.xml');
        } finally {
            $zip->close();
        }
    }

    public function sheets(): array
    {
        return $this->sheetInfos;
    }

    public function rows(?int $sheetIndex = null): iterable
    {
        if ($this->closed) {
            throw new ReadException('Reader is closed.');
        }

        $index = $sheetIndex ?? 0;
        if (isset($this->sheetParts[$index])) {
            yield from $this->streamWorksheet($this->sheetParts[$index]);
        }
    }

    public function values(?int $sheetIndex = null): iterable
    {
        foreach ($this->rows($sheetIndex) as $rowNumber => $cells) {
            $row = [];
            foreach ($cells as $cell) {
                $row[] = $cell->value;
            }
            yield $rowNumber => $row;
        }
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function style(?int $styleId): ?Style
    {
        if ($styleId === null || $styleId === 0) {
            return null;
        }

        if ($this->styles === null) {
            $this->styles = $this->stylesXml !== null ? (new StyleReader())->parse($this->stylesXml) : [];
        }

        return $this->styles[$styleId] ?? null;
    }

    public function mergedCells(?int $sheetIndex = null): array
    {
        if ($this->closed) {
            throw new ReadException('Reader is closed.');
        }

        $reader = $this->openWorksheet($sheetIndex);
        if ($reader === null) {
            return [];
        }

        $refs = [];
        try {
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'mergeCell') {
                    $ref = $reader->getAttribute('ref');
                    if ($ref !== null && $ref !== '') {
                        $refs[] = $ref;
                    }
                }
            }
        } finally {
            $reader->close();
        }

        return $refs;
    }

    /**
     * @return array<int, float> 1-based column index => width
     */
    public function columnWidths(?int $sheetIndex = null): array
    {
        $reader = $this->openWorksheet($sheetIndex);
        if ($reader === null) {
            return [];
        }

        $widths = [];
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }
                if ($reader->localName === 'sheetData') {
                    break;
                }
                if ($reader->localName === 'col') {
                    $width = $reader->getAttribute('width');
                    $min = (int) ($reader->getAttribute('min') ?? '0');
                    $max = (int) ($reader->getAttribute('max') ?? '0');
                    if ($width !== null && $min >= 1) {
                        for ($column = $min; $column <= $max && $column <= 16384; $column++) {
                            $widths[$column] = (float) $width;
                        }
                    }
                }
            }
        } finally {
            $reader->close();
        }

        return $widths;
    }

    /**
     * @return array<string, string> cell reference => external URL
     */
    public function hyperlinks(?int $sheetIndex = null): array
    {
        $part = $this->worksheetPart($sheetIndex);
        if ($part === null) {
            return [];
        }

        $reader = $this->openPart($part);

        $links = [];
        try {
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'hyperlink') {
                    $ref = $reader->getAttribute('ref');
                    $rId = $reader->getAttributeNs('id', self::RELATIONSHIPS_NS);
                    if ($ref !== null && $ref !== '' && $rId !== null) {
                        $links[$ref] = $rId;
                    }
                }
            }
        } finally {
            $reader->close();
        }
        if ($links === []) {
            return [];
        }

        $targets = [];
        foreach ($this->relationships($part) as $relationship) {
            $targets[$relationship['id']] = $relationship['target'];
        }

        $result = [];
        foreach ($links as $ref => $rId) {
            if (isset($targets[$rId])) {
                $result[$ref] = $targets[$rId];
            }
        }

        return $result;
    }

    /**
     * @return array<string, string> cell reference => comment text
     */
    public function comments(?int $sheetIndex = null): array
    {
        $part = $this->worksheetPart($sheetIndex);
        if ($part === null) {
            return [];
        }

        $target = null;
        foreach ($this->relationships($part) as $relationship) {
            if (str_ends_with($relationship['type'], '/comments')) {
                $target = $relationship['target'];
                break;
            }
        }
        if ($target === null) {
            return [];
        }

        $xml = $this->readMemberOrNull($this->resolveTarget(dirname($part), $target));
        if ($xml === null) {
            return [];
        }
        $root = @simplexml_load_string($xml, \SimpleXMLElement::class, \LIBXML_NONET);
        if ($root === false || !isset($root->commentList->comment)) {
            return [];
        }

        $result = [];
        foreach ($root->commentList->comment as $comment) {
            $ref = (string) $comment['ref'];
            if ($ref !== '' && isset($comment->text)) {
                $result[$ref] = $this->richText($comment->text);
            }
        }

        return $result;
    }

    /**
     * @return array<string, string> cell reference => formula expression
     */
    public function formulas(?int $sheetIndex = null): array
    {
        $reader = $this->openWorksheet($sheetIndex);
        if ($reader === null) {
            return [];
        }

        $formulas = [];
        $ref = '';
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }
                if ($reader->localName === 'c') {
                    $ref = $reader->getAttribute('r') ?? '';
                } elseif ($reader->localName === 'f' && $ref !== '' && !$reader->isEmptyElement) {
                    if ($reader->read() && $reader->nodeType === \XMLReader::TEXT && $reader->value !== '') {
                        $formulas[$ref] = $reader->value;
                    }
                }
            }
        } finally {
            $reader->close();
        }

        return $formulas;
    }

    /**
     * Returns the package path of the given sheet (or the first), or null when out of range.
     *
     * @param ?int $sheetIndex
     *
     * @return ?string
     */
    private function worksheetPart(?int $sheetIndex): ?string
    {
        if ($this->closed) {
            throw new ReadException('Reader is closed.');
        }

        return $this->sheetParts[$sheetIndex ?? 0] ?? null;
    }

    /**
     * Opens a streaming XMLReader over the given sheet's worksheet part, or null when absent.
     *
     * @param ?int $sheetIndex
     *
     * @return ?\XMLReader
     */
    private function openWorksheet(?int $sheetIndex): ?\XMLReader
    {
        $part = $this->worksheetPart($sheetIndex);
        if ($part === null) {
            return null;
        }

        return $this->openPart($part);
    }

    /**
     * Open a package part the workbook explicitly references. Failure here is a
     * corrupt or truncated file — surfaced as an exception, never as a silent
     * empty result.
     *
     * @param string $part
     *
     * @return \XMLReader
     */
    private function openPart(string $part): \XMLReader
    {
        $reader = @\XMLReader::open('zip://' . $this->path . '#' . $part);
        if ($reader === false) {
            throw new ReadException(sprintf('Cannot open package part: %s', $part));
        }

        return $reader;
    }

    /**
     * Parses a part's _rels file into a list of its relationships.
     *
     * @param string $part
     *
     * @return list<array{id: string, type: string, target: string}>
     */
    private function relationships(string $part): array
    {
        $xml = $this->readMemberOrNull($this->relsPathFor($part));
        if ($xml === null) {
            return [];
        }
        $rels = @simplexml_load_string($xml, \SimpleXMLElement::class, \LIBXML_NONET);
        if ($rels === false || !isset($rels->Relationship)) {
            return [];
        }

        $out = [];
        foreach ($rels->Relationship as $rel) {
            $out[] = ['id' => (string) $rel['Id'], 'type' => (string) $rel['Type'], 'target' => (string) $rel['Target']];
        }

        return $out;
    }

    /**
     * Returns the _rels/*.rels path that holds relationships for the given part.
     *
     * @param string $part
     *
     * @return string
     */
    private function relsPathFor(string $part): string
    {
        $dir = dirname($part);
        $prefix = $dir === '.' ? '' : $dir . '/';

        return $prefix . '_rels/' . basename($part) . '.rels';
    }

    /**
     * Collapses '.' and '..' segments in a package path to its canonical form.
     *
     * @param string $path
     *
     * @return string
     */
    private function normalizePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $segment;
        }

        return implode('/', $parts);
    }

    /**
     * Returns the named package member's bytes, or null when the member is absent.
     *
     * @param string $name
     *
     * @return ?string
     */
    private function readMemberOrNull(string $name): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($this->path) !== true) {
            return null;
        }

        try {
            return $this->readWholeOrNull($zip, $name);
        } finally {
            $zip->close();
        }
    }

    /**
     * Gate the whole archive against decompression bombs before reading anything.
     * Every part is checked from the central directory only (no inflation): one
     * whose decompressed size dwarfs its compressed size by more than
     * {@see ReadOptions::$maxCompressionRatio} is rejected. Real spreadsheet XML
     * compresses ≤~50:1; a bomb runs to hundreds or thousands to one.
     *
     * @param \ZipArchive $zip
     *
     * @return void
     */
    private function guardArchive(\ZipArchive $zip): void
    {
        if ($this->maxCompressionRatio <= 0) {
            return;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $size = $stat['size'];
            $compressed = $stat['comp_size'];
            if ($compressed <= 0 || $size <= $this->ratioFloorBytes) {
                continue;
            }

            if ($size > $compressed * $this->maxCompressionRatio) {
                throw new ReadException(sprintf(
                    'Refusing suspicious archive: part "%s" inflates %d:1 (%d bytes from %d) — above the %d:1 limit, likely a zip bomb.',
                    $stat['name'],
                    intdiv($size, $compressed),
                    $size,
                    $compressed,
                    $this->maxCompressionRatio,
                ));
            }
        }
    }

    /**
     * Read a part held whole in memory, capped at {@see ReadOptions::$maxWholeReadBytes}.
     * The declared size comes from the central directory, so the cap is enforced
     * before a byte is decompressed. Returns null when the part is absent.
     *
     * @param \ZipArchive $zip
     * @param string $name
     *
     * @return ?string
     */
    private function readWholeOrNull(\ZipArchive $zip, string $name): ?string
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            return null;
        }

        if ($this->maxWholeReadBytes > 0 && $stat['size'] > $this->maxWholeReadBytes) {
            throw new ReadException(sprintf(
                'Refusing oversized package part "%s": %d bytes exceeds the %d-byte whole-read limit, likely a zip bomb.',
                $name,
                $stat['size'],
                $this->maxWholeReadBytes,
            ));
        }

        $data = $zip->getFromName($name);

        return $data !== false ? $data : null;
    }

    /**
     * Reads xl/workbook.xml and its rels to resolve every sheet's part path and the date system.
     *
     * @param \ZipArchive $zip
     *
     * @return void
     */
    private function loadSheets(\ZipArchive $zip): void
    {
        $workbook = $this->parseXml($this->readMember($zip, 'xl/workbook.xml'));

        if (isset($workbook->workbookPr)) {
            $attribute = (string) $workbook->workbookPr['date1904'];
            $this->date1904 = $attribute === '1' || $attribute === 'true';
        }

        $targets = [];
        $relsData = $this->readWholeOrNull($zip, 'xl/_rels/workbook.xml.rels');
        if ($relsData !== null) {
            $rels = $this->parseXml($relsData);
            if (isset($rels->Relationship)) {
                foreach ($rels->Relationship as $relationship) {
                    $targets[(string) $relationship['Id']] = (string) $relationship['Target'];
                }
            }
        }

        if (!isset($workbook->sheets->sheet)) {
            return;
        }

        $index = 0;
        foreach ($workbook->sheets->sheet as $sheet) {
            $attributes = $sheet->attributes(self::RELATIONSHIPS_NS);
            $rId = $attributes !== null ? (string) $attributes['id'] : '';
            $target = $targets[$rId] ?? ('worksheets/sheet' . ($index + 1) . '.xml');

            $part = $this->resolveTarget('xl', $target);
            $this->sheetParts[$index] = $part;
            $this->sheetInfos[] = new SheetInfo($index, (string) $sheet['name'], $this->readDimension($part));
            $index++;
        }
    }

    /**
     * Resolve an OPC relationship target against its source part's directory:
     * absolute targets ("/xl/…") are package-root paths, relative ones may
     * climb with "../". Excel emits plain relative targets, but both forms are
     * legal and produced by other writers.
     *
     * @param string $baseDir
     * @param string $target
     *
     * @return string
     */
    private function resolveTarget(string $baseDir, string $target): string
    {
        if (str_starts_with($target, '/')) {
            return $this->normalizePath(ltrim($target, '/'));
        }

        return $this->normalizePath(($baseDir === '' ? '' : $baseDir . '/') . $target);
    }

    /**
     * The worksheet's declared `<dimension ref>` hint, or null when absent —
     * it precedes `<sheetData>`, so only the part head is touched.
     *
     * @param string $part
     *
     * @return ?string
     */
    private function readDimension(string $part): ?string
    {
        $reader = @\XMLReader::open('zip://' . $this->path . '#' . $part);
        if ($reader === false) {
            return null;
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }
                if ($reader->localName === 'dimension') {
                    $ref = $reader->getAttribute('ref');

                    return $ref !== null && $ref !== '' ? $ref : null;
                }
                if ($reader->localName === 'sheetData') {
                    break;
                }
            }
        } finally {
            $reader->close();
        }

        return null;
    }

    /**
     * Streams xl/sharedStrings.xml into the shared-string table, guarding against oversized tables.
     *
     * @param \ZipArchive $zip
     *
     * @return void
     */
    private function loadSharedStrings(\ZipArchive $zip): void
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return;
        }

        $reader = $this->openPart('xl/sharedStrings.xml');

        $used = 0;
        try {
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'si') {
                    do {
                        $outer = $reader->readOuterXml();
                        $node = $outer !== ''
                            ? simplexml_load_string($outer, \SimpleXMLElement::class, \LIBXML_NONET)
                            : false;
                        $string = $node !== false ? $this->richText($node) : '';
                        $used += strlen($string);
                        if ($this->maxSharedStringBytes > 0 && $used > $this->maxSharedStringBytes) {
                            throw new ReadException(sprintf(
                                'Shared-string table exceeded the %d-byte limit, likely a zip bomb.',
                                $this->maxSharedStringBytes,
                            ));
                        }
                        $this->sharedStrings[] = $string;
                    } while ($reader->next('si'));
                    break;
                }
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Streams a worksheet part row by row, yielding each row's cells keyed by row number.
     *
     * @param string $part
     *
     * @return \Generator<int<1, max>, list<Cell>>
     */
    private function streamWorksheet(string $part): \Generator
    {
        $reader = $this->openPart($part);

        try {
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'row') {
                    $lastRow = 0;
                    do {
                        $attribute = $reader->getAttribute('r');
                        $rowNumber = $attribute !== null && $attribute !== ''
                            ? max(1, (int) $attribute)
                            : $lastRow + 1;
                        $lastRow = $rowNumber;
                        $cells = $this->readRow($reader);
                        if ($cells === [] && $this->skipEmptyRows) {
                            continue;
                        }
                        yield $rowNumber => $cells;
                    } while ($reader->next('row'));
                    break;
                }
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Read a `<row>`'s cells directly off the cursor — no per-row DOM parse.
     *
     * @param \XMLReader $reader
     *
     * @return list<Cell>
     */
    private function readRow(\XMLReader $reader): array
    {
        if ($reader->isEmptyElement) {
            return [];
        }

        $cells = [];
        $maxColumn = 0;
        $autoColumn = 0;
        $rowDepth = $reader->depth;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $rowDepth) {
                break;
            }
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'c') {
                continue;
            }

            $reference = (string) $reader->getAttribute('r');
            $column = $reference !== '' ? $this->columnOf($reference) : $autoColumn + 1;
            $autoColumn = $column;
            $cells[$column] = $this->readCell($reader);
            if ($column > $maxColumn) {
                $maxColumn = $column;
            }
        }

        if ($maxColumn === 0) {
            return [];
        }

        $result = [];
        for ($i = 1; $i <= $maxColumn; $i++) {
            $result[] = $cells[$i] ?? new Cell(null, CellType::String);
        }

        return $result;
    }

    /**
     * Read one `<c>` cell off the cursor (positioned at its start tag).
     *
     * @param \XMLReader $reader
     *
     * @return Cell
     */
    private function readCell(\XMLReader $reader): Cell
    {
        $type = (string) $reader->getAttribute('t');
        $styleRaw = $reader->getAttribute('s');
        $style = $styleRaw !== null && $styleRaw !== '' ? (int) $styleRaw : null;

        $value = '';
        $formula = null;
        $inline = '';
        $hasValue = false;

        if (!$reader->isEmptyElement) {
            $cellDepth = $reader->depth;
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $cellDepth) {
                    break;
                }
                if ($reader->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }
                switch ($reader->localName) {
                    case 'v':
                        $value = $this->elementText($reader);
                        $hasValue = true;
                        break;
                    case 'f':
                        $formula = $this->elementText($reader);
                        break;
                    case 'is':
                        $inline = $this->inlineText($reader);
                        break;
                }
            }
        }

        return $this->cellFor($type, $style, $value, $formula, $inline, $hasValue);
    }

    /**
     * Builds a typed Cell from a raw <c> element's type, style, value, formula and inline text.
     *
     * @param string $type
     * @param ?int $style
     * @param string $value
     * @param ?string $formula
     * @param string $inline
     * @param bool $hasValue
     *
     * @return Cell
     */
    private function cellFor(string $type, ?int $style, string $value, ?string $formula, string $inline, bool $hasValue): Cell
    {
        switch ($type) {
            case 'inlineStr':
                return new Cell($inline, CellType::String, $style);

            case 's':
                $index = $hasValue ? (int) $value : -1;

                return new Cell($this->sharedStrings[$index] ?? '', CellType::String, $style);

            case 'b':
                return new Cell($hasValue && $value === '1', CellType::Bool, $style);

            case 'e':
                return new Cell($hasValue ? $value : '#ERROR!', CellType::Error, $style);

            case 'str':
                return new Cell($hasValue ? $value : '', CellType::String, $style);

            case 'd':
                if (!$hasValue || trim($value) === '') {
                    return new Cell(null, CellType::String, $style);
                }
                try {
                    $date = new \DateTimeImmutable(trim($value), new \DateTimeZone('UTC'));
                } catch (\DateMalformedStringException) {
                    return new Cell($value, CellType::String, $style);
                }

                return new Cell(ExcelDate::toSerial($date), CellType::Date, $style);

            default:
                if ($formula !== null && !$hasValue) {
                    return new Cell($formula, CellType::Formula, $style);
                }
                if (!$hasValue) {
                    return new Cell(null, CellType::String, $style);
                }

                $number = $this->parseNumber($value);
                if ($style !== null && $this->isDateStyle($style)) {
                    return new Cell(
                        $this->date1904 ? $number + ExcelDate::SERIAL_1904_OFFSET : $number,
                        CellType::Date,
                        $style,
                    );
                }

                return new Cell($number, CellType::Number, $style);
        }
    }

    /**
     * Whether a cellXfs index carries a date/time number format — built-in date
     * ids (14-22, 27-36, 45-47, 50-58) or a custom code containing date tokens.
     *
     * @param int $styleId
     *
     * @return bool
     */
    private function isDateStyle(int $styleId): bool
    {
        $this->dateStyles ??= $this->loadDateStyles();

        return $this->dateStyles[$styleId] ?? false;
    }

    /**
     * Builds a map of cellXfs index to whether that style applies a date/time number format.
     *
     * @return array<int, bool> cellXfs index => is date-formatted
     */
    private function loadDateStyles(): array
    {
        if ($this->stylesXml === null) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $root = simplexml_load_string($this->stylesXml, \SimpleXMLElement::class, \LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if ($root === false) {
            return [];
        }

        $customDateIds = [];
        if (isset($root->numFmts->numFmt)) {
            foreach ($root->numFmts->numFmt as $numFmt) {
                $customDateIds[(int) $numFmt['numFmtId']] = $this->isDateFormatCode((string) $numFmt['formatCode']);
            }
        }

        $dateStyles = [];
        if (isset($root->cellXfs->xf)) {
            $index = 0;
            foreach ($root->cellXfs->xf as $xf) {
                $numFmtId = (int) $xf['numFmtId'];
                $dateStyles[$index] = $customDateIds[$numFmtId]
                    ?? (($numFmtId >= 14 && $numFmtId <= 22)
                        || ($numFmtId >= 27 && $numFmtId <= 36)
                        || ($numFmtId >= 45 && $numFmtId <= 47)
                        || ($numFmtId >= 50 && $numFmtId <= 58));
                $index++;
            }
        }

        return $dateStyles;
    }

    /**
     * Heuristic for custom format codes: date/time tokens (d, m, y, h, s)
     * outside quoted literals, [bracketed] sections, and escaped characters.
     *
     * @param string $code
     *
     * @return bool
     */
    private function isDateFormatCode(string $code): bool
    {
        $stripped = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', $code) ?? $code;

        return preg_match('/[dmyhs]/i', $stripped) === 1;
    }

    /**
     * Accumulate the text content of the current element (cursor at its start tag).
     *
     * @param \XMLReader $reader
     *
     * @return string
     */
    private function elementText(\XMLReader $reader): string
    {
        if ($reader->isEmptyElement) {
            return '';
        }

        $depth = $reader->depth;
        $text = '';
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $depth) {
                break;
            }
            if ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
                $text .= $reader->value;
            }
        }

        return $text;
    }

    /**
     * Read an inline string `<is>` off the cursor, concatenating its `<t>` runs.
     *
     * @param \XMLReader $reader
     *
     * @return string
     */
    private function inlineText(\XMLReader $reader): string
    {
        if ($reader->isEmptyElement) {
            return '';
        }

        $depth = $reader->depth;
        $text = '';
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $depth) {
                break;
            }
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 't') {
                $text .= $this->elementText($reader);
            }
        }

        return $text;
    }

    /**
     * Extracts the plain text of a shared-string <si>, concatenating its rich-text runs.
     *
     * @param \SimpleXMLElement $container
     *
     * @return string
     */
    private function richText(\SimpleXMLElement $container): string
    {
        if (isset($container->t)) {
            return (string) $container->t;
        }

        $text = '';
        if (isset($container->r)) {
            foreach ($container->r as $run) {
                if (isset($run->t)) {
                    $text .= (string) $run->t;
                }
            }
        }

        return $text;
    }

    /**
     * Converts a cell reference such as 'AB12' to its 1-based column number.
     *
     * @param string $reference
     *
     * @return int
     */
    private function columnOf(string $reference): int
    {
        $letters = preg_replace('/[0-9]+/', '', $reference);
        if ($letters === null || $letters === '') {
            return 1;
        }

        return ColumnRef::number($letters);
    }

    /**
     * Parses a numeric cell value into an int when integral, otherwise a float.
     *
     * @param string $value
     *
     * @return int|float
     */
    private function parseNumber(string $value): int|float
    {
        if (preg_match('/^-?\d+$/', $value) === 1) {
            $asInt = (int) $value;
            if ((string) $asInt === $value) {
                return $asInt;
            }
        }

        return (float) $value;
    }

    /**
     * Reads a required package member's bytes, throwing when it is missing.
     *
     * @param \ZipArchive $zip
     * @param string $name
     *
     * @return string
     */
    private function readMember(\ZipArchive $zip, string $name): string
    {
        $data = $this->readWholeOrNull($zip, $name);
        if ($data === null) {
            throw new ReadException(sprintf('Missing required part: %s', $name));
        }

        return $data;
    }

    /**
     * Parses a package XML document into a SimpleXMLElement, throwing on malformed XML.
     *
     * @param string $xml
     *
     * @return \SimpleXMLElement
     */
    private function parseXml(string $xml): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $element = simplexml_load_string($xml, \SimpleXMLElement::class, \LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($element === false) {
            throw new ReadException('Malformed XML in the workbook package.');
        }

        return $element;
    }
}
