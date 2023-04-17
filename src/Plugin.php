<?php

namespace Thesebas\ArtifactInstall;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;


/**
 * Composer artifacts plugin.
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Artifacts configuration.
     *
     * @var string[]
     */
    private $config;

    /**
     * The composer input/output.
     *
     * @var IOInterface
     */
    private IOInterface $io;

    /**
     * Get the configuration.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
        $this->io->info('Activating plugin');
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            PluginEvents::PRE_FILE_DOWNLOAD => 'patchDownloadSource',
        ];
    }

    /**
     * Custom event handler to change configuration for artifacts.
     *
     * @param \Composer\Plugin\PreFileDownloadEvent $event
     *   The event.
     */
    public function patchDownloadSource(PreFileDownloadEvent $event): void
    {
        $package = $event->getContext();
        if ($package instanceof PackageInterface && \array_key_exists('artifacts', $package->getExtra())) {
            $event->setProcessedUrl($this->getPackageDistUrl($package));
            $package->setDistType($this->getPackageDistType($package));
        }
    }

    /**
     * Custom callback that returns tokens from the package.
     *
     * @param \Composer\Package\PackageInterface $package
     *   The package.
     *
     * @return string[]
     *   An array of tokens and values.
     */
    static function getPluginTokens(PackageInterface $package): array
    {
        [$vendorName, $projectName] = \explode(
            '/',
            $package->getPrettyName(),
            2
        );

        return [
            '{vendor-name}' => $vendorName,
            '{project-name}' => $projectName,
            '{pretty-version}' => $package->getPrettyVersion(),
            '{version}' => $package->getVersion(),
            '{name}' => $package->getName(),
            '{pretty-name}' => $package->getName(),
            '{stability}' => $package->getStability(),
            '{type}' => $package->getType(),
            '{checksum}' => $package->getDistSha1Checksum(),
        ];
    }

    /**
     * @param \Composer\Package\PackageInterface $package
     *
     * @return string
     */
    private function getPackageDistUrl(PackageInterface $package): string
    {
        $tokens = self::getPluginTokens($package);
        return strtr($package->getExtra()['url'], $tokens);
    }

    /**
     * @param \Composer\Package\PackageInterface $package
     *
     * @return string
     */
    private function getPackageDistType(PackageInterface $package): string
    {
        $tokens = self::getPluginTokens($package);
        return strtr($package->getExtra()['type'] ?? 'zip', $tokens);
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        $this->io->info('Deactivating plugin');
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $this->io->info('Uninstalling plugin');
    }
}