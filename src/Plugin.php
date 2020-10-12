<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\VcsInstaller;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\Package;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Magento\VcsInstaller\Plugin\CopierFactory;
use Magento\VcsInstaller\Util\Filesystem;
use Composer\Script\Event as ScriptEvent;

/**
 * General plugin.
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    private const CALLBACK_PRIORITY = 40000;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var CopierFactory
     */
    private $copierFactory;

    /**
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
        $this->filesystem = new Filesystem();
        $this->copierFactory = new CopierFactory();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_INSTALL_CMD => ['onInstallUpdateOrDump', self::CALLBACK_PRIORITY],
            ScriptEvents::PRE_UPDATE_CMD => ['onInstallUpdateOrDump', self::CALLBACK_PRIORITY],
            ScriptEvents::PRE_AUTOLOAD_DUMP => ['onInstallUpdateOrDump', self::CALLBACK_PRIORITY],
        ];
    }

    /**
     * @param ScriptEvent $event
     */
    public function onInstallUpdateOrDump(ScriptEvent $event): void
    {
        $composer = $event->getComposer();
        $extra = $composer->getPackage()->getExtra();

        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $rootDir = dirname($vendorDir);

        if (empty($extra['deploy']['repo'])) {
            return;
        }

        $strategy = CopierFactory::STRATEGY_SYMLINK;

        if (!empty($extra['deploy']['strategy'])) {
            $strategy = $extra['deploy']['strategy'];
        }

        if (!empty($_ENV['MAGENTO_CLOUD_TREE_ID'])) {
            $this->io->write(sprintf(
                'Detected Cloud environment, switching strategy to "%s"',
                CopierFactory::STRATEGY_COPY
            ));

            $strategy = CopierFactory::STRATEGY_COPY;
        }

        $composerAutoload = $composer->getPackage()->getAutoload();
        $composerRequire = $composer->getPackage()->getRequires();

        foreach ($extra['deploy']['repo'] as $name => $meta) {
            $repoDirectory = $vendorDir . DIRECTORY_SEPARATOR . $name;

            $this->download($composer, $repoDirectory, $name, $meta);

            $repoComposerFile = $repoDirectory . DIRECTORY_SEPARATOR . 'composer.json';

            if ($this->filesystem->exists($repoComposerFile)) {
                $repoComposer = Factory::create(new NullIO(), $repoComposerFile);

                $composerAutoload = array_replace($composerAutoload, $repoComposer->getPackage()->getAutoload());
                $composerRequire = array_replace($composerRequire, $repoComposer->getPackage()->getRequires());
            }

            if (!empty($meta['base'])) {
                $this->io->write(sprintf(
                    'Copying "%s(%s)" => %s using "%s" strategy',
                    $name,
                    $repoDirectory,
                    $rootDir,
                    $strategy
                ));

                $this->copierFactory->create($strategy)->copy($repoDirectory, $rootDir);
            }
        }

        $this->io->write('Updating composer.lock');

        $composer->getPackage()->setAutoload($composerAutoload);
        $composer->getPackage()->setRequires($composerRequire);
    }

    /**
     * @param Composer $composer
     * @param string $repoDirectory
     * @param string $name
     * @param array $meta
     */
    private function download(Composer $composer, string $repoDirectory, string $name, array $meta): void
    {
        $this->io->write(sprintf('Cloning "%s" => %s', $name, $repoDirectory));

        $version = $meta['ref'];
        $normalizedVersion = preg_replace('{(?:^dev-|(?:\.x)?-dev$)}i', '', $version);

        $package = new Package($name, $version, $version);
        $package->setSourceType('git');
        $package->setSourceUrl($meta['url']);
        $package->setSourceReference($normalizedVersion);
        $package->setInstallationSource('source');
        $package->setType('project');

        if (false && $this->filesystem->exists($repoDirectory . '/composer.json')) {
            $this->io->write(sprintf('Updating "%s"', $name));

            $composer->getConfig()->merge(['config' => ['discard-changes' => true]]);
            $composer->getDownloadManager()->update($package, $package, $repoDirectory);
        } else {
            $this->io->write(sprintf('Installing "%s"', $name));

            $composer->getDownloadManager()->download($package, $repoDirectory, true);
        }
    }
}
