<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\VcsInstaller\Util;

use FilesystemIterator;
use ErrorException;

class Filesystem
{
    /**
     * @param string $file
     * @return bool
     */
    public function exists(string $file): bool
    {
        return file_exists($file);
    }

    /**
     * @param string $directory
     * @param bool $preserve
     * @return bool
     */
    public function deleteDirectory(string $directory, bool $preserve = false): bool
    {
        if (!$this->isDirectory($directory)) {
            return false;
        }

        $items = new FilesystemIterator($directory);

        foreach ($items as $item) {
            // If the item is a directory, we can just recurse into the function and
            // delete that sub-directory otherwise we'll just delete the file and
            // keep iterating through each file until the directory is cleaned.
            if ($item->isDir() && !$item->isLink()) {
                $this->deleteDirectory($item->getPathname());
            } else {
                // If the item is just a file, we can go ahead and delete it since we're
                // just looping through and waxing all of the files in this directory
                // and calling directories recursively, so we delete the real path.
                $this->delete([$item->getPathname()]);
            }
        }

        if (!$preserve) {
            @rmdir($directory);
        }

        return true;
    }

    /**
     * @param string $directory
     * @return bool
     */
    public function isDirectory(string $directory): bool
    {
        return is_dir($directory);
    }

    /**
     * @param array $paths
     * @return bool
     */
    public function delete(array $paths): bool
    {
        $success = true;

        foreach ($paths as $path) {
            try {
                if (!@unlink($path)) {
                    $success = false;
                }
            } catch (ErrorException $e) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @param string $path
     * @param int $mode
     * @param bool $recursive
     * @param bool $force
     * @return bool
     */
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false): bool
    {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }

    /**
     * @param string $path
     * @return false|string
     */
    public function get(string $path)
    {
        if ($this->isFile($path)) {
            return file_get_contents($path);
        }

        throw new FileNotFoundException("File does not exist at path {$path}.");
    }

    /**
     * @param string $file
     * @return bool
     */
    public function isFile(string $file): bool
    {
        return is_file($file);
    }

    /**
     * Returns recursive directory iterator for given path with given pattern for files to find
     *
     * @param string $dir
     * @param string $filePattern File pattern to find
     * @param string $excludePattern Path pattern to exclude
     * @return \RegexIterator
     */
    public function getRecursiveFileIterator(
        string $dir,
        string $filePattern,
        string $excludePattern = ''
    ): \RegexIterator {
        $dirIterator = new \RecursiveDirectoryIterator($dir);
        $recursiveDirIterator = new \RecursiveIteratorIterator($dirIterator);
        if ($excludePattern) {
            $recursiveDirIterator = new \RegexIterator($recursiveDirIterator, $excludePattern, \RegexIterator::MATCH);
        }

        return new \RegexIterator($recursiveDirIterator, $filePattern, \RegexIterator::MATCH);
    }
}
