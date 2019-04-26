# Better Password plugin for OJS/OMP

This plugin provides additional password restriction options when users are selecting their own password.  [NIST recommends the following for passwords](https://pages.nist.gov/800-63-3/sp800-63b.html#appA):
* Establishing a minimum length
* Not limiting allowed characters
* Not requiring arbitrary complexity rules
* Checking passwords against known weak passwords
* Rate limiting failed password attempts

Additional arbitrary password complexity requirements are available, but not recommended.

## Requirements

* OJS/OMP 3.1.2 or later

## Configuration

Install this as a "generic" plugin in OJS.  The preferred installation method is through the Plugin Gallery.  To install manually via the filesystem, extract the contents of this archive to a "betterPassword" directory under "plugins/generic" in your OJS root.  To install via Git submodule, target that same directory path: `git submodule add https://github.com/ulsdevteam/pkp-betterPassword plugins/generic/betterPassword` and `git submodule update --init --recursive plugins/generic/betterPassword`.  Run the upgrade script to register this plugin, e.g.: `php tools/upgrade.php upgrade`

Login as a Site Administrator and navigate to any context.  Enable the plugin via Login -> Settings -> Website -> Plugins -> Better Password -> Enable.

To configure the plugin, you will need to select what types of restrictions you want to enable.

## Author / License

Written by Clinton Graham for the [University of Pittsburgh](http://www.pitt.edu).  Copyright (c) University of Pittsburgh.

Released under a license of GPL v2 or later.
