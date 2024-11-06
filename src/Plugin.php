<?php

namespace Thesebas\ArtifactInstall;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Util\GitHub;
use function array_key_exists;
use function explode;

/**
 * Composer artifacts plugin.
 *
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * The composer input/output.
     *
     * @var IOInterface
     */
    private IOInterface $io;


    /**
     * The composer instance.
     * @var Composer
     */
    private Composer $composer;

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
        $this->io->debug('Activating plugin');
        $this->composer = $composer;
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
     * @param PreFileDownloadEvent $event
     *   The event.
     */
    public function patchDownloadSource(PreFileDownloadEvent $event): void
    {
        $package = $event->getContext();
        if (!($package instanceof PackageInterface)) {
            return;
        }

        if (array_key_exists('artifacts', $package->getExtra())) {
            $this->io->debug("processing {$package->getPrettyName()}");
            $event->setProcessedUrl($this->getPackageDistUrl($package));
            $package->setDistType($this->getPackageDistType($package));
        } else {
            $this->io->debug("missing extra.artifacts in {$package->getPrettyName()}, skip");
        }
    }

    /**
     * Custom callback that returns tokens from the package.
     *
     * @param PackageInterface $package
     *   The package.
     *
     * @return string[]
     *   An array of tokens and values.
     */
    static function getPluginTokens(PackageInterface $package): array
    {
        [$vendorName, $projectName] = explode(
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
     * @param PackageInterface $package
     *
     * @return string
     */
    private function getPackageDistUrl(PackageInterface $package): string
    {
        $config = $package->getExtra()['artifacts'];
        $tokens = self::getPluginTokens($package);
        $config = array_map(function ($val) use ($tokens) {
            return strtr($val, $tokens);
        }, $config);
        if (array_key_exists('source', $config)) {
            switch ($config['source']) {
                case 'github-release-asset':
                    $package->setTransportOptions(['http' => ['header' => ['Accept: application/octet-stream']]]);
                    $httpDownloader = Factory::createHttpDownloader($this->io, $this->composer->getConfig());
                    $res = $httpDownloader->get("https://api.github.com/repos/{$config['repo']}/releases/tags/{$config['tag']}");
                    $resBody = $res->decodeJson();
                    foreach ($resBody['assets'] as $asset) {
                        if ($asset['name'] === $config['file']) {
                            return $asset['url'];
                        }
                    }
                    throw new \RuntimeException('Missing matching asset');
                default:
                    throw new \UnexpectedValueException('Unsupported source type');
            }
        } else {

            return $config['url'];
        }
    }

    /**
     * @param PackageInterface $package
     *
     * @return string
     */
    private function getPackageDistType(PackageInterface $package): string
    {
        $tokens = self::getPluginTokens($package);
        return strtr($package->getExtra()['artifacts']['type'] ?? 'zip', $tokens);
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        $this->io->debug('Deactivating plugin');
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $this->io->debug('Uninstalling plugin');
    }
}