<?php

declare(strict_types=1);

/**
 * Immutable options governing a write operation.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

final class WriteOptions
{
    /**
     * Configures a write: shared-string interning and the document metadata fields.
     *
     * @param bool $useSharedStrings
     * @param ?string $creator
     * @param ?string $title
     * @param ?string $subject
     * @param ?string $keywords
     * @param ?string $description
     * @param ?string $category
     */
    public function __construct(
        public readonly bool $useSharedStrings = false,
        public readonly null|string $creator = null,
        public readonly null|string $title = null,
        public readonly null|string $subject = null,
        public readonly null|string $keywords = null,
        public readonly null|string $description = null,
        public readonly null|string $category = null,
    ) {}
}
