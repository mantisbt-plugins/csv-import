<?php
# Mantis - a php based bugtracking system

require_once( 'core.php' );
$t_core_path = config_get( 'core_path' );
require_once( $t_core_path . 'category_api.php' );
require_once( $t_core_path . 'database_api.php' );
require_once( $t_core_path . 'user_api.php' );
require_once( $t_core_path . 'bug_api.php' );
access_ensure_project_level( config_get( 'manage_site_threshold' ) );
require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'import_issues_inc.php' );

# Check a project is selected
$g_project_id = helper_get_current_project();
if( $g_project_id == ALL_PROJECTS ) {
	plugin_error( 'ERROR_ALL_PROJECT', ERROR );
}

# Get submitted data
$f_create_unknown_cats = gpc_get_bool( 'cb_create_unknown_cats' );
$f_import_file = gpc_get_string( 'import_file' );
$f_columns = gpc_get_string_array( 'columns' );
$f_skip_first = gpc_get_bool( 'cb_skip_first_line' );
$f_separator = gpc_get_string('edt_cell_separator');
$f_keys = gpc_get_string_array( 'cb_keys', array() );

# Load custom field ids
$t_linked_ids = custom_field_get_linked_ids( $g_project_id );

# Convert the order-based key array to column-based key array
$t = array();
foreach($f_keys as $aKey) {
   $t[$f_columns[$aKey]] = 'standard';
}
$f_keys = $t;
unset($t);

# Get custom field id of primary keys
foreach($t_linked_ids as $cf_id) {
	$t_def = custom_field_get_definition($cf_id);
	$t_custom_col_name = $g_custom_field_identifier . $t_def['name'];

	if(isset($f_keys[$t_custom_col_name]))
	{
		$f_keys[$t_custom_col_name] = $cf_id;
	}
}

# Check given parameters - File
$t_file_content = array();
if( file_exists( $f_import_file ) ) {
	$t_file_content = read_csv_file( $f_import_file );
} else {
	error_parameters( plugin_lang_get( 'error_file_not_found' ) );
	plugin_error( 'ERROR_FILE_FORMAT', ERROR );
}

# Check given parameters - Columns
if( count( $f_columns ) <= 0 ) {
	trigger_error( ERROR_EMPTY_FIELD, ERROR );
}

# ignore_column have to be ... ignored
foreach ($f_columns as $key => $value) {
	if ($value == 'ignore_column') {
		unset($f_columns[$key]);
	}
}

# Other columns check
if( count( $f_columns ) != count( array_unique( $f_columns ) ) ) {
	error_parameters( plugin_lang_get( 'error_col_multiple' ) );
	plugin_error( 'ERROR_FILE_FORMAT', ERROR );
}

# Some default values for filter
$t_page_number = 1;
$t_issues_per_page = 25;
$t_page_count = 0;
$t_issues_count = 0;

# Import file content
$t_first_run = true;
$t_success_count = 0;
$t_failure_count = 0;
$t_error_messages = '';

# Determine import mode
$t_import_mode = 'all_new';
if(array_isearch( 'id', $f_columns ) !== false)
{
	$t_import_mode = 'by_id';
}
else
{
	if(count($f_keys) > 0)
	{
		$t_import_mode = 'by_keys';
	}
}

# Let's go
helper_begin_long_process( );

foreach( $t_file_content as $t_file_row ) {

	# Check if first row skipped
	if( $t_first_run && $f_skip_first ) {
		$t_first_run = false;
		continue;
	}

	# Explode into elements
	$t_file_row = read_csv_row( $t_file_row, $f_separator );

	# Get Id
	$t_bug_id = null;
	switch($t_import_mode)
	{
		case 'by_id' :
			$t_bug_id = get_column_value( 'id', $t_file_row );
			break;

		case 'by_keys' :

			$t_filter = filter_get_default();
			$t_filter[FILTER_PROPERTY_HIDE_STATUS_ID] = array(
				'0' => META_FILTER_ANY,
			);

			$t_values_for_error = array();
			foreach($f_keys as $aKey => $v)
			{
			   $filterValue = array(get_column_value( $aKey, $t_file_row, '' ));
				if($v == 'standard')
				{
					$t_filter[$aKey] = $filterValue;
				}
				else
				{
					$t_filter['custom_fields'][$v] = $filterValue;
				}
				$t_values_for_error[] = $filterValue[0];
			}

			$t_issues = filter_get_bug_rows( $t_page_number, $t_issues_per_page, $t_page_count, $t_issues_count, $t_filter );

			switch($t_issues_count)
			{
				case 1:
					$t_bug_id = $t_issues[0]->id;
					break;
				case 0:
					$t_bug_id = null;
					break;
				default :
					$t_bugs_id = array();
					foreach($t_issues as $issue)
					{
						$t_bugs_id[] = $issue->id;
					}

					$t_error_messages .= sprintf( plugin_lang_get( 'error_keys' ), implode('/', $t_values_for_error),
																										implode('/', $t_bugs_id)) . '<br />';
					$t_failure_count++;
					continue 3;
			}

			break;

		default :
			$t_bug_id = null;
	}

	# Set default parameters
	if( $t_bug_id === null ) {
		#Default bug will be with default values
		$t_bug_data = new BugData;

		$t_bug_data->project_id			= $g_project_id;
		$t_bug_data->category_id		= get_csv_import_category_id($g_project_id, 'csv_imported');
		$t_bug_data->reporter_id		= auth_get_current_user_id();
		$t_bug_data->priority			= config_get( 'default_bug_priority' );
		$t_bug_data->severity			= config_get( 'default_bug_severity' );
		$t_bug_data->reproducibility	= config_get( 'default_bug_reproducibility' );
		$t_bug_data->date_submitted	= date('Y-m-d G:i:s');
		$t_bug_data->handler_id			= auth_get_current_user_id();
		$t_bug_data->status				= config_get( 'bug_submit_status' );
		$t_bug_data->resolution			= OPEN;
		$t_bug_data->view_state			= config_get( 'default_bug_view_status' );
		$t_bug_data->profile_id			= 0;
		$t_bug_data->due_date			= date_get_null();
	} else {
		if( !bug_exists( $t_bug_id ) ) {
			$t_error_messages .= sprintf( plugin_lang_get( 'error_bug_not_exist' ), $t_bug_id) . '<br />';
			$t_failure_count++;
			continue;
		}
		$t_bug_data = bug_get( $t_bug_id, true );
		if( $t_bug_data->project_id != $g_project_id ) {
			$t_error_messages .= sprintf( plugin_lang_get( 'error_bug_bad_project' ), $t_bug_id) . '<br />';
			$t_failure_count++;
			continue;
		}
	}

	$fields = array();

	# Determine 'reporter_id' field
	$fields['reporter_id']			= get_user_column_value( 'reporter_id', $t_file_row, '' );

	# Determine 'summary' field
	$fields['summary']				= get_column_value( 'summary', $t_file_row, '' );

	# Determine 'category_id' field
	$fields['category_id']			= get_category_column_value('category', $t_file_row, $t_bug_data->project_id , '' );
	if( $fields['category_id'] == '' ) {
		$t_cat = trim ( get_column_value( 'category', $t_file_row ) );
		if( $t_cat != '' && $f_create_unknown_cats ) {
			get_csv_import_category_id($g_project_id, $t_cat);
			$fields['category_id']	= get_category_column_value('category', $t_file_row, $t_bug_data->project_id , '' );
		}
	}

	# Determine 'priority' field
	$fields['priority']				= get_enum_column_value( 'priority', $t_file_row, '' );

	# Determine 'severity' field
	$fields['severity']				= get_enum_column_value( 'severity', $t_file_row, '' );

	# Determine 'reproducibility' field
	$fields['reproducibility']		= get_enum_column_value( 'reproducibility', $t_file_row, '' );

	# Determine 'date_submitted' field
	$fields['date_submitted']		= get_date_column_value( 'date_submitted', $t_file_row, '' );

	# Determine 'last_updated' field
	$fields['last_updated']			= get_date_column_value( 'last_updated', $t_file_row, '' );

	# Determine 'handler_id' field
	$fields['handler_id']			= get_user_column_value( 'handler_id', $t_file_row, '' );

	# Determine 'status' field
	$fields['status']					= get_column_value( 'status', $t_file_row );
	if($fields['status'] != '' && !is_numeric($fields['status'])) {
		$fields['status']				= get_enum_column_value( 'status', $t_file_row, '' );
	}

	# Determine 'resolution' field
	$fields['resolution']			= get_column_value('resolution', $t_file_row);
	if($fields['resolution'] != '' && !is_numeric($fields['resolution'])) {
		$fields['resolution']		= get_enum_column_value( 'resolution', $t_file_row, '' );
	}

	# Determine 'os' field
	$fields['os']						= get_column_value( 'os', $t_file_row, '' );

	# Determine 'os_build' field
	$fields['os_build']				= get_column_value( 'os_build', $t_file_row, '' );

	# Determine 'platform' field
	$fields['platform']				= get_column_value( 'platform', $t_file_row, '' );

	# Determine 'version' field
	$fields['version']				= get_column_value( 'version', $t_file_row, '' );

	# Determine 'projection' field
	$fields['projection']			= get_enum_column_value( 'projection', $t_file_row, '' );

	# Determine 'eta' field
	$fields['eta']						= get_enum_column_value( 'eta', $t_file_row, '' );

	# Determine 'target_version' field
	$fields['target_version']		= get_column_value( 'target_version', $t_file_row, '' );

	# Determine 'build' field
	$fields['build']					= get_column_value( 'build', $t_file_row, '' );

	# Determine 'view_state' field
	$fields['view_state']			= get_enum_column_value( 'view_state', $t_file_row, '' );

	# Determine 'due_date' field
	$fields['due_date']				= get_date_column_value( 'due_date', $t_file_row, '' );

	# Determine 'description' field
	$fields['description']			= get_column_value( 'description', $t_file_row, '' );

	# Determine 'steps_to_reproduce' field
	$fields['steps_to_reproduce']	= get_column_value( 'steps_to_reproduce', $t_file_row, '' );

	# Determine 'additional_information' field
	$fields['additional_information'] = get_column_value( 'additional_information', $t_file_row, '' );

	# Determine 'additional_information' field
	$fields['fixed_in_version']	= get_column_value('fixed_in_version', $t_file_row, '');

	# Affect changes
	$detectChanges = false;
	# 'date_submitted' and 'last_updated' have to be updated differently
	$exceptions = array('date_submitted', 'last_updated');
	foreach($fields as $k => $v) {
		if( !in_array($k, $exceptions)) {
			if( $v != '') {
				if( $t_bug_id === null || $t_bug_data->$k != $v ) {
					$detectChanges = true;
					$t_bug_data->$k = $v;
				}
			}
		}
	}

	# Create or update bug on DB
	if( $t_bug_id === null) {
		$t_bug_id = $t_bug_data->create();
	} else {
		if( $detectChanges && !$t_bug_data->update( true, ( false == $t_notify ) ) ){
			$t_bug_id = null;
		}
	}

	# Update other bug fields
	if( $t_bug_id !== null ) {

		# Exceptions (dates) have to be updated differently
		foreach($exceptions as $aException) {
			# In import ?
			if($fields[$aException] != '') {
				# Get timestamp
				$t_time = is_numeric($fields[$aException]) ?
																$fields[$aException] :
																strtotime($fields[$aException]);
				# Different from bug ?
				if($t_bug_data->$aException != $t_time) {
					bug_set_field( $t_bug_id, 'date_submitted', $t_time );
				}
			}
		}

		# Variables
		$t_error = false;

		# Import custom fields
		foreach( $t_linked_ids as &$t_id ) {
			# Look if this field is set
			$t_def = custom_field_get_definition( $t_id );
			$t_custom_col_name = $g_custom_field_identifier . $t_def['name'];
			if( !column_value_exists( $t_custom_col_name , $t_file_row ) ) {
				continue;
			}

			# Prepare value
			$t_value = get_column_value( $t_custom_col_name , $t_file_row );
			if( ($t_value != '') && ($t_def['type'] == CUSTOM_FIELD_TYPE_DATE) ) {
				$t_value = is_numeric($t_value) ? $t_value : strtotime($t_value);
			}

			# Have to be different
			if( $t_value == custom_field_get_value( $t_id, $t_bug_id) ) {
				continue;
			}

			# Import value
			if( !custom_field_set_value( $t_id, $t_bug_id, $t_value ) ) {
				$t_error_messages .= sprintf( plugin_lang_get( 'error_custom_field' ), $t_def['name'], $t_bug_data->summary) . '<br />';
				$t_error = true;
			}
			else {
				# Mantis core doesn't update "last_updated" when setting custom fields
				bug_update_date( $t_bug_id );
			}
		}

		# Result
		if($t_error) {
			$t_failure_count++;
		}
		else {
			$t_success_count++;
		}
	} else {
		$t_error_messages .= sprintf( plugin_lang_get( 'error_any' ), $t_bug_data->summary) . '<br />';
		$t_failure_count++;
		continue;
	}
}

html_page_top1() ;
$t_redirect_url = 'view_all_bug_page.php';
if( $t_failure_count == 0 ) {
	html_meta_redirect( $t_redirect_url );
}
html_page_top2();
?>
<br />
<div align="center">
<?php
echo $t_error_messages;
if( $t_failure_count ) {
	echo sprintf( plugin_lang_get( 'result_failure_ct' ), $t_failure_count) . '<br />';
}
echo sprintf( plugin_lang_get( $t_import_mode != 'all_new' ? 'result_update_success_ct' : 'result_import_success_ct' ),
$t_success_count) . '<br />';
print_bracket_link( $t_redirect_url, lang_get( 'proceed' ) );
?>
</div>

<?php
html_page_bottom1( __FILE__ );
