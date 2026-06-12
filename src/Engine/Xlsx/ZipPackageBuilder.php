<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Xlsx;

use PHPdot\Sheets\Engine\Support\TempDir;
use PHPdot\Sheets\Engine\Support\WriteException;
use PHPdot\Sheets\Engine\Support\Xml;

/**
 * Assembles an XLSX Open-Packaging archive on disk: XML/streamed/media
 * parts plus accumulated content types and relationships, zipped at finalize.
 *
 * Owns content-type and relationship aggregation so codec parts and feature
 * serializer parts compose without either knowing about the other.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ZipPackageBuilder implements PackageBuilder
{
    private readonly string $tempDir;

    /** @var array<string, string> Extension => content type. */
    private array $defaultContentTypes = [];

    /** @var array<string, string> "/part/name" => content type. */
    private array $overrideContentTypes = [];

    /** @var array<string, list<array{id: string, type: string, target: string, targetMode: string|null}>> Rels-file path => relationships. */
    private array $relationships = [];

    /** @var array<string, int> Rels-file path => last rId number. */
    private array $relCounters = [];

    public function __construct()
    {
        $this->tempDir = TempDir::create('phpdot_sheets_');
    }

    /**
     * Remove the staging directory if the package was abandoned before
     * `finalizeZip()` — without this, a writer dropped mid-write (e.g. an
     * exception between open and close) strands its temp files forever, which
     * matters in long-lived Swoole workers where nothing sweeps the temp dir.
     */
    public function __destruct()
    {
        TempDir::remove($this->tempDir);
    }

    public function addXmlPart(string $path, string $xml): void
    {
        $this->writeFile($path, $xml);
    }

    public function openPart(string $path): PartWriter
    {
        $full = $this->ensureDirectory($path);
        $handle = fopen($full, 'wb');

        if ($handle === false) {
            throw new WriteException(sprintf('Cannot open part for writing: %s', $path));
        }

        return new StreamPartWriter($handle);
    }

    public function addMediaPart(string $path, string $bytes): string
    {
        $this->writeFile($path, $bytes);

        return $path;
    }

    public function addRelationship(string $fromPart, string $type, string $target, ?string $targetMode = null): string
    {
        $relsPath = $this->relsPathFor($fromPart);
        $next = ($this->relCounters[$relsPath] ?? 0) + 1;
        $this->relCounters[$relsPath] = $next;
        $id = 'rId' . $next;

        $this->relationships[$relsPath][] = ['id' => $id, 'type' => $type, 'target' => $target, 'targetMode' => $targetMode];

        return $id;
    }

    public function registerContentType(string $partOrExtension, string $contentType): void
    {
        if (str_contains($partOrExtension, '/')) {
            $this->overrideContentTypes['/' . ltrim($partOrExtension, '/')] = $contentType;
        } else {
            $this->defaultContentTypes[$partOrExtension] = $contentType;
        }
    }

    public function finalizeZip(string $outputPath): void
    {
        try {
            $this->writeContentTypes();
            $this->writeRelationshipFiles();
            $this->zip($outputPath);
        } finally {
            TempDir::remove($this->tempDir);
        }
    }

    private function writeContentTypes(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';

        foreach ($this->defaultContentTypes as $extension => $contentType) {
            $xml .= '<Default Extension="' . Xml::attribute($extension)
                . '" ContentType="' . Xml::attribute($contentType) . '"/>';
        }
        foreach ($this->overrideContentTypes as $part => $contentType) {
            $xml .= '<Override PartName="' . Xml::attribute($part)
                . '" ContentType="' . Xml::attribute($contentType) . '"/>';
        }

        $xml .= '</Types>';

        $this->writeFile('[Content_Types].xml', $xml);
    }

    private function writeRelationshipFiles(): void
    {
        foreach ($this->relationships as $relsPath => $rels) {
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

            foreach ($rels as $rel) {
                // Targets carry user data (hyperlink URLs) — unescaped, a `&`
                // in a query string corrupts the whole rels part.
                $xml .= '<Relationship Id="' . Xml::attribute($rel['id'])
                    . '" Type="' . Xml::attribute($rel['type'])
                    . '" Target="' . Xml::attribute($rel['target']) . '"';
                if ($rel['targetMode'] !== null) {
                    $xml .= ' TargetMode="' . Xml::attribute($rel['targetMode']) . '"';
                }
                $xml .= '/>';
            }

            $xml .= '</Relationships>';

            $this->writeFile($relsPath, $xml);
        }
    }

    private function zip(string $outputPath): void
    {
        $zip = new \ZipArchive();
        $result = $zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new WriteException(sprintf('Cannot create archive "%s" (code %d).', $outputPath, $result));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $prefixLength = strlen($this->tempDir) + 1;

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $local = str_replace('\\', '/', substr($file->getPathname(), $prefixLength));

            if (!$zip->addFile($file->getPathname(), $local)) {
                throw new WriteException(sprintf('Cannot add "%s" to the archive.', $local));
            }
        }

        if (!$zip->close()) {
            throw new WriteException(sprintf('Cannot finalize the archive "%s".', $outputPath));
        }
    }

    private function relsPathFor(string $fromPart): string
    {
        if ($fromPart === '') {
            return '_rels/.rels';
        }

        $dir = dirname($fromPart);
        $prefix = $dir === '.' ? '' : $dir . '/';

        return $prefix . '_rels/' . basename($fromPart) . '.rels';
    }

    private function writeFile(string $path, string $content): void
    {
        $full = $this->ensureDirectory($path);

        if (file_put_contents($full, $content) === false) {
            throw new WriteException(sprintf('Cannot write part: %s', $path));
        }
    }

    private function ensureDirectory(string $path): string
    {
        $full = $this->tempDir . '/' . $path;
        $dir = dirname($full);

        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new WriteException(sprintf('Cannot create directory for part: %s', $path));
        }

        return $full;
    }
}
