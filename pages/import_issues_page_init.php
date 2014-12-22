<?php
# Mantis - a php based bugtracking system
require_once( 'core.php' );
access_ensure_project_level( plugin_config_get( 'import_issues_threshold' ) );

layout_page_header( plugin_lang_get( 'manage_issues' ) );
layout_page_begin( __FILE__ );

$import_page = plugin_page('import_issues_page_col_set');
?>
<br />
<?php
require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'import_issues_inc.php' );

$t_max_file_size = (int)min( ini_get_number( 'upload_max_filesize' ), ini_get_number( 'post_max_size' ),
config_get( 'max_file_size' ) );
?>

<div class="col-xs-12 col-md-8 col-md-offset-2">
   <div class="space-10"></div>
   <div id="config-div" class="form-container">
      <form method="post" enctype="multipart/form-data" action="<?php echo $import_page ?>">
         <div class="widget-box widget-color-blue2">
            <div class="widget-header widget-header-small">
               <h4 class="widget-title lighter">
                  <?php echo plugin_lang_get( 'issues_file' ) ?>
               </h4>
            </div>
            <div class="widget-body">
               <div class="widget-main no-padding">
                  <div class="form-container">
                     <div class="table-responsive">
                        <table class="table table-bordered table-condensed table-striped">
                           <fieldset>
                              <tr>
                                 <td class="category">
                                    <input id="edt_cell_separator" name="edt_cell_separator" type="text" size="15" maxlength="1" value="<?php echo config_get( 'csv_separator' )?>" style="text-align:center"/>
                                 </td>
                                 <td>
                                    <?php echo plugin_lang_get( 'file_format_col_spacer' ) ?> -
                                    <a href="#" onclick="javascript:document.getElementById('edt_cell_separator').value=String.fromCharCode(9);">[<?php echo plugin_lang_get( 'tab_csv_separator' ) ?>]</a>
                                 </td>
                              </tr>

                              <tr>
                                 <td class="category" style="text-align:center">
                                    <input type="checkbox" name="cb_skip_first_line" value="1" checked="checked"/>
                                 </td>
                                 <td>
                                    <?php echo plugin_lang_get( 'skip_first_line' ) ?>
                                 </td>
                              </tr>

                              <tr>
                                 <td class="category" style="text-align:center">
                                    <input type="checkbox" name="cb_skip_blank_lines" value="1" checked="checked"/>
                                 </td>
                                 <td>
                                    <?php echo plugin_lang_get( 'skip_blank_lines' ) ?>
                                 </td>
                              </tr>

                              <tr>
                                 <td class="category" style="text-align:center">
                                    <input type="checkbox" name="cb_trim_blank_cols" value="1"/>
                                 </td>
                                 <td colspan="4">
                                    <?php echo plugin_lang_get( 'skip_blank_columns' ) ?>
                                 </td>
                              </tr>

                              <tr>
                                 <td class="category" style="text-align:center">
                                    <input type="checkbox" name="cb_create_unknown_cats" value="1"/>
                                 </td>
                                 <td colspan="4">
                                    <?php echo plugin_lang_get( 'create_unknown_cats' ) ?>
                                 </td>
                              </tr>

                              <tr>
                                 <td class="category" width="15%">
                                    <?php echo lang_get( 'select_file' ) ?><br />
                                    <?php echo '<span class="small">(' . plugin_lang_get( 'max_file_size_label' ) . ': ' . number_format( $t_max_file_size/1000 ) . 'k)</span>'?>
                                 </td>
                                 <td width="85%" colspan="2">
                                    <input type="hidden" name="max_file_size" value="<?php echo $t_max_file_size ?>" />
                                    <input type="file" name="import_file" size="40" />
                                 </td>
                              </tr>
                           </fieldset>
                        </table>
                     </div>
                  </div>
               </div>
               <div class="widget-toolbox padding-8 clearfix">
                  <input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo lang_get( 'upload_file_button' ) ?>" />
               </div>
            </div>
         </div>
      </form>
   </div>
   <div class="space-10"></div>
</div>

<?php
layout_page_end();
