<?php

use Symfony\Component\Filesystem\Filesystem;

function get($url)
{
    if (ini_get('allow_url_fopen') == '1') {
        $content = file_get_contents($url);
    } else {
        $content = fetchUrlCurl($url, ['timeout' => 600]);
    }

    return $content;
}

function main()
{
    global $addonsUpdater, $pageroot, $configfile;

    $v = json_decode(get('https://download.phplist.org/version.json'));
    $latestVersion = $v->version;
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
        throw new Exception(s('Download failed'));
    }
    $r = file_put_contents($distributionZip, $download);

    if (!$r) {
        throw new Exception(s('Unable to save zip file'));
    }
    $zip = new ZipArchive();

    if (true !== ($error = $zip->open($distributionZip))) {
        throw new Exception(s('Unable to open zip file, %s', $error));
    }
    if (!$zip->extractTo($work)) {
        throw new Exception(s('Unable to extract zip file'));
    }
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
    $files = [];
    $dirs = [];

    if ($configfile == '../config/config.php') {
        // config file is in the default location
        $files[] = 'config/config.php';
    }

    if (PLUGIN_ROOTDIR == 'plugins') {
        // plugins are in the default location, copy additional files and directories from the backup plugins directory
        $distPlugins = scandir("$lists/admin/plugins");
        $installedPlugins = scandir("$backupDir/admin/plugins");
        $additional = array_diff($installedPlugins, $distPlugins);

        foreach ($additional as $file) {
            $backupName = "$backupDir/admin/plugins/$file";

            if (is_file($backupName)) {
                $files[] = "admin/plugins/$file";
            } else {
                $dirs[] = "admin/plugins/$file";
            }
        }
    }

    if (isset($addonsUpdater['files'])) {
        $files = array_merge($files, $addonsUpdater['files']);
    }

    foreach ($files as $file) {
        $backupName = "$backupDir/$file";
        $targetName = "$lists/$file";

        if (file_exists($backupName)) {
            $fs->copy($backupName, $targetName, true);
        }
    }

    if (isset($addonsUpdater['directories'])) {
        $dirs = array_merge($dirs, $addonsUpdater['directories']);
    }

    foreach ($dirs as $dir) {
        $backupName = "$backupDir/$dir";
        $targetName = "$lists/$dir";

        if (file_exists($backupName)) {
            $fs->mkdir($targetName, 0755);
            $fs->mirror($backupName, $targetName, null, ['override' => true]);
        }
    }

    // tidy-up
    $fs->remove($distributionDir);
    $fs->remove($distributionZip);

    echo s('phpList code has been updated to version %s', $latestVersion), '<br/>';
}

if (isset($_POST['submit'])) {
    try {
        ob_start();
        main();
        $_SESSION['update_result'] = ob_get_clean();
    } catch (Exception $e) {
        ob_end_clean();
        $_SESSION['update_result'] = $e->getMessage();
    }
    header("Location: {$_SERVER['REQUEST_URI']}");

    exit;
}

if (isset($_SESSION['update_result'])) {
    echo $_SESSION['update_result'], '<br/>';
    unset($_SESSION['update_result']);

    return;
}

if (!(ini_get('allow_url_fopen') == '1' || function_exists('curl_init'))) {
    echo 'curl or URL-aware fopen wrappers are required', '<br/>';

    return;
}
$v = json_decode(get('https://download.phplist.org/version.json'));
$latestVersion = $v->version;

if (!(isset($_GET['force']) || version_compare($latestVersion, VERSION) > 0)) {
    echo s('phpList is up to date, version %s', VERSION), '<br/>';

    return;
}
$prompt = s('phpList version %s is available', $latestVersion);
// remove force from query parameters
$params = $_GET;
unset($params['force']);
$query = http_build_query($params);
echo <<<END
$prompt
<form method="POST" action="./?$query">
    <input type="submit" name="submit" value="Update"/>
</form>
END;
