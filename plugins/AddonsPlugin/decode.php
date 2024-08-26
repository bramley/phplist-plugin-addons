<?php
/**
 * AddonsPlugin for phplist.
 *
 * This file is a part of AddonsPlugin.
 *
 * @category  phplist
 *
 * @author    Duncan Cameron
 * @copyright 2011-2024 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */

namespace phpList\plugin\AddonsPlugin;

use phpList\plugin\Common\Context;
use phpList\plugin\Common\DAO\Message;
use phpList\plugin\Common\DB;

if ($commandline) {
    $context = Context::create();
    $context->start();
    $options = getopt('t:p:c:m:');

    if (isset($options['t'])) {
        list($tid, $url, $campaign, $email) = decode($options['t']);
        $context->output("$tid\n$url, $campaign, $email");
    } else {
        $context->output('-t parameter is required');
    }
    $context->finish();
} else {
    if (isset($_GET['tid'])) {
        list($tid, $url, $campaign, $email) = decode($_GET['tid']);
        $url = htmlspecialchars($url);
        echo <<<END
<table>
<tr><td>tid</td><td style="word-break: break-all;">$tid</td></tr>
<tr><td>URL</td><td>$url</td></tr>
<tr><td>campaign</td><td>$campaign</td></tr>
<tr><td>subscriber</td><td>$email</td></tr>
</table>
END;
    }
    echo <<<END
<form method="GET">
<p>Enter link track URL or tid</p>
<input type="hidden" name="page" value="{$_GET['page']}"/>
<input type="hidden" name="pi" value="{$_GET['pi']}"/>
<input name="tid" />
<button name="submit" value="process">Process</button>
</form>
END;
}

function decode($parameter)
{
    if (preg_match('|tid=([0-9A-Za-z +/]+)|', $parameter, $matches)) {
        $tid = $matches[1];
    } else {
        $tid = $parameter;
    }

    if (strlen($tid) == 64) {
        $tid = str_replace(' ', '+', $tid);
        $dec = bin2hex(base64_decode($tid));
        $track = 'T|'.substr($dec, 0, 8).'-'.substr($dec, 8, 4).'-4'.substr($dec, 13, 3).'-'.substr($dec, 16, 4).'-'.substr($dec, 20, 12).'|'.
            substr($dec, 32, 8).'-'.substr($dec, 40, 4).'-4'.substr($dec, 45, 3).'-'.substr($dec, 48, 4).'-'.substr($dec, 52, 12).'|'.
            substr($dec, 64, 8).'-'.substr($dec, 72, 4).'-4'.substr($dec, 77, 3).'-'.substr($dec, 80, 4).'-'.substr($dec, 84, 12);
    } else {
        $track = base64_decode($tid);
        $track = $track ^ XORmask;
    }

    if (!preg_match(
        '/^(H|T)
        \|([a-f0-9]{8}-?[a-f0-9]{4}-?[a-f0-9]{4}-?[89ab][a-f0-9]{3}-?[a-f0-9]{12})
        \|([a-f0-9]{8}-?[a-f0-9]{4}-?[a-f0-9]{4}-?[89ab][a-f0-9]{3}-?[a-f0-9]{12})
        \|([a-f0-9]{8}-?[a-f0-9]{4}-?[a-f0-9]{4}-?[89ab][a-f0-9]{3}-?[a-f0-9]{12})$/x',
        $track,
        $matches
    )) {
        return [$track, 1, 1, 1];
    }
    $linkdata = Sql_Fetch_Assoc_query(
        sprintf('select * from %s where uuid = "%s"', $GLOBALS['tables']['linktrack_forward'], $matches[2])
    );
    $url = $linkdata['url'];

    $dao = new Message(new DB());
    $message = $dao->messageByUuid($matches[3]);
    $campaign = $message['id'];

    $userdata = Sql_Fetch_array_query(
        sprintf('select email from %s where uuid = "%s"', $GLOBALS['tables']['user'], $matches[4])
    );
    $email = $userdata['email'];

    return [$tid, $url, $campaign, $email];
}
