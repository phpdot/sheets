<?php

declare(strict_types=1);

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

namespace PHPdot\Sheets\Engine\Xlsx;

use PHPdot\Sheets\Engine\Support\TempDir;
use PHPdot\Sheets\Engine\Support\WriteException;
use PHPdot\Sheets\Engine\Support\Xml;

final class ZipPackageBuilder implements PackageBuilder
{
    private readonly string $tempDir;

    /**
     * @var array<string, string> Extension => content type.
     */

    private array $defaultContentTypes = [];

    /**
     * @var array<string, string> "/part/name" => content type.
     */

    private array $overrideContentTypes = [];

    /**
     * @var array<string, list<array{id: string, type: string, target: string, targetMode: string|null}>>
     */

    private array $relationships = [];

    /**
     * @var array<string, int> Rels-file path => last rId number.
     */

    private array $relCounters = [];

    /**
     * __construct.
     */
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

    /**
     * Writes the [Content_Types].xml part declaring every default and override content type.
     *
     * @return void
     */
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

    /**
     * Writes each _rels/*.rels part from the accumulated relationships.
     *
     * @return void
     */
    private function writeRelationshipFiles(): void
    {
        foreach ($this->relationships as $relsPath => $rels) {
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

            foreach ($rels as $rel) {
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

    /**
     * Packages the staged temp directory into the final .xlsx zip archive at the given path.
     *
     * @param string $outputPath
     *
     * @return void
     */
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

    /**
     * Returns the _rels/*.rels path that holds relationships for the given part.
     *
     * @param string $fromPart
     *
     * @return string
     */
    private function relsPathFor(string $fromPart): string
    {
        if ($fromPart === '') {
            return '_rels/.rels';
        }

        $dir = dirname($fromPart);
        $prefix = $dir === '.' ? '' : $dir . '/';

        return $prefix . '_rels/' . basename($fromPart) . '.rels';
    }

    /**
     * Writes a staged part to the temp directory, creating parent directories as needed.
     *
     * @param string $path
     * @param string $content
     *
     * @return void
     */
    private function writeFile(string $path, string $content): void
    {
        $full = $this->ensureDirectory($path);

        if (file_put_contents($full, $content) === false) {
            throw new WriteException(sprintf('Cannot write part: %s', $path));
        }
    }

    /**
     * Ensures the staged part's parent directory exists and returns its absolute path.
     *
     * @param string $path
     *
     * @return string
     */
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
