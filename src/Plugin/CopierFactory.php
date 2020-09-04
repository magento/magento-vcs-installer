<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\VcsInstaller\Plugin;

class CopierFactory
{
    public const STRATEGY_SYMLINK = 'symlink';

    /**
     * @var string[]
     */
    private static $strategies = [
        self::STRATEGY_SYMLINK => Symlink::class
    ];

    /**
     * @param string $strategy
     * @return mixed
     *
     * @throws CopierException
     */
    public function create(string $strategy): CopierInterface
    {
        if (!isset(self::$strategies[$strategy])) {
            throw new CopierException('Wrong strategy');
        }

        return new self::$strategies[$strategy];
    }
}
