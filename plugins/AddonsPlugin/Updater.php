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
use PharData;
use phpList\plugin\Common\Logger;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

class ZipExtractor
{
    public function extension()
    {
        return 'zip';
    }

    public function extract($file, $dir)
    {
        $zip = new ZipArchive();

        if (true !== ($error = $zip->open($file))) {
            throw new Exception(s('Unable to open zip file, %s', $error));
        }

        if (!$zip->extractTo($dir)) {
            throw new Exception(s('Unable to extract zip file'));
        }
        $zip->close();
    }
}

class TgzExtractor
{
    public function extension()
    {
        return 'tgz';
    }

    public function extract($file, $dir)
    {
        $phar = new PharData($file);
        $phar->extractTo($dir);
    }
}

class Updater
{
    private $basename;
    private $archiveFile;
    private $archiveUrl;
    private $md5Url;
    private $workDir;
    private $distributionDir;
    private $distributionArchive;
    private $logger;

    public function __construct($version)
    {
        global $addonsUpdater;

        $this->basename = sprintf('phplist-%s', $version);
        if (class_exists('PharData', false)) {
            $this->extractor = new TgzExtractor();
        } else {
            $this->extractor = new ZipExtractor();
        }
        $this->archiveFile = sprintf('%s.%s', $this->basename, $this->extractor->extension());
        $urlTemplate = false === strpos($version, 'RC')
            ? 'https://sourceforge.net/projects/phplist/files/phplist/%s/%s/download'
            : 'https://sourceforge.net/projects/phplist/files/phplist-development/%s/%s/download';
        $this->archiveUrl = sprintf($urlTemplate, $version, $this->archiveFile);
        $this->md5Url = sprintf($urlTemplate, $version, $this->basename . '.md5');
        $this->workDir = $addonsUpdater['work'];
        $this->distributionDir = "$this->workDir/dist";
        $this->distributionArchive = "$this->workDir/$this->archiveFile";
        $this->logger = Logger::instance();
    }

    public function downloadZipFile()
    {
        $filesMd5 = $this->parseMd5Contents(getUrl($this->md5Url));
        $expectedMd5 = $filesMd5[$this->archiveFile];

        // Use existing file if the MD5 is correct
        if (file_exists($this->distributionArchive)) {
            $actualMd5 = md5_file($this->distributionArchive);
            $this->logger->debug(sprintf('Expected md5 %s actual md5 %s', $expectedMd5, $actualMd5));

            if ($actualMd5 == $expectedMd5) {
                $this->logger->debug(sprintf('Using existing archive file %s', $this->distributionArchive));

                return;
            }
        }
        $this->logger->debug(sprintf('Downloading %s', $this->archiveUrl));
        $archiveContents = getUrl($this->archiveUrl);

        if (!$archiveContents) {
            throw new Exception(s('Download failed'));
        }
        $actualMd5 = md5($archiveContents);
        $this->logger->debug(sprintf('Expected md5 %s actual md5 %s', $expectedMd5, $actualMd5));

        if ($actualMd5 != $expectedMd5) {
            throw new Exception(s('MD5 verification failed, expected %s actual %s', $expectedMd5, $actualMd5));
        }
        $r = file_put_contents($this->distributionArchive, $archiveContents);

        if (!$r) {
            throw new Exception(s('Unable to save archive file'));
        }
        $this->logger->debug('Stored download');
    }

    public function extractZipFile()
    {
        $this->logger->debug('Extracting archive');
        $this->extractor->extract($this->distributionArchive, $this->distributionDir);
        $this->logger->debug('After extracting archive');
    }

    public function replaceFiles()
    {
        global $addonsUpdater, $pageroot, $configfile;

        $listsDir = $_SERVER['DOCUMENT_ROOT'] . $pageroot;
        $backupDir = sprintf('%s/lists_%s_%s', $this->workDir, VERSION, date('YmdHis'));

        // create set of specific files and directories to be copied from the backup
        $additionalFiles = [];

        if ($configfile == '../config/config.php') {
            // config file is in the default location
            $additionalFiles[] = 'config/config.php';
        }

        if (PLUGIN_ROOTDIR == 'plugins' || realpath(PLUGIN_ROOTDIR) == realpath('plugins')) {
            // plugins are in the default location, copy additional files and directories
            $distPlugins = scandir("$this->distributionDir/$this->basename/public_html/lists/admin/plugins");
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

        $it = new DirectoryIterator("$this->distributionDir/$this->basename/public_html/lists");
        $doNotInstall = isset($addonsUpdater['do_not_install']) ? $addonsUpdater['do_not_install'] : [];

        foreach ($it as $fileinfo) {
            if ($fileinfo->isDot()) {
                continue;
            }
            $targetName = $listsDir . '/' . $fileinfo->getFilename();
            $backupName = $backupDir . '/' . $fileinfo->getFilename();

            if (file_exists($targetName)) {
                $fs->rename($targetName, $backupName);
                $this->logger->debug("Renamed $targetName");
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
                    $this->logger->debug("Copied directory $sourceName");
                } else {
                    $fs->copy($sourceName, $targetName, true);
                    $this->logger->debug("Copied file $sourceName");
                }
            }
        }

        // tidy-up
        $fs->remove($this->distributionDir);
        $this->logger->debug('Deleted distribution directory');
    }

    private function parseMd5Contents($md5Contents)
    {
        $md5 = [];

        foreach (explode("\n", trim($md5Contents)) as $line) {
            list($hash, $file) = preg_split('/\s+/', $line);
            $md5[$file] = $hash;
        }
        $this->logger->debug(print_r($md5, true));

        return $md5;
    }
}
