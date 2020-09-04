<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\VcsInstaller\Plugin;

interface CopierInterface
{
    /**
     * Copy content to desired directory.
     *
     * @param string $from
     * @param string $to
     */
    public function copy(string $from, string $to): void;

    /**
     * Restore content if possible.
     *
     * @param string $path
     */
    public function restore(string $path): void;
}
