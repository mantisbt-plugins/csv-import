<?php
class Csv_importPlugin extends MantisPlugin
{
	function register() {
		$this->name = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );
		$this->version = '1.4.1';
		$this->requires = array( 'MantisCore' => '1.2.0' );
		$this->author = 'Bug 4220 Team';
		$this->contact = 'https://github.com/mantisbt-plugins/csv-import/';
		$this->url = 'https://github.com/mantisbt-plugins/csv-import/';
		$this->page = 'config';
	}

	function config() {
		return array(
			'import_issues_threshold'	=> MANAGER ,
			);
	}

	function hooks() {
		return array(
			'EVENT_MENU_MANAGE' => 'csv_import_menu',
		);
	}

	function csv_import_menu() {
		return array(
			'<a href="' . plugin_page( 'import_issues_page_init' ) . '">' . plugin_lang_get( 'manage_issues_link' ) . '</a>',
		);
	}
}
