<?php

require 'vendor/autoload.php';

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

function stage1($v)
{
    global $addonsUpdater;

    $latestVersion = $v->version;
    $work = $addonsUpdater['work'];
    $distributionZip = "$work/phplist-$latestVersion.zip";

    // download the distribution zip file
    $download = get($v->url);

    if (!$download) {
        throw new Exception(s('Download failed'));
    }
    $r = file_put_contents($distributionZip, $download);

    if (!$r) {
        throw new Exception(s('Unable to save zip file'));
    }

    $successMessage = s('phpList zip file %s has been downloaded to  %s', $v->url, $distributionZip);
    printf('<p>%s</p>', $successMessage);
    logEvent($successMessage);
}

function stage2($v)
{
    global $addonsUpdater, $pageroot, $configfile;

    $latestVersion = $v->version;
    $currentVersion = VERSION;
    $work = $addonsUpdater['work'];
    $listsDir = $_SERVER['DOCUMENT_ROOT'] . $pageroot;
    $now = date('YmdHi');
    $backupDir = "$work/lists_{$currentVersion}_$now";
    $distributionDir = "$work/dist";
    $distributionZip = "$work/phplist-$latestVersion.zip";

    $zip = new ZipArchive();

    if (true !== ($error = $zip->open($distributionZip))) {
        throw new Exception(s('Unable to open zip file, %s', $error));
    }

    if (!$zip->extractTo($distributionDir)) {
        throw new Exception(s('Unable to extract zip file'));
    }
    $zip->close();

    // create set of specific files and directories to be copied from the backup
    $additionalFiles = [];

    if ($configfile == '../config/config.php') {
        // config file is in the default location
        $additionalFiles[] = 'config/config.php';
    }

    if (PLUGIN_ROOTDIR == 'plugins' || realpath(PLUGIN_ROOTDIR) == realpath('plugins')) {
        // plugins are in the default location, copy additional files and directories
        $distPlugins = scandir("$distributionDir/phplist/public_html/lists/admin/plugins");
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

    // backup and copy the files and directories in the distribution /lists directory

    $it = new DirectoryIterator("$distributionDir/phplist/public_html/lists");

    foreach ($it as $fileinfo) {
        if ($fileinfo->isDot()) {
            continue;
        }
        $targetName = $listsDir . '/' . $fileinfo->getFilename();
        $backupName = $backupDir . '/' . $fileinfo->getFilename();

        if (file_exists($targetName)) {
            $fs->rename($targetName, $backupName);
        }
        $fs->rename($fileinfo->getPathname(), $targetName);
    }

    // copy additional files and directories from the backup

    foreach ($additionalFiles as $relativePath) {
        $sourceName = "$backupDir/$relativePath";
        $targetName = "$listsDir/$relativePath";

        if (file_exists($sourceName)) {
            if (is_dir($sourceName)) {
                $fs->mkdir($targetName, 0755);
                $fs->mirror($sourceName, $targetName, null, ['override' => true]);
            } else {
                $fs->copy($sourceName, $targetName, true);
            }
        }
    }

    // tidy-up
    $fs->remove($distributionDir);
    $fs->remove($distributionZip);

    $successMessage = s('phpList code has been updated to version %s', $latestVersion);
    $format = <<<END
<p>%s</p>
<p>%s</p>
END;
    printf(
        $format,
        $successMessage,
        s('Now <a href="%s">upgrade</a> the database.', './?page=upgrade')
    );
    logEvent($successMessage);
}

if (isset($_POST['stage'])) {
    switch ($_POST['stage']) {
        case 1:
            try {
                ob_start();
                stage1($_SESSION['addons_version']);
                $_SESSION['update_result'] = ob_get_clean();
                $nextStage = 2;
            } catch (Exception $e) {
                ob_end_clean();
                $_SESSION['update_result'] = $e->getMessage();
                $nextStage = 'error';
            }
            $query = http_build_query($_GET + ['stage' => $nextStage]);
            header("Location: ./?$query");

            exit;
        case 2:
            try {
                ob_start();
                stage2($_SESSION['addons_version']);
                $_SESSION['update_result'] = ob_get_clean();
                $nextStage = 3;
            } catch (Exception $e) {
                ob_end_clean();
                $_SESSION['update_result'] = $e->getMessage();
                $nextStage = 'error';
            }
            $query = http_build_query($_GET + ['stage' => $nextStage]);
            header("Location: ./?$query");

            exit;
    }
}

if (!(ini_get('allow_url_fopen') == '1' || function_exists('curl_init'))) {
    echo 'curl or URL-aware fopen wrappers are required', '<br/>';

    return;
}

if (!isset($_SESSION['addons_version'])) {
    $_SESSION['addons_version'] = json_decode(get('https://download.phplist.org/version.json'));
}
$stage = isset($_GET['stage']) ? $_GET['stage'] : 1;

switch ($stage) {
    case 1:
        $v = $_SESSION['addons_version'];
        $latestVersion = $v->version;

        if (!(isset($_GET['force']) || version_compare($latestVersion, VERSION) > 0)) {
            echo '<p>', s('phpList is up to date, version %s', VERSION), '</p>';

            break;
        }
        /*
         * form for stage 1
         */
        $prompt = s('phpList version %s is available', $latestVersion);
        $warning = false !== strpos($latestVersion, 'RC')
            ? s('Note that the latest version is a release candidate, which is not for general use.')
            : '';
        // remove force from query parameters
        $query = htmlspecialchars(http_build_query(array_diff_key($_GET, ['force' => ''])));
        echo <<<END
            <p>$prompt<br/>
            $warning</p>
            <form method="POST" action="./?$query">
                <button type="submit" name="stage" value="1">Download phpList zip file</button>
            </form>
END;
        break;
    case 2:
        echo $_SESSION['update_result'], '<br/>';
        unset($_SESSION['update_result']);
        /*
         * form for stage 2
         */
        $prompt = s('phplist zip file downloaded, now update');
        $query = htmlspecialchars(http_build_query(array_diff_key($_GET, ['stage' => ''])));
        echo <<<END
        <p>$prompt</p>
        <form method="POST" action="./?$query">
            <button type="submit" name="stage" value="2">Update phpList code</button>
        </form>
END;

        break;
    case 3:
        echo $_SESSION['update_result'], '<br/>';
        unset($_SESSION['update_result']);
        break;
    case 'error':
        echo $_SESSION['update_result'], '<br/>';
        unset($_SESSION['update_result']);
        break;
}
