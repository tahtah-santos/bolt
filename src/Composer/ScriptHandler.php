<?php
/**
 * Based on Sensio\Bundle\DistributionBundle\Composer\ScriptHandler
 * @see https://github.com/sensio/SensioDistributionBundle/blob/master/Composer/ScriptHandler.php
 */

namespace Bolt\Composer;

use Symfony\Component\Filesystem\Filesystem;
use Composer\Script\CommandEvent;
use Composer\Script\PackageEvent;

class ScriptHandler
{
    /**
     *
     * @param CommandEvent $event
     */
    public static function installAssets(CommandEvent $event, $options = false)
    {
        if (false === $options) {
            $options = self::getOptions($event);
        }
        $webDir = $options['bolt-web-dir'];
        $dirMode = $options['bolt-dir-mode'];
        if (is_string($dirMode)) {
            $dirMode = octdec($dirMode);
        }

        umask(0777 - $dirMode);

        if (!is_dir($webDir)) {
            echo 'The bolt-web-dir (' . $webDir . ') specified in composer.json was not found in ' . getcwd() . ', can not install assets.' . PHP_EOL;

            return;
        }

        $targetDir = $webDir . '/bolt-public/';

        $filesystem = new Filesystem();
        $filesystem->remove($targetDir);
        $filesystem->mkdir($targetDir, $dirMode);

        foreach (array('css', 'fonts', 'img', 'js', 'lib') as $dir) {
            $filesystem->mirror(__DIR__ . '/../../app/view/' . $dir, $targetDir . '/view/' . $dir);
        }

        if (!$filesystem->exists($webDir . '/files/')) {
            $filesystem->mirror(__DIR__ . '/../../files', $webDir . '/files');
        }

        if (!$filesystem->exists($webDir . '/theme/')) {
            $filesystem->mkdir($webDir . '/theme/', $dirMode);
        }

        // The first check handles the case where the bolt-web-dir is different to the root.
        // If thie first works, then the second won't need to run
        if (!$filesystem->exists(getcwd() . '/extensions/')) {
            $filesystem->mkdir(getcwd() . '/extensions/', $dirMode);
        }

        if (!$filesystem->exists($webDir . '/extensions/')) {
            $filesystem->mkdir($webDir . '/extensions/', $dirMode);
        }

        // Now we handle the app directory creation
        $appDir = $options['bolt-app-dir'];
        if (!$filesystem->exists($appDir)) {
            $filesystem->mkdir($appDir, $dirMode);
        }

    }

    /**
     * Composer post-package-install and post-package-update event handler
     *
     * @param PackageEvent $event
     */
    public static function extensions(PackageEvent $event)
    {
        $installedPackage = $event->getComposer()->getPackage();
        $rootExtra = $event->getComposer()->getPackage()->getExtra();
        $extra = $installedPackage->getExtra();
        if (isset($extra['bolt-assets'])) {
            $type = $installedPackage->getType();
            $pathToPublic = $rootExtra['bolt-web-path'];

            // Get the path from extensions base through to public
            $parts = array(getcwd(),$pathToPublic,"extensions",'vendor',$installedPackage->getName(), $extra['bolt-assets']);
            $path = join(DIRECTORY_SEPARATOR, $parts);
            if ($type == 'bolt-extension' && isset($extra['bolt-assets'])) {
                $fromParts = array(getcwd(), 'vendor', $installedPackage->getName(),$extra['bolt-assets']);
                $fromPath = join(DIRECTORY_SEPARATOR, $fromParts);
                $filesystem = new Filesystem();
                $filesystem->mirror($fromPath, $path);
            }
        }
    }

    public static function bootstrap(CommandEvent $event)
    {
        $webroot = $event->getIO()->ask('<info>Do you want your web directory to be a separate folder to root? [y/n] </info>', false);
        if ($webroot === 'y') {
            $webroot = true;
        } else {
            $webroot = false;
            $assetDir = '.';
        }

        if ($webroot) {
            $webname = $event->getIO()->ask('<info>What do you want your public directory to be named? [default: public] </info>', 'public');
            $webname = trim($webname, '/');
            $assetDir = './' . $webname;
        } else {
            $webname = null;
        }

        $generator = new BootstrapGenerator($webroot, $webname);
        $location = $generator->create();
        $options = array_merge(self::getOptions($event), array('bolt-web-dir' => $assetDir));
        self::installAssets($event, $options);
        $event->getIO()->write("<info>Your project has been setup</info>");
    }

    /**
     *
     * @param  CommandEvent $event
     * @return array
     */
    protected static function getOptions(CommandEvent $event)
    {
        $options = array_merge(
            array(
                'bolt-web-dir' => 'web',
                'bolt-app-dir' => 'app',
                'bolt-dir-mode' => 0777
            ),
            $event->getComposer()->getPackage()->getExtra()
        );

        return $options;
    }
}
