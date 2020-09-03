<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\VcsInstaller\Plugin;

/**
 * Links files between folders.
 */
class Linker
{
    private static $excluded = [
        '/.git',
        '/composer.json',
        '/composer.lock',
        '/vendor',
        '/var',
    ];

    /**
     * @param string $from
     * @param string $to
     */
    public function link(string $from, string $to): void
    {
        foreach ($this->scanFiles($from) as $filename) {
            $target = preg_replace('#^' . preg_quote($from) . "#", '', $filename);

            if (in_array($filename, self::$excluded, true)) {
                continue;
            }

            if (!file_exists(dirname($to . $target))) {
                @symlink(dirname($filename), dirname($to . $target));
            } elseif (!file_exists($to . $target)) {
                if (is_link(dirname($to . $target))) {
                    continue;
                }
                @symlink($filename, $to . $target);
            } else {
                continue;
            }
        }

        foreach ($this->scanFiles($to) as $filename) {
            if (is_link($filename) && !file_exists($filename)) {
                $this->unlinkFile($filename);
            }
        }
    }

    public function unlink(string $to): void
    {
        foreach ($this->scanFiles($to) as $filename) {
            if (is_link($filename)) {
                $this->unlinkFile($filename);
            }
        }
    }

    /**
     * Scan all files from Magento root
     *
     * @param string $path
     * @return array
     */
    private function scanFiles(string $path): array
    {
        $results = [];
        foreach (glob($path . DIRECTORY_SEPARATOR . '*') as $filename) {

            $results[] = $filename;
            if (is_dir($filename)) {
                $results = array_merge($results, $this->scanFiles($filename));
            }
        }

        return $results;
    }

    /**
     * OS depends unlink
     *
     * @param string $filename
     * @return void
     */
    private function unlinkFile($filename): void
    {
        stripos(PHP_OS, 'WIN') === 0 && is_dir($filename) ? @rmdir($filename) : @unlink($filename);
    }

    /**
     * Resolve path to Unix format
     *
     * @param string $path
     * @return string
     */
    private function resolvePath($path)
    {
        return ltrim(str_replace('\\', '/', $path), '/');
    }
}
