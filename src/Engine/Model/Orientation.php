<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

/**
 * Page orientation for printing.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
enum Orientation: string
{
    case Portrait = 'portrait';
    case Landscape = 'landscape';
}
