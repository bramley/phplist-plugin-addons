<?php

function exec_enabled()
{
    $disabled = explode(',', ini_get('disable_functions'));

    return !in_array('exec', $disabled);
}

function get($url)
{
    if (function_exists('curl_init')) {
        $content = fetchUrlCurl($url, ['timeout' => 600]);
    } else {
        $content = file_get_contents($url);
    }

    return $content;
}

if (!exec_enabled()) {
    echo 'exec() is not enabled';

    return;
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
$distribution = "$work/phplist-$latestVersion";

// download and expand the distribution zip file
$commands = [
    "wget -P $work -o $work/wget.log https://downloads.sourceforge.net/project/phplist/phplist/$latestVersion/phplist-$latestVersion.zip",
    "unzip -d $work $distribution.zip",
    "mkdir $backupDir",
];
try {
    run($commands);
} catch (Exception $e) {
    return;
}

// backup and copy the files and directories in the distribution /lists directory
$commands = [];
$it = new DirectoryIterator("$distribution/public_html/lists");

foreach ($it as $fileinfo) {
    if ($fileinfo->isDot()) {
        continue;
    }
    $dest = $lists . '/' . $fileinfo->getFilename();

    if (file_exists($dest)) {
        $commands[] = "mv -t $backupDir $dest";
    }
    $commands[] = sprintf("mv -t $lists %s", $fileinfo->getPathname());
}
try {
    run($commands);
} catch (Exception $e) {
    return;
}

// copy specific files and directories from the backup
$commands = [];

if (isset($addonsUpdater['files'])) {
    foreach ($addonsUpdater['files'] as $file) {
        $filename = "$backupDir/$file";

        if (file_exists($filename)) {
            $commands[] = "cp $filename $lists/$file";
        }
    }
}

if (isset($addonsUpdater['directories'])) {
    foreach ($addonsUpdater['directories'] as $dir) {
        if (file_exists("$backupDir/$dir")) {
            $commands[] = "cp -r $backupDir/$dir $lists/$dir";
        }
    }
}

// tidy-up
$commands[] = "rm -r $distribution";
$commands[] = "rm $distribution.zip";
try {
    run($commands);
} catch (Exception $e) {
    return;
}

function run($commands)
{
    foreach ($commands as $command) {
        $output = [];
        echo sprintf('<code>%s</code><br/>', $command);
        $lastLine = exec($command, $output, $returnStatus);

        if ($returnStatus != 0) {
            var_dump($command);
            var_dump($lastLine);
            var_dump($output);
            var_dump($returnStatus);

            throw new Exception();
        }
    }
}
