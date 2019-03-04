# Addons Plugin #

## Description ##
This plugin adds various small features to phpList which aren't worth implementing as individual plugins.
Each feature can be enabled separately through the plugin's group on the Settings page.

## Installation ##

### Dependencies ###

This plugin requires phplist 3.3.2 or later.

Requires php version 5.6 or later.

### Install through phplist ###
The recommended way to install is through the Plugins page (menu Config > Manage plugins) using the package URL
`https://github.com/bramley/phplist-plugin-addons/archive/master.zip`.

## Version history ##

    version         Description
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
