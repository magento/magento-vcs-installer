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
     * Types of supported Magento component packages
     */
    private const COMPONENT_PACKAGE_TYPES = [
        'magento2-module',
        'magento2-theme',
        'magento2-language',
        'magento2-library'
    ];

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
     * @inheritDoc
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
            $this->io->write('No VCS repositories defined');

            return;
        }

        $strategy = $extra['deploy']['strategy'] ?? CopierFactory::STRATEGY_SYMLINK;

        if (!empty($_ENV['MAGENTO_CLOUD_TREE_ID'])) {
            $this->io->write(sprintf(
                'Detected Cloud environment, switching strategy to "%s"',
                CopierFactory::STRATEGY_COPY
            ));

            $strategy = CopierFactory::STRATEGY_COPY;
        }

        $composerAutoload = [$composer->getPackage()->getAutoload()];
        $composerRequire = [$composer->getPackage()->getRequires()];

        foreach ($extra['deploy']['repo'] as $name => $meta) {
            $repoDirectory = $vendorDir . DIRECTORY_SEPARATOR . $name;

            $this->download($composer, $repoDirectory, $name, $meta);

            $repoComposerFile = $repoDirectory . DIRECTORY_SEPARATOR . 'composer.json';

            if ($this->filesystem->exists($repoComposerFile)) {
                $repoComposer = Factory::create(new NullIO(), $repoComposerFile);

                $this->io->write('Project type: ' .  $repoComposer->getPackage()->getType());

                if ($repoComposer->getPackage()->getType() === CopierFactory::TYPE_PROJECT) {
                    $composerAutoload[] = $repoComposer->getPackage()->getAutoload();
                    $composerRequire[] = $repoComposer->getPackage()->getRequires();

                    $this->io->write(sprintf(
                        'Copying "%s(%s)" => %s using "%s" strategy',
                        $name,
                        $repoDirectory,
                        $rootDir,
                        $strategy
                    ));

                    $this->copierFactory->create($strategy)
                        ->copy($repoDirectory, $rootDir);
                }
            } else {
                $this->processNonComposerPackage($repoDirectory, $rootDir, $strategy);
            }
        }

        $this->io->write('Updating composer.lock');

        $composer->getPackage()->setAutoload(array_replace(...$composerAutoload));
        $composer->getPackage()->setRequires(array_replace(...$composerRequire));
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

        if ($this->filesystem->exists($repoDirectory . '/composer.json')) {
            $this->io->write(sprintf('Updating "%s"', $name));

            $composer->getConfig()->merge(['config' => ['discard-changes' => true]]);
            $composer->getDownloadManager()->update($package, $package, $repoDirectory);
        } else {
            $this->io->write(sprintf('Cleaning "%s"', $name));

            $this->filesystem->deleteDirectory($repoDirectory);

            $this->io->write(sprintf('Installing "%s"', $name));

            // @phpstan-ignore-next-line
            if (version_compare(PluginInterface::PLUGIN_API_VERSION, '2.0.0', '<')) {
                $composer->getDownloadManager()->download($package, $repoDirectory);
            } else {
                $composer->getDownloadManager()->install($package, $repoDirectory);
            }
        }
    }

    /**
     * Processes repositories with magento modules in the root
     *
     * @param string $repoDirectory
     * @param string $rootDir
     * @param string $strategy
     */
    private function processNonComposerPackage(string $repoDirectory, string $rootDir, string $strategy): void
    {
        $dirIterator = $this->filesystem->getRecursiveFileIterator(
            $repoDirectory,
            '/composer.json$/'
        );

        foreach ($dirIterator as $currentFileInfo) {
            $packageInfo = json_decode($this->filesystem->get($currentFileInfo->getPathName()), true);
            if ($this->isComponentPackage($packageInfo)) {
                $this->io->write(sprintf(
                    'Copying "%s(%s)" => %s using "%s" strategy',
                    basename($currentFileInfo->getPath()),
                    $currentFileInfo->getPath(),
                    $rootDir . '/app/code/Magento/' . basename($currentFileInfo->getPath()),
                    $strategy
                ));

                $this->copierFactory->create($strategy)
                    ->copy(
                        $currentFileInfo->getPath(),
                        $rootDir . '/app/code/Magento/' . basename($currentFileInfo->getPath())
                    );
            }
        }
    }

    /**
     * Check if provided package info belongs to a Magento component package
     *
     * @param array $packageInfo
     * @return bool
     */
    private function isComponentPackage(array $packageInfo): bool
    {
        return isset($packageInfo['type']) && in_array($packageInfo['type'], self::COMPONENT_PACKAGE_TYPES);
    }

    /**
     * @inheritDoc
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @inheritDoc
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }
}
