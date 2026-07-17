<?php

declare(strict_types=1);

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

namespace PHPdot\Sheets\Engine\Support;

final class TempDir
{
    /**
     * Not instantiable — the class exposes only static temporary-directory helpers.
     */
    private function __construct() {}

    /**
     * Create a fresh, private temporary directory and return its absolute path.
     *
     * @param string $prefix
     *
     * @throws WriteException When the directory cannot be created.
     *
     * @return string
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
     *
     * @param string $dir
     *
     * @return void
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
