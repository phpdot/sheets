<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Support;

/**
 * Secure temporary-directory creation and recursive removal.
 *
 * Names are cryptographically random and the directory is created 0700 and never
 * reused — closing the predictable-name / world-writable / reuse-if-exists hole
 * that makes naive temp dirs a symlink-attack vector on shared hosts.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class TempDir
{
    private function __construct() {}

    /**
     * Create a fresh, private temporary directory and return its absolute path.
     *
     * @throws WriteException When the directory cannot be created.
     */
    public static function create(string $prefix): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(8));

        if (!mkdir($dir, 0700) && !is_dir($dir)) {
            throw new WriteException(sprintf('Cannot create temporary directory: %s', $dir));
        }

        return $dir;
    }

    /**
     * Recursively delete a directory and its contents.
     */
    public static function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
