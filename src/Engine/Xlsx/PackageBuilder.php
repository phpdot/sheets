<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Xlsx;

/**
 * The Open-Packaging substrate for the XLSX codec (zip + XML): XML parts,
 * streamed parts, binary media, relationships, and content types. This is the
 * seam that lets feature serializers add charts/images/etc. without the codec
 * knowing about them.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface PackageBuilder
{
    /**
     * Add a complete XML part at the given package path.
     */
    public function addXmlPart(string $path, string $xml): void;

    /**
     * Open a part for streaming writes (for large bodies).
     */
    public function openPart(string $path): PartWriter;

    /**
     * Add a binary media part (e.g. an image) and return its package path.
     */
    public function addMediaPart(string $path, string $bytes): string;

    /**
     * Add a relationship from one part to a target and return the generated id.
     * Pass `$targetMode = "External"` for external targets (e.g. hyperlink URLs).
     */
    public function addRelationship(string $fromPart, string $type, string $target, ?string $targetMode = null): string;

    /**
     * Declare a content type for a part path or a file extension.
     */
    public function registerContentType(string $partOrExtension, string $contentType): void;

    /**
     * Zip the assembled parts to the output path.
     */
    public function finalizeZip(string $outputPath): void;
}
