# WissKI Permalink

This module provides a [FieldFormatter](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Field%21Annotation%21FieldFormatter.php/class/FieldFormatter/8.2.x) for fields of type `plain_text`. Its primary usage is transforming a WissKI URI into a permalink by prefixing it with a URI resolver and displaying this link in the browsers URL bar.

## Installation
Over the `Manage` &rarr; `Extend` menu or via:
```
drush en wisski_permalink
```
## Deinstallation
Over the `Manage` &rarr; `Extend` menu or via:
```
drush pm:uninstall wisski_permalink
```

## Usage
You can enable the formatter by navigating to `Manage` &rarr; `Structure` &rarr; `WissKI Entities and Bundles`. Now `Edit` the bundle in which you want to activate permalinks. Switch to the `Manage Display` tab, enable the `WissKI URI` field and select `WissKI Permalink` in the `Format` column.

By default the module generates links by prefixing the URI with the local URI resolver `/wisski/get?uri=` and also writes this link into the browser's URL bar.

### Configuration
The configuration can be accessed by clicking on the small gear symbol on the right.

In case you do not want to display the link in the browser's URL bar, just disable the corresponding checkbox.

The module also allows redirecting to another (external) URI resolver by setting it in the `URI Resolver (Hostname or URL prefix)` textfield.
Here you can specify any URI resolver by supplying either the resolvers full URI (e.g. `https://example.wisski.data.fau.de/wisski/get?uri=`) or just the hostname of the resolvers WissKI (e.g. `example.wisski.data.fau.de`).
This setting can also be used to display the full permalink on the entity pages by explicitly stating the local hostname or the full URI to the local resolver.
Leaving the field empty will result in default behavior.