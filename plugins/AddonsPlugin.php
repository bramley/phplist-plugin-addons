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
 * @copyright 2018 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */
class AddonsPlugin extends phplistPlugin
{
    const VERSION_FILE = 'version.txt';
    const PLUGIN = 'AddonsPlugin';

    public $name = 'Addons Plugin';
    public $authors = 'Duncan Cameron';
    public $description = 'Additional functions for phpList';
    public $settings = array(
        'addons_remote_processing_log' => array(
            'value' => false,
            'description' => 'Log events when using remote page processing',
            'type' => 'boolean',
            'allowempty' => true,
            'category' => 'Addons',
        ),
    );

    public function __construct()
    {
        $this->coderoot = dirname(__FILE__) . '/' . __CLASS__ . '/';
        $this->version = file_get_contents($this->coderoot . self::VERSION_FILE);
        parent::__construct();
    }

    public function activate()
    {
        global $addonsUpdater;

        if (isset($addonsUpdater)) {
            $this->topMenuLinks = [
                'update' => array('category' => 'system'),
            ];
            $this->pageTitles = [
                'update' => 'Alternative updater',
            ];
        }
        parent::activate();
    }

    public function processSendStats($sent = 0, $invalid = 0, $failed_sent = 0, $unconfirmed = 0, $counters = array())
    {
        if (getConfig('addons_remote_processing_log')) {
            $this->remoteQueueLog($sent, $invalid, $failed_sent, $unconfirmed, $counters);
        }
    }

    public function processSendingCampaignFinished($messageId, $msgdata)
    {
        if (getConfig('addons_remote_processing_log')) {
            $this->remoteQueueCampaignFinished($messageId, $msgdata);
        }
    }

    private function remoteQueueLog($sent, $invalid, $failed_sent, $unconfirmed, $counters)
    {
        global $inRemoteCall;

        if (!$inRemoteCall) {
            return;
        }
        if (VERBOSE && $counters['campaign'] == 0) {
            logEvent('There are no campaigns to send');

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
}
