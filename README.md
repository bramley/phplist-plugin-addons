    # Addons Plugin #

## Description ##
This plugin adds various small features to phpList which aren't worth implementing as individual plugins.
Each feature can be enabled separately through the plugin's group on the Settings page.

## Installation ##

### Dependencies ###

This plugin requires phplist 3.5.4 or later, requires php version 7 or later, and also requires the Common plugin to be enabled.

### Install through phplist ###
The recommended way to install is through the Plugins page (menu Config > Manage plugins) using the package URL
`https://github.com/bramley/phplist-plugin-addons/archive/master.zip`.

## Version history ##

    version         Description
    1.16.0+20240629 Remove the (test) prefix from the subject of a test campaign
    1.15.0+20240627 Mark campaign as sent when all attempts fail
    1.14.1+20231201 Improve matching of bounce rule
    1.14.0+20230702 Add function to apply bounce rules when sending an email fails
    1.13.0+20230518 Remove the updater functionality
    1.12.0+20230427 Implement the Updater interface
    1.11.6+20230217 Improvement to logging
    1.11.5+20230205 Improvement to updater handling of files not to be installed
    1.11.4+20221024 Fix the test of config file being in the default location
    1.11.3+20221003 Find the "lists" directory within the distribution directory
    1.11.2+20220514 Allow forcing a re-install of the currently installed version
    1.11.1+20220413 Add documentation URL
    1.11.0+20220228 Validate subscribe page request for a valid HTTP_REFERER header
    1.10.0+20220116 Minor changes, now required php 7
    1.9.1+20211120  Correct identification of top level directory
    1.9.0+20211120  Allow updater to continue after an MD5 error
    1.8.0+20201110  Add page to export the event log
    1.7.3+20200306  Improvements to error reporting
    1.7.2+20190623  Set max execution time and memory limit for all updater stages
    1.7.1+20190418  Download zip phplist archive file as default
    1.7.0+20190319  Support downloading .tgz file instead of .zip
    1.6.0+20190312  Verify MD5 hash of downloaded files
    1.5.2+20190308  Update the requirements in README
    1.5.1+20190304  Test whether phplist and work directories are writeable
    1.5.0+20190221  More rework of the phplist update processing
    1.4.0+20190214  Rework the phplist update processing
    1.3.0+20190205  Add workaround for Exim problem with line starting with dot character
    1.2.5+20190118  Avoid downloading version.json more than once
    1.2.4+20190111  Re-order some of the processing
    1.2.3+20190110  Enable update to release candidate version
    1.2.2+20190102  Fix code error in previous version
    1.2.1+20190102  Allow messages to be translated
    1.2.0+20181231  Improve updating of phplist code
    1.1.0+20181229  Add update page to update the phplist code
    1.0.0+20181207  Log events for remote queue processing
