CSV Issues Importer
===================

A plugin that imports issues from CSV files.

Install
-------

- Download or clone the repository and place it under the MantisBT plugins folder.
  Make sure you get the correct version for your MantisBT (See [Compatibility](#compatibility) below). 
- Rename the folder to Csv_import (case sensitive)
- Go to Manage - Manage Plugins and install the plugin.
- Go to Manage - Import Issues

Features
--------

The plugin supports:

- auto-creating users as part of import.
- auto-creating categories as part of import.
- custom fields importing.
- importing as new issues.
- updating issues after matching by ids.
- updating issues after matching by selected fields assuming a single match.

A few hints about usability
---------------------------

- Have a separate csv file per project.
- Switch to the project before importing.
- If the goal is to add the rows from csv, then make sure to map Id fields (if in CSV) to be ignored.
- If the id is supplied, then issues with be updated based on data from the csv after matching by id.
- Users that are auto-created will 
  - be disabled 
  - have random passwords, 
  - have email as `username@localhost`.

Compatibility
-------------

Depending on which version of MantisBT you are using, please make sure to
get the appropriate version of the source code.
Use [release tags](https://github.com/mantisbt-plugins/csv-import/releases),
or the relevant branch in the Plugin's GitHub repository, as per the table below:

MantisBT version | Plugin version | Branch
:---:|:---:|---
2.x   | v2.x | [master](https://github.com/mantisbt-plugins/csv-import/archive/master.zip)
1.3.x | v1.5.x | [master-1.3.x](https://github.com/mantisbt-plugins/csv-import/archive/master-1.3.x.zip)
1.2.x | v1.4.1 | [master-1.2.x](https://github.com/mantisbt-plugins/csv-import/archive/master-1.2.x.zip)

Support
-------

Report issue in the [MantisBT bug tracker](https://mantisbt.org/bugs)
under the **Plugin - CsvImport** project.

However, please note that this plugin is not actively supported by its original authors.
 