<?php
# The current project
$g_project_id = helper_get_current_project();
if( $g_project_id == ALL_PROJECTS )
{
	plugin_error( 'ERROR_ALL_PROJECT', ERROR );
}

# This identify a custom field
$g_custom_field_identifier = 'custom_';

# All column names that can be used with this project
$g_all_fields = array();
if( config_is_set( 'csv_import_columns' ) ) {
	$g_all_fields = config_get( 'csv_import_columns' );
};
if( count( $g_all_fields ) == 0 ) {
	$g_all_fields = array(
		'additional_information',
		'build',
		'category',
		'date_submitted',
		'description',
		'due_date',
		'eta',
		'fixed_in_version',
		'handler_id',
		'id',
		'last_updated',
		'os',
		'os_build',
		'platform',
		'priority',
		'projection',
		'reporter_id',
		'reproducibility',
		'resolution',
		'severity',
		'status',
		'steps_to_reproduce',
		'summary',
		'target_version',
		'version',
		'view_state',
	);
}
$g_all_fields = array_unique($g_all_fields);

foreach( custom_field_get_linked_ids( $g_project_id ) as $t_id ) {
	$g_all_fields[] =
		$g_custom_field_identifier . custom_field_get_field( $t_id, 'name' );
};
$g_all_fields[] = 'ignore_column'; # @@@ u.sommer added
$g_all_fields = prepare_all_fields_array( $g_all_fields );

# --------------------
function prepare_all_fields_array( $p_all_fields ) {
	global $g_custom_field_identifier;

	# Correspondance between field names and language identifiers
	$t_translated_fields = array(
		'reporter_id' => 'reporter',
		'last_updated' => 'updated',
		'handler_id' => 'assigned_to',
		'os_build' => 'os_version',
		'version' => 'product_version',
		'view_state' => 'view_status',
	);

	# Create the translated array
	$t_fields = array();
	foreach( array_unique( $p_all_fields ) as $t_element )
	{
		$t_lang_id = $t_element;
		if( strpos( $t_element, $g_custom_field_identifier ) === 0 )
			$t_lang_id = substr( $t_element, strlen( $g_custom_field_identifier ) );
		elseif( isset( $t_translated_fields[$t_element] ) )
			$t_lang_id = $t_translated_fields[$t_element];
		$t_fields[$t_element] = lang_get_defaulted( $t_lang_id );
	}

	# Set to all fields
	return $t_fields;
}

# --------------------
function print_all_fields_option_list( $p_selected ) {
	global $g_all_fields;
	foreach( $g_all_fields as $t_element => $t_translated )
	{
		echo "<option value=\"$t_element\"";
		check_selected( $t_element, $p_selected );
		echo ">" . $t_translated . "</option>";
	}
}

# --------------------
function csv_string_unescape( $p_string ) {
	$t_wo_quotes = preg_replace( '/\A"(.*)"\z/sm', '${1}', $p_string );
	if( $t_wo_quotes !== $p_string )
		$t_wo_quotes = str_replace( '""', '"', $t_wo_quotes );
	return $t_wo_quotes;
}

# --------------------
function read_csv_file( $p_filename ) {
	$t_regexp = '/\G((?:[^"\r\n]+|"[^"]*")+)[\r|\n]*/sm';

	$t_file_content = file_get_contents( $p_filename );
	preg_match_all($t_regexp, $t_file_content, $t_file_rows);
	return $t_file_rows[1];
}

# --------------------
function read_csv_row( $p_file_row, $p_separator ) {
	$t_regexp = '/\G(?:\A|\\' . $p_separator . ')([^"\\' . $p_separator . ']+|(?:"[^"]*")*)/sm';

	preg_match_all($t_regexp, $p_file_row, $t_row_element);

	# Return result
	return array_map( 'csv_string_unescape', $t_row_element[1] );
}

function category_get_id_by_name_ne( $p_category_name, $p_project_id ) {
	$t_category_table = db_get_table( 'mantis_category_table' );
	$t_project_name = project_get_name( $p_project_id );

	$t_query = "SELECT id FROM $t_category_table
			WHERE name=". db_param() . " AND (project_id=" . db_param() . " OR project_id = 0)";
	$t_result = db_query_bound( $t_query, array( $p_category_name, (int) $p_project_id ) );
	$t_count = db_num_rows( $t_result );
	if ( 1 > $t_count ) {
		return false;
	}

	return db_result( $t_result );
}

function prepare_output( $t_string , $t_encode_only = false ) {
	return string_html_specialchars( utf8_encode($t_string) );
}

function get_csv_import_category_id( $t_project_id , $t_category_name ) {
	project_ensure_exists( $t_project_id );

	$t_category_id = category_get_id_by_name_ne($t_category_name , $t_project_id);
	if( !$t_category_id )
	{
		return category_add( $t_project_id, $t_category_name );
	}
	else
	{
		return $t_category_id;
	};

	# Just in case...
	return null;
}

# --------------------
# Breaks up an enum string into num:value elements
function explode_enum_string( $p_enum_string ) {
	return explode( ',', $p_enum_string );
}
# --------------------
# Given one num:value pair it will return both in an array
# num will be first (element 0) value second (element 1)
function explode_enum_arr( $p_enum_elem ) {
	return explode( ':', $p_enum_elem );
}

function array_isearch( $str , $array ) {
	foreach($array as $k => $v) {
		if(strcasecmp($str, $v) == 0) {
			return $k;
		}
	};
	return false;
}

#-----------------------
function get_enum_column_value( $p_name, $p_row, $p_default ) {
	$t_value = get_column_value( $p_name, $p_row );
	if( is_blank( $t_value ) ) return $p_default;
	# First chance, search element in language enumeration string
	$t_element_enum_string = lang_get( $p_name . '_enum_string' );
	$t_arr = explode_enum_string( $t_element_enum_string );
	$t_arr_count = count( $t_arr );
	for( $i = 0; $i < $t_arr_count; $i++ ) {
		$elem_arr = explode_enum_arr( $t_arr[$i] );
		if( $elem_arr[1] == $t_value ) {
			return $elem_arr[0];
		} elseif( $elem_arr[0] == $t_value ) {
				return $elem_arr[0];
		};
	};

	# Second chance, search element in configuration enumeration string
	$t_element_enum_string = config_get( $p_name . '_enum_string' );
	$t_arr = explode_enum_string( $t_element_enum_string );
	$t_arr_count = count( $t_arr );
	for( $i = 0; $i < $t_arr_count; $i++ ) {
		$elem_arr = explode_enum_arr( $t_arr[$i] );
		if( $elem_arr[1] == $t_value ) {
			return $elem_arr[0];
		} elseif( $elem_arr[0] == $t_value )	{
				return $elem_arr[0];
		};
	};
	return $p_default;
}

#-----------------------
function get_date_column_value( $p_name, $p_row, $p_default ) {
	$t_date = get_column_value( $p_name, $p_row );

	if(!is_blank($t_date))
	{
	   return is_numeric($t_date) ? $t_date : strtotime( $t_date );
	}

	return $p_default;
}


function string_MkPretty( $t_str ) {
	$t_str = utf8_encode(strtolower(trim(utf8_decode($t_str))));
	$t_str = preg_replace('/\xfc/ui', 'ue', $t_str);
	$t_str = preg_replace('/\xf6/ui', 'oe', $t_str);
	$t_str = preg_replace('/\xe4/ui', 'ae', $t_str);
	$t_str = preg_replace('/\xdf/ui', 'ss', $t_str);
	return $t_str;
}


function get_user_column_value( $p_name, $p_row, $p_default ) {
	$t_username = get_column_value( $p_name, $p_row );
	if( is_blank( $t_username ) ) {
		return $p_default;
	}

	if( ($t_user_id = user_get_id_by_name($t_username)) !== false ) {
		return $t_user_id;
	}

	$t_username_pretty = string_MkPretty( $t_username );
	if ( $t_username_pretty !== $t_username ) {
		if ( $t_user_id = user_get_id_by_name( $t_username_pretty ) ) {
			return $t_user_id;
		}
	}

	if ( strstr( $t_username, '@' ) === false ) {
		$t_email = $t_username . '@localhost';
	} else {
		$t_email = $t_username;
	}

	$t_password = time() . rand( 1, 10000 );

	if( user_create(
			$t_username,
			$t_password,    # password
			$t_email,       # email address
			null,           # access_level
			false,          # protected
			false) ) {      # enabled - disable user - admin can reset password and enable.
		return user_get_id_by_name($t_username);
	}

	return $p_default;
}

#-----------------------
function get_column_value( $p_name, $p_row, $p_default = '' ) {
	global $f_columns;
	$t_column = array_isearch( $p_name, $f_columns );
	$t_value = ( ($t_column === false) || (!isset( $p_row[$t_column] )) ) ? $p_default : utf8_encode(trim( $p_row[$t_column] ));

	$t_value = str_replace( '\n', "\n", $t_value );
	return $t_value;
}

#-----------------------
function column_value_exists( $p_name, $p_row ) {
	global $f_columns;
	$t_column = array_isearch( $p_name, $f_columns );
	return (($t_column !== false) && (isset( $p_row[$t_column] ))) ? true : false;
}

function get_category_column_value( $p_name, $p_row, $p_project, $p_default ) {
	$t_category_id = category_get_id_by_name_ne ( trim ( get_column_value( $p_name, $p_row ) ) , $p_project );
	return (($t_category_id === false) ? $p_default : $t_category_id);
}

/**
 * A more readable var_dump
 *
 * @param mixed
 */
function hvar_dump()
{
	$numargs = func_num_args();
	$arg_list = func_get_args();
	echo '<pre style="text-align: left;">';
	for ($i = 0; $i < $numargs; $i++)
	{
		var_dump($arg_list[$i]);
	}

	echo '</pre>';
}
