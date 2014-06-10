<?php
auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$f_import_issues_threshold = gpc_get_int( 'import_issues_threshold', 70 );

plugin_config_set( 'import_issues_threshold', $f_import_issues_threshold );

print_successful_redirect( plugin_page( 'config', TRUE ) );
