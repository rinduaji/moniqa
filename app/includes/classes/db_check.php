<?php
/*********************************************
  CPG Dragonfly™ CMS
  ********************************************
  Copyright © 2004 - 2006 by CPG-Nuke Dev Team
  http://dragonflycms.org

  Dragonfly is released under the terms and conditions
  of the GNU GPL version 2 or any later version

  $Source: /cvs/html/includes/classes/db_check.php,v $
  $Revision: 1.12 $
  $Author: nanocaiordo $
  $Date: 2007/03/01 14:12:48 $
**********************************************/

# The way these 4 definitions provide insertions to the database are different.
# DF_DATA_CHECK_ONLY handles SERIALs correctly across all DBs. The other
# definitions do not. Therefore, in the definition of the table data, another
# member must be defined. This new member MUST be called 'serial' and MUST
# contain the name of the SERIAL column. Check out the users and users_fields
# arrays in sql/data/core.php for examples.
define('DF_DATA_CHECK_ONLY', 0);   # table contains data do nothing
define('DF_DATA_MUST_BE_SAME', 1); # not the same then update
define('DF_DATA_EXIST_LEVEL1', 2); # only if not exist add record
define('DF_DATA_EXIST_LEVEL2', 3); # see DF_DATA_EXIST_LEVEL1

class db_check
{

	function table_structure(&$table, &$columns, &$indexes)
	{
		if (db_check::table_columns($table, $columns)) {
			db_check::table_indexes($table, $indexes);
		} else {
			# table doesn't exists so create it
			db_check::create_table($table, $columns, $indexes);
		}
	}

	# Function that compares the columns
	function table_columns(&$table, &$columns)
	{
		global $db, $installer, $tablelist, $optional, $table_ids, $enum_fields;
		$querytable = $tablelist[$table];
		# check if table exists
		if ($result = $db->list_columns($querytable)) {
			# now check each existing column
			echo "<br />\n$table columns: ";
			foreach ($result as $row) {
				$field =& $row['Field'];
				# if column exists in dragonfly so we compare it
				if (isset($columns[$field])) {
					# Default must be checked on NULL
					$col =& $columns[$field];
					if (SQL_LAYER == 'postgresql' && eregi('VARBINARY', $col['Type'])) $col['Type'] = 'VARBINARY';
					if ($row['Type'] != $col['Type'] ||
						$row['Null'] != $col['Null'] ||
						(isset($row['Default']) != isset($col['Default']) || $row['Default'] != (string)$col['Default']))
					{
						# not the same so modify
						if (eregi('(DATETIME|TIMESTAMP)', $row['Type'])) {
							db_check::dttotime($querytable, $table, $table_ids[$table], $field, array($field, $col['Type'], $col['Null'], $col['Default']));
						} else if (!eregi('int', $row['Type'])) {
							if (($table == 'users' || $table == 'users_temp') && $field == 'user_regdate') {
								db_check::rdtotime($querytable, $table, 'user_id', $field);
								$installer->add_query('CHANGE', $table, array($field, $col['Type'], $col['Null'], $col['Default']));
							} else if (eregi('int', $col['Type']) && eregi('enum',$row['Type'])) {
								# fail safe, not critical error.
								if (!isset($enum_fields[$table])) {
									echo '<br />Couldn\'t stat indexes for '.$table.'. Contact CPG DragonflyCMS Dev Team about the warning<br />';
									continue;
								}
								$installer->add_query('CHANGE', $table, array($field, $col['Type'], $col['Null'], $col['Default']));
								db_check::enumtoint($querytable, $table, $enum_fields[$table], $field);
							}
						}
					}
					unset($columns[$field]); # free some memory since we don't need it anymore
				} else {
					# column not used in dragonfly so we delete it
					$optional[] = "ALTER TABLE $querytable DROP $field";
				}
				echo '. ';
				flush();
			}
			# add the not existing columns
			foreach ($columns as $field => $col) {
				$installer->add_query('ADD', $table, array($field, $col['Type'], $col['Null'], $col['Default']));
			}
			return true;
		}
		# table doesn't exists
		return false;
	}

	# Function that compares the indexes
	function table_indexes(&$table, &$indexes)
	{
		global $db, $installer, $tablelist, $optional;
		$querytable = $tablelist[$table];
		# check each index
		if ($result = $db->list_indexes($querytable)) {
			echo "<br />\n$table indexes: ";
			$drop = array();
			foreach ($result as $key => $row) {
				if (isset($indexes[$key])) {
					if (!isset($drop[$key])) $drop[$key] = false;
					if ($row['unique'] != $indexes[$key]['unique'] || $row['type'] != $indexes[$key]['type']) {
						$drop[$key] = true;
					}

					foreach ($row['columns'] as $colindex => $data) {
						if ($data['name'] != $indexes[$key][$colindex]['name']) {
//							|| $data['Sub_part'] != $indexes[$key][$colindex]['Sub_part']
							$drop[$key] = true; break;
						}
					}
				} else {
					# index not used in Dragonfly so we delete it
					$optional[] = "ALTER TABLE $querytable DROP ".(($key == 'PRIMARY') ? "$key KEY" : "$key");
					$drop[$key] = true;
				}
				echo '. ';
				flush();
			}
			# add or modify the indexes
			foreach ($indexes as $key => $idata) {
				if (isset($drop[$key]) && $drop[$key]) {
					$installer->add_query('DROP_INDEX', $table, $key);
				}
				if (!isset($drop[$key]) || $drop[$key]) {
					$type = 'INDEX';
					if ($idata['unique']) $type = 'UNIQUE';
					if ($idata['type'] == 'FULLTEXT') $type = 'FULLTEXT';
					$columns = array();
					$i = 0;
					while (isset($idata[$i])) {
						$field = $idata[$i]['name'];
						if (!empty($idata[$i]['Sub_part'])) $field .= '('.$idata[$i]['Sub_part'].')';
						$columns[] = $field;
						$i++;
					}
					$installer->add_query($type, $table, $key, implode(', ', $columns));
				}
			}
			return true;
		}
		return false;
	}

	# Function that checks the data of a table
	function table_data(&$table, &$content)
	{
		global $db, $installer, $prefix, $user_prefix, $tablelist;
		if (isset($tablelist[$table])) {
			$querytable = $tablelist[$table];
			if ($content['compare'] == DF_DATA_CHECK_ONLY) {
				$result = $db->query("SELECT COUNT(*) FROM $querytable", true);
				list($count) = $db->sql_fetchrow($result, SQL_NUM);
				if ($count > 0) $content['content'] = array();
			} else {
				echo "<br />\n$table content: ";
				$result = $db->query("SELECT ".$content['query']." FROM $querytable", true);
				# table exists now check each existing record
				while ($row = $db->sql_fetchrow($result, SQL_NUM)) {
					switch ($content['compare']) {
	
						case DF_DATA_MUST_BE_SAME:
							if (isset($content['content'][$row[0]])) {
								if ($row[1] == $content['content'][$row[0]][0]) {
									unset($content['content'][$row[0]]);
								} else if (isset($content['del'])) {
									$installer->add_query('DELETE', $table, $content['del']."='$row[0]'");
								}
							}
							break;

						case DF_DATA_EXIST_LEVEL1:
							if (isset($content['content'][$row[0]])) {
								unset($content['content'][$row[0]]);
							}
							break;

						case DF_DATA_EXIST_LEVEL2:
							if (isset($content['content'][$row[0]][$row[1]])) {
								unset($content['content'][$row[0]][$row[1]]);
							}
							break;

					}
					echo '. ';
					flush();
				}
			}
			$db->sql_freeresult($result);
		}
		db_check::insert_data($table, $content);
	}

	# Function to convert DATETIME & TIMESTAMP fields in records to INT
	function dttotime($querytable, $table, $id, $field, $column)
	{
		global $db, $installer;
		$result = $db->query("SELECT $id, UNIX_TIMESTAMP($field) FROM $querytable");
		$installer->add_query('DEL', $table, $field);
		$installer->add_query('ADD', $table, $column);
		while ($row = $db->fetch_array($result, SQL_NUM)) {
			$installer->add_query('UPDATE', $table, "$field='".$db->escape_string($row[1])."' WHERE $id='$row[0]'");
		}
		$db->free_result($result);
	}

	# Function to convert regdate (string) field in records to INT
	function rdtotime($querytable, $table, $id, $field)
	{
		global $db, $installer;
		$result = $db->query("SELECT $id, $field FROM $querytable");
		while ($row = $db->fetch_array($result, SQL_NUM)) {
			$installer->add_query('UPDATE', $table, "$field='".strtotime($row[1])."' WHERE $id='$row[0]'");
		}
		$db->free_result($result);
	}

	# Function to convert ENUM field in records to INT
	function enumtoint($querytable, $table, $id, $field)
	{
		global $db, $installer;
		$user_active_cp = array();
		$result = $db->query("SELECT $id FROM $querytable WHERE $field='NO'");
		while ($tid = $db->fetch_array($result, SQL_NUM)) { $user_active_cp[] = $tid[0]; }
		$db->free_result($result);
		if (count($user_active_cp) > 0) {
			$user_active_cp = implode(',', $user_active_cp);
			$installer->add_query('UPDATE', $table, "$field=0 WHERE $id IN ($user_active_cp)");
			$installer->add_query('UPDATE', $table, "$field=1 WHERE $id NOT IN ($user_active_cp)");
		} else {
			$installer->add_query('UPDATE', $table, "$field=1 WHERE $id>0");
		}
	}

	function create_table(&$table, &$columns, &$indexes)
	{
		global $installer;
		$struct = array();
		# process the fields
		foreach ($columns as $field => $col) {
			$add = "  $field $col[Type]";
			if (strpos($col['Type'], 'SERIAL') === FALSE) {
				if (SQL_LAYER != 'postgresql' && ($col['Type'] == 'TEXT' || $col['Type'] == 'BLOB')) {
					$add .= ($col['Null']) ? ' NULL' : ' NOT NULL';
				} else {
					if (!$col['Null']) $add .= ' NOT';
					$add .= ' NULL DEFAULT '.(isset($col['Default']) ? "'$col[Default]'" : 'NULL');
				}
			}
			$struct[] = $add;
		}
		# process the indexes
		foreach ($indexes as $index => $idata) {
			$type = '';
			if ($index == 'PRIMARY') {
				$type = ' PRIMARY';
				$index = '';
			}
			else if ($idata['unique']) $type = ' UNIQUE';
			else if ($idata['type'] == 'FULLTEXT') $type = ' FULLTEXT';
			$columns = array();
			$i = 0;
			while (isset($idata[$i])) {
				$field = $idata[$i]['name'];
				if (!empty($idata[$i]['Sub_part'])) $field .= '('.$idata[$i]['Sub_part'].')';
				$columns[] = $field;
				$i++;
			}
			$struct[] = " $type KEY $index (".implode(', ', $columns).')';
		}
		$installer->add_query('CREATE', $table, "\n".implode(",\n", $struct)."\n", $table);
	}

	function insert_data(&$table, &$content)
	{
		global $installer;
		foreach ($content['content'] as $main => $data) {
			switch ($content['compare']) {

				case DF_DATA_CHECK_ONLY:
					$tmp = array();
					foreach ($data as $val) { $tmp[] = ($val == NULL)?'DEFAULT':"'$val'"; }
					$installer->add_query('INSERT', $table, 'DEFAULT, '.implode(', ', $tmp));
					break;

				case DF_DATA_MUST_BE_SAME:
					$tmp = array();
					foreach ($data as $val) { $tmp[] = ($val == NULL)?'DEFAULT':"'$val'"; }
					$installer->add_query('INSERT', $table, "'$main', ".implode(', ', $tmp));
					break;

				case DF_DATA_EXIST_LEVEL1:
					$installer->add_query('INSERT', $table, "'$main', $data");
					break;

				case DF_DATA_EXIST_LEVEL2:
					foreach ($data as $sub => $value) {
						$installer->add_query('INSERT', $table, "'$main', '$sub', '$value'");
					}
					break;

			}
		}
		if (isset($content['serial'])) {
			$increment['field'] = $content['serial'];
			$increment['value'] = count($content['content']);
			$installer->add_query('INC_SERIAL', $table, $increment);
		}
	}

}
