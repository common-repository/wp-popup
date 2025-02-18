<?php
// a true setting for PO_GLOBAL means that this plugin operates on a global site-admin basis
// setting this to false means that the plugin operates on a blog by blog basis
if(!defined('PO_GLOBAL')) define('PO_GLOBAL', false);
// The url that we are using to return the country - it should only return the country code for the passed IP address
if(!defined('PO_REMOTE_IP_URL')) define('PO_REMOTE_IP_URL', 'http://api.hostip.info/country.php?ip=%ip%');
// If there is a problem with the API, then you can set a default country to use for wppopup showing. Set this to false if you'd rather have the wppopup not show in such circumstances
if(!defined('PO_DEFAULT_COUNTRY')) define('PO_DEFAULT_COUNTRY', 'US');
?>