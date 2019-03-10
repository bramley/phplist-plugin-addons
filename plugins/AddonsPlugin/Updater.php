<?php
/**
 * AddonsPlugin for phplist.
 *
 * This file is a part of AddonsPlugin.
 *
 * This plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @category  phplist
 *
 * @author    Duncan Cameron
 * @copyright 2019 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */

namespace phpList\plugin\AddonsPlugin;

use DirectoryIterator;
use Exception;
use phpList\plugin\Common\Logger;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

class Updater
{
    public function __construct($v)
    {
        global $addonsUpdater;

        $this->url = $v->url;
        $this->latestVersion = $v->version;
        $this->workDir = $addonsUpdater['work'];
        $this->distributionDir = "$this->workDir/dist";
        $this->distributionZip = "$this->workDir/phplist-$this->latestVersion.zip";
        $this->logger = Logger::instance();
    }

    public function downloadZipFile()
    {
        $this->logger->debug('before downloading');
        $download = getUrl($this->url);

        if (!$download) {
            throw new Exception(s('Download failed'));
        }
        $r = file_put_contents($this->distributionZip, $download);

        if (!$r) {
            throw new Exception(s('Unable to save zip file'));
        }
        $this->logger->debug('stored download');
    }

    public function extractZipFile()
    {
        $zip = new ZipArchive();

        if (true !== ($error = $zip->open($this->distributionZip))) {
            throw new Exception(s('Unable to open zip file, %s', $error));
        }
        $this->logger->debug('before extracting zip archive');

        if (!$zip->extractTo($this->distributionDir)) {
            throw new Exception(s('Unable to extract zip file'));
        }
        $zip->close();
        $this->logger->debug('extracted zip archive');
    }

    public function replaceFiles()
    {
        global $addonsUpdater, $pageroot, $configfile;

        $listsDir = $_SERVER['DOCUMENT_ROOT'] . $pageroot;
        $backupDir = sprintf('%s/lists_%s_%s', $this->workDir, VERSION, date('YmdHi'));

        // create set of specific files and directories to be copied from the backup
        $additionalFiles = [];

        if ($configfile == '../config/config.php') {
            // config file is in the default location
            $additionalFiles[] = 'config/config.php';
        }

        if (PLUGIN_ROOTDIR == 'plugins' || realpath(PLUGIN_ROOTDIR) == realpath('plugins')) {
            // plugins are in the default location, copy additional files and directories
            $distPlugins = scandir("$this->distributionDir/phplist/public_html/lists/admin/plugins");
            $installedPlugins = scandir("$listsDir/admin/plugins");
            $additional = array_diff($installedPlugins, $distPlugins);

            foreach ($additional as $file) {
                $additionalFiles[] = "admin/plugins/$file";
            }
        }

        if (isset($addonsUpdater['files'])) {
            $additionalFiles = array_merge($additionalFiles, $addonsUpdater['files']);
        }

        $fs = new Filesystem();
        $fs->mkdir($backupDir, 0755);

        // backup and move the files and directories in the distribution /lists directory

        $it = new DirectoryIterator("$this->distributionDir/phplist/public_html/lists");
        $doNotInstall = isset($addonsUpdater['do_not_install']) ? $addonsUpdater['do_not_install'] : [];

        foreach ($it as $fileinfo) {
            if ($fileinfo->isDot()) {
                continue;
            }
            $targetName = $listsDir . '/' . $fileinfo->getFilename();
            $backupName = $backupDir . '/' . $fileinfo->getFilename();

            if (file_exists($targetName)) {
                $fs->rename($targetName, $backupName);
                $this->logger->debug("renamed $targetName to $backupName");
            }

            if (in_array($fileinfo->getFilename(), $doNotInstall)) {
                $this->logger->debug("not installing $targetName");
            } else {
                $fs->rename($fileinfo->getPathname(), $targetName);
                $this->logger->debug("installed $targetName from distribution");
            }
        }

        // copy additional files and directories from the backup

        foreach ($additionalFiles as $relativePath) {
            $sourceName = "$backupDir/$relativePath";
            $targetName = "$listsDir/$relativePath";

            if (file_exists($sourceName)) {
                if (is_dir($sourceName)) {
                    $fs->mkdir($targetName, 0755);
                    $fs->mirror($sourceName, $targetName, null, ['override' => true]);
                    $this->logger->debug("copied directory $sourceName to $targetName");
                } else {
                    $fs->copy($sourceName, $targetName, true);
                    $this->logger->debug("copied file $sourceName to $targetName");
                }
            }
        }

        // tidy-up
        $fs->remove($this->distributionDir);
        $fs->remove($this->distributionZip);
        $this->logger->debug('deleted distribution directory and zip file');
    }
}
