# SnapShots

Contributors: everpress, xaverb  
Tags: snapshots, dev, database, development  
Requires at least: 4.5  
Tested up to: 6.4  
Requires PHP: 7.4  
Stable tag: 2.8.0  
License: GPLv2 or later  
License URI: <https://www.gnu.org/licenses/gpl-2.0.html>

Quickly Create SnapShots of your development sites and restore them with a click.

## Description

You are developing things on a WordPress site and would like to have a _snapshot_ of the current state of your site? **SnapShots** will help you save states of your WordPress environment.

- Save snapshots of your site with a simple click.
- Name your snapshots for easy distinction.
- SnapShots stores current location and redirects after restore.
- Stores and restores database tables and files in upload folder.
- Automatically logs in current user.
- Automatically clears old snapshots with same name.
- Small footprint and minimal UI.

[youtube https://www.youtube.com/watch?v=-ybCpL5Ri44]

## Installation

1. Upload `snapshosts` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Click on "Click here to create your first Snapshot!" in the admin bar to create your first Snapshot.
4. Restore the saved snapshot by clicking on the menu entry and confirm.
5. All tables not used in the current SnapShot with the DB prefix will be removed!

## Screenshots

### 1. Minimal dropdown UI of SnapShots

![Minimal dropdown UI of SnapShots.](https://ps.w.org/snapshots/assets/screenshot-1.png)

## Frequently Asked Questions

### What are the requirements for SnapShots

You need [WP CLI](https://wp-cli.org/) installed (at least version 2.2) and PHP must be able to execute external programs with PHPs [`exec`](https://www.php.net/manual/en/function.exec.php)

### Does it work without WP CLI?

No, WP CLI is essential here and SnapShots will not work without it. You will not be able to activate the plugin without the addon.

### Does it work with "Local by Flywheel"

Yes, the plugin has been tested with the amazing tool from Flywheel and works out of the box.

## Screenshots

1. SnapShots adds a small menu to the WP Admin bar

## Changelog

### 2.8.0

- Tested up to PHP 8.3
- fixed: redirects now work with fragments in URLs
- change: current snapshot names is now stored in the database (not in the localstorage anymore)

### 2.7.0

- better error handling
- keyboard search
- tested up to 6.1
- implemented upgrade mechanism for future update routines

### 2.6.0

- fixed: Snapshots with numbers are now recognized.
- moved to external script and styles

### 2.5.0

- store last use snapshots in localstorage

### 2.4

- Now requires at least PHP 7.0

### 2.3

- updated usage of CLI commands for better support

### 2.2

- checks for ZIPAchive and falls back to PCLZIP if not available
- automatically checks the `home_url` and do a `search-replace` if necessary

### 2.1

- Updated look in the dropdown menu.

### 2.0

- Breaking changes: Snapshosts are now stored in a subdirectory.
- added option to remove snapshots from the UI

### 1.0

- Initial release

## Options

All options are defined via constants and can get overwritten with a filter. The format of options is

`SNAPSHOTS_[OPTION_NAME]`

Best to define your custom option constants in the `wp-config.php` file.

You can use filters options like

    add_filter( 'snapshots_[option_name]', function( $default_option ){
     return $my_option;
    });

### Default Options

    // Default save location.
    SNAPSHOTS_FOLDER : WP_CONTENT_DIR . '/.snapshots'

    // add '--allow-root' to each command if you run the commands as root.
    SNAPSHOTS_CLI_ALLOW_ROOT: false

    // define the location of your 'wp' binaries.
    SNAPSHOTS_CLI_PATH: '/usr/local/bin'

    // Number of shots kept with the same name.
    SNAPSHOTS_MAX_SHOTS: 2

    // SnapShot includes files from content folder.
    SNAPSHOTS_SAVE_FILES: true

    // SnapShot includes location (URL) during creation to redirect on restore.
    SNAPSHOTS_SAVE_LOCATION: true
