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
        public readonly ?string $creator = null,
        public readonly ?string $title = null,
        public readonly ?string $subject = null,
        public readonly ?string $keywords = null,
        public readonly ?string $description = null,
        public readonly ?string $category = null,
    ) {}
}
