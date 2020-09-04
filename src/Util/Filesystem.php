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
     * @param $directory
     * @param false $preserve
     * @return bool
     */
    public function deleteDirectory($directory, $preserve = false): bool
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
            }

            // If the item is just a file, we can go ahead and delete it since we're
            // just looping through and waxing all of the files in this directory
            // and calling directories recursively, so we delete the real path.
            else {
                $this->delete($item->getPathname());
            }
        }

        if (!$preserve) {
            @rmdir($directory);
        }

        return true;
    }

    /**
     * @param $directory
     * @return bool
     */
    public function isDirectory($directory): bool
    {
        return is_dir($directory);
    }

    /**
     * @param $paths
     * @return bool
     */
    public function delete($paths): bool
    {
        $paths = is_array($paths) ? $paths : func_get_args();

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
     * @param $path
     * @param int $mode
     * @param false $recursive
     * @param false $force
     * @return bool
     */
    public function makeDirectory($path, $mode = 0755, $recursive = false, $force = false): bool
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
}
