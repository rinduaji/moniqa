<?php
/*********************************************
  CPG Dragonfly™ CMS
  ********************************************
  Copyright © 2004 - 2005 by CPG-Nuke Dev Team
  http://dragonflycms.org

  Dragonfly is released under the terms and conditions
  of the GNU GPL version 2 or any later version

  $Source: /cvs/html/includes/classes/sqlctrl.php,v $
  $Revision: 9.17 $
  $Author: nanocaiordo $
  $Date: 2006/12/02 05:30:14 $
**********************************************/
if (!defined('CPG_NUKE')) { exit; }

class DBCtrl {

	function output($str, $compress, $end=false)
	{
		static $buffer;
		if ($compress) {
			$buffer .= $str;
			if ($end || strlen($buffer) > 20480) {
				echo gzencode($buffer);
				$buffer = '';
			}
		} else {
			echo $str;
		}
	}


	function query_file($file, &$error, $replace_prefix=false)
	{
		$error = false;
		if (!is_array($file)) {
			$tmp['name'] = $tmp['tmp_name'] = $file;
			$tmp['type'] = preg_match("/\.gz$/is", $file) ? 'application/x-gzip' : 'text/plain';
			$file = $tmp;
		}
		if (empty($file['tmp_name']) || empty($file['name'])) cpg_error('ERROR no file specified!');
		// Most servers identify a .gz as x-tar
		if (preg_match("/^(text\/[a-zA-Z]+)|(application\/(x\-)?(gzip|tar)(\-compressed)?)|(application\/octet-stream)$/is", $file['type'])) {
			$filedata = '';
			$open = 'gzopen';
			$eof = 'gzeof';
			$read = 'gzgets';
			$close = 'gzclose';
			if (!GZIPSUPPORT) {
				if (preg_match("/\.gz$/is", $file['name'])) {
					$error = "Can't decompress file";
					return false;
				}
				$open = 'fopen';
				$eof = 'feof';
				$read = 'fread';
				$close = 'fclose';
			}
			$rc = $open($file['tmp_name'], 'rb');
			if ($rc) {
				while (!$eof($rc)) $filedata .= $read($rc, 100000);
				$close($rc);
			} else {
				$error = 'Couldn\'t open '.$file['tmp_name'].' for processing';
			}
		} else {
			$error = "Invalid filename: $file[type] $file[name]";
		}
		if ($error) { return false; }
		$filedata = DBCtrl::remove_remarks($filedata);
		$queries = DBCtrl::split_sql_file($filedata, ";\n");
		if (count($queries) < 1) {
			$error = 'There are no queries in '.$file['name'];
			return false;
		}
		global $db, $prefix;
		set_time_limit(0);
		foreach($queries AS $query) {
			if (!$replace_prefix) {
				$query = preg_replace('#(TABLE|INTO|EXISTS|ON) ([a-zA-Z]*?(_))#i', "\\1 $prefix".'_', $query);
			} else {
				foreach($replace_prefix AS $oldprefix => $newprefix) {
					if ($oldprefix != $newprefix) {
						$query = preg_replace("/$oldprefix/", $newprefix, $query);
					}
				}
			}
			if (SQL_LAYER == 'mysql' && ereg('^CREATE TABLE ', $query) && !eregi('ENGINE=MyISAM', $query)) {
				$query .= ' ENGINE=MyISAM';
			}
			$db->sql_query($query);
		}
		return true;
	}

	// remove_remarks will strip the sql comment lines out of an uploaded sql file
	function remove_remarks($lines)
	{
		$lines = explode("\n", $lines);
		$linecount = count($lines);
		$output = '';
		for ($i = 0; $i < $linecount; $i++) {
			$line = trim($lines[$i]);
			if (strlen($line) > 0) {
				if ($line[0] != "#" && $line[0] != "-") { $output .= $line . "\n"; }
				# Trading a bit of speed for lower memory use here.
				$lines[$i] = '';
			}
		}
		return $output;
	}

	// split_sql_file will split an uploaded sql file into single sql statements.
	// Note: expects trim() to have already been run on $sql.
	function split_sql_file(&$sql, $delimiter)
	{
		// Split up our string into "possible" SQL statements.
		$tokens = explode($delimiter, $sql);
		unset($sql);
		$output = array();

		// we don't actually care about the matches preg gives us.
		$matches = array();

		// this is faster than calling count($oktens) every time thru the loop.
		$token_count = count($tokens);
		for ($i = 0; $i < $token_count; $i++) {
			// Don't wanna add an empty string as the last thing in the array.
			if (($i != ($token_count - 1)) || (strlen($tokens[$i] > 0))) {
				// This is the total number of single quotes in the token.
				$total_quotes = preg_match_all("/'/", $tokens[$i], $matches);
				// Counts single quotes that are preceded by an odd number of backslashes,
				// which means they're escaped quotes.
				$escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$i], $matches);

				$unescaped_quotes = $total_quotes - $escaped_quotes;

				// If the number of unescaped quotes is even, then the delimiter did NOT occur inside a string literal.
				if (($unescaped_quotes % 2) == 0) {
					// It's a complete sql statement.
					$output[] = $tokens[$i];
					// save memory.
					$tokens[$i] = '';
				} else {
					// incomplete sql statement. keep adding tokens until we have a complete one.
					// $temp will hold what we have so far.
					$temp = $tokens[$i].$delimiter;
					// save memory..
					$tokens[$i] = '';

					// Do we have a complete statement yet?
					$complete_stmt = false;

					for ($j = $i + 1; (!$complete_stmt && ($j < $token_count)); $j++) {
						// This is the total number of single quotes in the token.
						$total_quotes = preg_match_all("/'/", $tokens[$j], $matches);
						// Counts single quotes that are preceded by an odd number of backslashes,
						// which means they're escaped quotes.
						$escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$j], $matches);

						$unescaped_quotes = $total_quotes - $escaped_quotes;

						if (($unescaped_quotes % 2) == 1) {
							// odd number of unescaped quotes. In combination with the previous incomplete
							// statement(s), we now have a complete statement. (2 odds always make an even)
							$output[] = $temp.$tokens[$j];

							$tokens[$j] = '';
							$temp = '';

							// exit the loop.
							$complete_stmt = true;
							// make sure the outer loop continues at the right point.
							$i = $j;
						} else {
							// even number of unescaped quotes. We still don't have a complete statement.
							// (1 odd and 1 even always make an odd)
							$temp .= $tokens[$j].$delimiter;
							$tokens[$j] = '';
						}
					} // for..
				} // else
			}
		}
		return $output;
	}
}
require(CORE_PATH.'classes/sqlctrl/'.DB_TYPE.'.php');
