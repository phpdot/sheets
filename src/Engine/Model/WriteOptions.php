<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

/**
 * Immutable options governing a write operation.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class WriteOptions
{
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
