<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\VcsInstaller\Plugin;

class Copy implements CopierInterface
{
    /**
     * @var string[]
     */
    private static $exclude = [
        '.git',
        'vendor',
        'composer.json',
        'composer.lock'
    ];

    /**
     * @inheritDoc
     */
    public function copy(string $from, string $to): void
    {
        $excludeStr = '';

        foreach (self::$exclude as $exclude) {
            $excludeStr .= sprintf(" --exclude='%s'", $exclude);
        }

        shell_exec(sprintf("rsync -azhm --stats %s %s/* %s/", $excludeStr, $from, $to));
    }

    /**
     * @param string $path
     */
    public function restore(string $path): void
    {
        // Not implemented.
    }
}
