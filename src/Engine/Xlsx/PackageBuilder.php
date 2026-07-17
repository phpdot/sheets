<?php

declare(strict_types=1);

/**
 * The Open-Packaging substrate for the XLSX codec (zip + XML): XML parts,
 * streamed parts, binary media, relationships, and content types. This is the
 * seam that lets feature serializers add charts/images/etc. without the codec
 * knowing about them.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Xlsx;

interface PackageBuilder
{
    /**
     * Add a complete XML part at the given package path.
     *
     * @param string $path
     * @param string $xml
     *
     * @return void
     */
    public function addXmlPart(string $path, string $xml): void;

    /**
     * Open a part for streaming writes (for large bodies).
     *
     * @param string $path
     *
     * @return PartWriter
     */
    public function openPart(string $path): PartWriter;

    /**
     * Add a binary media part (e.g. an image) and return its package path.
     *
     * @param string $bytes
     * @param string $path
     *
     * @return string
     */
    public function addMediaPart(string $path, string $bytes): string;

    /**
     * Add a relationship from one part to a target and return the generated id.
     * Pass `$targetMode = "External"` for external targets (e.g. hyperlink URLs).
     *
     * @param string $fromPart
     * @param string $type
     * @param string $target
     * @param ?string $targetMode
     *
     * @return string
     */
    public function addRelationship(string $fromPart, string $type, string $target, ?string $targetMode = null): string;

    /**
     * Declare a content type for a part path or a file extension.
     *
     * @param string $partOrExtension
     * @param string $contentType
     *
     * @return void
     */
    public function registerContentType(string $partOrExtension, string $contentType): void;

    /**
     * Zip the assembled parts to the output path.
     *
     * @param string $outputPath
     *
     * @return void
     */
    public function finalizeZip(string $outputPath): void;
}
