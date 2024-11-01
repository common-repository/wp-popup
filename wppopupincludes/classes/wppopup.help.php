<?php
if(!class_exists('wppopup_Help')) {

	class wppopup_Help {
		// The screen we want to access help for
		var $screen = false;

		function __construct( &$screen = false ) {

			$this->screen = $screen;

			//print_r($screen);

		}

		function wppopup_Help( &$screen = false ) {
			$this->__construct( $screen );
		}

		function show() {



		}

		function attach() {

			switch($this->screen->id) {

				case 'toplevel_page_wppopup':						$this->main_help();
																	break;

				case 'pop-overs_page_wppopupaddons':				$this->addons_help();
																	break;


			}

		}

		// Specific help content creation functions

		function main_help() {

			ob_start();
			include_once(wppopup_dir('wppopupincludes/help/wppopup.help.php'));
			$help = ob_get_clean();

			ob_start();
			include_once(wppopup_dir('wppopupincludes/help/wppopupedit.help.php'));
			$helpedit = ob_get_clean();

			$this->screen->add_help_tab( array(
				'id'      => 'wppopup',
				'title'   => __( 'Overview' , 'wppopup' ),
				'content' => $help,
			) );

			$this->screen->add_help_tab( array(
				'id'      => 'edit',
				'title'   => __( 'Adding / Editing' , 'wppopup' ),
				'content' => $helpedit,
			) );

		}

		function addons_help() {

			ob_start();
			include_once(wppopup_dir('wppopupincludes/help/wppopupaddons.help.php'));
			$help = ob_get_clean();

			$this->screen->add_help_tab( array(
				'id'      => 'addons',
				'title'   => __( 'Add-ons', 'wppopup' ),
				'content' => $help,
			) );

		}


	}

}
?>