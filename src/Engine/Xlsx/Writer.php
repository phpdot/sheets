<?php

declare(strict_types=1);

/**
 * Streaming XLSX writer. Rows serialize straight to a per-sheet temp stream (O(1)
 * memory). Each sheet's `<sheetData>` is closed, then that sheet's buffered
 * features run — contributing a single drawing part and ordered worksheet
 * trailers — before `</worksheet>` is written and the part closed.
 *
 * Two write paths: the default streams the worksheet directly (zero overhead); a
 * sheet with `autoSizeColumns` defers its body to a scratch file so the computed
 * `<cols>` can be written before `<sheetData>` — still O(1) memory (one int per
 * column tracked, rows kept on disk).
 *
 * Created per output file — never shared as a singleton.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Xlsx;

use PHPdot\Sheets\Engine\Feature\FeatureContext;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\FeaturePlugin;
use PHPdot\Sheets\Engine\Feature\FeatureSerializer;
use PHPdot\Sheets\Engine\Feature\SheetTrailerOrder;
use PHPdot\Sheets\Engine\Model\Cell;
use PHPdot\Sheets\Engine\Model\CellType;
use PHPdot\Sheets\Engine\Model\PageSetup;
use PHPdot\Sheets\Engine\Model\Row;
use PHPdot\Sheets\Engine\Model\SheetOptions;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Model\WriteOptions;
use PHPdot\Sheets\Engine\Model\WriterInterface;
use PHPdot\Sheets\Engine\Support\ColumnRef;
use PHPdot\Sheets\Engine\Support\TempDir;
use PHPdot\Sheets\Engine\Support\WriteException;
use PHPdot\Sheets\Engine\Support\Xml;

final class Writer implements WriterInterface
{
    private const NS_STYLES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles';
    private const NS_WORKSHEET = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet';
    private const NS_OFFICE_DOC = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const NS_CORE = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
    private const NS_APP = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties';
    private const NS_SHARED_STRINGS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings';
    private const HYPERLINK_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';
    private const COMMENTS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments';
    private const VML_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/vmlDrawing';

    private readonly PackageBuilder $package;
    private readonly StyleTable $styles;
    private readonly WriteOptions $options;
    private readonly PartCounter $drawingCounter;
    private readonly PartCounter $mediaCounter;
    private readonly PartCounter $chartCounter;
    private readonly PartCounter $commentCounter;
    private readonly ?SharedStrings $sharedStrings;

    /**
     * @var list<array{id: int, name: string, hidden: bool}>
     */

    private array $sheets = [];

    /**
     * @var list<array{name: string, formula: string, localSheetId: int|null}> Workbook-level defined names.
     */

    private array $definedNames = [];

    private ?PartWriter $currentSheet = null;
    private int $currentSheetId = 0;
    private int $currentRow = 0;

    /**
     * @var array<string, FeatureSerializer> Capability value => serializer.
     */

    private array $serializers = [];

    /**
     * @var list<FeatureNode> Feature nodes added to the current sheet.
     */

    private array $currentSheetNodes = [];

    /**
     * @var list<string> Merged ranges on the current sheet.
     */

    private array $mergedRanges = [];

    /**
     * @var list<array{cell: string, url: string, tooltip: string|null}> Hyperlinks on the current sheet.
     */

    private array $hyperlinks = [];

    private ?string $autoFilterRange = null;

    /**
     * @var list<int> 1-based hidden column indices on the current sheet.
     */

    private array $hiddenColumns = [];

    private bool $protectSheet = false;
    private ?string $password = null;
    private ?PageSetup $pageSetup = null;

    /**
     * @var list<array{cell: string, text: string, author: string, col: int, row: int}>
     */

    private array $comments = [];

    private bool $autoSize = false;
    private string $sheetViews = '';
    private string $sheetPr = '';

    /**
     * @var array<int, float> 1-based column => explicit width.
     */

    private array $manualWidths = [];

    /**
     * @var array<int, int> 1-based column => longest seen value (auto-size).
     */

    private array $maxLength = [];

    private ?string $scratchPath = null;
    private ?string $scratchDir = null;

    private bool $closed = false;

    /**
     * __construct.
     *
     * @param string $outputPath
     * @param ?WriteOptions $options
     */
    public function __construct(
        private readonly string $outputPath,
        ?WriteOptions $options = null,
    ) {
        $this->options = $options ?? new WriteOptions();
        $this->package = new ZipPackageBuilder();
        $this->styles = new StyleTable();
        $this->drawingCounter = new PartCounter();
        $this->mediaCounter = new PartCounter();
        $this->chartCounter = new PartCounter();
        $this->commentCounter = new PartCounter();
        $this->sharedStrings = $this->options->useSharedStrings ? new SharedStrings() : null;
    }

    /**
     * Remove the auto-size scratch directory if the writer was abandoned before
     * `close()` (the package builder cleans its own staging dir the same way).
     */
    public function __destruct()
    {
        if ($this->scratchDir !== null) {
            TempDir::remove($this->scratchDir);
        }
    }

    public function use(FeaturePlugin ...$plugins): static
    {
        foreach ($plugins as $plugin) {
            foreach ($plugin->serializers() as $serializer) {
                $this->serializers[$serializer->capability()->value] = $serializer;
            }
        }

        return $this;
    }

    public function registerStyle(Style $style): int
    {
        return $this->styles->register($style);
    }

    public function startSheet(string $name, ?SheetOptions $options = null): void
    {
        $this->assertOpen();
        $name = $this->validateSheetName($name);
        $this->finishCurrentSheet();

        $options ??= new SheetOptions();
        $id = count($this->sheets) + 1;
        $this->sheets[] = ['id' => $id, 'name' => $name, 'hidden' => $options->hidden];
        $this->currentSheetId = $id;
        $this->currentRow = 0;
        $this->currentSheetNodes = [];
        $this->mergedRanges = [];
        $this->hyperlinks = [];
        $this->autoFilterRange = null;
        $this->comments = [];
        $this->autoSize = $options->autoSizeColumns;
        $this->sheetViews = $this->buildSheetViews($options);
        $this->sheetPr = $this->buildSheetPr($options);
        $this->manualWidths = $this->normalizeWidths($options->columnWidths);
        $this->hiddenColumns = array_map(static fn(int $c): int => $c + 1, $options->hiddenColumns);
        $this->protectSheet = $options->protectSheet;
        $this->password = $options->password;
        $this->pageSetup = $options->pageSetup;
        $this->maxLength = [];

        if ($this->autoSize) {
            $this->scratchPath = $this->scratchFile();
            $handle = fopen($this->scratchPath, 'wb');
            if ($handle === false) {
                throw new WriteException(sprintf('Cannot open scratch body for sheet %d.', $id));
            }
            $this->currentSheet = new StreamPartWriter($handle);

            return;
        }

        $this->scratchPath = null;
        $part = $this->package->openPart(sprintf('xl/worksheets/sheet%d.xml', $id));
        $part->write(
            $this->worksheetPrefix() . $this->sheetPr . $this->sheetViews
            . $this->colsXml($this->manualWidths, false) . '<sheetData>',
        );
        $this->currentSheet = $part;
    }

    public function addRow(array $values, ?int $styleId = null, ?float $height = null, bool $hidden = false): void
    {
        $part = $this->requireSheet();
        $this->currentRow++;

        $xml = '<row r="' . $this->currentRow . '"' . $this->rowAttrs($height, $hidden) . '>';
        $column = 1;
        foreach ($values as $value) {
            $xml .= $this->scalarCell($column, $value, $styleId);
            if ($this->autoSize) {
                $this->trackWidth($column, $this->displayLength($value));
            }
            $column++;
        }
        $xml .= '</row>';

        $part->write($xml);
    }

    public function addRowObject(Row $row): void
    {
        $part = $this->requireSheet();
        $this->currentRow++;

        $xml = '<row r="' . $this->currentRow . '"' . $this->rowAttrs($row->height, $row->hidden) . '>';
        $column = 1;
        foreach ($row->cells as $cell) {
            $xml .= $this->typedCell($column, $cell, $cell->styleId ?? $row->styleId);
            if ($this->autoSize) {
                $this->trackWidth($column, $this->typedDisplayLength($cell));
            }
            $column++;
        }
        $xml .= '</row>';

        $part->write($xml);
    }

    public function add(FeatureNode $node): void
    {
        $this->assertOpen();
        $this->currentSheetNodes[] = $node;
    }

    public function mergeCells(string $range): void
    {
        $this->assertOpen();

        if ($this->currentSheet === null) {
            throw new WriteException('No active sheet. Call startSheet() first.');
        }
        if (preg_match('/^[A-Z]{1,3}[1-9]\d*:[A-Z]{1,3}[1-9]\d*$/', $range) !== 1) {
            throw new WriteException(sprintf('Invalid merge range "%s" (expected e.g. "A1:D1").', $range));
        }

        $this->mergedRanges[] = $this->normalizeRange($range);
    }

    public function hyperlink(string $cell, string $url, ?string $tooltip = null): void
    {
        $this->assertOpen();

        if ($this->currentSheet === null) {
            throw new WriteException('No active sheet. Call startSheet() first.');
        }
        if (preg_match('/^[A-Z]{1,3}[1-9]\d*$/', $cell) !== 1) {
            throw new WriteException(sprintf('Invalid hyperlink cell "%s" (expected e.g. "A1").', $cell));
        }

        $this->hyperlinks[] = ['cell' => $cell, 'url' => $url, 'tooltip' => $tooltip];
    }

    public function autoFilter(string $range): void
    {
        $this->assertOpen();

        if ($this->currentSheet === null) {
            throw new WriteException('No active sheet. Call startSheet() first.');
        }
        if (preg_match('/^[A-Z]{1,3}[1-9]\d*:[A-Z]{1,3}[1-9]\d*$/', $range) !== 1) {
            throw new WriteException(sprintf('Invalid auto-filter range "%s" (expected e.g. "A1:E1").', $range));
        }

        $this->autoFilterRange = $this->normalizeRange($range);
    }

    public function comment(string $cell, string $text, ?string $author = null): void
    {
        $this->assertOpen();

        if ($this->currentSheet === null) {
            throw new WriteException('No active sheet. Call startSheet() first.');
        }
        if (preg_match('/^([A-Z]{1,3})([1-9]\d*)$/', $cell, $m) !== 1) {
            throw new WriteException(sprintf('Invalid comment cell "%s" (expected e.g. "A1").', $cell));
        }

        $this->comments[] = [
            'cell' => $cell,
            'text' => $text,
            'author' => $author ?? 'Author',
            'col' => ColumnRef::number($m[1]) - 1,
            'row' => (int) $m[2] - 1,
        ];
    }

    public function defineName(string $name, string $formula): void
    {
        $this->assertOpen();

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name) !== 1) {
            throw new WriteException(sprintf('Invalid defined name "%s".', $name));
        }
        if (preg_match('/^([A-Za-z]{1,3}\d+|[Rr]\d*[Cc]\d*|[RrCc])$/', $name) === 1) {
            throw new WriteException(sprintf('Defined name "%s" is a cell reference, which Excel forbids.', $name));
        }

        $this->definedNames[] = ['name' => $name, 'formula' => $formula, 'localSheetId' => null];
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->finishCurrentSheet();

        if ($this->sheets === []) {
            $this->startSheet('Sheet1');
            $this->finishCurrentSheet();
        }

        $this->closed = true;

        try {
            $this->writeStyles();
            $this->writeWorkbook();
            $this->writeDocProps();
            $this->writeSharedStrings();
            $this->package->registerContentType('rels', 'application/vnd.openxmlformats-package.relationships+xml');
            $this->package->registerContentType('xml', 'application/xml');
            $this->package->finalizeZip($this->outputPath);
        } finally {
            if ($this->scratchDir !== null) {
                TempDir::remove($this->scratchDir);
                $this->scratchDir = null;
            }
        }
    }

    /**
     * Flushes the current worksheet part, streaming its buffered body and appending its trailers.
     *
     * @return void
     */
    private function finishCurrentSheet(): void
    {
        if ($this->currentSheet === null) {
            return;
        }

        if ($this->autoSize) {
            $this->currentSheet->close();
            $part = $this->package->openPart(sprintf('xl/worksheets/sheet%d.xml', $this->currentSheetId));
            $part->write(
                $this->worksheetPrefix() . $this->sheetPr . $this->sheetViews
                . $this->colsXml($this->computedWidths(), true) . '<sheetData>',
            );
            if ($this->scratchPath !== null) {
                $scratch = $this->scratchPath;
                $this->copyFileInto($part, $scratch);
                @unlink($scratch);
                $this->scratchPath = null;
            }
        } else {
            $part = $this->currentSheet;
        }

        $part->write('</sheetData>');

        $sheetPartPath = sprintf('xl/worksheets/sheet%d.xml', $this->currentSheetId);
        $drawing = new XlsxDrawingCollector(
            $this->package,
            $sheetPartPath,
            $this->drawingCounter,
            $this->mediaCounter,
            $this->chartCounter,
        );
        $trailers = new SheetTrailers();
        foreach ($this->mergedRanges as $range) {
            $trailers->add(SheetTrailerOrder::MERGE_CELLS, '<mergeCell ref="' . Xml::attribute($range) . '"/>', 'mergeCells');
        }
        if ($this->autoFilterRange !== null) {
            $trailers->add(
                SheetTrailerOrder::AUTO_FILTER,
                '<autoFilter ref="' . Xml::attribute($this->autoFilterRange) . '"/>',
            );
        }
        if ($this->hyperlinks !== []) {
            $hyperlinksXml = '<hyperlinks>';
            $urlRelIds = [];
            foreach ($this->hyperlinks as $link) {
                $rId = $urlRelIds[$link['url']]
                    ??= $this->package->addRelationship($sheetPartPath, self::HYPERLINK_REL, $link['url'], 'External');
                $hyperlinksXml .= '<hyperlink ref="' . Xml::attribute($link['cell']) . '" r:id="' . $rId . '"';
                if ($link['tooltip'] !== null) {
                    $hyperlinksXml .= ' tooltip="' . Xml::attribute($link['tooltip']) . '"';
                }
                $hyperlinksXml .= '/>';
            }
            $trailers->add(SheetTrailerOrder::HYPERLINKS, $hyperlinksXml . '</hyperlinks>');
        }
        if ($this->protectSheet) {
            $protection = ' sheet="1"';
            if ($this->password !== null) {
                $protection .= ' password="' . $this->passwordHash($this->password) . '"';
            }
            $trailers->add(SheetTrailerOrder::SHEET_PROTECTION, '<sheetProtection' . $protection . '/>');
        }
        $this->emitPageSetup($trailers, $this->currentSheetId - 1);
        $this->emitComments($trailers, $sheetPartPath);
        $context = new FeatureContext(
            $this->package,
            $this->currentSheetId - 1,
            $sheetPartPath,
            $drawing,
            $trailers,
            $this->styles,
        );

        foreach ($this->currentSheetNodes as $node) {
            $serializer = $this->serializers[$node->capability()->value] ?? null;
            if ($serializer !== null) {
                $serializer->serialize($node, $context);
            }
        }

        $drawing->flush($trailers);
        $part->write($trailers->toXml());
        $part->write('</worksheet>');
        $part->close();

        $this->currentSheet = null;
        $this->currentSheetNodes = [];
        $this->mergedRanges = [];
        $this->hyperlinks = [];
        $this->autoFilterRange = null;
        $this->comments = [];
        $this->hiddenColumns = [];
        $this->protectSheet = false;
        $this->password = null;
        $this->pageSetup = null;
        $this->autoSize = false;
        $this->maxLength = [];
        $this->manualWidths = [];
        $this->sheetViews = '';
        $this->sheetPr = '';
    }

    /**
     * Returns the XML declaration and opening <worksheet> element with its namespaces.
     *
     * @return string
     */
    private function worksheetPrefix(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    }

    /**
     * Builds the <sheetViews> element for frozen panes and grid-line visibility, or empty.
     *
     * @param SheetOptions $options
     *
     * @return string
     */
    private function buildSheetViews(SheetOptions $options): string
    {
        $frozenRows = max(0, $options->frozenRows);
        $frozenColumns = max(0, $options->frozenColumns);
        $hasFreeze = $frozenRows > 0 || $frozenColumns > 0;

        if (!$hasFreeze && $options->showGridLines) {
            return '';
        }

        $view = '<sheetView workbookViewId="0"';
        if (!$options->showGridLines) {
            $view .= ' showGridLines="0"';
        }
        $view .= '>';

        if ($hasFreeze) {
            $topLeft = ColumnRef::letters($frozenColumns + 1) . ($frozenRows + 1);
            $activePane = $frozenRows > 0 && $frozenColumns > 0
                ? 'bottomRight'
                : ($frozenRows > 0 ? 'bottomLeft' : 'topRight');
            $view .= '<pane';
            if ($frozenColumns > 0) {
                $view .= ' xSplit="' . $frozenColumns . '"';
            }
            if ($frozenRows > 0) {
                $view .= ' ySplit="' . $frozenRows . '"';
            }
            $view .= ' topLeftCell="' . $topLeft . '" activePane="' . $activePane . '" state="frozen"/>';
        }

        return '<sheetViews>' . $view . '</sheetView></sheetViews>';
    }

    /**
     * Builds the <sheetPr> element for the tab color and fit-to-page flag, or empty.
     *
     * @param SheetOptions $options
     *
     * @return string
     */
    private function buildSheetPr(SheetOptions $options): string
    {
        $inner = '';
        if ($options->tabColor !== null) {
            $inner .= '<tabColor rgb="FF' . $options->tabColor->rgb . '"/>';
        }
        if ($options->pageSetup !== null
            && ($options->pageSetup->fitToWidth !== null || $options->pageSetup->fitToHeight !== null)
        ) {
            $inner .= '<pageSetUpPr fitToPage="1"/>';
        }

        return $inner !== '' ? '<sheetPr>' . $inner . '</sheetPr>' : '';
    }

    /**
     * Adds the page-margins and page-setup trailer fragments for the sheet's PageSetup, if any.
     *
     * @param SheetTrailers $trailers
     * @param int $sheetIndex
     *
     * @return void
     */
    private function emitPageSetup(SheetTrailers $trailers, int $sheetIndex): void
    {
        $setup = $this->pageSetup;
        if ($setup === null) {
            return;
        }

        $trailers->add(
            SheetTrailerOrder::PAGE_MARGINS,
            '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>',
        );

        $page = '<pageSetup orientation="' . $setup->orientation->value . '"';
        if ($setup->fitToWidth !== null) {
            $page .= ' fitToWidth="' . $setup->fitToWidth . '"';
        }
        if ($setup->fitToHeight !== null) {
            $page .= ' fitToHeight="' . $setup->fitToHeight . '"';
        }
        $trailers->add(SheetTrailerOrder::PAGE_SETUP, $page . '/>');

        if ($setup->header !== null || $setup->footer !== null) {
            $headerFooter = '<headerFooter>';
            if ($setup->header !== null) {
                $headerFooter .= '<oddHeader>&amp;C' . Xml::text($setup->header) . '</oddHeader>';
            }
            if ($setup->footer !== null) {
                $headerFooter .= '<oddFooter>&amp;C' . Xml::text($setup->footer) . '</oddFooter>';
            }
            $trailers->add(SheetTrailerOrder::HEADER_FOOTER, $headerFooter . '</headerFooter>');
        }

        $sheet = $this->sheets[$sheetIndex] ?? null;
        if ($sheet === null) {
            return;
        }
        $sheetRef = "'" . str_replace("'", "''", $sheet['name']) . "'";
        if ($setup->printArea !== null) {
            $this->definedNames[] = [
                'name' => '_xlnm.Print_Area',
                'formula' => $sheetRef . '!' . $this->absoluteRange($setup->printArea),
                'localSheetId' => $sheetIndex,
            ];
        }
        if ($setup->repeatRows !== null && $setup->repeatRows > 0) {
            $this->definedNames[] = [
                'name' => '_xlnm.Print_Titles',
                'formula' => $sheetRef . '!$1:$' . $setup->repeatRows,
                'localSheetId' => $sheetIndex,
            ];
        }
    }

    /**
     * Order a validated "X9:Y9"-style range so the top-left cell comes first —
     * Excel rejects (or repairs) ranges whose start lies below/right of the end.
     *
     * @param string $range
     *
     * @return string
     */
    private function normalizeRange(string $range): string
    {
        if (preg_match('/^([A-Z]{1,3})(\d+):([A-Z]{1,3})(\d+)$/', $range, $m) !== 1) {
            return $range;
        }

        $fromColumn = ColumnRef::number($m[1]);
        $toColumn = ColumnRef::number($m[3]);
        $fromRow = (int) $m[2];
        $toRow = (int) $m[4];

        return ColumnRef::letters(min($fromColumn, $toColumn)) . min($fromRow, $toRow)
            . ':' . ColumnRef::letters(max($fromColumn, $toColumn)) . max($fromRow, $toRow);
    }

    /**
     * Rewrites a cell range into absolute ($A$1) form.
     *
     * @param string $range
     *
     * @return string
     */
    private function absoluteRange(string $range): string
    {
        $absolute = preg_replace_callback(
            '/([A-Z]+)(\d+)/',
            static fn(array $m): string => '$' . $m[1] . '$' . $m[2],
            $range,
        );

        return $absolute ?? $range;
    }

    /**
     * Writes the comments and legacy VML parts for the sheet's comments and wires their rels.
     *
     * @param SheetTrailers $trailers
     * @param string $sheetPartPath
     *
     * @return void
     */
    private function emitComments(SheetTrailers $trailers, string $sheetPartPath): void
    {
        if ($this->comments === []) {
            return;
        }

        $n = $this->commentCounter->next();
        $commentsPath = sprintf('xl/comments%d.xml', $n);
        $vmlName = sprintf('vmlDrawing%d.vml', $n);

        $this->package->addXmlPart($commentsPath, $this->commentsXml());
        $this->package->registerContentType(
            '/' . $commentsPath,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.comments+xml',
        );
        $this->package->addXmlPart('xl/drawings/' . $vmlName, $this->vmlXml());
        $this->package->registerContentType('vml', 'application/vnd.openxmlformats-officedocument.vmlDrawing');

        $this->package->addRelationship($sheetPartPath, self::COMMENTS_REL, sprintf('../comments%d.xml', $n));
        $vmlRid = $this->package->addRelationship($sheetPartPath, self::VML_REL, '../drawings/' . $vmlName);
        $trailers->add(SheetTrailerOrder::LEGACY_DRAWING, '<legacyDrawing r:id="' . $vmlRid . '"/>');
    }

    /**
     * Serializes the sheet's comments into a commentsN.xml document with a de-duplicated author list.
     *
     * @return string
     */
    private function commentsXml(): string
    {
        $authors = [];
        foreach ($this->comments as $comment) {
            if (!in_array($comment['author'], $authors, true)) {
                $authors[] = $comment['author'];
            }
        }
        $authorIds = array_flip($authors);

        $authorsXml = '';
        foreach ($authors as $author) {
            $authorsXml .= '<author>' . Xml::text($author) . '</author>';
        }

        $list = '';
        foreach ($this->comments as $comment) {
            $list .= '<comment ref="' . Xml::attribute($comment['cell'])
                . '" authorId="' . ($authorIds[$comment['author']] ?? 0) . '">'
                . '<text><r><t xml:space="preserve">' . Xml::text($comment['text']) . '</t></r></text></comment>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<comments xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<authors>' . $authorsXml . '</authors>'
            . '<commentList>' . $list . '</commentList></comments>';
    }

    /**
     * Serializes the legacy VML drawing that positions each comment's note box.
     *
     * @return string
     */
    private function vmlXml(): string
    {
        $shapes = '';
        $index = 0;
        foreach ($this->comments as $comment) {
            $shapes .= '<v:shape id="_x0000_s' . (1025 + $index) . '" type="#_x0000_t202"'
                . ' style="position:absolute;margin-left:60pt;margin-top:1.5pt;width:108pt;height:60pt;'
                . 'z-index:' . ($index + 1) . ';visibility:hidden" fillcolor="#ffffe1" o:insetmode="auto">'
                . '<v:fill color2="#ffffe1"/><v:shadow on="t" color="black" obscured="t"/>'
                . '<v:path o:connecttype="none"/>'
                . '<v:textbox style="mso-direction-alt:auto"><div style="text-align:left"></div></v:textbox>'
                . '<x:ClientData ObjectType="Note"><x:MoveWithCells/><x:SizeWithCells/>'
                . '<x:Anchor>' . ($comment['col'] + 1) . ', 15, ' . $comment['row'] . ', 2, '
                . ($comment['col'] + 3) . ', 15, ' . ($comment['row'] + 4) . ', 4</x:Anchor>'
                . '<x:AutoFill>False</x:AutoFill>'
                . '<x:Row>' . $comment['row'] . '</x:Row><x:Column>' . $comment['col'] . '</x:Column>'
                . '</x:ClientData></v:shape>';
            $index++;
        }

        return '<xml xmlns:v="urn:schemas-microsoft-com:vml"'
            . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
            . ' xmlns:x="urn:schemas-microsoft-com:office:excel">'
            . '<o:shapelayout v:ext="edit"><o:idmap v:ext="edit" data="1"/></o:shapelayout>'
            . '<v:shapetype id="_x0000_t202" coordsize="21600,21600" o:spt="202" path="m,l,21600r21600,l21600,xe">'
            . '<v:stroke joinstyle="miter"/><v:path gradientshapeok="t" o:connecttype="rect"/></v:shapetype>'
            . $shapes . '</xml>';
    }

    /**
     * Reindexes 0-based manual column widths to the 1-based column numbering the writer uses.
     *
     * @param array<int, int|float> $columnWidths 0-based column => width
     *
     * @return array<int, float> 1-based column => width
     */
    private function normalizeWidths(array $columnWidths): array
    {
        $widths = [];
        foreach ($columnWidths as $column => $width) {
            $widths[$column + 1] = (float) $width;
        }

        return $widths;
    }

    /**
     * Merges auto-sized and manual column widths into a single 1-based width map.
     *
     * @return array<int, float> 1-based column => width
     */
    private function computedWidths(): array
    {
        $widths = [];
        foreach ($this->maxLength as $column => $length) {
            $widths[$column] = $this->estimateWidth($length);
        }
        foreach ($this->manualWidths as $column => $width) {
            $widths[$column] = $width;
        }

        return $widths;
    }

    /**
     * Serializes the column widths and hidden columns into a <cols> element.
     *
     * @param array<int, float> $widths 1-based column => width
     * @param bool $bestFit
     *
     * @return string
     */
    private function colsXml(array $widths, bool $bestFit): string
    {
        $columns = $widths;
        foreach ($this->hiddenColumns as $hiddenColumn) {
            if (!array_key_exists($hiddenColumn, $columns)) {
                $columns[$hiddenColumn] = null;
            }
        }
        if ($columns === []) {
            return '';
        }

        ksort($columns);
        $hidden = array_flip($this->hiddenColumns);
        $xml = '<cols>';
        foreach ($columns as $column => $width) {
            $xml .= '<col min="' . $column . '" max="' . $column . '"';
            if ($width !== null) {
                $xml .= ' width="' . (string) round($width, 2) . '" customWidth="1"' . ($bestFit ? ' bestFit="1"' : '');
            }
            if (isset($hidden[$column])) {
                $xml .= ' hidden="1"';
            }
            $xml .= '/>';
        }

        return $xml . '</cols>';
    }

    /**
     * Records the widest display length seen for a column, for auto-sizing.
     *
     * @param int $column
     * @param int $length
     *
     * @return void
     */
    private function trackWidth(int $column, int $length): void
    {
        if ($length > ($this->maxLength[$column] ?? 0)) {
            $this->maxLength[$column] = $length;
        }
    }

    /**
     * Estimates the display width of a value in characters.
     *
     * @param int|float|string|bool|null $value
     *
     * @return int
     */
    private function displayLength(int|float|string|bool|null $value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_bool($value)) {
            return $value ? 4 : 5;
        }
        if (is_int($value) || is_float($value)) {
            return strlen($this->number($value));
        }

        return mb_strwidth($value, 'UTF-8');
    }

    /**
     * Estimates the display width of a typed cell, using a fixed width for dates.
     *
     * @param Cell $cell
     *
     * @return int
     */
    private function typedDisplayLength(Cell $cell): int
    {
        if ($cell->type === CellType::Date) {
            return 11;
        }

        return $this->displayLength($cell->value);
    }

    /**
     * Converts a max character count into an Excel column width, clamped to 2-255.
     *
     * @param int $maxChars
     *
     * @return float
     */
    private function estimateWidth(int $maxChars): float
    {
        return min(255.0, max(2.0, (float) $maxChars + 2.0));
    }

    /**
     * Returns the scratch file path for the current sheet's streamed body, creating the temp dir.
     *
     * @return string
     */
    private function scratchFile(): string
    {
        if ($this->scratchDir === null) {
            $this->scratchDir = TempDir::create('phpdot_sheets_body_');
        }

        return $this->scratchDir . '/sheet' . $this->currentSheetId . '.xml';
    }

    /**
     * Streams a scratch body file into a package part in chunks.
     *
     * @param PartWriter $part
     * @param string $path
     *
     * @return void
     */
    private function copyFileInto(PartWriter $part, string $path): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new WriteException(sprintf('Cannot read scratch body: %s', $path));
        }

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 65536);
                if ($chunk !== false && $chunk !== '') {
                    $part->write($chunk);
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Serializes a scalar value into a <c> cell element, inferring its type.
     *
     * @param int $column
     * @param int|float|string|bool|null $value
     * @param ?int $styleId
     *
     * @return string
     */
    private function scalarCell(int $column, int|float|string|bool|null $value, ?int $styleId): string
    {
        $reference = ColumnRef::letters($column) . $this->currentRow;
        $attributes = $reference . '"' . $this->styleAttr($styleId);

        if ($value === null) {
            return '<c r="' . $attributes . '/>';
        }
        if (is_bool($value)) {
            return '<c r="' . $attributes . ' t="b"><v>' . ($value ? '1' : '0') . '</v></c>';
        }
        if (is_int($value) || is_float($value)) {
            return '<c r="' . $attributes . '><v>' . $this->number($value) . '</v></c>';
        }

        return $this->stringCell($attributes, $value);
    }

    /**
     * Serializes a typed Cell into a <c> element honoring its declared cell type.
     *
     * @param int $column
     * @param Cell $cell
     * @param ?int $styleId
     *
     * @return string
     */
    private function typedCell(int $column, Cell $cell, ?int $styleId): string
    {
        $reference = ColumnRef::letters($column) . $this->currentRow;
        $attributes = $reference . '"' . $this->styleAttr($styleId);
        $value = $cell->value;

        if (($cell->type === CellType::Number || $cell->type === CellType::Date)
            && (is_int($value) || is_float($value))
        ) {
            return '<c r="' . $attributes . '><v>' . $this->number($value) . '</v></c>';
        }

        if ($cell->type === CellType::Bool) {
            return '<c r="' . $attributes . ' t="b"><v>' . ((bool) $value ? '1' : '0') . '</v></c>';
        }

        if ($cell->type === CellType::Formula && is_string($value)) {
            return '<c r="' . $attributes . '><f>' . Xml::text($value) . '</f></c>';
        }

        if ($cell->type === CellType::Error) {
            return '<c r="' . $attributes . ' t="e"><v>' . Xml::text($this->stringify($value)) . '</v></c>';
        }

        $forceInline = $cell->type === CellType::Inline;

        return $this->stringCell($attributes, $this->stringify($value), $forceInline);
    }

    /**
     * Serializes a string as a shared-string or inline-string <c> element.
     *
     * @param string $attributes
     * @param string $value
     * @param bool $forceInline
     *
     * @return string
     */
    private function stringCell(string $attributes, string $value, bool $forceInline = false): string
    {
        if (!$forceInline && $this->sharedStrings !== null) {
            return '<c r="' . $attributes . ' t="s"><v>' . $this->sharedStrings->index($value) . '</v></c>';
        }

        return '<c r="' . $attributes . ' t="inlineStr"><is><t xml:space="preserve">'
            . Xml::text($value) . '</t></is></c>';
    }

    /**
     * Returns the s="..." style attribute for a non-default style id, or empty.
     *
     * @param ?int $styleId
     *
     * @return string
     */
    private function styleAttr(?int $styleId): string
    {
        return $styleId !== null && $styleId > 0 ? ' s="' . $styleId . '"' : '';
    }

    /**
     * Returns the height and hidden attributes for a <row> element.
     *
     * @param ?float $height
     * @param bool $hidden
     *
     * @return string
     */
    private function rowAttrs(?float $height, bool $hidden): string
    {
        $attr = $height !== null ? ' ht="' . (string) $height . '" customHeight="1"' : '';
        if ($hidden) {
            $attr .= ' hidden="1"';
        }

        return $attr;
    }

    /**
     * Computes the legacy Excel 16-bit sheet-protection password hash.
     *
     * @param string $password
     *
     * @return string
     */
    private function passwordHash(string $password): string
    {
        $hash = 0;
        for ($i = strlen($password) - 1; $i >= 0; $i--) {
            $hash = (($hash >> 14) & 0x01) | (($hash << 1) & 0x7FFF);
            $hash ^= ord($password[$i]);
        }
        $hash = (($hash >> 14) & 0x01) | (($hash << 1) & 0x7FFF);
        $hash ^= strlen($password);
        $hash ^= 0xCE4B;

        return strtoupper(dechex($hash));
    }

    /**
     * Renders a numeric value as a string, rejecting non-finite floats.
     *
     * @param int|float $value
     *
     * @return string
     */
    private function number(int|float $value): string
    {
        if (is_float($value) && !is_finite($value)) {
            throw new WriteException('Cannot write a non-finite number (NAN/INF) to a cell.');
        }

        return (string) $value;
    }

    /**
     * Renders any scalar value as its string representation for a cell.
     *
     * @param int|float|string|bool|null $value
     *
     * @return string
     */
    private function stringify(int|float|string|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_int($value) || is_float($value)) {
            return $this->number($value);
        }

        return $value;
    }

    /**
     * Writes the xl/styles.xml part and wires its content type and workbook relationship.
     *
     * @return void
     */
    private function writeStyles(): void
    {
        $this->package->addXmlPart('xl/styles.xml', $this->styles->toXml());
        $this->package->registerContentType(
            '/xl/styles.xml',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml',
        );
        $this->package->addRelationship('xl/workbook.xml', self::NS_STYLES, 'styles.xml');
    }

    /**
     * Writes the xl/sharedStrings.xml part when the shared-string table is non-empty.
     *
     * @return void
     */
    private function writeSharedStrings(): void
    {
        if ($this->sharedStrings === null || $this->sharedStrings->isEmpty()) {
            return;
        }

        $this->package->addXmlPart('xl/sharedStrings.xml', $this->sharedStrings->toXml());
        $this->package->registerContentType(
            '/xl/sharedStrings.xml',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml',
        );
        $this->package->addRelationship('xl/workbook.xml', self::NS_SHARED_STRINGS, 'sharedStrings.xml');
    }

    /**
     * Writes xl/workbook.xml listing every sheet and its defined names, and wires the office-doc rel.
     *
     * @return void
     */
    private function writeWorkbook(): void
    {
        $sheetsXml = '';
        foreach ($this->sheets as $sheet) {
            $part = sprintf('xl/worksheets/sheet%d.xml', $sheet['id']);
            $this->package->registerContentType(
                '/' . $part,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml',
            );
            $rId = $this->package->addRelationship(
                'xl/workbook.xml',
                self::NS_WORKSHEET,
                sprintf('worksheets/sheet%d.xml', $sheet['id']),
            );
            $sheetsXml .= '<sheet name="' . Xml::attribute($sheet['name'])
                . '" sheetId="' . $sheet['id'] . '"' . ($sheet['hidden'] ? ' state="hidden"' : '')
                . ' r:id="' . $rId . '"/>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetsXml . '</sheets>'
            . $this->definedNamesXml()
            . '<calcPr fullCalcOnLoad="1"/>'
            . '</workbook>';

        $this->package->addXmlPart('xl/workbook.xml', $xml);
        $this->package->registerContentType(
            '/xl/workbook.xml',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
        );
        $this->package->addRelationship('', self::NS_OFFICE_DOC, 'xl/workbook.xml');
    }

    /**
     * Writes the docProps/core.xml and docProps/app.xml metadata parts and wires their relationships.
     *
     * @return void
     */
    private function writeDocProps(): void
    {
        $creator = Xml::text($this->options->creator ?? 'phpdot/sheets');

        $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:creator>' . $creator . '</dc:creator>'
            . $this->coreProperty('dc:title', $this->options->title)
            . $this->coreProperty('dc:subject', $this->options->subject)
            . $this->coreProperty('cp:keywords', $this->options->keywords)
            . $this->coreProperty('dc:description', $this->options->description)
            . $this->coreProperty('cp:category', $this->options->category)
            . '</cp:coreProperties>';
        $this->package->addXmlPart('docProps/core.xml', $core);
        $this->package->registerContentType(
            '/docProps/core.xml',
            'application/vnd.openxmlformats-package.core-properties+xml',
        );
        $this->package->addRelationship('', self::NS_CORE, 'docProps/core.xml');

        $app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
            . '<Application>phpdot/sheets</Application></Properties>';
        $this->package->addXmlPart('docProps/app.xml', $app);
        $this->package->registerContentType(
            '/docProps/app.xml',
            'application/vnd.openxmlformats-officedocument.extended-properties+xml',
        );
        $this->package->addRelationship('', self::NS_APP, 'docProps/app.xml');
    }

    /**
     * Serializes the workbook's defined names into a <definedNames> element, or empty.
     *
     * @return string
     */
    private function definedNamesXml(): string
    {
        if ($this->definedNames === []) {
            return '';
        }

        $xml = '<definedNames>';
        foreach ($this->definedNames as $name) {
            $xml .= '<definedName name="' . Xml::attribute($name['name']) . '"';
            if ($name['localSheetId'] !== null) {
                $xml .= ' localSheetId="' . $name['localSheetId'] . '"';
            }
            $xml .= '>' . Xml::text($name['formula']) . '</definedName>';
        }

        return $xml . '</definedNames>';
    }

    /**
     * Serializes one optional core-property element, or empty when the value is null.
     *
     * @param string $tag
     * @param ?string $value
     *
     * @return string
     */
    private function coreProperty(string $tag, ?string $value): string
    {
        return $value !== null ? '<' . $tag . '>' . Xml::text($value) . '</' . $tag . '>' : '';
    }

    /**
     * Returns the active sheet's part writer, throwing when no sheet has been started.
     *
     * @return PartWriter
     */
    private function requireSheet(): PartWriter
    {
        $this->assertOpen();

        if ($this->currentSheet === null) {
            throw new WriteException('No active sheet. Call startSheet() first.');
        }

        return $this->currentSheet;
    }

    /**
     * Throws when the writer has already been closed.
     *
     * @return void
     */
    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new WriteException('Writer is already closed.');
        }
    }

    /**
     * Validates and trims a sheet name, rejecting empty, illegal, over-long or duplicate names.
     *
     * @param string $name
     *
     * @return string
     */
    private function validateSheetName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new WriteException('Sheet name must not be empty.');
        }
        if (strpbrk($trimmed, ":\\/?*[]") !== false) {
            throw new WriteException(
                sprintf('Sheet name "%s" contains an illegal character (one of : \\ / ? * [ ]).', $name),
            );
        }
        if (mb_strlen($trimmed) > 31) {
            throw new WriteException(sprintf('Sheet name "%s" exceeds the 31-character limit.', $name));
        }
        $folded = mb_strtolower($trimmed);
        foreach ($this->sheets as $sheet) {
            if (mb_strtolower($sheet['name']) === $folded) {
                throw new WriteException(sprintf('Duplicate sheet name: "%s".', $trimmed));
            }
        }

        return $trimmed;
    }
}
