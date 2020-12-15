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
    public const STRATEGY_COPY = 'copy';

    public const TYPE_PROJECT = 'project';

    /**
     * @var string[]
     */
    private static $strategies = [
        self::STRATEGY_SYMLINK => Symlink::class,
        self::STRATEGY_COPY => Copy::class
    ];

    /**
     * @param string $strategy
     * @return CopierInterface
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
