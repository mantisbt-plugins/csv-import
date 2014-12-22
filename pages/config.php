<?php
auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

layout_page_header( plugin_lang_get( 'title' ) );
layout_page_begin( __FILE__ );

print_manage_menu();

$t_import_issues_threshold = plugin_config_get( 'import_issues_threshold' );
?>

<br />

<div class="col-xs-12 col-md-8 col-md-offset-2">
	<div class="space-10"></div>
	<div id="config-div" class="form-container">
		<form id="config-form" method="post" action="<?php echo plugin_page( 'config_edit' ) ?>">
			<div class="widget-box widget-color-blue2">
				<div class="widget-header widget-header-small">
					<h4 class="widget-title lighter">
						<i class="ace-icon fa fa-user"></i>
						<?php echo plugin_lang_get( 'title' ) . ': ' . plugin_lang_get( 'config' ) ?>
					</h4>
				</div>
				<div class="widget-body">
					<div class="widget-main no-padding">
						<div class="form-container">
							<div class="table-responsive">
								<table class="table table-bordered table-condensed table-striped">
									<fieldset>
										<!-- mantistouch_url -->
										<tr>
											<td class="category">
												<?php echo lang_get( 'access_level' ) ?>
											</td>
											<td>
												<select name="import_issues_threshold">
													<?php print_enum_string_option_list( 'access_levels', $t_import_issues_threshold ) ?>
												</select>
											</td>
										</tr>
									</fieldset>
								</table>
							</div>
						</div>
					</div>

					<div class="widget-toolbox padding-8 clearfix">
						<input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo plugin_lang_get( 'update_config' ) ?>" />
					</div>
				</div>
			</div>
		</form>
	</div>
	<div class="space-10"></div>
</div>

<?php
layout_page_end();
