<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\VcsInstaller;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Magento\VcsInstaller\Plugin\Linker;
use Magento\VcsInstaller\Util\Filesystem;
use Composer\Script\Event as ScriptEvent;

/**
 * General plugin.
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    private const CALLBACK_PRIORITY = 40000;

    private const DIR = '.dev';

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var Linker
     */
    private $linker;

    /**
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
        $this->filesystem = new Filesystem();
        $this->linker = new Linker();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_AUTOLOAD_DUMP => ['onInstallUpdateOrDump', self::CALLBACK_PRIORITY]
        ];
    }

    /**
     * @param ScriptEvent $event
     */
    public function onInstallUpdateOrDump(ScriptEvent $event): void
    {

        $composer = $event->getComposer();
        $root = dirname($composer->getConfig()->get('vendor-dir'));

        $extra = $composer->getPackage()->getExtra();

        if (empty($extra['deploy']['repo'])) {
            return;
        }

        foreach ($extra['deploy']['repo'] as $name => $meta) {
            $repoDirectory = $root . DIRECTORY_SEPARATOR . self::DIR . DIRECTORY_SEPARATOR . $name;

            $this->io->write(sprintf(
                'Recreating directory %s',
                $repoDirectory
            ));

            if ($this->filesystem->exists($repoDirectory)) {
                $this->filesystem->deleteDirectory($repoDirectory);
            }

            $this->filesystem->makeDirectory($repoDirectory, 0755, true);

            $this->io->write(sprintf(
                'Cloning "%s" => %s',
                $name,
                $repoDirectory
            ));

            $package = new Package($name, $meta['ref'], $meta['ref']);
            $package->setSourceType('git');
            $package->setSourceUrl($meta['url']);
            $package->setSourceReference($meta['ref']);

            $composer->getDownloadManager()->download($package, $repoDirectory);

            if (!empty($meta['base'])) {
                $this->io->write(sprintf(
                    'Linking "%s(%s)" => %s',
                    $name,
                    $repoDirectory,
                    $root
                ));

                $this->linker->link($repoDirectory, $root);
            }
        }
    }
}
