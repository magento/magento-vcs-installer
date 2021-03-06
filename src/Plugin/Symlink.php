<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\VcsInstaller\Plugin;

use Generator;

/**
 * Links files between folders.
 */
class Symlink implements CopierInterface
{
    /**
     * @var string[]
     */
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
    public function copy(string $from, string $to): void
    {
        foreach ($this->scanFiles($from) as $filename) {
            $target = preg_replace('#^' . preg_quote($from, '/') . "#", '', $filename);

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

    /**
     * @param string $path
     */
    public function restore(string $path): void
    {
        foreach ($this->scanFiles($path) as $filename) {
            if (is_link($filename)) {
                $this->unlinkFile($filename);
            }
        }
    }

    /**
     * Scan all files from Magento root
     *
     * @param string $path
     * @return Generator
     */
    private function scanFiles(string $path): Generator
    {
        foreach (glob($path . DIRECTORY_SEPARATOR . '*') as $filename) {
            yield $filename;

            if (is_dir($filename)) {
                yield from $this->scanFiles($filename);
            }
        }
    }

    /**
     * OS depends unlink
     *
     * @param string $filename
     * @return void
     */
    private function unlinkFile(string $filename): void
    {
        stripos(PHP_OS_FAMILY, 'WIN') === 0 && is_dir($filename) ? @rmdir($filename) : @unlink($filename);
    }
}
