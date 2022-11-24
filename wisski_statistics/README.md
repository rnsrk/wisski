# WissKI Statistics

This module provides users with various statistics about the WissKI system.

## Installation
Over the `Manage` &rarr; `Extend` menu or via:
```
drush en wisski_statistics
```
## Deinstallation
Over the `Manage` &rarr; `Extend` menu or via:
```
drush pm:uninstall wisski_statistics
```

## Usage
By default the module provides a statistics page under `/wisski/statistics`. 
This page can be configured by navigating to `Configuration` &rarr; `WissKI Statistics`.

### Statistics Block

A subset of statistics can be displayed in a customizable block, which also provides access to the main statistics page, 
as well as the activated statistics groups, like E.g. triplestore statistics.

To place the block navigate to `Structure` &rarr; `Block Layout`.

## Configuration

The statistics page as well as the block can be configured to show only a subset of all available statistics.
The order of shown statistics groups as well as individual statistics within the groups can also be customized.
