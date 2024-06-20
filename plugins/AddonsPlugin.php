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
 * @copyright 2018-2023 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */
use phpList\plugin\Common\DAO\User;
use phpList\plugin\Common\DB;

class AddonsPlugin extends phplistPlugin
{
    const VERSION_FILE = 'version.txt';
    const PLUGIN = 'AddonsPlugin';

    public $name = 'Addons Plugin';
    public $authors = 'Duncan Cameron';
    public $description = 'Additional functions for phpList';
    public $documentationUrl = 'https://resources.phplist.com/plugin/addons';
    public $topMenuLinks = [
        'exportlog' => ['category' => 'system'],
    ];
    public $pageTitles = [
        'exportlog' => 'Export the event log',
     ];

    private $mailer;

    public function __construct()
    {
        $this->coderoot = dirname(__FILE__) . '/' . __CLASS__ . '/';
        parent::__construct();
        $this->version = file_get_contents($this->coderoot . self::VERSION_FILE);
    }

    public function dependencyCheck()
    {
        return [
            'phpList version 3.5.4 or greater' => version_compare(VERSION, '3.5.4') >= 0,
            'PHP version 7 or greater' => version_compare(PHP_VERSION, '7') > 0,
            'Common Plugin installed' => phpListPlugin::isEnabled('CommonPlugin'),
        ];
    }

    public function activate()
    {
        $this->settings = [
            'addons_remote_processing_log' => [
                'value' => false,
                'description' => s('Log events when using remote page processing'),
                'type' => 'boolean',
                'allowempty' => true,
                'category' => 'Addons',
            ],
            'addons_exim_dot' => [
                'value' => false,
                'description' => s('Workaround problem with Exim and line beginning with dot character'),
                'type' => 'boolean',
                'allowempty' => true,
                'category' => 'Addons',
            ],
            'addons_http_referrer' => [
                'description' => s('Verify that subscription requests originate from this website'),
                'type' => 'boolean',
                'value' => false,
                'allowempty' => true,
                'category' => 'Addons',
            ],
            'addons_process_send_fail' => [
                'description' => s('Apply bounce rules when sending an email fails'),
                'type' => 'boolean',
                'value' => false,
                'allowempty' => true,
                'category' => 'Addons',
            ],
            'addons_failing_campaign' => [
                'description' => s('Mark a campaign as complete when all attempts to send are failing'),
                'type' => 'boolean',
                'value' => false,
                'allowempty' => true,
                'category' => 'Addons',
            ],
            'addons_remove_test_subject' => [
                'description' => s('Remove the (test) prefix from the subject of a test campaign'),
                'type' => 'boolean',
                'value' => false,
                'allowempty' => true,
                'category' => 'Addons',
            ],
        ];
        parent::activate();
    }

    public function adminmenu()
    {
        return [];
    }

    public function processSendStats($sent = 0, $invalid = 0, $failed_sent = 0, $unconfirmed = 0, $counters = array())
    {
        if (getConfig('addons_remote_processing_log')) {
            $this->remoteQueueLog($sent, $invalid, $failed_sent, $unconfirmed, $counters);
        }

        if (getConfig('addons_failing_campaign')) {
            $this->failingCampaign($counters);
        }
    }

    public function processSendingCampaignFinished($messageId, array $msgdata)
    {
        if (getConfig('addons_remote_processing_log')) {
            $this->remoteQueueCampaignFinished($messageId, $msgdata);
        }
    }

    public function parseOutgoingTextMessage($messageid, $textmessage, $destinationemail, $userdata = null)
    {
        if (getConfig('addons_exim_dot')) {
            return preg_replace('/^\./m', ' .', $textmessage);
        }

        return $textmessage;
    }

    /**
     * Verify that a subscription request originated from this web site by
     * checking the HTTP_REFERER header.
     * If the header is not correct then return a 404 status code to try to stop the originator repeating
     * subscription requests.
     *
     * @param array $pageData subscribe page fields
     *
     * @return string an empty string when validation is successful
     */
    public function validateSubscriptionPage($pageData)
    {
        if (!getConfig('addons_http_referrer')) {
            return '';
        }

        if ($_GET['p'] == 'asubscribe' || $_GET['p'] == 'preferences') {
            return '';
        }

        if (!isset($_POST['email'])) {
            return '';
        }
        $website = getConfig('website');

        if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $website) !== false) {
            return '';
        }
        $headers = array_intersect_key(
            $_SERVER,
            array_flip(['HTTP_USER_AGENT', 'HTTP_REFERER', 'REMOTE_ADDR', 'REQUEST_URI'])
        );
        $headersDump = print_r($headers, true);
        $headersDump = strstr($headersDump, '[');
        $headersDump = rtrim($headersDump, "\n)");
        logEvent("Subscription by {$_POST['email']} does not have acceptable HTTP_REFERER " . $headersDump);

        ob_end_clean();
        header('HTTP/1.0 404 Not Found');
        exit;
    }

    /**
     * Use this hook to save the instance of PHPMailer.
     * Remove the prefix from a test campaign.
     *
     * @param PHPMailer\PHPMailer\PHPMailer $mail instance of PHPMailer
     *
     * @return array
     */
    public function messageHeaders($mailer)
    {
        $this->mailer = $mailer;

        if (getConfig('addons_remove_test_subject')) {
            $mailer->Subject = str_replace(s('(test)'), '', $mailer->Subject);
            $mailer->Subject = trim($mailer->Subject);
        }

        return [];
    }

    /**
     * Apply bounce rules to the text of the error message when sending an email fails.
     *
     * @param messageid integer
     * @param userdata  array
     * @param isTest    boolean, true when testmessage
     */
    public function processSendFailed($messageid, $userdata, $isTest = false)
    {
        static $rules = null;
        static $dao;

        if (!getConfig('addons_process_send_fail')) {
            return;
        }

        if ($rules === null) {
            $rules = loadBounceRules();
            $dao = new User(new DB());
        }
        // ErrorInfo seems to have new lines at arbitrary places
        $errorText = str_replace("\r\n", ' ', $this->mailer->ErrorInfo);

        foreach ($rules as $pattern => $rule) {
            if (stripos($errorText, $pattern) !== false) {
                $matched = $pattern;
            } else {
                $pattern = str_replace('~', '\~', $pattern);

                if (preg_match("~$pattern~i", $errorText, $matches) === 1) {
                    $matched = $matches[0];
                } else {
                    continue;
                }
            }
            $reason = sprintf('processqueue send failed - %s', $matched);

            switch ($rule['action']) {
                case 'unconfirmuser':
                case 'unconfirmuseranddeletebounce':
                    $dao->unconfirmUser($userdata['email']);
                    addUserHistory(
                        $userdata['email'],
                        s('Auto unconfirmed'),
                        s('Subscriber auto unconfirmed for reason %s', $reason)
                    );
                    logEvent(s('Subscriber %s unconfirmed by bounce rule %s', $userdata['email'], $rule['id']));
                    break;
                case 'blacklistuser':
                case 'blacklistuseranddeletebounce':
                    addUserToBlackList($userdata['email'], $reason);
                    logEvent(s('Subscriber %s blacklisted by bounce rule %s', $userdata['email'], $rule['id']));
                    break;
                case 'blacklistemail':
                case 'blacklistemailanddeletebounce':
                    addEmailToBlackList($userdata['email'], $reason);
                    logEvent(s('email %s blacklisted by bounce rule %s', $userdata['email'], $rule['id']));
                    break;
                case 'deleteuser':
                case 'deleteuserandbounce':
                    deleteUser($userdata['id']);
                    logEvent(s('Subscriber %s deleted by bounce rule %s', $userdata['email'], $rule['id']));
                    break;
                case 'deletebounce':
                    // not applicable so just report it
                    logEvent(s('Subscriber %s matched by bounce rule %s', $userdata['email'], $rule['id']));
                    break;
                default:
            }
            break;
        }
    }

    private function remoteQueueLog($sent, $invalid, $failed_sent, $unconfirmed, $counters)
    {
        global $inRemoteCall;

        if (!$inRemoteCall) {
            return;
        }
        if (VERBOSE && $counters['campaign'] == 0) {
            logEvent(s('There are no campaigns to send'));

            return;
        }
        $messageCounters = [];

        foreach ($counters as $key => $value) {
            if (preg_match('/^(\w+) (\d+)$/', $key, $matches)) {
                $counter = $matches[1];
                $messageId = $matches[2];
                $messageCounters[$messageId][$counter] = $value;
            }
        }

        foreach ($messageCounters as $messageId => $counters) {
            $event = s(
                'Campaign %d - subscribers processed: %d / %d, emails sent: %d, send failed: %d',
                $messageId,
                $counters['processed_users_for_message'],
                $counters['total_users_for_message'],
                $counters['sent_users_for_message'],
                $counters['failed_sent_for_message']
            );
            logEvent($event);
        }
    }

    private function remoteQueueCampaignFinished($messageId, $msgdata)
    {
        global $inRemoteCall;

        if (!$inRemoteCall) {
            return;
        }
        logEvent(s('Campaign %d finished sending', $messageId));
    }

    private function failingCampaign($counters)
    {
        global $tables;

        foreach ($counters as $counter => $value) {
            if (preg_match('/failed_sent_for_message (\d+)/', $counter, $matches) && $value > 0) {
                $messageId = $matches[1];
                $sent = $counters[sprintf('sent_users_for_message %d', $messageId)];

                if ($sent == 0) {
                    Sql_query(sprintf(
                        'update %s set status = "sent", sent = now() where id = %d and status = "inprocess"',
                        $tables['message'],
                        $messageId
                    ));

                    if (Sql_Affected_Rows() > 0) {
                        logEvent(sprintf('Campaign %d marked as sent due to sending failures', $messageId));
                    }
                }
            }
        }
    }
}
