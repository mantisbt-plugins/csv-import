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

# Convert the order-based key array to column-based key array
$t = array();
foreach($f_keys as $aKey) {
   $t[$f_columns[$aKey]] = 'standard';
}
$f_keys = $t;
unset($t);

# Load custom field ids
$t_linked_ids = custom_field_get_linked_ids( $g_project_id );
$t_custom_fields = array();

# Get custom field id of primary keys
foreach($t_linked_ids as $cf_id) {
	$t_def = custom_field_get_definition($cf_id);
	$t_custom_col_name = $g_custom_field_identifier . $t_def['name'];

	if ( in_array( $t_custom_col_name, $f_columns ) ) {
		$t_custom_fields[$t_custom_col_name] = $cf_id;
	}

	if(isset($f_keys[$t_custom_col_name])) {
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
$t_success_count = array();
$t_failure_count = 0;
$t_error_messages = '';

# Determine import mode
$t_import_mode = 'all_new';
if(array_isearch( 'id', $f_columns ) !== false) {
	$t_import_mode = 'by_id';
}
else {
	if(count($f_keys) > 0) {
		$t_import_mode = 'by_keys';
	}
}

# Let's go
$t_dry_mode = false;
$lineNumber = 0;
helper_begin_long_process( );

foreach( $t_file_content as $t_file_row ) {
	$lineNumber++;
   $t_operation_type = 'undefined';

	# Check if first row skipped
	if( $lineNumber == 1 && $f_skip_first ) {
		continue;
	}

	# Explode into elements
	$t_file_row = read_csv_row( $t_file_row, $f_separator );

	# Get Id
	$t_bug_id = null;
	switch($t_import_mode) {
		case 'by_id' :
			$t_operation_type = 'update';
			$t_bug_id = get_column_value( 'id', $t_file_row );
			break;

		case 'by_keys' :
			$t_filter = filter_get_default();
			$t_filter[FILTER_PROPERTY_HIDE_STATUS_ID] = array(
				'0' => META_FILTER_ANY,
			);

			$t_values_for_error = array();
			foreach($f_keys as $aKey => $v) {
			   $filterValue = array(get_column_value( $aKey, $t_file_row, '' ));
				if($v == 'standard') {
					$t_filter[$aKey] = $filterValue;
				}
				else {
					$t_filter['custom_fields'][$v] = $filterValue;
				}
				$t_values_for_error[] = $filterValue[0];
			}

			$t_issues = filter_get_bug_rows( $t_page_number, $t_issues_per_page, $t_page_count, $t_issues_count, $t_filter );

			switch($t_issues_count) {
				case 1:
	            $t_operation_type = 'update';
					$t_bug_id = $t_issues[0]->id;
					break;
				case 0:
				   $t_operation_type = 'new';
					$t_bug_id = null;
					break;
				default :
					$t_bugs_id = array();
					foreach($t_issues as $issue) {
						$t_bugs_id[] = $issue->id;
					}

					$t_failure_count++;
					$t_error_messages .= sprintf( $lineNumber . ' : ' . plugin_lang_get( 'error_keys' ), implode('/', $t_values_for_error),
																										implode('/', $t_bugs_id)) . '<br />';
					continue 3;
			}

			break;

		default :
			$t_operation_type = 'new';
		   $t_bug_id = null;
	}

	# If new, set default parameters
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
	   # If existing bug
		if( !bug_exists( $t_bug_id ) ) {
			$t_failure_count++;
			$t_error_messages .= sprintf( $lineNumber . ' : ' . plugin_lang_get( 'error_bug_not_exist' ), $t_bug_id) . '<br />';
			continue;
		}
		$t_bug_data = bug_get( $t_bug_id, true );
		if( $t_bug_data->project_id != $g_project_id ) {
			$t_failure_count++;
			$t_error_messages .= sprintf( $lineNumber . ' : ' . plugin_lang_get( 'error_bug_bad_project' ), $t_bug_id) . '<br />';
			continue;
		}
	}

	# From selected columns, get value

	$detectChanges = false;
	$callUpdate = false;
	$t_custom_fields_to_set = array();
	$t_date_submitted = null;
	$t_last_updated = null;
	$t_description_set = false;

	foreach($f_columns as $i => $aColumn) {
	   $v = null;
      $valueSet = true;

	   switch($aColumn) {
	      case 'priority' :
	      case 'severity' :
	      case 'reproducibility' :
	      case 'projection' :
	      case 'eta' :
	      case 'view_state' :
            $v = get_enum_column_value( $aColumn, $t_file_row, '' );
            break;
	      case 'date_submitted' :
	      case 'last_updated' :
	         $v = get_date_column_value( $aColumn, $t_file_row, '' );
	         break;
	      case 'due_date' :
	         $v = get_date_column_value( $aColumn, $t_file_row, date_get_null() );
	         break;
	      case 'handler_id' :
	      case 'reporter_id' :
	         $v = get_user_column_value( $aColumn, $t_file_row, '' );
	         break;
	      case 'status' :
	      case 'resolution' :
         	$v = get_column_value( $aColumn, $t_file_row );
         	if($v != '' && !is_numeric($v)) {
         		$v = get_enum_column_value( $aColumn, $t_file_row, '' );
         	}
         	break;
	      case 'summary' :
	      case 'os' :
	      case 'os_build' :
	      case 'platform' :
	      case 'version' :
	      case 'target_version' :
	      case 'build' :
	      case 'description' :
	      case 'steps_to_reproduce' :
	      case 'additional_information' :
	      case 'fixed_in_version' :
	         $v = get_column_value( $aColumn, $t_file_row, '');
	         break;
	      case 'description' :
             $t_description_set = true;
	         $v = get_column_value( $aColumn, $t_file_row, '');
	         break;
	      case 'category' :
	   	   $v = get_category_column_value('category', $t_file_row, $t_bug_data->project_id , null );

         	if( $v == null ) {
         		$t_cat = trim ( get_column_value( 'category', $t_file_row ) );
         		if( $t_cat != '' && $f_create_unknown_cats ) {
         			get_csv_import_category_id($g_project_id, $t_cat);
         			$v = get_category_column_value('category', $t_file_row, $t_bug_data->project_id , '' );
         		}
         	}
         	
         	$aColumn = 'category_id';
         	
	         break;
	      default :
	         $valueSet = false;
	         if(isset($t_custom_fields[$aColumn])) {
				$t_id = $t_custom_fields[$aColumn];

				# Prepare value
      			$t_value = get_column_value( $aColumn , $t_file_row );
      			if( ($t_value != '') && ($t_def['type'] == CUSTOM_FIELD_TYPE_DATE) ) {
      				$t_value = is_numeric($t_value) ? $t_value : strtotime($t_value);
      			}

      			# Have to be different
      			if( $t_bug_id && $t_value == custom_field_get_value( $t_id, $t_bug_id) ) {
      				continue;
      			}

      			$detectChanges = true;

      			$t_custom_fields_to_set[$t_id] = $t_value;
	         }
	   }

	   if( $valueSet && ($t_bug_id === null || $t_bug_data->$aColumn != $v) ) {
	      $detectChanges = true;

	      if($aColumn == 'date_submitted') {
            $t_date_submitted = $v ;
	      } else if ( $aColumn == 'last_updated' ) {
	        $t_last_updated = $v;
	      } else {
   			$t_bug_data->$aColumn = $v;
		      $callUpdate = true;
	      }
		}
	}

	if( $t_operation_type == 'update' &&  !$detectChanges ) {
	   $t_operation_type = 'nothing';
	}
   $t_success_count[$t_operation_type]++;

	# Set values
	if(!$t_dry_mode && $t_operation_type != 'nothing') {
		if ( !$t_description_set ) {
			$t_bug_data->description = $t_bug_data->summary;
		}
	   # Set non-custom fields
   	if( $t_bug_id === null) {
   		$t_bug_id = $t_bug_data->create();
   	} else {
   		if( $callUpdate ) {
   			$t_bug_data->update( true, ( false == $t_notify ) );
   		}
   	}

   	# Set custom fields (can be set only after bug creation)
   	foreach($t_custom_fields_to_set as $t_id => $t_value) {
      	if( custom_field_set_value( $t_id, $t_bug_id, $t_value ) ) {
      		# Mantis core doesn't update "last_updated" when setting custom fields
      		bug_update_date( $t_bug_id );
      	}
      	else {
      	   $t_failure_count++;
      	   $t_error_messages .= sprintf( $lineNumber . ' : ' . plugin_lang_get( 'error_custom_field' ),
      	                                                   $t_def['name'], $t_bug_data->summary) . '<br />';
            continue;
      	}
   	}

   	# Set date_submitted (can be set only after bug creation)
   	if($t_date_submitted) {
   	   bug_set_field( $t_bug_id, 'date_submitted', $t_date_submitted );
   	}

   	# Set last_updated (can be set only after bug creation / updated)
   	if ( $t_last_updated ) {
   	   bug_set_field( $t_bug_id, 'last_updated', $t_last_updated );
   	}
	}
}

html_page_top1();
$t_redirect_url = 'view_all_bug_page.php';
if( $t_failure_count == 0 ) {
	html_meta_redirect( $t_redirect_url );
}
html_page_top2();
?>
<br />
<div align="center">
<?php
echo sprintf( plugin_lang_get( 'result_update_success_ct'  ), $t_success_count['update']) . '<br />';
echo sprintf( plugin_lang_get( 'result_import_success_ct'  ), $t_success_count['new']) . '<br />';
echo sprintf( plugin_lang_get( 'result_nothing_success_ct' ), $t_success_count['nothing']) . '<br />';

if( $t_failure_count ) {
	echo '<b>'.sprintf( plugin_lang_get( 'result_failure_ct' ), $t_failure_count) . ' :</b><br />';
   echo $t_error_messages . '<br/>';
}
print_bracket_link( $t_redirect_url, lang_get( 'proceed' ) );
?>
</div>

<?php
html_page_bottom1( __FILE__ );
