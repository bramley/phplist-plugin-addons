<?php

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

function get($url)
{
    if (function_exists('curl_init')) {
        $content = fetchUrlCurl($url, ['timeout' => 600]);
    } else {
        $content = file_get_contents($url);
    }

    return $content;
}

$v = json_decode(get('https://download.phplist.org/version.json'));
$latestVersion = $v->version;

if (version_compare($latestVersion, VERSION) <= 0) {
    echo sprintf('phpList is up to date, version %s', VERSION);

    return;
}
$currentVersion = VERSION;
$work = $addonsUpdater['work'];
$lists = $_SERVER['DOCUMENT_ROOT'] . $pageroot;
$now = date('YmdHi');
$backupDir = "$work/lists_{$currentVersion}_$now";
$distributionDir = "$work/phplist-$latestVersion";
$distributionZip = "$work/phplist-$latestVersion.zip";

// download and expand the distribution zip file
$download = get("https://downloads.sourceforge.net/project/phplist/phplist/$latestVersion/phplist-$latestVersion.zip");

if (!$download) {
    echo 'download failed';

    return;
}
$r = file_put_contents($distributionZip, $download);

if (!$r) {
    echo 'file put failed';

    return;
}
$zip = new ZipArchive;

if (!$zip->open($distributionZip)) {
    echo 'zip open failed';

    return;
}
$zip->extractTo($work);
$zip->close();

$fs = new Filesystem();
$fs->mkdir($backupDir, 0755);

// backup and copy the files and directories in the distribution /lists directory

$it = new DirectoryIterator("$distributionDir/public_html/lists");

foreach ($it as $fileinfo) {
    if ($fileinfo->isDot()) {
        continue;
    }
    $targetName = $lists . '/' . $fileinfo->getFilename();
    $backupName = $backupDir . '/' . $fileinfo->getFilename();

    if (file_exists($targetName)) {
        $fs->rename($targetName, $backupName);
    }
    $fs->rename($fileinfo->getPathname(), $targetName);
}

// copy specific files and directories from the backup

if (isset($addonsUpdater['files'])) {
    foreach ($addonsUpdater['files'] as $file) {
        $backupName = "$backupDir/$file";
        $targetName = "$lists/$file";

        if (file_exists($backupName)) {
            $fs->copy($backupName, $targetName);
        }
    }
}

if (isset($addonsUpdater['directories'])) {
    foreach ($addonsUpdater['directories'] as $dir) {
        $backupName = "$backupDir/$dir";
        $targetName = "$lists/$dir";

        if (file_exists($backupName)) {
            $fs->mkdir($targetName, 0755);
            $fs->mirror($backupName, $targetName);
        }
    }
}

// tidy-up
$fs->remove($distributionDir);
$fs->remove($distributionZip);
