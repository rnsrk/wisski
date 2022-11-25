## WissKI Fire Brigade

## Installation
Over the `Manage` &rarr; `Extend` menu or via:
```
drush en wisski_fire_brigade
```
## Deinstallation
Over the `Manage` &rarr; `Extend` menu or via:
```
drush pm:uninstall wisski_fire_brigade
```


This module provides vairous migration and cleanup functionalities for WissKI systems regarding the management of URIs and EIDs in both triplestore and SQL database.

## Usage

By default the module provides a statistics page under `/wisski/statistics`. 
This page can be configured by navigating to `Configuration` &rarr; `WissKI Statistics`.


The module can be accessed by navigating to `Configuration` &rarr; `WissKI Fire Brigade`. For the specific functionalities of the module's tabs refer to their respective sections.

### Uri Migration

This tab provides the main migration functionality and migrates all Uris of a prticular adapter to a new base Uri.

### Clean up graphs

This tab can be used to rename URIs that differ from the base URI of their graph. E.g. the URI http://example.agfd.fau.de/example is contained in the Graph http://example.data.fau.de. The module will then allow renaming it to fit its graphs name, namely: http://example.data.fau.de/example. If there are no differing URIs this tab will show nothing.

### Clean up multiple EIDs

Here you can resolve conflicts in which a single URI has multiple EIDs. Here both the SQL databas and the triplestore are checked. You can then either choose to keep the lowest or the highes EIDs.

### Move EIDs to Triplestore

This tab is used to move the EID-URI mappings form the 'wisski_salz_id2uri' table from the SQL database into the 'baseFields' graph of the triplestore.
WissKI Fire Brigade administration pages
