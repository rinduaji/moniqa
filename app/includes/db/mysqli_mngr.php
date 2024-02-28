<?php
/*********************************************
  CPG Dragonfly™ CMS
  ********************************************
  Copyright © 2004 - 2005 by CPG-Nuke Dev Team
  http://dragonflycms.org

  Dragonfly is released under the terms and conditions
  of the GNU GPL version 2 or any later version

  $Source: /cvs/html/includes/db/mysqli_mngr.php,v $
  $Revision: 1.27 $
  $Author: nanocaiordo $
  $Date: 2007/02/02 23:57:43 $
**********************************************/
if (!defined('CPG_NUKE')) { exit; }

define('DBFT_INT4', 'INT4');
define('DBFT_INT3', 'INT3');
define('DBFT_INT2', 'INT2');
define('DBFT_INT1', 'INT1');
define('DBFT_VARBINARY', 'VARBINARY');
define('DBFT_BLOB', 'BLOB');
define('DBFT_INDEX_FULLTEXT', 'FULLTEXT');

class sql_mngr
{

	//
	// Constructor
	//
	function sql_mngr(&$owner)
	{
		$this->_owner =& $owner;
		$this->fields = array(
			'SERIAL4' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE',
			'SERIAL8' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE',
			'TEXT' => 'TEXT',
			'BLOB' => 'BLOB',
//			'BOOL' => 'TINYINT(1)',
			'INT1' => 'TINYINT',
			'INT2' => 'SMALLINT',
			'INT3' => 'MEDIUMINT',
			'INT4' => 'INT',
			'INT8' => 'BIGINT',
			'CHAR' => 'CHAR',
			'VARCHAR' => 'VARCHAR',
			'FLOAT4' => 'FLOAT',
			'FLOAT8' => 'DOUBLE',
			'DECIMAL' => 'DECIMAL'
		);
	}
	function _create_patterns()
	{
		if (!empty($this->query_pattern)) { return; }
		# fix uniform field types to DB specific types in ALTER TABLE and CREATE TABLE
//		$this->query_pattern = array('/ VARBINARY(\([0-9]+\))/s');
//		$this->query_replace = array(' VARCHAR\\1 BINARY');
		foreach ($this->fields as $uni => $field) {
			# if we don't use this then everything messes up
			$this->query_pattern[] = "/ $uni([,\ \(])/s";
			$this->query_replace[] = " $field\\1";
		}
	}

	function get_versions()
	{
		$version['engine'] = 'MySQLi';
		$version['client'] = mysqli_get_client_info();
		$version['server'] = $this->_owner->connect_id->server_info;
		return $version;
	}

	function get_details()
	{
		$result = $this->_owner->query('SHOW VARIABLES', false, true);
		while ($row = $result->fetch_row()) { $details[$row[0]] = $row[1]; }
		$result->free();
		$details['engine']  = 'MySQLi';
		$details['client']  = mysqli_get_client_info();
		$details['server']  = $this->_owner->connect_id->server_info;
		$details['unicode'] = (version_compare($details['server'], '4.1') >= 0);
		$details['host'] = mysqli_get_host_info($this->_owner->connect_id);
		return $details;
	}

	function create_table($query)
	{
		$this->_create_patterns();
		$query = preg_replace($this->query_pattern, $this->query_replace, $query);
		return $this->_owner->query('CREATE TABLE '.$query.' TYPE=MyISAM'.(DB_CHARSET ? ' DEFAULT CHARSET='.DB_CHARSET : ''));
	}

	function alter_table($query)
	{
		$this->_create_patterns();
		$query = preg_replace($this->query_pattern, $this->query_replace, $query);
		return $this->_owner->query('ALTER TABLE '.$query);
	}

	function drop_table($table)
	{
		return $this->_owner->query('DROP TABLE '.$table);
	}

	function list_databases()
	{
		$databases = array();
		//hopefully mysqli and mysql exts are both loaded
		if (function_exists('mysql_list_dbs') && strpos(ini_get('disable_functions'), 'mysql_list_dbs') === false) {
			$result = mysql_list_dbs($this->_owner->connect_id);
		}
		if (!$result) $result = $this->_owner->query('SHOW DATABASES', true);
		if ($result) {
			while (list($name) = $result->fetch_row()) { $databases[$name] = $name; }
			$result->free();
		}
		if (empty($databases) && defined('ADMIN_PAGES')) {
			global $dbname;
			$databases[$dbname] = $dbname;
		}
		return $databases;
	}

	function list_tables($database)
	{
		global $prefix, $user_prefix;
		$result = $this->_owner->query(empty($database) ? 'SHOW TABLES' : "SHOW TABLES FROM `$database`");
		$tables = array();
		while (list($name) = $result->fetch_row()) {
			$id = preg_replace("#^($prefix|$user_prefix)_#", '', $name);
			$tables[$id] = $name;
		}
		$result->free();
		return $tables;
	}

	function list_columns($table, $uniform=true)
	{
		if ($result = $this->_owner->query("SHOW COLUMNS FROM $table", defined('INSTALL'), true))
		{
			if (empty($this->type_pattern)) {
				$this->type_pattern = array('/^VARCHAR(\([0-9]+\)) BINARY$/');
				$this->type_replace = array('VARBINARY\\1');
				foreach ($this->fields as $uni => $field) {
					# if we don't use ^$ then everything messes up
					$this->type_pattern[] = "/^$field($|\()/";
					$this->type_replace[] = $uni.'\\1';
				}
			}
			$return = array();
			while ($row = $result->fetch_assoc()) {
				$field = $row['Field'];
				$row['Type'] = strtoupper($row['Type']);
				if ($uniform) {
					if ($row['Extra'] == 'auto_increment') {
						$row['Type'] = (strpos($row['Type'], 'BIGINT') === false) ? 'SERIAL4' : 'SERIAL8';
					} elseif (strpos($row['Type'], 'INT(1)') !== false) {
						$row['Type'] = 'BOOL';
					} else {
						$row['Type'] = str_replace(' UNSIGNED', '', $row['Type']);
						if (strpos($row['Type'], 'INT(') !== false) {
							$row['Type'] = preg_replace('#\([0-9]+\)#', '', $row['Type']);
						}
						$row['Type'] = preg_replace($this->type_pattern, $this->type_replace, $row['Type'], 1);
					}
				} else {
					$return[$field]['Extra'] = strtoupper($row['Extra']);
				}
				$return[$field]['Field'] = $field;
				$return[$field]['Type'] = $row['Type'];
				$return[$field]['Null'] = intval($row['Null'] == 'YES');
				$return[$field]['Default'] = $row['Default'];
			}
			$result->free();
			return $return;
		}
		return false;
	}

	function list_indexes($table)
	{
		if ($result = $this->_owner->query("SHOW INDEX FROM $table", defined('INSTALL'), true)) {
			$return = array();
			while ($row = $result->fetch_assoc()) {
				$key = $row['Key_name'];
				$i = intval($row['Seq_in_index'])-1;
				$return[$key]['name'] = $key;
				$return[$key]['unique'] = ($row['Non_unique'] == '0');
				$return[$key]['type'] = $row['Index_type']; # BTREE or FULLTEXT
				$return[$key]['columns'][$i] = array('name' => $row['Column_name']);
//					'Sub_part' => '',
//					'Packed' => '',
//					'Null' => ''
			}
			$result->free();
			return $return;
		}
		return false;
	}

	function alter_field($mode, $table, $field, $type='', $null=TRUE, $default=NULL)
	{
		switch ($mode)
		{
			case 'add':
				if ($type == 'TEXT') {
					return $this->alter_table("$table ADD $field $type".($null?'':' NOT').' NULL');
				} else {
					return $this->alter_table("$table ADD $field $type".($null?'':' NOT').' NULL DEFAULT '.(isset($default)?"'$default'":'NULL'));
				}

			case 'drop':
				return $this->_owner->query("ALTER TABLE $table DROP $field");

			case 'change':
				if (!is_array($field)) $field = array($field, $field);
				if ($type == 'TEXT') {
					return $this->alter_table("$table CHANGE $field[0] $field[1] $type".($null?'':' NOT').' NULL');
				} else {
					return $this->alter_table("$table CHANGE $field[0] $field[1] $type".($null?'':' NOT').' NULL DEFAULT '.(isset($default)?"'$default'":'NULL'));
				}
		}
	}

	function alter_index($mode, $table, $name, $columns='')
	{
		switch ($mode)
		{
			case 'index':
				return $this->_owner->query("CREATE INDEX $name ON $table ($columns)");

			case 'unique':
				if ($name == 'PRIMARY') {
					return $this->_owner->query("ALTER TABLE $table ADD PRIMARY KEY ($columns)");
				} else {
					return $this->_owner->query("CREATE UNIQUE INDEX $name ON $table ($columns)");
				}
			case 'fulltext':
				return $this->_owner->query("CREATE FULLTEXT INDEX $name ON $table ($columns)");
			case 'drop':
				$key = ($name == 'PRIMARY') ? 'PRIMARY KEY' : 'INDEX '.$name;
				return $this->_owner->query("ALTER TABLE $table DROP $key");
		}
	}

	function increment_serial($to, $table, $field)
	{
		# Don't have to do anything for this DB.
	}

	function optimize_table($table, $full)
	{
		return $this->_owner->query("OPTIMIZE TABLE $table");
	}

}
