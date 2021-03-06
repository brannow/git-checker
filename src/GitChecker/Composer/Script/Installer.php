<?php
namespace IchHabRecht\GitChecker\Composer\Script;

use Composer\Installer\InstallationManager;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

class Installer
{
    /**
     * @var Filesystem
     */
    protected static $fileSystem;

    /**
     * @var InstallationManager
     */
    protected static $installationManager;

    /**
     * @var InstalledRepositoryInterface
     */
    protected static $localRepository;

    /**
     * @param Event $event
     */
    public static function postInstall(Event $event)
    {
        static::$installationManager = $event->getComposer()->getInstallationManager();
        static::$localRepository = $event->getComposer()->getRepositoryManager()->getLocalRepository();

        $rootDirectory = __DIR__ . '/../../../../';
        static::copyFile('twbs/bootstrap', 'dist/css/bootstrap.min.css', $rootDirectory . 'public/css/bootstrap.min.css');
        static::mirrorDirectory('twbs/bootstrap', 'dist/fonts', $rootDirectory . 'public/fonts');
        if (!file_exists($rootDirectory . 'app/settings.yml')) {
            static::getFileSystem()->copy($rootDirectory . 'app/settings.example.yml', $rootDirectory . 'app/settings.yml', true);
        }
    }

    /**
     * @param string $packageName
     * @param string $sourceFile
     * @param string $targetFile
     * @param bool $override
     */
    protected static function copyFile($packageName, $sourceFile, $targetFile, $override = false)
    {
        if (!$override && file_exists($targetFile)) {
            return;
        }
        $packages = static::$localRepository->findPackages($packageName, null);
        foreach ($packages as $package) {
            if (static::$installationManager->getInstaller($package->getType())->isInstalled(static::$localRepository, $package)) {
                static::getFileSystem()->copy(
                    static::$installationManager->getInstallPath($package) . '/' . ltrim($sourceFile, '/'),
                    $targetFile,
                    $override
                );

                return;
            }
        }
    }

    /**
     * @param string $packageName
     * @param string $sourceDirectory
     * @param string $targetDirectory
     */
    protected static function mirrorDirectory($packageName, $sourceDirectory, $targetDirectory)
    {
        $packages = static::$localRepository->findPackages($packageName, null);
        foreach ($packages as $package) {
            if (static::$installationManager->getInstaller($package->getType())->isInstalled(static::$localRepository, $package)) {
                static::getFileSystem()->mirror(
                    static::$installationManager->getInstallPath($package) . '/' . ltrim($sourceDirectory, '/'),
                    $targetDirectory,
                    null,
                    [
                        'copy_on_windows',
                    ]
                );

                return;
            }
        }
    }

    /**
     * @return Filesystem
     */
    protected static function getFileSystem()
    {
        if (static::$fileSystem === null) {
            static::$fileSystem = new Filesystem();
        }

        return static::$fileSystem;
    }
}
