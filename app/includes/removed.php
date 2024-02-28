<?php
/*********************************************
  CPG Dragonfly™ CMS
  ********************************************
  Copyright © 2004 - 2005 by CPG-Nuke Dev Team
  http://dragonflycms.org

  Dragonfly is released under the terms and conditions
  of the GNU GPL version 2 or any later version

  $Source: /cvs/html/includes/removed.php,v $
  $Revision: 9.2 $
  $Author: djmaze $
  $Date: 2005/12/29 03:54:31 $
**********************************************/

# Deprecated Functions only available thru CPG_DEBUG mode

function depricated_warning($function, $backtrace) {
	$backtrace = ($backtrace) ? $backtrace[0] : array('file' => 'unknown file', 'line' => 0);
	trigger_error("DEPRECATED call to <a href=\"http://dragonflycms.org/$function\">$function</a>() by {$backtrace['file']} on line {$backtrace['line']}.", E_USER_WARNING);
}

function nuke_error($message, $title='ERROR', $redirect='') {
	depricated_warning('nuke_error', (PHPVERS >= 43) ? debug_backtrace() : false);
	cpg_error($message, $title, $redirect);
}

function cookiedecode() {
	depricated_warning('cookiedecode', (PHPVERS >= 43) ? debug_backtrace() : false);
	return false;
}
function getusrinfo() {
	depricated_warning('getusrinfo', (PHPVERS >= 43) ? debug_backtrace() : false);
	global $userinfo;
	return $userinfo;
}
function FixQuotes($what) {
	depricated_warning('FixQuotes', (PHPVERS >= 43) ? debug_backtrace() : false);
	$what = ereg_replace("'","''",$what);
	while (eregi("\\\\'", $what)) { $what = ereg_replace("\\\\'","'",$what); }
	return $what;
}
function formatTimestamp($time) {
	depricated_warning('formatTimestamp', (PHPVERS >= 43) ? debug_backtrace() : false);
	return formatDateTime($time, _DATESTRING);
}
function check_html($str, $strip='') {
	depricated_warning('check_html', (PHPVERS >= 43) ? debug_backtrace() : false);
	return Fix_Quotes($str, empty($strip));
}
function filter_text($Message, $strip='') {
	depricated_warning('filter_text', (PHPVERS >= 43) ? debug_backtrace() : false);
	return check_words($Message);
}
function delQuotes($string) {
	depricated_warning('delQuotes', (PHPVERS >= 43) ? debug_backtrace() : false);
	return $string;
}
function is_group() {
	depricated_warning('is_group', (PHPVERS >= 43) ? debug_backtrace() : false);
}
function update_points() {
	depricated_warning('update_points', (PHPVERS >= 43) ? debug_backtrace() : false);
}
function formatAidHeader($aid) {
	depricated_warning('formatAidHeader', (PHPVERS >= 43) ? debug_backtrace() : false);
	echo $aid;
}
function get_author($aid) {
	depricated_warning('get_author', (PHPVERS >= 43) ? debug_backtrace() : false);
	return $aid;
}
