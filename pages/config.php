<?php
auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );
html_page_top1( plugin_lang_get( 'title' ) );
html_page_top2();
print_manage_menu();

# TODO : Understand the warning ...
$t_import_issues_treshold = plugin_config_get( 'import_issues_treshold' );

?>
<br/>
<form action="<?php echo plugin_page( 'config_edit' ) ?>" method="post">
<table align="center" class="width75" cellspacing="1">
<tr>
	<td class="form-title" colspan="3">
		<?php echo plugin_lang_get( 'title' ) . ': ' . plugin_lang_get( 'config' ) ?>
	</td>
</tr>
<tr <?php echo helper_alternate_class() ?>>
	<td class="category">
		<?php echo lang_get( 'access_level' ) ?>
	</td>
	<td>
		<select name="import_issues_trshold">
			<?php print_enum_string_option_list( 'access_levels', $t_import_issues_treshold ) ?>
		</select>
	</td>
</tr> 
<tr>
	<td class="center" colspan="3">
		<input type="submit" class="button" value="<?php echo plugin_lang_get( 'update_config' ) ?>" />
	</td>
</tr>
</table>
</form>

<?php
html_page_bottom1( __FILE__ );
