<?php

if(!class_exists('wppopupadmin')) {

	class wppopupadmin {

		var $build = 5;

		var $db;

		var $tables = array( 'wppopup', 'wppopup_ip_cache' );
		var $wppopup;
		var $wppopup_ip_cache;

		function __construct() {

			global $wpdb;

			$this->db =& $wpdb;

			foreach($this->tables as $table) {
				$this->$table = wppopup_db_prefix($this->db, $table);
			}

			add_action( 'admin_menu', array(&$this, 'add_menu_pages' ) );
			add_action( 'network_admin_menu', array(&$this, 'add_menu_pages' ) );

			add_action( 'plugins_loaded', array(&$this, 'load_textdomain'));

			// Add header files
			add_action('load-toplevel_page_wppopup', array(&$this, 'add_admin_header_wppopup_menu'));
			add_action('load-pop-overs_page_wppopupaddons', array(&$this, 'add_admin_header_wppopup_addons'));

			// Ajax calls
			add_action( 'wp_ajax_wppopup_update_order', array(&$this, 'ajax_update_wppopup_order') );

			$installed = get_option('wppopup_installed', false);

			if($installed === false || $installed != $this->build) {
				$this->install();

				update_option('wppopup_installed', $this->build);
			}

		}

		function wppopupadmin() {
			$this->__construct();
		}

		function install() {

			if($this->db->get_var( "SHOW TABLES LIKE '" . $this->wppopup . "' ") != $this->wppopup) {
				 $sql = "CREATE TABLE `" . $this->wppopup . "` (
				  	`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					  `wppopup_title` varchar(250) DEFAULT NULL,
					  `wppopup_content` text,
					  `wppopup_settings` text,
					  `wppopup_order` bigint(20) DEFAULT '0',
					  `wppopup_active` int(11) DEFAULT '0',
					  PRIMARY KEY (`id`)
					)";

				$this->db->query($sql);

			}

			// Add in IP cache table
			if($this->db->get_var( "SHOW TABLES LIKE '" . $this->wppopup_ip_cache . "' ") != $this->wppopup_ip_cache) {
				 $sql = "CREATE TABLE `" . $this->wppopup_ip_cache . "` (
				  	`IP` varchar(12) NOT NULL DEFAULT '',
					  `country` varchar(2) DEFAULT NULL,
					  `cached` bigint(20) DEFAULT NULL,
					  PRIMARY KEY (`IP`),
					  KEY `cached` (`cached`)
					)";

				$this->db->query($sql);

			}

		}

		function load_textdomain() {

			$locale = apply_filters( 'wppopup_locale', get_locale() );
			$mofile = wppopup_dir( "wppopupincludes/languages/wppopup-$locale.mo" );

			if ( file_exists( $mofile ) )
				load_textdomain( 'wppopup', $mofile );

		}

		function add_menu_pages() {

			global $submenu;

			if(is_multisite() && (defined('PO_GLOBAL') && PO_GLOBAL == true)) {
				if(function_exists('is_network_admin') && is_network_admin()) {
					add_menu_page(__('WP Popup','wppopup'), __('WP Popup','wppopup'), 'manage_options',  'wppopup', array(&$this,'handle_wppopup_admin'), wppopup_url('wppopupincludes/images/window.png'));
				}
			} else {
				if(!function_exists('is_network_admin') || !is_network_admin()) {
					add_menu_page(__('WP Popup','wppopup'), __('WP Popup','wppopup'), 'manage_options',  'wppopup', array(&$this,'handle_wppopup_admin'), wppopup_url('wppopupincludes/images/window.png'));
				}
			}

			$addnew = add_submenu_page('wppopup', __('Create New WP Popup','wppopup'), __('Create New','wppopup'), 'manage_options', "wppopup&amp;action=add", array(&$this,'handle_addnewwppopup_panel'));
			add_submenu_page('wppopup', __('Manage Add-ons Plugins','wppopup'), __('Add-ons','wppopup'), 'manage_options', "wppopupaddons", array(&$this,'handle_addons_panel'));

		}

		function sanitise_array($arrayin) {

			foreach( (array) $arrayin as $key => $value) {
				$arrayin[$key] = htmlentities(stripslashes($value) ,ENT_QUOTES, 'UTF-8');
			}

			return $arrayin;
		}

		function ajax_update_wppopup_order() {

			if(check_ajax_referer( 'wppopup_order', '_ajax_nonce', false )) {
				$newnonce = wp_create_nonce('wppopup_order');

				$data = $_POST['data'];
				parse_str($data);
				foreach($dragbody as $key => $value) {
					$this->reorder_wppopups( $value, $key );
				}
				die($newnonce);
			} else {
				die('fail');
			}

		}

		function update_admin_header_wppopup() {

			global $action, $page, $allowedposttags;

			wp_reset_vars( array('action', 'page') );

			if($action == 'updated') {
				check_admin_referer('update-wppopup');

				$usemsg = 1;

				if(function_exists('get_site_option') && defined('PO_GLOBAL') && PO_GLOBAL == true) {
					$updateoption = 'update_site_option';
					$getoption = 'get_site_option';
				} else {
					$updateoption = 'update_option';
					$getoption = 'get_option';
				}

				if(isset($_POST['wppopupcontent'])) {
					if ( !current_user_can('unfiltered_html') ) {
						if(wp_kses($_POST['wppopupcontent'], $allowedposttags) != $_POST['wppopupcontent']) {
							$usemsg = 2;
						}
						$updateoption('wppopup_content', wp_kses($_POST['wppopupcontent'], $allowedposttags));
					} else {
						$updateoption('wppopup_content', $_POST['wppopupcontent']);
					}

				}

				if(isset($_POST['wppopupwidth']) || isset($_POST['wppopupheight'])) {

					$width = $_POST['wppopupwidth'];
					$height = $_POST['wppopupheight'];

					if($width == '') $width = '500px';
					if($height == '') $height = '200px';

					$updateoption('wppopup_size', array("width" => $width, "height" => $height));
				}

				if(isset($_POST['wppopupleft']) || isset($_POST['wppopuptop'])) {

					$left = $_POST['wppopupleft'];
					$top = $_POST['wppopuptop'];

					if($left == '') $left = '100px';
					if($top == '') $top = '100px';

					$updateoption('wppopup_location', array("left" => $left, "top" => $top));
				}

				if(isset($_POST['wppopupmargintop']) || isset($_POST['wppopupmarginleft']) || isset($_POST['wppopupmarginright']) || isset($_POST['wppopupmarginbottom'])) {

					$mleft = $_POST['wppopupmarginleft'];
					$mtop = $_POST['wppopupmargintop'];
					$mright = $_POST['wppopupmarginright'];
					$mbottom = $_POST['wppopupmarginbottom'];

					if($mleft == '') $mleft = '0px';
					if($mtop == '') $mtop = '0px';
					if($mright == '') $mright = '0px';
					if($mbottom == '') $mbottom = '0px';

					$updateoption('wppopup_margin', array('left' => $mleft, 'top' => $mtop, 'right' => $mright, 'bottom' => $mbottom));

				}

				if(isset($_POST['wppopupbackground']) || isset($_POST['wppopupforeground'])) {

					$back = $_POST['wppopupbackground'];
					$fore = $_POST['wppopupforeground'];

					if($back == '') $back = 'FFFFFF';
					if($fore == '') $fore = '000000';

					$updateoption('wppopup_colour', array("back" => $back, "fore" => $fore));
				}

				if(isset($_POST['wppopupcheck'])) {

					$updateoption('wppopup_check', $_POST['wppopupcheck']);

					if(isset($_POST['wppopupereg'])) {
						$updateoption('wppopup_ereg', $_POST['wppopupereg']);
					}

					if(isset($_POST['wppopupcount'])) {
						$updateoption('wppopup_count', intval($_POST['wppopupcount']) );
					}

				}

				if(isset($_POST['wppopupusejs'])) {
					$updateoption('wppopup_usejs', 'yes' );
				} else {
					$updateoption('wppopup_usejs', 'no' );
				}

				wp_safe_redirect( add_query_arg( array('msg' => $usemsg), wp_get_referer() ) );

			}

		}

		function add_admin_header_wppopup_menu() {

			$this->add_admin_header_core();

			if(in_array($_GET['action'], array('edit', 'add'))) {
				$this->add_admin_header_wppopup();
			} else {
				wp_enqueue_script('wppopupdragadminjs', wppopup_url('wppopupincludes/js/jquery.tablednd_0_5.js'), array('jquery'), $this->build);
				wp_enqueue_script('wppopupadminjs', wppopup_url('wppopupincludes/js/wppopupmenu.js'), array('jquery', 'wppopupdragadminjs' ), $this->build);

				wp_localize_script('wppopupadminjs', 'wppopup', array(	'ajaxurl'		=>	admin_url( 'admin-ajax.php' ),
				 														'ordernonce'	=>	wp_create_nonce('wppopup_order'),
																		'dragerror'		=>	__('An error occured updating the WP Popup order.','wppopup'),
																		'deletewppopup'	=>	__('Are you sure you want to delete this WP Popup?','wppopup')
																	));

				wp_enqueue_style('wppopupadmincss', wppopup_url('wppopupincludes/css/wppopupmenu.css'), array(), $this->build);

				// Check for transfer
				if(isset($_GET['transfer'])) {
					$this->handle_wppopup_transfer();
				}

				// Check for existing wppopups
				if($this->has_existing_wppopup()) {
					add_action('all_admin_notices', array(&$this, 'show_wppopup_transfer_offer'));
				}

				$this->update_wppopup_admin();
			}

		}

		function has_existing_wppopup() {

			if(function_exists('get_site_option') && defined('PO_GLOBAL') && PO_GLOBAL == true) {
				$getoption = 'get_site_option';
			} else {
				$getoption = 'get_option';
			}

			$popsexist = $this->db->get_var( "SELECT COUNT(*) FROM {$this->wppopup}");

			if($popsexist == 0 && $getoption('wppopup_content','no') != 'no' && $getoption('wppopup_notranfers', 'no') == 'no') {
				// No pops - and one set in the options
				return true;
			} else {
				return false;
			}
		}

		function show_wppopup_transfer_offer() {

			echo '<div class="updated fade below-h2"><p>' . sprintf(__("Welcome to wppopup, would you like to transfer your existing wppopup to the new format? <a href='%s'>Yes please transfer it</a> / <a href='%s'>No thanks, I'll create a new one myself.</a>", 'wppopup'), wp_nonce_url('admin.php?page=wppopup&amp;transfer=yes', 'transferwppopup'), wp_nonce_url('admin.php?page=wppopup&amp;transfer=no','notransferwppopup') ) . '</p></div>';

		}

		function handle_wppopup_transfer() {

			if(function_exists('get_site_option') && defined('PO_GLOBAL') && PO_GLOBAL == true) {
				$updateoption = 'update_site_option';
				$getoption = 'get_site_option';
			} else {
				$updateoption = 'update_option';
				$getoption = 'get_option';
			}

			switch($_GET['transfer']) {

				case 'yes':		check_admin_referer('transferwppopup');
								$wppopup = array();

								$wppopup['wppopup_title'] = __('Transferred wppopup', 'wppopup');
								$wppopup['wppopup_content'] = $getoption('wppopup_content');

								$wppopup['wppopup_settings'] = array();
								$wppopup['wppopup_settings']['wppopup_size'] = $getoption('wppopup_size');
								$wppopup['wppopup_settings']['wppopup_location'] = $getoption('wppopup_location');;
								$wppopup['wppopup_settings']['wppopup_colour'] = $getoption('wppopup_colour');
								$wppopup['wppopup_settings']['wppopup_margin'] = $getoption('wppopup_margin');
								$wppopup['wppopup_settings']['wppopup_check'] = $getoption('wppopup_check');

								$wppopup['wppopup_settings']['wppopup_count'] = $getoption('wppopup_count');
								$wppopup['wppopup_settings']['wppopup_usejs'] = $getoption('wppopup_usejs');

								$wppopup['wppopup_settings']['wppopup_style'] = 'Default';

								$wppopup['wppopup_settings'] = serialize($wppopup['wppopup_settings']);

								$wppopup['wppopup_active'] = 1;

								$this->db->insert( $this->wppopup, $wppopup );
								wp_safe_redirect( remove_query_arg( 'transfer', remove_query_arg( '_wpnonce' ) ) );
								break;

				case 'no':		check_admin_referer('notransferwppopup');
								$updateoption('wppopup_notranfers', 'yes');
								wp_safe_redirect( remove_query_arg( 'transfer', remove_query_arg( '_wpnonce' ) ) );
								break;
			}

		}

		function add_admin_header_core() {
			// Add in help pages
			$screen = get_current_screen();
			$help = new wppopup_Help( $screen );
			$help->attach();

		}

		function add_admin_header_wppopup() {

			global $wp_version;

			wp_enqueue_script('wppopupadminjs', wppopup_url('wppopupincludes/js/wppopupadmin.js'), array( 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable' ), $this->build);

			if(version_compare( preg_replace('/-.*$/', '', $wp_version), "3.3", '<')) {
				wp_enqueue_style('wppopupadmincss', wppopup_url('wppopupincludes/css/wppopupadmin.css'), array('widgets'), $this->build);
			} else {
				wp_enqueue_style('wppopupadmincss', wppopup_url('wppopupincludes/css/wppopupadmin.css'), array(), $this->build);
			}

			$this->update_admin_header_wppopup();
		}

		function add_admin_header_wppopup_addons() {

			$this->add_admin_header_core();

			$this->handle_addons_panel_updates();
		}

		function reorder_wppopups( $wppopup_id, $order ) {

			$this->db->update( $this->wppopup, array( 'wppopup_order' => $order ), array( 'id' => $wppopup_id) );

		}

		function get_wppopups() {

			$sql = $this->db->prepare( "SELECT * FROM {$this->wppopup} ORDER BY wppopup_order ASC" );

			return $this->db->get_results( $sql );

		}

		function get_wppopup( $id ) {
			return $this->db->get_row( $this->db->prepare("SELECT * FROM {$this->wppopup} WHERE id = %d", $id) );
		}

		function activate_wppopup( $id ) {
			return $this->db->update( $this->wppopup, array( 'wppopup_active' => 1 ), array( 'id' => $id) );
		}

		function deactivate_wppopup( $id ) {
			return $this->db->update( $this->wppopup, array( 'wppopup_active' => 0 ), array( 'id' => $id) );
		}

		function toggle_wppopup( $id ) {

			$sql = $this->db->prepare( "UPDATE {$this->wppopup} SET wppopup_active = NOT wppopup_active WHERE id = %d", $id );

			return $this->db->query( $sql );

		}

		function delete_wppopup( $id ) {

			return $this->db->query( $this->db->prepare( "DELETE FROM {$this->wppopup} WHERE id = %d", $id ) );

		}

		function add_wppopup( $data ) {

			global $action, $page, $allowedposttags;

			$wppopup = array();

			$wppopup['wppopup_title'] = $_POST['wppopup_title'];

			if ( !current_user_can('unfiltered_html') ) {
				$wppopup['wppopup_content'] = wp_kses($_POST['wppopup_content'], $allowedposttags);
			} else {
				$wppopup['wppopup_content'] = $_POST['wppopup_content'];
			}

			$wppopup['wppopup_settings'] = array();
			$wppopup['wppopup_settings']['wppopup_size'] = array( 'width' => $_POST['wppopupwidth'], 'height' => $_POST['wppopupheight'] );
			$wppopup['wppopup_settings']['wppopup_location'] = array( 'left' => $_POST['wppopupleft'], 'top' => $_POST['wppopuptop'] );
			$wppopup['wppopup_settings']['wppopup_colour'] = array( 'back' => $_POST['wppopupbackground'], 'fore' => $_POST['wppopupforeground'] );
			$wppopup['wppopup_settings']['wppopup_margin'] = array( 'left' => $_POST['wppopupmarginleft'], 'top' => $_POST['wppopupmargintop'], 'right' => $_POST['wppopupmarginright'], 'bottom' => $_POST['wppopupmarginbottom'] );
			$wppopup['wppopup_settings']['wppopup_check'] = $_POST['wppopupcheck'];

			if(isset($_POST['wppopupereg'])) {
				$wppopup['wppopup_settings']['wppopup_ereg'] = $_POST['wppopupereg'];
			} else {
				$wppopup['wppopup_settings']['wppopup_ereg'] = '';
			}

			if(isset($_POST['wppopupcount'])) {
				$wppopup['wppopup_settings']['wppopup_count'] = $_POST['wppopupcount'];
			} else {
				$wppopup['wppopup_settings']['wppopup_count'] = 3;
			}

			if($_POST['wppopupusejs'] == 'yes') {
				$wppopup['wppopup_settings']['wppopup_usejs'] = 'yes';
			} else {
				$wppopup['wppopup_settings']['wppopup_usejs'] = 'no';
			}

			$wppopup['wppopup_settings']['wppopup_style'] = $_POST['wppopupstyle'];

			if($_POST['wppopuphideforeverlink'] == 'yes') {
				$wppopup['wppopup_settings']['wppopuphideforeverlink'] = 'yes';
			} else {
				$wppopup['wppopup_settings']['wppopuphideforeverlink'] = 'no';
			}

			$wppopup['wppopup_settings']['wppopupdelay'] = $_POST['wppopupdelay'];

			$wppopup['wppopup_settings']['onurl'] = explode("\n", $_POST['wppopuponurl']);
			$wppopup['wppopup_settings']['notonurl'] = explode("\n", $_POST['wppopupnotonurl']);

			$wppopup['wppopup_settings']['incountry'] = $_POST['wppopupincountry'];
			$wppopup['wppopup_settings']['notincountry'] = $_POST['wppopupnotincountry'];

			$wppopup['wppopup_settings'] = serialize($wppopup['wppopup_settings']);

			if(isset($_POST['addandactivate'])) {
				$wppopup['wppopup_active'] = 1;
			}

			return $this->db->insert( $this->wppopup, $wppopup );

		}

		function update_wppopup( $id, $data ) {

			global $action, $page, $allowedposttags;

			$wppopup = array();

			$wppopup['wppopup_title'] = $_POST['wppopup_title'];

			if ( !current_user_can('unfiltered_html') ) {
				$wppopup['wppopup_content'] = wp_kses($_POST['wppopup_content'], $allowedposttags);
			} else {
				$wppopup['wppopup_content'] = $_POST['wppopup_content'];
			}

			$wppopup['wppopup_settings'] = array();
			$wppopup['wppopup_settings']['wppopup_size'] = array( 'width' => $_POST['wppopupwidth'], 'height' => $_POST['wppopupheight'] );
			$wppopup['wppopup_settings']['wppopup_location'] = array( 'left' => $_POST['wppopupleft'], 'top' => $_POST['wppopuptop'] );
			$wppopup['wppopup_settings']['wppopup_colour'] = array( 'back' => $_POST['wppopupbackground'], 'fore' => $_POST['wppopupforeground'] );
			$wppopup['wppopup_settings']['wppopup_margin'] = array( 'left' => $_POST['wppopupmarginleft'], 'top' => $_POST['wppopupmargintop'], 'right' => $_POST['wppopupmarginright'], 'bottom' => $_POST['wppopupmarginbottom'] );
			$wppopup['wppopup_settings']['wppopup_check'] = $_POST['wppopupcheck'];

			if(isset($_POST['wppopupereg'])) {
				$wppopup['wppopup_settings']['wppopup_ereg'] = $_POST['wppopupereg'];
			} else {
				$wppopup['wppopup_settings']['wppopup_ereg'] = '';
			}

			if(isset($_POST['wppopupcount'])) {
				$wppopup['wppopup_settings']['wppopup_count'] = $_POST['wppopupcount'];
			} else {
				$wppopup['wppopup_settings']['wppopup_count'] = 3;
			}

			if($_POST['wppopupusejs'] == 'yes') {
				$wppopup['wppopup_settings']['wppopup_usejs'] = 'yes';
			} else {
				$wppopup['wppopup_settings']['wppopup_usejs'] = 'no';
			}

			$wppopup['wppopup_settings']['wppopup_style'] = $_POST['wppopupstyle'];

			if($_POST['wppopuphideforeverlink'] == 'yes') {
				$wppopup['wppopup_settings']['wppopuphideforeverlink'] = 'yes';
			} else {
				$wppopup['wppopup_settings']['wppopuphideforeverlink'] = 'no';
			}

			$wppopup['wppopup_settings']['wppopupdelay'] = $_POST['wppopupdelay'];

			$wppopup['wppopup_settings']['onurl'] = explode("\n", $_POST['wppopuponurl']);
			$wppopup['wppopup_settings']['notonurl'] = explode("\n", $_POST['wppopupnotonurl']);

			$wppopup['wppopup_settings']['incountry'] = $_POST['wppopupincountry'];
			$wppopup['wppopup_settings']['notincountry'] = $_POST['wppopupnotincountry'];

			$wppopup['wppopup_settings'] = serialize($wppopup['wppopup_settings']);

			return $this->db->update( $this->wppopup, $wppopup, array( 'id' => $id ) );

		}

		function update_wppopup_admin() {
			global $action, $page;

			wp_reset_vars( array('action', 'page') );

			if(isset($_REQUEST['action']) || isset($_REQUEST['action2'])) {

				if(!empty($_REQUEST['action2'])) {
					$_REQUEST['action'] = $_REQUEST['action2'];
				}

				switch($_REQUEST['action']) {


					case 'activate': 		$id = (int) $_GET['wppopup'];
											if(!empty($id)) {
												check_admin_referer('toggle-wppopup-' . $id);

												if( $this->activate_wppopup( $id ) ) {
													wp_safe_redirect( add_query_arg( 'msg', 3, wp_get_referer() ) );
												} else {
													wp_safe_redirect( add_query_arg( 'msg', 4, wp_get_referer() ) );
												}

											}
											break;


					case 'deactivate':		$id = (int) $_GET['wppopup'];
											if(!empty($id)) {
												check_admin_referer('toggle-wppopup-' . $id);

												if( $this->deactivate_wppopup( $id ) ) {
													wp_safe_redirect( add_query_arg( 'msg', 5, wp_get_referer() ) );
												} else {
													wp_safe_redirect( add_query_arg( 'msg', 6, wp_get_referer() ) );
												}

											}
											break;

					case 'toggle':			$ids = $_REQUEST['wppopupcheck'];

											if(!empty($ids)) {
												check_admin_referer('bulk-wppopups');
												foreach( (array) $ids as $id ) {
													$this->toggle_wppopup( $id );
												}
												wp_safe_redirect( add_query_arg( 'msg', 7, wp_get_referer() ) );
											}
											break;

					case 'delete':			$id = (int) $_GET['wppopup'];

											if(!empty($id)) {
												check_admin_referer('delete-wppopup-' . $id);

												if( $this->delete_wppopup( $id ) ) {
													wp_safe_redirect( add_query_arg( 'msg', 8, wp_get_referer() ) );
												} else {
													wp_safe_redirect( add_query_arg( 'msg', 9, wp_get_referer() ) );
												}
											}
											break;

					case 'added':			$id = (int) $_POST['id'];
											if(empty($id)) {
												check_admin_referer('update-wppopup');
												if($this->add_wppopup( $_POST )) {
													wp_safe_redirect( add_query_arg( 'msg', 10, 'admin.php?page=wppopup' ) );
												} else {
													wp_safe_redirect( add_query_arg( 'msg', 11, 'admin.php?page=wppopup' ) );
												}
											}
											break;

					case 'updated':			$id = (int) $_POST['id'];
											if(!empty($id)) {
												check_admin_referer('update-wppopup');
												if($this->update_wppopup( $id, $_POST )) {
													wp_safe_redirect( add_query_arg( 'msg', 1, 'admin.php?page=wppopup' ) );
												} else {
													wp_safe_redirect( add_query_arg( 'msg', 2, 'admin.php?page=wppopup' ) );
												}
											}
											break;

				}


			}

		}

		function handle_wppopup_admin() {
			global $action, $page;

			if($action == 'edit') {
				if(isset($_GET['wppopup'])) {
					$id = (int) $_GET['wppopup'];
					$this->handle_wppopup_edit_panel( $id );
					return; // So we don't see the rest of this page
				}
			}

			if($action == 'add') {
				$this->handle_wppopup_edit_panel( false );
				return; // So we don't see the rest of this page
			}

			$messages = array();
			$messages[1] = __('WP Popup updated.','wppopup');
			$messages[2] = __('WP Popup not updated.','wppopup');

			$messages[3] = __('WP Popup activated.','wppopup');
			$messages[4] = __('WP Popup not activated.','wppopup');

			$messages[5] = __('WP Popup deactivated.','wppopup');
			$messages[6] = __('WP Popup not deactivated.','wppopup');

			$messages[7] = __('WP Popup activation toggled.','wppopup');

			$messages[8] = __('WP Popup deleted.','wppopup');
			$messages[9] = __('WP Popup not deleted.','wppopup');

			$messages[10] = __('WP Popup added.','wppopup');
			$messages[11] = __('WP Popup not added.','wppopup');
			?>
			<div class='wrap'>
				<div class="icon32" id="icon-themes"><br></div>
				<h2><?php _e('Edit WP Popup','wppopup'); ?><a class="add-new-h2" href="admin.php?page=<?php echo $page; ?>&action=add"><?php _e('Add New','membership'); ?></a></h2>

				<?php
				if ( isset($_GET['msg']) ) {
					echo '<div id="message" class="updated fade"><p>' . $messages[(int) $_GET['msg']] . '</p></div>';
					$_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
				}

				?>

				<form method="get" action="?page=<?php echo esc_attr($page); ?>" id="posts-filter">

				<input type='hidden' name='page' value='<?php echo esc_attr($page); ?>' />

				<div class="tablenav">

				<div class="alignleft actions">
				<select name="action">
				<option selected="selected" value=""><?php _e('Bulk Actions', 'wppopup'); ?></option>
				<option value="toggle"><?php _e('Toggle activation', 'wppopup'); ?></option>
				</select>
				<input type="submit" class="button-secondary action" id="doaction" name="doaction" value="<?php _e('Apply', 'wppopup'); ?>">

				</div>

				<div class="alignright actions"></div>

				<br class="clear">
				</div>

				<div class="clear"></div>

				<?php
					wp_original_referer_field(true, 'previous'); wp_nonce_field('bulk-wppopups');

					$columns = array(	"name"		=>	__('WP Popup Name', 'wppopup'),
										"rules" 	=> 	__('Conditions','wppopup'),
										"active"	=>	__('Active','wppopup')
									);

					$columns = apply_filters('wppopup_columns', $columns);

					$wppopups = $this->get_wppopups();

				?>

				<table cellspacing="0" class="widefat fixed" id="dragtable">
					<thead>
					<tr>

					<th style="width: 20px;" class="manage-column column-drag" id="cb" scope="col"></th>
					<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>

					<?php
						foreach($columns as $key => $col) {
							?>
							<th style="" class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>" scope="col"><?php echo $col; ?></th>
							<?php
						}
					?>
					</tr>
					</thead>

					<tfoot>
					<tr>

					<th style="" class="manage-column column-drag" scope="col"></th>
					<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>

					<?php
						reset($columns);
						foreach($columns as $key => $col) {
							?>
							<th style="" class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>" scope="col"><?php echo $col; ?></th>
							<?php
						}
					?>
					</tr>
					</tfoot>

					<tbody id='dragbody'>
						<?php
						if($wppopups) {
							$wppopupcount = 0;
							foreach($wppopups as $key => $wppopup) {
								?>
								<tr valign="middle" class="alternate draghandle" id="<?php echo $wppopup->id; ?>">

									<td class="check-drag" scope="row">
										&nbsp;
									</td>
									<td class="check-column" scope="row"><input type="checkbox" value="<?php echo $wppopup->id; ?>" name="wppopupcheck[]"></td>

									<td class="column-name">
										<strong><a href='<?php echo "?page=" . $page . "&amp;action=edit&amp;wppopup=" . $wppopup->id . ""; ?>'><?php echo esc_html($wppopup->wppopup_title); ?></a></strong>
										<?php
											$actions = array();

											$actions['edit'] = "<span class='edit'><a href='?page=" . $page . "&amp;action=edit&amp;wppopup=" . $wppopup->id . "'>" . __('Edit', 'wppopup') . "</a></span>";

											if($wppopup->wppopup_active) {
												$actions['toggle'] = "<span class='edit activate'><a href='" . wp_nonce_url("?page=" . $page. "&amp;action=deactivate&amp;wppopup=" . $wppopup->id . "", 'toggle-wppopup-' . $wppopup->id) . "'>" . __('Deactivate', 'wppopup') . "</a></span>";
											} else {
												$actions['toggle'] = "<span class='edit deactivate'><a href='" . wp_nonce_url("?page=" . $page. "&amp;action=activate&amp;wppopup=" . $wppopup->id . "", 'toggle-wppopup-' . $wppopup->id) . "'>" . __('Activate', 'wppopup') . "</a></span>";
											}

											$actions['delete'] = "<span class='delete'><a href='" . wp_nonce_url("?page=" . $page. "&amp;action=delete&amp;wppopup=" . $wppopup->id . "", 'delete-wppopup-' . $wppopup->id) . "'>" . __('Delete', 'wppopup') . "</a></span>";
										?>
										<br><div class="row-actions"><?php echo implode(" | ", $actions); ?></div>
										</td>

									<td class="column-name">
										<?php
											$p = maybe_unserialize($wppopup->wppopup_settings);
											$rules = $p['wppopup_check'];
											foreach( (array) $rules as $key => $value ) {
												if($key == 'order') {
													continue;
												}
												switch($key) {

													case 'supporter':		_e('Site is not a Pro-site', 'wppopup');
																			break;

													case 'isloggedin':		_e('Visitor is logged in', 'wppopup');
																			break;

													case 'loggedin':		_e('Visitor is not logged in', 'wppopup');
																			break;

													case 'commented':		_e('Visitor has never commented', 'wppopup');
																			break;

													case 'searchengine':	_e('Visit via a search engine', 'wppopup');
																			break;

													case 'internal':		_e('Visit not via an Internal link', 'wppopup');
																			break;

													case 'referrer':		_e('Visit via specific referer', 'wppopup');
																			break;

													case 'count':			_e('wppopup shown less than x times', 'wppopup');
																			break;

													case 'onurl':			_e('On specific URL', 'wppopup');
																			break;

													case 'notonurl':		_e('Not on specific URL', 'wppopup');
																			break;

													case 'incountry':		_e('In a specific country', 'wppopup');
																			break;

													case 'notincountry':	_e('Not in a specific country', 'wppopup');
																			break;

													default:				echo apply_filters('wppopup_nice_rule_name', $key);
																			break;
											}
											echo "<br/>";
										}
										?>
										</td>
									<td class="column-active">
										<?php
											if($wppopup->wppopup_active) {
												echo "<strong>" . __('Active', 'wppopup') . "</strong>";
											} else {
												echo __('Inactive', 'wppopup');
											}
										?>
									</td>
							    </tr>
								<?php
							}
						} else {
							$columncount = count($columns) + 2;
							?>
							<tr valign="middle" class="alternate" >
								<td colspan="<?php echo $columncount; ?>" scope="row"><?php _e('No WP Popup were found.','wppopup'); ?></td>
						    </tr>
							<?php
						}
						?>

					</tbody>
				</table>


				<div class="tablenav">

				<div class="alignleft actions">
				<select name="action2">
					<option selected="selected" value=""><?php _e('Bulk Actions', 'wppopup'); ?></option>
					<option value="toggle"><?php _e('Toggle activation', 'wppopup'); ?></option>
				</select>
				<input type="submit" class="button-secondary action" id="doaction2" name="doaction2" value="<?php _e('Apply', 'wppopup'); ?>">
				</div>
				<div class="alignright actions"></div>
				<br class="clear">
				</div>

				</form>

			</div> <!-- wrap -->
			<?php
		}


		function handle_wppopup_edit_panel( $id = false ) {

			global $page;

			if(function_exists('get_site_option') && defined('PO_GLOBAL') && PO_GLOBAL == true) {
				$updateoption = 'update_site_option';
				$getoption = 'get_site_option';
			} else {
				$updateoption = 'update_option';
				$getoption = 'get_option';
			}


			if($id !== false) {
				$wppopup = $this->get_wppopup( $id );

				$wppopup->wppopup_settings = unserialize($wppopup->wppopup_settings);
			} else {
				$wppopup = new stdClass;
				$wppopup->wppopup_title = __('New WP Popup','wppopup');
				$wppopup->wppopup_content = "";
			}

			$wppopup_title = stripslashes($wppopup->wppopup_title);

			$wppopup_content = stripslashes($wppopup->wppopup_content);

			if(empty($wppopup->wppopup_settings)) {
				$wppopup->wppopup_settings = array(	'wppopup_size'		=>	array('width' => '500px', 'height' => '200px'),
													'wppopup_location'	=>	array('left' => '100px', 'top' => '100px'),
													'wppopup_colour'	=>	array('back' => 'FFFFFF', 'fore' => '000000'),
													'wppopup_margin'	=>	array('left' => '0px', 'top' => '0px', 'right' => '0px', 'bottom' => '0px'),
													'wppopup_check'		=>	array(),
													'wppopup_ereg'		=>	'',
													'wppopup_count'		=>	3,
													'wppopup_usejs'		=>	'no'
													);
			}

			$wppopup_size = $wppopup->wppopup_settings['wppopup_size'];
			$wppopup_location = $wppopup->wppopup_settings['wppopup_location'];
			$wppopup_colour = $wppopup->wppopup_settings['wppopup_colour'];
			$wppopup_margin = $wppopup->wppopup_settings['wppopup_margin'];

			$wppopup_size = $this->sanitise_array($wppopup_size);
			$wppopup_location = $this->sanitise_array($wppopup_location);
			$wppopup_colour = $this->sanitise_array($wppopup_colour);
			$wppopup_margin = $this->sanitise_array($wppopup_margin);

			$wppopup_check = $wppopup->wppopup_settings['wppopup_check'];
			$wppopup_ereg = $wppopup->wppopup_settings['wppopup_ereg'];
			$wppopup_count = $wppopup->wppopup_settings['wppopup_count'];

			$wppopup_usejs = $wppopup->wppopup_settings['wppopup_usejs'];

			$wppopupstyle = $wppopup->wppopup_settings['wppopup_style'];

			$wppopup_hideforever = $wppopup->wppopup_settings['wppopuphideforeverlink'];

			$wppopup_delay = $wppopup->wppopup_settings['wppopupdelay'];

			$wppopup_onurl = $wppopup->wppopup_settings['onurl'];
			$wppopup_notonurl = $wppopup->wppopup_settings['notonurl'];

			$wppopup_incountry = $wppopup->wppopup_settings['incountry'];
			$wppopup_notincountry = $wppopup->wppopup_settings['notincountry'];

			$wppopup_onurl = $this->sanitise_array($wppopup_onurl);
			$wppopup_notonurl = $this->sanitise_array($wppopup_notonurl);

			?>
			<div class='wrap nosubsub'>
				<div class="icon32" id="icon-themes"><br></div>
				<?php if($id !== false) { ?>
					<h2><?php echo __('Edit WP Popup','wppopup'); ?></h2>
				<?php } else { ?>
					<h2><?php echo __('Add WP Popup','wppopup'); ?></h2>
				<?php } ?>
				<div class='wppopup-liquid-left'>

					<div id='wppopup-left'>
						<form action='?page=<?php echo $page; ?>' name='wppopupedit' method='post'>

							<input type='hidden' name='id' id='id' value='<?php echo $id; ?>' />
							<input type='hidden' name='beingdragged' id='beingdragged' value='' />
							<input type='hidden' name='wppopupcheck[order]' id='in-positive-rules' value='<?php echo esc_attr($wppopup_check['order']); ?>' />

						<div id='edit-wppopup' class='wppopup-holder-wrap'>
							<div class='sidebar-name no-movecursor'>
								<h3><?php echo __('WP Popup Settings','wppopup'); ?></h3>
							</div>
							<div class='wppopup-holder'>

								<div class='wppopup-details'>

								<label for='wppopup_title'><?php _e('wppopup title','wppopup'); ?></label><br/>
								<input name='wppopup_title' id='wppopup_title' style='width: 97%; border: 1px solid; border-color: #DFDFDF;' value='<?php echo stripslashes($wppopup_title); ?>' /><br/><br/>

								<label for='wppopupcontent'><?php _e('wppopup content','wppopup'); ?></label><br/>
								<?php
								$args = array("textarea_name" => "wppopup_content", "textarea_rows" => 5);
								wp_editor( stripslashes($wppopup_content), "wppopup_content", $args );
								/*
								?>
								<textarea name='wppopup_content' id='wppopup_content' style='width: 98%' rows='5' cols='10'><?php echo stripslashes($wppopup_content); ?></textarea>
								<?php
								*/
								?>
								</div>

								<h3><?php _e('Active conditions','wppopup'); ?></h3>
								<p class='description'><?php _e('These are the rules that will determine if a wppopup should show when a visitor arrives at your website ALL rules must be true for the wppopup to show.','wppopup'); ?></p>
								<div id='positive-rules-holder'>
									<?php

										$order = explode(',', $wppopup_check['order']);

										foreach($order as $key) {

											switch($key) {

												case 'supporter':		if( function_exists('is_pro_site') ) $this->admin_main('supporter','Site is not a Pro-site', 'Shows the wppopup if the site is not a Pro-site.', true);
																		break;

												case 'isloggedin':		$this->admin_main('isloggedin','Visitor is logged in', 'Shows the wppopup if the user is logged in to your site.', true);
																		break;
												case 'loggedin':		$this->admin_main('loggedin','Visitor is not logged in', 'Shows the wppopup if the user is <strong>not</strong> logged in to your site.', true);
																		break;
												case 'commented':		$this->admin_main('commented','Visitor has never commented', 'Shows the wppopup if the user has never left a comment.', true);
																		break;
												case 'searchengine':	$this->admin_main('searchengine','Visit via a search engine', 'Shows the wppopup if the user arrived via a search engine.', true);
																		break;
												case 'internal':		$this->admin_main('internal','Visit not via an Internal link', 'Shows the wppopup if the user did not arrive on this page via another page on your site.', true);
																		break;
												case 'referrer':		$this->admin_referer('referrer','Visit via specific referer', 'Shows the wppopup if the user arrived via the following referrer:', $wppopup_ereg);
																		break;
												case 'count':			$this->admin_viewcount('count','wppopup shown less than', 'Shows the wppopup if the user has only seen it less than the following number of times:', $wppopup_count);
																		break;
												case 'onurl':			$this->admin_urllist('onurl','On specific URL', 'Shows the wppopup if the user is on a certain URL (enter one URL per line)', $wppopup_onurl);
																		break;
												case 'notonurl':		$this->admin_urllist('notonurl','Not on specific URL', 'Shows the wppopup if the user is not on a certain URL (enter one URL per line)', $wppopup_notonurl);
																		break;
												case 'incountry':		$this->admin_countrylist('incountry','In a specific Country', 'Shows the wppopup if the user is in a certain country.', $wppopup_incountry);
																		break;
												case 'notincountry':	$this->admin_countrylist('notincountry','Not in a specific Country', 'Shows the wppopup if the user is not in a certain country.', $wppopup_notincountry);
																		break;

												default:				do_action('wppopup_active_rule_' . $key);
																		do_action('wppopup_active_rule', $key);
																		break;

											}

										}


									?>
								</div>
								<div id='positive-rules' class='droppable-rules wppopups-sortable'>
									<?php _e('Drop here','membership'); ?>
								</div>

								<h3><?php _e('Appearance settings','wppopup'); ?></h3>
								<table class='form-table' style=''>
									<tr>
										<th valign='top' scope='row' style='width: 25%;'><strong><?php _e('WP Popup Size','wppopup'); ?></strong></th>
										<td valign='top'>
											<?php _e('Width:','wppopup'); ?>&nbsp;
											<input type='text' name='wppopupwidth' id='wppopupwidth' style='width: 5em;' value='<?php echo $wppopup_size['width']; ?>' />&nbsp;
											<?php _e('Height:','wppopup'); ?>&nbsp;
											<input type='text' name='wppopupheight' id='wppopupheight' style='width: 5em;' value='<?php echo $wppopup_size['height']; ?>' />
										</td>
									</tr>

									<tr>
										<th valign='top' scope='row' style='width: 25%;'><strong><?php _e('WP Popup Position','wppopup'); ?></strong></th>
										<td valign='top'>
											<?php _e('Left:','wppopup'); ?>&nbsp;
											<input type='text' name='wppopupleft' id='wppopupleft' style='width: 5em;' value='<?php echo $wppopup_location['left']; ?>' />&nbsp;
											<?php _e('Top:','wppopup'); ?>&nbsp;
											<input type='text' name='wppopuptop' id='wppopuptop' style='width: 5em;' value='<?php echo $wppopup_location['top']; ?>' />
										</td>
									</tr>

									<tr>
										<th valign='top' scope='row' style='width: 25%;'><strong><?php _e('WP Popup Margins','wppopup'); ?></strong></th>
										<td valign='top'>
											<?php _e('Left:','wppopup'); ?>&nbsp;
											<input type='text' name='wppopupmarginleft' style='width: 5em;' value='<?php echo $wppopup_margin['left']; ?>' />&nbsp;
											<?php _e('Right:','wppopup'); ?>&nbsp;
											<input type='text' name='wppopupmarginright' style='width: 5em;' value='<?php echo $wppopup_margin['right']; ?>' /><br/>
											<?php _e('Top:','wppopup'); ?>&nbsp;
											<input type='text' name='wppopupmargintop' style='width: 5em;' value='<?php echo $wppopup_margin['top']; ?>' />&nbsp;
											<?php _e('Bottom:','wppopup'); ?>&nbsp;
											<input type='text' name='wppopupmarginbottom' style='width: 5em;' value='<?php echo $wppopup_margin['bottom']; ?>' />
										</td>
									</tr>

									<tr>
										<th valign='top' scope='row' style='width: 25%;'>&nbsp;</th>
										<td valign='top'>
											<?php _e('or use Javascript to resize and center the wppopup','wppopup'); ?>&nbsp;<input type='checkbox' name='wppopupusejs' id='wppopupusejs' value='yes' <?php if($wppopup_usejs == 'yes') echo "checked='checked'"; ?> />
										</td>
									</tr>

									</table>
									<table class='form-table'>

									<tr>
										<th valign='top' scope='row' style='width: 25%;'><strong><?php _e('Background Color','wppopup'); ?></strong></th>
										<td valign='top'>
											<?php _e('Hex:','wppopup'); ?>&nbsp;#
											<input type='text' name='wppopupbackground' id='wppopupbackground' style='width: 10em;' value='<?php echo $wppopup_colour['back']; ?>' />
										</td>
									</tr>

									<tr>
										<th valign='top' scope='row' style='width: 25%;'><strong><?php _e('Font Color','wppopup'); ?></strong></th>
										<td valign='top'>
											<?php _e('Hex:','wppopup'); ?>&nbsp;#
											<input type='text' name='wppopupforeground' id='wppopupforeground' style='width: 10em;' value='<?php echo $wppopup_colour['fore']; ?>' />
										</td>
									</tr>

								</table>

								<?php
								$availablestyles = apply_filters( 'wppopup_available_styles_directory', array() );

								if(count($availablestyles) > 1) {
									?>
									<h3><?php _e('WP Popup Style','wppopup'); ?></h3>
									<table class='form-table'>

									<tr>
										<th valign='top' scope='row' style='width: 25%;'><strong><?php _e('Use Style','wppopup'); ?></strong></th>
										<td valign='top'>
											<select name='wppopupstyle'>
											<?php
											foreach( (array) $availablestyles as $key => $location ) {
													?>
													<option value='<?php echo $key; ?>' <?php selected($key, $wppopupstyle); ?>><?php echo $key; ?></option>
													<?php
											}
											?>
											</select>

										</td>
									</tr>

									</table>
									<?php
								} else {
									foreach( (array) $availablestyles as $key => $location ) {
										// There's only one - but it's easy to get the key this way :)
										?>
										<input type='hidden' name='wppopupstyle' value='<?php echo $key; ?>' />
										<?php
									}
								}
								?>

								<h3><?php _e('Remove Hide Forever Link','wppopup'); ?></h3>
								<table class='form-table' style=''>
									<tr>
										<th valign='top' scope='row' style='width: 25%;'><strong><?php _e('Remove the "Never see this message again" link','wppopup'); ?></strong></th>
										<td valign='top'>
											<input type='checkbox' name='wppopuphideforeverlink' id='wppopuphideforeverlink' value='yes' <?php if($wppopup_hideforever == 'yes') { echo "checked='checked'"; } ?> />
										</td>
									</tr>
								</table>

								<h3><?php _e('WP Popup appearance delays','wppopup'); ?></h3>
								<table class='form-table' style=''>
									<tr>
										<th valign='top' scope='row' style='width: 25%;'><strong><?php _e('Show WP Popup','wppopup'); ?></strong></th>
										<td valign='top'>
											<select name='wppopupdelay'>
												<option value='immediate' <?php selected('immediate', $wppopup_delay); ?>><?php _e('immediately','wppopup'); ?></option>
												<?php
													for($n=1; $n <= 120; $n++) {
														?>
														<option value='<?php echo $n; ?>' <?php selected($n, $wppopup_delay); ?>><?php echo __('after','wppopup') . ' ' . $n . ' ' . __('seconds', 'wppopup') ; ?></option>
														<?php
													}
												?>
											</select>
										</td>
									</tr>
								</table>

								<div class='buttons'>
										<?php
										wp_original_referer_field(true, 'previous'); wp_nonce_field('update-wppopup');
										?>
										<?php if($id !== false) { ?>
											<input type='submit' value='<?php _e('Update', 'wppopup'); ?>' class='button-primary' />
											<input type='hidden' name='action' value='updated' />
										<?php } else { ?>
											<input type='submit' value='<?php _e('Add', 'wppopup'); ?>' class='button' name='add' />&nbsp;<input type='submit' value='<?php _e('Add and Activate', 'wppopup'); ?>' class='button-primary' name='addandactivate' />
											<input type='hidden' name='action' value='added' />
										<?php } ?>

								</div>

							</div>
						</div>
						</form>
					</div>


					<div id='hiden-actions'>
					<?php
						if(!isset($wppopup_check['supporter']) && function_exists('is_pro_site')) {
							$this->admin_main('supporter','Site is not a Pro-site', 'Shows the wppopup if the site is not a Pro-site.', true);
						}

						if(!isset($wppopup_check['isloggedin'])) {
							$this->admin_main('isloggedin','Visitor is logged in', 'Shows the wppopup if the user is logged in to your site.', true);
						}

						if(!isset($wppopup_check['loggedin'])) {
							$this->admin_main('loggedin','Visitor is not logged in', 'Shows the wppopup if the user is <strong>not</strong> logged in to your site.', true);
						}

						if(!isset($wppopup_check['commented'])) {
							$this->admin_main('commented','Visitor has never commented', 'Shows the wppopup if the user has never left a comment.', true);
						}

						if(!isset($wppopup_check['searchengine'])) {
							$this->admin_main('searchengine','Visit via a search engine', 'Shows the wppopup if the user arrived via a search engine.', true);
						}

						if(!isset($wppopup_check['internal'])) {
							$this->admin_main('internal','Visit not via an Internal link', 'Shows the wppopup if the user did not arrive on this page via another page on your site.', true);
						}

						if(!isset($wppopup_check['referrer'])) {
							$this->admin_referer('referrer','Visit via specific referer', 'Shows the wppopup if the user arrived via the following referrer:', $wppopup_ereg);
						}

						if(!isset($wppopup_check['onurl'])) {
							$this->admin_urllist('onurl','On specific URL', 'Shows the wppopup if the user is on a certain URL (enter one URL per line)', $wppopup_onurl);
						}

						if(!isset($wppopup_check['notonurl'])) {
							$this->admin_urllist('notonurl','Not on specific URL', 'Shows the wppopup if the user is not on a certain URL (enter one URL per line)', $wppopup_notonurl);
						}

						if(!isset($wppopup_check['incountry'])) {
							$this->admin_countrylist('incountry','In a specific Country', 'Shows the wppopup if the user is in a certain country.', $wppopup_incountry);
						}

						if(!isset($wppopup_check['notincountry'])) {
							$this->admin_countrylist('notincountry','Not in a specific Country', 'Shows the wppopup if the user is not in a certain country.', $wppopup_notincountry);
						}

						//$wppopup_count
						if(!isset($wppopup_check['count'])) {
							$this->admin_viewcount('count','wppopup shown less than', 'Shows the wppopup if the user has only seen it less than the following number of times:', $wppopup_count);
						}

						do_action('wppopup_additional_rules_main');

					?>
					</div> <!-- hidden-actions -->

				</div> <!-- wppopup-liquid-left -->

				<div class='wppopup-liquid-right'>
					<div class="wppopup-holder-wrap">

						<div class="sidebar-name no-movecursor">
							<h3><?php _e('Conditions', 'wppopup'); ?></h3>
						</div>
						<div class="section-holder" id="sidebar-rules" style="min-height: 98px;">
							<ul class='wppopups wppopups-draggable'>
								<?php

									if(isset($wppopup_check['supporter']) && function_exists('is_pro_site')) {
										$this->admin_sidebar('supporter','Site is not a Pro-site', 'Shows the wppopup if the site is not a Pro-site.', true);
									} elseif(function_exists('is_pro_site')) {
										$this->admin_sidebar('supporter','Site is not a Pro-site', 'Shows the wppopup if the site is not a Pro-site.', false);
									}

									if(isset($wppopup_check['isloggedin'])) {
										$this->admin_sidebar('isloggedin','Visitor is logged in', 'Shows the wppopup if the user is logged in to your site.', true);
									} else {
										$this->admin_sidebar('isloggedin','Visitor is logged in', 'Shows the wppopup if the user is logged in to your site.', false);
									}

									if(isset($wppopup_check['loggedin'])) {
										$this->admin_sidebar('loggedin','Visitor is not logged in', 'Shows the wppopup if the user is <strong>not</strong> logged in to your site.', true);
									} else {
										$this->admin_sidebar('loggedin','Visitor is not logged in', 'Shows the wppopup if the user is <strong>not</strong> logged in to your site.', false);
									}

									if(isset($wppopup_check['commented'])) {
										$this->admin_sidebar('commented','Visitor has never commented', 'Shows the wppopup if the user has never left a comment.', true);
									} else {
										$this->admin_sidebar('commented','Visitor has never commented', 'Shows the wppopup if the user has never left a comment.', false);
									}

									if(isset($wppopup_check['searchengine'])) {
										$this->admin_sidebar('searchengine','Visit via a search engine', 'Shows the wppopup if the user arrived via a search engine.', true);
									} else {
										$this->admin_sidebar('searchengine','Visit via a search engine', 'Shows the wppopup if the user arrived via a search engine.', false);
									}

									if(isset($wppopup_check['internal'])) {
										$this->admin_sidebar('internal','Visit not via an Internal link', 'Shows the wppopup if the user did not arrive on this page via another page on your site.', true);
									} else {
										$this->admin_sidebar('internal','Visit not via an Internal link', 'Shows the wppopup if the user did not arrive on this page via another page on your site.', false);
									}

									if(isset($wppopup_check['referrer'])) {
										$this->admin_sidebar('referrer','Visit via specific referer', 'Shows the wppopup if the user arrived via a specific referrer.', true);
									} else {
										$this->admin_sidebar('referrer','Visit via specific referer', 'Shows the wppopup if the user arrived via a specific referrer.', false);
									}

									if(isset($wppopup_check['count'])) {
										$this->admin_sidebar('count','wppopup shown less than', 'Shows the wppopup if the user has only seen it less than a specific number of times.', true);
									} else {
										$this->admin_sidebar('count','wppopup shown less than', 'Shows the wppopup if the user has only seen it less than a specific number of times.', false);
									}

									if(isset($wppopup_check['onurl'])) {
										$this->admin_sidebar('onurl','On specific URL', 'Shows the wppopup if the user is on a certain URL.', true);
									} else {
										$this->admin_sidebar('onurl','On specific URL', 'Shows the wppopup if the user is on a certain URL.', false);
									}

									if(isset($wppopup_check['notonurl'])) {
										$this->admin_sidebar('notonurl','Not on specific URL', 'Shows the wppopup if the user is not on a certain URL.', true);
									} else {
										$this->admin_sidebar('notonurl','Not on specific URL', 'Shows the wppopup if the user is not on a certain URL.', false);
									}

									if(isset($wppopup_check['incountry'])) {
										$this->admin_sidebar('incountry','In a specific Country', 'Shows the wppopup if the user is in a certain country.', true);
									} else {
										$this->admin_sidebar('incountry','In a specific Country', 'Shows the wppopup if the user is in a certain country.', false);
									}

									if(isset($wppopup_check['notincountry'])) {
										$this->admin_sidebar('notincountry','Not in a specific Country', 'Shows the wppopup if the user is not in a certain country.', true);
									} else {
										$this->admin_sidebar('notincountry','Not in a specific Country', 'Shows the wppopup if the user is not in a certain country.', false);
									}

									do_action('wppopup_additional_rules_sidebar');
								?>
							</ul>
						</div>

					</div> <!-- wppopup-holder-wrap -->

				</div> <!-- wppopup-liquid-left -->

			</div> <!-- wrap -->

			<?php
		}

		function admin_sidebar($id, $title, $message, $data = false) {
			?>
			<li class='wppopup-draggable' id='<?php echo $id; ?>' <?php if($data === true) echo "style='display:none;'"; ?>>

				<div class='action action-draggable'>
					<div class='action-top closed'>
					<a href="#available-actions" class="action-button hide-if-no-js"></a>
					<?php _e($title,'wppopup'); ?>
					</div>
					<div class='action-body closed'>
						<?php if(!empty($message)) { ?>
							<p>
								<?php _e($message, 'wppopup'); ?>
							</p>
						<?php } ?>
						<p>
							<a href='#addtowppopup' class='action-to-wppopup' title='<?php _e('Add this rule to the wppopup.','wppopup'); ?>'><?php _e('Add this rule to the wppopup.','wppopup'); ?></a>
						</p>
					</div>
				</div>

			</li>
			<?php
		}

		function admin_main($id, $title, $message, $data = false) {
			if(!$data) $data = array();
			?>
			<div class='wppopup-operation' id='main-<?php echo $id; ?>'>
				<h2 class='sidebar-name'><?php _e($title, 'wppopup');?><span><a href='#remove' class='removelink' id='remove-<?php echo $id; ?>' title='<?php _e("Remove $title tag from this rules area.",'wppopup'); ?>'><?php _e('Remove','wppopup'); ?></a></span></h2>
				<div class='inner-operation'>
					<p><?php _e($message, 'wppopup'); ?></p>
					<input type='hidden' name='wppopupcheck[<?php echo $id; ?>]' value='yes' />
				</div>
			</div>
			<?php
		}

		function admin_referer($id, $title, $message, $data = false) {
			if(!$data) $data = ''
			?>
			<div class='wppopup-operation' id='main-<?php echo $id; ?>'>
				<h2 class='sidebar-name'><?php _e($title, 'wppopup');?><span><a href='#remove' class='removelink' id='remove-<?php echo $id; ?>' title='<?php _e("Remove $title tag from this rules area.",'wppopup'); ?>'><?php _e('Remove','wppopup'); ?></a></span></h2>
				<div class='inner-operation'>
					<p><?php _e($message, 'wppopup'); ?></p>
					<input type='text' name='wppopupereg' id='wppopupereg' style='width: 10em;' value='<?php echo esc_html($data); ?>' />
					<input type='hidden' name='wppopupcheck[<?php echo $id; ?>]' value='yes' />
				</div>
			</div>
			<?php
		}

		function admin_viewcount($id, $title, $message, $data = false) {
			if(!$data) $data = '';
			?>
			<div class='wppopup-operation' id='main-<?php echo $id; ?>'>
				<h2 class='sidebar-name'><?php _e($title, 'wppopup');?><span><a href='#remove' class='removelink' id='remove-<?php echo $id; ?>' title='<?php _e("Remove $title tag from this rules area.",'wppopup'); ?>'><?php _e('Remove','wppopup'); ?></a></span></h2>
				<div class='inner-operation'>
					<p><?php _e($message, 'wppopup'); ?></p>
					<input type='text' name='wppopupcount' id='wppopupcount' style='width: 5em;' value='<?php echo esc_html($data); ?>' />&nbsp;
					<?php _e('times','wppopup'); ?>
					<input type='hidden' name='wppopupcheck[<?php echo $id; ?>]' value='yes' />
				</div>
			</div>
			<?php
		}

		function admin_urllist($id, $title, $message, $data = false) {
			if(!$data) $data = array();

			$data = implode("\n", $data);

			?>
			<div class='wppopup-operation' id='main-<?php echo $id; ?>'>
				<h2 class='sidebar-name'><?php _e($title, 'wppopup');?><span><a href='#remove' class='removelink' id='remove-<?php echo $id; ?>' title='<?php _e("Remove $title tag from this rules area.",'wppopup'); ?>'><?php _e('Remove','wppopup'); ?></a></span></h2>
				<div class='inner-operation'>
					<p><?php _e($message, 'wppopup'); ?></p>
					<textarea name='wppopup<?php echo $id; ?>' id='wppopup<?php echo $id; ?>' style=''><?php echo esc_html($data); ?></textarea>
					<input type='hidden' name='wppopupcheck[<?php echo $id; ?>]' value='yes' />
				</div>
			</div>
			<?php
		}

		function admin_countrylist($id, $title, $message, $data = false) {
			if(!$data) $data = '';


			?>
			<div class='wppopup-operation' id='main-<?php echo $id; ?>'>
				<h2 class='sidebar-name'><?php _e($title, 'wppopup');?><span><a href='#remove' class='removelink' id='remove-<?php echo $id; ?>' title='<?php _e("Remove $title tag from this rules area.",'wppopup'); ?>'><?php _e('Remove','wppopup'); ?></a></span></h2>
				<div class='inner-operation'>
					<p><?php _e($message, 'wppopup'); ?></p>
					<?php $countries = P_CountryList(); ?>
					<select name='wppopup<?php echo $id; ?>' id='wppopup<?php echo $id; ?>' style=''>
						<option value='' <?php selected('', $data); ?>><?php _e('Select a country from the list below' , 'wppopup') ?></option>
						<?php
							foreach( (array) $countries as $code => $country ) {
								?>
								<option value='<?php echo $code; ?>' <?php selected($code, $data); ?>><?php echo $country; ?></option>
								<?php
							}
						?>
					</select>
					<input type='hidden' name='wppopupcheck[<?php echo $id; ?>]' value='yes' />
				</div>
			</div>
			<?php
		}

		function handle_admin_panelold() {

			global $allowedposttags;

			if(is_multisite() && defined('PO_GLOBAL')) {
				$updateoption = 'update_site_option';
				$getoption = 'get_site_option';
			} else {
				$updateoption = 'update_option';
				$getoption = 'get_option';
			}

			if(isset($_POST['action']) && addslashes($_POST['action']) == 'updatewppopup') {

				//print_r($_POST);

				if(isset($_POST['wppopupcontent'])) {
					if(defined('PO_USEKSES')) {
						$updateoption('wppopup_content', wp_kses($_POST['wppopupcontent'], $allowedposttags));
					} else {
						$updateoption('wppopup_content', $_POST['wppopupcontent']);
					}

				}

				if(isset($_POST['wppopupwidth']) || isset($_POST['wppopupheight'])) {

					$width = $_POST['wppopupwidth'];
					$height = $_POST['wppopupheight'];

					if($width == '') $width = '500px';
					if($height == '') $height = '200px';

					$updateoption('wppopup_size', array("width" => $width, "height" => $height));
				}

				if(isset($_POST['wppopupleft']) || isset($_POST['wppopuptop'])) {

					$left = $_POST['wppopupleft'];
					$top = $_POST['wppopuptop'];

					if($left == '') $left = '100px';
					if($top == '') $top = '100px';

					$updateoption('wppopup_location', array("left" => $left, "top" => $top));
				}

				if(isset($_POST['wppopupmargintop']) || isset($_POST['wppopupmarginleft']) || isset($_POST['wppopupmarginright']) || isset($_POST['wppopupmarginbottom'])) {

					$mleft = $_POST['wppopupmarginleft'];
					$mtop = $_POST['wppopupmargintop'];
					$mright = $_POST['wppopupmarginright'];
					$mbottom = $_POST['wppopupmarginbottom'];

					if($mleft == '') $mleft = '0px';
					if($mtop == '') $mtop = '0px';
					if($mright == '') $mright = '0px';
					if($mbottom == '') $mbottom = '0px';

					$updateoption('wppopup_margin', array('left' => $mleft, 'top' => $mtop, 'right' => $mright, 'bottom' => $mbottom));

				}

				if(isset($_POST['wppopupbackground']) || isset($_POST['wppopupforeground'])) {

					$back = $_POST['wppopupbackground'];
					$fore = $_POST['wppopupforeground'];

					if($back == '') $back = 'FFFFFF';
					if($fore == '') $fore = '000000';

					$updateoption('wppopup_colour', array("back" => $back, "fore" => $fore));
				}

				if(isset($_POST['wppopupcheck'])) {

					$updateoption('wppopup_check', $_POST['wppopupcheck']);

					if(isset($_POST['wppopupereg'])) {
						$updateoption('wppopup_ereg', $_POST['wppopupereg']);
					}

					if(isset($_POST['wppopupcount'])) {
						$updateoption('wppopup_count', intval($_POST['wppopupcount']) );
					}

				}

				if(isset($_POST['wppopupusejs'])) {
					$updateoption('wppopup_usejs', 'yes' );
				} else {
					$updateoption('wppopup_usejs', 'no' );
				}

				echo '<div id="message" class="updated fade"><p>' . __('Your settings have been saved.', 'wppopup') . '</p></div>';

			}

			$wppopup_content = stripslashes($getoption('wppopup_content', ''));
			$wppopup_size = $getoption('wppopup_size', array('width' => '500px', 'height' => '200px'));
			$wppopup_location = $getoption('wppopup_location', array('left' => '100px', 'top' => '100px'));
			$wppopup_colour = $getoption('wppopup_colour', array('back' => 'FFFFFF', 'fore' => '000000'));
			$wppopup_margin = $getoption('wppopup_margin', array('left' => '0px', 'top' => '0px', 'right' => '0px', 'bottom' => '0px'));

			$wppopup_size = $this->sanitise_array($wppopup_size);
			$wppopup_location = $this->sanitise_array($wppopup_location);
			$wppopup_colour = $this->sanitise_array($wppopup_colour);
			$wppopup_margin = $this->sanitise_array($wppopup_margin);

			$wppopup_check = $getoption('wppopup_check', array());
			$wppopup_ereg = $getoption('wppopup_ereg', '');
			$wppopup_count = $getoption('wppopup_count', '3');

			$wppopup_usejs = $getoption('wppopup_usejs', 'no' );

			?>

			<div class='wrap'>

				<form action='' method='post'>
					<input type='hidden' name='action' value='updatewppopup' />
					<?php wp_nonce_field('updatewppopup'); ?>

				<h2><?php _e('WP Popup content settings','wppopup'); ?></h2>
				<p><?php _e('Use the settings below to modify the content of your WP Popup and the rules that will determine when, or if, it will be displayed.','wppopup'); ?></p>

				<h3><?php _e('WP Popup content','wppopup'); ?></h3>
				<p><?php _e('Enter the content for your WP Popup in the text area below. HTML is allowed.','wppopup'); ?></p>
				<textarea name='wppopupcontent' id='wppopupcontent' style='width: 90%' rows='10' cols='10'><?php echo stripslashes($wppopup_content); ?></textarea>

				<p class="submit">
				<input class="button" type="submit" name="go" value="<?php _e('Update content', 'wppopup'); ?>" />
				</p>

				<h3><?php _e('WP Popup display settings','wppopup'); ?></h3>
				<p><?php _e('Use the options below to determine the look, and display settings for the WP Popup.','wppopup'); ?></p>

				<table class='form-table'>

					<tr>
						<td valign='top' width='49%'>
							<h3><?php _e('Appearance Settings','wppopup'); ?></h3>

							<table class='form-table' style='border: 1px solid #ccc; padding-top: 10px; padding-bottom: 10px; margin-bottom: 10px;'>
								<tr>
									<th valign='top' scope='row' style='width: 25%;'><?php _e('WP Popup Size','wppopup'); ?></th>
									<td valign='top'>
										<?php _e('Width:','wppopup'); ?>&nbsp;
										<input type='text' name='wppopupwidth' id='wppopupwidth' style='width: 5em;' value='<?php echo $wppopup_size['width']; ?>' />&nbsp;
										<?php _e('Height:','wppopup'); ?>&nbsp;
										<input type='text' name='wppopupheight' id='wppopupheight' style='width: 5em;' value='<?php echo $wppopup_size['height']; ?>' />
									</td>
								</tr>

								<tr>
									<th valign='top' scope='row' style='width: 25%;'><?php _e('WP Popup Position','wppopup'); ?></th>
									<td valign='top'>
										<?php _e('Left:','wppopup'); ?>&nbsp;
										<input type='text' name='wppopupleft' id='wppopupleft' style='width: 5em;' value='<?php echo $wppopup_location['left']; ?>' />&nbsp;
										<?php _e('Top:','wppopup'); ?>&nbsp;
										<input type='text' name='wppopuptop' id='wppopuptop' style='width: 5em;' value='<?php echo $wppopup_location['top']; ?>' />
									</td>
								</tr>

								<tr>
									<th valign='top' scope='row' style='width: 25%;'><?php _e('WP Popup Margins','wppopup'); ?></th>
									<td valign='top'>
										<?php _e('Left:','wppopup'); ?>&nbsp;
										<input type='text' name='wppopupmarginleft' style='width: 5em;' value='<?php echo $wppopup_margin['left']; ?>' />&nbsp;
										<?php _e('Right:','wppopup'); ?>&nbsp;
										<input type='text' name='wppopupmarginright' style='width: 5em;' value='<?php echo $wppopup_margin['right']; ?>' /><br/>
										<?php _e('Top:','wppopup'); ?>&nbsp;
										<input type='text' name='wppopupmargintop' style='width: 5em;' value='<?php echo $wppopup_margin['top']; ?>' />&nbsp;
										<?php _e('Bottom:','wppopup'); ?>&nbsp;
										<input type='text' name='wppopupmarginbottom' style='width: 5em;' value='<?php echo $wppopup_margin['bottom']; ?>' />
									</td>
								</tr>

								<tr>
									<th valign='top' scope='row' style='width: 25%;'>&nbsp;</th>
									<td valign='top'>
										<?php _e('or just override the above with JS','wppopup'); ?>&nbsp;<input type='checkbox' name='wppopupusejs' id='wppopupusejs' value='yes' <?php if($wppopup_usejs == 'yes') echo "checked='checked'"; ?> />
									</td>
								</tr>

								</table>
								<table class='form-table'>



								<tr>
									<th valign='top' scope='row' style='width: 25%;'><?php _e('Background Colour','wppopup'); ?></th>
									<td valign='top'>
										<?php _e('Hex:','wppopup'); ?>&nbsp;#
										<input type='text' name='wppopupbackground' id='wppopupbackground' style='width: 10em;' value='<?php echo $wppopup_colour['back']; ?>' />
									</td>
								</tr>

								<tr>
									<th valign='top' scope='row' style='width: 25%;'><?php _e('Font Colour','wppopup'); ?></th>
									<td valign='top'>
										<?php _e('Hex:','wppopup'); ?>&nbsp;#
										<input type='text' name='wppopupforeground' id='wppopupforeground' style='width: 10em;' value='<?php echo $wppopup_colour['fore']; ?>' />
									</td>
								</tr>

							</table>

						</td>

						<td valign='top' width='49%'>
							<h3><?php _e('Display Rules','wppopup'); ?></h3>

								<p><?php _e('Show the WP Popup if <strong>one</strong> of the following checked rules is true.','wppopup'); ?></p>
								<input type='hidden' name='wppopupcheck[none]' value='none' />
								<table class='form-table'>
									<?php
										if(function_exists('is_supporter')) {
									?>
									<tr>
										<td valign='middle' style='width: 5%;'>
											<input type='checkbox' name='wppopupcheck[notsupporter]' <?php if(isset($wppopup_check['notsupporter'])) echo "checked='checked'"; ?> />
										</td>
										<th valign='bottom' scope='row'><?php _e('Visitor is not a supporter.','wppopup'); ?></th>
									</tr>
									<?php
										}
									?>
									<tr>
										<td valign='middle' style='width: 5%;'>
											<input type='checkbox' name='wppopupcheck[isloggedin]' <?php if(isset($wppopup_check['isloggedin'])) echo "checked='checked'"; ?> />
										</td>
										<th valign='bottom' scope='row'><?php _e('Visitor is logged in.','wppopup'); ?></th>
									</tr>
									<tr>
										<td valign='middle' style='width: 5%;'>
											<input type='checkbox' name='wppopupcheck[loggedin]' <?php if(isset($wppopup_check['loggedin'])) echo "checked='checked'"; ?> />
										</td>
										<th valign='bottom' scope='row'><?php _e('Visitor is not logged in.','wppopup'); ?></th>
									</tr>
									<tr>
										<td valign='middle' style='width: 5%;'>
											<input type='checkbox' name='wppopupcheck[commented]' <?php if(isset($wppopup_check['commented'])) echo "checked='checked'"; ?> />
										</td>
										<th valign='bottom' scope='row'><?php _e('Visitor has never commented here before.','wppopup'); ?></th>
									</tr>
									<tr>
										<td valign='middle' style='width: 5%;'>
											<input type='checkbox' name='wppopupcheck[searchengine]' <?php if(isset($wppopup_check['searchengine'])) echo "checked='checked'"; ?> />
										</td>
										<th valign='bottom' scope='row'><?php _e('Visitor came from a search engine.','wppopup'); ?></th>
									</tr>
									<tr>
										<td valign='middle' style='width: 5%;'>
											<input type='checkbox' name='wppopupcheck[internal]' <?php if(isset($wppopup_check['internal'])) echo "checked='checked'"; ?> />
										</td>
										<th valign='bottom' scope='row'><?php _e('Visitor did not come from an internal page.','wppopup'); ?></th>
									</tr>
									<tr>
										<td valign='middle' style='width: 5%;'>
											<input type='checkbox' name='wppopupcheck[referrer]' <?php if(isset($wppopup_check['referrer'])) echo "checked='checked'"; ?> />
										</td>
										<th valign='bottom' scope='row'><?php _e('Visitor referrer matches','wppopup'); ?>&nbsp;
										<input type='text' name='wppopupereg' id='wppopupereg' style='width: 10em;' value='<?php echo htmlentities($wppopup_ereg,ENT_QUOTES, 'UTF-8'); ?>' />
										</th>
									</tr>

									</table>

									<p><?php _e('And the visitor has seen the WP Popup less than','wppopup'); ?>&nbsp;
									<input type='text' name='wppopupcount' id='wppopupcount' style='width: 2em;' value='<?php echo htmlentities($wppopup_count,ENT_QUOTES, 'UTF-8'); ?>' />&nbsp;
									<?php _e('times','wppopup'); ?></p>

						</td>
					</tr>

				</table>

				<p class="submit">
				<input class="button" type="submit" name="goagain" value="<?php _e('Update settings', 'wppopup'); ?>" />
				</p>

				</form>

			</div>

			<?php
		}

		function handle_addons_panel_updates() {
			global $action, $page;

			wp_reset_vars( array('action', 'page') );

			if(isset($_GET['doaction']) || isset($_GET['doaction2'])) {
				if(addslashes($_GET['action']) == 'toggle' || addslashes($_GET['action2']) == 'toggle') {
					$action = 'bulk-toggle';
				}
			}

			$active = get_option('wppopup_activated_addons', array());

			switch(addslashes($action)) {

				case 'deactivate':	$key = addslashes($_GET['addon']);
									if(!empty($key)) {
										check_admin_referer('toggle-addon-' . $key);

										$found = array_search($key, $active);
										if($found !== false) {
											unset($active[$found]);
											update_option('wppopup_activated_addons', array_unique($active));
											wp_safe_redirect( add_query_arg( 'msg', 5, wp_get_referer() ) );
										} else {
											wp_safe_redirect( add_query_arg( 'msg', 6, wp_get_referer() ) );
										}
									}
									break;

				case 'activate':	$key = addslashes($_GET['addon']);
									if(!empty($key)) {
										check_admin_referer('toggle-addon-' . $key);

										if(!in_array($key, $active)) {
											$active[] = $key;
											update_option('wppopup_activated_addons', array_unique($active));
											wp_safe_redirect( add_query_arg( 'msg', 3, wp_get_referer() ) );
										} else {
											wp_safe_redirect( add_query_arg( 'msg', 4, wp_get_referer() ) );
										}
									}
									break;

				case 'bulk-toggle':
									check_admin_referer('bulk-addons');
									foreach($_GET['addoncheck'] AS $key) {
										$found = array_search($key, $active);
										if($found !== false) {
											unset($active[$found]);
										} else {
											$active[] = $key;
										}
									}
									update_option('wppopup_activated_addons', array_unique($active));
									wp_safe_redirect( add_query_arg( 'msg', 7, wp_get_referer() ) );
									break;

			}
		}

		function handle_addons_panel() {
			global $action, $page;

			wp_reset_vars( array('action', 'page') );

			$messages = array();
			$messages[1] = __('Add-on updated.','wppopup');
			$messages[2] = __('Add-on not updated.','wppopup');

			$messages[3] = __('Add-on activated.','wppopup');
			$messages[4] = __('Add-on not activated.','wppopup');

			$messages[5] = __('Add-on deactivated.','wppopup');
			$messages[6] = __('Add-on not deactivated.','wppopup');

			$messages[7] = __('Add-on activation toggled.','wppopup');

			?>
			<div class='wrap'>
				<div class="icon32" id="icon-plugins"><br></div>
				<h2><?php _e('Edit Add-ons','wppopup'); ?></h2>

				<?php
				if ( isset($_GET['msg']) ) {
					echo '<div id="message" class="updated fade"><p>' . $messages[(int) $_GET['msg']] . '</p></div>';
					$_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
				}

				?>

				<form method="get" action="?page=<?php echo esc_attr($page); ?>" id="posts-filter">

				<input type='hidden' name='page' value='<?php echo esc_attr($page); ?>' />

				<div class="tablenav">

				<div class="alignleft actions">
				<select name="action">
				<option selected="selected" value=""><?php _e('Bulk Actions', 'wppopup'); ?></option>
				<option value="toggle"><?php _e('Toggle activation', 'wppopup'); ?></option>
				</select>
				<input type="submit" class="button-secondary action" id="doaction" name="doaction" value="<?php _e('Apply', 'wppopup'); ?>">

				</div>

				<div class="alignright actions"></div>

				<br class="clear">
				</div>

				<div class="clear"></div>

				<?php
					wp_original_referer_field(true, 'previous'); wp_nonce_field('bulk-addons');

					$columns = array(	"name"		=>	__('Add-on Name', 'wppopup'),
										"file" 		=> 	__('Add-on File','wppopup'),
										"active"	=>	__('Active','wppopup')
									);

					$columns = apply_filters('wppopup_addoncolumns', $columns);

					$addons = get_wppopup_addons();

					$active = get_option('wppopup_activated_addons', array());

				?>

				<table cellspacing="0" class="widefat fixed">
					<thead>
					<tr>
					<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
					<?php
						foreach($columns as $key => $col) {
							?>
							<th style="" class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>" scope="col"><?php echo $col; ?></th>
							<?php
						}
					?>
					</tr>
					</thead>

					<tfoot>
					<tr>
					<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
					<?php
						reset($columns);
						foreach($columns as $key => $col) {
							?>
							<th style="" class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>" scope="col"><?php echo $col; ?></th>
							<?php
						}
					?>
					</tr>
					</tfoot>

					<tbody>
						<?php
						if($addons) {
							foreach($addons as $key => $addon) {
								$default_headers = array(
									                'Name' => 'Addon Name',
													'Author' => 'Author',
													'Description'	=>	'Description',
													'AuthorURI' => 'Author URI'
									        );

								$addon_data = get_file_data( wppopup_dir('wppopupincludes/addons/' . $addon), $default_headers, 'plugin' );

								if(empty($addon_data['Name'])) {
									continue;
								}

								?>
								<tr valign="middle" class="alternate" id="addon-<?php echo $addon; ?>">
									<th class="check-column" scope="row"><input type="checkbox" value="<?php echo esc_attr($addon); ?>" name="addoncheck[]"></th>
									<td class="column-name">
										<strong><?php echo esc_html($addon_data['Name']) . "</strong>" . __(' by ', 'wppopup') . "<a href='" . esc_attr($addon_data['AuthorURI']) . "'>" . esc_html($addon_data['Author']) . "</a>"; ?>
										<?php if(!empty($addon_data['Description'])) {
											?><br/><?php echo esc_html($addon_data['Description']);
											}

											$actions = array();

											if(in_array($addon, $active)) {
												$actions['toggle'] = "<span class='edit activate'><a href='" . wp_nonce_url("?page=" . $page. "&amp;action=deactivate&amp;addon=" . $addon . "", 'toggle-addon-' . $addon) . "'>" . __('Deactivate', 'wppopup') . "</a></span>";
											} else {
												$actions['toggle'] = "<span class='edit deactivate'><a href='" . wp_nonce_url("?page=" . $page. "&amp;action=activate&amp;addon=" . $addon . "", 'toggle-addon-' . $addon) . "'>" . __('Activate', 'wppopup') . "</a></span>";
											}
										?>
										<br><div class="row-actions"><?php echo implode(" | ", $actions); ?></div>
										</td>

									<td class="column-name">
										<?php echo esc_html($addon); ?>
										</td>
									<td class="column-active">
										<?php
											if(in_array($addon, $active)) {
												echo "<strong>" . __('Active', 'wppopup') . "</strong>";
											} else {
												echo __('Inactive', 'wppopup');
											}
										?>
									</td>
							    </tr>
								<?php
							}
						} else {
							$columncount = count($columns) + 1;
							?>
							<tr valign="middle" class="alternate" >
								<td colspan="<?php echo $columncount; ?>" scope="row"><?php _e('No Add-ons where found for this install.','wppopup'); ?></td>
						    </tr>
							<?php
						}
						?>

					</tbody>
				</table>


				<div class="tablenav">

				<div class="alignleft actions">
				<select name="action2">
					<option selected="selected" value=""><?php _e('Bulk Actions', 'wppopup'); ?></option>
					<option value="toggle"><?php _e('Toggle activation', 'wppopup'); ?></option>
				</select>
				<input type="submit" class="button-secondary action" id="doaction2" name="doaction2" value="<?php _e('Apply', 'wppopup'); ?>">
				</div>
				<div class="alignright actions"></div>
				<br class="clear">
				</div>

				</form>

			</div> <!-- wrap -->
			<?php
		}

	}

}

?>