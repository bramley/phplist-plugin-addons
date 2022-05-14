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

use Exception;

if (!isset($_SESSION['addons_version'])) {
    $_SESSION['addons_version'] = json_decode(fetchUrlDirect('https://download.phplist.org/version.json'));
}
$versionToInstall = isset($_GET['force']) ? ($_GET['force'] ?: VERSION) : $_SESSION['addons_version']->version;

if (isset($_POST['stage'])) {
    try {
        $updater = new Updater($versionToInstall);

        switch ($_POST['stage']) {
            case 1:
                $updater->downloadZipFile();
                $nextStage = 2;
                break;
            case 2:
                $updater->extractZipFile();
                $nextStage = 3;
                break;
            case 3:
                $updater->replaceFiles();
                $nextStage = 4;
                break;
        }
    } catch (MD5Exception $e) {
        $_SESSION['update_result'] = $e->getMessage();
        $nextStage = 'md5error';
    } catch (Exception $e) {
        $_SESSION['update_result'] = $e->getMessage();
        $nextStage = 'error';
    }
    $query = http_build_query(array_merge($_GET, ['stage' => $nextStage]));
    header("Location: ./?$query");

    exit;
}
$stage = isset($_GET['stage']) ? $_GET['stage'] : 1;

switch ($stage) {
    case 1:
        if (!is_writeable($workDir = $addonsUpdater['work'])) {
            echo '<p>', s('work directory %s is not writeable', $workDir), '</p>';
            break;
        }

        if (!is_writeable($listsDir = $_SERVER['DOCUMENT_ROOT'] . $pageroot)) {
            echo '<p>', s('phpList directory %s is not writeable', $listsDir), '</p>';
            break;
        }

        if (!(isset($_GET['force']) || version_compare($versionToInstall, VERSION) > 0)) {
            echo '<p>', s('phpList is up to date, version %s', VERSION), '</p>';
            break;
        }
        /*
         * form to run the download stage
         */
        $prompt = isset($_GET['force'])
            ? s('Forcing installation of phpList version %s', $versionToInstall)
            : s('phpList version %s is available', $versionToInstall);
        $warning = false !== strpos($versionToInstall, 'RC')
            ? s('Note that the latest version is a release candidate, which is not for general use.')
            : '';
        echo <<<END
            <p>$prompt<br/>
            $warning</p>
            <form method="POST">
                <button type="submit" name="stage" value="1">Download phpList zip file</button>
            </form>
END;
        break;
    case 2:
        /*
         * form to run the extract stage
         */
        $prompt = s('phplist zip file downloaded, now extract the zip file');
        echo <<<END
        <p>$prompt</p>
        <form method="POST">
            <button type="submit" name="stage" value="2">Extract zip file</button>
        </form>
END;
        break;
    case 3:
        /*
         * form to run the update stage
         */
        $prompt = s('phplist zip file extracted, now update the phpList code');
        $backup = s('A backup of the phpList code will be made in %s', sprintf('<code>%s</code>', $addonsUpdater['work']));
        echo <<<END
        <p>$prompt</p>
        <p>$backup</p>
        <form method="POST">
            <button type="submit" name="stage" value="3">Update phpList code</button>
        </form>
END;
        break;
    case 4:
        $successMessage = s('phpList code has been updated to version %s', $versionToInstall);
        printf(
            '<p>%s</p><p>%s</p>',
            $successMessage,
            s('Now <a href="%s">upgrade</a> the database.', './?page=upgrade')
        );
        logEvent($successMessage);
        break;
    case 'md5error':
        // Allow continuing after MD5 error
        $prompt = $_SESSION['update_result'];
        echo <<<END
        <p>$prompt</p>
        <form method="POST">
            <button type="submit" name="stage" value="2">Ignore and continue to extract zip file</button>
        </form>
        <a class="button" href=".">Cancel</a>
END;
        break;

    case 'error':
        logEvent($_SESSION['update_result']);
        echo $_SESSION['update_result'];
        unset($_SESSION['update_result']);
        break;
}
