<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\Chart\ChartPlugin;
use PHPdot\Sheets\Engine\Feature\Image\ImagePlugin;
use PHPdot\Sheets\Engine\Feature\Validation\ValidationPlugin;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Model\WriteOptions;
use PHPdot\Sheets\Engine\Support\RuntimeException;
use PHPdot\Sheets\Engine\Support\WriteException;
use PHPdot\Sheets\Engine\Xlsx\Writer;

/**
 * A workbook being written. Returned by {@see \PHPdot\Sheets\Sheets::write()}.
 *
 * Owns the document properties, the style factory, the sheets, and the final
 * `save()`. The underlying streaming {@see Writer} is created lazily on the first
 * sheet so properties (written at close) can be set first — afterwards they throw.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Workbook
{
    private ?string $creator = null;
    private ?string $title = null;
    private ?string $subject = null;
    private ?string $keywords = null;
    private ?string $description = null;
    private ?string $category = null;

    private ?Writer $writer = null;
    private ?Sheet $current = null;
    private bool $saved = false;

    /** @var list<string> Lowercased sheet names, for eager duplicate detection. */
    private array $sheetNames = [];

    public function __construct(
        private readonly string $path,
        private readonly bool $sharedStrings = false,
    ) {}

    public function creator(string $creator): self
    {
        $this->assertNotStarted();
        $this->creator = $creator;

        return $this;
    }

    public function title(string $title): self
    {
        $this->assertNotStarted();
        $this->title = $title;

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->assertNotStarted();
        $this->subject = $subject;

        return $this;
    }

    public function keywords(string $keywords): self
    {
        $this->assertNotStarted();
        $this->keywords = $keywords;

        return $this;
    }

    public function description(string $description): self
    {
        $this->assertNotStarted();
        $this->description = $description;

        return $this;
    }

    public function category(string $category): self
    {
        $this->assertNotStarted();
        $this->category = $category;

        return $this;
    }

    /**
     * A fresh style to chain on, e.g. `$xlsx->style()->bold()->fontColor('FFFFFF')`.
     * Registered with the workbook's dedup table on first use.
     */
    public function style(): Style
    {
        return Style::make();
    }

    /**
     * Define a workbook-level named range, e.g. `name('Sales', 'Data!$A$1:$A$9')`.
     * Cell-reference-shaped names are rejected (Excel forbids them).
     */
    public function name(string $name, string $formula): self
    {
        $this->assertNotSaved();
        $this->writer()->defineName($name, $formula);

        return $this;
    }

    /**
     * Start a new sheet and return it. The name is validated here (≤31 chars, no
     * `:\/?*[]`, case-insensitively unique) so a bad name fails now rather than at
     * the first row. Opening a sheet finalizes the previous one.
     */
    public function addSheet(string $name): Sheet
    {
        $this->assertNotSaved();
        $name = $this->validateSheetName($name);
        $this->current?->finalize();
        $this->sheetNames[] = mb_strtolower($name);

        return $this->current = new Sheet($this->writer(), $name);
    }

    /**
     * Finalize features, write remaining parts, and zip the file. Idempotent.
     */
    public function save(): void
    {
        if ($this->saved) {
            return;
        }

        $this->current?->finalize();
        $this->writer()->close();
        $this->saved = true;
    }

    private function writer(): Writer
    {
        if ($this->writer === null) {
            $this->writer = new Writer($this->path, new WriteOptions(
                useSharedStrings: $this->sharedStrings,
                creator: $this->creator,
                title: $this->title,
                subject: $this->subject,
                keywords: $this->keywords,
                description: $this->description,
                category: $this->category,
            ));
            // Built-in features are always available — no use() ceremony (T-3).
            $this->writer->use(new ChartPlugin(), new ImagePlugin(), new ValidationPlugin());
        }

        return $this->writer;
    }

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
        if (in_array(mb_strtolower($trimmed), $this->sheetNames, true)) {
            throw new WriteException(sprintf('Duplicate sheet name: "%s".', $trimmed));
        }

        return $trimmed;
    }

    private function assertNotStarted(): void
    {
        if ($this->writer !== null) {
            throw new RuntimeException('Document properties must be set before the first sheet.');
        }
    }

    private function assertNotSaved(): void
    {
        if ($this->saved) {
            throw new RuntimeException('Workbook has already been saved.');
        }
    }
}
