<?php
if(!class_exists('wppopupajax')) {

	class wppopupajax {

		var $mylocation = '';
		var $build = 3;
		var $db;

		var $tables = array( 'wppopup' );
		var $wppopup;

		var $activewppopup = false;

		function __construct() {

			global $wpdb;

			$this->db =& $wpdb;

			foreach($this->tables as $table) {
				$this->$table = wppopup_db_prefix($this->db, $table);
			}

			//add_action('init', array(&$this, 'selective_message_display'), 1);

			add_action( 'plugins_loaded', array(&$this, 'load_textdomain'));

			$directories = explode(DIRECTORY_SEPARATOR,dirname(__FILE__));
			$this->mylocation = $directories[count($directories)-1];

			// Adding in Ajax calls - need to be in the admin area as it uses admin_url :/
			add_action('wp_ajax_po_wppopup', array(&$this, 'selective_message_display') );
			add_action('wp_ajax_nopriv_po_wppopup', array(&$this, 'selective_message_display') );

		}

		function wppopupajax() {
			$this->__construct();
		}

		function load_textdomain() {

			$locale = apply_filters( 'wppopup_locale', get_locale() );
			$mofile = wppopup_dir( "wppopupincludes/languages/wppopup-$locale.mo" );

			if ( file_exists( $mofile ) )
				load_textdomain( 'wppopup', $mofile );

		}

		function get_active_wppopups() {
			$sql = $this->db->prepare( "SELECT * FROM {$this->wppopup} WHERE wppopup_active = 1 ORDER BY wppopup_order ASC" );

			return $this->db->get_results( $sql );
		}

		function selective_message_display() {

			die('hello');

			if(function_exists('get_site_option') && defined('PO_GLOBAL') && PO_GLOBAL == true) {
				$updateoption = 'update_site_option';
				$getoption = 'get_site_option';
			} else {
				$updateoption = 'update_option';
				$getoption = 'get_option';
			}

			$wppopups = $this->get_active_wppopups();

			if(!empty($wppopups)) {

				foreach( (array) $wppopups as $wppopup ) {

					// We have an active wppopup so extract the information and test it
					$wppopup_title = stripslashes($wppopup->wppopup_title);
					$wppopup_content = stripslashes($wppopup->wppopup_content);
					$wppopup->wppopup_settings = unserialize($wppopup->wppopup_settings);

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

					$wppopup_delay = $wppopup->wppopup_settings['wppopupdelay'];

					$wppopup_onurl = $wppopup->wppopup_settings['onurl'];
					$wppopup_notonurl = $wppopup->wppopup_settings['notonurl'];

					$wppopup_onurl = $this->sanitise_array($wppopup_onurl);
					$wppopup_notonurl = $this->sanitise_array($wppopup_notonurl);

					$show = true;

					if(!empty($wppopup_check)) {

						$order = explode(',', $wppopup_check['order']);

						foreach($order as $key) {

							switch ($key) {

								case "supporter":
													if(function_exists('is_pro_site') && is_pro_site()) {
														$show = false;
													}
													break;

								case "loggedin":	if($this->is_loggedin()) {
														$show = false;
													}
													break;

								case "isloggedin":	if(!$this->is_loggedin()) {
														$show = false;
													}
													break;

								case "commented":	if($this->has_commented()) {
														$show = false;
													}
													break;

								case "searchengine":
													if(!$this->is_fromsearchengine()) {
														$show = false;
													}
													break;

								case "internal":	$internal = str_replace('http://','',get_option('home'));
													if($this->referrer_matches(addcslashes($internal,"/"))) {
														$show = false;
													}
													break;

								case "referrer":	$match = $wppopup_ereg;
													if(!$this->referrer_matches(addcslashes($match,"/"))) {
														$show = false;
													}
													break;

								case "count":		if($this->has_reached_limit($wppopup_count)) {
														$show = false;
													}
													break;

								case 'onurl':		if(!$this->onurl( $wppopup_onurl )) {
														$show = false;
													}
													break;

								case 'notonurl':	if($this->onurl( $wppopup_notonurl )) {
														$show = false;
													}
													break;

								default:			if(has_filter('wppopup_process_rule_' . $key)) {
														if(!apply_filters( 'wppopup_process_rule_' . $key, false )) {
															$show = false;
														}
													}
													break;

							}
						}
					}

					if($show == true) {

						if($this->clear_forever()) {
							$show = false;
						}

					}

					if($show == true) {

						// Store the active wppopup so we know what we are using in the footer.
						$this->activewppopup = $wppopup;

						wp_enqueue_script('jquery');

						$wppopup_messagebox = 'a' . md5(date('d')) . '-po';
						// Show the advert
						wp_enqueue_script('wppopupjs', wppopup_url('wppopupincludes/js/wppopup.js'), array('jquery'), $this->build);
						if(!empty($wppopup_delay) && $wppopup_delay != 'immediate') {
							// Set the delay
							wp_localize_script('wppopupjs', 'wppopup', array(	'messagebox'		=>	'#' . $wppopup_messagebox,
																				'messagedelay'		=>	$wppopup_delay * 1000
																				));
						} else {
							wp_localize_script('wppopupjs', 'wppopup', array(	'messagebox'		=>	'#' . $wppopup_messagebox,
																				'messagedelay'		=>	0
																				));
						}

						if($wppopup_usejs == 'yes') {
							wp_enqueue_script('wppopupoverridejs', wppopup_url('wppopupincludes/js/wppopupsizing.js'), array('jquery'), $this->build);
						}

						add_action('wp_head', array(&$this, 'page_header'));
						add_action('wp_footer', array(&$this, 'page_footer'));

						// Add the cookie
						if ( isset($_COOKIE['wppopup_view_'.COOKIEHASH]) ) {
							$count = intval($_COOKIE['wppopup_view_'.COOKIEHASH]);
							if(!is_numeric($count)) $count = 0;
							$count++;
						} else {
							$count = 1;
						}
						if(!headers_sent()) setcookie('wppopup_view_'.COOKIEHASH, $count , time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);

						break;
					}


				}

			}

		}

		function sanitise_array($arrayin) {

			foreach($arrayin as $key => $value) {
				$arrayin[$key] = htmlentities(stripslashes($value) ,ENT_QUOTES, 'UTF-8');
			}

			return $arrayin;
		}

		function page_header() {

			if(!$this->activewppopup) {
				return;
			}

			$wppopup = $this->activewppopup;

			$wppopup_title = stripslashes($wppopup->wppopup_title);
			$wppopup_content = stripslashes($wppopup->wppopup_content);

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

			$wppopup_messagebox = 'a' . md5(date('d')) . '-po';

			$availablestyles = apply_filters( 'wppopup_available_styles_directory', array() );
			$availablestylesurl = apply_filters( 'wppopup_available_styles_url', array() );

			if( in_array($wppopupstyle, array_keys($availablestyles)) ) {
				// Add the styles
				if(file_exists(trailingslashit($availablestyles[$wppopupstyle]) . 'style.css')) {
					ob_start();
					include_once( trailingslashit($availablestyles[$wppopupstyle]) . 'style.css' );
					$content = ob_get_contents();
					ob_end_clean();

					echo "<style type='text/css'>\n";
					$content = str_replace('#messagebox', '#' . $wppopup_messagebox, $content);
					$content = str_replace('%styleurl%', trailingslashit($availablestylesurl[$wppopupstyle]), $content);
					echo $content;
					echo "</style>\n";
				}

			}

		}

		function page_footer() {

			if(!$this->activewppopup) {
				return;
			}

			$wppopup = $this->activewppopup;

			$wppopup_title = stripslashes($wppopup->wppopup_title);
			$wppopup_content = stripslashes($wppopup->wppopup_content);

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

			$style = '';
			$backgroundstyle = '';

			if($wppopup_usejs == 'yes') {
				$style = 'z-index:999999;';
				$box = 'color: #' . $wppopup_colour['fore'] . '; background: #' . $wppopup_colour['back'] . ';';
				$style .= 'left: -1000px; top: =100px;';
			} else {
				$style =  'left: ' . $wppopup_location['left'] . '; top: ' . $wppopup_location['top'] . ';' . ' z-index:999999;';
				$style .= 'margin-top: ' . $wppopup_margin['top'] . '; margin-bottom: ' . $wppopup_margin['bottom'] . '; margin-right: ' . $wppopup_margin['right'] . '; margin-left: ' . $wppopup_margin['left'] . ';';

				$box = 'width: ' . $wppopup_size['width'] . '; height: ' . $wppopup_size['height'] . '; color: #' . $wppopup_colour['fore'] . '; background: #' . $wppopup_colour['back'] . ';';

			}

			if(!empty($wppopup_delay) && $wppopup_delay != 'immediate') {
				// Hide the wppopup initially
				$style .= ' visibility: hidden;';
				$backgroundstyle .= ' visibility: hidden;';
			}

			$availablestyles = apply_filters( 'wppopup_available_styles_directory', array() );

			if( in_array($wppopupstyle, array_keys($availablestyles)) ) {
				$wppopup_messagebox = 'a' . md5(date('d')) . '-po';

				if(file_exists(trailingslashit($availablestyles[$wppopupstyle]) . 'wppopup.php')) {
					ob_start();
					include_once( trailingslashit($availablestyles[$wppopupstyle]) . 'wppopup.php' );
					ob_end_flush();
				}
			}


		}

		function is_fromsearchengine() {
			$ref = $_SERVER['HTTP_REFERER'];

			$SE = array('/search?', '.google.', 'web.info.com', 'search.', 'del.icio.us/search', 'soso.com', '/search/', '.yahoo.', '.bing.' );

			foreach ($SE as $url) {
				if (strpos($ref,$url)!==false) return true;
			}
			return false;
		}

		function is_ie()
		{
		    if (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false))
		        return true;
		    else
		        return false;
		}

		function is_loggedin() {
			return is_user_logged_in();
		}

		function has_commented() {
			if ( isset($_COOKIE['comment_author_'.COOKIEHASH]) ) {
				return true;
			} else {
				return false;
			}
		}

		function referrer_matches($check) {

			if(preg_match( '/' . $check . '/i', $_SERVER['HTTP_REFERER'] )) {
				return true;
			} else {
				return false;
			}

		}

		function has_reached_limit($count = 3) {
			if ( isset($_COOKIE['wppopup_view_'.COOKIEHASH]) && addslashes($_COOKIE['wppopup_view_'.COOKIEHASH]) >= $count ) {
				return true;
			} else {
				return false;
			}
		}

		function myURL() {

		 	if ($_SERVER["HTTPS"] == "on") {
				$url .= "https://";
			} else {
				$url = 'http://';
			}

			if ($_SERVER["SERVER_PORT"] != "80") {
		  		$url .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
		 	} else {
		  		$url .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		 	}

		 	return trailingslashit($url);
		}

		function onurl( $urllist = array() ) {

			$urllist = array_map( 'trim', $urllist );

			if(!empty($urllist)) {
				if(in_array($this->myURL(), $urllist)) {
					// we are on the list
					return true;
				} else {
					return false;
				}
			} else {
				return true;
			}

		}

		function clear_forever() {
			if ( isset($_COOKIE['wppopup_never_view']) ) {
				return true;
			} else {
				return false;
			}
		}

	}
}
?>