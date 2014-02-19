csv-import
==========

A csv importer plugin for MantisBT v1.2.

- Download or clone the repository and place it under the MantisBT plugins folder.
- Rename the folder to Csv_import
- Go to Manage - Manage Plugins and install the plugin.
- Go to Manage - Import Issues

Features
--------
- Supports auto-creating users as part of import.
- Supports auto-creating categories as part of import.
- Supports custom fields importing.
- Supports importing as new issues.
- Supports updating issues after matching by ids.
- Supports updating issues after matching by selectd fields assuming a single match.

Few hints about usability:
--------------------------
- Have a separate csv file per project.
- Switch to the project before importing.
- If the goal to add the rows from csv, then make sure to map Id fields (if in CSV) to be ignored.
- If the id is supplied, then issues with be updated based on data from the csv after matching by id.
- Users that are auto-created will have random passwords, will be disabled and will have email as username@localhost.
