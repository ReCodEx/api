<?php
/** Adminer - Compact database management
* @link https://www.adminer.org/
* @author Jakub Vrana, https://www.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
* @version 5.4.1
*/
// this is matched by compile.php

namespace Adminer;

?>
<?php
?>
<?php
const VERSION = "5.4.1";

?>
<?php
error_reporting(24575); // all but E_DEPRECATED (overriding mysqli methods without types is deprecated)
set_error_handler(function ($errno, $errstr) {
	// "Undefined array key" mutes $_GET["q"] if there's no ?q=
	// "Undefined offset" and "Undefined index" are older messages for the same thing
	return !!preg_match('~^Undefined (array key|offset|index)~', $errstr);
}, E_WARNING | E_NOTICE); // warning since PHP 8.0

// this is matched by compile.php


// disable filter.default
$filter = !preg_match('~^(unsafe_raw)?$~', ini_get("filter.default"));
if ($filter || ini_get("filter.default_flags")) {
	foreach (array('_GET', '_POST', '_COOKIE', '_SERVER') as $val) {
		$unsafe = filter_input_array(constant("INPUT$val"), FILTER_UNSAFE_RAW);
		if ($unsafe) {
			$$val = $unsafe;
		}
	}
}

if (function_exists("mb_internal_encoding")) {
	mb_internal_encoding("8bit");
}

?>
<?php
// This file is used both in Adminer and Adminer Editor.

/** Get database connection
* @param ?Db $connection2 custom connection to use instead of the default
* @return Db
*/
function connection(?Db $connection2 = null) {
	// can be used in customization, Db::$instance is minified
	return ($connection2 ?: Db::$instance);
}

/** Get Adminer object
* @return Adminer|Plugins
*/
function adminer() {
	return Adminer::$instance;
}

/** Get Driver object */
function driver(): Driver {
	return Driver::$instance;
}

/** Connect to the database */
function connect(): ?Db {
	$credentials = adminer()->credentials();
	$return = Driver::connect($credentials[0], $credentials[1], $credentials[2]);
	return (is_object($return) ? $return : null);
}

/** Unescape database identifier
* @param string $idf text inside ``
*/
function idf_unescape(string $idf): string {
	if (!preg_match('~^[`\'"[]~', $idf)) {
		return $idf;
	}
	$last = substr($idf, -1);
	return str_replace($last . $last, $last, substr($idf, 1, -1));
}

/** Shortcut for connection()->quote($string) */
function q(string $string): string {
	return connection()->quote($string);
}

/** Escape string to use inside '' */
function escape_string(string $val): string {
	return substr(q($val), 1, -1);
}

/** Get a possibly missing item from a possibly missing array
* idx($row, $key) is better than $row[$key] ?? null because PHP will report error for undefined $row
* @param ?mixed[] $array
* @param array-key $key
* @param mixed $default
* @return mixed
*/
function idx(?array $array, $key, $default = null) {
	return ($array && array_key_exists($key, $array) ? $array[$key] : $default);
}

/** Remove non-digits from a string; used instead of intval() to not corrupt big numbers
* @return numeric-string
*/
function number(string $val): string {
	return preg_replace('~[^0-9]+~', '', $val);
}

/** Get regular expression to match numeric types */
function number_type(): string {
	return '((?<!o)int(?!er)|numeric|real|float|double|decimal|money)'; // not point, not interval
}

/** Disable magic_quotes_gpc
* @param list<array> $process e.g. [&$_GET, &$_POST, &$_COOKIE]
* @param bool $filter whether to leave values as is
* @return void modified in place
*/
function remove_slashes(array $process, bool $filter = false): void {
	if (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) {
		while (list($key, $val) = each($process)) {
			foreach ($val as $k => $v) {
				unset($process[$key][$k]);
				if (is_array($v)) {
					$process[$key][stripslashes($k)] = $v;
					$process[] = &$process[$key][stripslashes($k)];
				} else {
					$process[$key][stripslashes($k)] = ($filter ? $v : stripslashes($v));
				}
			}
		}
	}
}

/** Escape or unescape string to use inside form [] */
function bracket_escape(string $idf, bool $back = false): string {
	// escape brackets inside name="x[]"
	static $trans = array(':' => ':1', ']' => ':2', '[' => ':3', '"' => ':4');
	return strtr($idf, ($back ? array_flip($trans) : $trans));
}

/** Check if connection has at least the given version
* @param string|float $version required version
* @param string|float $maria_db required MariaDB version
*/
function min_version($version, $maria_db = "", ?Db $connection2 = null): bool {
	$connection2 = connection($connection2);
	$server_info = $connection2->server_info;
	if ($maria_db && preg_match('~([\d.]+)-MariaDB~', $server_info, $match)) {
		$server_info = $match[1];
		$version = $maria_db;
	}
	return $version && version_compare($server_info, $version) >= 0;
}

/** Get connection charset */
function charset(Db $connection): string {
	return (min_version("5.5.3", 0, $connection) ? "utf8mb4" : "utf8"); // SHOW CHARSET would require an extra query
}

/** Get INI boolean value */
function ini_bool(string $ini): bool {
	$val = ini_get($ini);
	return (preg_match('~^(on|true|yes)$~i', $val) || (int) $val); // boolean values set by php_value are strings
}

/** Get INI bytes value */
function ini_bytes(string $ini): int {
	$val = ini_get($ini);
	switch (strtolower(substr($val, -1))) {
		case 'g':
			$val = (int) $val * 1024; // no break
		case 'm':
			$val = (int) $val * 1024; // no break
		case 'k':
			$val = (int) $val * 1024;
	}
	return $val;
}

/** Check if SID is necessary */
function sid(): bool {
	static $return;
	if ($return === null) { // restart_session() defines SID
		$return = (SID && !($_COOKIE && ini_bool("session.use_cookies"))); // $_COOKIE - don't pass SID with permanent login
	}
	return $return;
}

/** Set password to session */
function set_password(string $vendor, ?string $server, string $username, ?string $password): void {
	$_SESSION["pwds"][$vendor][$server][$username] = ($_COOKIE["adminer_key"] && is_string($password)
		? array(encrypt_string($password, $_COOKIE["adminer_key"]))
		: $password
	);
}

/** Get password from session
* @return string|false|null null for missing password, false for expired password
*/
function get_password() {
	$return = get_session("pwds");
	if (is_array($return)) {
		$return = ($_COOKIE["adminer_key"]
			? decrypt_string($return[0], $_COOKIE["adminer_key"])
			: false
		);
	}
	return $return;
}

/** Get single value from database
* @return string|false false if error
*/
function get_val(string $query, int $field = 0, ?Db $conn = null) {
	$conn = connection($conn);
	$result = $conn->query($query);
	if (!is_object($result)) {
		return false;
	}
	$row = $result->fetch_row();
	return ($row ? $row[$field] : false);
}

/** Get list of values from database
* @param array-key $column
* @return list<string>
*/
function get_vals(string $query, $column = 0): array {
	$return = array();
	$result = connection()->query($query);
	if (is_object($result)) {
		while ($row = $result->fetch_row()) {
			$return[] = $row[$column];
		}
	}
	return $return;
}

/** Get keys from first column and values from second
* @return string[]
*/
function get_key_vals(string $query, ?Db $connection2 = null, bool $set_keys = true): array {
	$connection2 = connection($connection2);
	$return = array();
	$result = $connection2->query($query);
	if (is_object($result)) {
		while ($row = $result->fetch_row()) {
			if ($set_keys) {
				$return[$row[0]] = $row[1];
			} else {
				$return[] = $row[0];
			}
		}
	}
	return $return;
}

/** Get all rows of result
* @return list<string[]> of associative arrays
*/
function get_rows(string $query, ?Db $connection2 = null, string $error = "<p class='error'>"): array {
	$conn = connection($connection2);
	$return = array();
	$result = $conn->query($query);
	if (is_object($result)) { // can return true
		while ($row = $result->fetch_assoc()) {
			$return[] = $row;
		}
	} elseif (!$result && !$connection2 && $error && (defined('Adminer\PAGE_HEADER') || $error == "-- ")) {
		echo $error . error() . "\n";
	}
	return $return;
}

/** Find unique identifier of a row
* @param string[] $row
* @param Index[] $indexes
* @return string[]|void null if there is no unique identifier
*/
function unique_array(?array $row, array $indexes) {
	foreach ($indexes as $index) {
		if (preg_match("~PRIMARY|UNIQUE~", $index["type"])) {
			$return = array();
			foreach ($index["columns"] as $key) {
				if (!isset($row[$key])) { // NULL is ambiguous
					continue 2;
				}
				$return[$key] = $row[$key];
			}
			return $return;
		}
	}
}

/** Escape column key used in where() */
function escape_key(string $key): string {
	if (preg_match('(^([\w(]+)(' . str_replace("_", ".*", preg_quote(idf_escape("_"))) . ')([ \w)]+)$)', $key, $match)) { //! columns looking like functions
		return $match[1] . idf_escape(idf_unescape($match[2])) . $match[3]; //! SQL injection
	}
	return idf_escape($key);
}

/** Create SQL condition from parsed query string
* @param array{where:string[], null:list<string>} $where parsed query string
* @param Field[] $fields
*/
function where(array $where, array $fields = array()): string {
	$return = array();
	foreach ((array) $where["where"] as $key => $val) {
		$key = bracket_escape($key, true); // true - back
		$column = escape_key($key);
		$field = idx($fields, $key, array());
		$field_type = $field["type"];
		$return[] = $column
			. (JUSH == "sql" && $field_type == "json" ? " = CAST(" . q($val) . " AS JSON)"
				: (JUSH == "pgsql" && preg_match('~^json~', $field_type) ? "::jsonb = " . q($val) . "::jsonb"
				: (JUSH == "sql" && is_numeric($val) && preg_match('~\.~', $val) ? " LIKE " . q($val) // LIKE because of floats but slow with ints
				: (JUSH == "mssql" && strpos($field_type, "datetime") === false ? " LIKE " . q(preg_replace('~[_%[]~', '[\0]', $val)) // LIKE because of text but it does not work with datetime
				: " = " . unconvert_field($field, q($val))))))
		; //! enum and set
		if (JUSH == "sql" && preg_match('~char|text~', $field_type) && preg_match("~[^ -@]~", $val)) { // not just [a-z] to catch non-ASCII characters
			$return[] = "$column = " . q($val) . " COLLATE " . charset(connection()) . "_bin";
		}
	}
	foreach ((array) $where["null"] as $key) {
		$return[] = escape_key($key) . " IS NULL";
	}
	return implode(" AND ", $return);
}

/** Create SQL condition from query string
* @param Field[] $fields
*/
function where_check(string $val, array $fields = array()): string {
	parse_str($val, $check);
	remove_slashes(array(&$check));
	return where($check, $fields);
}

/** Create query string where condition from value
* @param int $i condition order
* @param string $column column identifier
*/
function where_link(int $i, string $column, ?string $value, string $operator = "="): string {
	return "&where%5B$i%5D%5Bcol%5D=" . urlencode($column) . "&where%5B$i%5D%5Bop%5D=" . urlencode(($value !== null ? $operator : "IS NULL")) . "&where%5B$i%5D%5Bval%5D=" . urlencode($value);
}

/** Get select clause for convertible fields
* @param mixed[] $columns only keys are used
* @param Field[] $fields
* @param list<string> $select
*/
function convert_fields(array $columns, array $fields, array $select = array()): string {
	$return = "";
	foreach ($columns as $key => $val) {
		if ($select && !in_array(idf_escape($key), $select)) {
			continue;
		}
		$as = convert_field($fields[$key]);
		if ($as) {
			$return .= ", $as AS " . idf_escape($key);
		}
	}
	return $return;
}

/** Set cookie valid on current path
* @param int $lifetime number of seconds, 0 for session cookie, 2592000 - 30 days
*/
function cookie(string $name, ?string $value, int $lifetime = 2592000): void {
	header(
		"Set-Cookie: $name=" . urlencode($value)
			. ($lifetime ? "; expires=" . gmdate("D, d M Y H:i:s", time() + $lifetime) . " GMT" : "")
			. "; path=" . preg_replace('~\?.*~', '', $_SERVER["REQUEST_URI"])
			. (HTTPS ? "; secure" : "")
			. "; HttpOnly; SameSite=lax",
		false
	);
}

/** Get settings stored in a cookie
* @return mixed[]
*/
function get_settings(string $cookie): array {
	parse_str($_COOKIE[$cookie], $settings);
	return $settings;
}

/** Get setting stored in a cookie
* @param mixed $default
* @return mixed
*/
function get_setting(string $key, string $cookie = "adminer_settings", $default = null) {
	return idx(get_settings($cookie), $key, $default);
}

/** Store settings to a cookie
* @param mixed[] $settings
*/
function save_settings(array $settings, string $cookie = "adminer_settings"): void {
	$value = http_build_query($settings + get_settings($cookie));
	cookie($cookie, $value);
	$_COOKIE[$cookie] = $value;
}

/** Restart stopped session */
function restart_session(): void {
	if (!ini_bool("session.use_cookies") && (!function_exists('session_status') || session_status() == 1)) { // 1 - PHP_SESSION_NONE, session_status() available since PHP 5.4
		session_start();
	}
}

/** Stop session if possible */
function stop_session(bool $force = false): void {
	$use_cookies = ini_bool("session.use_cookies");
	if (!$use_cookies || $force) {
		session_write_close(); // improves concurrency if a user opens several pages at once, may be restarted later
		if ($use_cookies && @ini_set("session.use_cookies", '0') === false) { // @ - may be disabled
			session_start();
		}
	}
}

/** Get session variable for current server
* @return mixed
*/
function &get_session(string $key) {
	return $_SESSION[$key][DRIVER][SERVER][$_GET["username"]];
}

/** Set session variable for current server
* @param mixed $val
* @return mixed
*/
function set_session(string $key, $val) {
	$_SESSION[$key][DRIVER][SERVER][$_GET["username"]] = $val; // used also in auth.inc.php
}

/** Get authenticated URL */
function auth_url(string $vendor, ?string $server, string $username, ?string $db = null): string {
	$uri = remove_from_uri(implode("|", array_keys(SqlDriver::$drivers))
		. "|username|ext|"
		. ($db !== null ? "db|" : "")
		. ($vendor == 'mssql' || $vendor == 'pgsql' ? "" : "ns|") // we don't have access to support() here
		. session_name())
	;
	preg_match('~([^?]*)\??(.*)~', $uri, $match);
	return "$match[1]?"
		. (sid() ? SID . "&" : "")
		. ($vendor != "server" || $server != "" ? urlencode($vendor) . "=" . urlencode($server) . "&" : "")
		. ($_GET["ext"] ? "ext=" . urlencode($_GET["ext"]) . "&" : "")
		. "username=" . urlencode($username)
		. ($db != "" ? "&db=" . urlencode($db) : "")
		. ($match[2] ? "&$match[2]" : "")
	;
}

/** Find whether it is an AJAX request */
function is_ajax(): bool {
	return ($_SERVER["HTTP_X_REQUESTED_WITH"] == "XMLHttpRequest");
}

/** Send Location header and exit
* @param ?string $location null to only set a message
*/
function redirect(?string $location, ?string $message = null): void {
	if ($message !== null) {
		restart_session();
		$_SESSION["messages"][preg_replace('~^[^?]*~', '', ($location !== null ? $location : $_SERVER["REQUEST_URI"]))][] = $message;
	}
	if ($location !== null) {
		if ($location == "") {
			$location = ".";
		}
		header("Location: $location");
		exit;
	}
}

/** Execute query and redirect if successful
* @param bool $redirect
*/
function query_redirect(string $query, ?string $location, string $message, $redirect = true, bool $execute = true, bool $failed = false, string $time = ""): bool {
	if ($execute) {
		$start = microtime(true);
		$failed = !connection()->query($query);
		$time = format_time($start);
	}
	$sql = ($query ? adminer()->messageQuery($query, $time, $failed) : "");
	if ($failed) {
		adminer()->error .= error() . $sql . script("messagesPrint();") . "<br>";
		return false;
	}
	if ($redirect) {
		redirect($location, $message . $sql);
	}
	return true;
}

class Queries {
	/** @var string[] */ static array $queries = array();
	static float $start = 0;
}

/** Execute and remember query
* @param string $query end with ';' to use DELIMITER
* @return Result|bool
*/
function queries(string $query) {
	if (!Queries::$start) {
		Queries::$start = microtime(true);
	}
	Queries::$queries[] = (preg_match('~;$~', $query) ? "DELIMITER ;;\n$query;\nDELIMITER " : $query) . ";";
	return connection()->query($query);
}

/** Apply command to all array items
* @param list<string> $tables
* @param callable(string):string $escape
*/
function apply_queries(string $query, array $tables, $escape = 'Adminer\table'): bool {
	foreach ($tables as $table) {
		if (!queries("$query " . $escape($table))) {
			return false;
		}
	}
	return true;
}

/** Redirect by remembered queries
* @param bool $redirect
*/
function queries_redirect(?string $location, string $message, $redirect): bool {
	$queries = implode("\n", Queries::$queries);
	$time = format_time(Queries::$start);
	return query_redirect($queries, $location, $message, $redirect, false, !$redirect, $time);
}

/** Format elapsed time
* @param float $start output of microtime(true)
* @return string HTML code
*/
function format_time(float $start): string {
	return lang(0, max(0, microtime(true) - $start));
}

/** Get relative REQUEST_URI */
function relative_uri(): string {
	return str_replace(":", "%3a", preg_replace('~^[^?]*/([^?]*)~', '\1', $_SERVER["REQUEST_URI"]));
}

/** Remove parameter from query string */
function remove_from_uri(string $param = ""): string {
	return substr(preg_replace("~(?<=[?&])($param" . (SID ? "" : "|" . session_name()) . ")=[^&]*&~", '', relative_uri() . "&"), 0, -1);
}

/** Get file contents from $_FILES
* @return mixed int for error, string otherwise
*/
function get_file(string $key, bool $decompress = false, string $delimiter = "") {
	$file = $_FILES[$key];
	if (!$file) {
		return null;
	}
	foreach ($file as $key => $val) {
		$file[$key] = (array) $val;
	}
	$return = '';
	foreach ($file["error"] as $key => $error) {
		if ($error) {
			return $error;
		}
		$name = $file["name"][$key];
		$tmp_name = $file["tmp_name"][$key];
		$content = file_get_contents(
			$decompress && preg_match('~\.gz$~', $name)
			? "compress.zlib://$tmp_name"
			: $tmp_name
		); //! may not be reachable because of open_basedir
		if ($decompress) {
			$start = substr($content, 0, 3);
			if (function_exists("iconv") && preg_match("~^\xFE\xFF|^\xFF\xFE~", $start)) { // not ternary operator to save memory
				$content = iconv("utf-16", "utf-8", $content);
			} elseif ($start == "\xEF\xBB\xBF") { // UTF-8 BOM
				$content = substr($content, 3);
			}
		}
		$return .= $content;
		if ($delimiter) {
			$return .= (preg_match("($delimiter\\s*\$)", $content) ? "" : $delimiter) . "\n\n";
		}
	}
	return $return;
}

/** Determine upload error */
function upload_error(int $error): string {
	$max_size = ($error == UPLOAD_ERR_INI_SIZE ? ini_get("upload_max_filesize") : 0); // post_max_size is checked in index.php
	return ($error ? lang(1) . ($max_size ? " " . lang(2, $max_size) : "") : lang(3));
}

/** Create repeat pattern for preg */
function repeat_pattern(string $pattern, int $length): string {
	// fix for Compilation failed: number too big in {} quantifier
	return str_repeat("$pattern{0,65535}", $length / 65535) . "$pattern{0," . ($length % 65535) . "}"; // can create {0,0} which is OK
}

/** Check whether the string is in UTF-8 */
function is_utf8(?string $val): bool {
	// don't print control chars except \t\r\n
	return (preg_match('~~u', $val) && !preg_match('~[\0-\x8\xB\xC\xE-\x1F]~', $val));
}

/** Format decimal number
* @param float|numeric-string $val
*/
function format_number($val): string {
	return strtr(number_format($val, 0, ".", lang(4)), preg_split('~~u', lang(5), -1, PREG_SPLIT_NO_EMPTY));
}

/** Generate friendly URL */
function friendly_url(string $val): string {
	// used for blobs and export
	return preg_replace('~\W~i', '-', $val);
}

/** Get status of a single table and fall back to name on error
* @return TableStatus one element from table_status()
*/
function table_status1(string $table, bool $fast = false): array {
	$return = table_status($table, $fast);
	return ($return ? reset($return) : array("Name" => $table));
}

/** Find out foreign keys for each column
* @return list<ForeignKey>[] [$col => []]
*/
function column_foreign_keys(string $table): array {
	$return = array();
	foreach (adminer()->foreignKeys($table) as $foreign_key) {
		foreach ($foreign_key["source"] as $val) {
			$return[$val][] = $foreign_key;
		}
	}
	return $return;
}

/** Compute fields() from $_POST edit data; used by Mongo and SimpleDB
* @return Field[] same as fields()
*/
function fields_from_edit(): array {
	$return = array();
	foreach ((array) $_POST["field_keys"] as $key => $val) {
		if ($val != "") {
			$val = bracket_escape($val);
			$_POST["function"][$val] = $_POST["field_funs"][$key];
			$_POST["fields"][$val] = $_POST["field_vals"][$key];
		}
	}
	foreach ((array) $_POST["fields"] as $key => $val) {
		$name = bracket_escape($key, true); // true - back
		$return[$name] = array(
			"field" => $name,
			"privileges" => array("insert" => 1, "update" => 1, "where" => 1, "order" => 1),
			"null" => 1,
			"auto_increment" => ($key == driver()->primary),
		);
	}
	return $return;
}

/** Send headers for export
* @return string extension
*/
function dump_headers(string $identifier, bool $multi_table = false): string {
	$return = adminer()->dumpHeaders($identifier, $multi_table);
	$output = $_POST["output"];
	if ($output != "text") {
		header("Content-Disposition: attachment; filename=" . adminer()->dumpFilename($identifier) . ".$return" . ($output != "file" && preg_match('~^[0-9a-z]+$~', $output) ? ".$output" : ""));
	}
	session_write_close();
	if (!ob_get_level()) {
		ob_start(null, 4096);
	}
	ob_flush();
	flush();
	return $return;
}

/** Print CSV row
* @param string[] $row
*/
function dump_csv(array $row): void {
	foreach ($row as $key => $val) {
		if (preg_match('~["\n,;\t]|^0.|\.\d*0$~', $val) || $val === "") {
			$row[$key] = '"' . str_replace('"', '""', $val) . '"';
		}
	}
	echo implode(($_POST["format"] == "csv" ? "," : ($_POST["format"] == "tsv" ? "\t" : ";")), $row) . "\r\n";
}

/** Apply SQL function
* @param string $column escaped column identifier
*/
function apply_sql_function(?string $function, string $column): string {
	return ($function ? ($function == "unixepoch" ? "DATETIME($column, '$function')" : ($function == "count distinct" ? "COUNT(DISTINCT " : strtoupper("$function(")) . "$column)") : $column);
}

/** Get path of the temporary directory */
function get_temp_dir(): string {
	$return = ini_get("upload_tmp_dir"); // session_save_path() may contain other storage path
	if (!$return) {
		if (function_exists('sys_get_temp_dir')) {
			$return = sys_get_temp_dir();
		} else {
			$filename = @tempnam("", ""); // @ - temp directory can be disabled by open_basedir
			if (!$filename) {
				return '';
			}
			$return = dirname($filename);
			unlink($filename);
		}
	}
	return $return;
}

/** Open and exclusively lock a file
* @return resource|void null for error
*/
function file_open_lock(string $filename) {
	if (is_link($filename)) {
		return; // https://cwe.mitre.org/data/definitions/61.html
	}
	$fp = @fopen($filename, "c+"); // @ - may not be writable
	if (!$fp) {
		return;
	}
	@chmod($filename, 0660); // @ - may not be permitted
	if (!flock($fp, LOCK_EX)) {
		fclose($fp);
		return;
	}
	return $fp;
}

/** Write and unlock a file
* @param resource $fp
*/
function file_write_unlock($fp, string $data): void {
	rewind($fp);
	fwrite($fp, $data);
	ftruncate($fp, strlen($data));
	file_unlock($fp);
}

/** Unlock and close a file
* @param resource $fp
*/
function file_unlock($fp): void {
	flock($fp, LOCK_UN);
	fclose($fp);
}

/** Get first element of an array
* @param mixed[] $array
* @return mixed if not found
*/
function first(array $array) {
	// reset(f()) triggers a notice
	return reset($array);
}

/** Read password from file adminer.key in temporary directory or create one
* @return string '' if the file can not be created
*/
function password_file(bool $create): string {
	$filename = get_temp_dir() . "/adminer.key";
	if (!$create && !file_exists($filename)) {
		return '';
	}
	$fp = file_open_lock($filename);
	if (!$fp) {
		return '';
	}
	$return = stream_get_contents($fp);
	if (!$return) {
		$return = rand_string();
		file_write_unlock($fp, $return);
	} else {
		file_unlock($fp);
	}
	return $return;
}

/** Get a random string
* @return string 32 hexadecimal characters
*/
function rand_string(): string {
	return md5(uniqid(strval(mt_rand()), true));
}

/** Format value to use in select
* @param string|string[] $val
* @param Field $field
* @param ?numeric-string $text_length
* @return string HTML
*/
function select_value($val, string $link, array $field, ?string $text_length): string {
	if (is_array($val)) {
		$return = "";
		foreach ($val as $k => $v) {
			$return .= "<tr>"
				. ($val != array_values($val) ? "<th>" . h($k) : "")
				. "<td>" . select_value($v, $link, $field, $text_length)
			;
		}
		return "<table>$return</table>";
	}
	if (!$link) {
		$link = adminer()->selectLink($val, $field);
	}
	if ($link === null) {
		if (is_mail($val)) {
			$link = "mailto:$val";
		}
		if (is_url($val)) {
			$link = $val; // IE 11 and all modern browsers hide referrer
		}
	}
	$return = adminer()->editVal($val, $field);
	if ($return !== null) {
		if (!is_utf8($return)) {
			$return = "\0"; // htmlspecialchars of binary data returns an empty string
		} elseif ($text_length != "" && is_shortable($field)) {
			$return = shorten_utf8($return, max(0, +$text_length)); // usage of LEFT() would reduce traffic but complicate query - expected average speedup: .001 s VS .01 s on local network
		} else {
			$return = h($return);
		}
	}
	return adminer()->selectVal($return, $link, $field, $val);
}

/** Check whether the field type is blob or equivalent
* @param Field $field
*/
function is_blob(array $field): bool {
	return preg_match('~blob|bytea|raw|file~', $field["type"]) && !in_array($field["type"], idx(driver()->structuredTypes(), lang(6), array()));
}

/** Check whether the string is e-mail address */
function is_mail(?string $email): bool {
	$atom = '[-a-z0-9!#$%&\'*+/=?^_`{|}~]'; // characters of local-name
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // one domain component
	$pattern = "$atom+(\\.$atom+)*@($domain?\\.)+$domain";
	return is_string($email) && preg_match("(^$pattern(,\\s*$pattern)*\$)i", $email);
}

/** Check whether the string is URL address */
function is_url(?string $string): bool {
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // one domain component //! IDN
	return preg_match("~^(https?)://($domain?\\.)+$domain(:\\d+)?(/.*)?(\\?.*)?(#.*)?\$~i", $string); //! restrict path, query and fragment characters
}

/** Check if field should be shortened
* @param Field $field
*/
function is_shortable(array $field): bool {
	return preg_match('~char|text|json|lob|geometry|point|linestring|polygon|string|bytea|hstore~', $field["type"]);
}

/** Split server into host and (port or socket)
* @return array{0: string, 1: string}
*/
function host_port(string $server) {
	return (preg_match('~^(\[(.+)]|([^:]+)):([^:]+)$~', $server, $match) // [a:b] - IPv6
		? array($match[2] . $match[3], $match[4])
		: array($server, '')
	);
}

/** Get query to compute number of found rows
* @param list<string> $where
* @param list<string> $group
*/
function count_rows(string $table, array $where, bool $is_group, array $group): string {
	$query = " FROM " . table($table) . ($where ? " WHERE " . implode(" AND ", $where) : "");
	return ($is_group && (JUSH == "sql" || count($group) == 1)
		? "SELECT COUNT(DISTINCT " . implode(", ", $group) . ")$query"
		: "SELECT COUNT(*)" . ($is_group ? " FROM (SELECT 1$query GROUP BY " . implode(", ", $group) . ") x" : $query)
	);
}

/** Run query which can be killed by AJAX call after timing out
* @return string[]
*/
function slow_query(string $query): array {
	$db = adminer()->database();
	$timeout = adminer()->queryTimeout();
	$slow_query = driver()->slowQuery($query, $timeout);
	$connection2 = null;
	if (!$slow_query && support("kill")) {
		$connection2 = connect();
		if ($connection2 && ($db == "" || $connection2->select_db($db))) {
			$kill = get_val(connection_id(), 0, $connection2); // MySQL and MySQLi can use thread_id but it's not in PDO_MySQL
			echo script("const timeout = setTimeout(() => { ajax('" . js_escape(ME) . "script=kill', function () {}, 'kill=$kill&token=" . get_token() . "'); }, 1000 * $timeout);");
		}
	}
	ob_flush();
	flush();
	$return = @get_key_vals(($slow_query ?: $query), $connection2, false); // @ - may be killed
	if ($connection2) {
		echo script("clearTimeout(timeout);");
		ob_flush();
		flush();
	}
	return $return;
}

/** Generate BREACH resistant CSRF token */
function get_token(): string {
	$rand = rand(1, 1e6);
	return ($rand ^ $_SESSION["token"]) . ":$rand";
}

/** Verify if supplied CSRF token is valid */
function verify_token(): bool {
	list($token, $rand) = explode(":", $_POST["token"]);
	return ($rand ^ $_SESSION["token"]) == $token;
}

// used in compiled version
function lzw_decompress(string $binary): string {
	// convert binary string to codes
	$dictionary_count = 256;
	$bits = 8; // ceil(log($dictionary_count, 2))
	$codes = array();
	$rest = 0;
	$rest_length = 0;
	for ($i=0; $i < strlen($binary); $i++) {
		$rest = ($rest << 8) + ord($binary[$i]);
		$rest_length += 8;
		if ($rest_length >= $bits) {
			$rest_length -= $bits;
			$codes[] = $rest >> $rest_length;
			$rest &= (1 << $rest_length) - 1;
			$dictionary_count++;
			if ($dictionary_count >> $bits) {
				$bits++;
			}
		}
	}
	// decompression
	/** @var list<?string> */
	$dictionary = range("\0", "\xFF");
	$return = "";
	$word = "";
	foreach ($codes as $i => $code) {
		$element = $dictionary[$code];
		if (!isset($element)) {
			$element = $word . $word[0];
		}
		$return .= $element;
		if ($i) {
			$dictionary[] = $word . $element[0];
		}
		$word = $element;
	}
	return $return;
}

?>
<?php
/** Return <script> element */
function script(string $source, string $trailing = "\n"): string {
	return "<script" . nonce() . ">$source</script>$trailing";
}

/** Return <script src> element */
function script_src(string $url, bool $defer = false): string {
	return "<script src='" . h($url) . "'" . nonce() . ($defer ? " defer" : "") . "></script>\n";
}

/** Get a nonce="" attribute with CSP nonce */
function nonce(): string {
	return ' nonce="' . get_nonce() . '"';
}

/** Get <input type="hidden">
* @param string|int $value
* @return string HTML
*/
function input_hidden(string $name, $value = ""): string {
	return "<input type='hidden' name='" . h($name) . "' value='" . h($value) . "'>\n";
}

/** Get CSRF <input type="hidden" name="token">
* @return string HTML
*/
function input_token(): string {
	return input_hidden("token", get_token());
}

/** Get a target="_blank" attribute */
function target_blank(): string {
	return ' target="_blank" rel="noreferrer noopener"';
}

/** Escape for HTML */
function h(?string $string): string {
	return str_replace("\0", "&#0;", htmlspecialchars($string, ENT_QUOTES, 'utf-8'));
}

/** Convert \n to <br> */
function nl_br(string $string): string {
	return str_replace("\n", "<br>", $string); // nl2br() uses XHTML before PHP 5.3
}

/** Generate HTML checkbox
* @param string|int $value
*/
function checkbox(string $name, $value, ?bool $checked, string $label = "", string $onclick = "", string $class = "", string $labelled_by = ""): string {
	$return = "<input type='checkbox' name='$name' value='" . h($value) . "'"
		. ($checked ? " checked" : "")
		. ($labelled_by ? " aria-labelledby='$labelled_by'" : "")
		. ">"
		. ($onclick ? script("qsl('input').onclick = function () { $onclick };", "") : "")
	;
	return ($label != "" || $class ? "<label" . ($class ? " class='$class'" : "") . ">$return" . h($label) . "</label>" : $return);
}

/** Generate list of HTML options
* @param string[]|string[][] $options array of strings or arrays (creates optgroup)
* @param mixed $selected
* @param bool $use_keys always use array keys for value="", otherwise only string keys are used
*/
function optionlist($options, $selected = null, bool $use_keys = false): string {
	$return = "";
	foreach ($options as $k => $v) {
		$opts = array($k => $v);
		if (is_array($v)) {
			$return .= '<optgroup label="' . h($k) . '">';
			$opts = $v;
		}
		foreach ($opts as $key => $val) {
			$return .= '<option'
				. ($use_keys || is_string($key) ? ' value="' . h($key) . '"' : '')
				. ($selected !== null && ($use_keys || is_string($key) ? (string) $key : $val) === $selected ? ' selected' : '')
				. '>' . h($val)
			;
		}
		if (is_array($v)) {
			$return .= '</optgroup>';
		}
	}
	return $return;
}

/** Generate HTML <select>
* @param string[] $options
*/
function html_select(string $name, array $options, ?string $value = "", string $onchange = "", string $labelled_by = ""): string {
	static $label = 0;
	$label_option = "";
	if (!$labelled_by && substr($options[""], 0, 1) == "(") {
		$label++;
		$labelled_by = "label-$label";
		$label_option = "<option value='' id='$labelled_by'>" . h($options[""]);
		unset($options[""]);
	}
	return "<select name='" . h($name) . "'"
		. ($labelled_by ? " aria-labelledby='$labelled_by'" : "")
		. ">" . $label_option . optionlist($options, $value) . "</select>"
		. ($onchange ? script("qsl('select').onchange = function () { $onchange };", "") : "")
	;
}

/** Generate HTML radio list
* @param string[] $options
*/
function html_radios(string $name, array $options, ?string $value = "", string $separator = ""): string {
	$return = "";
	foreach ($options as $key => $val) {
		$return .= "<label><input type='radio' name='" . h($name) . "' value='" . h($key) . "'" . ($key == $value ? " checked" : "") . ">" . h($val) . "</label>$separator";
	}
	return $return;
}

/** Get onclick confirmation */
function confirm(string $message = "", string $selector = "qsl('input')"): string {
	return script("$selector.onclick = () => confirm('" . ($message ? js_escape($message) : lang(7)) . "');", "");
}

/** Print header for hidden fieldset (close by </div></fieldset>)
* @param bool $visible
*/
function print_fieldset(string $id, string $legend, $visible = false): void {
	echo "<fieldset><legend>";
	echo "<a href='#fieldset-$id'>$legend</a>";
	echo script("qsl('a').onclick = partial(toggle, 'fieldset-$id');", "");
	echo "</legend>";
	echo "<div id='fieldset-$id'" . ($visible ? "" : " class='hidden'") . ">\n";
}

/** Return class='active' if $bold is true */
function bold(bool $bold, string $class = ""): string {
	return ($bold ? " class='active $class'" : ($class ? " class='$class'" : ""));
}

/** Escape string for JavaScript apostrophes */
function js_escape(string $string): string {
	return addcslashes($string, "\r\n'\\/"); // slash for <script>
}

/** Generate page number for pagination */
function pagination(int $page, ?int $current): string {
	return " " . ($page == $current
		? $page + 1
		: '<a href="' . h(remove_from_uri("page") . ($page ? "&page=$page" . ($_GET["next"] ? "&next=" . urlencode($_GET["next"]) : "") : "")) . '">' . ($page + 1) . "</a>"
	);
}

/** Print hidden fields
* @param mixed[] $process
* @param list<string> $ignore
*/
function hidden_fields(array $process, array $ignore = array(), string $prefix = ''): bool {
	$return = false;
	foreach ($process as $key => $val) {
		if (!in_array($key, $ignore)) {
			if (is_array($val)) {
				hidden_fields($val, array(), $key);
			} else {
				$return = true;
				echo input_hidden(($prefix ? $prefix . "[$key]" : $key), $val);
			}
		}
	}
	return $return;
}

/** Print hidden fields for GET forms */
function hidden_fields_get(): void {
	echo (sid() ? input_hidden(session_name(), session_id()) : '');
	echo (SERVER !== null ? input_hidden(DRIVER, SERVER) : "");
	echo input_hidden("username", $_GET["username"]);
}

/** Get <input type='file'> */
function file_input(string $input): string {
	$max_file_uploads = "max_file_uploads";
	$max_file_uploads_value = ini_get($max_file_uploads);
	$upload_max_filesize = "upload_max_filesize";
	$upload_max_filesize_value = ini_get($upload_max_filesize);
	return (ini_bool("file_uploads")
		? $input . script("qsl('input[type=\"file\"]').onchange = partialArg(fileChange, "
				. "$max_file_uploads_value, '" . lang(8, "$max_file_uploads = $max_file_uploads_value") . "', " // ignore post_max_size because it is for all form fields together and bytes computing would be necessary
				. ini_bytes("upload_max_filesize") . ", '" . lang(8, "$upload_max_filesize = $upload_max_filesize_value") . "')")
		: lang(9)
	);
}

/** Print enum or set input field
* @param 'radio'|'checkbox' $type
* @param Field $field
* @param string|string[]|false|null $value false means original value
*/
function enum_input(string $type, string $attrs, array $field, $value, string $empty = ""): string {
	preg_match_all("~'((?:[^']|'')*)'~", $field["length"], $matches);
	$prefix = ($field["type"] == "enum" ? "val-" : "");
	$checked = (is_array($value) ? in_array("null", $value) : $value === null);
	$return = ($field["null"] && $prefix ? "<label><input type='$type'$attrs value='null'" . ($checked ? " checked" : "") . "><i>$empty</i></label>" : "");
	foreach ($matches[1] as $val) {
		$val = stripcslashes(str_replace("''", "'", $val));
		$checked = (is_array($value) ? in_array($prefix . $val, $value) : $value === $val);
		$return .= " <label><input type='$type'$attrs value='" . h($prefix . $val) . "'" . ($checked ? ' checked' : '') . '>' . h(adminer()->editVal($val, $field)) . '</label>';
	}
	return $return;
}

/** Print edit input field
* @param Field|RoutineField $field
* @param mixed $value
*/
function input(array $field, $value, ?string $function, ?bool $autofocus = false): void {
	$name = h(bracket_escape($field["field"]));
	echo "<td class='function'>";
	if (is_array($value) && !$function) {
		$value = json_encode($value, 128 | 64 | 256); // 128 - JSON_PRETTY_PRINT, 64 - JSON_UNESCAPED_SLASHES, 256 - JSON_UNESCAPED_UNICODE available since PHP 5.4
		$function = "json";
	}
	$reset = (JUSH == "mssql" && $field["auto_increment"]);
	if ($reset && !$_POST["save"]) {
		$function = null;
	}
	$functions = (isset($_GET["select"]) || $reset ? array("orig" => lang(10)) : array()) + adminer()->editFunctions($field);
	$enums = driver()->enumLength($field);
	if ($enums) {
		$field["type"] = "enum";
		$field["length"] = $enums;
	}
	$disabled = stripos($field["default"], "GENERATED ALWAYS AS ") === 0 ? " disabled=''" : "";
	$attrs = " name='fields[$name]" . ($field["type"] == "enum" || $field["type"] == "set" ? "[]" : "") . "'$disabled" . ($autofocus ? " autofocus" : "");
	echo driver()->unconvertFunction($field) . " ";
	$table = $_GET["edit"] ?: $_GET["select"];
	if ($field["type"] == "enum") {
		echo h($functions[""]) . "<td>" . adminer()->editInput($table, $field, $attrs, $value);
	} else {
		$has_function = (in_array($function, $functions) || isset($functions[$function]));
		echo (count($functions) > 1
			? "<select name='function[$name]'$disabled>" . optionlist($functions, $function === null || $has_function ? $function : "") . "</select>"
				. on_help("event.target.value.replace(/^SQL\$/, '')", 1)
				. script("qsl('select').onchange = functionChange;", "")
			: h(reset($functions))
		) . '<td>';
		$input = adminer()->editInput($table, $field, $attrs, $value); // usage in call is without a table
		if ($input != "") {
			echo $input;
		} elseif (preg_match('~bool~', $field["type"])) {
			echo "<input type='hidden'$attrs value='0'>"
				. "<input type='checkbox'" . (preg_match('~^(1|t|true|y|yes|on)$~i', $value) ? " checked='checked'" : "") . "$attrs value='1'>";
		} elseif ($field["type"] == "set") {
			echo enum_input("checkbox", $attrs, $field, (is_string($value) ? explode(",", $value) : $value));
		} elseif (is_blob($field) && ini_bool("file_uploads")) {
			echo "<input type='file' name='fields-$name'>";
		} elseif ($function == "json" || preg_match('~^jsonb?$~', $field["type"])) {
			echo "<textarea$attrs cols='50' rows='12' class='jush-js'>" . h($value) . '</textarea>';
		} elseif (($text = preg_match('~text|lob|memo~i', $field["type"])) || preg_match("~\n~", $value)) {
			if ($text && JUSH != "sqlite") {
				$attrs .= " cols='50' rows='12'";
			} else {
				$rows = min(12, substr_count($value, "\n") + 1);
				$attrs .= " cols='30' rows='$rows'";
			}
			echo "<textarea$attrs>" . h($value) . '</textarea>';
		} else {
			// int(3) is only a display hint
			$types = driver()->types();
			$maxlength = (!preg_match('~int~', $field["type"]) && preg_match('~^(\d+)(,(\d+))?$~', $field["length"], $match)
				? ((preg_match("~binary~", $field["type"]) ? 2 : 1) * $match[1] + ($match[3] ? 1 : 0) + ($match[2] && !$field["unsigned"] ? 1 : 0))
				: ($types[$field["type"]] ? $types[$field["type"]] + ($field["unsigned"] ? 0 : 1) : 0)
			);
			if (JUSH == 'sql' && min_version(5.6) && preg_match('~time~', $field["type"])) {
				$maxlength += 7; // microtime
			}
			// type='date' and type='time' display localized value which may be confusing, type='datetime' uses 'T' as date and time separator
			echo "<input"
				. ((!$has_function || $function === "") && preg_match('~(?<!o)int(?!er)~', $field["type"]) && !preg_match('~\[\]~', $field["full_type"]) ? " type='number'" : "")
				. " value='" . h($value) . "'" . ($maxlength ? " data-maxlength='$maxlength'" : "")
				. (preg_match('~char|binary~', $field["type"]) && $maxlength > 20 ? " size='" . ($maxlength > 99 ? 60 : 40) . "'" : "")
				. "$attrs>"
			;
		}
		echo adminer()->editHint($table, $field, $value);
		// skip 'original'
		$first = 0;
		foreach ($functions as $key => $val) {
			if ($key === "" || !$val) {
				break;
			}
			$first++;
		}
		if ($first && count($functions) > 1) {
			echo script("qsl('td').oninput = partial(skipOriginal, $first);");
		}
	}
}

/** Process edit input field
* @param Field|RoutineField $field
* @return mixed false to leave the original value
*/
function process_input(array $field) {
	if (stripos($field["default"], "GENERATED ALWAYS AS ") === 0) {
		return;
	}
	$idf = bracket_escape($field["field"]);
	$function = idx($_POST["function"], $idf);
	$value = idx($_POST["fields"], $idf);
	if ($field["type"] == "enum" || driver()->enumLength($field)) {
		$value = $value[0];
		if ($value == "orig") {
			return false;
		}
		if ($value == "null") {
			return "NULL";
		}
		$value = substr($value, 4); // 4 - strlen("val-")
	}
	if ($field["auto_increment"] && $value == "") {
		return null;
	}
	if ($function == "orig") {
		return (preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"]) ? idf_escape($field["field"]) : false);
	}
	if ($function == "NULL") {
		return "NULL";
	}
	if ($field["type"] == "set") {
		$value = implode(",", (array) $value);
	}
	if ($function == "json") {
		$function = "";
		$value = json_decode($value, true);
		if (!is_array($value)) {
			return false; //! report errors
		}
		return $value;
	}
	if (is_blob($field) && ini_bool("file_uploads")) {
		$file = get_file("fields-$idf");
		if (!is_string($file)) {
			return false; //! report errors
		}
		return driver()->quoteBinary($file);
	}
	return adminer()->processInput($field, $value, $function);
}

/** Print results of search in all tables
* @uses $_GET["where"][0]
* @uses $_POST["tables"]
*/
function search_tables(): void {
	$_GET["where"][0]["val"] = $_POST["query"];
	$sep = "<ul>\n";
	foreach (table_status('', true) as $table => $table_status) {
		$name = adminer()->tableName($table_status);
		if (isset($table_status["Engine"]) && $name != "" && (!$_POST["tables"] || in_array($table, $_POST["tables"]))) {
			$result = connection()->query("SELECT" . limit("1 FROM " . table($table), " WHERE " . implode(" AND ", adminer()->selectSearchProcess(fields($table), array())), 1));
			if (!$result || $result->fetch_row()) {
				$print = "<a href='" . h(ME . "select=" . urlencode($table) . "&where[0][op]=" . urlencode($_GET["where"][0]["op"]) . "&where[0][val]=" . urlencode($_GET["where"][0]["val"])) . "'>$name</a>";
				echo "$sep<li>" . ($result ? $print : "<p class='error'>$print: " . error()) . "\n";
				$sep = "";
			}
		}
	}
	echo ($sep ? "<p class='message'>" . lang(11) : "</ul>") . "\n";
}

/** Return events to display help on mouse over
* @param string $command JS expression
* @param int $side 0 top, 1 left
*/
function on_help(string $command, int $side = 0): string {
	return script("mixin(qsl('select, input'), {onmouseover: function (event) { helpMouseover.call(this, event, $command, $side) }, onmouseout: helpMouseout});", "");
}

/** Print edit data form
* @param Field[] $fields
* @param mixed $row
*/
function edit_form(string $table, array $fields, $row, ?bool $update, string $error = ''): void {
	$table_name = adminer()->tableName(table_status1($table, true));
	page_header(
		($update ? lang(12) : lang(13)),
		$error,
		array("select" => array($table, $table_name)),
		$table_name
	);
	adminer()->editRowPrint($table, $fields, $row, $update);
	if ($row === false) {
		echo "<p class='error'>" . lang(14) . "\n";
		return;
	}
	echo "<form action='' method='post' enctype='multipart/form-data' id='form'>\n";
	if (!$fields) {
		echo "<p class='error'>" . lang(15) . "\n";
	} else {
		echo "<table class='layout'>" . script("qsl('table').onkeydown = editingKeydown;");
		$autofocus = !$_POST;
		foreach ($fields as $name => $field) {
			echo "<tr><th>" . adminer()->fieldName($field);
			$default = idx($_GET["set"], bracket_escape($name));
			if ($default === null) {
				$default = $field["default"];
				if ($field["type"] == "bit" && preg_match("~^b'([01]*)'\$~", $default, $regs)) {
					$default = $regs[1];
				}
				if (JUSH == "sql" && preg_match('~binary~', $field["type"])) {
					$default = bin2hex($default); // same as UNHEX
				}
			}
			$value = ($row !== null
				? ($row[$name] != "" && JUSH == "sql" && preg_match("~enum|set~", $field["type"]) && is_array($row[$name])
					? implode(",", $row[$name])
					: (is_bool($row[$name]) ? +$row[$name] : $row[$name])
				)
				: (!$update && $field["auto_increment"]
					? ""
					: (isset($_GET["select"]) ? false : $default)
				)
			);
			if (!$_POST["save"] && is_string($value)) {
				$value = adminer()->editVal($value, $field);
			}
			$function = ($_POST["save"]
				? idx($_POST["function"], $name, "")
				: ($update && preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"])
					? "now"
					: ($value === false ? null : ($value !== null ? '' : 'NULL'))
				)
			);
			if (!$_POST && !$update && $value == $field["default"] && preg_match('~^[\w.]+\(~', $value)) {
				$function = "SQL";
			}
			if (preg_match("~time~", $field["type"]) && preg_match('~^CURRENT_TIMESTAMP~i', $value)) {
				$value = "";
				$function = "now";
			}
			if ($field["type"] == "uuid" && $value == "uuid()") {
				$value = "";
				$function = "uuid";
			}
			if ($autofocus !== false) {
				$autofocus = ($field["auto_increment"] || $function == "now" || $function == "uuid" ? null : true); // null - don't autofocus this input but check the next one
			}
			input($field, $value, $function, $autofocus);
			if ($autofocus) {
				$autofocus = false;
			}
			echo "\n";
		}
		if (!support("table") && !fields($table)) {
			echo "<tr>"
				. "<th><input name='field_keys[]'>"
				. script("qsl('input').oninput = fieldChange;")
				. "<td class='function'>" . html_select("field_funs[]", adminer()->editFunctions(array("null" => isset($_GET["select"]))))
				. "<td><input name='field_vals[]'>"
				. "\n"
			;
		}
		echo "</table>\n";
	}
	echo "<p>\n";
	if ($fields) {
		echo "<input type='submit' value='" . lang(16) . "'>\n";
		if (!isset($_GET["select"])) {
			echo "<input type='submit' name='insert' value='" . ($update
				? lang(17)
				: lang(18)
			) . "' title='Ctrl+Shift+Enter'>\n";
			echo ($update ? script("qsl('input').onclick = function () { return !ajaxForm(this.form, '" . lang(19) . "â€¦', this); };") : "");
		}
	}
	echo ($update ? "<input type='submit' name='delete' value='" . lang(20) . "'>" . confirm() . "\n" : "");
	if (isset($_GET["select"])) {
		hidden_fields(array("check" => (array) $_POST["check"], "clone" => $_POST["clone"], "all" => $_POST["all"]));
	}
	echo input_hidden("referer", (isset($_POST["referer"]) ? $_POST["referer"] : $_SERVER["HTTP_REFERER"]));
	echo input_hidden("save", 1);
	echo input_token();
	echo "</form>\n";
}

/** Shorten UTF-8 string
* @return string escaped string with appended ...
*/
function shorten_utf8(string $string, int $length = 80, string $suffix = ""): string {
	if (!preg_match("(^(" . repeat_pattern("[\t\r\n -\x{10FFFF}]", $length) . ")($)?)u", $string, $match)) { // ~s causes trash in $match[2] under some PHP versions, (.|\n) is slow
		preg_match("(^(" . repeat_pattern("[\t\r\n -~]", $length) . ")($)?)", $string, $match);
	}
	return h($match[1]) . $suffix . (isset($match[2]) ? "" : "<i>â€¦</i>");
}

/** Get button with icon */
function icon(string $icon, string $name, string $html, string $title): string {
	return "<button type='submit' name='$name' title='" . h($title) . "' class='icon icon-$icon'><span>$html</span></button>";
}


// used only in compiled file
if (isset($_GET["file"])) {
	?>
<?php
if (substr(VERSION, -4) != '-dev') {
	if ($_SERVER["HTTP_IF_MODIFIED_SINCE"]) {
		header("HTTP/1.1 304 Not Modified");
		exit;
	}
	header("Expires: " . gmdate("D, d M Y H:i:s", time() + 365*24*60*60) . " GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: immutable");
}

@ini_set("zlib.output_compression", '1'); // @ - may be disabled

if ($_GET["file"] == "default.css") {
	header("Content-Type: text/css; charset=utf-8");
	echo lzw_decompress("h:M‡±h´ÄgÌĞ±ÜÍŒ\"PÑiÒm„™cQCa¤é	2Ã³éˆŞd<Ìfóa¼ä:;NBˆqœR;1Lf³9ÈŞu7&)¤l;3ÍÑñÈÀJ/‹†CQXÊr2MÆaäi0›„ƒ)°ìe:LuÃhæ-9ÕÍ23lÈÎi7†³màZw4™†Ñš<-•ÒÌ´¹!†U,—ŒFÃ©”vt2‘S,¬äa´Ò‡FêVXúa˜Nqã)“-—ÖÎÇœhê:n5û9ÈY¨;jµ”-Ş÷_‘9krùœÙ“;.ĞtTqËo¦0‹³­Öò®{íóyùı\rçHnìGS™ Zh²œ;¼i^ÀuxøWÎ’C@Äö¤©k€Ò=¡Ğb©Ëâì¼/AØà0¤+Â(ÚÁ°lÂÉÂ\\ê Ãxè:\rèÀb8\0æ–0!\0FÆ\nB”Íã(Ò3 \r\\ºÛêÈ„a¼„œ'Iâ|ê(iš\n‹\r©¸ú4Oüg@4ÁC’î¼†º@@†!ÄQB°İ	Â°¸c¤ÊÂ¯Äq,\r1EhèÈ&2PZ‡¦ğiGûH9G’\"v§ê’¢££¤œ4r”ÆñÍDĞR¤\n†pJë-A“|/.¯cê“Du·£¤ö:,˜Ê=°¢RÅ]U5¥mVÁkÍLLQ@-\\ª¦ËŒ@9Áã%ÚSrÁÎñMPDãÂIa\rƒ(YY\\ã@XõpÃê:£p÷lLC —Åñè¸ƒÍÊO,\rÆ2]7œ?m06ä»pÜTÑÍaÒ¥Cœ;_Ë—ÑyÈ´d‘>¨²bnğ…«n¼Ü£3÷X¾€ö8\rí[Ë€-)Ûi>V[Yãy&L3¯#ÌX|Õ	†X \\Ã¹`ËC§ç˜å#ÑÙHÉÌ2Ê2.# ö‹Zƒ`Â<¾ãs®·¹ªÃ’£º\0uœhÖ¾—¥M²Í_\niZeO/CÓ’_†`3İòğ1>‹=Ğk3£…‰R/;ä/dÛÜ\0ú‹ŒãŞÚµmùúò¾¤7/«ÖAÎXƒÂÿ„°“Ãq.½sáL£ı— :\$ÉF¢—¸ª¾£‚w‰8óß¾~«HÔj…­\"¨¼œ•¹Ô³7gSõä±âFLéÎ¯çQò_¤’O'WØö]c=ı5¾1X~7;˜™iş´\rí*\n’¨JS1Z¦™ø£ØÆßÍcå‚tœüAÔVí86fĞdÃy;Y]©õzIÀp¡Ñû§ğc‰3®YË]}Â˜@¡\$.+”1¶'>ZÃcpdàéÒGLæá„#kô8PzœYÒAuÏvİ]s9‰ÑØ_AqÎÁ„:†ÆÅ\nK€hB¼;­ÖŠXbAHq,âCIÉ`†‚çj¹S[ËŒ¶1ÆVÓrŠñÔ;¶pŞBÃÛ)#é‰;4ÌHñÒ/*Õ<Â3L Á;lfª\n¶s\$K`Ğ}ÆôÕ”£¾7ƒjx`d–%j] ¸4œ—Y¤–HbY ØJ`¤GG ’.ÅÜK‚òfÊI©)2ÂŠMfÖ¸İX‰RC‰¸Ì±V,©ÛÑ~g\0è‚àg6İ:õ[jí1H½:AlIq©u3\"™êæq¤æ|8<9s'ãQ]JÊ|Ğ\0Â`p ³îƒ«‰jf„OÆbĞÉú¬¨q¬¢\$é©²Ã1J¹>RœH(Ç”q\n#rŠ’à@e(yóVJµ0¡QÒˆ£òˆ6†Pæ[C:·Gä¼‘ İ4©‘Ò^ÓğÃPZŠµ\\´‘è(\nÖ)š~¦´°9R%×Sj·{‰7ä0Ş_šÇs	z|8ÅHê	\"@Ü#9DVLÅ\$H5ÔWJ@—…z®a¿J Ä^	‘)®2\nQvÀÔ]ëÇ†ÄÁ˜‰j (A¸Ó°BB05´6†bË°][ŒèkªA•wvkgôÆ´öºÕ+k[jm„zc¶}èMyDZií\$5e˜«Ê·°º	”A˜ CY%.W€b*ë®¼‚.­Ùóq/%}BÌXˆ­çZV337‡Ê»a™„€ºòŞwW[áLQÊŞ²ü_È2`Ç1IÑi,÷æ›£’Mf&(s-˜ä˜ëÂAÄ°Ø*””DwØÄTNÀÉ»ÅjX\$éxª+;ĞğËFÚ93µJkÂ™S;·§ÁqR{>l;B1AÈIâb) (6±­r÷\rİ\rÚ‡’Ú‚ìZ‘R^SOy/“ŞM#ÆÏ9{k„àê¸v\"úKCâJƒ¨rEo\0øÌ\\,Ñ|faÍš†³hI“©/oÌ4Äk^pî1HÈ^“ÍphÇ¡VÁvox@ø`ígŸ&(ùˆ­ü;›ƒ~ÇzÌ6×8¯*°ÆÜ5®Ü‰±E ÁÂp†éâîÓ˜˜¤´3“öÅ†gŸ™rDÑLó)4g{»ˆä½å³©—Lš&ú>è„»¢ØÚZì7¡\0ú°ÌŠ@×ĞÓÛœffÅRVhÖ²çIŠÛˆ½âğrÓw)‹ ‚„=x^˜,k’Ÿ2ôÒİ“jàbël0uë\"¬fp¨¸1ñRI¿ƒz[]¤wpN6dIªzëõån.7X{;ÁÈ3ØË-I	‹âûü7pjÃ¢R#ª,ù_-ĞüÂ[ó>3À\\æêÛWqŞq”JÖ˜uh£‡ĞFbLÁKÔåçyVÄ¾©¦ÃŞÑ•®µªüVœîÃf{K}S ÊŞ…‰Mş‡·Í€¼¦.M¶\\ªix¸bÁ¡1‡+£Î±?<Å3ê~HıÓ\$÷\\Ğ2Û\$î eØ6tÔOÌˆã\$s¼¼©xÄşx•ó§CánSkVÄÉ=z6½‰¡Ê'Ã¦äNaŸ¢Ö¸hŒÜü¸º±ı¯R¤å™£8g‰¢äÊw:_³î­íÿêÒ’IRKÃ¨.½nkVU+dwj™§%³`#,{é†³ËğÊƒY‡ı×õ(oÕ¾Éğ.¨c‚0gâDXOk†7®èKäÎlÒÍhx;ÏØ İƒLû´\$09*–9 ÜhNrüMÕ.>\0ØrP9ï\$Èg	\0\$\\Fó*²d'ÎõLå:‹bú—ğ42Àô¢ğ9Àğ@ÂHnbì-¤óE #ÄœÉÃ êrPY‚ê¨ tÍ Ø\nğ5.©àÊâî\$op l€X\n@`\r€	àˆ\r€Ğ Î ¦ ’ ‚	 ÊàêğÚ Î	@Ú@Ú\n ƒ †	\0j@ƒQ@™1\rÀ‚@“ ¢	\$p	 V\0ò``\n\0¨\n Ğ\n@¨' ìÀ¤\n\0`\rÀÚ ¬	à’\rà¤ ´\0Ğr°æÀò	\0„`‚	àî {	,\"¨È^PŸ0¥\n¬4±\n0·¤ˆ.0ÃpËğÓ\rpÛ\rğãpëğópûñqñQ0ß%€ÑÑ1Q8\n Ô\0ôkÊÈ¼\0^—àÒ\0`àÚ@´àÈ>\nÑo1w±,Y	h*=Š¡P¦:Ñ–VƒïĞ¸.q£ÅÍ\rÕ\r‘péĞñ1ÁÑQ	ÑÑ1× ƒ`Ññ/17±ëñò\r ^Àä\"y`\nÀ Œ# ˜\0ê	 p\n€ò\n€š`Œ ˆr ”Q†ğ¦bç1Ò3\n°¯#°µ#ğ¼1¥\$q«\$Ñ±%0å%q½%Ğù&Ç&qÍ ƒ&ñ'1Ú\rR}16	 ï@b\r`µ`Ü\rÀˆ	€ŞÀÌ€dàª€¨	j\n¯``À†\n€œ`dcÑP–€,ò1R×Ÿ\$¿rIÒO ‚	Q	òY32b1É&‘Ï01ÓÑÙ ’Ó fÀÏ\0ª\0¤ Îf€\0j\n f`â	 ®\n`´@˜\$n=`†\0ÈÒv nIĞ\$ÿP(Âd'Ëğô„Äà·gÉ6‘™-Šƒ-ÒC7Rçà‡ —	4à ô-1Ë&±Ñ2t\rô\"\n 	H*@	ˆ`\n ¤ è	àòlÕ2¿,z\rì~È è\r—Fìth‰Šö€Ø ëmõäÄì´z”~¡\0]GÌF\\¥×I€\\¥£}ItC\nÁT„}ªØ×IEJ\rx×ÉûÂ>ÙMp‹„IHô~êäfht„ë¯.b…—xYEìiK´ªoj\nğíÅLÀŞtr×.À~d»H‡2U4©Gà\\Aê‚ç4ş„uPtŞÃÕ½è° òàÍL/¿P×	\"G!RîÎMtŸO-Ìµ<#õAPuI‡ëRè\$“c’¹ÃD‹ÆŠ €§¢-‚ÃGâ´O`Pv§^W@tH;Q°µRÄ™Õ\$´©gKèF<\rR*\$4®' ó¨ĞÈÊ[í°ÛIªó­UmÑÆh:+ş¼5@/­l¾I¾ªí2¦‚^\0ODøšª¬Ø\rR'Â\rèTĞ­[êÖ÷ÄÄª®«MCëMÃZ4æE B\"æ`ö‚´euNí,ä™¬é]Ïğtú\rª`Ü@hö*\r¶.Vƒ–%Ú!MBlPF™Ï\"Øï&Õ/@îv\\CŞï©:mMgnò®öÊi8˜I2\rpívjí©Æ÷ï+Z mT©ueõÕfv>f´Ğ˜Ö`DU[ZTÏVĞCàµTğ\r–¹Uv‹kõ^×¦øLëÙb/¾K¶Sev2÷ubvÇOVDğÖImÕ\$ò%ÖX?udç!W•|,\rø+îµcnUe×ZÆÄÊ–€şöë-~X¯ºûîÀêÔöBGd¶\$i¶çMv!t#Lì3o·UI—O—u?ZweRÏ ëcwª. `È¡iøñ\rb§% ú");
} elseif ($_GET["file"] == "dark.css") {
	header("Content-Type: text/css; charset=utf-8");
	echo lzw_decompress("h:M‡±h´ÄgÆÈh0ÁLĞàd91¢S!¤Û	Fƒ!°æ\"-6N‘€ÄbdGgÓ°Â:;Nr£)öc7›\rç(HØb81˜†s9¼¤Ük\rçc)Êm8O•VA¡Âc1”c34Of*’ª- P¨‚1©”r41Ùî6˜Ìd2ŒÖ•®Ûo½ÜÌ#3—‰–BÇf#	ŒÖg9Î¦êØŒfc\rÇI™ĞÂb6E‡C&¬Ğ,buÄêm7aVã•ÂÁs²#m!ôèhµårùœŞv\\3\rL:SA”Âdk5İnÇ·×ìšıÊaF†¸3é˜Òe6fS¦ëy¾óør!ÇLú -ÎK,Ì3Lâ@º“J¶ƒË²¢*J äìµ£¤‚»	¸ğ—¹Ášb©cèà9­ˆê9¹¤æ@ÏÔè¿ÃHÜ8£ \\·Ãê6>«`ğÅ¸Ş;‡Aˆà<T™'¨p&q´qEˆê4Å\rl­…ÃhÂ<5#pÏÈR Ñ#I„İ%„êfBIØŞÜ²”¨>…Ê«29<«åCîj2¯î»¦¶7j¬“8jÒìc(nÔÄç?(a\0Å@”5*3:Î´æ6Œ£˜æ0Œã-àAÀlL›•PÆ4@ÊÉ°ê\$¡H¥4 n31¶æ1Ítò0®áÍ™9ŒƒéWO!¨r¼ÚÔØÜÛÕèHÈ†£Ã9ŒQ°Â96èF±¬«<ø7°\rœ-xC\n Üã®@Òø…ÜÔƒ:\$iÜØ¶m«ªË4íKid¬²{\n6\r–…xhË‹â#^'4Vø@aÍÇ<´#h0¦Sæ-…c¸Ö9‰+pŠ«Ša2Ôcy†h®BO\$Áç9öw‡iX›É”ùVY9*r÷Htm	@bÖÑ|@ü/€l’\$z¦­ +Ô%p2l‹˜É.õØúÕÛìÄ7ï;Ç&{ÀËm„€X¨C<l9ğí6x9ïmìò¤ƒ¯À­7RüÀ0\\ê4Î÷PÈ)AÈoÀx„ÄÚqÍO#¸¥Èf[;»ª6~PÛ\rŒa¸ÊTGT0„èìu¸ŞŸ¾³Ş\n3ğ\\ \\ÊƒJ©udªCGÀ§©PZ÷>“³Áûd8ÖÒ¨èéñ½ïåôC?V…·dLğÅL.(tiƒ’­>«,ôƒÖLÀ");
} elseif ($_GET["file"] == "functions.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo lzw_decompress("':œÌ¢™Ğäi1ã°P(^*æS €Ìi9ADSa”Úe7ELG˜\$pË	7œQÀÂr0›`cI¸Ï+KeólT&“±Øü„º 2LÆ©°ès\rôƒyŒëE:N(¡ÔänOè48ô‚²*‚ŒÇSt\$Òo°NbˆvPrw¡”ğtˆ` Mpé^°\n/rôTø|ªU«‘qÄêe9Jrk½Ó9R9\na‡Øl>#Š›1©5`A#’İ´©tÂeƒÍ¶“¹”úB¢Y¨ôš]6ŸQ©™*µ{5k\rˆ°ïì•‰]¦×m:[î'3f{e)¼âï·ü‹Ö*ÑÈÃŞ“è<7Ìn‹	Ï¯ÆıE¸áÌ\\¦Ã Ğ¡\0b.´m* ‰\"\0Â6\rÒÊª\r‚êø¥-Ûl™·)ºXÚ§òÆà¨ÊB”¦)Ê‚¤È¹‰œ®¿\"pŞ¤‰ƒJ\nê­KbÜ¸=£›ß®ï›ò0O¼`Ä¼Lh@ÇÅŒ›*Ë³,Û¾90ƒà»¾Í\"ŠR<‹GNÌx1Áãbj3¢Ë\0î4ÀJˆĞÒR^3Åª|4:ñØİ;¦Atı°ïÌó1MSd8+ˆä;£\$İG´6®ÌÃsÊÂ=¬æõ„ä˜!pÂ8`ò\rÊhØ¼Îs¨æÊ;ˆ@Ê/³/RQàQM5İ°S55QUmXC=f†#ì-¢2í‡/×«ÉLóHA5Í°‹£(¬@¯£”7Q×ÁqŠÿYµ”ù0;Õï?Ô¡lÛô5)D³eÚ4RDs|Ç”¨ÓÔC=1MH¯:àSáÙQT–5SUÕ°…™:+|kàDç`4ø¶(ïdçhXWøİYPç‘äÖ¦ÓT#˜æ4Œë\0í²ê€Ì9\rí¸Ş¯hV:Nh¥î'ŒCSã«ë:Ş€\r£HğšÙ:¨è¼zxå¨Ø2R1Xº*5Œ©\"j‚m[f*ê–j(-îƒÎc¼j/ºÁ#I-hÉGmêàÚ7Ñ\0†)ŠkÓU¡^ë	¬éÍÏL÷¸¶1\rãz:0Âí#0Òp}Ò!ókš8ğö¢rb2¯)\0Â1#¶\0Ò3næXÃk ¯àİß£¡\0~ã„×©Érƒ(N.±]Ïw]qpR\"*\rã8ÏèÑ°Ò1\r30è<ŞíÇJ–?QÕ½b,ƒÈ¯a‹½ô¾µnC\"ÀS§é#ĞP	ÁØ`‚+¢80&?O%å£`èŠ“ê#°=„8ò@	ÕÓùç¨2³ôf×1˜\r®°³§RÃHe~¤Ğİ!óx¼J¸b3	=C`ß¹«hm¼†@ÂU›q8¾ÀÔâÁ\0D%õ_­¨Aq‘j.Eğ\\È¤dVæ	Äè ®Q’,Ï&Ã˜š!`‚d,‡€àF(s°H¤E¸Zœb\nÆaá’B¬¨á«l)Åu(Â2¾¡ò~ĞõÑCù6šxw,T)Ã^jáÔ™'’~\"(@A%¤l‹’åÀÅâ¸HKÉ^Uç!º1CÌ^â<9ïZI™€¿%šp À—•r´ [Ã6ÄöGêòJä†:ešÍ'w\n„Ş#ñ…µÀĞN tÌğ^ç‚¦“9âRÁ|È;@¼Èùn\$ ‚sø³N–îÁu#á””•Ô{…š`º¬Òj+D6ˆ˜IÅ˜ci‹p›N`ÜÈ]¡Tù‘Ğ\\šÃ\"m=€œ—êRhüa¥±7Ñ¢+LÁˆ.@ÔSzrACÍ.ˆÙTÄúL\\òĞzÁ\"¨ÂBóì”ä}¡Ñ3HİV§àù½¼7‹X§±íÜÓ9Ñ:§dîŞyOJÒáC{bÁ¸C0èĞìŸ&bd†àz\np+ ¼âN\n-hŠ…],Ì\n+õ€e‹`D‰‡<É¬ÆŸS*BYĞĞ<Î²«0† ÂDº ORpá@-n¬\nıV@öë½²Ql0Û0N	Š”½ 0Nä’Vi\rĞ¸0¬+¢åÁ¹…x@†Â`d},¾dEZ8l2à€7¼D€èIÒsÓl‚\0y{ë#o„Ô8Pè—ñù•}Ø#ËŞšB·½ï	â€C{ã	ŸQ¡%væY\n°…é2ëBè@»Ş£`n\röÜí0·„0ødÂ…\$<8–j~ABW/¡‘SN’¯ôØh¤ÀöU p\$ï¨ç§ŠqŞ	{&Äø¨Ø@}aq–R6\n<C[\rCA«KáÏ!<‰ˆ¢hyä}Î\$I|Ÿq:¥Ô_”D°¸ı:—Vğ\$‚Ì*Uë!.ó\nnY®j/Ëèg±ú…èló\rÆ ’°pÓ89Ğ‚ğgBdòŸá^wD~ò­KÎ¥	|5¤å%€¦Ü¹È¾`«ËÅİæTFè!ÚĞ™´—ÇÜâĞ¤Á9…WÏ3ÊÕ&mK6}bNÜ¨Õå€ïÏğb\rm²³ùyĞ/Ç‘²	\n2)8sLŸ’9”A0/I¼1·Eò¢V¶	˜Rş‡KèYÎ¶]áÊï’p×Ÿô|\"¤î\0é¶6`' Úa–”ã0í\"RÉ/RÀoåXGİª5]YwÛƒ‘2Óœ|”†Ğ¶õPƒßÚornPüö-©›vPA;\0OÈDÀ¨Í“ËU2AÅBk‚ 	äœ‡“¦^b\\Qßï’D^`ŒüPŠ­\r±	u^Ü˜Bql#ÁÏ\\¿y;&¡â'\$@¦æ—`s3h€Ùç˜‹{ÂO\$7Œ22Ø`¬b	Ğ6Ø^[cUµê=‡¦	ŞÃÚú>	ø›ëîÙ\\©­Dğ¸@?°áp2°gX\$±aŸmŞë'r½Ğ”ÆÇ¨\naBºp\\ö]x!! ­x¡±–rá4Cvõ½÷Mß“¸ÀlÜqF¨ŒÃ()³v^ş_è@r‹¶Ô16féÇÏƒõ-<@{<µ?¦s2çô›¥Õu6ıİ¨;¡et(L[ªÖªÍhÌ‰T&MÑ`à+Ft:\"‚Ş‹üŞíŒGZÿMşÌo²b¯Œn.BBdëÆt¡.J±¬ÎŠ#Ì§\r<Œş#À]´¯CZy-ØİÈ.4İÆhoù°Z\r«*şé\"0,ÿ„\"i€î*î†¨ıÇ4‰@zPrz\0ÊL\0îÉ]	¢;Ëx<Ğ6ù”ëÏšáP©ı\n‚¢€t\\‚\n«\0~%û¯Îe‚\räê†\0ª-°j „Îz/üÍ	<í0&XKprï=(÷È\"¶o‚03¯Şº‰¸xÍĞ5€ëc0ùmÖâÅ\"¡\0*K\0bA\0©RìâxsĞ ŞÍ‹\0àA(üÎ‘:ë‘\n\"ÎKÅ¼oÔ/0`b¢:\"°sª:0¾Ğh.ëĞåNC‡AÎ’²ğT/­>y‚äo¥¨¯Q¸`VfîÅåµ€Èİ¤>'4êLA	î	®Şªª9±dtìì§[FÖ n¢İ±s@Êv¤²\nÄw¥nQÃØàÎ­úÎz€èb¢1¯„Y%Ó…’ICÙ\"`\\²hÄÉãÈe‹.ïh\rg’L²4¸BÌ/ V<r#\$D\"Š^%àÜLğcñÉGS’R§ÂêD–2\0BMeØ”(Î\"‘Òˆ¤™\"šP…~Älï\$ Tâ%¡\"Ïx¬(ç!â(Z\r ~-¤ÚÌøÏÎárwlÚòĞ—pÈıÀø ù@ù&MR/r¯N<úGæü B¦pru\0ğ¯2y'®’Z30`Şİ²VJÍ˜²\"Ìµqú\rh)hY'Åƒ+­¾Õ-Âûh²àBxÃæà^rÂ\r€W2ëØ]ä”Ö+Â*Jí¥á338º<úPØ‹¿\n#8+Ã#3.ÛÌãrKqÌf‡êƒNe2è»'ğPøp^woÄğr`»0Î’én\\éÀ»9pl=“4 Fhå±Î^¢\\«î©;o:ÑÒ\0`ø}­V`¸`¸ H±‘­‘°|o‹ğøßÈ ZéÓï°}Oñ\"Ç78¢¼À¦a\0Ìİ(\$Ç³	P\08ÀÒU\"½ã´_±eÙd\\%ÏÕP°Rs1ÒS “*b³I1ƒóu;N<­\0çCÀè	fêålC1xQÄ™JÍ;¢>üT…AFöq¹(T©G¤¿9“ÔÄ´ Ã.²²-%º=€CLpté1“°åÇJÁ!ŒügBó.\":÷/·-¯ÕJÉ¨ñ”²ÔŠõ?Ø&ÏĞı-ëL³›vX4)AĞïRk X3ñNªÛ#æ*™SN9Q5²uTÀI²\ru'1U4³•P¤²>L715`º/ìqCô F÷T“šgMÉ-ÔsÈlÕ¼ÓqLƒní @-ƒˆ´%Ú*hÄÕ	şú£#`Ú¶âHÀmòÏOğï @!÷¢æà¶¢øhóCTó€_@@¸@ºïXz€¸€HÊòuôœP°š•TÏu£+.†\0’ŠTørLÁµĞÛ£Ød/‰O¤úâ“*€Ü’•Œ¦jnZ5z rxykdC~¶/7oğh®]…âû(\ræ†P…îú±lëQqG\$xÕ`Ê\npMƒ´à„A ág\n9,M¢n\r¦ÛÖ:+‡Úíà¦}äÍQ†Yi`ç\rÉ–nq6*f¦MâË¬¾QÓëkâ•q	Y–ß\\(‹msßàĞ+€Ì/6ÖÁ†á,Çt	ÀQonWpPN\r‚ª‹t[nâ•eÖğ¶w«Â0\$\rL+mk–nÖ@„0/\$á‡Ê!­LŠ˜5Fì/ Û	BÀ( ëñãFKáF¬Ì×u\"@ ’Ô|\$5Ô¸­½!ÆÍ#2Üb@ÃO²èË‡X¯-8)ìÀÌŒ@˜)B*‚w”½·š\"€®Ú¬—•cÈÁzˆÅzêb@d\n jæÎq|—4#„Ìe!R•>§J“{©,×‹\nf}¦†ıÊosæ€	 nÅtè)v…[•?Ç>:—g\0j	ëı£	*¥ÇºWè”)Ì„¢’·E¦¼ÇQĞ‘€n¾\"³dö&­F˜sÒ£Gä•Ëßƒà¤”8 ¯b5„°oSé ĞxB=‡”ªªSr¼/§4\0èú¥u„³®Ë\"lĞ«Şêâ8»Ø”¡‰¡Š(×Šfß‚4½ôìGÈ½êÁ	ÖX,!B3õÕè5ì`Â ô´ô€Vôû` bá€Å\$ÅL*›;Tæ#TëX¼ÒÏÕŒ)CŒuûØÌ=‘›X×¸ß8æ˜ëàM++­àhMFĞ¯!4uQòÿP®)D“<•5“ÑÍ3(öL?¶Q11šÇ‰Íd±Ç‰¹ˆu²û¸]“Œ¼XÄİ\0¡jˆjbçúmd‰@È#®º,\0Š1‚UT˜'P%ê¨¬XFØøXanî3ã6%ëšã””WrğÍHèæp.òÕ g(²¼\$ ]m`a—ß™YÌ°&à0 z·ËXkÌv™–†ËÔ\r¢ÁŒÀÃCñ>`‰Ò[¢ 9º*lŞ¹Â`ßœ¹™¤†tƒ}ìyŸÄm—ÂÌú³†\\¹†Â#dÂxÿ3KKÌ}E‡T*å´>…çuGL“â1®y“â¡3:ßZ§”` GÂ‰YBm¸²ÃÂ\\,=àOxËtõ¤m‘‹.â÷¡ İ@Éš¸ƒš¥KRy­:”)Í¬zxš£”¼I!2¦ÍLêî…®Z„Z/™‚-ˆ Èì©8+d¼lïÑs£ù—¤t†0’¸Û¨‚JŒ@[°k\0á2|T¯d˜sª–ÅA{Jú%u\"ÀÒ‰ ÒØ°]KÆ™`v‚‹ì)^?Ä\0ä¶ ÓY´¨´¬L\r,\\é[,÷À·\0I\n ·¬`»`»\\Ù:Á­6ƒLÁ6ñ›'SUXµ]Wu`ûÅ¸ƒş\$x\rƒµú[8«ÀÆ™qc'vf›K½´58XªuˆÑ;5a¹[™¹ËÓº3Çºc(mÂ¬.hRW™cƒ¦ªÌsVnò\r¨gfœ&G€›ƒì´×ò²^ù¯› å›bÌšŸY¸çY2«5yÖXUg“5y¯Ó)ŸĞ9®0-94ªĞvY¯¶®qI'‡ItšL©BQ¯ñC‹»Bd6 SYšeÓ5ëì¿ÖQĞ˜1²ó\nõœF\rùµuÚ‚í']Å…¯±‘ñ'P€ó	B\rÙÖ/,p\"àXv€¦*@áÆr¶A²À§ùîƒı#=\nÀÔ  Ğ\nœÀ›0èlĞnlİÑİ /òókï¸|ª;¼ûÈ5ÆÙõCÙœt­K•\0cŸ®Wx&ÊoÒ#«c?.ı\"²ë‘2w`ùè[s\\°ˆu)ÒÇ€+Ûù?å§N¿Â¤u«7Ù.KÙ|ôu¨9 pƒdêõÚı‚\\¯àÑuÅXĞàAÜ¶üÌ¬\0öÇ\0ÄıÉØç£.ÈWÛ@ÚŒ=ÌMS=¡Àı…SÛë …TĞğòçQRåµ¶¸¨®`2’‹yÓó[DEÕC¼“áî¥u”cÅxZR|â6ëÆh¤Ğ}\n¼úÔ=e#+ÇÓÜeÔ=R|‘Cü~å}”ó¾}ê!]_,æ%Ğ´ûOŞ^I\"	Ìç€u€×d¨Ä\n€Ò#æŸ‹y ªôç;ÓüfæÜ (º¸¨+t\" î% ×1>}ä;DşÂóŸºtŠÑuÀã^\$âT>`ªØ]^ßI@VœëÄFëÄœMFm…Å§ùÅøQ¶Œ&Üé^U¬Ô†ë9P€aåyñÕoÄ`qÔL÷ê]C|ÓSòW5ïID/?1s^æé(ì]çòÃõ\r6²Máä,ñŸ@ñååÏÖ°Lï\"zŒãj¢jç÷”ÔA²»Ñy{{ŒO÷bm¸òûIİa- ä¯\$™j#õù‡ù°bÓŸ£.ÕIÓ{àú¬MhV‰g6ù‰ˆÀ»ó_­ÆûÊûÏhÉiĞ Sı«6äõ¯E‚ïÇ©ºÊHRS\\‚ñ·Ä¹ÙÏ¯;+àfæ7¸\0&m;O\0U³À‹Ìº#öD†`xmØ›OŒ-“²0¡›Ì*ûRÌô-’ï·èoS¡ú˜nÒ—e*Œ“¸À¼-–;Q³\$(1q÷Ôùåh˜T\"âqp¸/k–L*'à!ÑeŒİ’õ\nÜgLŒTÚ ÂR4@*A0ñaìVR•¨~„fÁ\\ğY?\$ÊuIÁ„­NÂóÄĞ\$f¤Ôƒğ€èv©…%l\0TŠ(Zø: í¯2)šL™tíˆ*>Pd@ÊO!¨3B(­PŒ„)O½(6A¹½  ñD ÁÒâ\$è)“\0á\$4HLÂ’É‰TÊ¦Bà‡ íĞ£ÚC‘¾&ğ6Àv‹8bºÌH^C‡º\0A5xö\r Š”¿™m¡2YñàDÑ ß™ôıÀC_T	ï\0'•ªİMúº…2•F«µŒ\r^óïÂŞüæœã¦\r†ûRb°¿6Ş£{‡hlš`dÖ?±3)Ä£ÙÇ8óCa†Ãw)›ÈfĞ#eK]{\nC~ÎX¼e†KØf@¶i‡ÇØ\\y?Í°§Ä‰?LûM±>9 Ù\n|ÆC©à/,bI2 ÛâxÕ¯œàn‡³%y’–åÁLè!„SÓÎU>§Ô€lTæBf{ÀPÌV³ı'0.?ƒA-''ŸäçÁ€‚ƒBY¬íˆ;T2ÅğDP`('ƒ'P—b´X£Õ¨Niëšß-x±2,€p‹0ÒB*6÷~°ˆLŠ¾VHX04Æj¬ AP^¢Ö@ÊÓ9.A%­-yl\\¬2‰€k\"iqòUÁ¡]A\\‹;¡¹ÅÕ¬íü,¼g\"ì2ÂÆ0\"Ø@aÂ]ˆÕ5 scÑeã0¹ÍÆª5‡òÊÃ0²1ê\0ñ¸MFBŒmÈĞ…bÒÆ\$ÆàÛJæ]\$vG¨Ê‹]±ªFñO¬!8^@CcìŒÒ4Íg'´Qc£Ç@	àS£ï@Np\"Ç´\$¹r³uFàY2ÍÇŒ ¦¤h‚\nQë`çy`H@[ÃJÅæ‹ˆ€ÀğÒ¹€´å -%ĞHö”Š?øWqä€é ‡<\0-ğ£@Z½qV4é‹ c:Ç¨ı|.Ùs”l‚úX%\n)É|0lŒXÔ?×9Fíò0`0l\r€dÖcOÈúFÁ‚¡g##8£Èº4ê˜„AƒcÀœæî\n¹Q±ºÈ@V@²À ¥\nIR]\n€ôä|(•¥À5Ó!<”p›^„ñ¡!F/á)Alã\nZÄv9d{lsØô©¨é›ò:²\\œvÈÁ¹?ªåW1Pü”£È!v\0¢?_!TŒMĞU_+Œğ (^ZN¢`%L	àN4!—•Ä[9¸Ñy>3Í\0Æ5Î0.7A<¦ÉtãA/\0†5Q%ÜŒKz¥³AÁ(=’¡•/š¡&r@É¤ Rk,« Ææ¤Ñ»Çà¯Ršì§VÃ	ÿĞ¶P»ñ¢R4#`çlplRRµ•ğI&!h@ä¿à3 LÒñrÊÔƒÖ[ái,‚ w9:QR!`<„„µêç(Ô¼ÉĞXE„r¯IÿÄ4Ét*±W7J£‡1«6+Â\"7šÌÏæÅ9µÅÆxˆX#U1cŠ`ä±—Pï^ìÇ=­\$\"²Â	é‹¥œ@Ã¯‹Nãœ‚<ƒö!Q°’,\rÒÈ P4 ¬Ô§|Ğò%>˜x§ºŒ¶0h`l¦Uà0*XÀøóCò‰°Ò&”<‰¦8é:\n`aïØç—fŸ ²gÓtqdÚ}‚R%Â²i&â‚UGµËÈZo\0R›ĞÓføw·î©õŒ28gÌ¤jiÆ	‰ÔÕt«	šMÌ\r@o!qÍ0ÙA–É=ˆ#c`¡35e®ĞóIP<Ê‘.ƒ×\$e“¢V‹•¤~y¸pà.BŸV=@’²mô.#.e(%é]’ê%rÓÓO˜ëÉ-´êíldÈÇ©jz3×\\‡\$ĞÌ©f2n¾(¤ËJÁ=hÙºíÈàu•yœ“øe\r@©9«LàËK@²)‚”ú@Hc¸÷…a=¹ô–V<\0sf8MPY>ˆŞ’2£T™d£Ã @`©¡\$ŸƒaXñ`9¡PÍS·H‘wMe€a’(ä.ipÃÖS\\Ğ“(¶	œ(î\nø·DWAâ'Ê¦dmI6\"ì¦JÂ7À.„	¡¤RáÕÀ\$NëbãÈ(»Ô¯®ıbfq‹•<ªœ\$i¾ÌÃ€a½]mBş¿P2°yĞõôÈÅ\"·ŒÁ´EUJ	Ä\0àQ±Q‘âóÀV¾@¸a=²É ô»Ã~-™ ÜIBL<óYf•G‘ê\$ÑLiÊD?Ğ¾›İ#¢ó<z^	³ç†\nÀn:BÁEúÏ9-…gg{ãÙöÀ\0ÜånBydT›3÷!…:\0ÆÕ¢«¨\nÅ>¤ÔÒ¥Ø!e±ú€„^¢zÈ(Uj—{(û\nD±7a¢jV¸@Ô¨}há”a`Rõæ‰Ğeè2Qü@›öa¼ÀÆ”àoyĞO\0\$Ä\$Ê@…Y‡í3h’G?¯Ä©HgJRÓ8cÓÀ§i-\"\0„ìQåA°LŒ0…+†ÄòØ\$€ê!f_\0›D\nP…h,H<J\\áÇ‰yê\rCÂ_Q‰¤j¢ÇÖ*˜™À¤SèùÂ#Ó&=ª– Ä`pL-3U_ˆ5	 ˆÍy;uzÎå¬ªızio“rJ®£„şÌTNÈS·N5Nå`H˜e ëı•@ N¨\0˜t˜—²;T|YoÖZö1¾Ò(eİf/ê+¢1Ô\0Sy¬¼%äSäuPoUpC¢’=Jˆ .¤´0”hµåv¸a2\nËŠ!Ö[9Á&o3{*ŞèøÙÀ1†y…c‘àÜ%‘*i5Tá0ì·¦ìVbí#úĞK-#\n¸dŠw’?LdsÎXZ)÷­\$HcÊM*=‘èiJ¥lYAõÂ5€rt2×+r}êà¹šiÍ®1¹Mö«v¾\r\0	fqìpeÊ¾äÕËˆ gÅïAÉT9°:ª#,TM*éX@„'+°\ni9\rf~ºcÖG€W\0P&cóæ…eKTe=D¶ÑÑ\$MƒÑ”—êÌ\0„V±„w¬ZŞ\n¥`j?I„=•û:Fk2ÅåpQ€A1°ûÌrBE¼HÂW2»¬d÷bí+ô>¥ãmÃYÑ©M£3<“ıŒÄf*ZcÕ´ÑäıhBşƒ¹vÏâ^3H\n'ø}Šª<nrªšDtPi¨*Š¶EÆÎr-Yeì¦*T7—„–íçƒ{mëğ&ÃôXd#Ãİv;,ÈX¨•åX,=•Œª'rÉ…­ér¿ZæH&Lõ\\/*Ç6\n€Ùe(´<.€ÔÎ1¡Æb2¯H0Ïc ÕÜûhG@¾-JHt1í{E'fº\$/\nëİd2ŸtëÁÄ@EfÀ~i—»MËOaµ_\rå_IÕÆ®uuŸÌ½\":£ô/5V‘ha‰CV¨NÂ3m:İ/Vr'û#-eWI°È~Ö¤èE}[‹ıhAÚt 5˜%¨%è-ÙV¡ü jËsXCûcÚÒÚÖÌ?U³™ÙE9«ZlSp‚]HfF‰±¥’I,„y™U7pZüõ|+¦,àrÊÛà¬VæSë±_¦”9Rü+ÖĞ_í²æf\"pã[NÙ6©vm\"1ê3ˆ•Öş[‚+(ˆ\r	ˆì%Älƒó%Sõ)=©UH\nš”h{´ı]åè\"`â…:âV¨·e¢À^É´r\"7Âi€Ã;GV`d¹-Î\\üó[•5}O·.\nq¦ò³1G[¨\$S0¹Úî6jZ½1oƒFĞ#t1€].é¶¥¹C½“LøÄî:5I2kKÉ·D}+¹:>ØUQ[êyŒ.%éú2\n×P-µàm|È‘Ş¦øä­`„³¢! S;­aÈ(Í¨€õ\0<GäKÈrY…¦Â\"ÁûC	š„„”»ì¦2­#=,zp8½Å³±Ç»€Ù“¥Ğ^YœÍ>JàAf*ÀH\${@¸^ØöĞË¿nd ¸]‰Åf¦¬®µ¬®v&ËjÜØÊ·‘év@üßYFt?aèœXvúwT#”)Z‘\"6“‰)¦Á,#yLí~ÆõÁˆ(sÓøgÃÀªE vû¨òT8oê%;Ä€ÉˆìYmcmlÇºşv\\oKo›,ßÉ²Øn+qæná1»àIP#p´ƒf”8Í`J[/íTMşïæ®ğ.àŒ¸\"qå«pÛ&®ÖD3ş\0… B«ñ‹NôŒsíŠ/Âl×Y<‰wÏ%Áüá5\nWEÁúpo*‘Ã¥q2€Ÿ	méª£¥™piÌ#BªKı‰§Sø:Âq\0¯Â;zœŒšœ,Ùt/aM—(@À`HŠf^ìïgEIyÅRq‚±`ù?\0!DpÛ€Ş~r:L&£¶ÂD-ï„ë…Ï£©?:Ÿpü£m\\ÅXaöX9\$¥Ü¶sX\"+â„;\$ºÄÀ©í[°{–ÂJQ»Ù3 ôÀĞ€³SWÎë3£ .kdKê\"×I¿pWøe˜€#( ,œd¥2EÓ¼°†Á˜ÜZØ<Z¿v¡	°\ns)âîÖã÷±+ïJ´p\"<	´ë½åu.x‰ œ²ôôJUŞK7w’lR@ _ZÈÅ@†GY!EèAª¤àÄ(”oDùIÌG{à9\0‰d©ø-Éú6‘lƒºF’¬@‘`7d¹rµ2-eh&{# jÁRäš¶v–Vd[\$x+ÎÕ^\0—	”C%å—*°rŠS2ŠŒ?%9Èxƒ@Ò@Úà½ƒYÀcoH)€ôÃªÇ\$x(ÉfIŒ«Œ‹Y´ó€'¬Z¹\r*òeAV9R¸	ÀZÊvÍãE0ª}idò¿ÈÚDï\"Ù[¡çN´HÀ1€¶REÄË9…g€ -XVrÖ©¬®u^´PLG‰Ì¹Î„€ràü˜„¤ \\'rŸsXí(D]€-ÌpKÀó™3å†„€mhÑÂ…@9åc-Y\0N`]h(°ÌfÄƒÊÄâ†›hf4¡/{»3U•¬Àeµ‘«}xBÂfÔz‹+qsrºlİæŸ7Ù~Íf`@^0ºGg4Ó_\"†vó /´Š\0q/\n@¾X€-O # ³©—üë\079¦Ï<ï3Ô=Lö2s8\0	É|¢•5…]eC)x-JMoÛ“ŒŞ%éL:Fk‚’bŠgcMpU@_ 9çÒ¹9°ˆèĞ¬EyÖƒ%´°w\ræœ€BÊÖk´*=èK*äqzŞ/•‚Ùä7EšWò,ªüšcd]Y&Ù»F˜ÆÀ4díSZ&“4\\GÔ”\$:3\n®jI	ÜS?œ€g\nàô{•\r\0Aâë	­=nó^ì{£\nqè\\{dmÑÚhÈôåÅƒ[”øæaP}’ÊZ¼UK¾áÈŸ—´¯ôñpí=¥¼g` {2bfni¬ØÊØI{­‘@Pà>pVwym¼ÆÊvµ5ØÔŠ Yœtä¤”’\rôºŸ‚«8lR®	Ë˜\nâ£X÷iÛ¶Û*Ü`t¶iœudì=P7å‰r:ÃÖZÌãªœrR„Ùº ­Æp5ƒ³zÙ—?ê¯Ó\rl‹¥|pÓˆ5—£˜Ls!GELQÖ¬l4Åf/v8¯LhØk±¶ıÑ(rV'†uÀmF\0øÆ*sˆÓ!`H[n‹â ËØpÀŞõ£…}ëÁ„Éî*0\r“ü9G,QYaØíã*üMİ¬(¿ö0Üt!4Ü3*Û‘Æ°Ôêó!„¸hO]q+İU·CY]™¦ŠõyÿÀ(+e…â6dĞOÜ,\0ƒ‘óh„„}Äl†ŠJ†6=Û*Î×<¢-–\0x°@ò·šö^c\nFÈèD! 2¹ï‡ç1ÌpŞîk¡ı\0è\nw³ƒ5Q¶G»¤r©µÅ?Û2©ÊN¢QñKwn.BD,8G<åv±…Û›\0nqd²èLİv½ y\0à•<±‘‰Ó9İš¬P<}¶˜\rµ@à÷â—Ëô²k²2»gú}•„Şá”£ì³@&÷;Uƒtê¦²€N NNOïGzH^–cÍ 1ºÉt0	Áà	ğK‚@¬­©qjæ¢Ë)F.|¼ËşV@º”’Á´©]pÒé3]R+ÎÇĞ¤’Æòa]Ú™½°š|R&úÃC_ãŠ‚'²’ßtP¨yš¤_¯gÁNhô2? 2êz\n—ß£kÃ1d»¤á@#%¨ì¡ˆLÕÚëÍ4@	_:æ˜C`’æÀ%H<10éìop9&Êe	óêB—§§h•á]lZø‘`2‰Óá\"˜™YsŠ€ÇôÌ{I¿Œ«-|c(¬õØl\rx@òGƒÄáLé ÃŠ¯¯sø‡|·\\Rú(Rß^é©:ºŞ=¡É³,®u´0rÚRtbğ#wqˆ2\"rÛE(˜	àP eŠCœ|iã<xÆJ+Ceuoİ£¸Şr^8,i¦é(P€†A~ğQ5 S\n˜Ø\0004P”IlØ=>7øë*5mì±3Mp^É ‘U%x30Ç\nç\$YÜÏ‡˜šŠµÁì'¸ìà]üXfä']>@ÚZº\nŠ|1á»ğM!˜ÁrP_ß1©Ã-ó\"‰fI‚Œ?„‹. ,)¢.YQaIDÙqn)@—|ÆzåóšH`Ş_&­¥ş:ê€O·¢/î)00AÖ›ø‚vÑ>&Ãá“„M9Ìî¿š+ï@ÀQ×%Ø!ª•è!‰@9Ğ6”ô¾œ6æ@¹¥Ì¥iÁìí	Cì1ÅÄ.¡xEkdbk:ˆúz|š´@ze÷8Kp^,PTEÍğeOsĞä7ñLÁü7d‚y›ÄğÜ&>†ÀNCHDŸ{\nä°ZÄ “-OV·Tj‰€¢­óşŞ,‹ˆ4ˆé™A—È?¬Ñ`¤[KƒO{¢{ YÃA¶\0¯„\ráÚœ¯Å°Ï±u«Pãr”¸:¦3†Êqü0ZÒ,ZbÍ_•„`,StXà1˜ÉP–U’¶Ô[Õ‚SAÚ=œ»²‹MÀÂã<oíT™ËÓÕÃ¦v\\@åÆp	ÌzÀÊÓµ„],LsNÚMXŞ`K(\0¤Xø`\n1`_«Ù\"™öÌ·’ ÑzPhû\0¡ÕÙ&* MÕ[?Ù™WÅ—íP“Ò¨Ş­õšJ±ÓÕ¡ØÁ;­Aş,PuÕöXĞ<=Œª\n’&\r0 3ó\ra!ï‘Ş¦fAàš„\$c«ÃX>@oÀ!5šøUÍ'©æ±É4=üë+»»£ Ï?¯!ñëße]£Ú £‘íºßÕ–M)ªnñ·?¯œíV‘;ìxãÆ™-À9@TÅl•â>¦x¡¡˜àaŞÓ\rÄB#wíbUšx+>ìtõŞ-U‹wÀÂŞ«éØ²ş,ÜÍûT7~ñøà>:í[ü}ÚÿwgÈj¸îîˆTDs\0¥¿“Ù9IûX[¾­>=À!=Ëq<)â'7*ŒŠ¹ŞNÿÃËä!ô¬İŸfô”¼\$\"¶7Áéé'b–g¿È@úüÈŸf÷;Í*=·ÀMÃMxoN!ßKL†¼t;`_¢½PË\n\\½\"°sÄ®¯o	0¸¤{ù!\0ÄÏnÏú¨¶~¯î·ªFeë>ã‰QŒı!\0Şæs°!’ ·„ĞöáB\r¸}l;Œ¤\"§QZ´VvÅ‡¯ü\$&_ŒºVß}€°+ƒ]Á` @€‹ì³!ÓÔÁĞö[@^¼€V´Ğ\rÕW.@ígÆŸ€Û2•4\\ÏÃ¦wµÎÚÑ•¶İÆ+¹¿à\"Û	\\©DòŸKZşLËSf™Ø†	”2à#\0¶öõr¸J­õ7T§…OÑ?3°Á)_ºˆÏtí½†u¾ü_\0êÀ‡vÜß‚A×àìŒØàK°=…Â¯“’øˆM°Ëoğ€…çá`tùãáÀÏ†_ŠKqVŠùböÿ˜sF	Ÿ3gÍFÈlÄ|êã£ÚIú3Ï	„'Â¿%.x¬\"4};!6„gÅ>Í¤°7q„ LúçÃ­\$«\\ÁÅ,LÅÅ–èt—rÃ\nÆ	-TşŞ,¸}ËwÅş×Ş~ö_ê¥óX\" HÚú:ÍÛAb6* lHèÜSA¯l¾dh–Ù%5H/|	’{n!F¢ƒÌÙS‚±!´Qu•Án¸pÄ¾Æ Ğu»›ó,šÂâ©¨SŞ7A—Ï@Ïò\"º|Ù–x.Œ•{İW/áM+ø‹)ş2<Ãä¤³ùn×e§ÓóIïèÎ·úsïş¨÷±÷Ó€#‹ùßß`;ü:¯ğÆüjšüàÄº9?ëüûIplÎ^¾\nl1<i\r7Zù3ÌBŸZÃÚq,åt»³nèŞSè5B!`,©7£•|‰Rÿ¾{÷j×6@Â|AV9f£3UÀ/»ò,Ï÷À&Fó¦æÃ.îFpåÆÌN¬€úF\\öÁKàÁï¾20q‰	§•qê´£K(T™Šˆ¥µ¢?ªråşü ]è™\nüÉ‰ïÍ¿Œ¹œŒ?TÿªÙ¯»¶ØÂö„ˆ2<ğoÔó÷¦F6‡0íã{Ÿ„}‰R *¤îŠ‚R({À‡Ê ö*B¡P@Ñ˜Ìğ5„Ï°ÆkÌ?\nÚ¹t,äGĞ£!¢œ\$«¥Èˆ8¨jcş¯‰¹ÌWR·hèpÀ!ÈcK!€n¿Ê‰-¢Zé›ä°5êR¸o©…\0\nş\"Û…@e:<¬N)¶Ú€]‚	Å09G­˜FX!e\nJàÀ&/I\0ê;{goà0n†¦›\0\00002ÔğŠf“‰\"¾ìŸ0W“(¨;(h¦Ú¿t@ë¼›‹,Ps‹ï@±ìÃ°*æ¢ıJQŒ-2Q&z	ï=„B*Ÿ¸?Ğn—4¡HÓXƒÙq¹ğ~¾OJ§ïs÷BwÇÍ)œA“X8h`›¢y	 \nı4àÙ#µÀ7/Š‡â‚Âğ—4C#!èI€€0p£KNÅU•ZŸ¨n ˆğÀf»šÉ	»±À-€dÀ³åCfœ:ğbS‚sÀ•ë\0æ³ˆñª”¸‚Î°0¦ò‰¢á4À°=jXÄ(Aè„˜ôğ]/P\0êõAãm¨™¬ß!\0003±BÈ%Û­P³6~ñÀL\\ˆ¾Tl&˜ƒÏPÎ`&ò3É“ ></€¾è‚d†™!ôaä0¦YAhûB¦š˜<ˆ \rî¢z»F‡L¯\"d+Èlræo®0×h`ú¿™Rà\røAÊx:<ÄàĞ×\0~ì;½.Ä e \"¦@Øì¸fÇ;/	ùL\"©Œœö0BÌB{	ü¥ÎóˆöÀ˜ò¿Z#-ù¢@8R\$Pè	B¿\"#Kï\"8ŠC«#Š¼¿¡‹àÏ®Eúâ‹Ö\"Vd\\ã0€çÁb÷ÃÆ7©ÏÛÃÎ»Ï”›dÀ(¢,¡CÕëÍŠºøL64Hü;ñ\$=š¨#Î†^h \0ºdÈÌ†iT@-Œ¡Şi˜ş0\0P#@’‚CÔô=Q5ìš¥íeÁß¸Å…	Ã¾¿ó¨ŒHC,ÔB‘á<>pú˜hÅB‘‘\n8RÅ3_ª¡y2….m½€j¸€!”äÈWğûA–ÉóaæÛÃöØ™·ğÿAåc0‰ÀH\n”À¶ÀP	LÄtØÊ%à<wù=%.»´š“ÉI\"”BKx˜ï©ÂÇ¾9Zu±*Òÿ›XõÄ«\n:-Y;\\C€ÌD=¢4q;;v|@DÌòÜ	&Ä\n€æEN?VûXğK TÁ¹)F^±€tHÃnB@Pù^lJ9¨¦«â%¨E@Œø`ÛÏãE,bY4j½®:oàºáXşb›ó°\$%¯+œòÙ/û‡»\nè f >×)n]Ø\nh×£8,(Ğ‰Üı3ç~Á#\r¦+¿ÿ ;ÀDT@—ğ˜í\0\r°T9ğ™XQàÿDÄA@/Ä\\H¼nb™°É¿ñŒ:HŸ¢BÜ §d—F*p¦b“„ãÑÁH	òş°jD4EŒ‹ÄY\r°Ã8¡Ñğjò¬Î•j}ŠôÅ®ºc=?ëCûØ€àL\\\$Ã¢ı‘Oq}C¨ıÂj¢Ÿs¡û\"¼D‰b\n:ÀŞ€œS'õ™ÜH±#«ìB@—\$…‚“4S09\0çaö#¤‡´BDT`¿ÆˆìhºFW±§‡A(M£ÆjeùcêràpHe\0„´PÀ5cƒ¶Š«ÿ!Õ…ÔÃ\nºÄÚš\rP•Á·‚Ø\"Z—8§\r\$ã>Û»~BEÒ¿\\0ÄU#¹CmFü(D \0m\rÂˆo°¾:nÒil@¶A1	J™àÔ+ÍkæL“HAWÎ¦°	{1¨b2ğ:iO¯ ˜|Ô5‰3Ììte¦”IûE\\«\rav 6¦¾¤t‰ÖÇL·°åqÚµ¸0×Dê«5`¤šœš¶Ê´BbÜ¡ªa?ÒêÀ²ñİ™P8Sw¨Ùºî\$ğÇ. q@‚`1™<qŞƒ²*àj…n§VšTæÃ“Œ]í•İ»f“Ÿö›Y9†X;¶LèúQ@òÔb~¤¾Äo”‘îÇ`;P\0ŠôàJäC„£¸à‚jÑÏ³I‘ÚÇÌP±âGún°úQË½lmâ<… 1'’lK„b°HÔÀoÚ 0ô\$ÒƒBã-àƒ€ÆšØ 6&Æ¨˜€åÈì‡#!ˆg!´áºÄA ´zGşX\\ ¸\0¾«”!lÈD6Èz’£¼||f³‚b„‡\"CH«\"\$¬S\0šÑà”ÇšP	H8â/ÈªCğ- €jpÀ\0k\"*g±íH7ä‰H=3‘!12'È”:;¤ç=Ï\"à0	mƒûÊf¨=’ÑI3´ ×È+†ÃÃBÙ’Ö?êúDo\0óHŸœ‘Œs“œè`Á0\r8'È%òGBÌx2\n\r>ì¤'äÆ…¢‹f÷´öH¨†6:ÓeÂ1F.»<c\"\"É&…Sè~0~¢S§(rÆl7Ši«È\$#”Æ´'`Æ?å¬kLò™=Ü–Ev±¨©øôŸ„BÇjğ¨.rN ¢,©\$\nµ¢ŠC€²†IÈ_zòªIª’É¹'l_òwÀŒ4H†DëòCSÇj‰ª\n*õ=¬ôÂ¼C 1à*\0è1\0006+£\nÑÃ­IÀ÷~\0®;Ô¸€3&˜CÉ€Q!I_áïµ3\r€†¼³Ò;S@´\0Ï€+ˆƒØOåïÉ’t\0Š‹LZq,ï»ªxŒÄ=Ä¼`n™*\\´ÅDR+Ô0	ú…2n…\$ÃŒvô'Äô½Ì<˜…‘‹=Yà³Úóhé2¡\n\rÜ'±H!€˜KÑ6)³)›²p\$Í)h)ÇF'8s´¥İC”2êàš„lş¤PIş|–ÒG¡¢hTAşÉ\"‹óøÌ2®*ĞI‚İIˆ¦`I¢­ôiºÁy.÷ÜğÖ€VËËSÎë”Âìø{NÏ8ÈSÂ	»Œ÷4³#Ô²ğ<ÌA7:9_€¼²;—,ëÿ-‰_Å€¹-d]ŠBËpÈP’Úm-!ˆñzÉè®Ä`{‹B)¹‚Q~ÉÜ*|`dt\\x`]QµS\$+!{¶l Øˆ(g/¹		C¦¹A§µ\"J@èà·ÉÉø\$\$üÓ¹Ê71©d‰®`KÄF²8\"Œ’¸¸`…òé¶]Sµ¬‹ØÍš]DYK¶¡ûµƒ19/[Òÿ5\n;á6Ü­èw>-d¾cˆÌ+	¿ÈM/üŸª	(>tm³Jºnql†1@;N>(é²™Í¼ìrKn '#ààŞhö<jëÔq‹•…u\$¹}çe\0O1€báï¸”BØ€ì…	1ÈÇ¤£\$cêó#Ì:¹Ó&\0ÜÊû~ë–H¤NtËÓ*u\$!pÒC\rĞ½»S/C-%»fqºõ2ôUæı£6`Á‡ŠÁ\0Êaü_/¿ÆN²\\iÇÉZNOhLúÔYOÁï,Š!ŠtC¸Aèmb'œh:ÀˆÌ!0âDâ/©³#yA%šcpÎ=£…mªø°i ×\nÌFãß/ã**ˆ\"]ÅPß„Â¯Ë2TÕà³@t¢i¬“\rA/pÓ-f¬Ö¢\\LC29ÄI‘D‡XnS“­0”“Mˆ>«äVŠO‹‡Ã—‡°QÛ‰¯Ğ8 \$*K\n@*è]zû»ö’KD\0ÇYÀ\"äeŠöJìëÄ@2}t’‹Q@01ÁØìL°ÁèFòk%º¿ĞLš–ä“àPhİ¥jh´°„\$óÁP¢X]QOóx~’¿9Â\r”Üs‹\r<ÎåûC³3Ú#“C¢€‰F¥6JZ)«M(FD(&.6Üë¤f»Öm	„f4ËPdÁ_e>Ê´X²¦>cÄ7Qşµ9Q\"S0™ƒ4k^H¤N*½ãŒX\$nNqIr¸Ùâ@ÎI(¬Ï“Ìù\"'Ó?Õ9i¥0ÈM\$Š|qÇ×ºã9¸3ï:-Œñ´Ó g-i^ÎÖ	d¼B¼™6ÂÓ,D1:Sép º„S¼ŞÓœ‘¦T·n©Nï-ìï@9ÎøËÁÚ¢]@Î™4J(K(IÏ:Œ=J‡,{S­O*ŒXŠáac8‚.Äë39Æ\",ò/Š<®s´õA±A0ØT˜D”bÔ;Á4î-ùÖ€Ù\0NĞ*.¡> ¸½ü¿Ô\$.¹5XVÄİs²Í/´•êÎÄÿ/ËÇîó§ZëTPîÈ:‰|•µ<:0lÌ‹0‡*n¦“¼‰Vs™ÓÜÄô§z£.‚ y&èà¥¼Ä-œüò·':õkÙ+ĞOJ>3µ“ÔÍ=¬õ“\0™1jûoÕO\"`üöeÀÏô¼Ë~I‚¿Y‹„öEêvQ‰uÄ|şL¼0Re<\0(¦«:2,†öKz#>Æ0[`\0’p€\"€°ÇğÓ”@î¼Á¶ãLUJZsÀÎ¼ÄWÍ;DCã¦¤ûÂtià«ı˜~eä˜jÎi>[®\0ÙO>>\"°OÄdîÑÅN};Â¦9dÄò“¿Î‹B3Á‘ÜL^³µ@ÛÉÓ«<b´öe½P&À\n‘/<DF¬šÁ<dÓÇ3­C”2™9\nŞ¥È“šÌsSËË*«Q³ÍNÈb‰è \$3:z­³ñEèby‰4\$¼Ü¹˜/„Üä¡ô×0ä)ŒJºÜò5r×œ<ì?—v\"(=¦0ş:J±Íÿ§›º²IÂ+(ˆïs%:DÜ„!‰ÊQq6iÚ º¼í0ÉCÇ€LêñràÌ•F]ªÛ:ÍXò³%\$æà‚ŒF¤L¦WÑFİ§@	cÈÉf¼Ñ×˜äà¸Tıtp@ÛFuÀ<Ñà%4s£ÜËñäÌ:ğ¬BÑB´†ˆ¸«ˆçH±Gn€ÑÛõ\n«0´ÀB¥­æ“N×À¡´‡Bp’g¤‚®Xb¥ \0pëqïÔîÔŸ§ˆ€	\0(ApÚô	®M«’¿c&É:ÁSoF¯Y,\\„7IÃÒôV?UIœÆj!;æH(i<£2™ph`=RM)T£#pR\0p.’mI™·L-\nÉJÃhYJ*e¡ò?Öà‰â„¸öq±Ù\$²f‘ø‰ön *ğ‘‡‚•ùæ€™'I˜Œ4¦&\\up¬”¤¡•+@ºRíJr3NMa™ì_›ô¡°­B4:@İëeÇ>…îE¡tœ¿:!øsÉ”\0Û7&õç1 .Âäë¨Ä P*˜».ÉÉ–œ„ÉE×<8tQ\n.ÇÑFH\n”Äƒ1Fdœså]Mˆh”ÅSh/\\@„'=Xk¤•%\$˜¯ö½rîIÕÔ¹ÓH¤sä´õêºnK\røö>ò'Ì”¦ÄÉÓcÈúš°ç #5<9iuÉÊ¸^t Ë\$ˆ€ğÈ,S<rìX‡ó®pV¡°CŠˆfÅcÌX€DãñE°Ìo6éÅjõ¸¢é}¦Sä	Å*O<»€Æ'‘’ÆÚ’m¢´.zh!Ôæ¶°\0é3¤F¨X\$(¬4„¬à:*6§hX2{T.0,À,—5Páz£ƒŒG'ìa¡†´M?“˜ˆ(#Õã½@ê)„P™0.Œ ¼‚\$`:Pù(UˆsPÉsT\nRô'';\$¥!Ÿ\0éQ0@²RnVåI¤£7P=‚ß¥Bœ¼à„Ûqµ#Öˆ¶êCöŒ*Ã!ª1 { b[¥a0ÙSB)›_a]¥w€ÈW‰qÈğÍJiFúˆ*\\¡oŠQÓSúwõAi\0æ–\nÕ’#Å©¼B¡üğ0›Ù2# £„™\n)´ Kid¨AôéR[^FB¨Õ‘Cè\0b hª•p&‰9µE3¬ño\0AÔh±3ÊQ­eM-¸ÂİSµO°TmH`:UWĞ‹p>	'Ğ€3;0-Xª„Uy¾«ÊÕŠ mXµUˆö*-Z†öQ†Jˆ%\"›R¤_ª‚ˆEUBãU¸Ê€ubÃ±Ø¢²A<Sz”àeZUW2-V\rZÕz¬&^‚…5m€ÚÈXX5`Õ¾¿´8«–›_á~\"——±W=]ÕuV?\\OÅ7‹B!…OPªÕØ40È×•ÜWƒ!BÙÕd÷0Á~Ul,H5\\KTÌ°)@62ôü¶XV7/@Äucƒ{ ¦öUB´-T…W’,t;@VES¹yuÕER½IŒ¨.o­WåÉU‚^ãVÕ J¿Vk…5ÔVÊÒ0Á'ƒ0ÕaqVW%a¢®oVB áßÉÃY­jÀ80X•`Œÿ2.-	~—U®?…Zâ	Õ³m]l‹V²¼­hU²U”!ĞâÖo[]`U¶Ô¶øÁ¨ìòãZÃ¤Ôv®_µ¹ÕéR%p±	ÓË[ˆŒ¾Öo[dŸ¥™Êî@\0{µŸEX‡«pTqJ®G;‚o‹•Tx<c¡fCş?uRµÇÍbĞSA&(§T¥R +UH<EHÀ³T5ˆ05RB©\\µBu:­ô; ‚'IL)g†ì}R…œ6\n„Wê=Z-™Âb=€t\$%^	ˆ%\"HR¬¸Si0¢Ù×Ê;Ğ¼UôpÁ_	ú…@¬ˆªÉ§¬©­M·È²µvÂ?&°‚\"Ç©Lz¬T‘LM8X3:Ò€{|*Iß´ê,è˜Aê§î&5€Æ7§„õz³\"Ùµ™ìÄÔ\0BÄÀËÏ	GT]EupDH³ï'‚L@ûÈ	.L¸Å2åÔ‘â/`¿À¨\0y0ø¬w))µ‡f\n„*ñ˜ë¡âîú«B\nXl`\0+%ĞÉêW8#”ÈœÆüı?µÒ\"º±Šë¨&rÕZá¡Yc£á5¢ø \$cî—MÆüq&\n×TImÿ•Ô}QägD­Ñ€os4!y£ılv\nè»ÉIÀØibyÄ—\$Àe–¢ˆñdß\0>óXğo¼˜ôûÊÂàd£Pí]µ”(¶G»9KäØcˆ¾ó2x„ö\0˜tGT@‡Y)ì(aZA3’0K „	ªÆëägÅÓ8Š\n:M’•¾TAãb0ŠDP\r•£ˆ&\nà!/^è-/ÃV¦E’’¡\n0)õDçKY¥hä€@¡Kñ¶*ÖŠ<›@‹Œ©¡d¶^×#Wğn`‹kf%CâÙÙ ıZöfXÄzvhÔ:¨h@aíY¡fô¨V|Y«eUŸµ‡Ù´À9¶rRK3H vGÄ…LYéOÈ^]m‹+ X×b«ñå?]\$Š¶id CB¾JÂİ¡³*à¥8oÅ˜ÇLVAZ2Š ³v=LÔoºtˆ½“\$-¡6*‘sirrKL¶ÄRz–ILÅ/êæxƒH");
} elseif ($_GET["file"] == "jush.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo lzw_decompress("");
} elseif ($_GET["file"] == "logo.png") {
	header("Content-Type: image/png");
	echo "‰PNG\r\n\n\0\0\0\rIHDR\0\0\09\0\0\09\0\0\0~6¶\0\0\0000PLTE\0\0\0ƒ—­+NvYt“s‰£®¾´¾ÌÈÒÚü‘üsuüIJ÷ÓÔü/.üü¯±úüúC¥×\0\0\0tRNS\0@æØf\0\0\0	pHYs\0\0\0\0\0šœ\0\0´IDAT8Õ”ÍNÂ@ÇûEáìlÏ¶õ¤p6ˆG.\$=£¥Ç>á	w5r}‚z7²>€‘På#\$Œ³K¡j«7üİ¶¿ÌÎÌ?4m•„ˆÑ÷t&î~À3!0“0Šš^„½Af0Ş\"å½í,Êğ* ç4¼Œâo¥Eè³è×X(*YÓó¼¸	6	ïPcOW¢ÉÎÜŠm’¬rƒ0Ã~/ áL¨\rXj#ÖmÊÁújÀC€]G¦mæ\0¶}ŞË¬ß‘u¼A9ÀX£\nÔØ8¼V±YÄ+ÇD#¨iqŞnKQ8Jà1Q6²æY0§`•ŸP³bQ\\h”~>ó:pSÉ€£¦¼¢ØóGEõQ=îIÏ{’*Ÿ3ë2£7÷\neÊLèBŠ~Ğ/R(\$°)Êç‹ —ÁHQn€i•6J¶	<×-.–wÇÉªjêVm«êüm¿?SŞH ›vÃÌûñÆ©§İ\0àÖ^Õq«¶)ª—Û]÷‹U¹92Ñ,;ÿÇî'pøµ£!XËƒäÚÜÿLñD.»tÃ¦—ı/wÃÓäìR÷	w­dÓÖr2ïÆ¤ª4[=½E5÷S+ñ—c\0\0\0\0IEND®B`‚";
}
exit;

}

if ($_GET["script"] == "version") {
	$filename = get_temp_dir() . "/adminer.version";
	@unlink($filename); // it may not be writable by us, @ - it may not exist
	$fp = file_open_lock($filename);
	if ($fp) {
		file_write_unlock($fp, serialize(array("signature" => $_POST["signature"], "version" => $_POST["version"])));
	}
	exit;
}

// Adminer doesn't use any global variables; they used to be declared here

if (!$_SERVER["REQUEST_URI"]) { // IIS 5 compatibility
	$_SERVER["REQUEST_URI"] = $_SERVER["ORIG_PATH_INFO"];
}
if (!strpos($_SERVER["REQUEST_URI"], '?') && $_SERVER["QUERY_STRING"] != "") { // IIS 7 compatibility
	$_SERVER["REQUEST_URI"] .= "?$_SERVER[QUERY_STRING]";
}
if ($_SERVER["HTTP_X_FORWARDED_PREFIX"]) {
	$_SERVER["REQUEST_URI"] = $_SERVER["HTTP_X_FORWARDED_PREFIX"] . $_SERVER["REQUEST_URI"];
}
define('Adminer\HTTPS', ($_SERVER["HTTPS"] && strcasecmp($_SERVER["HTTPS"], "off")) || ini_bool("session.cookie_secure")); // session.cookie_secure could be set on HTTP if we are behind a reverse proxy

@ini_set("session.use_trans_sid", '0'); // protect links in export, @ - may be disabled
if (!defined("SID")) {
	session_cache_limiter(""); // to allow restarting session
	session_name("adminer_sid"); // use specific session name to get own namespace
	session_set_cookie_params(0, preg_replace('~\?.*~', '', $_SERVER["REQUEST_URI"]), "", HTTPS, true); // ini_set() may be disabled
	session_start();
}

// disable magic quotes to be able to use database escaping function
remove_slashes(array(&$_GET, &$_POST, &$_COOKIE), $filter);
if (function_exists("get_magic_quotes_runtime") && get_magic_quotes_runtime()) {
	set_magic_quotes_runtime(false);
}
@set_time_limit(0); // @ - can be disabled
@ini_set("precision", '15'); // @ - can be disabled, 15 - internal PHP precision

?>
<?php
/** Translate string
* @param literal-string $idf
* @param float|string $number
*/
function lang($idf, $number = null) {
	if (is_string($idf)) { // compiled version uses numbers, string comes from a plugin
		// English translation is closest to the original identifiers //! pluralized translations are not found
		$pos = array_search($idf, get_translations("en")); //! this should be cached
		if ($pos !== false) {
			$idf = $pos;
		}
	}
	$args = func_get_args();
	// this is matched by compile.php
	$args[0] = Lang::$translations[$idf] ?: $idf;
	return call_user_func_array('Adminer\lang_format', $args);
}

/** Format translation, usable also by plugins
* @param string|list<string> $translation
* @param float|string $number
*/
function lang_format($translation, $number = null): string {
	if (is_array($translation)) {
		// this is matched by compile.php
		$pos = ($number == 1 ? 0
			: (LANG == 'cs' || LANG == 'sk' ? ($number && $number < 5 ? 1 : 2) // different forms for 1, 2-4, other
			: (LANG == 'fr' ? (!$number ? 0 : 1) // different forms for 0-1, other
			: (LANG == 'pl' ? ($number % 10 > 1 && $number % 10 < 5 && $number / 10 % 10 != 1 ? 1 : 2) // different forms for 1, 2-4 except 12-14, other
			: (LANG == 'sl' ? ($number % 100 == 1 ? 0 : ($number % 100 == 2 ? 1 : ($number % 100 == 3 || $number % 100 == 4 ? 2 : 3))) // different forms for 1, 2, 3-4, other
			: (LANG == 'lt' ? ($number % 10 == 1 && $number % 100 != 11 ? 0 : ($number % 10 > 1 && $number / 10 % 10 != 1 ? 1 : 2)) // different forms for 1, 12-19, other
			: (LANG == 'lv' ? ($number % 10 == 1 && $number % 100 != 11 ? 0 : ($number ? 1 : 2)) // different forms for 1 except 11, other, 0
			: (in_array(LANG, array('bs', 'ru', 'sr', 'uk')) ? ($number % 10 == 1 && $number % 100 != 11 ? 0 : ($number % 10 > 1 && $number % 10 < 5 && $number / 10 % 10 != 1 ? 1 : 2)) // different forms for 1 except 11, 2-4 except 12-14, other
			: 1)))))))) // different forms for 1, other
		; // http://www.gnu.org/software/gettext/manual/html_node/Plural-forms.html
		$translation = $translation[$pos];
	}
	$translation = str_replace("'", 'â€™', $translation); // translations can contain HTML or be used in optionlist (we couldn't escape them here) but they can also be used e.g. in title='' //! escape plaintext translations
	$args = func_get_args();
	array_shift($args);
	$format = str_replace("%d", "%s", $translation);
	if ($format != $translation) {
		$args[0] = format_number($number);
	}
	return vsprintf($format, $args);
}

// this is matched by compile.php
// not used in a single language version from here

/** Get available languages
* @return string[]
*/
function langs(): array {
	return array(
		'en' => 'English', // Jakub VrÃ¡na - https://www.vrana.cz
		'ar' => 'Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©', // Y.M Amine - Algeria - nbr7@live.fr
		'bg' => 'Ğ‘ÑŠĞ»Ğ³Ğ°Ñ€ÑĞºĞ¸', // Deyan Delchev
		'bn' => 'à¦¬à¦¾à¦‚à¦²à¦¾', // Dipak Kumar - dipak.ndc@gmail.com, Hossain Ahmed Saiman - hossain.ahmed@altscope.com
		'bs' => 'Bosanski', // Emir Kurtovic
		'ca' => 'CatalÃ ', // Joan Llosas
		'cs' => 'ÄŒeÅ¡tina', // Jakub VrÃ¡na - https://www.vrana.cz
		'da' => 'Dansk', // Jarne W. Beutnagel - jarne@beutnagel.dk
		'de' => 'Deutsch', // Klemens HÃ¤ckel - http://clickdimension.wordpress.com
		'el' => 'Î•Î»Î»Î·Î½Î¹ÎºÎ¬', // Dimitrios T. Tanis - jtanis@tanisfood.gr
		'es' => 'EspaÃ±ol', // Klemens HÃ¤ckel - http://clickdimension.wordpress.com
		'et' => 'Eesti', // Priit Kallas
		'fa' => 'ÙØ§Ø±Ø³ÛŒ', // mojtaba barghbani - Iran - mbarghbani@gmail.com, Nima Amini - http://nimlog.com
		'fi' => 'Suomi', // Finnish - Kari Eveli - http://www.lexitec.fi/
		'fr' => 'FranÃ§ais', // Francis GagnÃ©, AurÃ©lien Royer
		'gl' => 'Galego', // Eduardo Penabad Ramos
		'he' => '×¢×‘×¨×™×ª', // Binyamin Yawitz - https://stuff-group.com/
		'hi' => 'à¤¹à¤¿à¤¨à¥à¤¦à¥€', // Joshi yogesh
		'hu' => 'Magyar', // Borsos SzilÃ¡rd (Borsosfi) - http://www.borsosfi.hu, info@borsosfi.hu
		'id' => 'Bahasa Indonesia', // Ivan Lanin - http://ivan.lanin.org
		'it' => 'Italiano', // Alessandro Fiorotto, Paolo Asperti
		'ja' => 'æ—¥æœ¬èª', // Hitoshi Ozawa - http://sourceforge.jp/projects/oss-ja-jpn/releases/
		'ka' => 'áƒ¥áƒáƒ áƒ—áƒ£áƒšáƒ˜', // Saba Khmaladze skhmaladze@uglt.org
		'ko' => 'í•œêµ­ì–´', // dalli - skcha67@gmail.com
		'lt' => 'LietuviÅ³', // Paulius LeÅ¡Äinskas - http://www.lescinskas.lt
		'lv' => 'LatvieÅ¡u', // Kristaps LediÅ†Å¡ - https://krysits.com
		'ms' => 'Bahasa Melayu', // Pisyek
		'nl' => 'Nederlands', // Maarten Balliauw - http://blog.maartenballiauw.be
		'no' => 'Norsk', // Iver Odin Kvello, mupublishing.com
		'pl' => 'Polski', // RadosÅ‚aw Kowalewski - http://srsbiz.pl/
		'pt' => 'PortuguÃªs', // AndrÃ© Dias
		'pt-br' => 'PortuguÃªs (Brazil)', // Gian Live - gian@live.com, Davi Alexandre davi@davialexandre.com.br, RobertoPC - http://www.robertopc.com.br
		'ro' => 'Limba RomÃ¢nÄƒ', // .nick .messing - dot.nick.dot.messing@gmail.com
		'ru' => 'Ğ ÑƒÑÑĞºĞ¸Ğ¹', // Maksim Izmaylov; Andre Polykanine - https://github.com/Oire/
		'sk' => 'SlovenÄina', // Ivan Suchy - http://www.ivansuchy.com, Juraj Krivda - http://www.jstudio.cz
		'sl' => 'Slovenski', // Matej Ferlan - www.itdinamik.com, matej.ferlan@itdinamik.com
		'sr' => 'Ğ¡Ñ€Ğ¿ÑĞºĞ¸', // Nikola RadovanoviÄ‡ - cobisimo@gmail.com
		'sv' => 'Svenska', // rasmusolle - https://github.com/rasmusolle
		'ta' => 'à®¤â€Œà®®à®¿à®´à¯', // G. Sampath Kumar, Chennai, India, sampathkumar11@gmail.com
		'th' => 'à¸ à¸²à¸©à¸²à¹„à¸—à¸¢', // Panya Saraphi, elect.tu@gmail.com - http://www.opencart2u.com/
		'tr' => 'TÃ¼rkÃ§e', // Bilgehan Korkmaz - turktron.com
		'uk' => 'Ğ£ĞºÑ€Ğ°Ñ—Ğ½ÑÑŒĞºĞ°', // Valerii Kryzhov
		'uz' => 'OÊ»zbekcha', // Junaydullaev Inoyatullokhon - https://av.uz/
		'vi' => 'Tiáº¿ng Viá»‡t', // Giang Manh @ manhgd google mail
		'zh' => 'ç®€ä½“ä¸­æ–‡', // Mr. Lodar, vea - urn2.net - vea.urn2@gmail.com
		'zh-tw' => 'ç¹é«”ä¸­æ–‡', // http://tzangms.com
	);
}

function switch_lang(): void {
	echo "<form action='' method='post'>\n<div id='lang'>";
	echo "<label>" . lang(21) . ": " . html_select("lang", langs(), LANG, "this.form.submit();") . "</label>";
	echo " <input type='submit' value='" . lang(22) . "' class='hidden'>\n";
	echo input_token();
	echo "</div>\n</form>\n";
}

if (isset($_POST["lang"]) && verify_token()) { // $error not yet available
	cookie("adminer_lang", $_POST["lang"]);
	$_SESSION["lang"] = $_POST["lang"]; // cookies may be disabled
	redirect(remove_from_uri());
}

$LANG = "en";
if (idx(langs(), $_COOKIE["adminer_lang"])) {
	cookie("adminer_lang", $_COOKIE["adminer_lang"]);
	$LANG = $_COOKIE["adminer_lang"];
} elseif (idx(langs(), $_SESSION["lang"])) {
	$LANG = $_SESSION["lang"];
} else {
	$accept_language = array();
	preg_match_all('~([-a-z]+)(;q=([0-9.]+))?~', str_replace("_", "-", strtolower($_SERVER["HTTP_ACCEPT_LANGUAGE"])), $matches, PREG_SET_ORDER);
	foreach ($matches as $match) {
		$accept_language[$match[1]] = (isset($match[3]) ? $match[3] : 1);
	}
	arsort($accept_language);
	foreach ($accept_language as $key => $q) {
		if (idx(langs(), $key)) {
			$LANG = $key;
			break;
		}
		$key = preg_replace('~-.*~', '', $key);
		if (!isset($accept_language[$key]) && idx(langs(), $key)) {
			$LANG = $key;
			break;
		}
	}
}

define('Adminer\LANG', $LANG);

class Lang {
	/** @var array<literal-string, string|list<string>> */ static array $translations;
}

Lang::$translations = (array) $_SESSION["translations"];
if ($_SESSION["translations_version"] != LANG . 3043401755) {
	Lang::$translations = array();
	$_SESSION["translations_version"] = LANG . 3043401755;
}
if (!Lang::$translations) {
	Lang::$translations = get_translations(LANG);
	$_SESSION["translations"] = Lang::$translations;
}

function get_translations($lang) {
	switch ($lang) {
		case "en": $compressed = "%ÌÂ˜(ªn0˜†QĞŞ :œ\r†ó	@a0±p(ša<M§Sl\\Ù;™bÑ¨\\Òz†Nb)Ì…#Fá†Cy–fn7Y	Ìé†Ìh5\rÇ˜1ÌÊr†NàQå<›Î°C­|~\n\$›Œuó\rZhsœN¢(¡’fa¯ˆ“(L,É7œ&sL Ø\n'CÎ—Ùôt‹{:Z\rÕc–G 9Î÷\0QfÄ 4NĞÊ\0€á‚;NŒóèl>\"d0!‡CDÊŒ”ôFPVëG7EŒfóqÓ\nu†J9ô0ÃÁar”#u™¢Â™ÁDC,/d\n&sÌçS®«¼ƒšèsuå™9GH›M¶w=ŸĞl‡†„8-£˜îÀ¯àP …¾È‚Üâ!ƒzÚ9#ÃdÉ.\"fš®)jˆ™Œàœ’Jº\nù¶N,ã\r­à¦:0CpÎ‚ˆL*ğ<±(éCX#Œ£|SF‚˜ò££kz«´c“Î9!L\r\0Ç#¶úO³À7°ƒt«®xÊâ„œ²®Œƒk\n«¯OÀ@ (Cš\"ş²¨¸Æ1§Ã›²‹¯R¤;¹cBÄ¡#\0ç@`@#B¾3¡Ğ:ƒ€æáxïK…È¬Ìê¾,ÎËá}BC ^&¡ğÚÀ¦#pÌÀË®3ˆã|Ü»£(Æ¡6[{?è\$X:×k+x ’°Å¡V=“45#(ïØ.\\Š\nëÀÜì ¡*,1-od‚ˆcxØ’KB¢´Ş]#lRâ«£ª-+\\‹/².àÌ0¶H@;7\\U\\×`PŒ:­U“&®Œã:¾3¶0¬´)Œ«ÀÆ4Nxò;\n¼TÿC\"}:8.ËÀ‘9`P¨ëL2øÎÚ+µŞ-…`#cof ƒŞŠ\"`@áŞN|¤7«rĞæİ«¶z±^¾ª¶›eg¹ºkdJ9ãr(Ã)ÙN¹«kâƒPÕ+‚”ÿ3 ¸Àâä-JàŠÑ¸¨(’6ÎˆŠ<o:Æ´Ù±Ø„Şëºº¬…á)¨@‘¨Ô8ÃT«ã¢Êö¹è»Ş05¥lÑMÓW0v74-î‹Û;kæyÅ0*Õ¦}oëKó´,‰âdÉLòÆ¼A£0Ì6LıÄ±?åÃz.ÃÊ ÉØ“¥3`3°éPÒ/Š0½2h‹Sw°Ê\"§•ÅãŞ|İ9Î¾¦u1{håç<£»îÜŸ\rÏãóúÈû›#¡PĞ†àÖLÕyX©Ù\0‚™S8rPÇ\r\n2b°¢\r‹Qª=H©5*¥Ãº™‚\np)å@y<!9SƒèTÅQs£W\n\r‹6øJødPÄIŒ–çàåàn#¤}\"…F”a°p‡¨8è2TbR\nIJ)e0¦ Œ%J}P»D(q:©nğñy‡GFéQú~Dï,0†²bÒâ^8°ÍÛÔÜó‹e	ºã`!ÎfÇ'ÂN¹Ha[P%‘`Âš£ëzAÍ÷=d³!Sé\rd\$0¢–‡Wài`¨x‡Åèêb` AÒh†GvòJ\0P	B!8êLÁA5.Xç0B6nÁÄ:¬Ú¯sNMI5dY¼“3l›	1(‰†I‚<C…(,¢\r†r@—“lFš\0Ny:`à½2P@’Ú±·`Ô|ˆ•\r€b:Zf¨i\"Á)… ŒkctB`èNYöß´B#Ä€‘DÖˆD«`³H•†“º]¡²t%Q2X ‚ÜaWÒ	u\$–Æ^È´Ğ*ÔÚ“2¾ÛÉñBÆd’Àõ6šídL·Cl€\r\\|áˆÁËÂ¢y1q4)¥ˆläÛP•EbÊ{&ÜT#GŒ91Y^Êû64dußOCÚDH™O3Ğ6‚FK‘©#*KFŒt[Äy9dfšDUÉÂ¬0g¶7\$0pÉ=^KA<'\0ª A\nXÀ@(L¶N`®°Ú{Ri¼\nâN£dÑhO2f<80âéIÖ#Á¼ß‘foÉáìU5Uš9¨ÁgÕJj/°\\2fhMú!KåèÌŸÉ¦ÆB¼OMI¬±”JÔ«RlÁ¾I¦8‚±x”K5¶˜·\$·IšI éÒ›C2C8h–2ÎZ»XjMR»–¶ö_Š›LÉó]6%‡2ãÛIzğGÓœïİtl’D¹©\\ŠË%|\nk…}Ú§ã#>.lmz!›¯6°IZ+L+Qe¦	id&	×Ä|d™0x+‹!ª\\rW™bIÓE´lÔÊºd%v3Ø@Ê!p¿]b×r›.0Wè9ZğÈ‰òÙ«IXËáÊ„q:©â@k*½°c\nVÆâå\\L‚²M’\n!„€AL¬x	¬ıJãı4Â)ïu¯w@#EÈÁ\0\n\\†d;®2-¥(Q9Î+§ÄE‚xKPÚLÍw…ƒâ`	ÓE]çM=™µ£Òæh»êuw4Ì»Dy”a{êàÆøˆêäÑºw_ë\0–ù6›¢ÄÏ_iğA¨Lv:Ø¦ñÜ¦b…¦Qr­Õ…WÓ(’VÀe[}4Â˜ep!fô‡	T{J¹ğWú”âÍ[¢²è\n	pÀå’Œ}{¢e ÙSBLîQídÇzi„\nhlˆ¹f¿ ^‘\\WâG´é|!\0Ìgá–å†KšVä!ã\\\$“,©§Œç+ä”é¿ò–yj’g;qA€0)ªrÍ-åÑ™S—\rWOèÖ»¥fší-à`½jënøß-d\\Xà”@@»ŠÛM¸Ğ4\$l»İªc;¨¹ó ÍÑ%Ãä]g“4P|®™æW,¯ÚÜnÏzâ<²ñû‹à{Ÿ,`¼¹…7\n¢|3¹Úçøÿä¼/•l^åø¾¶GBmKÙ3ùÊuDÉV²3–G†mP_ÀO£8ÇWÓOBOê^ÿ«3z›×kt\n²fNyİï…ry§ğ\nıƒÚùès`~NMùy?ÚùŸ§w1y¡XÑœmÈª¶ÒÜ\"²VŞÎ3!2>î9×CÆïlÕıÕÕ÷ëº‹dMÄ‹U/Ø†Ä8ú\0ÊùK\nÈ¯êgmÖÊ#ÄXì¾hoî³ëÀÅÏšiÉ¦Å¥¤ö®ê¹ÄÅ‚ø‰>ÊÄ\nº¬îÅ`neùŒ@°LL<ùÍn‚…Ï¬#¬xM\0ËìD°n_Ø/ô80JI\$h	êtÁc¼†£N'Êş·fÖö=.ï	I	Ï\"ó°(@¯dô°dæ°hiÂ2a‰KPjˆÃ2N€ÈXĞr!‡¨a¦oDJ4ë¸©Â¾·dlroÎ`ê`n0Pò°òœp·qïäp¬ñº’0ün‰\rÉ[d¾ÈÁ®é(]ï«ŒYeß\r0îk¤\rp÷OA0'@WQE.+\$ĞËt\nŒÂMŸÌ´0lÅï?ì¹1gå“Ñ‚Ë†\\\$Cyc\"\n£)p£‘i\n§)\nâ;±	Úd\$¥#ªGjº^ŠšÀŞÍ\$™ÊN¯j\rb6/Í‘âPcXüN³o\"'åÈ{¢1c	qÛã¢áğZÖÎ>ÒÒH®®¹/\$õ¯^ôX\r€V§`ÒÊoä#~6g–\râöÇœ9d2ıhöGº\n ¨ÀZ\\8böEÍ‚Öp;Jğ•ï`ÔwX\$Ri§^%¥îpìşg	²8\r2=€Ø±B\n9Q–-Ò˜»È5§(²<“Cm#'Œ­EV/& Â’Z;KF<Ò’(Å°OÂZ+äÜY,¼!!‹–XbÕ²Ö!r!X·®^ÀòÛ.Ï0i\n,æÒé/q+A-’pòFES0³®ìVm¦2ÈkäªQøÀğJEÎtî @	Ôê¯ã3„\\f“4Ì9	ƒ6êM~\$‹*µ¦X&RË ‚0Àó5Êš@pËPÀÄËo#ITBï3v˜ àF‹h*àîÆ„Tk°Îİ`‚(Cn‚\0"; break;
		case "ar": $compressed = "%ÌÂ˜)²Šl*›–ÂÁ°±CÛ(X²…l¡\"qd+aN.6­…d^\"§ŒÅå(<e°£l ›VÊ&,‡l¢S™\nA”Æ#RÆÂêNd”¥|€X\nFC1 Ôl7`ÈjRæ[¬á-…sa_ƒN‘‚±ÌvfÂ|I7ÎFS	ÌË;9ÏÖ18­Á+[è´x„]°´Å¡'ò„\$¾g)EA²ªxŠª”¬³Dt\nú\"3?…C,è¨Ì…JÙ·dí…j=Ïèv=ššI ,›Î¢A„í7Ä‘¤ìi6LæS˜€éÊ:œ†¥üèh4õN†F~­Â.5Ò/LZuJÙÍ-xkª­¥Åè¿bÄ”*ûxÌB›Œ4Ã:°¤I(—FÁSRÇ2€Pª7\rnHî7(ä9\rã’@\";\"ú¿Œƒ{¨9#¢ì,d8/£˜ïŒŒš‚‡©iÓ,‹¢PB¿©ÌšÀR:ÆÒ6r‚ŞGÌ:†¤‘\nÌŸ”h\\Œ³AÉrÙ°hA\\ş0ÉÊb„¤%š\\ÙÍÄ\"BU·mê	±æÁPl®p™²œ-Í\"Å<Aëqp ³*²Ê+D†“M0b²¬ôLù•*ûÁË%Š™6mT\$ˆÏhBP·6eRB…,I+Xù²‹¡]5NKZû¾m%fÓW,IL´# ÛDA[Æ1˜áQÂ1Œn çá\0ÃcE#VãK¾áYÎ\\sC X–¸Ğ½ŒÁèD4ƒ à9‡Ax^;ßpÃaX‘4J3…ã(ÜÇœuáx\r±*ÿÑ(Úî\r#xÜã|Ÿ¡…‰°[@U*TM’ö=>ˆSA¨åD%UËÍZ34u¦`ûUè;¤%ËvZ©	\$–„àP®0Cu¨‚„£%H !R•K:,Ò®K%ÎnÌ&9e¨4(JSPû2\"£0Â:‘ì0ƒ¨Ë\n,¬õL‡ÑR\\³¬i*0„kSÔ”U Ä\n–‘JJçSæöí=Us©°Y@’V¬[Ö”ÒÜZï%.…6m¨¶\r³Èè ƒÂ7B’&|Í¤Â˜¢&p«Z½Öo¡æs]BºS/¦ES±:“SŞVúÊ[›»\\İ¶M¢áåù°\\‹æè²)ß´\rcáòZ•b×£¼{g¯Õ;Í9±5™'Ä—N4}kXË©¥ÂHd(æVŞÁşO.„Ñ7£ZŠ1ÏQç;f†˜	à \r¡Ô9£%ÒÂËØt­%k\"ƒychApæ‚Á¤3Á°ËÁñ€ À\"sÂéËüD¨„7Cˆeàˆet7-6,`Tùğl¥¡f†ÀpNH/á¼3`Ø±\rB)läúWÖ+\0PT\rç)‰†àò¬E«AifØ`oè 9®pè£8aá†CF›@u;  9‚“P¤d[fqt‰%‘X©\rÙ 6b´CŠ‡«Q¡i @°VnDKœ1ÄpÒ*é8‹±w/ä½²øëé~J%ü˜è¶‡F*ÀÁ>—ŒQÃ\0Œğ‡1JÄ’™U6aIÏi’‘[‰‹JºE«t­	\0M/§vM°V¹Qx¤½£ÕĞº¥bï^+Íz¯uò¾å\n\"Ëı€°8…\"4Àaa\$6‡¢Xt†Ê„-öœuàa\rl=¢(ËBXã\\šíˆŠ3ærnÔ:GfÁâÉPĞ_Ö|¾bËpè:PÄ_Ã‚\"“T!§Í9œl`Ñ¹ÒÇæˆéß:Ôœ‚Ğe•º_7¢y‰äD£ƒF\$qLyï•/¥!TrÃ@p\0 •4GM#>4ÄuàÕRÕÓİ\$¤7J Ç/¤õ9‡8è*”rÑ±Õ<(À7¬àÙC½J'ï÷ )¾¯*¡Œ\"-X’1SI‘:«\\9£…£Ñßaá¸8H%ÄÁ—#Nç€1†‰\0×€ ¥õ¿º¨(L@€!…0¤¬V²gÒJVt€IRcÅˆÊ\$Ø´gˆêdTjmQ™h¼MIcl\$*I¦¿WÏ¢¢ªgĞ˜\"hM‰I•=\npj¬RÉ±ô0tviCRî@ÃÉÂXÒÂ#\"ü¨yà:¥ì8·(*2\$\r²~Z¬[Dˆq/ÕÁ£ƒ§vHë¯MWD·…\0Â¡.l‰<FGq½‰!ÁëØFˆ@µ\"\$¿\\l5ZŞ)l'8©H2ç(İŞî<5•²7Y\n=ÒU%|\0úuH€ìÇöµ­”,È&`Šúz×ÁP(AÖœh*6†hË†ü_ƒ‘ÇDÈ,Â*’+fÁ%-Ì‰B&\$€ãÅ@\n	á8P TºB@Š.ŠuªAX³ê°˜~,Aõr·)ãèæá!VmİĞ2W<-Š{Ùù™“`L]é2©w\r’·¬]é2É¹Ã'#X»¾f7Uïä—ÃpZ\r”%ÖZCâÂF,Ş°oseálgÜdÒ+*ÅF%Qj³+c+Ï?ğamÙ ©Ÿ\nŸWÆ;o@>€7	!Zwp¤¥cŸHÑn,E•LÁušœÓy¤HÖ#û¼ªîLÉÙ”9Y\"fãÉ2¶>E²äàI¸lïá•?­Ğ’ƒ6\na¤=78SAds\na”è×z”îiA07¯ºÿAƒ­? ¡Î¤° æ,+Ö› ˆ;‰8Nâ\"\r-Ã’WóC»=HØ³]B^)9Í’g@¢:)Î¥xÌÅ\\\\nÀd;^èÆ“O ù”YÄ»fJ×Sê›¦F¢c\$YäİªPé8c\rd!—¾“R<ko.Q’Uã×ª	Fª\">2©pİÖVõAäêV\\öRú¿Q;º/˜À}	¡B)û’œpú`Ğ@Á\")E€ Aa `^”‹â•ıYK0î£ÒÒSáz‚ídubœªo-ü‚\0õßÓ×@ÜÂ(X……ÎußHB®±x],O3¢é¾Èel¹¶3ŠG6`ŞÔÙÖM‹ïq`Ù¯ºÈÂü#èú.vûö-É\$\$@\\&Äáí^pĞüO£sI\"1)(r/û\0nfÍ¼Hâ\n\"Kè%B†ÎV%Ie,(B6‚\"TàP^R¥44ÂĞ0&!ªÎ%bBşí`%Æcèx'ì)AVùNcÖş1/Ú%ÄºzÈpÂd®ôL¢ÈzÍ€îNÊM&XV¯¸IM (*gâ\0êygÇ7\ro®p\"I.ØôĞß\rAc\r#cèêÃNáÉ´g	º%Ñ\0002Qö-ÆzPş€ÑÓ&iÎÔ\$fVKØÈdš0:6alNà„H¬Bxìè&DÜÅ„öÚoÖeæRMdÜÈmêâí4LQ\\>ïFAñ.QĞÊxîæìÎÂ»&°0¯Üìæğ ØäJ[ÀĞ\r®ş\ræâ\r¨Šo-@?B¢Ğ®€È[É0û†ÈÜÍªİ\r®áÃsÂJ+ªU‚T!2Î­†İ­ÀU0Ø€G¢@-ÊxBÚÊK1xì±îÈ1Âdq÷°ÍÏÜ‘æöHR'®y°Ù¨¹ îí°HŠÒ@\0ÂéñV'ÖnÃ`\\í†¼gè‹OØÆÄ–şvøĞê×îöR15Ò)lö òì’\"(2b’rfÏDõ&Ñg&ó&KÉ&’~D¸jjĞØ„~ÊŞHNÜIpˆ!@tàC<eƒLàP0fsBº\\â*ò²½Å/+¢nù	®1Ñˆ1°(›\r-Òíí£*B])–Gæ<4\n<ºr¬OB[+RÒ6°®\$\$Ãw%ä»'Îı¢Î²BĞ¢îÀUïîÇ‚ÒÂ˜#Ñù'\n2¦vĞ%Ìğ¼.§!1@3Pdé!Q4q4Ä~Á°ro\$rÓo5H#­–.“q2é6Çñ(R8ÃÖeÃI	Ñ\n'B ĞÆwÚà&Â|cv™21 ‘º@R>>á\r70Ü¾B•\$Ã(3a5’=<³À‹3t±Ğ8“vsÃ@Ô£kS5Bş\rÅ?\$“>²0Õæ³&«Ë;£è~'âş2	9€™Çà%ÔsA7tBSİ<t/?“ñ¢#CtRP¢»7Ô<jôK>B-'AÑíEşñ#ˆ/«d¦XÑB¦/Œğ\0ÊğTf:ª_Fæàœ‹F4z\rtF´n©.’øÑŒ§[Hñ¸æ0Ğ%Ï(J3Å£éJä·=V@T¸)´Trg\$jÔ\\òtÊCL&GŞ½S…JpÎ¸Ïk³/İNe+DÒ‡NâÆöÁ\ní¾(æ­;çÚÎñ2¤#:2F@SèchÇ1Zq4>å@ÈÉ”3†›´Ï®iĞ‹!†ü+§à­RêCI\$‚]h¦,Şºbp'U6ïèî\r€VÂ ÓGÊVZæŞn#¼Œ€ß ÌŒå¼\$\0ŒËè&Àëf†€ª\n€Œ p‡‰K\$\r1Š¥%t>ûLP Æ¢KÂı­²É&‚™‘\$	µ†\r5Šg¯¤lÇ7GZú\"…;Ãóà@PÚî`DÿU÷É”ÚSQr­‚@æŒ®a¢ö[ÆænkZÄ2â(²Æó\nµ´OÂÜqãA§TBIÖy‚½2K2Z+Œ_.BW“JßBÉeVKôJ–`%XŒ(p:£Š8ô”D\0Ş	|XnK%•Ñeg{/D‘¦xpBG	uCh¶cb\$bCn\$1ÂsîjV­ÎĞíK˜IMÛ‘Y\$±VSÀ¬Yàê±˜Ñ^6j\$g8vÆ¸îƒ.3dd\"ÖöRö\"4½¿.’ô¼ª\"MlÕì^+u:@ŞÎ@îëFæ®CPG\npäÕM‚á6ÆŠ	\0@š	 t\n`¦"; break;
		case "bg": $compressed = "%ÌÂ˜) h-Z(6Š ¿„´Q\rëA| ‡´P\rÃAtĞX4Pí”‚)	ŒEVŠL¹h.ÅĞdä™u\r4’eÜ/“-è¨šÖO!AH#8´Æ:œÊ¥4©l¾cZˆ§2Í ¤«.Ú(¦Š\n§Y†ØÚ(˜ŠË\$…É\$1`(`1ÆƒQ°Üp9ƒ\$§+Jl–³‹YhmŒrßF® ÊÊÎ@Š®#eº’µ‚‰&ãÈÊa9™kG:ò~ÈdrU„I‹Ñå’í¬Âz¾¹ağëy2ÆµŠÑ¢«êòû^Ğ¦GeS2u¢¨Jíû\\nE¢†W”ü&ÖoI\\qöØÕ=räBz½~Ì²7FÂp0Õî·bv¤%Ê6Ú°ÈÃˆ•©¬k–¸;\r£l©»JK¸§=/\0X+ÄºLˆ=\$\n\r\r6°âŒ3L[Ê;ìqÃlq*oÔYÅÏƒÖh‚„A9ğs’ƒ±Òr] ÑËˆÆ¹Ä\0*ÃXÜ7ãp@2CŞ9&b Â:#PÕƒxÊ9„xè‚£€á+ÌãƒP9ò¸È¯&®ÄG…‘\$„ˆNÒ\\±éKè¸;œ³=J&ô¾é;ÒG»mZ¼°7ªtÃ\$IBÒ²-ˆS<¸o‚¼SÆ©é`ƒ‰\"%‰C éËˆı>F…R’¸JFÌ¦ŒYl Äšà'm»^ë²qsì²kR6YYÈ1>ÄÇ”kô³î¡¡i¤(ÜûSšŠ‚¥:+^‘¢B©¥¤fÄ6¶+¤Ñø‚2\r£Hİ*Ç2ó8ç4Íc”Î0ŒcÆ9ßC8@0ß²Ü»/„¸Ò:\rxëƒ„pç8NSÈyˆ\r\r0Ì„C@è:˜t…ã¾d7Åõ*Ò¸ÎŒ£p_dÈ„JØ|6ÊíUô3Jãl¶4ãpxŒ!óZ¦¶3õë\0ªW.·t,Q\0¸¼ì¼i(#é®ªNÛ\nE7]o=®ÆÙr#®\"˜¿«\n¬)Ê5ŠŠ, P®0Cv‚„£\"0Æ·«(Æñé{¶ƒJk~£½ˆíÜhs6\"^»hÒH.\rÃ²·×IòYP°Û…]Gæ†ÃÕ&ZÒ³1\rÌùrÑ;}­–¶–±qÇÕ©H¸¢©»À²]³Ü’¦õ\n!İ3È1Eg'UjéÚs°›‘Z÷‹Û1;Ğõv¬Ìuuİ®ºÖ‹ ÏğÂÖ¥›û5² ù%#¯œ×>RzrÌéï>/ø˜vÚ„›yŞBÎ\\)…˜F!Ù\$ç”ƒ;ö²qKç@æ!¶†è/Zäk®A! …Ì£¡1M…\rÚBÜ»ªBK5òœDOP~åp6p}MÓo6¨UÂõĞÜÉ1´†'8ğ†ÌG1Î!Íñ¯+D\0d	ó»‹Iò­wX²ÁPO„iÂ‚ÿìlPñ!C÷‰[qİ1ÈX@„³b9ÿƒ¯%»CäVÁ\0m¡Í3²6Œiƒ uq,=)8€äCËSj¤ÎG0|Úˆf\r!IY4X _JJ>†æ—Caª‘	])†éa,“\$´‘”<FzÃ‰¬Y(ˆ·Gƒ`tNuM)õUU0Ğw%y#?€çâFk1rS\rÉÉ2ä8ˆEF\"=r-Ù5ÓÉMNXÏ‘‚ y L\\…iÇ¤eĞ.+lÅÍ·FEˆÁ;›ô ÛN39ŒÌè„²v)y¬¹gŒé1ĞA@OUàG§ÉäŸ”\$¨ĞÂ‘R‘…´4Ü‡^N')'ÄÌ*€Â“S#L@‚E°Ğ@½×ÊûIä1ÌÒã#”2²vRÊÙk/f,ÍšÔpÎ™ã>L2Ø:5|õ[iá¸3Êr¼eò*Uf¼¬²>©İ\$NÇ^x’X¸Ğ\n‡'ÅÈæ,,\\‰R«í¥K…0HÎgFÔ¨Â \\*UL©Ì©–2æ`Ìƒ»4fÕ	œ‡&vÏYä½—õy¢4hÒ‘È‰÷1òT•n»gŠ#”–ô €¢@1­Oœèic¢Ë^‘±ò«óbªÛi­/@–-SècM¥³\\…¡¾ûTQJ=4\r©„UÖ¢ÅCHl\r€€1 à•)Ğm®80†i}NØ	aa˜:Ş0ØÃ<«bÌ`1ƒU+ïP 4·­ÕĞÂ&>‡üûdğŞôoÆÀ¦Ì÷xïÌ%Ã,„m_Àµ@PÁ±/˜³2uÁ\0(+`¤³Òâì©˜\n”¹}†:»Y/èoc¡È4‡kÂC=ëc©±2Jæ\0ÁïP½gğ§ ë9ãŠÕ)ñ™l–\"GÌÓ^>&fí%F”™€sMÌ*U%+ı™Cƒc©½8´&*Hc\r&Q²«Èj³8c2,2äÂ“Œ£%Š,–ØChZàÁ1\"8‘Xõ·<t\"4{,·›qÔ	UI'µP§s:zW	Ğ›‚:Êv'¶Æ2Â™ìæ´ÓÆ.S#•£“®ŠO¼àã^VJÙ^u\nÆ*ØÎ;¬B—5qå˜˜½\r³Q¢ŞŸÙ´©laÕ|(zfDJgŸd{.t âı’¶h‰8A™FĞg¼nhQMœK-0B‰<Ö*·ÄHƒ–‰>'Ò›,¸»½bˆ.;²Ü§sàüĞ½ÒÂ ô*\"hSUş¿Ş›I!ª¢F¶à×DèAX.UT¨»U¦/j`©ˆ•fZ)t3SÇÔcZ‘\r—z†!‰Fáˆ,h2a´60Ç,ğÆÕ\r‘¯OHí¼JP±wüsvÆ±lÕ^HóŸ×äûN’ÀxŒ¼øÆªN,ZÒCg×´8‰è6º1D_8\n{^P;âóó¢„‚UK²·ìÿ¾œôI…–Ë€.zN¸vo:‰ÜÄã)ÙºÌZò]sË¤fŞ’wÿ“\\mè¶{À;ô+\\p¹¯Æ3)1pº3¼r*ØÇŞ¥P\nF+|£¤:d81°‡(ğukxaFÚ˜ƒ(œJ£_vÀ £AıM£.\"uz‡1yP§‹¿VÈ7lAFúx×@»yÃøépâ·	4gŸÎrÓ©”:äoÜôÃŒº¡zñBBñ¬	€Şf¬„\0Ø«ò\rÂ\nl—æ2±m¹(†;\$2E­ò×â(êc îç>ôÆö<‡´ş¨\$XF»lxŠrJÀÚ¥*0¼ù¨¦n¨’k®ÓQOú72}ÄˆLü°v†oøî”A„ìèbŞé`†»`Æ\rb\ncLKcTÎ@Ë'´9â˜!B6z0œîHRî¤#‹R4@İ.È@Ä(²øÈRk¯¢ÒğªíGì}í:t¨æ+çÒuqU-êõ£Ü‡Ër­şl¤(ÏÈ2\0 ¨\n€‚`\0â¤¨\r%şqTLÄĞMDØÉe‚Ûb&Ú\"H¢¯ÚG.r€^\0r„LrgDÎÔD^¸ä\$ÅÍ,ÜÈ6ÂO1lQ©ö@(˜¹PJëŠxÚ\r²A\rÜ­#‚UÑsğ-A–)â˜ôp?Œ\\ˆ2Õnæ/)1®9/¢frˆ[­dVB3qà±ÑqÑ¤ÛæğÙô ñÊÜ‘òÕ2V¡6'\"v[ñ…h×î™fğ¦Ú7‰¤[A<,®x( ÃoŠYåœĞNQ#.TıO í\$Â&¸*’07É†ş1·²2Ë®K‚ƒé\rÂL'iÂ\\’l\"1V·GÎ«Ag6:(Ğš®>ï®¬şDãÏ&P¢k§Â1ÂFè¨Üîçt‘~ß­lêfÄ£b¼H(¹+¥V1©Ìß×\"\"),²¸tgX‘\rXóÉÚ=§Ê#ñÈ+ÇôBpZâ2À(®8ˆ‡àô€ş’üÜS-RÂ·ƒ\rÇ1#1RÓ(I›n€ëÒZB0Y1@ÔÍŒY‡¤wÂSîš¡Œ0x(Í\0ƒä„¬V42 \\p€áÃ’Bë6ÏÊŒdrëæÚº±ŒŠ-ã€w#5l|Ğƒ(‡~O>÷‹]2\n]#ßğ~mÕ Øä®bàÑ©AàÛBÃBœWîÒ\\~>Lf†.«Â\\I0Xr²×0gVôq¦+Óé²¿>ó·cæ£”à¦·0óş‰”ö¬³1“Shê\$Ó524mèà/92ÃéÓ§s\"ˆÂ?ó+?+q0“øBêÏ´I+ô!2ï^Dô+0ôE0”]C“¥+ª0è #©¢)ˆ>tq&‚œ83\nE¼E(¬!„L?]\"huåÅDÎGÎ1GoyIGI‚AIÑu\"(U”¨@ª›0ø)1|\\”_C¯Bñb;c[\nmÏoK	GG½G”ğç4İCÓ1FTv+ÇCH¥çJÛ…vHdaKÎTlK>6İM@w‡î:gÉ¢äsÄóRÃ°ºW ,*3˜²Ö€àT	ôFùtæ+i!‰ÚzcÉ ”2òÔ×P-²şrû2d*\\”³Vp	hR\\ÒŠ0×U¤ûUîÕMN‡CÔı-ò\r'îAã?TIE\"Êš„…[eP®yBck3TWF1*²oPLÈ€s?uÃN£q]§„ÙK^75¿1Ô> êúŒ•şâ¶_\"˜µÕ9ƒ—NèOYhñaÕêV¯]aïb†ÃİbçÎß26£~sÔ#{£Şz\$@£è@âpŸJ*çÈØ\"oÜø¼¸tÁ1u¯Np_“#fÈ«Kôó[Öv„êOö\$16ƒg‡a”aaôgi.Ÿ²\\y„PyèĞøxnïAI6–Šó‹]Ä	\"ÖªT'šE%‘lrgFÓföÁ7o¤.\"ÄBHÚZhµZ¨¼Ym\$‹COuóg“?°î‹uwol«4öÿZÓóBV×b96ôá÷{h”ùhÇ½i”êrIô¿!ıv3qö6İwAp÷)7·-cò71_+S?uø+×c\\õ¿qÓ2tv¥khjRãmOal—„sÌe\0Ö©cPÎæz_³b¼äçy0ÃyjlL‹Ëyì½ñQ\n÷«y—°·Àp½z`‚™%ùÄûİ×p%_que­?]	[!{/—Û~÷wa×]—;jÛ~°ßaQxË›¤”]4æ|ö3cĞï6ü¬QõN—æÑïÄZÒˆ×VNW5¯ƒ¬§ƒäï„5Ó€7| ÁK2È\"ˆ§nrı-L:Íô‚ímCVtƒIDGƒ\n\n-BÑû1Ø…óŸ.Ö»;BÉˆí³:§2ër¨Gñé*çZPö|‚pñ±èŒs@çy`t¥´«‰8ÅCòÓ:ÉŒôÒu‚fi”\r€V-cÔ#îüH…4×^;m°öƒ´Õu± ƒRbÂ*‹Tµ1\\ñ¼\0ª\n€Œ pÑ)®ş.”Üq¤Wğæ¨ÖæøcqûU½Ä“ÇÒŒó]:¢!Bœ“,›¨<y/íx%EŞÖ¯š*J‹IjäU Ç~'¶Zr)êù‚yÄTê+G6(‰/YäfWÏ+3¯ÂùkW¨aTQ¥~…ø!,l<ˆãpÇî9FÑc•Iñ\0ÑVíu†UŠäó§á”ë¬>ˆFËW19êçYñ:4Nï‰œQÒù3ú£Ú	åŠï/yoØ›Ÿ5ÁƒX§¡:m”Ê\$ÜÎ+!¤…”:#¡VĞFñ\\Î‘l7esšT°Í™£v°3å’C¥–OğÖÑ€™¹VÎÿ2ã=s°ƒsµå’WNkÉº†onHº8'0)§H!Äúò:H|îÙ¤pÒÚ³e0#oæu¦¥M¼Ü(Òªz•§Ÿc±˜äoM-øö¶hØT4€r³ë§ïò‡uDS\\x ŞÄ¨ğb¦üUöß\"¸vVcQ\rŞ°Ø Ã’"; break;
		case "bn": $compressed = "%ÌÂ˜)À¦UÁ×Ğt<d ƒ¡ ê¨sN‹ƒ¨b\nd¬a\n® êè²6­ƒ«#k˜:jKMÅñµD)À¥RA”Ò%4}O&S+&Êe<JÆĞ°yª#‹FÊj4I„©¡jhjšVë©Á\0”æB›Î`õ›ULŸªÏcqØ½2•`—©”—ÜşS4™C- ¡dOTSÑTôÕLZ(§„©èJyBH§WÎ²t|¦,­G©8úšrƒÑg°uê\$¦Š)¦ºk¦ƒ­¯2íÅè~\n\$›Œg#)„æe²œÅÓ«f\n½×ìVUÎßN·¼İ(]>uL¼Úêë]	†q:ÇöôjtZut©*#w=v…½¯ì¨pß=áLË¨\rŠ¶ª?JÒtH;:º”Äâ˜÷º’BÒ6ÊcöÁ äzù°*\nâüº(Ì:O¼-*¶X#psô¿Å{ÈØBÍPB/’¥Åj{ş«B±Zºş-IÚ¼NÒìÅJ‹GED!´Q¤Y\$IMV§.ĞË<SPœw@H<Ù—ÈÛx­ºmë¼^&HÛ¼­ÉÏôˆÒÈÅ4‘Äš6Ø´¯º| /¤½\"Aj”U<#²¼›'Ë’Œë*Io>–¯)‹ê2ñÕ,™­pë§,6IÒQIó4¼»Ï»A§QÄU8\$äXŸGKƒpş”Å\n‡[+lšº\\OjxIÁH<ÙOî«ü’JêK,Æ’½9¡uÃáhCVxÛ3ªxú+­›u	,ÈÛ7bÃÅ5VIuk\\9ªâ´³\\;MÃSÕB/vBâK[’Ü»eİäŸ>SzATñ•Ã\n4ÉÒš®ÖÖ\$[cB‘a*AÑdŞ¨7¸	F4é•ÔdN¼ÔucœD õ{Dúİ‘Š·–¢vbõæn )ÕuhPöq§SI.çN2Yg´:\n-A-Ú}åPˆ# Ú4Ã(å`ÆVèˆÚchö<Ëi\n|°.ù‡oC¶f ú¥›Ús Xƒ@48ƒ0z\r è8aĞ^ü¨\\0ëÛÄ\rãÎŒ£p^88Ã˜ïÎŒxD³ÈŒ£=G…5Ÿ‘xÃÖ×fÎ:Ú¶­·›ü®E§ZÑAh1\níûj7¶Ó¿3âã?/8…ZAé:âDìä·d¯V…ìT1Šû&ÜË‹±«]9U&Q˜ÁDà50J2}8’O?¿ò[K{é\\¡¼PÙáDJ&µ’×ŞÒŒã(İ{/´ê÷˜Ùß}'±1“TÉÛ§+*| \"FHœ±q|İà=’PÒ\"ãOğËÀE³\\%(Äé~ Ät'	ÒZk]	†¦PØ».f± ¨¡fÉ	dOl¬,@Brtó’‘@[älÄ‡|)„Â¨‰Ì8î±Ä\r\0	Neå*À–}Ş‚cËùæ\0 ¦B`-JË1X\"ÆŠAáéRM*™9(âò•2»nHt²3ÓÄiŞ¢,zËµEÄb€¢M<|{¯N/É4¬O\$–JÒeX¾O’»XZ2‚D·dÜĞlr’åÆ>‚óĞ‘ä>EliÍœ4F}V¡eAÉ:+¦U‰²µõ\rf!ÎY†<©>>N–éy'“R/Íwö‡c‚óVR ŸJ§Æ“!sG’îQ-IĞ)§…e\rà€¥ÊÒÙºnÅqÂX––Ò=)sí—ŸUè\\ZKàzêÉiš|í]¹ƒp³ıh›2²iÚÒ'’…2ˆã’·IƒóÅ#™n\\S]ˆ¢™5HYGİı0Óe-ô:¸–<™	©cR¥Ş_©n4ê%SÀèt6bü»Âøş‘ò/_é\\4ç\\)™¡o˜¨ù+4õ@šjaŸo€R‘º¬ÖÓÁéJ\$¹¥Ã™¾Vœşd“„Iº\rUác9Õ°®Öè¤¥\\èª‰B7µ+ÓkYªõf.¢-Áçk­}°¶:ÄgÚëFsd\"™Œï\rMŸ]ä©¸7\náÜK‹q®=È¹7*Ü»™²®qÏ:Dxn!Ğ4Û—TëJœHR(¨Û×LëioRÎuÜ¾YQ]†Hu—PŸUô•¶v–¦Üx4•˜ƒµ¥ÃO—Ì|´%²¶Í[t_WM.ê¥ò»vÚC¢ŞîßîyŠ½\0—ç­i¾æúP×°§œÉD¾“;Nâ+Šq9È9'(åœÅ”snuÏº@£¡võÑ:°çp\nìÓ/5‚ëLØKqi—+­Q]+‹¢niË\$¬˜æ,¥Ë¤ü¥5çŞ}Y„h®&Ìü=g©¥ß6&)—?¾5=½\\xwìJú°Â™˜Ö%§Yï>A³²8Ÿ-5³âúÌ—éy\0Häø’È¡Ø'WÔX§0Û–,ê]‚€H\n²/µCO€iK?Ùë! PYMígö>R²6ƒŸL¸­R>ÃË¦–ŠˆÓ=A“[, …Šƒ½Ã©¥5r¤49·^ÊàÊ£§¢Ï“Î”`Qù|äó­t’úcWá¾+œ³’s\\í1êşƒßë¿–S~w¾µßÜ°s*ùGNT\np@Â˜RÈqF&N\nÆi­õ„Sd;Ç›ˆ;Çy›jcjÔ#¬\n¶é?çPè”fO'š2ƒå!AÈérÃ&ÑGˆW.Õ§WŠí‘ÑãV^ZOc‘„‹çÜ¤Ò3&{n\n,ƒ÷¡\\\"â› é´²njË;\$;¹³ºÓâ–\nPœûÃˆİÇÀ‹A€«îb°`âv/;È­ì•Ó¹¶A¹êâ£¢T–Ûh \n<)…B6Ñú)6İ\rÃ¬Ø\rMA§Óz\"çº›K‰#Q4X¸Ñ—£SÅK‹ï\$ÜÁ¾@b\n#éDc’Ô£l‚¦ƒ Öõ@©÷Œ³º‰èÇß¨Çp™¾  ‘Ê|©oÛ9µ÷‚Ò[/PR—Q›³+¼Ã#ÜR^fæÅ±¾_,¦5`Îgo¥eĞ@CzK3¡,Z›'?º¶'UB÷ÉZAâÚô™&N¢ÈÌ½,ÃOBn¦˜?äê.ÅøÂÈ˜C\"©‚ClÈ÷Ş«Wà¬m(z|NªËÒ®íIgDÍç\$ˆô)ÁJSõàn]¼ÿ|\nbÁ)½Òie>’¯tZ/<]Æêé±\r>_.°­åz—i˜eÔ#e2<¯Ö2\"ºkúÁ.\"Î¹Ì¼f§zØ«Hx/Ïêÿí>#búœÄéPÓcJÆâw‚†Å'CxM%˜N4]J¶>ïì¸åDmü>BÌC’® æêMñ)´›æ–Ïf.¯E˜1K@ÊÂjÅ.¤c@e)ıjÎí8…èial€Ï(çl …È°œ,¨ºÏ¼İ¤¬fE¢Í¤`7«…#zà(Zd¯øTüS°\0‰¯¶ò­TùBÜ+kÌF*¼ÖÉØıƒö›ĞÛ“IÜ„ëÒDk¨¥ÌM.A¥”Ë‡·°ôŠéÀCâtôL#j²/¯ô(n\0„¢eD‰G\0è+nÆadˆ­ˆ«0~€¨&®û£>“cz’(¡eÜı,ÂÀi®¯Ã2ûñš“¨\\+-²öp^_EB/Í\\¯„¬tåğl”ì}èÜŒ,YTŠJ0İñÄÔåÎAã‚\rÄ\r,B\0Ä(\$ÀädÁ)Uˆcë­í~øk’´H2½ÉZë©‚FRfÉ°\\ÁOÎ ¨\n€‚`6¤‘Şolvd­dc…pæe@'OŞZmjœPèÅ6ÇğàA(:ÇÇúüùÙĞæZğ¸ùfn•P|\$dLk»:>ªã)õ)QxJ/A\rŠ+ À\0=b1)åĞ ²¤ºÒ¨ÍêW,‹ÿ+BË+‚Ã%®2aÒÂ/’¯,¦pÆÒÜó2¬ÌJ4dÈF°F|èÊ\0id€ÈğPâ¥õ&ãTN¼Ëröş³2nOäÙòş’2RhGRlƒÏİ)‘–?Ó5,3£c3òû.s‰Y)2ğeQ†ùÍóÇÊ©Ï\"Eã¤B0É.íŠôëÄ¤âthß7Ó›ã#’ö* ?\$Ïf~`\nNTŒÙo¶Zs ñÖ…ÉôGCŞÄeDg>I|í«òˆ¸C§–…œáN1\"Ó¬eÚ”%ô>ñíû’ªgQáÖVĞ„ü\"úË01so4Ss\"n„}”\"ş'#‘DZ‘æ»3	C2³Ct×T>}òkC>¤n¾P¨—ÄÎM)CEó:£ñB‘BÅ<”³CE4wt{IÖØ©<°+Hp-Ctx)Ç*1±Ò±IôŠä>ú&tù*Ğ/\$T˜	Z„O¶„´(ªF¾fE\"–P7ï2Ôƒz†NPÌdvàHèÆIl×huNh\\õÔä4ë¦¾¤/\n˜4aş‘‡5Ğ'7Pó.ÉJ´'Ğ<jQÁS\$5CJt ‹¤%ø¯¦ƒ±ÜØ¯®‚4ºú`†· É LI\"ğ@iBÇGÅ5HI´sH…q°S1IP‡Ì°NâÄ«9G¯RÓGV•ƒVĞEJiSR•{K0UVôüáFÄME°°ŠL7©4ºÕsôƒWWÕÈuÌ”°[/—f9GS^	Ş¹§7e©ZÕÛ\\pÔ—«Àtl?5êRuš¼êCƒi:Ã¸”]M#CÅ/raaRüHÏªş˜Ãaã\\Uœò·aÒ–bbQˆ€–+/2À‘v3JÖ6_1¼¦òT•P+d¯ÿ4…ZöKfÏdoòE[¥Ò|—a6i7„õöˆ•UçZ´OcV™_)ª›Lî>æZ'Àâ÷d5ıl’Zu€[uj+´Ù!¶~Œ²ÎréV¶ºDvÀù¶&Š,ÌJÎk\$† æ–^1¿&âxß¯{k‘`‘Ğ†/„zçPEìˆ‚:^‡g2‡nókÂŞÜƒí–Çbj5JÇF‘I\\N{—;—MW–Rér ÷TKõÒZt.©õ™f­h{rAJ–§iuğH7b«{jK#œ™’(±¶wpÍg×]wD‚™jn/+_µ\"å5'wõ*âI”{P§z×Šœµ­uõŸy7ªa”-÷´¹¦o@—©|7ÕZ—Èïbu=¦n/lFNÇ0dVK0ir7øz«úØqB¯Døüdèóyt÷òİe¡yµuzsj¤ƒ>£z­,}[ÑŸ|·¥`“È.\$ñj7ëI˜/x3¸+hbZ¥t•÷‘#ÇÀ;×şI0o8_I6‘†P^8Z¥xM+\0M¨Ø¸lâáG‡6Š”ÒBÁv<j(0úXO“m~°JváµÑIö{C9%–ƒ&mëbG|d{iCAŒW¼ŸsŒÃÿ}uı{xQ^Ì±…Xİ2˜·W²ÊØèì¸²˜î/‹˜^—Ş'U85•q‹Ñ	‚ØìM˜ñ';¸Ã^õİ‘\r«uxctØg‘\$“›ˆ±Ï„DŠVÓ’Î1•/9˜xü7[GŒdƒ•Â“~˜Ódy/ù2şG–=sñàa¸:ÎÜFx)]Uw…99™+ë‡y†|9gd˜ÛšËšYEŠÃj™#˜Ù·©hùeœH1›™u”x#8°}×âÅÒ\$\\è2UF)ˆã¹‘vy‘¸ÁxŸÅSDI\r#¶RK÷Ù×İFù±Rú³9óeY»„0'H£*ÚW8DV¸I3àdÉlŠSädsşş*åcò¾<PhYQÁseğà6jüõyy Ç4­×¥0fo:[Q:^*éC´Í{–›\$ë\ryàãk¬*Elvİo²F:g@r‰§'öô §Ï§Kög{µ.Ù{Pù…œÕ¾¡iL&`†¨àØnsxä›i¯#§ ª\n€Œ pÎãfˆ‚_p8ÚÖÛ#Ï¤öóeZzKö\\#oNã6¯~yU¬G§Qb±)¡b›dRšÚ)œ4JJI‰éúÒ©¥KÙËsÙ­]{Ô’ïmAU\$_ê\0ZuªĞòƒXzÔ¹t²nEÕ õÄÆ”°p#²ƒlŸí”`ªãJv\rŒivÌ€äòÙŞj:ß£@­‚'íDä\nüè…à+¶Hûf0~|ÚËb¶„qëstRÄe£†%³ùŸJ2yq/ğ¯õ`Y|èİ¾Iu™î*#Â6-ÙK½¿;B-Ú]Ÿ—vğ5œKM÷ÀÊ¾ V-`Ï[›#Á¶\n\\	'…³kB7xwg¥Ûãªû¬ZgtU6é¢‡ø ka=L;ÙUB(VAW¾@·\n[Í¥?5R|‚E³ÄåÆEŸ„ë\\š\rn°‹T¤GMˆ\$p6<=h€ûïæìmCŒ‹<êJI1+¤M0Âtmb·¾Ìı<eÇ“d‰s8ˆûû\"‡åøVòv-§»(\rÁS§«¤£Íf·É„¿Õ0R†+bÃJ@“äÑøÀ"; break;
		case "bs": $compressed = "%ÌÂ˜(¦l0›FQÂt7¦¸a¸ÓNg)°Ş.Œ&£±•ˆ‡0ÃMç£±¼Ù7Jd¦ÃKi˜Ãañœ20%9¤IÜH×)7Cóœ@ÔiCˆÈf4†ãÈ(–o9Nqi±¬Ò :igcH*ˆ šA\"PCIœêr‹ÁD“qŒäe0œá”	>’m7İ¤æSq¼A9Â!PÈtBŒaX.³ƒ	°B2­w1{=bøËiTÚe:E³úüÈo;iË&ó¨€Ğa’ˆ1¢…†Øl2™Ì§;F8êpÊÃ†‹ŒÅÈÌ3c®äÕí£{²1cMàYîòd2àîw¼±T/cgÈêÌ’d9—À¶\rÃ;P1,&)B¶‰MÒ5«ÌÒšÖÃ[;Á\0Ê9K†‡´Œ(7¹n\"‚9êXä:8Œæ;¬\"@&¥ÃHÚ\rprÒ¹¡ht*7Œ:8\n0‹rŠ‰¦Oãˆ¦)Êƒ?»ê è:Æk8ì°¡mx*\"jk>Î/x&¯æ)|0B8Ê7µã¤±4\nk\$6 ĞºJ9A«\nÆš‘±«Ë-	‰Zp£³ãô”©l¬…4\n{s-Irâ¢ò ŒR’9%Qq*Á#ÆåiÀÎ%4PÅ¸Òé·•\$R9ÅqhX‰HĞ¿ŒÁèD4ƒ à9‡Ax^;ÙpÃL§¸\\°á{TX9xD ÌBşòÃ2À6¡ãHŞ7xÂ\$MÒ¬2[*ŠTl0ÌÏÄÜ:r˜ä:Òó2î4Ò#S¬à‚°á2XÎä‰p”½µ+Œ+ÅT‚„±í^5€NBdrM‘\$h…’¢c-­Pk^7HÀ‚:¢m)HÀÒ¢2ŒÃê6E\rÎbÿ^M\$.¶ˆÃ¬\"1µo\0Öç¥èKßF¶ˆÏ4CJ<ã&¶°=‰7Û(pÔ:±sM\"‹s@ˆ:ß©ÊŒôRã^¨5NZş‹nhÄj6CbéiÎB(‰‘¤Ø„¡ÛXè–¬ R6À­¸q}ƒâ5xôŞÑ0å	Ït\$2¸ sáQCÖ`†)AÖ%=•tÛ²V–ñÂ–yh¤#HôÜ-¢#í’)Z–MG Œ.+Ó…æ+p„vİÀãiÜÊ€uªmx0Üş\n¡5d007mŞW·\0‡uŒË«×ùÒ#˜NñC,Fä?à@X±1:ææ@‡ÆCÁ\rÊ¥u‡2@‘şi	àİ›Ö:CxfÄĞ¤˜VÌ¥‚ o1Ë 7’3Ãª¦U™£—Nš»MĞ¸0µø\0:šg”0RH©¨œ£Ç\0nI\0T7èDæ.e8F•P S\ni«°ÇCIûSŠôß,„±2ÈYK0;¬å ¦Ö˜rZ«\\Û“ÒºÖğ>«¥\0¿Â@—à¡c-J˜òCYWjR'«2`’Y%¨½¡un®VâÁ„T\\ùã+DŒëc¬•–³V|\\QÆ9†å­Ld•Ë|9ƒà’âC7ò&ĞĞkäHRÀå`tEå€‡Âb„:³,ê\$9 f®9„.(†‚©cºVA°62‚”…â±¯G¡„3%8¨a’¸†“”x\0¬™h›Ä0šğ@_¤ôÉ\r&v3DmÔR„	ÇéöÌ	¨™ˆHi\r\0€(€ J¨I)\0œ#VJŠiÏ!\$û†â’ËJ˜*Pãœ™<’Ñ;ÿ9Š}VN@Şäñ 	Á”Ü¦cø‡Éa.³uÈs˜ÆÊ§\\“êC†ààÏ\$ºÛG¡Üê:&jƒ:ÄœÕ2´cN©âüÇğ³„0¦‚3’“ê4¦Ds#¤‰¸1Ó¤:’¸ºZ# 5ª•Ğ³(Jª ’óYDÊ[YÀDfwOã-‹Ä‡“xCc\n\"T13ª_Ãˆu9h 3!”gÖŠœ:f2‘é!gbÙ9UØ¿“4YÂ€O\naQ{:kŒ‰s!ÄòæéR\0k<¥ÄµÙHC¹ú1¯™®Wô¤Z¢ë2’u€†“‚‘`í]B­`7¬õXÊ¨ \nn@?˜\n[	HF\n”]Ê'ôLŠí´Óh9's²›¨Š4%íÀFÓÂÚÔˆv!Wy­à\0U\n …@ŠB”&‡2KD\rÉ\n ¤`ğ@(L¸[a¤›,ÄX‘«¼ä™ˆm)/SdR¤Sj‘-Íà_ÌJùşyM¸‚VêIËhPM†­z“ÎÚÎS!yUVÔ dJë-´ì±=lîT2ˆw—\$Ó„%îèÊ\$<¡éc*†j\rptN¹Ø:é3Z‹ Òp9¤#ˆ†U>…_6˜Õ†â_wğSH	MÄïœ\0:€Å,Äs{Eh½!éŸUõ#fÒ4!¿MK‡¢^E Ø'âŞn‘xÇùû¸ˆ‡!	LKË: bÚ V}/q!Ö{A75@%Œ¿qÛ®Š<F3.´4á6f–;®Ñél¡ß,±9<–Üjh/“x°‡H«i‹Øn?uõÎ°÷bÂ‰Æó0OH°\0 †Øû¨b\r«%BšÜË<‰mäÛMàÇ_wñ™³ÿ‡ ÀYˆq6y.ñ…ş0xwì‘Ìˆ<©\$’T9{#eˆ:ùCòÉjŒX¶9\$ôEf\n!„€AhZz!ƒöiïN[”K)ŞœõfÁ8/\0)“›fVmdğaêæÙ‹åù8ŠÁ“„.&çŒ•ëÊ»ê´¼ŒV'Rp	\n½~CWÙ	ÃìóUÙ†îÔM³ƒÛÙ¹™åİ;wé]è–wÃßúÁ·7=×·p3H\r¢pîP³Íö’	“\$Ÿ§vØ_eÎ}£¾÷I×üvì~/³xÚóá½z³%>'Ùú¯?ßHûÒï›_”2È)Á¤èË\$ƒÁ»vHé\rñèP@ICĞBê382ÎeIãWAj¯\$k‹°àgL½,ÿ/¢èØV…1\r“õ¯€œŒÓ€Úa? óP>£X ‚J ÏÌÔ‰Î„ô!#NÒC\0Ìç\0&&+ÿ¢R|c [/nŠ-Òî§ŒÎ­MĞ4¢ƒRÑ¢`4íqOv‘ğ0ï£NÆ¬nHD?Î\rã¾Ê@ÈR'G\0Bº°“Ë¦-\"ÜC àÂõV¹„Å	qÉnÎ’?À­àÉ\"Ú/ô?zxĞ &œ\n‚Ô\\ôM\0‚\rŒ®İ ĞN‡îÇ-œŒˆÈĞßÅÖ‰¤]e\n›+QpIĞóÒ·¬Øî£Š¤d1†/İ\rÇ70îÍLâÛJ&N\$ºÚÑ%AğV Z1ƒOI±@®ÂVy²%î¦¥ Âòlò¤`ÊQT'\"1£9²ÎO*v\",î1lÄ1;Bc%±~no‚ÒNÀN°÷.á\0‘áL	pL1‹ÿì&Îë¯Zß‚ğéşà’Çd!…Ä\$czl\"Ò<¢š1\"Ğ-Q_â^'&`fBS«Ñ-òK±€%\"ÎìâÀoP7Í*!+\"Œç\0Ô„±¬À1°áÿ\"SCL{ñ&5‚Üã‘ÔÇFÊMÌG±%‘«\$Æ)leÒO1?¢DÌÉ¦1&)\$&¾‡šaQ3‚†yrx<’SqªyRw'²d1¸`§>àf6ò’mRjÁ/¿(RzË\"l6„+Æ Ãr,ÏÆYéªÈ¤–›€ñÀèüG…Ò]ãO-£9Q) .Qq’™ŞëÚjFü\"æf8 ŞTğÜ`nZÏ£È††§0S˜òı0Ó.òJ7‚°\"ÒŠ4R%ó:î©Ó\ntxÒè=Ò›/¦\"\"Ç£*§¯5†i³?âA3r–¯\"A5CÉ3bÜh‚p\r-³8n*7ÃœËY&Ø#„\\á ÊáóŒ9ƒJµƒ:Î‘8“¹C:@ÊµŠ\0™³›©Üâ“²Ä®?f¤sÌŒ#Ã4ğ\"0³ÏP//ss0ˆ>3\"5F\"ãÓØá¦5dµ)˜mB¾æä-óÕ@¢ÄçÜ&@F´¹Sç7\0	•Å/|°¢Ú	â–xâØê„{2„Bì\"Ì\rÆxõÔFÑ–ö&\0'x÷W0ôZî´_E (f°Şà`8fBËæÂ;æNc0púêôŒ‘ï)ñ€¨t•\0¢Îí±fòíüMÀØi@ârº\$s2Ñí®®ZlQØ#DP4ª–\n ¨ÀZ‹ğè@/FğØôì>¬nàëqzT\"3O1KO‘“FÕJÊv|ƒ7«†|èu‡°`…ä\r¢`ÁĞª)‚Û-´Aï®4#zóÒ?E84½˜IÀªãOÌŸ£\rä\r\rU\"oUdéĞB–?4¾p\rüMn°tfæR#´™G¬LĞ\"Îõ†€´ıu\$éC\"•—X¦eUÔõYMrÎP;•±läm‚Ò5T@!5»Xƒæ)rNÊ'ÔhÍ!ïN{	úNİÌ†o1 wµêÈ¢s *úN<Dx8pòP€¤“Îº\0Æ¤¨ÈõÂxÀëä2Ì¶s¢b&Œm+ì¥&ÎTzÀô6Jî5u9QF.ÆPÏõ`P–>!oü5Ã_¦ÖNàîİ£ü\nCDÍÎ\n²Ğü¯ä´) "; break;
		case "ca": $compressed = "%ÌÂ˜(’m8Îg3IˆØeL†£©¸èa9¦Á˜Òt<NBàQ0Â 6šL’sk\r@x4›dç	´Ês“™#qØü†2ÃTœÄ¡\0”æB’c‘éˆ@n7Æ¦3¡”Òx’CˆÈf4†ãÈ(¨i8hTC`ÔuŒADZ¤º„s2™Î§!üÜc9L7Š)ÎI&ZMQ)ÌB™>¡MÎ’êÜÂc:NØÉ!¼äi3šMÆ`(Q4D‰9ŒÂpEÎ¦Ã\r\$É0ß¯¾›QªÖ5Û†©’Mç]Y„í¨bsçcL<Ï7ØÔN§	]Wc©¡EáÆY!,\nóN«Åê¬xmâñoF[×ë7nıñ¨çµ†^¯ ¦ç4C8)»lúlŞ‰-¸Ş™B«26#ãÓr*ÃZ ;ÈĞä93Ï(Âñ0h€È7»\n€è‚;hHåæ;³Ã\"H)µKSÚ¡`@:ÂpªNŠ±¡È\n«ò4ïË\n¤©iêœÅªCJ¨÷¤8Ú10((‰¤î<Üˆh”›BD¸BÂ0<7\"8ËŒ££>ÜÇ¼ )¤N¤N97Ãj†·kƒ>rlíÃËr!D¬ª‡3 ¢HÜ4ŒcJNÚ ‰ºa–p=<4ÔJj0Ğè#\"ST£1<R7Åt(áEl¸ÇQÃ:#IÄŒ î]rÄÆQ ä2GÁâN40#0z\r è8aĞ^öè\\‰Ué\\Ïá|Æ(]”2áŒ\r¬ò ÑÌòdÌÉ¡à^0‡Ñº62ŒtÌšœÒ€Pš…Qr0NÉC«0½·8;n5Ò2NÙ6˜0ß‰ÓÚNê*ËNæ_é³è+£½z‚„£ @1*¨–åùŒÑDƒ¨ÚÑ¬ËBÕDNˆ´ÙK0.M\\Èz\\ÌŒo¨Ì0¶q[7œŒµ€É™àŒŠàOÍX3¸¬½IPˆi\\\$ãŠà6/°Ş\rƒ`ß¦‰ö\\Ü§Cv7'O ˜a•©MV&I­è:åÉÒ8ªÉ5f±PZŞâØŒ/×\rÀVê20!\0¦(‰€Pß.j»Î#8¦=—Ò*\"W¼ãXæ*¶¦ûV202;Ú½ºæ9öÉ•%N]lM‚Š›ª¼ÊÀP¥ 4SDø8¿æP¿3@Šç¢ÔªÏ\"ë˜ñ\\w~Ñò›r\"]ÿ‚Ü¸²´í4(ÉBÕÙ×{ÃPíPDPøa+é~GòÁò‰\rÄx½Œ)øIç¡+9RLñ4nYúÁ—êRJ!‰4ÃT(•È(lNÀ(åÂ0DxfÈQTRnÒj©3ä*ò¢L”kçP¬Ocf‘ƒy¡?(ù;’\"Ã	¢‚LÑW¤\0Ê\n˜)†ÍêÂÆpŒñ‡d*Ä2P—©-H*ÕÂ‘ô8IdèŒ¬ãV´VšÕZëem­Ğî·ÕsÑ\$k‘sà^‰ÍøÀˆH„F`y‡\$åà…Åóƒ€iH&çĞ˜Tº™#\0µT¾sjÁŒU#+¥£R¥&L\n6ÙhU¤µ²Ø[Kqo.	¸Ã’å\\ğ%B9»È8p!íÁl+.ü0†µæŒ:©\"Ä‘2‘ÇlFŠ¡ ¦¨š\"Z*\nmGEJLhKH—‘@a¹0ÄD',i&Ì¸0†iT‹\"!ˆÍÂ\$Å%‡4ù'ØáKT¦›dÜ5åJb¶3|jÎ­¢e°ìLHDÖJP	A>FòAF%\r+•.xæä]3²i#4òÎ».W	+õhD[ˆoá•(Šö\r%<\rÁØ—!FóA\"^e\nI£w¦‚ó\rÅ¥,™\\ÍHc\r Ğ-V^D*ÉÂjâ2ù¬‡BS\nAüTÁASp ‡¯ô“’tuÁü#«Ùõôn	Î\n2mä~Á¶çIi/&*H×ƒ!häœ£@’@ÃÉË¥Œ¹)ÖøwéévÅP¢¤	C¡¹I¡OQ‰×0ça×€ Â˜T Š“\"IÛ¨²áJ¢;Ò®‘­â”DÑ4[6Å©db3&nÊ\$ØÄÊ¥³°²Ñ9„2ƒyWáˆĞ9ç@O ½€*REPË‰Š¸=h¬3Zå:y#Qò4&5RAaÁ1)±9\0\0U\n …@Šæ°€D¡0\"áeğÍkogLñ-R8ŒC‘.™æˆ JÜĞpk¤ †bò~ÉÙş@ÑƒÂå'\n˜Ö>x¦õ{ÓKÆRtœÑWj)ÂˆêÙXÄë\$¢’·°•>5vºIŞ)„uÌ!»™M–3Çv2‘u%‘›š!@(+,ö™\n«\nÄ‰O‹3\n ˆaKl*\$µJQ)\$(Í\0007C¢.ŠğIĞ.æ_Øn™}¹Mw…P…F ‘Ğ8iMUDˆ¬Õ˜Uˆ“á5:g1(‚·£–*«.¾ºÔÒTIj#\$TCQT@@u(wÎÊiór¥ª‰Ÿ‘¢×\0¡†#[ä  ¬IÔ±ÓøPœÙEERê+]İlø×Üôˆp‘åƒ“lğ!ÕÆÈ.e0Gz±0\0Öã®Ù3ØÓ}Üÿ©™›Oåûh‘ıŒÌ˜nn!„aŠuM9•Bª@ï€•Œ½‘èÌ™˜Š£õ\n›0¨T!\$lgÎÄ/§ÊÕ¾\nˆRPü@7b9(XTnY /\0)š?Y›.èÏÏ|ÂF£_‰cH20!ıS •R>è¯3£’‡Pêƒ .0+Ÿfè@À:™+ê·k¬nµÒºçLëÎÉÂTÊl0M÷&e;³õ\"/€™§=éåK´~Õ:´;hı‹ÀôèUGzi;#¶x·ÅáMÈL&61|hDÈ®nä¦<‘qnÓñr)HŒ¿WıI¨a‹Ë÷êü”Jj€/ò¿!Ôìèì%\$H@0sIìq@2-±|B\nÃIßVdm\\oB)ò µb•W”NTóh­ÚLµUaìK4‰ò0¿Í6Ğ>R?ïGèOŞ/à’zµÁ]†0Êä^.èü†.ÙÏĞ5§s\0‡Ò6„²AìAcr^)¦Pên@ä(IÊ2¬l3ª„@6Œ/ Œ;PiŠ@S Î„€\n1ĞTÄ¢Ø&pZS\n-®ş(ÌîmÔDm¾\rÂ'B°g¢Ò-`Oè@U€Ù-¤\r\0ÚhrghRp¢>qÑƒäk0pÿo¬ÿ§ˆÉL·\0êÔ©e4Ebv±ĞÌ,ÒÉbo‹ºÌLÕ\rp³NÀ»ĞËE)Å†xz&PÙ-	ÇooÚw‘\n¨Î;c\0ã°a£24nŞ–²¾c®Â/ô¡± »\$Mq+ã±îÀËål¬5­â%Q\0ƒÑ4ç‘ZÀÑa\r÷† ŞQ^·b„‡Àå3Ã@çcTuÃ\$Ói,éØ)Ï^^ãö30†GÅ9FH¦ólÏÃ‚úïãñ¨5L®Ëq,–Ê¤©\nO1¼‹ÉÂÀ@‡­æÙ§ÔËQ’4m8QP:…Xà£öİñ¬gdñ\000125Ğd/OùmÙ‘%9!	NßGtcí™qU'¸ì0çRĞ’7!ğÜarBİ'U\$5\"‡Î#R6hLâ£r#\\x±ôÆÈO«çl™Dê#1!q\"zGÒ\rÂO2‡ÑS’”í¹\$å8ˆÇ°rÛÂvÎv6æ¨ÇÌİò¥2Tä²°¨\\>‚ÏqÏi'ğ·!‚ğzxzÊ‘\$P-çªzéE(»í.2ğSM(²ø²á.IE+’*S³/òü2€°Q¤-Ré2|„²ªb¢w2-òä}2¥ŞÀÖ9¢„îvs€ŞVîo3íı4JÎçcH•SRŞ³V5“F²u3R/#-úŞòØÊ¯ÚC4àL²=\$s„áDC)R ó“8“î†9î,.à.\n†Œ[ç%±;#&-âã/Q<.N°Ó™\$sÅ=\00j¢ÕgŸ@íæUk˜mÉ­dFbVf’°ùNÄÿGü÷“ú§D@Wb!I¸nFxfvšb.4¯vb;(´\n?†Œüñ8ÊK‹BÂŠeÆJUHKb„ä †NÀØkhS~ä¦¦ƒ038àä¦ä\$€Œygìµ‰î‚@ª\n€Œ pƒHp6å{ò|xëcŒ~tCƒÖ{ä_I.áIdº³Ç¾’§,/¼k+Sÿ\$ÀòyÂ6lPU,‡ÂBCªHMâChâ¥,8âğèc¢2+VjCI9m`eÅâs£ºó+ÓH„\nhg‚VêÉ6,Åhùb„K®Úp²wfô5ò`üƒ°cå@C*i”âO\rÕ.Óõ3IÈp“5@<5Es<¯S	ŞÎâ„5ƒŸ5¤;4ã2%-F¨­<6c°5RfæÌ`ŠNçu[U&5bK\nGq\"!¦\np…{\nu˜aPŒÜbèF1ÈB‚öj´nÔêêFtÆ5nHLèØgTUƒFB€èBÔÄÅr.ƒ‡&\rÍS²aE©>…I6ÍğvÓ^•>Q¥TÇDÀ îÚFª	ÆPàƒ%eâhâ†`eV	\0@š	 t\n`¦"; break;
		case "cs": $compressed = "%ÌÂ˜(œe8Ì†*dÒl7Á¢q„Ğra¨NCyÔÄo9DÓ	àÒmŒ›\rÌ5h‚v7›²µ€ìe6Mfóœlçœ¢TLJs!HŠt	PÊe“ON´Y€0Œ†cA¨Øn8‚Š¬Uñ¤ìa:Nf¶¤@t<œ ¢yÀäa;£ğQhìybÆ¨Ç9:-P£2¹lş= b«éØÉq¾a27äGŒÀÉŒ1W±œı¶Şa1M†³Ìˆ«v¼N€¢´BÉ²ĞèÔ:[t7I¬Â™e!¾í;˜‰¼¡”É²ËZ-çS¤Dé¨ÕÎºíµ—fUîì„©®îÄFôcga;da1šl^ßíôBÍ˜eˆÖ64ˆÊ\$\nchÂ=-\0P•#[h<ŒK»fƒI£†cD 0¤B\"ĞÔ##Ò&7!R¡(¡\0Ğ2h‚D(IâX6²£«n5-*#œ7(cĞ@Ã,2a¥)ƒ“Ú¨Š˜Ê‘„bYŒ T=&Æ#Ğ0 ¦)02Xô1Œ P„4¦ƒ“@)¥†)Jã(Ş6ÌÓ2ŠcÊz¶° Ê9&ã’ÄÛ¬ëHØ	b!+CãC2ŠhÒ4@æñs(©@0ÜÎ!Ô¢2À¦oè‚2\r³Dş§!#\$O'%ÍñZ©ûóFÄ#H@1±ôW*\0xò\"ã(Ì„C@è:˜t…ã½¤5ET¤8^1axá.cº@2á1ElÈM2Ai`xŒ!òD+.#›¤…ºëèßÀôÀè¡\rrj„\nxô6¹csÄëµ-Xë„aXc˜ñ!4£œƒ2Ğîr¨¬0Æ,QBĞ£%v0Ç\0NS•ÇÎ]•Y`èÈÀˆ€ê8£*. P—9¦c¥FLB¨ˆ‰2äáh­¡†^Ã8RY!LÍû|Y°HŒ:Ã\\É¤ŒúxÎøL·Æ5~CXÉ\"&+4ü†Ú­Å3Z9DØ&\$íÀˆ¶µo\"Ò<få~“±R“#ªåS%<Óâ-d(‰•‰2–d#Ğ7­#µdÙµC{nÙá8^ñ_‘B=>].-‡'ĞÌù‘#¢aIv#GgvEL\r[âzèã±;ËA\ró\"ô9/@3lDü5¦òÊ*Œ‰Jx:>BHÛ¶b(ñğAı·D‡#xƒ³‰ˆ‚yİwÒªÿ®¶j8@6£¯aÜã²î™Û«Íz’'øÁñ†H˜4†pêŠLÉ9“h(H‚Ğad%‘¿dHGÌ©—G(Ä=ÁZCU)-ƒÁ–\$tü…Cm.a± ¢zDÃxfÄå –7\nEËŠÒr`_¹™\rD„ ædN+–RjURÅ‚¢r9: \n™dzyz©G‰4\0˜ˆK\"95\r±*&ø\"b’ˆ1T–Ex²DâÜ]Ñ}#2¨Äu\rlfVQ¦\$†ø–y_ÌpŠQÌPÂ˜ìşbÑQò0¦ƒ©¢T‘¦P»/ó–¥‰ì!&øİ¤:æjF®«ªxÄŸÃªÁXh©§¬u’²ÖjÏZ+MjÊõ°–ÒÜá7‘W¹1W(>T¤9‡– ô‚dT›52`ŠR,Ró›#C`aX3-J%Är¦Ir1BµI4_8C¡™'¨ªpœ’Y!Õüá›¥ÙRN\\°–\$µY)f,å ´ƒºÔZÈ’_ÌºTHn›a¹r®w¼	’t\rÉ6hA'î˜QC(ëÔ<Ú0ZyOb„™MçDçJsa¤¬·?‰Ö®Úœ2ÄÅRŠ’D\\B»­¾˜“vß5H bêfœ³M¤ä“P¤‰BÒ‰X½U2.p˜¢¼gbº©d,5’Dh%4’Bƒ@AÌ¤Äo˜²V“L•j':5e‚\0 …kaU´LÕ@PQÁJ/RD\$51ÖNÕÒ)v,Ä<2†zöÔê‰ p¨ÑIÊvfÑ!ÂµU\"(jûW¬l±'°„b”ò4\r¦D²5/C¢Á¶¨ê ‚TRŠ\"iÑsƒIç‹S-ñ¸™‚\0†ÂFŸğü2˜dWprPLÅÃ3ÉB!±Ë\$Dbz.j\$Ç•5r_O*É•½é\0\nÙş_ŒÄ“³­Bæv™Pj†å½¢ÆÒÈµKÂDU„òp“˜¸%r×œF\"¤Ø¶OLCa‹îzÂ–›d!75h(ğ¦j*DrêJt EüÀ£˜–gÜáCp6†Ñ\\CKO`¥™æÂà¬N`½MÊÃ’­˜¯¶¨äŠ‘r3NƒkÉjá*XÈIßêkp‰©•,	C\r!êÈ_[Ğ«Ôö1&,şÄEtéaËa\nH‚ lÙcÅiQç±‘ĞàØÂvƒ%Ì’§WØà	´&!ˆ¹çeø­ÏØ¡Î° 9¶:Fò© bqiã¨~I‹8ºŒ	¢Ç‰œ±Juvµ+º¤D®ïÍ!X`0¼cZƒõf±W/•Û¾„êÏSW¤V¨£ÉÜ3ÈuôÑpìmş·(àê\n^X+_ì\rlÓPÂ™BÕ\rS\rÅ\rı¹¹Âxb»ñbhÉ\0T;¬¹ärrNÉê!' –sfá…èÚÿ^;aõé¦£Ô3¦‚æ¿ÌB\"AÄf˜‰ì[ƒ›½@Äˆ’”ì§s]—ÉLd™F(ÚÙşûÒ!h2¾jsši­¼<iV*éÈÚhç…Ïš#Ô‚h/®¾!©-.|sa-?ä°ô4)ŠÀÉıv»ñ\$°š›¡?é^œt˜cOå÷›ó}¨¦Ë:\0ÉD)Ìµ!z­\0PF]ù]F5¾¶ùÏ6,ªœ&Pˆçf\nP „0S°2ª»z–zišPÓ5‚Óë‡¦&ËÁyVeò‘·†¶f=!¬ôş¥œFqZæiˆb“÷’Q>s,uÅäÏ>ÅÄC³#/®öwÙ	µ)¥—”¾ãJÉRëØnEeYşC/‚¬¾¯ö#Û|µƒód—Ğ9„E—ıM˜dk9º7YÌx”Š_Òt¾À)—ªÛ^>×Æö†WÔ¿NşÏ^ñ/ˆögšö¯úŒ¯êûŠÿIBÿ”‰LµÆ:.o…R#oHHÊÍ\0ÉÂ‚b`´Ì¤şP&&)lÛ©¨èY¤hÌĞLàI S’/©Ì6(Ï„2BôN0`*t­om(Q	˜:¤ìm ò=7^.àì<G<‘¦(dÈù£Èlü1d9\n§DÆB÷#ÈƒDšå\0ô±Â*EâcæB~Ğ°&Ãº&pºßG#\nëÆ<v`mza°ææPìŸOw‚Eƒ³ãìïêİ­ÖcØ}MøJ–<,jÏ@¬jfªê&°Çà.€Èá£Ä(âø\nFæâr§@È&Q8{(Œ.qDCØ!QTìk'8.nv*šù¯a¯£bâ]€Üç†& Ø²ÄìŠlg-lg²'ĞQb…P%†ÂJ­jÖMo\rğò.ç±®QğÄ¼ Ô£PÑÅpÖqÂ/çÍ\$;ñ³è¸üPÒ}çf÷\"#pöüb”äPş”1Ü«B\nqùÑã‘õÃ.A'yL{\$2ğ¸ûëÇ ¬ò9züÈbø-œ9Ò\rò&2\"÷1w\" ÜúO|um˜2#‚ï!Âñ\$NÂoÁ\"‡Œü¦÷èbıÍlÚê÷#‹°ôxÌÓ'¤ÍÒ~ù²ha²‹¦£Ñ(ÔØ¢fƒ\0ÈèĞ*yÊÀ\"ätĞë†&®%Z'Ê®¾%øc¬”.\0àX\"*q¨Ñ-J¶Ğ¦v>'ï,BZ¶â6mè»ãxyÎè@ë.&¬`¦Æ®*=R›'² ®‘.&Lr\nén€İ\0ÒÒòèg†|a&×(9Õ3ò•ˆåî[*Qÿ*­ˆğ\ntæ°{IçR…‘,G²¥‡·!Ñå³c7Sg¦|ğå\rOvğ3r\"_ÖØÊ‡6Cfƒ.0DhdHgÀ–!|{&¨\r(Õ€áF®AªP°)!’[66òVyq\r)c™³Û#³…5qæÅ€yç¢>3,bè#g–bs˜ë‚6zÃ?”5Êc='gü-Ô-æ‚ÙG¨&e×=R‡6ô(z´/>³›8§§C‹mCg]?%3B¤‚>Tw%ESøhk4³k İÔg\"s†­ÓŒé‡Òh4ZØÒØêÊ6F)õCm>\"E:Î¿=ôK´™HÓßGmË³ñ?Ä~êôŒì\$ìîÈITi=sDgô»KóAQL¥W)T©5”®vßMEH&N@J\$¹7 Po-q(M)”ø,Ôü¼’•D£\\P•5T?Fğl\rN²áS<òXJ¦2ãpƒ\"Øud¼KÈ³Â«šş²60€SÇ›T	ŠáRL“­5<\"5T9UD3/{UÄY'@–\"¾A&^qÀÖ8CıUa7<8£î,Ïxü²p\r¨ËU'#’ú2/Yµ`¼qïY5¦üu«%rìÒ``Øcş'FòÊƒÈcò|'e‚`¤ş4Ä2€'8àèIS1ò§(,-f˜ğjàª\n€Œ p¼2&Ì5\rºúµ×%Ø{ößM#­Tõ±aìÂßm\$ï«'BU¢!Â ª§æ_GÒæPEGÃhC®C@gp 7\0—^oÄ-âÚ‹lnkJL#¤nG3GÖa\nÇÁJröS¨LÅ.à’Ê@E2Ú¬2Ò\"E¤v? †'”1Nîå°9ù\$Iöã#?®br1saÅ@Ô¨Æ×ö¼5–À‹Œ%†J1vØæVÎùÒƒLRKvílŒ{„'0ÏbVùlÇœåƒœëFÖ_d>‚ÑSd35B5‚p'Svmıs	æèB g\0¬&*Õ,‚z\rãBt—X¢z7ëpÌ=BúëÔv@‚\"¯pd.¶b6f¹lNÔ:3-N˜‚ˆMLu1°R\r8ÔÓ¢'`ánWem'èa*\rÃhVëâKb\nD€"; break;
		case "da": $compressed = "%ÌÂ˜(–u7Œ¢I¬×:œ\r†ó	’f4›À¢i„Ös4›N¦ÑÒ2lŠ\"ñ“™Ñ†¸9Œ¦Ãœ,Êr	Nd(Ù2e7±óL¶o7†CŒ±±\0(`1ÆƒQ°Üp9GS<Üèy8MÁDYÁë†ÃÎCğQ\$Üc™f³“œö2 ˆÄâ´)ÁÌØÃR™N‚1ÈÃ7œ&sI¸Âl¶›«´¡„Å36Mãe#)“b·l51Ó#“´”£”l‰g6˜rY™ÄÈé&3š3´‰1°@aÁé\rÆI‚-	…åræÌÉº6G2›A]	!¼Ï„Ä4z]Nw?ŠÉtú\"´3‡ÛÁ›´”o´Ûb)ætÅ3Ë­Y­™ÁESq¬Ü7ê\nnû5 Pˆà2Ë’2\rã(æ?ìæˆÈ@8.C˜îÄŒˆÚ´®²€61ij„ Œ(0æ¢É¢n….‹³šÂ1¨²ê	ÉŞ9@Î\0Şò:é0È\nc¢dÉªËÒG‚s;Iƒ[Ä7½ò0Ó\"*3¤)¦ñy«£“;\rÈ0Ş‹C‘ë•BcËB‚nØ¡Cs‰±“(‚2\r¬j‘AĞ€ß	B°Â1Œpr@É7î41AA\0î4ƒ@Ş:°c/ÃcÈå44&C0z\r\r¸à9‡Ax^;Ör+>¡Mk3…ê\0_pÔ8„IĞ|6±([31-ŠL7Áà^0‡ÈØ¬Ò\r¸ÉB²Š‚HÃ¨Ö:±á†C8n+”™WÊ…ÃHÊ;ÇËè¡@P®0ÌLhÎ‚„£ @1^C(~ßø\n\n%è›\n‹“„=Ê*™G‚\r1­Ëƒ£‰@Ã(Ì0£c;1ã¬“i”Ö¨#!øÓf>\"7cã İ}H‰{ô4ÌHç*7I£æì¬XøØã¢ÓjCJaŠZ¿”Ì–’\$6C\$Hğåš:JË&ahæ1¶R ¢&£\\©:A—I\nËÌ®¥Ór8]6][Š‡¶ Pú°[»¢0ÍPJj9ï\0 Å]¬Ë±\nTÄıKƒ\\D7&±àáE¼ªÚ6Â£’U¨ÿ:Óom¶¶åÀÂ‰ÂÛ*(	MQXMµÃE	ˆÂ<ÙÖ‚7Úağ‡f#=Â2÷¡òèóF02ŠË3>[ú_aéö(ğ•r™(‰Š/>dQà³K·î(Ş3Úº—6¼£¬†*\rñüè<¯ ëDQC6BÁj#?€ÂÃ	yŒZ)€Ê\n˜)|•¢0JPt#aP4\">v	ÀuQg]=«Bn¨Ì\$Xœ* @©ò§U!ÑUªÕ^¬U›Êİ\\†à^ƒrË‡ÀˆD€¾IKŠõïø^±/2æ‚ÜuÜš\$_f\rÃ&FÈéFM•3†T8ßJCò/ââ)‚åB¨Õ,2UJ±W+\0î¬“ä:‡Šéí=Â@³Â	.pÌôìMŞJ'v&\n2Â–²	ÁÀœ—œR,`ËrNŒJ%(§¼A+næ=¤Â_Œ}7°lİğÇ”‘'_ä.LÉŞ¿‘KŸ”ñ^¿ã}\0J\n“R¤–V‡6dBØÑÇ\$Æ=–ãPü—k™I3:'AR,şaa-%æÜİ‚\0 €-š¦i€PNLO7²Q¨•Ø´ûŞÈ•ëè’£óê\\He+«ùÑ õ¶„MùÌ@t…PÎZ9Ÿ€	¼áB\$–YºH@°p£Ä¦ƒšQ/1J¬`ÜÂ›WªupĞChv&-TË9‰25 ´„†sÂ˜RÀ´:òPb©Ğ¢&ˆ /élP#\$ˆ’#*oƒ13fÁ…2°Ş—œ!2’à‚*³¨,¢Ò&'Dl\$0òúòş Ö†©U¶Lƒ‹&DÆ*JãéyAdL¹Öä†\níIƒá@'…0¨£Œ¬OªäŞ¬›¸4áfQ\$à6œ ÎqÍùZö\$ÌŠ½6‰2EÅ4—HÈÍZáˆìÀç bÎÍ‘x\"DRkÄâZôÂ›c¨­†•4‚ PH1Î4I-Œ-K¥Ö„bdÄC!êœÚ\0¦~ç‰»8	á8P T­êî@Š-âLÌ):ÕDÊÄÇsé2„Ë,ÒB±?WØS\$îz…1ìÁ±yPæWIea©Ÿºhg–³s¦NHVÂlN™DHHù¨4¶V¦MdÕ½7ÃfÜIpá½D—çÄÉ×Å¾Ã'ÀÊ®øƒêÃŞO-v©©¿8gÒ.À3•Ø¹CYømÇdèñT6 AB£N;WœF¸s€iI%á¹È¶oÑ¦‡Ø8?*gP¸l+\në1¢€Ô±¦ Ô#!'R\\ı¾D™m.ÕŞësşVu¸İ£´a±Ò4-8±7SŠtb<	de¤„Ó\0d4rI\n„d3ğÕ¢Œ·ºWS. ÆGÀPC.\$¨RË©ØaŠÈ¥†iÍ<Iu’a2:Jf†\0œS™²bP\0Æ¥#8„ÙÀek'e s+‰]0b?%Šğ…@‚Â@ ®7e@¯‚Zå	*C¬¬2†£}id‚VwbFÊk5B˜¥05ı¼Ía1ëùùiÒ``ÂxKTÈÕô.`hr!ğh„½÷7ô¢à<{ğR‘ºÅ/	áu‘\$Ì{šª\r’&~ÉWŞÁ-ƒZä»üp]½)ß“ˆğ\0–FÂ\\•iIôÁŸ`îAIñ¾/6Ñ•˜b	rÑàW¡‰Ê¥ëv]òg9&ĞÈ\$PŞ µFÜ¦Am ğÖB'[A!ñ’Ã	yÒ²'~€™’ÆHRó/¸tÁ°‹Ü#A8êöl£áşîBÉ}’HÖ\råtfRñ‚ÃŞåørpæ\r·Œ³¨½boÃ2½ôK¹*p×ÀdJ™O.äy˜ú¹t-—ÁÏ)I>ˆ’¬\$Ñ±{/]\n¼Ó(ô¦€áéagš‹qÃú,•òÂ‚\nÕìÊTé<<ÕÜ+PjI\$'^íR³ôBÂˆ+Ê/ü4ß|LÜÃû;”:N4é=8úì%ŠGW6Ş_òa_áãş‘umÿ¼NèÃà¦p¯úşOî¯ö6/úòáÆG\$Äê‰îVsÎ(5.,àî2\r.î äüû°&*ßÕ5\0ËœÔ#:ÔoÿĞ\nÃğRºXşàAP`¹íDgP[\0Äç\r(¨ª<«* ØlËØñR…ê¨”æd=ë8TX”c|ôcŠ‰Í ƒî0v-.Œ\rÅ@<¬P ŠêT¢&D7°¨(BˆŒGß0rºnÜÒĞˆ\\í0ndÖ ØßÍ?Å˜^¥´]ğÿ0\r îÿ¯Âòñ\r\0o-\0ÀœÀ¬ü^.ø\"f­ˆŸ0\roz´hŸÂ7I¤û°xòí2…ç1¤’t¦ú\rNEÄˆbcæÍCL	¼[,Ğ—ÎhäÄ\\–fHG=/ÿã6qÑ€ÿÖÃñ‘G\0ÑJÃúûQJ>¬X3§ÚûÆ\\Y‘¬“ìQ\"Í#8\\<aÄˆ(g'Ñƒé:rLßQ\0Í¯»ÆŞ]n‡)qïæaÏ»>a¢ÃQŞ#qJøÑRÄÂK€†ÔÀ×	D2èœ8®,-Ç!­_!è;\" Êš¦D(ò/!Ò –hœcC€ ‘#âpÕÍ`\nr¹‘/Mb1dÃ‘é%Òi&ã ±ênÄ¬ÖdyC'ítÓd‡P?¹&\0CÍ¦HğkŒ>Lal>jŒà0P„’éğBç'çG’\\5bJ#% +§^Ÿ‘ºÛ Ì/Å0ñnaLZr«3.z Æ-iê©ï‘-ñe/…è–N6æ'1ì>dŒ\r€V\rdI!ğ¶‰Ğ¬3É’P8Êeb~('”¬XËº\n€Œ\nxŸCúLåô#nŞ)Btnúåó^5ˆŸ6kkb-%ØÄ#’ğÂ'±À&kVV «C¿)C¯9DÂ¦¯!é 7ãƒ“8!\$a2àÒãˆ¢À)’Å°4,Ë&’…cØ	f!pĞd,k+M³¤ÄÂï¼¬6³&Ì»8Sâ‰ìg>§Ú^O/=ì±?b‘?¦úlÀàôÓòñ¤_>Ršó@Œ0ûãêâL6\$Hr°\r@Ch¸q\"  ¦«ĞÆ´°ìº šPcp/¤²é¦÷¢?Ef’¹ËJÅÌï<%àìBcôot.1âŠ,@¨Á„¤#À‡43„0b’ÿ>ãLÀl\rF«\"oŞÀåäLÔ,K@îĞÍ4œŠ2%Ş0	&Ÿ`H@!@Ô"; break;
		case "de": $compressed = "%ÌÂ˜(o1š\r†!”Ü ;áäC	ĞÊiŒ°£9”ç	…ÇMÂàQ4Âx4›L&Á”å:˜¢Â¤XÒg90ÖÌ4ù”@i9S™\nI5‹ËeLº„n4ÂN’A\0(`1ÆƒQ°Üp9¡ÇS¡ê]\r3jéñPòp‡Šv£ ‚ç>9ÔMáø(’n1œŒ¦œú‰\$\$›Nó‘Ò›‚‡ÄbqX¼8@a1Gcæ‰\\Z¦\n'œ¦ö©X(—7[sSa²\$±NF(¼XÜ\n\"ÚŒÌ5ä‹M¨ŸR\rÇ6’Êe’]ÄÍ¤<×ÀŒµ#(°@d¦áDM^¶|z:åÍgC¬®×Ü®©vÜ§ë„ûDSuÔïµ—6›-¡§lõ\"ïˆä‡¾‹¨Üâ¡*,Ô7mêâ÷À+ÛÜ\rÃ¢5Áã€ä0¾ P‚:c»Š….\"¨ÜÚ\rcİ\n¿\"26×J:šš2‘<T5¾q`ä ·‹*„ç­A\0 ÂD,c>!?É›€‡¡£”h›‹{,¢?K‘JB02©lrœ‚!(’H-1#nš£lrº‹M¨6½ƒs:?òDR@Pœ2¬¯¸ä5B8Ê7³ãD2Ë1¨Å®ÃÌ6®Ipì—,2²Í<ÈcœŠÆÑ-µ9B`Ş3­@U3M¨C›á	6âÈ6­OËè‹Gc¤z=(Hj-©ÀÒ3 cêº?\rãCS!Hƒ¼Œ*\n“¬.[3¡Ñ:˜t…ã½Ä!•ƒS±ƒ8^†…ã…šÆxD¢Ô(Ñ'/ã3•1È’ã|£Æ¬¼A“2ä:C«Æ:ÉÑRÌ›®.ãhï+êöB²š;ã%&7ÏC(êü¸Cš*=B»d7>ğJºˆCÊ,æaj‹ ¯{;D-ÃƒMBP°«e,ƒxZ¿lŠ:c·KW?(HÒ‚×7ÎA‰¤H‚\$Ï£Æ4ßÕhÎœ£:+²5/pêø¶øºš1&í\0ä2m S1b­+´\rÏ\0Ü3B¤¸:\r˜Ò;lzø¬7µ±æDì#¸É8ÚPÓx3f.»ó‹5rä(‰h—¢EC ß½p(¾²Ê\r4êcä9Üø¥]¬›Û¯}Ê];Ó=³c*T:N§7¬•‹Ü29?ZÛˆ¬n¨STCÅ±¯G¨<{’ó!Â»­r[Zô+ˆˆ!.]ÀÂk‹Œ¨6Ä˜Aj^@@ PØòÖØ¼a¬œÖ\0À‰#ø`ùÓ†âÒØ€e€àø¡¿e`PŒ:Y0mŒ;=âìŒÒ oI§ˆÇAÒ\\eQØ'ÏTûŸâ\\ÌR›ö„Gæ„Â€Ãc.HÌ¼ŸP@Ö\0lV\$ †%\nÒo<0™óåô8l\rçiQ*@ÜB³¿måì3¤’OĞonœ‡œ¢<OÈ{9	¥¨²àÜBA--D¨6à¯M‰¡Ê'Å¦O¢©j‹høÅÔ#\0rŒPz3?dƒ£a¶ZÂ9èé\"1¬v'9Ğ^˜\"r:§]\"Aò|«—1ù\$E‘\\³VHßºÕ^á•l-¥¸·—âë‘W«Ğ—RìuA¹µ‘%äƒ!áÜ4šĞÖÑp!‚g\$ÿ8 ÎÃ\"Â?&`“4³t‘œÔ–C/%¤Ñé\"Ø“  äU:Î1¤ü ™s2kß‹«QkKE²¶Ã¢İ[ë…q®Y|—Jë ½Ãâó1—œ{f¹§½è')¼ ‰ğ4C3¬šIkö.È•6Ò„x*n‘+Dx/&±7‰8(€ B™¡ç”¿XHa4ó0¥©Jw2ÃKwZr8ÉØ õû!ÎÁw<pÍaÍªQ†Í™\$°ƒ™A«ÜÏšub!aøa®Ë¢‚JâN7õ«7~­…â>2ÔÖ›Ö˜]ZÚè((€¦z\"\"ŒäÊ¨ ‚,›2èçiB¨5´˜ù\$4«Em¾r`L©cƒ7åŠU¹ÒÒàrû˜­´’Wì©	”•02’æ&Ü	új7.Õ®å5<ƒ¡Ö›/Ú\0ªêaxA¬\"²,IS\nAí†ó-¼´CEú2O‚2û¤-»°SÚÉŠ{!%FëØ²TJ#¥XDÊS¸ÊK!.‰æ±‘xæÙ¢‘ÂC“4¨KƒA:/i`+èÊŒáQÚ09\r¹[9aˆ“û—«œ¢CJÌ!ÇXÍ×y\n0Ù'Ôæ@ Â˜T­j—ZPNy<‹§JM‡©ŠIğG'\$ìÃøF¤æCÄèŒ+ AƒgšTH0Àö!jæ¡>¹‚Ù ºÏ19vt˜ÍØ\nF0T\n.8¼œe\"+kÉªœ‡,‘n_Ş\$§LJoÈlhf¶Õš\0q‚€D!P\"èbhB`EÑ‡©Ÿ¢°Â¡SÖ—'Ä5û\$46¡ir¡qJ '‡úŞBAj<4ßê™În™!(äÿ„T*…ÛÊÆQŒÆÑ˜ë¸ì‘´¢™lC'ä,{LşºNL¢3\\ğ”òŒ®ï¤Û…	Y”ÔBALtÙ“1³ØYDuß¶È* ­)?É\n›Úü­éDCÕ²®'Ã.ôq‡ó˜É È^§|Œ_pd\"u‰ÀR)\r®ñ½æ‘XC+!DY\nlt0mÔn!Äø(Å{[Ÿi‚¡Ôu¨7Ü´ÑBúô›œ–Ã\"‡1·Ô¼•&:Çøé/Á¹	çì¤R/çcls¾‰ØÓ»WUXß€PÃÙ°gÜş/4	ËÕKŞà†^àëçMm7‚áMgRS´‘Ôœ:Ş¬.%ÿ‘bêƒÊ‰dö 0†eilËR€z°ì6TÔ•f'vmÁ¢Â Aa!ı†.€Zê~ÉØ_ZWungÑr\$IÙLü…<Ç|»‹g ¼ª³–RÊÙÁue'ã¡ï-…Iõ`¬Fò²o\\YQeJ¬2 O ê=ÆÌ÷bóŞ”?eğ=¡før)`šÌCİ8m¤®©¯wËèİ/Èóú§Iıgî¥§¼1±ÙœúoÃíÿüµB8şŠÇúø÷}_@ã}#hpÌÚ’%,òßçÎ…\$RŒC\nÎÆPÌ­äKM˜ÿCn,B0ô\$`œã8‡\"â	Æ Í^›gZo¢†ãï ˜'GŠU¦0?cf?8fV\0îØ‚|›°T*F6Ï‚PiÌ:®’)Ğv¡ÆVJjnïÈ-O–ã>t1	\"%­nÙI\nÍ\nPoG's\n0€ñÃ>ÕÅ\nsÄBØílŠÅqé€jbØ#\"<qÒ~GìØÍp!ÂúÓÃÔè<M%L6Pşm:ÓPÌY‡ºàL â†\r-±0†ÿ†K€Zh\$<Š?ÀĞR}ƒo&DqpÈQ\0†\"C°àM”Gµ\n,B°ŠØ1YÒ9¤U\r€v‘[QcP€#±&3îLw¥«°pÃ7NOgĞ5±•	Q%¯ğCî€Xbâ\nG˜1Å—¯ìıK‚ùÄŸÏ„6ˆú¢F1´y±»¾ùƒ÷ÏŸ¢'ÑÌú‰ôê§ŒÏÂ@Ğ\n%‘w\n¤ô­®±ñ£\nq’	n®ë1˜xr\$€¢dãZ¤Ö\0´ETÒªSÃ,\"\$ÔŠ\$\"2Ã„J à ƒP:ÈÎ~…4ó¤œÉÒ65’;e ğ)Ho(|¸¥®ŒŒ|-ƒ&’@Û\$äK¬õ@Û‚\$\r&hr|¥êN©âfîë„6æ<â2q‚\$’ºdã?,r¾|ò%,æ?-B,ìçªuÇ±-0õ\nÃpzÀÏ.q†F3òâzâ.‘›\"rï.Sg©/æñÄ‘B‚@”³\"ÕÍf„d>oî“ù—‚},­p3ñ×“CòøI³IjÏ\"2ß0†ÂNMNwF8ÚÀŞ,É6IÓlÅ6sÚ­¯2?7ç\n-ç<~À¤€H	Nfg§ zP}/gtR'¢!Ó£3SŸ;Ó!rú93ºp3hdŠs:óÉ8†ƒ;ãY%·\nó5ÇC	ó7s×8Æ€rÒ›€ÊI¥`?1gSÁ5K ì\nqy@4<£ní‚níÒé QqBÔIÒ÷,.ÑB#ó>Q\nîÚ?3íD/0bâN\n7³0\n,ORô',Ã`RÔ;55EÉYF±¥A`0‚ö“?4Òî³ââ\nàÒI¦›àg3lÛ_ Ş\rLı‚ëIªubƒJ4¦4È¤o&r­Ã4Òæ Âp6?ærƒ9âYïŠ= †a€Ø`–ˆSô<Ô60^…Ä˜Œñ#ËR\\~Àª\n€Œ p	…J„5H÷ğyq[\0„Œ²Ô©c‡²¾!õ\${°½3 î¾ñ\rè#‚<\$âê.¦éC¤à1n#O\$ëFŒÓc,†Í—NÆuUãú’L#O‚ØbH	¢6IÆ´ãQB!Ò?\05ÊôP\"=LB\\ğ‚íŸS0‚5Ä¾^0(0¯*C\"wäÂ9µ¾àƒ-CJ5)Z•ÏÏåB‚„\0È‘@á[ÕØØp”!ô¢1ÅaĞĞ»ë,fz‹b|H5ş@ÖİÇV-€''®ŒK–È…Ïƒ*IÂÄş€8û\rä8±ÔµøáÍ¬sbâÂbVQÎ:EÜæBâ¶ˆÈ´­`Ç[®vœäš8'†Ùl3fæ.+gÓú.'N7Œ\0!'¬Ö4P6åxb*Ø€®6WdÜ5\"Ô"; break;
		case "el": $compressed = "%ÌÂ˜)œ‘g-èVrõœ±g/Êøx‚\"ÎZ³ĞözŒg cLôK=Î[³ĞQeŒ…‡ŒDÙËøXº¤™Å¢JÖrÍœ¹“F§1†z#@ÑøºÖCÏf+‰œªY.˜S¢“D,ZµOˆ.DS™\nlÎœ/êò*ÌÊÕ	˜¯Dº+9YX˜®fÓa€Äd3\rFÃqÀænÏFİWóûšBÎWPckx2V'’Œ\\äñIõs4AÁD“qŒäe0œÌ¶3/¼ÕèÔètf“ÅOåê¥j,·Q#rØô‚D„’I¥½…jI\r›QeÒ^Dƒ…ÅA”üšJ¾­uŠC¢ª\"\nÎ•ºÓ—ÔM¼s7ÊÑäñŸ>|®íw2ò¾U:€¤•©RÎJ.(´¬¨Eª,Z7O\" ï(¹b‹<K›¤¦Š42™·LŠNŒ£pR8ì:°´8¹<,ä‹rªÑZˆì\$ì¢²’39q’ÂÍ!j|¼¢ªRbõ¶Ê’Z÷¥¤\rCMäròGnS1‹Ë”ú>Ì‚¼éŠj® ÄšdÚ¨Qüo(ÆÒé Ğ!r‡§¬{ˆÈL¦qvg‘Êæ%ì|<”B¨Ü5Ãxî7(ä9\rã’l\"# Â15-XÈ7Œ£˜ADˆ ê8B85#˜ïHŒ‹9@)/é=‘Ìk”¤óš%\r±s›œAÁ.ç	£ŠY(	\\Œ§Jï¨éZÈ³­.bÚ­®nÂŒ»­‹ÌZÇÎj¥v‰ ÄºšÏ¢åºŠXs>NÈ14˜¢ˆ“h™Î2\n!Nvi8¦Vk¯|Ó23ŞÊBdX ÅÌ]H–gNS£ÈÅ¢J€²2q0¡ØÄŠí©0ò…N}ù£éRIÈ´§A™x^¹åîÄ)ğb38Äâ˜‡WQîFçª\$à ŒƒhÒ7QÁ7NÓã}B9Ôu(åPŒ#ÇNzxÎ:•+KÓ!\0î4ƒ@Ş:ëP9ÕU`X›(ĞÓŒÁèD4ƒ à9‡Ax^;ñpÃ¦éôp]HŒáxÊ7ûê9xD²Ãm\"Õéã5\"6Ò£HŞ7xÂ<ë¬æ]™Éá}`®¤8¨¼H1AtPç`WA}»!—-ûÜ¢•Ã—¼Qóxò“+.j\\0gw¬_â½ŒŠ§'Ï­bÏ¸Â9\rÛ\nŒ\0Ä<ƒ(ö}ß…;ã“Õ	â±0×ˆÃŒrav	Ì_¤òèuP“'-dÉq„¬î—A(Î¼˜“çpòÈÛE?8ôA7ôbÏ¹ih™ãÚEËÆñt1¤\$“ÑP¶xfN—R Î!)ˆ&éĞõ§6p˜H“ÊFÂ™)ª™Æ7P ¥2ª²’¡º‚é’\$#OñğÊ½í”’R	:Ü9(Õê›¢+bgQV;ä¼ÂˆL Ñ¡›¨-‰jl‰ÉU \nGŒÿ”Œƒ9˜1E©Ê¬ˆI\0˜#\$P\0b1,ºBr*Pdl	‡,z\0#–\$E¤(”@h5:&¢Ğ¡ˆå\n#B˜`ì‚êvx˜.Äòø;-cÆZ%Ä#b©ë—ÅJ\r I'\$©R]‘–#+&;“,HƒA#u,æ \n,€€6‡Pæ¨[Ëœ4áĞ:¾†È£8r!åÔº²m8C˜>nœ3Ï9C,íÆ°4óX¤ÃHsRá°ÕÍµ\"£Cu ªzƒÍ°Êˆnl.œÖÂ#.ÆÏ(\n\rĞ92ów‰:T3‰f2AágÉY@gü°E*iOî§Ck5ÍÙ‚ğ¤–ù¬P¹Í?oUã,¶”¢Í<(4ÅÚÒÁİË‹<g»I„SÅaš33mıA*L€L†ÔúœI:vU©²F¨Ñk÷¬Uj1v©ê¥S’ÏàuOx5J)jFEª¼g«H*®;\n¼FÅbß¥é™äÚƒ¢öKğL‘‰jCÉ°T\r…C©çDŞl@¦4æ ›¸c¢¤25òØeo­ıÀ¸7\náÜK‹´Î9È9'(¦èHttÎPë€éCpgŸEœK¤MÈQİH¤òXŒäİá²vK\nÒçWzæw‘\r€­6\$ƒ‡ôº’A-ì¤Ä”™²™Jekù7†ôßó€pNÃ8€îâœe§qáÉÈ¹7%D¸r6Â¤¯í#ŸH™+•¨bÙ¬ü*K\r\nIxJCH±ä‡Ì‘‡_Eú®1èÙ¥”*½Qb.88Ü%ç\$Ùº=8ÒÅ±ÅÁuZÀ¹äÆè”yN/lÈh5mvá:vÔC`l}Æ¬8(ë>C+í!™øÚ°×›\0f¹H6ğÏ?›[m\r´ÕĞ,°\r6Yrw0†Ê*3—™\"S.±bxÀsûÏ\$_>G’>²úÚ®Ÿ‹b˜½©õhšÔ–ÄÕ¢€H\n\0€@RğM¡^Lò•à¬4¤€UÊé&ÓÌ75\0Çp®Nk\ríÈ9í”(gË-ÉS)êÕZæQQYeÖ¬VD„XŒV<Ÿ\$Şî³\0*Í(ç@§›(sU\r~~¨ÌÙ·CƒpnJ¥U¹†ÔHc\rr{8§šòVp3x2–xV³NÂÄ2­còÄÂ˜RÌÄg0äBL“İáDu–”™?!¹v,â”º ’ïRZ=*\$ùIdÊ:)Z?•È¸H¹4±•Û! E•y-V7m>rzÙ5áZpÀ¹;TÁÈ£°%·…ÒdL[Ğ{'BFËCÉI€TuØ:®ĞòXj.í	j\$KÜ-h;(ƒŠ®ÎÂ¬»Z¢EñÊ\n<)…N'¤cX)§–cò3\0S/x s“3Ò‘BÀAe<\\Ò l.8í ¼r’sº&/a'\ròC¼û´í'\\F‰fµ±j0àê‚H0TÓic!^œ–£	RD—C,^ÊÕ“\"ã¬póñ\\dûA`ß}Y9²±X­VQt®yrĞAÈ±8\\Ât‹ˆGÈBfÿ4„#ôFuÍbƒ8LüäÍ*ˆDDûl}1ô³0Œe‹Äd		İfNØTTîÅ‹¼Jj‚ˆ@v'ˆwg \$ëGª¼ˆJ!bîfVHŒ‘c¤zø8n@‚I¬IŸJŞ4L]ªü9€Í2ïáœ„n\"*2‚ä®”\"Îä³MJN‡Ä¯ÈV]EÜÉ*uÇôvGiI\$\"éß)”H°‰n¯ÂàWÈ®Ğ)L!°5ç°¼â„@&<Ï%œ322Š¸Ïê¢JÀtÃOğXäşM*œÓ\r45†vÈf|JOø#\$¥¤ ¯œy¢@\"JN	æùLš\rÇtÔed•Èš'ˆ°JI\"Î(J)°ãƒ”‰uè<êÈîí&\0ÔĞrNãğ	€Şqmv\0Ø¬Î¢@lâÁ&Ü‚Şé’-I0“Í§È>7,nø\"Çp?\nç´O¶wˆÜƒe^™¤|eÃHÉMj‹<R\0Ú`¾ĞÇ 8zieú\$l¥Ô˜e¢a±¨‘1Ì1q°p®èFD®=E`éH¦ãädø*¡@†É@Æ\rb\ncNR£Vİ@Ë£â<F\$é8h!\0±Ú<‘Ş,•	#%mv}ğÄæ‹\n¥*›!H¥Âª#k¨¦i¨‰¯,|‰ŠÈ‡…OÆ˜éV’Åˆd‡†£( ¨\n€‚`\0â².j‡Î5e@TEHTÍˆghVóÏÜ£Ş6Åàø	¨~€^	´}­,?âì~r´Z¹Hw*\"\\O²¨{æXàç`ôÏ€J‚-4ª	¬%/¯ÒpGBÇ+ä´)+¬%ª=’¦§2ÑP‚bğÒá.Bë.‚bª²Ú¦Òò°b@\nZümBÇŠü[¢¸Äâ’\nYL^Ò‚b°Ã0åC¡,d¾\$RÍ0D&~‚c0’×0Òİ1ğ¬X’¥5i&èb+5ÓD)Óc.ğ{-ój>-\$êğFÅ^I)½Ñ&—S\$?äù\nˆ›î8ÂGäzæJ„+pMmïsÏŞ€Åº+âÔ«kóÆc/Şm@ÊS*ºz‘âZèf-Ï:Ó!3Ä-¢Ä(2šRR{³pÄĞN!Â(†C¤ÕçP-@ŠäÙ0ì‹A34¬~_Ïš\"\\\\¤ñ,HìhĞ)–)„öEÊÔGÂNÒY=„ë/‡É‚0¹”]Fb8…nö^é'7\$¥FÁkG+GQ<«ƒü9C\"&¨ğf™„T²”÷t.ğvGTy“öI¤,‘ NdcI´° Á?J›JGƒ&HL˜”ƒLoØ©?KIœ È‚6#ˆ#1CÊYĞeÀˆR\0à5€~‡hƒ\"‡S¶«\r“x‰/Úáâp\"IC1È¯²Å6Œeb)C¾äEysLPzçú‰í@`&N@‚\r€ÎR&Ø\rŸ ê\r±P\"ğë´&…íX†Ø¸tZ€\\Ôe4¬7d¨¹	ÅÎÃôá.Î•Y5ƒY„MI’>ôÛZOÂ”†”T»±ÎCµ³FóQu*X”©XÔkMÅ«\\Î–.­	±T•ÈùñdğU[ñÏTTÃYê2Ó>¦€¹§ŒëIšÔSa\0S„#(Ó)Ó_Ë	/T“/“m`‡¡`Ï…5ä0¶¶Òëb4l†ó#+lW2²ÿ¹,ìr-îªH„õŠ1õHE¨(vX÷óeï‡fB'_UBÆÕGbVs*/|8özLv~‹6gM0&tÙ^”‡L…Ş£áO³E˜öGîœ2QÄ'0'\0LZõ«¦66Eœ\"Î¾‰t»Tá~»\nŞÅƒbøT\0/ıU5:ÑŠP2n~G)bçöÇf\rIo\r@(3–wiLqcpæf´øì#â˜VyqæCi£ufqÔÑ#î§gˆ!	abi\0ÈœÇv±=`‘z{'ôğÕ@z\$9LÇjµ·l£vGf´iJõµv…î÷Z¶£MuçhĞŠc6¤'ÔSv1†x’ÿc:!³¡<T«]uµz´‹Aó ÿ7lW¶‹gPöL{\nOjyWË]”½Q)}®‡î<cÉy5®ab-zÉs?Ñ¼Ò)#ÖFÈFF8\nù¨Ğ7´>YÌ)3‘~¯ˆºŒdz÷„2(¼?´[`©YrtK—ƒy—wc8:õ×k×É_·Í„iV#v^õ^Wß{·â‘Sìø=†EÙz˜kõJô¨¾tP#—ñ0P\n,øaÌ†ŒHhF§‰7Ûlùˆ)Hã8v—g]lPR8®óUGnGˆüM¼ñn²×ù{UÕxUëŒGôº©f­˜Ïy£†XUMØÙt©d•k.ş¸O\\7qj‰\\!\$¯ŒxİF‰øyøÏÃwøÍŠôÉn'Jx?{xÕwTË‹ù+}‰)—s5÷†ÈÉ“‡ü·›“øÃÆ”ñõ€Ö¶RÊg&jUÊ¥[•ò–+6SÀÄ¹jÎ¬»)q÷—ye—Ù€QŒã YsU¬ºÎa|îÒMõß]6m{•ë/ù¦G”ã´¿†y²(y·d‹YT=¸ë†™~Sì^âZ12E7‘ŠÏ	4!áQ<åâ`·¿}G‰L¡&Îü‹÷XE Zb?‡¸S”9' e¡™-^0£5ê¾1îÿ¶‰£j ÉT3Ø\\,\næB81Äûƒ&€!Ï¸~‡d“!p›bS#ä’'Ø¬³üÁÚd¤£2\\ezoˆhR,åçEdÇ„à~‚‚Èq~_¢í¦¦H]BOı1cbd«gÛH±;%“l¦¶ZdÓ|*Z¾…p´ìDç-ƒ2j8\r€V`Øİ9b?¥„ÃÊúïn8H¸L7vÙ¡Ì\"ó\$ChïA=\n‰2–ÎÃ¥ˆHvö:7\\9\0ª\n€Œ p&ÀIb“&.ÍD…Ô—H*Á©’Áe%•I\"O´4šKsO2×Ä²!L‡7÷ôôCƒïLÔ¶yùG‡È…1föeI&<Òl¨zrbrlyR;_Šÿl*ì±¹É,ŒZI£¹7ûƒûhWÇ°lHAOOÕ(,áLá³³>Ê¦\"Ò©{r2_{XÈ‰%cÈâòa³F&NÀPÔFdÒab€VÈ„£e\$§½4™ãjÔ(×=kCgC¦[kµKÁ[uêĞòtw\0B“^ˆoeßÁ”£Oªl(Dç5ıÃ¼)NDİ’Ñ9šøÕÅ5Â¼V\"Ç{8ICš;\\|a¼B§Xæ‹3îxNˆd28›‚²\\fÛàÄ8;ùÄÎ£(š%\"aNğ5W*ĞfnÎwsÊäe#Üµo£'¶{Ì^2ÓHñbaG/pdéD3!Q*“GÆò¡©Gö¼Ñ1mWF«õš„lÿğá¨Y;S”ÁÚ Ch7ĞØ…o-³ÚñBëÏ[‰\r\n½*²øçœIHÒX@ŞÅñ–²·´§T3ª¼ëÍ»Á¥ÜÂôE\$V|P\"EBe\0\$` "; break;
		case "es": $compressed = "%ÌÂ˜(œoNb¼æi1¢„äg‹BM‚±ŒĞi;ÅÀ¢,lèa6˜XkAµ†¡<M°ƒ\$N;ÂabS™\nFE9ÍQé İ2ÌNgC,Œ@\nFC1 Ôl7AECL653MÆ“\$:o9F„S™Ö,i7‚˜KúÒ_2™Î§#xüI7ÎFS\rA<‘’M°Ó”œÆia¬ÍÂ	¬r‡8³MNfƒDÒl4ÉÌ† Òg±MjE*™““Äp²2i¼Èi°ÅN@¢	ŸİÁá:ã.O~i¥ßr2­,ÊdQÄCO&p9H3÷›„,á0ÇgKv€õ…IúyÓfG·´{¿„[æ <Å\ræî¡»â„¶é8Ü²½ï‹î‰¬JŒ¡ëÓªş P¦0ËÎ’4kRİ‰-¸Ş”Nj,’KâÒÍoØŞÇ¬©˜ğŒL*&Ê´c¢Ìéc{õ;ã˜æ;­\"F(-\0Í\n-bÖÃ£sÂÊ½¨Z’×¡ïÚÃ¹i#›¨¯ÃÂ¤¹\nbF'eĞÚ2¹@PŒ2£Ïò4-!Œ)¬œPÚà\nN{Ş2Ãã(è9eläÚÆÑ:'°Ñ Ã ¢œÑ\n¬­ÛšøÏb÷AÆI\\ä!FL¤gG¡ÀP˜7µt\nû#1Kø9S¥Œä ŒĞİ4:(Ds¼#Æ1¡HÌ›T4u ‚,H£Ú;²PxğÌ„C@è:˜t…ã½¼Nµ´Ğ-8^â…ñì …áx\nÕÂj“ˆæã|‘ŠÎCÊ4¼ëM\\×¬‹‹jä½Hêñ¯t;lÜ\r-ÓÖÙM«nÜ£0ª—9	Ã¨ÊœÏ­C’¸Ö–câ±ì(ºò¦9`A—)ˆ-46£lî®!³ş-ë‹\0Á³;ø„#¬l—.¤Rƒ,:ƒ @;5Xö*‡SØ&±ò²\nŞ·ó­KM@x‹ ÉK,‰¨3ù8±Pâ¶î*™S&M#SÍCÈè2ª`ñ7•ş×Uk÷®&‰ ˆ­UM%0)Š\"`Î»-ÚõV7x¶!‰TmGHkx¾#„8ô@!°}g]9(n‹'Ñv/ORŠÌ*‰5âù`6ğÖõRÒC<À9P‹fhè+^7b(ñêN}ŠÒØ½³À‰-vÑ‘ ©ã§¢}°Agİì'-2k,ßƒ}ñ}\$`æm\nô¬“÷É`„™3Ô	ã\$eó4ˆÖL\r€‰Lâ¥Un–BbaeMUKã tJ_2f[à†š x!æEUÖœYb5ëÊ«å€A€†dLâ˜s ­šQ\$jJ™\$ŒjO2u<*¸1RLQ±#\n†p7²’)lBÀZ. ä’•q&¥¥g‚¢Ô–¢Ö[in-àî¸©b\\k•s†à^Û”ypÀˆGƒúàC!N,Š'V’QŒHÉ ´£D	mYHQ‘3üÇÉ\n\nuŸ @JOI|8aÀ4«ZúÖ‚Ò+]l­µº·×rK‹ÇUÎáà¬z]«½é‘&ŒS LèØ²ÃÁ•òh~¤149VôS`@b‡°„´™ÀÄgÍJ!iFG¢‹\n:M\nú=…`BCRliQ)Q0àÇŠúp&9‘™.Ö%ù‰“q\r2RBÚ\"ve+Ù1ƒ8~` nˆª¨'!e†¨Ü¡0Y+…\0\":-ˆÂ‚\0POI:.Â„Ê‚XÈŒĞm	±ÂÇŒif;ià<ÒÊBÑÁû™É\rCF–T»A?\0¼„5—%O7!‰¢TØIÉH'åù£¾Ø`)j]I\0ù\$©–òU/9íÁ\\Ù\n[pT\rá®„0¦‚0 ,AØ—š	N˜ğlk¸â¡R2ıJú¤\$n,SÆ¨ĞIøxx…ğÖg%O“¬Ÿ%äÄ”Ñ*bé”Í4,å‚ˆZR9!--&|¥ˆ†UÍb2µõæ–šÌMpeœ´ÅÙeQ±Jæ>CÆ71•Iî\n<)…C(£İÄÿ¦Ä ßŞ}Ô=RÔIÉI+!\r!œÌú\"“å-OvXı¿æcáe¦D0zÕO¢,pY“s0\\Öß'Î{ÍB\0€#J0‚,ÂS,ÍZ²6‚”¡h\$a£¥)p’qE'¶ô6Àƒ}C\nW„´B‘¼¾'… ¥ˆ\r`mFò3†tÇş+©mB\n†_1ûpAÔ2\0 7†ø¼¢3jÉŸœky Î†Ô\\I+}~	áİ\\uSAƒ<g¹Ò¥uŒYƒ*54şA'~ÈÎ]¢Õ³½ Aš²ùƒ6üÊ„VB•(V-ç°…%£D\\Wˆr¡i`@CÂ§+ ©7|á¥Iehµ'˜ä½IŠŠI…Òa†¦\0ˆÙ¼ä……lö2 ¦À‹”ííIfb™rŠ¶DaAœ*š{Û1m\n§\0èør\rÕĞP×i2¿mù))‡gOsF¶A'ÔŒPÀ„Ğhb¹|\r (aˆÖÓ\rİK£#*oçÖæiÎ,)e‰ƒ0ö0nÛ&E`UF¦„îö³¶Í¦o–<\\B%&<\nW>ïàmçw-ü7mc³«Ù˜y\$»\ny›s¦zÓÛ<#•À,Ùß€‚ ’âÚkU£§P†ÅÀ¨0ÈHµ*îÍ¢~‰R-a³¥åv~ùNÒf@¼§³'Æ Ã{1:4ò†¨z¹Üê£‰+£ÆFG5OlÀ¸ôîU::”ˆ‡Àƒ¦`ÃÖrİ7LhY2™=1s¦)ßœıG‘bFÌŠ\ny‡†ùöêhÏÕNËsç®w‰ŸŞÈ_}î“®Ÿøµv&y?ç“¬İÁš{ÙxwzXŸbˆHwQ•Áxt»è”Ÿ¤Ü2†\$°I<Ÿ>›ĞcCÑÛŒ)4‡œáĞµÈSl	EŞ…£>„„ÂdNËOÏL¥ê 31+!L2‹0å.¶Ÿ:·H“û¹Õ¢ß°ÈºCæA)£?Å-JF¾±£üÆËíº¢+ú¶v?Àƒâ¬X”4>¥DÍDíTøƒ¼0eæ³Dœ¦æ /€àKüÆ†œB21‰4\"ĞlLRÆJ îPÔlô~©èÛ¼6èn<CÈÙ­rŠBº÷Hš­Ê7€ØãL€ĞÛfng*Gêp#BÇ*0f|m#¨?l¨tÌ¬ÌÅ\0¬¸Ê¬Ø!c(ïì‰BFÎv çğ…	/ÚÜOàv­‚ú­dûĞ´vâ(şi3ë*&oÜúà˜=¢R7n¼êbìNªü	Dìî´E®ºèKğŞ4n¨éo²9N±¨„Nmè°®0Kğ‹	Ñ’„ŒN(K\næ0ş‘\ræ‰D^ \0ŞÅÌ\"¢Lèz?#4?¯tšC&AED#iú&gÎ!¤”&©¤8dÂ”«ã-Ê·X›Œ'Š:&¢7mØ?Ôì®ÈjDĞe–TQbL\rÖ{bPšèœ\rÌŞˆ(Âä\nÎFƒÒİÂİì†l®„äÚ#ÃĞ°=ÑÈı»5-,„ÌÜcM¤yçLÇg FÑ û±ñ±.ûÇDİàA†äB3 ‡0Ô. Œ,Dæ‰PX?C\"O:r£^M	4xkòxÆĞ\n“'zÎçŠAñõÑ#\$5\$qıChû†\$ÆÀqqºubÒÎË#‡vG&˜ÔruòRo§+pN¢±’Œ¯-¤y'–e2=R™—\niyR¥oŞ=Ò&‚ñ*Î÷ ÂÒ(2¼e\"\ng‚‘/½,ÑÜúò¶bNXäìİ-äæßÅ)ï¼ß\0ÊßI\nÅK–Árè¨²Y!òøU²şj”Ä‹‘î\$ná3-2M“0æÌ`S-¢31®å2±¾Ş@A3N7b¸h¦{!âä¢á.É35[%Ï¯5’ÖûÊ¤3døiCS§‰#¦¿#vfRlÏD`í7Ò4G)ã8QÁŒÑ7£ ‡¤l^ˆ\"ó˜#F‹°èí\"DéS¨—îÌíşK †P`ØjŒb*3ÌÂjïÿ&©¿.|~¢~#d¸'#¨:À@}QPE0ÎÈ.H\n€Œ pîğıJ–ó·;NÊ†4íCX'BÎ!êR/ÌÈ*şÿ&) .\$O¬(›Â2#tD#¨¶(x#cŠâª ¿,*2s7°H`Q;¤\\\rë\"&oî¹rN¤,“B^¸-¸Fª&BâO®‚lÒ\$(.12qê:Š…˜¦°e,ÈBB&&d*M\nt›‡¸6oâ£¤R”mKd¥-µ\nR!c8Y†f¨zGC¨C@ô9ïÖ›ƒóFTBØbP­9ÍJ ¥ág\0Çg	\n,sf2­É\"(\n¯5#@ÆO¾úlòWÀêg,|ÚÓğØCììF-àIÈ’7dû3cvùkZÉepœ%8fpä,#5KÃv\ræLîB#zoh2sÒ&s¸ØP@lÀ	\0@š	 t\n`¦"; break;
		case "et": $compressed = "%ÌÂ˜(Œa4›\r\"ğØe9›&!¤Úi7D|<@va­bÆQ¬\\\n&˜Mg9’2 3B!G3©Ôäu9ˆ§2	…™apóIĞêd“‹CˆÈf4†ãÈ(–aš&£	Ò\r1L•jÀ‚:e2\rqƒ!”Ø?MÆ3‘–±Ï¦V(Ô6‰ÅbòóyºÔe·WhòsyÈÒgŒDÍ€¢¡„Ån¡ZhB\n%‘(š™ ¢™¤ç­i4ÚsY„İm´œ4'S´RNY7Dƒ	Ú4n7ÆñÇhI”Ï8'S†ˆé:4Üœ´>NS’ozÁ³µZW<,5!ÓZ 6ÍN‘~Ş“³¨0ø~3?‰«Èr3àÌ¾î!¸Î«'\n3R%±š®¬´b¨Ü5¸»2C““ˆŠë,»„ Şä8¢æ#ƒ”<8+˜îÆ³â‚H:°Íl†ƒD<‰\r#+_\0µ	Ğæ½!/ê1>#*VÓ9ğ€Ò1\$âpê6ŒLrf´@cÈ2Ï“6\"CŒÊHNÚÄLˆÂ9B²B9\ra\0PŒ‘<ĞB8Ê7¬@èÇ4b_:È\nÂ,7îTÈ)Â\rôÉªÒ¸7\"ÃHBHè=“â&‚‰ƒ{ ‚5oØ”ò<Ó:­Ëm'J£„3\r§0ì>8D0ğÂ1Œh‚Xÿ‹´á\0î¢\rÍdÄ³pÈãâ4.£0zFC à9‡Ax^;Ûr(ıAs3…ñ°_q4P„IØ|6±¨Ò031£jº4°¡à^0‡É8¬‰¼\ru*ÌBsÈ¢¾.Ún5¨Éµ&´/Š´Ë¡H\$î:á	«Ğ(ï‹jŠ°ğP+	\"(¯8	3âµCSö+Ìtcü‚„«L€¢9ˆA™”p¬Ä?¢¾^\n´é:ÊÔÇIÑ3¥øÓòh›FÒ \"©ˆë¦£ñ[ö%¶a	eF,y*ŠıBxè–M¨Ñ£k;HÆÈ¸»ZŞ#-®›G#HäÑŠ’îr#XÖœK8íÄkòÃ¨1#Ã˜Æ×4‚ˆ˜Šº0ÿØÁ&Œ)ÄŒ‚o{¶ø¦*\0ÜÑá˜Ís=Œ³îÙÖ´X–„âôËƒƒbÅ'ØvHXæ5±ha’¬D±Š–Ö´bÒø”Š\"“SN8±µj9Îš=ùsPŠ+¢p¨)ÎHÛ¢ PŠ•ı”wt&©Î·jbI‰â©°áÍ¨\n'g¡\0¬Âº‹©FF×+”\"CÊö_e. †aC3Ê.°H# @FÒdÎ@¼“±Õ„¥˜9AàÊxnlPÈ“²T¼XÚjd7é1‘ Şƒ1\n#0ÕM²@)a\\2Š\0ôàòı!uVŠØ35s9a’ÈNñ<0†ra¡60j\$2‚‡¸¾\$I\$‘ıÁjˆÉÛ'œ*7‚VL!98s`€ ª‚2–@c0¤°¶ÂÕ”nÖjÏZ+Mj­u²¶ÕJİ[ë„7ô4êÙÉ…]\0úK¯3ıº¶^†³@ÃŒ‹\n:ÈD±Es qß£ÙKÊqr†RNJNÚª\\‹˜9 ğñhĞD5Y’ÔZË`;­¨ü„ñø’‹†#hfºSê=¢H9“©Ñ\rå¤åD×‰Ñ^De¢ÊË(Û!.ÆÔ´–uägùŠ3ñÔ+92a•û2óÈ9/\0äKHaÇ>“H¦¹b¨l•Ğ_’ÛÈ aN €º„lÎI\";}Å¡ã8B°d@&‘Bº6âGˆj28Ä,å1Ùô€H\n£yrvÓQ;\$Ç\0ÊÙÊ¸œ¡ÀáœSl[¡9:j¸’ÀŞæîH¬Æ—VÂZzÿŸm¼ºÁòrÁQ\"µƒæè®àÜl‘ÅE\nøé0Ğzz2fçBŒ97DuÃya^E0¦‚4ªEf%âµ,²ñ–& 4ËrTK‘.#Q5Ø•Kõ63Ó\rJÃÓË©¯‹°”“„’MÊ,@Ws_EÎ™u{ä3! Û&|-­¦1ÕeztNš\$8äœ(ğ¦jşœ\$iyW©HBƒI\$iÌZÒX²>y}ª–HÌİòD‡Ú…â…aÀ4\0ZiØàt6öÅÉ˜c™U©ñ,!œ7,L)» €#Jv¿ ûën“ ˜[â	KVêe\$)œS×XE(A†Pòœ\nğ¤mïÜÉ„ğœ¨P*VÓ‰Â E	¦Vxn”aQé'cmk|pÎ!}’R¡±õ>çåTä†úÔIñ´!Ì7&Í”êX9T‡¾àÊ¼ƒa¢2ˆ±J˜ªÂ¥ŠÁ'R™¢BJXQJkXpñÈ­Z4ÊI]ävY\"&?\\ÎìTºyÆwÙìr›ó=AÔ®0¦ÑŠ30'*±Pt–B.Ê4šœÓµÙ8Ú¾—'fªZÑ3=¨ˆ(U{6©Ÿb*lÄ¡–-É|Ò‡£÷ß\\d¥„>¤%¹\r™c†ÆVdœ‘ušË:7;™Å6Z†]’­‹IÄ¡ß;¡mc¬•d(¤æŞ§BâkÒê_v‡Ä¶%Ö\0RÃ…+©UÄdÆ3I¼ØP³Ûxc%{üº•Ò5]ª8kQçò%çZABw1ç92FLFø`¤áÉ¨* ’Â8E\nO¤7n#ÌƒÏ„²!p¤ÍVd ËÅ~;úÅŸY8-Ec(Ä¬XC	\0‚İ˜ã‘m¢UêÆ¬¹‘Hc÷:\"\n§I˜óFÍx €E¤Çf.Ô/á!¨ÑÈ9¬€ÚHyu¦ıt:ê#>øji6E’vlÒ¸zÅ¶q3©‹f¦Ç¼åŒŞÅ.Ñjïì;wH|ÁQ¨y	r\nOØSK&&¨@ä¡’!&X?Ïº=PU¼Yàu&L(c<Æ‹´(Vù|Ó?†¸mZÍ³7Üƒ%ğ‚W6(uQáÔŞ:}ğÉÉ…QÚıQ¥S»‘\$úª”üâ_Ø£Ò*Íù•@9IŞüIôÎÆUˆfïÁœ›1{Yçùêƒó‡‰=%ş¿È[¿7ñk[PO½db,rQÌôRäşP#Îï”=ˆŒã\$Dyf¦B@àGBàRrmiàQĞ(Üp.y„Ç­*QŒ€ÇN^>í\"Jä-¤†9ïÜÿ/kjú¬ííÒ9&~h-àãXŒ‚éX\r­˜†  œÈ*Š\"©şæÌÙø-OÒğ#>Ím\0,¯¾ÿD<!¤˜UM	ÌÔx/	OÂP\nU	ğ¸OÇlw§¢²kT¯°5ægnbĞ¼ı\r€ÎÍ\râm\$g×°Íğ§¤<şbÚñ¯R4gšP#N¥PîÄ\"øpoÎÌíÔë‚Äí¢t3äÌ­øÂÂğ–ÎošÎÑ3l/q<şCBş±0ÂajóäÏ¯á®ÿ¨j¼¤¬%„d7Ä (éä7§ªİ)rHÉî<F”çJëÈ+Áx!©€Yt‘x?è¢@à.ÀÄ,É¸>.Îƒî¦íÚæ\\¿ƒˆ,Jn¼ƒbq‚Hq†1Åä5¬*ßÃJF@ZÄÉÇJ#æx?åöc„ÔdD0ğ€éC#F>d&G ±Aeõ!2!’	ï÷C>~lück=!r4‡¾R®u\"Îû	©ô:òB=%{ëU’S\$qc	où)na¢	&2Vv¿#Ntrt|GÈ|ÄàŞØ\"CãR	(¼É’¦ÃØÿì<¦(*ª\"\r¡Òfñ2R²yñÇòY\$’\ro×òÅÍ&¯Öİb`l,lmuÊ™+RÆ~LúçHªAræ0®p–\"s.òÖÃ*‹ÎÉ‘œG¦v²Í+òO1‡© ²Ü3ó>2ÍQU&ç\\Ñ­‚‰“<gÑšh\$ÏÇ³)#8v³hô83Zh.\0á#v,£.FÄ3®3ófàSj'3n†šB\n³7¤Òøª÷7\n:àĞŸ8hZááq¼ãFrM34ÒÒã.‡‡`ıï	r\\9ÏÖÕS;S«51høî1:s¶Ïò{/sË=“Î:ãîBäe&Kg¼‡À ¥QS1+°å/×?â¶>2Ë@Q/ç”2†LÎÀá£¤¤ÀP	°màÈNÕ&,wçVËÌÀëÍVÒä-BÆb¨`È¢‹ÓĞŒÚWDï]2 –ÿ€ÜhL†íÅFâQPœ-O¸3.Ü§¥zşcÖö¨îdî\r€V\rbJxBÜ!àØ'Ì%D< h@\n ¨ÀZP.iÆskZÁIz(¢fÎºÚoØb&ôQ¢^ĞÖì‚QLàÌå%\rñQ4pFéğK Ôš5‚rÓEB=CØå«*Ï‡ª]ŠÊWu \"£üÉ\0Êmîü!Ãÿ©Ö?`¨`mfáã’jì¿Ô]'Ös½ğhF&U%ï×EÊLÄ‹g-¬Ÿ\"ãVô-@ßä!V¯ÿW“¿+Ó°ÿÈH'#x7Ó’0Õ~g\".ÛéX¢Ò<F6äæÓÕ|Êşx#Ú›I¸£â»ğc¢5æÜğP%‡	,îH\0@&ä–QÀ¥¬îbjxÀ¤g–0¤î#¢wâHgæØRüp\ncV-óW#À9ïk[ğÉÇ”@u`\0\råã9å&+ƒZ€\$ô¤ÔÄgˆ2`	\0t	 š@¦\n`"; break;
		case "fa": $compressed = "%ÌÂ˜)²‚l)Û\nöÂÄ@ØT6PğõD&Ú†,\"ËÚ0@Ù@Âc­”\$}\rl,Û\n©B¼\\\n	Nd(z¶	m*[\n¸l=NÙCMáK(”~B§‘¡%ò	2ID6šŠ¾MB†Âåâ\0Sm`Û,›k6ÚÑ¶µm­›kvÚá¶¹BhH²äA9Ä!°d+anÙ¾©¨ô<¦W-l'ÁD“qŒäe0œÌ³œ¾õ\nXÆ¬Ävº”C©‹›”–-*Ue¡KY\$vâ¬…Õ5±ë¥N«W†f+PdF†šØZ\\a‹•ÆT·†ç¶·Jµ±Ä—\\V˜Lù°®Ã£#u\rõ#´´ŸHĞĞı‚¿e¦â)÷¹nZ4®ĞÄ®>ÿŒN©ÖÜà(µNì£‚Íºïª ‡¸j’(l4…{\\)Œ#°Ò7ëŠlX\$‰dË¨ŠÔ)ˆSÌCB¨Ü5Ãxî7(ä9\rã’^\"# Â12,˜È7Œ£˜Aˆ ê8lz82#˜ïŒŠY¿C¡:œ³È±d—ÉKd Ãîª²õ.J	Të¢ÅBLê!E2ZÊ)j:’®[nÓ¥mT¬ëŒ¾Î»Etl(‹ê~C..!h•¿l)Ná9á×8‚rù\"NrY¾IJQ®2º(2‚Ÿ«ºtİ\"¨áQä#ô‰ÎhŠ£N›2«¼Í'õcrØÄã˜ŞSs}nÁS\"È6ÁñPAÇ1Øßqü‚9G£Æ1Ç#œ3„\rÆq¨@;# Ğ7¶ˆA\"r4å´41ã0z\r è8aĞ^÷è\\0Øv,]ŒáxÊ7÷EÔ9xD™ÃlZÉÁã4Z6Æ#HŞ7xÂ5Nt¾ì!SéHÓ#ü‡¥HS±;¯tT·•ªM‰’BLÚA1ÏÏòâQ%S\0ò!ih+Œ#İk ¡(É»ÉX§j\r5¡fôæ¨®\"¢µ;El4¦Bú‹Àë¹‹Ã0¾ î|©•©!m±ËÓK;.™,;*N¦ºØ5éƒ0¬ÊtØPî£‰/ÌKÃ¡¹Ê&nB5Î¢ló*º´™ÉÍ”%?\\£´…PÒSG¼{WC¢sÂoUá\0¦(‰‹Š*¡ñÍ>ú;E Ş÷­ûÁX©µ¢‹“×>fïåˆ¶^ÃIG…ù¹rWè0Ş¶`ÑVR{ó[\"ĞşÆ¾¥ø‹tµëJ‰!+‹X@°ŸmzÄCßg¨·J·rósğß®vIÅ7ïL«3WZ!\0PDJií=„şÿÓ\n&@€6‡PæWs1áĞ:´¥²ŠHr!å±ò_C˜>ll3ÏC,%ÆP ôÑCHsFa°ÉÁ4ZŠCt;‡¨êÁ0Ê£ZÌlÊgDaš†K@6\$ÈCÅ \$…4Á˜¢íÎí.0\0Ú«Ç|U_±ÊSçé›ˆE¡C&¤Ü5Cb ‰‚xè(F¢¡Y\nxl¥¨³´K€Jº\n5\$Ûcy)ò9ÈêÓã¼yoxÈ- ÙT†7f]6(İĞª!ì¬ŠÂ ãá»\n 0¢4tÅƒ ‚Ë\\,%ˆ‘Rìq04†EŒ»ğ«Éz/eğ¾—âş`ıæÁ˜B7ˆ!Ñ0€D¦ÓBÈ¥”j¡¿Oé9Ñ”È¢6\r¢K/åHÀï³ø+0é¼£¢¦ËêS\"Æ±Ñ5˜i¥ƒÇÆ¤‡Æs\rÚí]ëÅy¯Uî¾WÚıëı€Ì˜+`Ñ\"%MÖÄ‰(~ª±–§Â;•ûxéŞ,:\"VQ¥¥ŠC”´ª ß*\$äy)yÎJÚ©Ú5(šB~Icñ7:2ÌÉ­)¸ÆÖøi\r°#&T¸\r¡•§†ÍeÊÍZkT3Z¸xg†ËqÄdáÕc:²0y¸CdMxŠQ¬½Cœ¨›Œf\"'º’t~U\rE.D…É3í#ğP	BtÔPíÊQ¡\$L S)J‰¶°Ò/Éõ\$KáXn˜n!\nì×8r\r(:zÈ¹Ò:‡+)hÕ´MY‘¤±Fa–áY#lEÊ/BÄ—ÕC\rQÒÚijCTQ]îğp\\«\"¤v·Ã@ia¢\nBåëWLàa†[`ÂF¶á­ƒ•{¢õÕ)Ö5\0ºz•îñÉP¹3Î¢1\r‡.N	Ñ<é}¾ŒRUÁMs¯NH#.‚%yZ#óÉ&Á:nOì£Â¶2˜²W\"Ç˜·‚0v¸®!Õ£ĞÌ‹l½£«»¢€Ç\\•v½(é\"[ã,MŠ‘Vè|ëFZlĞ9ÄDc'ç”P#á(®¨Â¶t>™Ï½*”¤![ìLzsÜIäèâ°”qCçlØ¯4ü›‹XK¹¡5âJìÂf_\$vG8Y#©£xF\n–s™’ÙNI´ZFztúú:ğ¦Æ%RKcMD•‡çªw®rDyA<'\0ª A\nYëPˆB`E×jzG<Ö°ä#Z¡x†êÄ%õ:æİsÚ¥Il•¶2CCŸâA*2´\"SÈ£Ç´º(M¾SÈû{²àæt H6ëxÏ‚•ßæı€m>x¯}øìÃ½wr~\n5şJ=ºİˆ„éV{ñä=µ8TœV»ı\\Då/RˆØh9,Ç®hëöW‚Ù5cœJ…qE‚`êˆh,º³ÀÚ<t¦:Yõ7J¬-m.òÂdí’–ÓÖ:¹/ë\re?\"İÙGgüDQ]‚—¾.…B`o`ü8Àë\\ƒquò%.0éÛŞÅöé*YË(OÚ~hªœ¹ÈS€kÙ³81¡.o8r’/\nR)3#ÓOğ|ˆı*\\aæŞámdø¢lq³ç¬©à‡ßk ¡Ç£'zÃ/¡oŠš?t¬òäÑÙÒqN÷¾ûZxb.™ÅáñoÅc}Zµœğ#…˜º‹Qâ\r¨¬ªP®‚ Aa c¾ú²ZI“Gˆù \$+‘ğB\\8jˆº\nm3šx/+‘ØãÖ§&?jQåGó*c¬l4l°ÀŸ°¿IÔ¾äzc,Rf¼Ó,²ÃB,ÿªMËV9ãvjæêÏjÿ.\r§ÚrÆlîj0ÍéIî3C9\0Ä:)'L BT¡Å:‚pJĞÂ á#\rGMMı§\\Øèôàl~mTÿ§”âüÔŒöLÂéG¶%\\Ş, ¦'kG”ğç˜R\$Úê.6P‹\"Bfvàƒ-\n¦„²%J.:{+B¨ô‚DtãF‚hbnÕª2ÃÊ<èæàPÚï;‡ƒpÊé8€l-MˆTX<æØ*-VÚÛğ8ÎP¢Úá°n`ˆE€à2€~è°Plìîod.ƒ…èøñ’@ñ*m‘–-;Ï'O	pÅNµå<.‚ì‹„°ğo\r€ÎE¥Â\r\0ÚHVëÀÚì\"â/ç'ãÆ6…Â›°”ábäŞ…q0³«hm–âHöùd\0!Ğ±±©„=q®®Êc÷°ğ”jZyë˜êî1ŞzñâùPû‡Âzò‘ÚŸáj(BBçøP&ò)î2eÒ¨nª&/àÿ£D|‚,ÂPÚ'ÔÜmñrÿs¦a\"üÔé•bòÕ±¥cw\$7\$ã•†i²X€¯9%ÈçóCì/¢*7\$?B (\nv,KGnâçÊÄ.†P°ÔŒ¦K_&rL7O‰R0¯\$o@4/,ö\r²Š¯.ìxr`yüî±]T:2Ñqñ°ı&ãš+„é%0ç.Ì§Ç–¡òâÓÉ²ß'wÆi0èáÆk.Êf„¥	ÚkAUdìQá\nMlC¡°0©£JË§Ò¡ÍÁRó4’,²ı3P4ÓŸ“5FÎf7ƒû,Êxù¥6Ù%+5ñ¶oF»7Ê\\x0¿7¢Tª¢?\$°‹¦Rk‡İ2Õ/'Ş<.)G:s\r,Ñ©6B}óâ±;Îys£,óšÄåÓ¹<s¾ÂÓØóïR\ri’2+à¿ É\nÀI1|ôä–„t«Ëú°\nĞûóàô3ç?àÊ¿ªøôÓô Ø­\n÷?Vö’ñ./gû;”,Ó(ı:“0’ÙCT.ÓÛ.F8d,LÛ1§ÂUG7Hÿ<²eEìëC3n¡ó’ÔÔhºt=<òê	\rELí%*¡ÇÊİ¢4Â‰Ñ¸>çöC&¨ã.6RR@6Ps/!T/W+ÂU‘‰\0!R)æ¨ø¦wJON¨í‘­\"B\$Oôıè±\r-•M‚v“´~yrù\nıs\0ÍfÜ¶€ä\r€VÈëÕ>LÅ¦â:‰UÃM(òJ\n ¨ÀZ\n,Ì[#Ô2/3`‘0Ô55ÑJ“´€êFFL1sÆŠ;.îÚ‚*/ë/3eìF<BsO1\\T¬5U†ÏgæÜN„(ñ£PéG-.=Šˆ!Bş\$ætˆà;ç®ÙïÆİÓKYôüóÂ)ZR¡DLLp…V*äîzPèé%Ë-PUŠ*Îrgì'ÆDm³K'N«¬UUÒQá|´Gt”pß^Ó4Ñ_]Õó^.r<G]¶\0ï5õ„\$ubPæjp#lJ5í\ruNgÙ\0­ŸÑ'Q‡hñQTøî\0ÚGÍ\\0<So\nVUn{3†vÂ%aJXè-]WL¬Q„¿[§EçA3'{EÂèNìÆÎ‡tÜ­½4.r•oíæâ‘Æ”IT•ègøÁv‘  ŞÄTët‰4ROÃÅO¤ºs¬ãˆÆ4 "; break;
		case "fi": $compressed = "%ÌÂ˜(¨i2™\rç3¡¼Â 2šDcy¤É6bçHyÀÂl;M†“lˆØeŠgS©ÈÒn‚GägC¡Ô@t„B¡†ó\\ğŞ 7Ì§2¦	…ÃañR,#!˜Ğj6 ¢[\rHyšWUéĞòy8N€¢|œé=‰˜NFÓüI7ÎFS	ÌÊ ¡Ñ§4“y¨Ë0ŒÇ&Ù~AH¤’kı!2Í2˜ù¬êp2œ«ØÓp(‹M‡SQ†RM:Ï\rf(˜i9×«Úh”ºCcRJJrıTf!7Éè“Y”ë4èÎÖ£¦éI7¯uzú^º\r2Ã›¶¥O»Ä Öú6øy·bkÙâ÷Îù²Oæ‡úd{•%zçM ìñ¼£s2Ú4¢‚*†6¢Z‘«ŠòÀİŠƒ¨Ü:«¢˜:cĞ” ÈBÙÂê[P:¯ãš>¾Œ/¼®«îÓĞ7£‰{\09…¨ÈÂÃ<Ã¨Ö9¾nò!ª`äÃ+ìT”‰ÃªJ9F€P éBC›Ô.cj&´/€Ò5(2ˆ„—ºÉtÒŠÒêzã(Ş’‰sj%+Ã“Ì\rƒkv(#é\nFÒÆ´à\rÉ Ò•EqSÅ³)äZúAÓ°Â:Œƒ¨\$#ã¬ZÇ#˜òÿ\rcM\$çÂÃ+Úˆ##N:o1îµ<ÿ'lHÅ&LóMÅHÍÚf:iú899£mVˆxĞ½ŒÁèD4&ƒ€æáxïm…ÈÕVĞ…Ãxä3…ì8^c˜ïqxD¡‡ÎÒ¼6;Aà^0‡ÉH¬­Kğ@¶¢t%@.kÌ2Mƒ‡»”Ö–#\0Ü¯\rcÌôş”€Pª:È	¼EWÜ µGËòÖŒ‰¸Â; NQ•e¨¦¹.j³J¹7¤°ºÔ.|ŞÚ& @ıÊ«bİ.H¦C~X˜@\riÄ¢ Œã:ö3èƒL£La\r›™P£ï ™º‰°ã,†Pâ”vñŒ±úp˜-)®8ƒ\"¹êÒ2aZ}•5P¶¯-#\\%Ÿ%Bˆ˜§¹êem >Ï·ˆa‘óx]…rÂccÑò´dGGæ½\"Øæ9ÜÖ)9ãJ_ŸŠ{ªB7]9ûÈZ’sÖƒ6<`¬„q5Iµh0–®uĞf¿J@÷!–`Ãx/i¨ä˜Sk”<Ş×ÂSfŞ‚3\r#:Z2ü¡ônFÌ¥â<Tba–Ècüõ‘©@,ê‹gøb\r¢<\rÌqùÀìéŒÊ\r‰Ez&”fJ\0fÉü2‚Òp¯’Y\\TFµJcTBÍ³¶Sa´ ±Ê\nñAP¤1ö\"ádT*Œš˜Å<K‹3ş‹œÕt_Éc€AŠm—ª¶ Œñ«&Šh\0“na\\-)pÀRC#ç\rN7X7C¸IMÀyˆ\ntíDBxDNTI±,¡2˜œíI„Q†ÑQA0ç”œhLé“PÒJPqj!qÃ?¤&)  U+|—å‚ESÚ<2­¦µVºÙ[aİnª£—\nã\\¡¸£ÄÕ¥`\"^!”4CüFCB`dˆüÇ¨„üIHI†ˆ\\Ÿ 0Ü‡Ğ¼`ˆI©PAœÉ‡5åõ Z¯Qaç)‰ ˆ160DÛêJ5ÒjNIå¨²Ø[KqoJyR¹0eaŞ5éb¼¢49Äqø£pZœxd\"Eø²·T2n«\nt<‡f(&Ã!¤ÕP¤bšG±cØ¯fúKÒ(s61«ÃpaÍ0ò1#ÒÙÖÌT…Ñ^²*}ˆ|,\"àTzÕÑÆQĞ”švO>4µFE^fCªA@\$sR)ü8b  ¡‚•]7)Ô+ÇMtÑĞV e.ÈˆR*sN|ª!¡ÄMt&F‡IšŠI‘Ô5‡yã¡åL}¨D‘Ìù˜0èp‹<Şœ\"œE>-ÄA?„Å_\"ÙÅ_èÍ}\$a²Ë³åô¨ù&hÁ)… Œ[yIéx\0˜Wú2‚eĞæÌõ(e&‘\\0íÇ+ÒfMfé\$bæá‹§SüIœ3`€%—ÔÄZiFä¤%’ÜáÅ%¹;A¸°HÅÔàyBïòIJeXV\r\"\r†ó^„€Ë	LWW„°—U0Â -`4R#ƒTŞAá?Ì†%—Fî¹»\$=ëĞÜûs#gÊÕ\"¸Bˆ¬‘#,\0‚_ÃÜt0dì˜`¨Ôy,DôÖâ.É‚@¯\rá>™\"J½,!@'­, 3&ZÉí8=mx¶à@B€D!P\"äĞ@(L¹QD0ZTL)ŒJLÑ8&f\$¡’“\$oa“2S3\naÉ×/Äµ×5EĞ„;,ğ¢_ºAuN~#šòBsÈ’WÑjì|ë’újùZº´Jm>•Ğ†Ô(%üïOİKbÔÖ–²F^ªV\$!É ğtüê¥VGP+7YR‡ìU2§Ç=a@BÄY\nö¯ÖöPÄ&w’š±ßp¥­Á&¶ÏØBû&g?@ ÃlCœ)¬3’ò\nËÒ¦w¡(İW*«´>‰šsV,¼C“bÆí\nF*ÅöSÍ/~ %`Ì”iw\rÎ ¶ÄI/Ç\0Û[‡\0Îå«àæÔÛç'‹îEK&”!¡ğÆÀPC/g¬¿†><áHi#Æã#ç¬Ñİ·+H—}šÔBA\0b)J†WŞaUÄÌ=ğ_tôéA›¼¦C	A7¼Ã%¡f8	N’ óÖÊa5@öÃuÂıi++eà¼=VRoƒK.íá¤é›°—×m\$„‹á¦7K^ö`;™Ó%ñW»öpóŞ¡§|XA¿¿xŸ\0;‡ƒ7-ÉRH—7:ï2ö+á¼D†Š¾{¼)ó¯¼IÂ¤e¨Ú·)tÄ{†ÀÈS¡ºjR±OÆMÌ²ÔıÖ>\$Ä>IÊw ‹ujé!é\"ŒáÃ	ï–A=ø)ÿç´nJ½[;[à()ŞáĞí©/ÆoY\\Q·áTÅ¬\$?2xÃÌ¹ÁLdØ#hèÛXuJÃG™†ŠòBìJ4î4'\0càÊzÆ’\rÂŒy‚l2Ïú³\"kç\nùb`=ÊâŠp,5j,5dh°:pÌÂPèXF‚%Ípi€ä\"‚ÖAM Wà(bì	íZ\"K²§ØÕ¢×ÉìÌìÄ0ZJ\"ğá@Êëc-P)NáBÔgt Øï2€Ğà'Ô5`Úw€o\"X  œÍÔàbÔÍVğ01çğÒ0ÎuŠ,Ì+¤·Ç\0š0\r¤Kãc°ì1J-	PØÜÊ3h\"vĞë11‘H=¦!o\$	c¤OZv§nXQ\"nú Oeï#ÀvĞÑ.Šq2ïñ9\0\$xãBã'*FĞ%\rp8d.0A4¤ÓÑcDJÑZ¥¦0˜¶ëÂp\0ZJâzâ\$TËÂ\"pÊ5EL)£ŒEJhcÅ,ÂĞ-),ñqfƒà\"\$€FÂj\$ã*²®¡\"\$=,X\$ÀÜ„8vÂ8W‚;BÀ,ñ È1WŒŒPvP aæåÉ¿&ja\rêŞî_	12\nŞoNoaR½-ôyMâŞÒ;ç€‡°ÓQbwàÂx1§!7RA\$PÓ\"˜âfU#g„éG—%Âèh&d‚\n	€ª\n»£f%CF´ˆÛÄ8#è|QAƒ‘!ĞcRvño!2™…B5ç8\0Ìj†¬ÔGD\r<!+øÿR-+(ÊÙ2-+Â,ZPäø#%H! Pçr\rgvw­!ğ™.rêc²'„wCw£ı\$—*ÇD/Òé0\$€7r-1öPà©.0Õ)qfñ®\"R)2²YÆr\r3Ó2âä.N“n2MÄ_ä g\"%.:®?4„­4Ãl£C5sE5À×6s4íÂä€È%0¤š @äÓpå.eÒ”ÿÒ*æ\$²æf#0ØŠóÒU0Æ\"\$nU9±lÓ;¦hµ#RC#„\"F.š:ò=©×Êxåñq=SÊPM~:ó2òE0d>’*E	«J›ÈD\$ÃveòĞÓæ:‚cA0®ä1ò¾w¢1'3\n‘&(',óÄ²KÄÚôA±÷ÆRÒ¤PmÄğ”;t?D¸ †N ØcÄZÉÂP‡ˆl6‰x¢Ã¢Ê`qèêì\n‹^	ş6\"t„1N:e^F‘AnàV‘\0 DË&¹¤õ‘ü>‚V­,Ş=ÏìA¦?Ô¸>hG\"^KÔÀÔtlÓãP;#üÂkòé\"0Œi¾Áƒl#Tà4£†e€ğBJ4†v.Â…Èp¤xŒCø^‹Ø2ÂÌşF3PF’#-ìüºU¢Ï¤kÆIcÓò—	•ŠJ,øp…•2ß-x=)½ É RàÂ/ìpƒVĞ@š\rğPó.é&ğÍB±ÍW&ô ¥öÑ&<Õ\0¥\0ƒvÎÂ(6 F\"£¾Y\r#ÄíœpE\$ş@úÏ¡	zÑgXE¢TvÌüîÏS\0ŒÌB#ƒ‹…æ\$óÀXqû\0\"Ì²T.³À\" "; break;
		case "fr": $compressed = "%ÌÂ˜(’m8Îg3IˆØeæ˜A¼ät2œ„ñ˜Òc4c\"àQ0Â :M&Èá´ÂxŠc†C)Î;ÆfÓS¤F %9¤„È„zA\"O“qĞäo:Œ0ã,X\nFC1 Ôl7AECÉÂj :%f“™†0u9˜h¨ÁÌZv¨MŒq™M0PeˆğcqŒäe0œçç:N+·MæéôŞRŒŒ5M´Çj;g* ¨±¤ÏL™ˆ'SÔİ”Õ\$„ÓyÓŒáyÌ=ÇW­Ê³‡3©²Rt’¯\"pœÂv2„LnÎd§šN“hMÀ@m2)Ñ€@j¨F¯~-…N\$\"›°úsŸãñ9³3ÓNÔ7¬Ã8Û-L˜ü?O\n–77eKzé©éT7@ïÊú<o›ŒÃ0Â½®)0Ü3À Pªµ\r‹cr\"L;¼¦?£t\0Ñ¤Á\0ÄÄ¢	¢\"ÉÍl× ƒ¨à„¢î›ª‡¦ÃhŞí²®¢ÑŠÃ(ê•¡î’Òµ±HĞØµÑÊÎ2ÄÉA€¡¨©ÂÌ¦«F'\rãhÄÃ ¢\"ÜˆACD¤ÃBĞ0˜es^‚ˆM@Ó:B“†Ä PÎN,âœ‘B˜òú˜eCÁJ£.‚áEdïO¶‰ìu'1@P…2£\$y5KLô¦¶èû#5c¥fµ(Õ»(³Is^¦Œ­PÈ6©ŒÄ€âHh²1#&ÒÜ>1Ó)°Â:´p›GUte ƒË%TÈ…P2…\0x“8Ì„CCÄ8aĞ^÷È\\0ØöKò‹áxÊ7õ@î‹xD Ã€Ø:Ûˆ˜Ü3\"Ét\n7xÂ\$‚:œ3Dwo•)«U¤\nNúM`è¸X­£lÜKI;b‡Y•jÌK¡\0íÎ\nˆÊ6àØÎCC£Tè\"ëc¤ ¡(È\rìûÂêZ¦¬íÏ£xÙX«\"Í®ºJU>	,+„Té!®:N,ˆãŠÉ”÷##LÙS-4®Œ2³ó#^¸ˆÌŸÉ ¢ÏµŒõõÀOC–R¸¹¨¤‰gær±J@½0Ã~qiBb(íX¢c'nã ê”LÃUÃÂ‚˜Çq2»‚Òˆ\r™³İbŠC.æa•Vx¦(‰\"£Î®8æ¶>ÙËkæ¶ÓVÕù*f²iûµçŒ çğmŸl4|,¯{ëKÙ{íB‡:;%tÈÏ“\nJƒşÉ‚™œnl½Q†\"M¨5í@ƒò0}ÃÃ—7!\rò¦zÍÖ(BŠUôàbˆyŸE\0ï±zºØa‡eáÈÊ;€Â‹Cyc,l’B€æ‚“#ÈÃÃ0|™‰±Lˆ\"4>hÄ\$*\r¹À´È€ÕÔ\$v,†8‚†ô1Æ.')ˆ4WŠYÃAZ&˜×ÄC8ŒMy0@ÁP§‡+d&Ñ0‡T|ºI©m'E¢`ÜT‚×\"+ˆ›5•€À.1°7Æäu#Re…B;™’cáå9’FH4Ë!Šc»1ŠÄrğ¡Á\0!,iü7²lÅ ±D`€ ¯×ºQ‰&„iu‚ÚWzñ^kÕ{¯î¾å±#`	‚-å|ÂôÎ?á@§S\"l\nÑ.•<•#áx‹­½]½Hû&Ú×M}%¤ÓõÉ:`›m¬ıKÙ0Wt^‹Ù|/¥ù!æPr`,\r&¨™S ì9¤020¡ñ\$áµIFu		2ˆèpÜv1XT8ä¥)Àt¢8i‰ÌˆÅ¸äIáYô¤ÉB3<CÄg-&‰·„*	°sïñ‚àgÌãn:Œë\$—ˆxãÙ:äBH&CáA“5)…:@;õbpñ;«U2…\0\nÍ\"ë€sLÑl!!¸†òJIê­5ZëeËÇîşEI§8g0”¤œÛ‹I¥A¦\\ÎUzÅÚ(©\$Q’’@’ÑËn-PšB÷'–‰\\“¹'™’šÚÁs\r'Lô‡\"TOX^¨¯½Û±Fxk¶!)… Œ‚Ê’)&‡é(¥2Y(ñxih¢±SŠÙÑ¡éÈÃò^E¨méü„³êDLâ¨3—“\\zPC;I‰2Œ)Ú¹ÄÎqĞ:VÜª’zÖy‡xq|Ÿ¦M¥­\0K¤™F\";H£åø\\Á…ıÄJ¤M‚€O\naP”FĞ@â×41€E\n—\\Š“ï/·ŸUHYâZ×½4:>>û‚ëL] ¢Ubà¯Œ­f\n\$åÛäZpo_—”ù^šŒÃÇ	à`©ZTTJk=-¹eœf	¥ş-y,©ãòŸZ9²%9qu2¢*Ùnõ'´Âp \n¡@\"¨s~q&\\î“…Ú£•á¯6\r\0œH‹¯:–ºŠ\nƒÆI]ZSá<81wzİÌB\rU?”ÀEKCæa¦Z|Ûôıáw¨Ÿ§¼È(ECºŒ¸ª·®·p}À&A¦á’RÏ¬*›ò\\û1Ô•™Oˆ¢>šÍıÚ8<g°Æ' °PÑAd´5ÆÒ\$±cé¸\nq+us®ü‚…3ßRˆr¬†V¨ş	:[ŠudÊc¨€¬UÕVµ&Ø’’›jP İÅDÑ›\\ J9qm0?5ÎğË›‚¦ä,dªã§ÒdkCc~0…‹¿0Ê\n˜)R©dC‰£Sa× ¦WÎaâ¤aiO­ö”Ëƒ¯‡bÆğ4ë»‚²%â%PÂ9µ+Êxm0„Ğ<=&fšÙLéŠTô—´û]Y‹Óf†«L@û7U3cµÈ3¬Tüà%sã1=”†0×ªl‘0©è¸iwı‘re*}´ôb0ÔÃyÛvÅ`R46Ör”p«·iüøö­›-\n„¶–òâğoÙu–P*†a{Ãìu²:-u²ß¨íáSàƒØ‘¸ÈZÃSE!A¸ÿ2)Í’§l–¥8‚Ö¥@]-gİ¤•ÓÍŠó½g¦\nt)áÏøÇä-_—î½ºIëA§Ôöz[.<‡›ë|(IÏ®¨Ÿëıš—ñşIŒ÷2•á}D¾ø#Zû´øª˜ûòªï®7'LY¼dOšdÊÊí¬2Âr. ˆ¬´ÔÌºO`ÌŠ%¸uğ6;À® Ä@ğ\0Ó'¼(Ğ*5DZşb’2‡nb\"2EÂâ6‹N38®¡Êš3/Tî 	 4¨C„<DÆÉØéğ¨n6¡­ş\$å\$ØÎHsª(#hĞ¤'°¨0	­÷BK\nÂi*0Ø¡RAdŠî%~³P«\ri	/£\rè1PÀ©/ŸdhwÂ©PÂ0Ã9í}Ñ\0gÉ†ê!îêÉ†ôog\nâ(½c\n^-N:3\r~±2;HäĞíw‚NÑq4L€nĞ„m.™B<8bHlDrbN¦ ØãJWN”‡&\r¤F¾Dm\$.-kª?F­°G-sğÂØğ±ñ”U²ãÅ­\rOøºp³çàÚ1­Kîmq¤\$ñ–ØPĞøüq¤ôH:\$ñÑ\rq Ù0È(‡¾}‡Ó×R¼m}§Ûñ\\üÈ\$†hp\$âJä Û\0)ºLÏÀ÷‹EK*\$k!'ÿØş¦Jûïm\"OÄú´(jğì%\$ìlÌ«!fZ‹+\rìÖ©2NÌ®Ë#Pûò^F²c\rrfì’T\"QÖ/M\nFM‚ôbà³ä[¢x5 UÊUÊˆº§\\;ê6Ê]–h’ '«29çEn¤L‚¹’‰%U²š\rÌpAFD[Ñ¾m`Ì7ˆ\\ :\rş\$ÜËà‡\$ĞÁ%Êâq~{‡<.º|ª©(£_\ræ(±&ò“#PêCñÆgÎ(1Å%í¨d­¬\"£8(Ò0³aS4Ğ{\ns#\"“Y5ÆÛ3‘“È“JS3NCs?7S[7“^iM@%ÏT. ¨¤EüO¤ÿèrA…:3æÃ,h!Ã\0Pß#“#SU33µ!sc\$Q¹;ó!j3£G7'ªbƒ\nbîœœÇŞ§Æª@f`ò@ëâ:pÓİ7óBÛÓèO‘d2¨T=0ŞA¨0³Ëqá‘·\$‚€DA5(Rret €”3%óÔÆt\rB.­?“ñCô1 é+3¹A‘ù”N,Ô'3£füfC+ÇDC\\%³9ĞÔâl@Ê¶#°‘¢ÌŠ`ONİG„mGèZ8Â2´‰GTOô{ISÎítœc´˜=TŒíñèï(î‚554UdrŒîõLm6QûKÎænómsqFP'Mö7ÓNtÍKğ99+º.H8LÜ	„¸ò…GL1±2ò\\1¤®E/+MÓÃuP-Eón\$€CñìQ'üY%ìf”x\"ğ·Ok>M¾œq°…ã„x«ÆÿuH(ºØ“ÍU\n‰À2crk)nRã ƒè¾.&³†Ù5'	X0£Xbš&ÕaARV€†)ÀØlÖ\r(:C‰?¬^;Ãºz³\$\$m|Èã.İDw*-ÿ*dğ0£j°kb\n ¨ÀZá\"3Á<Ì¬­r*ùÂj•ÏYÕï\0Bj²Â*¥ÑGDã‡ePàè“vóÜx'zÒÀ[ââDä°Å§x\$C(ö(ëX;Ö(¨Êe4QU/„ş Q8aå¹´OÈ^lİösâ2®“Ã£ˆ8ÂÜ´ÂÕBÎƒ™_°Â«€ÜxrG\\6dU›&ôÔR]`6‘hø'³Ì?©¶õˆ&Ö¥imù%¶±gíÉjdmcQ°‘¢ÔY”n†dD1â\nx'&rlddV€zO,Ò„ÆMÖğuƒ¼£‘hLk<\"	Bòƒ8Ûr@èpÍí*tä²èxë`ƒ-€ØRãrÃ­7)h¤ÍTÖ[\\†zmë«²àV\rAiÈ&‘§<Œë]r \"Ë .A\0òD”mª|ŒâØHş\rÀ"; break;
		case "gl": $compressed = "%ÌÂ˜(œo7j‘ÀŞs4˜†Q¤Û9'!¼@f4˜ÍSIÈŞ.Ä£i…†±XjÄZ<dŠH\$RI44Êr6šN†“\$z œ§2¢U:‘ÉcÆè@€Ë59²\0(`1ÆƒQ°Üp9\r0ã Ë 7Q!ğÓy²<u9cf“x(‹Y™Á¦s¬˜~\n\$›Œg#)„ç¥Ê	1s|dÂc4°ÖpšMBysœÍ‘¤ÙB0™2Œ©¦jn0› ÆSvİ£•ÌÌFı]øÉ¨9b\rØó”—gµa®¡8ËÉ²5E”Aá5«iÃŠvÓUïXÙ„A—:^´ç¨İZ³Ş:n·’‚<oUÁöœ½ø,KVßÆÔPQôù<¨ Òá§ï»û\rÊÓú÷/Úú!2£’6	0B¨ç	Š£pÖªèJ~“I@ˆ0£CŠ(£*Úªˆ ê8#ˆàÇc»*2%ƒ*Œ#´~¼\"ƒ«nï5Kj®­8lË6¶²)J>ª)èğï;JÊ¶ƒ9#j~‡Q:4«C+mKPö¾ŠC*p(ß/ƒ‘†VÃ˜Ó-% P¦Ÿ»\n+ƒ\r?ëà!¡Ë76BV·)DÁF\rÃ(Æ8\"‚~–Ó#~hE4ç¯¢È Ó(ä[ñŠó2±‹/QhƒjïÒ±M&;¨S0ë[!±Ätù\0xï\r`Ì„C@è:˜t…ã½´55b~2£8^Úñ½scŒxD¥‡Ãk+\rÃ3*–¨h@xŒ!òQA¢c0„<­Pé07t6EÓÈëM°°Àš7·éÓÊô”-Şˆ\r8’8ì<è0ß!*Èœ½¯¢º6¸¶¨(J2ò­9^[—ÅÔZê:­‚Òµ¯´fGNQLK2çèğÃaÎéš‡RB³c£pÍS¯·å5¶#3Ó†#;3µ3\0„¼pš¤±òºRù6›CQ?m¤&Ojî„&¯X¢¥r]SIÃrü9í4Ê=R¢èJ·8«y›T\0¦(‰€SHâ¸;\$\0àáøŠBòÒL})L3Ñá˜â:è“C\0õİ…¿õı)tÊOP‚Š»²šÌ•‡7 ´ÇØ1CK¿Û´ÀÉÆ°˜Š<z®‡Ù£˜¦-0ˆ\\?s@±÷î±)a\0Ú¼F6]ØÆ<#’»ã”\$<Ş÷ÊQ÷aõ0H!Œ ø¥ÔQ24„N‘Öl‘-¨ıô¤³hD@s%TóSœpN“-ª•L“V¼TÑ£#†<ˆ5äÂìHƒe€È%éÒ“	Ó^Y(\r&¢nE\n£ 6…)–*Æ€HD†8¢Ã8zh¤8„ÆnÃDaø ˆ\$z!³(ŒÀàĞ\nSÄˆ7¤Ey*¤*¤-¾’rÒhĞñm^Dp¼!`@ª–ğrY0ˆ‚8²Ó*ÎZIj-e°¶ƒºÜUh\ro®ÆyE6ÖH }\$‘B€d¡4†Fc:.p«%¨†d‚ÕºrIÆâU	rº6\$4â¼Ôn!©“áÔ,¥™ –ŠÓZ«]l­µ»#,\\Y&…Ô»£\"nÁ†‘×ÔBØ€}(İ	\$D]‰ñ@A­Ù-„Ûô6¡Š!°“ÊhÃ¦5	\$”BFwÔÙ|!+\0006K‰Ù8cš^e„3¨VÂÕÀsÁÖ\\`ÏÖ\0t\r®{¡´¼©a8A/86FB“\n°xI´Íd,‘ÂI¤4Â–Ğ \rïV•‚–\nO3)	ü‡ò\"DäáT%\r]¸ó¬NÁéÈº˜¬´ı\ráİ9IÊ2mÀaOà€…| –JYÊp€Ã±34ñ6z’3EKiŞhİQS§=ĞpXks£°@ÃBT}A¤3­&ZE(”Qhæ>z†ğ×ÂS\nA»V‘CZÉ8 ¡º´ŠÔY+ RĞØP›ÉC×TFüÕM`Ü—Rü×&„ÙJ6“(uTùB.Ph#â9)’hìş	/¹Xˆ¬ãÎ'i(ó“æ—hÑI#Ñâdd×‰ñ\"K483^oSs\n<)…BÜKa‹€lH4ĞËÔã'!†ÖŒ‡S¾Î'\"¼7æ2|‡iW—„a­tÍÀˆÄÄr21Ğ„™â¤¸LR—¬é†ZKÈõ;Á*Se´àº\$PE\nedá£z)*‘ó`ACÀoñe´¡%6OğH(T³öL€RØ	PAaP*„˜BĞA\nUp'„à@B€D!P\"ãğ‚J‚O\nAK%dÌœ(L¹Sz/kqÛ8gYšÏÛÒ6LÓÁ&Bğ¡TæÃ†köm²_³êaàeL=èÜ'ìDX¾…;è¨×ĞŠiÕbt¹µß×eJı•9½ÑäY“2Lùmw˜%ßZ;Œù§Ó¤Rz–0(·¸4›§´hQ?¦2’uƒz¸L\r8âÀ¶j_ÕCM'ô„”°uOB­\"¦ùÒ¸/t¥/¦%-›T6Ë—vÇÚ‰Ä3Ìj1–4;¸ÜæUÌv1ènÇáPõ<ğ©…‰q0QfÅåîƒ‚Õœ\$.ÓzDà„ÃxN`p\rÖ‡µ÷c ºf‰ÛêÑZ‹‘(\nÚÔï/D\nlCIä5M7ŠÚp«qBD˜6€¡†#OÙ!f®©¡[ÊUÃo5Ú\n%”_yc¬/ÆÔÔŸ{´ôvsòMŒ1†²\nöÌiZT5¦xwT6¿ç™øß×> ey+L±—kFÈ®ÀpSó(.1ÆÂ2óRÉ‡YôƒÊ¢{`\nS!€@îN.	#r±Y#4kUKóö*gz>PÂ4™/Å–x¯Ë	èñ‰Ottx3á’ÀxSKÔ†’Ì¼‰Ş šdÕD…0sSWóg?ÏDO(cûSS~vSFvÅì.ssÌú²6J—…9ŞõåúÈÕ¦=Ä‰Ëü<Yï©V—¿„²rZŸ5ñPšoG—ĞW¨­~âT3ğ¼9İ#±;ZüX=¶(BäG* b€¥ûëùËúóP;°>\\‡8²DeR`ÃÊc4*Ï  Ö£48¦˜RB&\$§¬?&&b\rÒnŒÈ¸šFH%rT°0d&ˆzC\$@„ô°>°4`d¢ßp.tÃĞİ°8ctŒ°\\r&,ªÄìŒĞ^rCzÍ‡ {Ç&]Æ8&œ.ÍÊ:ÄäªÒ(šL\"L\n0ïô#\"BÕ\"8#Ä#k&<Ìã½\np¶¤°Š]â\n#œ'í<ƒ,H-¤öæ\\¦xRÚ7åDl\0ØÆcŠ¢NJQg(<Î†üĞLØÚ£òŸBıÂ©FZÕdXÔÀN\"¨KĞh\"ƒ¼ÔmdŒqeyÍX!ğbtP=±9±1í9'L‹‡jv13ñV%±>uqBÄƒÌ|czcF%§µ140 äxã`óï 4ÏDöOXôÑxŒÑ}Zôˆ®1Œö‹S4ğŒT\"ˆ,èQcqçñ°Å±´ÕĞs±¼è#`%	ÌPúÅå(5¯4LƒB¨ˆ~‚8‚&\$\\]ÂVg…’'	şK\"†Á]\rÈ´öÒg/`wÆæäÈ´HÀœÇš†BİŠBÖ¢JÅ\$ƒêgP€¿ñâæíæ\r©âzÎ¦çÆo!\rBC @c‚5qO%Òaã&)Ë&±LU‘4‹‡£rW~*Ò‚`q[2R‡CÊ²g­_*¤8Pª/ Œ@mæã`‚qdîàP”<¥@ Ò{±h×W2…Ê\r(²Ñ	&‹)2Î\$ÒÓ.2šå¦¶y¨]£Ë-âph±Äû†¸p³\nòé.ª-BR‹ıÂÜHC²gCyj°yÒä”3-\"q¶tr]2§˜«3:Æ£3j³0³K4'œ °Yh(b3\"=6İ/‡ºg“Q6…é„Êö	Øˆ`É	îÄG“vS@×7­D¨†6*ğq8Ó°è†hdPğØTbvè²Ë5É¬ê¯xPÓG(³¼êäKSÄéä%<îu€@“Ğl§´ÕóÜêÓÒë\0¨h‚z«5²Š«ËQ=qfšÓü8¨Üéú/3ş	\$`Éñ˜Ú²Ò8\$âHM¾fRŞÿÂ>\rïp'†:'Bâæb«C‹v“‚ìk/ºÂVV#b…òÈfPQ-GôogCÔa\"çF…PŒ †O Ø`Æ4àÆ¦@Ä­ì2iğ®;£ğ5ì)Ä¦'l¤r§#ÊT±@ÚèHÉà¨ÀZmĞ\"¬Aï&û´Ä¦ƒ:\"Œ!‚Ø§â(|¦¯\nÅy#ô\0Bdâİ0kää83£Nëë°)4öÏEŠ›¥İJçZéSÅ\ræG<¶J¶wõl„ŒK¼MÎLCœ&ÑEÏ0p,Mƒb'ÒÔa#èÂĞ‚Óğ\\-µT¨ à3!CUÑ)V\rÚŒ3[Tõ_ÃxŒ,ğ(b‚ìk\rMCWÔÒUTL’j ¦Îq¾ˆ‡URWlæ\rÆúÎÍ(Ömı[ë!R/änBn§€Ş§È\"R²òµIä\$85‚ÓƒÈ{R¾\$ÄT8 ‚6ÇNÀÊ÷ƒƒÂxBmTc¼Œ¥g{‡Rcâ~fÕfmM\nğòĞ@‚óCöCp#(®Ğèl *€Ü"; break;
		case "he": $compressed = "%ÌÂ˜)®”k¨šéÆºA®ªAÚêvºU®‘k©b*ºm®©…ÁàÉ(«]'ˆ§¢mu]2×•C!É˜Œ2\n™AÇB)Ì…„E\"ÑˆÔ6\\×%b1I|½:\n†Ìh5\rÇ˜4¶-\$šL#ğû‚@£'b0åT#LIRãğQ\$Üc9L'3,ğæ.´N(Ñ	\\aMGµX£kŒ1U•Pšê‰tf×OÄn1‰Ì[	ÉÉSVˆôqC–£ælql¦{Q/ÕCQD#) ‡g¶–+n^UºÂ¤ñíVnB”ˆ¥¢°iÿ'Ì±k\"1hDªA³àèbÚ;9QÓ‰ušı‰´v‘GÓêìJŒƒ]/è)\$Q)·¬\n*›fãyÜÜ£ä7L\0ˆ0ƒÄ½¯£ Ş2aø: ƒ¨à8@ à½c¼20¼DŠ\$Cè²:ËzŒ÷’iJ\$¶¢Ékä§/È3\$¢)j:Î±FMvŞÇÏÀ!D£Ú÷µ¦»”×DIz8‡2„ÊÛ¡éÜ¬KŒ¢ù¥HS(3é)0šìKÄû#êëĞ±HÑâL¬ˆ²9¡kŞŒI,üê÷DNúBÃ¥I|a*ˆ# Ú4ĞABß\np´09BƒÆ1Â3„\rAP`@;# Ğ7´¨A\rpì>å<4/#0z\r è8aĞ^ö\\0ĞÔD@ƒ8^2Á}YWC ^'¡ğÛ/´@Í\r°@Ò7Áà^0‡Ñ\n1\$Nç2­¢%HÂ96¤›5&Î…ì²0êûæùKdÂ#M\"¸Â9\rÔÚ\nŒ‘RÛ¡NrO6«Æ¹\$À´3j’\$é²Ö’®(²T¸ĞH4½!Oª<Ç`„â\rs¹æ»å9%l:_&0èÂ^ÊM¯{äTåï192JzÄ¦³RO?!›Æ´\$ŒãPÖeBâŠ%¤Âù!H²&%²Ú)8ØòÄœŞŠ2^Ç!á\0¦(‰šÚÈKºõô·MÑkÏu1¼L–£ÉTÛ~²h6M\\Û¼İ)-×£`\n:,‹mx·%*»F¾Hóì	72Ï²òJ¥èÁ ƒ%ì?Nkğ“öÊ‘‰úXópZ'¡\0Ú:p¥ej¯# ë„S¯ş9#ÍÅr0øæw\0Ì4ŒşËå‡ËğAD/Ğ0Ò9ÁCbûÜÀ\0İğüpËÜŒ£ÀéfSWÿ)¬i{œÇÒƒbÑ'e	–ˆIH°Ÿ-´ÑNê	)mOÊÏLåÊK‹4©­Â<Hú°(É=8ÔL×S{\"\$œÉ'¤^ŸÄ	7f†óŞ›T\"(”±¦R[ÁjÄPê\$91ø8XÌ“CV@ZUl®Ò¼WËaC¥ŒVJËY¨9ó‡E¾³A>\$0(’‰×°A‹i‰9D¨ÕVdh	´!Ğı=§w\nz„Šj%ä´•CªQšÌo>DäD8‹ÕÊ»Wªı`‡u†±aâÈK)f,·Üü\"ºÓZ°œŠ¥KEØ\"\$á@RjDšqØ-‡%¼6ÈIÒ’|(r–PÖDSA/#È˜ŒÒA\0‚ h/ªZ+.FC`lˆ¾‡¶Ãm¬40†gßTŠ—S!˜:Ì ØÃ;ÜTŠ˜*búø&P /-fE`Â›j3À’Éƒ EŒL—âŒ\0\0(' ¦R@ÈôCr‰qX7)ÀèÕXr\r!Ú`PÏ2ÕZB/}G)Y‚fZ!r,İ§fRp	\$rö\\ ²„TğsCjaíŸù½KC‚©Uhq-FHc\réê+™†_ixc.ğ2¢.J.	ğÂ@Wb Ô=ËÜ‹!CÉ,q\"D…BJ‘ëL,dœàGs(Îr*#‘¼1·<ĞÌ5-2|H1òÚ‰ª‘ï0•¬±¢a8T,;QDIş‰dIk%´Š7ö¿:ÒR\\(äœQ9bq/•	Â¼Ã€ Â˜T­\$HŠU(ë‰á²d\$İ‘jëVë{‹µ¹¥Â¢Ié]e´ØÆ‹lE1ˆ‡*O‚JC„´Ÿ<Gm™äÊ‹ˆBbs¨ùQã€r’ÛFˆ,Ô£¦Ò1Wdİ«#Ğ ŞÄ{É{c:%ˆ’V'zÍÛ‘dlÅ,C¸C™û™fî`„#ænİ1h9W\$¥Ó€’UÊsGÌ„6ÚK[ÕNfˆĞ±ØÉSXyÂGŸ\n_ÒÆl´oÃE8áÅØë	,vx‘¾#´¨ıœ]w\$6¦|LÑ£Ë!%á¹c)‰Û¹s×Úv_éé=§Å¹¼õf7‰¼‚AM	\n#•µë¸KÓk¯K~Æ1Ò\rz#…ºDI!¾¢|@zÄ¢À6Y²ˆ(sœoÁS‡G›*Æ>eµÂC	t7-‹à³8H‹´º¡AÑ†dMyç„Í¯VğeÈæ“%¸×*³^p›ñhÈÈºêÕ;\"{ºa¬‚„2ò‚í:º¬öµÂq>“İ-’CÙ¢P\n¥¢ˆ<£bH“³ª¦V3ÆµÚ£#ÌX·ºÔ\\}K)Ğ2í°ˆ¨C	}şATPy“!`(ÒĞ±í²È‘¡/á†¤òZ\rÆ·İè¨‹tŠDe™!–jÂokm‡‰¶¤ÿrTêˆ©>FJ²8³Êe>ğŞ»åÊ¨:öIw÷DD\"'Æ4hE+P_aª\$ÂnqÑÌ«å%”Ğ™ônFMİÒ\"(Ûs:Q6(Ù7}ÅÒ¯ƒ\r\nïÏ=àhr0\$ßÛ³ÇIm6‘ËÔhIØ!y—JÖœ	Qï€ıx£°#@EIì„½d,<rQä„†–‡^‰¸Géß–7¾ö¾Ä0ìòø.î{`ˆ¢è)|—\"¤¶Êò^^ˆ8âé’İM¶@‹fvá\"fOe6²r<'V™%znç¢’šŠşäÒ^¬ØĞ\"¥\rAèæĞÛœ™¡;„€§Ğ\0È©b¾Ã\$#\r¸û[‡ÚycôX@İüÛ…ë¬—ÔÃX—ç9ÌP{gÛÚ†'d~NáÜÏªRÌ´~ÈÏívãòÔäÚEríd}õn’ îãlRàâJá.Î-¨ncv»Ô\$#¸íJğïêéNĞ&OÏf)84%\0ƒ\"Å#À!\r>Ã#Ê¿lş(Ëœ)L%æœªîüÂdÊƒCŞ•jğ»«ºöOTkT½®ĞqpH4ô\"Èh”ëXµD€ÂŒ±\"ViíL»uã	® ,eæÔGf<½ÎÏ‚JúşÈèG,ï\nïNOüÜÍúş0ÌOĞÙâĞt\"J²¢%¦UğÆéì\"ĞñoÀÒÄM\" Í¬GE«>oXë‹Êqq\"õ£šÅ/ô1ø­°Ò!Ø\"D’se÷ªïpZüÄá\rï	ğiÏüfÃçÎ\$s#â±[q`ÊãçQlsÆ¨bÂ¼,ˆ:\$ù\nB\$Â3±@é”g‘VûñœÈoè©ñOÆÿ‡LWpòúÙF-/oÇ\r1†*bíRÖ`Öˆ‚ö§å˜Qo(˜Ä@QŞÕqäB)‰©Î™ê3MU Ñqş?éÆÖòöÉœR\nîÄ‚×Ñ˜ú&­\"p(©îNKB\"½ñ°×’:f1#‚^×ÂŒnÕµM°E1ÂªQÆÆÒa®O&‚?Æbîcå'3ÊCàú¼Ş‰Ø!\rpôï[§fkã #ÃäeÎ\n–¦ªhL¿)²ŒŒæúîòáNO‚4 `ä˜@V“ìÀ0ã!B<O	>dë˜ëP9OJ©‚É‚‹ël¯‹4½À@U Ì q+/ÊO\"\"¿gL9lÇ¨ìĞÎ`<ê_2à;bĞ­‹²ñcDß\rÎ†äFØñ¸åä¶–dT\$t©ÒÃ€¶rà;‚â!CTúÌ(‚\$zGlu5‚bCPÌ‹ÈhJ:ş·\"MDÃ\$ğnÿ7ï!HS#o\r,¯\"ó‰&0Õ7ÂBËkÂ…0XKäsh9QdË/:ˆË3æ}'&sĞğ©m7¢Äñ/ƒ³\\Äëêe\r0sÆ°ÚjÂ‹–Oƒ)2®I‡&(ÆŠph.*ìjœn“˜íB)\$_-Œ\nÅgC!Çn\"S'c@€À\ràì@ îÑ`ÊŒ\"Œ’¾Fiú²6Ê‚d"; break;
		case "hi": $compressed = "%ÌÂ‘pàR¡à©X*\n\n¡AUpUô¤YAˆX*ª\n²„\"áñ¨b‘aTBñ¥tªA ²Ù4!RÂ‘ÜO_ÂàªIœÂQ@Ìèq¨š’*¤‹Æ`¨j:\n°	Nd(ÒæˆO)´úŒ²Œ‚§!å\"š5)RW”¨	|å`RÎÅ‘*š?RÊTª˜DyKR«!\nØDµJ¯\"c°U|†\n—ªÉÔ³u%Ãg\$êI-=a<†fò‘HÕQHªAÔ´•%Â‚¤[M ©ª.í_†ÁD“qŒäe0œÌµ“˜ºÅ‹GèÚøşYH¡éês‹z.ıK`RC¯3Ìu±e¨ë\"#Iùr“·÷®ôêU­»Üì’®öIáBè#ĞR¤E#ÔÉ¿Ò† >+Šù¼IÚ§5)\\§—Ò/ ¯b‚½ê“Hºhó®ö“ïòjÚ¥Oòæé°M¢hÏğŠå\n+®Æû;ÈºÕ¼)ãî¹HP4J*íŠ\r «ój-OÓ4@#M-H”‰!¢ä—& ©‹1³è|H\"±ì,·óL¼D'ŠñHö?Dz1Ó¸§20c+2ñs50§‰Œ†ÎĞ!H(RjÙÄ-ìÈ“âÅH‘ó ¥«ÿK;\n©}'‰£4Î'2š/GŒÌ«SmIC5ŠÒ5?Dô³ïL(+sXK4Û'!5úUh+\$üĞğI/E@©H±º/‚”R­\$†¶-Îòñ&OÄ·K2³ìÿ[¶õŒr‚É‰5Xš2{94OâØÌXIãŞ²VrºxÁ\"óåMK³„ªyGµoÒhÛÇ3øÙ_i-†Tª½j:Ó:KWÖöŒa‰'‘UHRay#¯E%A§“e§3¶åF-KV*},:¥.nşgH-Õº\$È÷iS¶U.å­Ó¯CTæ™=µ&U\r±dj­-ïSÖ	Du	®±<^ÏÁ`@!\0ĞãŒÁèD4ƒ à9‡Ax^;ğpÂ2\r£HÜ2ApŞ9áxÊ7ãƒ’9üXÈ„JĞ}2§Ïá+*@‹ƒKSà^0ótÜNµÚ£Í¸ÚØÑ·™>M)6òİİ\"Ó2\$J=ò”ÙFc·b×àJ:‰“İ¹3]«Õ©ãy\"m˜îNÛù¨)£'ıa O»ïà“Ş¤\\@öšAè²t³GÑ¤ÉäøÛ´0aG	§„zEÃÒ}‚¿KšßX©í˜\nCnSEá}MI”£ôÑE#Ó6Œ!w­·ü×9‚3¡£v¤•àòDƒ\$ñu ”ÁI*;m&0¹ uúÒZaûRÂl;8Ë:	Glìù“A|ük\\c„>²ØÀÈ*xUMÿÃƒõ ¥vhLõ×~¾Šß%\0()…˜‹¡óş\"¼¨*‘Ïl'wYLŸ¦f}ûÂKu†¶ˆ`Éfq\r`Ã×[KOX	º6>hÜÖVdu€1Ü0³nnR\"ƒ)äşD·â©Ëb\">phû(×`má£ÄğLFÙcY®{&uƒŸÆ]*×BÄD‡JY»iQ![ĞSíeÈ¨ü¡r˜l	ı_E—øÜÔ¨säH*vªhÍº|eÂ¡Ó:†¿ HÓµ)éèÛ§ãÇ2››X+NNAaId¨¤LâÂjƒã˜²“	Ì,3`¿ÄÕ:M‘Ší©E¶—›;ØŠ«mó»Ø_:2Â‘`AHb‰™ÙSfÈ¶;W°lÈã`A_Ôæ­òŠ„äx«5PxRå¦'ØŞ6ŠNÉCo2Éêé)¨™›RÆrh ı>Í¹ñÀæs%©O\\±F	QPuB ĞÕbÑ2\$uLê0ÑMK0ºqOgR”½9P´ÎöTÛG¨Ì-4TµTJ¶l\rR\"ŸÁF¤¤å]¬.¥6æàÜ›£vo\ré¾7ç\0Ü„pÎ!Å8Çä o\rÁ„:›\$æ'R)óQÓÎêäÂ*özÆŞm4º\"›VÔùXÍ™.Æ¢g#n‹*í'bšmÊÂojµœZÉùD—E®“JĞ²¶¢ªú~§ôïVj„^_ë]j±ñš´g-e=s‹6î»P’‘‰Ø¯íÌ2·VîŞ[Û}oîÁ¸Wâ\\[qî8<Gì³s3…n˜ã9rÓd~µ	îvÕDŸU¡¾>æ²èºZâƒÕ•Y²kJ„§LŞ]´\\£(_EÓ²Ó6MÿÌäÕ€Ö> Mmf]:®·+B§Öù¾D¢Œ±\"UP„º+“Ò³±*é–Gâ¶y!\rJ	Ak¥W5%äRÂ€H\n!Pgı‘AAZ5µ>µ@g!¢³…G†“­«—4-zb\rMöàØBmÒ.¡ÍsÑµQ£¯†ã#+Tî¶“;ë¦Š¨DÅ8º¹p0ÆÓ´õÓqÌâ©»óÆeH—Ú‡é; NrXÑŠ:Ê«	+lÌªl˜ÚêôS\nA*¨†Ô`´ˆZl»	âûq‹HÔ,I6£RIø¬c¶+Äø²,âBcMŠ©Ö«¤¶.³9?Ò…¢K×9¤em+z½o|QYÑÏ¦½¬;J;«‡3ÌÂ3H'0ĞÉîÄëfqÌQÙ1]tZcùwRˆ¡nª#¨Sß\\˜p®ƒŞ\\©›ßi|\0Â¡—1´ŠİO.£Ş¢’ÏCS9”?“¬öäUgn‘~„[so I¶¨öTá\\µÅØ¿‹öúİ#!*e;¶YÑ€Ùçvé\"–‚‚t“#Ô=f®fÒ½mÊéeÌ·\\_Ö¥ƒ46uigßP£ÁN~2Rú'>˜õé¾&(«‰ÀNÒ¬‘\\éÀ(‡³&–Ø°«}§€Ñ”ó|î'y}‰¢# H¡#Š®Â5ò{qÒM5Ò9£ËH3¨¼7@îä!‚>oNîtÕÅË ªzSÒßÆjf¤+ø“1ê…mvßFÓòuSÄiÜ4b4™ë¿_¥<_ŠK?csÕ—ª]ûóßvd)¨94qÈÿE&€zLz,\nLŸV`3²ú¹ÛO tsvQçÛ» ¬5²n7ëÑİÎ¼e[‹^æÛçò`ÏÓo!<z¾~sÇûNyn>ìè¶ÊÊ|ê:ÓƒĞ»ëşôâÖî‡\\ùÇ®(dò÷¯È„ØòˆÒL\0¥\0Ï¶¨).	 ¥ïJt/NÒÈúÎ*¸0ù¥Ú¤…ºJ¨¹Šèh¤‘°ğD¢y¤¼\"ïšëFÖß\"¿¨Äˆ0<*Hˆ„(ôˆøİp\$«\"ôêb?lbymdÄÎà5,r:Îo8‹(6úp˜{ï{H€ª)Òä†w¬>¥¢Ho ÿ§b8€Ğq\0Ò¾Ç¼@ò—¢p@†~®©Ò-†¬J^}­ùPØ4ĞñoxÈ~ZExÕÆDĞÍD»@gü}f\0003ŠM‡‹íK’eÏ®ĞbàÌn6.ì]¸TÄˆJïìÌ˜€\r(¸{À^v´1jDp|G¼A‘‚RÄpªLjQe‹®§J°ÿj¶ĞÊ¼B#n;…,ÑÏj3ª‰£óë`BÚ…å)ï«\"ò1D‡fŠ„º§fJ}ª¸3‘ªäâ?Q¶¶\"³ÄUáJ¡„	å*]mî®@‘öšïÖÆª°›ª‰(À£FŞlq½#Û‘ß*µî¦Yñì‘ğÚ1ö'QˆÃÑFtğœåY°)ñ¦cˆœ§\$ÒCù éZ2NĞ°b4ùDCø•aY‰(…l†ÉÌ|¤Lín!æ7\$m¤Ÿ0pwÄ»+¢[”>´9çÀ–€M\rÀ…+Ú^còê#4”æC'¬DÒÖTñr)Vãq¶wRÓÏ°6äôC†6)è(¤º…ŒH>âÙR{1\nZ[¤øÙoDñ¶Ú\rÇ3\\Sg­—*‰L@dÊÏˆµ3“\$&ièiñÊY3D|–(­,Ó*NÓ.óJNÓ76R¸ÆòÆnNÓrdÓw\riõ,ÏSp -G7“Æğ–Ï€dª‚€îhe,ÁíLş\${IP#„y*º@ú#QÏ²^‚\rÁÈì¡ß(SÜ‘nM:IGP,\"Øí°š–§„ö‹º¥rÏîÀ[³÷	“hš~N•`Ü\$¥>éÆ«(ˆÊ’‰2~ïÀk\$ö¿&`uVÓe2Ò“9p!8“cDit÷ÔLmFL»-—7äM83I9œuO‰FáCõ3‡9´]ˆŞªsı,ÔpöôtÏì—ĞU;ïg<ŒHLì—8°>‘h}\r\rI„H”²—èğy3ÍÇJôZ‹'Ô'ƒ4“(í4+:S8J±lùAD_=Hœb’Tş‡Í\$d}D]4t¼y´íÔñNbãlÎŞÒ'¥µnÆÆ”Í6§oLÔ­L,ìIû´ÍFÔõ7GeÑR•?N½KˆŸKÔÑ4åÑ\n#¬Ğn¡ª]•BÌáFOSD†MQôdësğîäA)±é¶®ËdMnÙõrîÕvEN¢kk£@ğ™WuŠÂ©²@‰¸öÅéXCğJF\\P24(ş1ÅÂ\\ln¹µµYê›µ&¥u+T[\n†¥(/Qb?\nèå*îÏğfyqeÎ7TTÉPÓ_4ÅÏ+v\rJTQ/TU6aaË­`ïHT›KÙaô/â\n”©\rXì1JheJ²)TÉH”ÈÿaOUÕ9dDùd““UªYUöd6XNÔlve]©i’iM¦6µË–]õA¨¢Šst:Ë@ò…YiĞª-ÿè+M§z“v!6ÕPö[VµP6¹I4ËbTéEu9li4·ÖjgÖ'l%6)²\0ıág²¡X	CköÌxh+n‘×>¶Şô”ô}ï—aV‚óp¹DŞÄ–õ<ÏF±Ö”V»S6ÑG6ÕeHorO˜è—s33nEĞ&79ooßo´röÁiö+slõtÔkL–üwIuï—v3¤ù7\nÓ—+u7Hw4µô<ÕYnÓb–sxD_A(+qä­x·øÏDö½uVu—“cHà7Qa4›xœ]¸§?cWAc—Ëy·f…ø‰£nw{|A|V9}ä_~&—eÔ—{4Yuµ-Æê7Ïn7´[Wğù·åo¨ç.GowL˜2–Wdb(cí³ÒŸ6ŸW}|67M*Ç¥­ƒfK}˜?~¸C€\nÜºØJ·ñ‘e¶3€×ÿy1\\ıQM‘m£kr,ô³Sò4nT3Š—kŠ¨·•pĞÙqïi„ÇaTÒWww;rén¹2i‰í‹Š4÷lƒóò5#h#'»hòC‹‰:Í—Xù Ää6”†ø¼\$£nÄn€³w¼Ù‹VÕÍ¹‹~Îò M\$j6\r€WgÖ¦î5^/û,£à\n ¨ÀZ€œ¬’ãn_ñk¶¡LÌ/[­Ü<x‘õ€ÃZªV¯SVS|x§ùO4fÒ•E%ŠnÌ´P3DÈ\$Vv¢ÒfÒ&8šĞÁJûXpİ“)ç—1‰j-O˜ÓjÓ!F-My™˜ç;šK?!ï×‘u½’„JTá›ÄZ¸d-‘¥7V±#dØÀÍ˜`yyŞÒUŠNŒ1†±¶’‚-‘’ğrk<ï(?‡îeôÆ-‰€(›æQXS@oq—¼ÈdKWk©ÃõŸ×à7Éù¡ô½c—:(h:_ãÄ•~š!›øég¡d¶#\"T¢Z]7¯TL’#JB¹gçXd“&3…7Ñyo—uw–;bâÓ8Ék§9;W®0Œƒ¬I	:“òõÈÿCb)è €sùWz¸‰H¸<[òån¹ÿ/r¶<¸PâÙB—j˜CR<Òzaò,‚7‘æ¥vecÈ‰«ü×ğÖ…úÊrÔùÚk~1Í(‘úö3Ï›•uç\n£Vû/2L\ng¬4nû2X|˜O¡z¥\$¦\\’‘›X&ã‹Ci‘VŞ€"; break;
		case "hu": $compressed = "%ÌÂk\rBs7™S‘ŒN2›DC©ß3M‡FÚ6e7Dšj‹D!„ği‚¨M†“œ–Nl‚ªNFS €K5!J¥’e @nˆˆ\rŒ5IĞÊz4šåB\0PÀb2£a¸àr\n#F™èİ¥¬äQÅiÀês'£¡¾jbRà¢I¸Ç;²gÇ:ÚŠl’Æ£‘èâ¦jlÁŒ&è™¦7’•C¦Iá¦i¿McŠ°Ã*)¡†³-”éqÖ˜k£–C2™ÍQ©\rZt4O©hÉ97eE“yÔAc;`ÆñÀäi;e·:ØŸPêp2iÑ3DÒ&aÒ™eDÙ6áì7{„É­—W±ùæÃÉÓÄƒcø>O£æ]\rO@¼,­›j)Œ.ÈÜ3B¢:9)lr<£C°\$,‹2Ğ\n£pÖéì¢9\rã’T\"<OC\"Îã¦nøá§£‚È9ñhÈ•D*Î™A)P=,@5*päİµÏ èÂ¨Š42§*\nÔ Ãh\" È¢%\r##‡/¶E³£\n.R;®¸	A†YO#Œ¨ºxëÀBÂ:H2 ¸5(c¢Ú¬#bÇ#NÅ˜%#Pm%A<T)CC¶ÈàúB(ÛµƒĞôS\nVã´dh7ÆÃ|qG‰Æ1µ£›@3¤q¤b1Fc¼¢49©êIr„âH4'c0z\r è8aĞ^öè\\0ÕŠD]Œáz@ÙPä2á~\r±kĞ?CÚñ\r#xÜã|•\nƒäÿ\rˆ¼¾:C¨Ö6,Ò4È\$î‹móG\0¡êcT ¥)‹@†M\rHš˜§\r€P¯\$0šàŒZN:9†d¢æ™µ455âzË‚«|·=íËv3-”4 «­î:*+zº0Êà\"£0Â:£a\$6£(%»DC‹¬¬L13¸Ö•Œã:v3ÎÈ>ÆÍb°Õ49X™H§ØB@ÎiK€ÇŠ–Ã[X7Mc\\Z¥”-|Á77.zì²Àf)cª§›HØÄ?ÏH@9Œlœ¾(‰®ÉğàPÚÜ¹2\\öÕtÍóc¸ûËêeï‰cË¶@ƒÀBŸ|’KiİˆÙx­	Û¾¾8(:îÌ23#­ã·ŠZk@7íûüY<®»xŠ9¢*c’¾\r±Şº®(ñ÷5ø{KädşPßæc „ïEJ÷I@;6dªİ‰ù-äõg.òvE‘”2dŒ9 ÂWâş%P49ƒà†¾ƒ0iáÔÁ|“ƒEäĞôœ‹N(n…¦\nÃCÁN\rÊé}@	¡şDlÚœÃœÀŒoÁ˜™”‚TÙò \n¼ê/s’R9Vêä35Â”ŞÄ;YŒ <’0ÎaL)fÁ´Ğ4ĞÊ\n˜)5E¼\$ó,Sù`UëÒ\n\n6.%˜áêiUË8ç­¦µVºÙ[kt;­õÂCrä\\Á¸‘hdÔÒíÒy|!8LzÛPk.¼¤Ó ˆ3Y†ìÈJ©ËÂ\rPXõ–D¤ê–JA]gù†’vÖjÏ‘ËQk-…´·òà²bM.xq¡äœ]Ğt\$¾Ó!+¤&IÎ¼à“ºKM` J'T†EcÚaCl©•d€TO=pW+ä7\$Ò@ÌÙL?…<è¤ò4b%©!=HTà«i@eŸAf…‡%êëÙˆaÒş-+…“hD`…+àC€]x /ælö†Â!ğN4;%\$¨]26ææ2‘¢˜ßÄ4kÆ3—U¬€H\nÔ†Ssä\n	úM}´•AğÜRPEÔê½Sµ@‹ô&§™’7>Ã¹›%CD‡(\n*NK(\$A¾öf§Q5\$Í+‰Ôeó\rÅ¹£ù„ÌC¹åa ‚B%«Dé5*ko¢µÖÚß\\Má”aL)bF—ÓÂwbÌ\0–0ÅàlŒÄÙË²HU;šC…¥rVKIy1&dÕ×‡£†ƒE\nñ'´øéš¡f.½^ùìOÔ*/„<œÔhšJ:²tnòbv[s'¡™)«C•&2‚¾‹ºMQõ\0`ÌÄ€Ö¯B€O\naRº BˆqG´9Ñ•N¸Q9ìf&ÔÜ¥ªW°\$¼‚–îÈ=¶ˆÖHÊö\n¸èb„@€)º‚Jvšõ.*T³&ÌIƒôWòİºİ&áœ P5µÇ[OÁÑ=À*òF*îÌs6¦-L”™ğzh2¦êÉÌ¿)X\$9põ”ƒlmÍä‡EJpÜ²¦ ËŞ(Ò2†aûY¸Š„`ì~Şp­•¹õÛ3^z›&…	A©¥qmPoFaçhcÍ 58yªc@èw’^^m?aæU'À‚±h.¦ÈY¶ñ† \\ı´V@*¨² î‰?Y•\$(kà® p::Ø°'<Â+HÈ\$ò)cJAŸ	slP}öÆä¾o*É›Î„X1CğŞ¸ÙÕl„¸:BvHšÄ5ùîËçéjèŞ<½JT¸™š¦bvC(wc\"¥´& €‹éÁ:áÒ?^RøŒàxÛÿ€¼tÅĞFEª¥»:€Ø¢m'W<z)£ËO!‘Ş3‚ÃYpf\0ñØ°ËÈ©×n\$3”Â*zxÖkã7|Ä£¶CyCç`ñ8çøØÉ„ô5ü){&W´ó‹bD)´æ—™›4˜T\n!„€Ax9±Ü‰eY‡éZ¹ÛyÚŸ¸Cè^v¬f ¼ª3b™«1íıÅÑ¢Üâû)O@ÊèÖ·Ğe+{rÓ…%™Û¡…?49kà;!:ìÆ9ÑÇNı\\üÊğÓÃîåâI×‹'‰_Çù×Ş¼§|o`¦x\"ÇáC‡ó÷§ÅFJ©Õ4ó>\r28,İT4\$«·yc¸d;Ç’õûPŠŸ]æáù0h°;—\0ŒMÌNu1‡Ñ±(œoN)\ná”1o¾aŠ1?,b.­cşóB@ip&·bWpİ	“)À!†û¬ÿäÙ?ÎhAl}êàs¤-\0¦¬A†Å’5Cê5@ä5„ª1ÆÄ%ƒeM\0¢:\rƒ¢0°2#BJ×Æ-íX4FOD6ChÑ0ØÆ\"¹MÆLŞî.Æ¸¶Ğ_Í.–lñ­ì~æ¦\$<¸Nì#L&#Kzôcæ¸¢˜5†Ş„X/` `F m…ô~ŠTÂ4Êæ\r,ÄÔ°ÅfÚø¶lLö1°¤%eN4Ÿ˜€¢dÔcüKÂúà¢Øh…\r€ÎE¥†LH>l\0Ú‡g\\§ç:¹ˆJ¬„¢ŸàĞ\r&°dÑcOl*<ÄË\r`õÀ‚Àí\nz” ËĞq)ÇsR€íxÈš'qT¶Ñlwñ^G1b=€Î\r|CÇº«OïZÏ:ñhô/lñ èR¹‘•bóÎëã¯/Iƒ)Ñ‹¯g	ñºñ‹lâƒ¶ÇŒ}ÍULvIñÛñ.+01ñQd°ád	âjjìÊ@ŞGdNÿ‹>´0P-¢Ş6r2€ÖY‚I R1hÀ¯ğæ#ëï\"„8)/ Ez<£\$ÌÇöVEŒPhe\$pR,ÀÒ0CXsñã1éq £F~í@ÔG÷\0L gòéÎdw‚X,íè'‘í#)†6Ñ\r©’¥)Ğz#°’¦Òí2ÒeNşƒ§±ßÒÂªlû)ñc’Å-2³ël|çÒ®Ëìò@Rå,qO-0gx	b’‚ùÆÄ	°ä\$`##vp¬”şã†B ¶æi\"Y2qªâ1î0³02’ß\0Œ•0ËÒîÎ‰`.·“54³BmpÓ/f/§ù/\0Ù4óS6õ6m@ÈÇÈ§ãè\$J\rÄ¾E§öAS*5ˆ|“q2µ“’Á35*†|s x-~w‡Å8©]¾c-\"İ*±3;æ‰9g–Ñ“ÈjRìÃ¼hf¥+Òüy®<äëîQê&®`É\n¢Hqãóê,“î‚B>˜ê«?ÃŸ@ğ®jV<NÁ		~äÎQTåò©s,\"t&UÑ:T\$åÅ]3ël\n”2½3×\0tECËÓ>tâ„×/,ú¶ìŞQäD&DïB±áF”Rhø7-o³FN™F´@H‚È83BBj#d8p³wä#¨f#ô?:•ÀÊÂĞJ4îéJ­8iâ•K/óKofÔ½JôÃKC~Ñ€–-#¦Ëâ;ïè2†`Î4Ô5æm#ö±ÑL¦cOQÌôQ¥O0SOq¡O±a\0ØkÙ‹î_EzkæÂœåã\0ÌŒ¤¢%@ŒÅ	â¼K … ª\n€Œ p†)&ø‘ÊÆ\$x-0îŞGÛU¢eUñVGéUÑ2\"\$\"‚,\"M &FÎ2bD\$’~r^	¢\r5.@¤§ÁC üĞ(.„>ébĞ£'GUª>RTç³ãSËœ2“ƒ\n3I=‚ybT	®¹eäWÈ…]…ÀW`?cÂe&š?f*RPNQ,˜òÆc†šıæ1p„ÍâÊFN9MsÇ\0L£‚ qB¡gìrîL¢b!=a¦!\0B¨f&£ 0T2’jØİËçc¡Cbï)*âœmó\0ïÄ\rXÖe¥€í5”.£j	±5ğö‡-ç[v:m>\nÀÂ`êèàP¥âæD`ŠsâA1ôj¢#IÀ”B&,é*`O%amÆ+bD\rc\"£­e–Íb4šâÏì\råí÷\r8)ÀÛIÑ„\\\\DÔ.ÑfÖ¼@Ú\r "; break;
		case "id": $compressed = "%ÌÂ˜(¨i2MbIÀÂtL¦ã9Ö(g0š#)ÈÖa9‹D#)ÌÂrÇcç1äÃ†M'£Iº>na&ÈÈ€Js!H¤‘é\0€é…Na2)Àb2£a¸àr\n%DÍ2Ã„LÏ7ADt&[\nšÁD“qŒäegÒQB½¯Æeòš\$°Èi6ÃÍ3y€ØiƒR!s£\rÃ6HŠqj<PS­šN|L'f1Iër\"É¼ê 4N×#q¼@p9NÆ“a”Ï%£k§IÒät4VñÆ-®K7eø÷¸Lâxn5b#qç)53ò¼ˆeìç™ÍŞã›_K«b)ê»\0¢A„àu‚¯R`Q-\n—‚Š³miŞpCxä‘ˆ{¸¡ƒ{pÖ¢›v8@H‚9c¼2\$Ohä÷ğœ\$Éâ4“8Ê5mğÜï®jT¢ÁÈJ4Æ\$K Î Ézò6;©Àî…D¬¸èØ²ÂjÑ+\r(üˆã(Ş‰HlC1Œ£k.µc’ê¯CÌº^¿¤i„Ş¬Bj@:`TÍ!jèäÀÈšÆ2\r©ª;Ác|Â)ÈÆ1¤®ú`ÆÌæ¹ãK€Ó\"l'\nÂá`@1£BŞ3¡Ğ:ƒ€æáxïQ…ÉÔë8ÀpÎ¢a}C ^'áğÛ.I¨Í0*8Ş7xÂ\$Bƒh4£S &Yz	!Í,´*´(ĞèÇŒ£cõj±ÎÛhüˆ£t¢¤ã¨+¤ãs°‚„£\"7E,BXŞ6IêÂ´‚ŞN¥¯--ƒZß}LA\0:Iì\n\"£0Â:ˆ€ì0ƒ¨ÊºØã@#/úÆ3­èÆ4!Éú\"—ÑÈ³ô9:Lã¨:åKåk ¢‚^5GJ4N‚ ë,?ŠgCK‡l„\rª(Ü‰k£B˜¢&CĞ±;îÛój'´4Ç[ièæ5Ê«„:ÙÚŞºËëŒ\rÈï¯ÚÀ Ø¶m­\nN’jaxè­[’\$»pñBÖ¿gZì†è!l›áŠIú\"ø\"¥k»e‰Âö“ÃcÍƒa¤\\æuøÌ4¢«6.akîŠÛÚõ0@‰õë—ÙqÃ(ñ;õúéŒ×Ø†ËM+N“®CxÌ30óŠD%Û;´*\rítà<„´?Ğ#6 ã:j9ÒR°0£_Wv¢)«¤2…˜SMK¿k˜ŠN‘\nJlKÙ(>`èƒ’’nø„'u(jº™SjuOªFÕ,U\0¹U*ÀÜÃ\"¿0JıYƒèBXL¨gtæH’³ãRŠÚ’~è}‘¦Æ‚úI\"Mfø”*ô.ìÃÃ‹-èaI©X\"¦”âT\n‰R*h’«U®éŞ˜H­û|Õ}:wR”@o]Ì‘µÇT„ˆêp\"aÑ&‚ğw¡i¨\\¤± ÌÿAr!_#ãhá¯#°‰.àÂğS»ÛPQï#Åèø£ñÀ(Ñä‚\$pe‘¬4±èQDU(Æ(¥)@•[>\n (r`_Ã\$n&\0 Ÿ‚™Fg¥)\"t!¹8ÇÈ	\r²6†ÚMô\"K'!±zy4QA¸(òà“ˆğGUÌt „9@:¢ppÈn=èH)b¹Ádi:†u6Jd™rl-C¿ÀŞ×	ÀC\naH#Kr“5Ap 	a¤Ø†×²sÃ8 Œ2‚ êŒše†\$:†ÔDAE0( ÀQY]BKù3&¦!¤8XL7u\$‡“LLWtÊPå–Iœ\"ŞX’)¡Hƒ@eNç8kÑCÓR¸ú*@À€(ğ¦°r'ŒD”’d@™b:gÉœ­sN›ˆåV&%¼5”rİ¤«(°ŸšJ_<ÉÁ»^„èœ®úÒ‚e\n˜`€Ù˜ĞŒ%j_uMô9NÒyKuç‘`ÆÏ“–­ñğœ¨P*Pe\0D¡0\"ÙĞ¤±²kÖÔ6b†Ò.¬ÍYõ<b¤–[Šß\"#ÏiÃ	ƒ Ì±“ÓŞ|SiZóø…ĞÂÚíË<oØÚSqÖ+lí]k’6ÀDµÊ,O}šô¡È(V4Kam5®‰oI­Š}ÔÉ´–å	E’®V“ôÔ™îm}4®F\n!É^VRÜ^Ã”˜›¡;o½+›Xù&aî>,7“©‹Ã¬ ¡ÎLß¨ÂİÂ›e1³N‘˜tCÈì0øAw0ÊÑÊŞ9èåÃÈòlC¤\0§…¬²³v²F‰¬›E^Š4½UššÕÇ+pò–Û  ÈC!%„2ßŒ\$Æ[M%Ù:†5ÍnCjÜ+¹•scìbo—pb%Õ\r–y “y/NGµû±uxCÛ£Xpq ‹ŸrÆ Aa SŒbn@ ¦IòÂÌóêÄq;’À€òã»x ÓëØ¯†\$lKåÔ\rŠIöKTJêKeXÎ”“	«H{+‰4hsÌ&£Óº¢é¦\$x_õù/Ø!+Lcò³©@!Ü‚Ÿ²òF+Ğ:¡C‡\$²HÑy,Û©ftQ,\r³6âÜ'²NR Ô`Ìi'ÜöuºâÃe*ŞZ\0)”~K›¹C;á Î5Å8ml\ráqlB;ª®¶“ü5ŸlWîLkå‡<1(¸î-uKûh3[Ü™\rS±¸ØÇìpÒV«ü½œŸ‹—öœCÉ­Åx8‰(+N·ø¨aGçä\" à\\ËS2°¦dˆ‘;ˆİúQGc\\è×’~¤bw@È™(–rÒcÌ1rö]D°¶uÂÆ:QA¡+:\"QX\n9ĞËs .Ëü}¹÷pÆõâÿuÏB¹7G¾ğşP_ÈÇÒV¤óø+¡W;ğiæñ³½Ş.iÈ91q-›ÈøéŠZï—åŞLÉb¯E×üÓ2\"ë|ä·*Í¨¹9|ÚjÌâh‰d<Ö8 :ë@—c²¼ó¼JÆåbs–=9ìûà|…á¹¯’»ï=yØ”šS¹Í’¹@œ–PK’u0á­Ÿ¥ïá(ü•04ıb@IËßŞ>7ñ6}’Yº=ı èÒ¯ä‚–O~øË\";.JhLûŒÊbzµd¬Í\rğİGêğï2ì0 õ’Y®UãŸ/H»Ç’Ğ\"9&ò\"p2óÂE¢pò/—&õÏ¦ÈçfoP@\"\0Ë€ša‹‚Â¢K`¦¢=ÆŒi\r”;ÀÂl%³£õÇVønU	Vú1¦ànP£o£æ¨#G¼\"†5kÌ\rå–'»êc#ĞpÃfXìC„÷dª4#è‰°¼¤åĞPnëqFí0©	6nĞj!ë\rÎ»îRğ1)Ììq\"¶îìÄQÀÈèÂºC,¬¹SÆ ‡,ËQ@!gíj„ËìàF¦Né×\$¶ÍNø‘j\r,ÍRJ±t4ï#LÕÌÍ¬×åÎHÍD¶Ğ¢ÃîT.Ñœğ°±\nq{qC\"@ ÈPª2­À6\$r<F6Ä7:İ¢^\$@Kæv}†Zå´	Ô9'ØmP÷B¼\\­hd†\r€V¨qt!e~®¦b#~zÈÄÇ°QBDÊöáJt'V\n ¨ÀZf-²\"‚DÕ¯q#@QØÀdxü+´p¥²MRÌæ\nE²L@AÎ2Ãì,*T6HÎòh…\"ö2z:‚*[…Ô'î]BD	Œ:]Ån-ïÃ‚t=cÚ'„àHx!C‚KP~b-ghåiBş­\0æWäorÁ'gN	-,1ªåÄB&®däĞ’ß Ş0-ó-ÎfûE®ÇCòd\rP«4 ÒT[@š…£’õNè¡\$\n»³ f®ÊÈÓ&1¢HPÄÂÔ ½LÌ¢«m/¦ 3‚ì<ƒè,† 3&b#£BşĞRÍ4Ì’#*TŒËÂı1ók-£’='š@ŞK îÈ&Ù€ÈGå´fBÌGêÊ4èxED‚5 "; break;
		case "it": $compressed = "%ÌÂ˜(†a9Lfi”Üt7ˆ†S`€Ìi6Dãy¸A	:œÌf˜€¸L0Ä0ÓqÌÓL'9tÊ%‹F#L5@€Js!I‰1X¼f7eÇ3¡–M&FC1 Ôl7AECIÀÓ7‹›¤•ó!„Øli°ó((§\n:œãğQ\$Üc9fq©üš	…Ë\"š1Òs0˜£C”o„™Í&ë5´:bb‘™14ß†‹Âî²Ó,&Di©G3®R>i3˜dÙxñ Ã_¯œ!'iÖH@pÒˆ&|–C)yN´¬Èƒ2bÍì­Öc±‡¦lêÒD8éÓ&uëú’˜ÖLç¥ÃÈÄŞ°érëõs<Ix(Šl•äúÄÌ™ŸÀ\n¬Cì9.NBDí:Ô7¨HªÊç¯j:<Öƒ æ	˜æ;²\"M\0¥-jR‰¬èÓ˜‡%Èê¾¾®ª\"t¤©jh@Éef:¡¨H\"1¯ @È</{ú4-¢õ\nC*šã(Ş†ÈO¦¦KsJ;4°×%ŠÔ89‘Üz1/`P‚µ!Îò=%ãrÚ&\rì’<àI”Ü¶LÃ ÚÉ¸(ä&:Â°ºR0ŒcVˆÄsÎ‚!ÃÒS¦’øÈâR4/#0z\r è8aĞ^õ(\\0ÏSàä28^’ô„7…á~+Æt2c3 ™M‘PxŒ!òL+0ïğ=QÈ“ÔY\rbÌ4Ù‰J˜9-C¢âë3Í”¹îˆn-\nRŞ)°œ:ã²\"Š¬ß3SÔlÚ¶„£ @1*h|ßwê”ƒ\rãb:Ê+jíæ!É÷”Í×‹ºó‡\nË3 7C*&°¤—˜§xX8@ŒÖä-@‚â/-B¶ˆm+Evµ®RÚ'ÌĞÃ\$Hr'<?“Ü–&5£<‰j¹Îeæ ås3)eÙ¬šš9\\ÒÎëa“°ÈˆŠbˆ˜-ÓPÄ;ã?rÛöt óÜ{Ì‚r\$ª!¯6¶Ûx¼;šm±3( İ·­@¤7­Sä­™\rÔsí,!í@’6ÂÃ“2û|o¸/Nv¤6:Â „·nÎ³ÎŒä.²~\r©éJŒ5ºómHğÃ×@ƒóaØ©5-[áƒr.ÿ/= |Ÿx€“Œ;Å¹½Å?O‚7Â«ŠùàÄélÚ4¤Ïâ˜¶½Ã”¬²=8=“­:¶¹Bªü·g·˜ˆÍlS×b\\•4åJ~xËcú!-ÕÚB)`	ì7 °\\B£è+Í‡¾ÃpîŸ‚ÚA/ú§&ş\rË)De}ş—#ªr™>_PÀ¡óNsC(fÉ)m#Ó¶R\rb…|elÛàÖğUğr=d¸7p@UJnJPî=b'”° S¥M©Õ>¨U¥ê šUX•qí\\Êâ:­Aô\\Yğõßf>M»lU¨0©@ÂšIOÌÃµØ»ÊKÈtß’îWL¨ b¦5HØDJS15O*D©2¨€‘YVªğèÃ(x-h¤7+UnâÃ0Pß<^C]\"J3äE:öti\\Á¿4©Ù4”âNF<hEDİwÄ`yÎCÇy¦6[ƒ„ÊÁX@ƒP£\0ÊŒazDö?hBuOÃW…äx£=²hF‹Ã^ËMò=·­.Óô})I¤Ô¢hˆ‰º%Fmë‚\0 PJb\0POÁI*r(©æLPƒ6ÓtºMÇß¡v0ßÉ’Ÿ³¦AË\$êjÎ“”gi±i¡)’´ÈœåJdhÖ‘\"V	:&ı\rõ\$ÄfÑnt4÷†PÄt%ù¥#RÄ…’¤xk!À€!…0¤¨ôØ\r-^D\$“&o	L±0 4œÊŠù+Ò (ÌWtÁG	¦\0<4­G´ª\rm?RSTêªé9ÍAŸ¨\$áĞp]•fªXNãÒrªïB ò¿T'°O\naR¦Ô²ã1_Qœ‰baYŞ;1Ö\"»µ·2`P¯gl„-ó{;P\rÄLÂ¦²+™\$„h#IòÆ•ãŒ\r.8„0eœEfÊ/ÕR°ôNëKiÜjm\$'„à@B€D!P\"€«š E	ê”Ÿ8+]W~–WD”|=hn'‡J™£A+å˜÷±ä-\\an¾ç4ÉP‰\\w5ñ³‘©ÓRªbbÀ¥\$ü@EŸ™mQ8ƒ7ÌŞ[A÷!Çr>†õ\r\0S!3Ô32D1	¥“LJkõq\\öŸ‡¥µ¾`Îá\rÅ¤şøWZÕ+˜i!QË–ÜˆÏÁÖ>æ% %gA„±A÷?,!8*‹q(Cªr ¤ˆ¼¢€ĞÃ£œmÄ§ÒˆR™)&M<4­5ªãÂ³5è53F‘Ht‡È6š3HÛJŞ!lG?âU[-n±Š…¥whÈh™=ı‡¥~†S`Gªy É1Ã|*^‘“sÓ¯œØt³¤CMò®z•,—lñl×Òü\$æL5Ñih0¬=Æ5y„e¯0Q¨rˆ…-–Û¦C	\$å’•GƒšŸJŞ×È½*j{0Òé¦\0Á\0\n`D0¯õôdR#¡Œ:Ì„I,Yr €'„µ)¸Í)iÔèš#Øùw^í;¼”ïç½w¾ç6	ˆ´ú1µI«=˜å«Ë3¦y\$Ìƒq€ëL¡€u†DÑnm¹¼¸õ³›së‘íË3øş³®¦½¡2ËJ`SI(¥µ\\®–B½6¸5yãÎ4-in\"Â}°^[J×Z×…Ÿœq)0ÎÑ‡Ì¹ZÚSËÏ\r/,”õãä”\\äáCÚO·k!á½ÑcĞc³¥+\" Ì’nó¶ÃkjÆôıË’™Ã¿®ô¨\\9ÛÀõ¢ÚÅX1	LlÉ–78BÉÈ'åÑ.¸å©„¨°\nó§z÷™yP†|óIòAÅZ²Wü‚Áì%hB±ƒ`gõĞ4gÖÁ-_õU«âYa0Ñx7`¬0‰°iú1Øçšò9ƒ¾šcB>ìà¿c£û€Ss½ŸìfÅ˜ÎÛq1ßº:OBÌpC¸‰U¿¸Ûsnk°‹šo¢Ø/-úï ş£¼şïæG®æßp\0001 ×ÂòÒà@Ó/ÈúC¥\r,í0­æûåÌï %¸:@˜¼\rÊÚ(°–£´\"b˜?MÜ©z]é¾9\rŞ¤ÂšäƒœaC´=B&K\nr´â<2lş—\0êÛk”¼Gé\n¥Ó	ÈYé\\…bòp¬Ú;ÂMP5p8+o\nGíSÍab1C¬Õ\rU	pT:Å¢9°<ğeÍ0*‹£¥\rïıJ2ÂÅĞqàŠqïoÊ|‘\0\"0âşÏîØûŒÎìËrÓãÉ£Fóízp@ŸFL\"¦’iÔ¸(\0¿ç\0¯ ïƒ¥\0„øıÏePşğô3¯Ş% Ìd…îÑÆÖÅÆ¡B-‚a‘pcE¶¿ä”.\"<€ò89g\n<£Ì p…›±£…›ïÍ<ñ¤jŒ-‘¬:Â¸ö¯(,C,¢Î--‹®án,…/+âÌ-àÔ/Õ±c±ÉÌâÑçMØqõæ9QÓ¦XÓj6Ç‚:\n†ˆcK†İr&eöª†¾ŠtCÍ7\"ˆ•\"Ò#Ï¹\"DÌ=Ã‚Ô@ÖÔƒˆ81úLÎ-X‡Q°ï\"ÔÈu‘`öMW%g½ D…'‹¬jì‘(\n±×%¨ºÌòÒg)Q×'\0	fH„Ã¢Ú«ª/‘ˆ]ÃD`ƒ.NÆ€ò¼.L>~rÂÌ´ ĞRtEô„\" 3\"24fÏ‚¼E£^×LN\"(ŠF(ÿ…à^H(ró	2ö›rüxF0)Ùcn¨ƒŞ\r€VcğÓ®š=‰°Û)[\0ä`Äµ©x#JÀ§H_'@ª\n€Œ pãnbVŸßj·3`–Æ>Éâ`)bñib\"@„æÄöQˆÈG®Q°@W‚*?mOÀ¢Óœ\\ÃfB˜\"32ãBæå\r%t²3²0\$º°@0ÂV:Âìiâ\"4ÓØNrÌê›­¤]\$d`ì]ŒN˜¢57‰q>sò ‹ö˜‡³?lŠ “üŸ£¸+Ô–óà3sú—Óé¯±?TM£Bÿ-CòÍélïâŒ#Púe†\\Ì”B²æ3Ï\$æ‚½l&^kÒæ¦‰ÏnØ'ê\r# IØÇ\"·c¬	ğÈOow+êÎÀ‚ie¨pÌ\0õÃ>ìÀ<4xKè.L.L†Ğ\nt`”M¢Æ Ë5\0 Í¢Ä\\ÄÌh§•\09ç^b8"; break;
		case "ja": $compressed = "%ÌÂ:\$\nq Ò®4†¤„ªá‰(bŠƒ„¥á*ØJò‰q Tòl…}!MÃn4æN ªI*ADq\$Ö]HUâ)Ì„ ÈĞ)™dº†Ïçt'*µ0åN*\$1¤)AJå ¡`(`1ÆƒQ°Üp9„ ÔÑØbÚ:åW&œëåK•<‹^î…\n2·&Ó(·zñ>\n\$›Œg#)„æe¢Å×„âu@«¢±xÌnèƒ Qt\"…ÊŠ\\Òq4Ü\nqCiÒÑ‚†\"±ùVÑÎ·T:Shiz1~åB©AXMöû€‚áWe[†W¡„îPqäî¦‚I9“kG2Ya³A\"ÜÊ…K¥2ŞÈzıšõ‚Ä—…ù:ª“\0TªÌ9Så±3“P41¤yĞ_—­yA	AÄ¹\$#†L…Ñ+D‘O±Hé•ĞUĞ1z_œä¡QiÌLÉ	T†+DRº\$M›ºAë¡_¡*cÆ†6-RHÊI^Óµ%YÊW—Ç)~NCéDí8‹h¢©B“Hc|E¤%qÌEµÅájs,^¢g\$f”i@G¤%B…Ír;:©4aÌK¢Ä´«+\nÑ®+ÊbÆs¥‚ªK¥ä¹Js)sÚCÓç)P!/Âr„¸DT¸¤¤0A‘´”W¯Ì\\!‘ù‡WIU^®2LŸ%É²Qrs•qÒPÆ(b}'oÃÆ¸®vZìñœ¤¸Ê„bAÊöÁdÉRa'#×2âO}^A–iy`\\B951¢Dé{'WÈ‚2\r£HÜ2SMÓrÜ÷KÈÔ´D5Ém›eÊ6BsÅ, ´ä¹/L½18oòšƒ@42ƒ0z\r è8aĞ^ú\\0áV\rãÎŒ£p^82Ã˜ï¤ŒxD¢‡ÇARYœå¡`ã|İ–Y~„\\Èb9lZ¤Äò<Í\\tjñ6½	™õ‹ŠVA”²Q%½o‰{ÜtÔ!V!…œ°‰œÎs éiLr’\$PŒŠE²©F!pİ‘Æ¡6'\\GI\0Qœä­gG”[¹Ê@EgI\\ÄTbt’Å8Xu®^ºBx:—”„æºQ@ÅÙvs„~Ñ>[ìF%™UŞ?\rT¤¿‡>—@¡ŒÊE’³ñ8Ü7MæòB«[IµĞ7ÀÂˆLuO—VºÈIæHb¹ˆ\"ì¤Œ ¨â]!ÄÙWŠî^	TĞ·Fì^H›„=m Ø¿³N'¡}J°™¶!t2\"Ü+mg‘Ÿ3ê}Ë‹<±ÿÁn’…ŒJ\r \$çVèJ“«¦\$'ò•ÛHƒ‰oeú-†âŸEèœÂ H\0 ˆ¡#j„îµİ»Ôbš\n1k¤BˆÕTFB†%°6\$¼!\\CìJFüà¢4Î sM\"¸¢RDjmeº·xê“P‚Cˆ‹h=°×H|‹%‚9§TîGB{3)¤¯	ò…*Ü€œ&½(š¸ Ô*‡e‰ÅB rm…¤(’9ÄP¶ÂP”¥4S˜^ÄZ˜‘4W¬IN\$ÌX©ÇÌ§Mé3M¥,£ÔÉ3ÜâªÃ”_ä´!„kZÇRHÉ9+%Í¡¦›\"\nj-ÕrÊ“!\\•Åƒ°–“‰!Š¨T£U,~ÏÈRÆä@Ç1nÓ]\$4A„¶! »CÂW³fÍY»9glõŸ´îĞÚ-i\r)¦4àÈÃpa¦œ5i  Nù82=²Ávi?dÕÇ1ñ8'Ï°UI\$Ab[>˜\$BRãœõÏYs%¥ÜĞ€¯éw1tºé0ªŞ©¦é>Å£í4r“3@ÊÍ™Ã:gŒù 4&‰Aš;Iim5¦€èÓC<iÍ^EÑá\\`Å…‚øE‚™A\rmjª5¼¦ŞPÓCÉ|òm2ÉÏ>DGVĞø„ÔÉc,äò’«ÒR](’\\¸MòŸ„0-ØRG0­P4Å09µ9,õ~C˜R¯Gcç¥\0S&ÒRTìÒ|tA2à@PN7VëÊ„öcDAE1¦¤ÃZ“H±mİ«ep¨ä@˜ÏéÜ%ÃœD‹˜lBb™äŸìy92GI ±¢=+Ö\nªÊèÍùLÅŒr#›ƒpÖÆ^õ6gG&kÉN7Ò¸AÖeĞãÔç\"ès‰›´#	 \$ø=²Ë’ÅI€Q/c'Ç(›’©ÀAU0‘ÿGêá#à†ÂFšÊî­aÚ®M„¤<Š¼Ï\\ P	 âÚVl`KÉ{/¸2Tİ¡TL	‘4)ä1şFŞçqÇf•êSdÓƒQÁ1ËrC=­¬˜%Ô£P‰D ˜¢Æ!‘Aü®yşR˜¸„¢€Z.ÿƒNù¢iµe¾¡\$ğ\rM#Ì“Ş°İğÂ¡	YPfpç!Ig œ×O+ë2“	é?E¼‹	&¢\$”\".UÁæïu¶¸’zIUùğXÉ™J×ª\0@,°gå\$”a*Î-MX”ˆ)ø«r54kFcZÆœühòA†ğL¶z³3“1,:Dù†m¡Õ¸Å—Rª`\n	á8P T¼+†@Š/tD%Ò\nÂ¸ÒŸu+·ºÑ+ä²,ƒèF!¤pæ°©€E[û%át}¬˜ÒïYÆÊr\$}|³9Ó„(¡ ¬ıFçËlÌmxÂ]O]öûÑ0c_ôÈV\$®\\\\.;½zTuÍ…Qp¦æRg‡‰2&TÌsaÍV¤¥Rà1EQ‚À/\$§»&eyÍ.äQKŒÄ!<ŒT’t ^<\"EVàD[A>UûÀÏè\"8®TéŞ;ì*+Q:)(­ŠÑĞ/…àæ\næùò)öÁc‚]+ò8ï\rÜ/“¡“’Ó›¿¨fB`*Eçµˆğé+ãÈ†Ã#ä%¿¸·ó<9ñĞ'È Ñ29„IÏ6Ñ!t¯ÏÜ_âÕû.¯\n^ºÎm¹x‹+ŞèFşà	ÁrØ`‚H¡%¾Äÿ/öÿ¡|!hDHâC\\Êed!./X—¯\"oìCc\"\r\r+s Ä#atÙğüod0!ác§j‡¬üˆ2[„”.ÃĞë(ºâ ¨/ò‘\r(ÒD¤„¶K¤¾’+ğ;l&)ÏÌ‰Ğx#ÃÁ		Œ[g8\0^6s£¨mw%â¹ã0®ŒAJ9	éèo.Êoº \n8Í¶7Í”€h\nücP¢+¸É\$Îs­&H’„+¡‹¥Ì=!:aB‚H(:‹Fppª&Ğ\"Ëäë„°ò-è#¬*;-DQeîc8&åãîø0P«Ğ›	A\\Óm:\r>¡‹AZz<ËJ!~‚(&‚¡Ê%F¦úÁlÊÁvG\0NaX8ã’9bâquf&èoD\"÷Î“ÑùcĞoƒÚ=ñšÏé2í‡€q“g\$:0Ë	 mï¨<çèG.HñÈ<­Âni„Äoj»ñ×gëĞiJæé1Ún.KÏlíåòv‡lwÖ‘ÏpÊ1C1ÅÈ4ãrEöîä%±\"r*ñˆzÄ²\$Î‹X÷fä§nEi´ûĞêGqêSø;­ñ%J1B/ñ~ÁhøÂ‚t®7	¤6.NPã\\B\nxÌ¡B¤!lê€ƒrT±Ñ’]Î|ç2&?*KbBÏ²^š~ƒNwh²‹më*qÅ)’ÄèH;&ñ%qé'1í²`„¯~€‘”è²çèd„ğ%a’ø÷ÒıpÉ/l<!x°J+hr4èxöÇ1BB©®.¹0ÒZ8rÒşNŒE—!rõ-îìÿ4(Y(S0L<“Rş“Gs&°é¢B/Ø!Ü÷*GF!)*W ·0\\×åzç†.\\£}Éz?ˆ?îªYszVås8Dt©?&%”ûè8:ÈúÇSYƒT@…*AS–­Á9ÊãM±6ç4Ax0\$¸bX‘şòĞ·“²Ë%füú3G5³jššpR¡!”@'\0pSi4Ó1A0hüHŒÊfó*3]&Šû³¯.BC°İCô3ÓN*T.¾@„tUCÈ°‹N´x¡N0\rÎAÈC˜Á^Ì‚F\"ğQÈ¸‰Ü@è.7…±22ó4IHvˆS9.TDé(t!4™,”e,ôQB+DÏ4Z…OZ›\n”^.ß\"´Âu§ƒ´*4´ÈíÂM*‹n¼RJ›rm\$%âˆ0DéÔ4´»0tÊ!”øöÎt7ÕU\nüòYJ•µQ¥Í?ooOt¤4Ú.Pb.ìÒ/““C4J¤[¥â[óSâú/òş…é+T‰¡TÂëSÂõTÿ?u8[ÕhÍE{B°\0ÿı\0´›*U\",u\0P	\0Á%C”¢•`ÿõXxõZCUZX5•Y‘‰P!A5Iå‹a4§Zõ½ë0_X©¡õÕ@uÏ\\Î±,®´ê¥îÄlx”_D°aWUEP\nå/•sTí¡.5Ñ`õgKRÍZà2Ğ,“Io†Ë7jDÂ¡%ãéc'ãv;pŸá3\ng=‹İ%TsGu]Ğ¬4Å°_-<òâ‰b”Üë®¯e´ye‘¤=#×µ®i‚\r€W°C;ÂENğfääÉ±š™ñ.*1*\"`ª\n€Œ p)4A-äÚ¬ÊÚâ]3KÚ\"’bÀ,§\\SóÏ8ŒÎx×’U¸9‡’@+ö9š!\n€›gpO¶ æË”&KlŠîF‹Kl-ÌÍo‚ K3ÆØÿ1b:aT#‘\\ôğ”ï>.-ILÂ^&4r6¨\$¡ Á<~oŠŠÇíYlV­õŸu¯Â\ngŞQfmÑò'–Ğuhç·wm·}Oôxwz–!\0wBúŠ1y\\*j\$úùî”Ö‘¦y§”ĞH2ñ·'(nüGW;²R7Ån—bÖñ\0QÇ–Â©âÁ{¢®é\\o!6…ÀP\nÀÂ`ê ÚlŒáyËyÇ˜‹A?©ÕuHº@1B/—Ô(+epg¢£\rE,.¬m'wv÷snOwàà‚x3ç#ÔcÎ@H‚NßBo r%P/%HEU«LLa"; break;
		case "ka": $compressed = "%ÌÂ˜)ÂƒRAÒtÄ5B êŠƒ† ÔPt¬2'KÂ¢ª:R>ƒ äÈ5-%A¡(Ä:<ƒPÅSsE,I5AÎâÓdN˜ŠËĞiØ=	  ˆ§2Æi?•ÈcXM­Í\"–)ô–‘ƒÓv‰ÄÄ@\nFC1 Ôl7fT+U	]M²J¬ôHÌæË^¿à©x8´É94¡\$ã“{]&?MÆ3‘”Âs2Ôuiz3`ÂÈìÒÌ*Zƒ¥%\"±xÜ¢o¯­Jiğt”ÒµTAèÈ=D+I?‹« êy¼ı12¶EéQ~\r…ªƒâuúx†.Òue}·2TÕğØ?¦½¯rµİÚö¿¿¤‹¦â¾NÖSšŠ·¯zhÄ¬	ZØÔ¸H:»±Ûë\0'i.ğµo‚.Ä·I“ÄƒÂÎË[2H³öÖ¸¯3§Ğ‚½\0µå[W-o:\rpé\$H<C'ñÂ€Ãor.Ÿ©ĞÔ+Ãé“äê(„‚d•ÂÉ’.×½É\\3ºğâÒÆ)›VòD+ëŒè&œ¼ëJi¤¿01ÚVµnÛŠ“Ì\nÔÔ4ó-¨cø+(ªVÜ³Š@úOPS­-P2D©´Šê.î:Î2Ö¶L\n°Æ-KaBÒ<ˆÊº×ÑÈÂeµäê+¢ªÔÒ Èbd”¥q„™-UL¹CÔÈ:zœEõ“xÕÌLSk9=–~ƒ¶©\\ŠÃĞtÂ½Ï“b‚ÀÔµ­„AÎ¯¬øÔLŠó§#1IúRÊ¦Nsf½rc­75’d¸ví_;­(«ä”Ì7j*è¥ÏQWCšNïÕi£«Z1P# Ú4Ã(åBn©0'M[k.¨‚»\\İ¨=Bæ×‰LÅ_i½!\0ĞÎŒÁèD4ƒ à9‡Ax^;èpÃ‰b˜°\\7C8^2ÁxàÏc¾2á¦1ŠŸxÂ?	<«\$İi;‡_¶®‘ =ØdjÕº)“ø£°‘LÎñÄ‘‘%¤ëäuaÏïşï“o6L.šaïji>VzAÑ-+q+ûƒÎŒ—úhŒãõƒÇ¹¢WNïT»kğ(Õ±.JMkkb¶’ÃíEN’Oİ6g¿ÛİR‹9½oƒ€ÖĞ\n'IÃlM\\®Ò¸o…“ÄöÚ/5|¡' ñw£·Ù>,ceõÍƒqJHu?š@5/°AôÕ²i~ûèÉ.™)U‹ËW¹ıÓ¯ÉûnË(Œ½Aö„¢Ôtêü¡´BûÊñkgY¹¯(ÊÈº6D¥(ç…0¢ÁlE®íí¾÷\"bIúQpoqÃ¿µÂí‘ùF‡‘s·¢~¯Ös‚c)}Â¾øZEÜJÊ‡¶°baâ¨}ç\r¹b¼ß !D‚\r™ÿ—¼­ÏY2/(“¼wŞş‹SÂ0ïiñ Òeˆ:İdùş¿ó‰;pˆİº\"fV bZY,\0ØCe|jÖ{½lp¸©‚\0ÚC˜tÁ«™ĞèCn†Iäƒyk­|˜ÈÀæ‚o\rÁ˜4†y dØ>4@Š\$òo‰º4ò­Å‘T„IÊÒ²Ö]¹h¤¥Â|}Ç›¤àØ“­%ÕÀ˜jP¼³&„¥×ÇTÌ–f„¯+Ë™&“'µ&+1‰X“Šôã©«:³€¯M'P±LIN\$ëeà>Tr·±„ÆªlcW,ÜÂúKiM¤öéÑô&ó¹u,¥Øø]ä4“Ê{\"h½\0ß;”D/Ùˆ4F*Åè\\üUï´ÆaYIÍcƒWÉšuLœ³dÍ³8gLñŸ4\0îĞ¨óFi\r)¦ğÉ(ƒt\r2‰ªµˆvb¢ò#&¾	ÅéS1)7eqB<˜ª`ı`¨_&A>“äÚˆU+—Æ™Õ°2*bVÕ.+Ñ‹ÍDø²&»×4‘’:°GÊ'Ê&HêˆÌÇ~yHÊ;ŸtÙ™†VjÍÙË;g¬ı ´6'GÚ;Iim42‡€èÓKi­ZOÒÃÖZˆÊ~JşT±Ó£áH*u©Ù°9ïVÒÂ!(S9D\$‚¼ı™èQib ¼Û\nMÓñjH\nn(‘j&Ÿ*í\n„³¢¬Á\"‰WÎ‰€¤İZZéàÉWŠ¥D³¹¾/)CËddÕÿF²3^jÔF‘lÅ	*¢í°ºgy½*ñT ¦	ÖZÊ½x'æåN³‘˜Æı>çÈXçy71\0PTÁI¤[«!•E)›<Øi°œ×›Zü'(å~…9„•ã†Ââuq#'‚e[R~|¯uŞ=sÆ¶–Ìdù1Di°)à™/hºQÖ‡NF(MSÊû«R4#/iù\"#V*ìb']=ôaEù28upõİ%TÂ˜RÌc':kVF'İøvîºkò°tXŠÓV¹®JPµÏVÆÜI‹‹Wsn½3Õ·e[pW 7¢…‚´kjZªùğ‹Õ©ÈÍs_Wè£Iğ(£Z´÷+Jı·dlQp}†£9úiÇ¥'LX—bÌc;úÔ¼N•P9\\®BIÌïO1\n<)…Kµ™}&\\ŠÍöê{ñn½ÆyÆ§’ºô%1ˆÎÃ Xm<ï	Y9Ù{7g½Í£y¶ñË{d-‘™_öá¬VÊçÔP(ŞÅ­‘ØŠÜmøù\r®^9:Y„`¨Á¿ÑRÑRV\$¬2–ŸéòwF‰ñ‰\"¬üqÍ¹d/9}ï\r†t\"\rı*XÚé´Á·‰ĞšBİaßN„a[_•Š<b—öUô•¬u·C`4EªèîˆÌAó·N%\"ğ^†ôlm>â+ÆÖ·­Y 8ä¶&s[\$®v‰¡ó0øà£Ó(alFA×•á×4ÇÜcì{L°ê<<õ†Óáäİ19•Ğ×ß\roR‹¡ó-ZÛ¹ûğÆˆJ° 8†}ÁnMÓàâ¦¹<‡ˆ&%WÎìº€cXªò¾>£d	ÌÉ—0¶8ïøw(²4søñË'&qÈ{\\QŠ½]˜¯UĞ{C®d8ÀêØ¡fp2Ú\0ĞÃ§À›|ëhoØ.t\nv8”M*È+…±1ÖùåÁ_p¬ŒĞhbÁ¤:0@ƒo\r±=Uı\"{hÚŠÎ¤ÿèî‡ƒÊ]¸OÅî‚*½èô(VñĞ&G\"TT&ZK¨‰Gü‚\0†ş@Æ\rb\nc:©#Bp@\rbdèÂ€¤‹Âuò&ğ\\mp6¼ï`ş/æ³ç2@òC†·K¸>‚ØkC	ŠÈtê(°&æD®ëÀzâdİã_Ç\0CJÎmâš–ÇkÀßBØD¤€ï°8F  ¨\n€‚`c\rJrQğ4¿Œ¬uHvâhÀÇ‚ä(1DÀ÷dÖ÷ÈètíRø¥d  ^-ê§Ïm^ÔãZUˆV'ê¨ºêÒâ‚oIØ)ñ^D>%Î@çdL–{èG¬LipÛ0Ş…‘,YQH,1hĞÈJ&E‘ÄPŞmºˆÇõæÈ4­nÈünóƒtäBNäˆ”äq>×¨3\n'6NizÑE¡ñ>61°àpÀ°Ìalúp¼èfw…Š¥	¶Q¢Çi1ØßêÿŠ	Ğ´Ï¢~‰…x½‘ÌdüÑ¢¯p%8ë±<üŒSéÖI£H–èXûò°Ï]Âø291ª—²\$ØñncúÁ@î„Ï%@öãü:Ì¤p”2²S/ß%ƒV#,²îå”…¬eŒötæâl±:^KF@(Lµ§¼¤Îoq²©éØ¹CD3\n\"9døRMÿ#r¨tâºåÒ®ÖÒ²-Bºğ«}'n„íGPè¯hñrZ›^¶ïú5oşåÃI\nÏü0òÄ®GFŒÀ‚\r€Îiè\rö”/œ\r Ü ²ìpSNVı(ìIB\rÀÈş‹Dˆ®òîr›!îĞ4ˆøšä€r%àqÎ¿#/jîQg&Îı4S7Rk%Ç×4O¾ª	ï\$Ò\"q4ÇPÂ«|†A5î*î„ü-q16ŒúŠ\r­\"VŠjÖ âÕ8óÑq‘o\0tïw*£5rÙ5±4s25+Ò˜üQÑù	!©c;“Æ\$°.+Ğ2~³K5í4P+=“Ë=ÓÎ…sm(ÒpåcäOÑÜd ¢òÓCÃƒé \0à²ßkRO„€qÃ*p[\"T¤Rz¤â|­Lé@¤ø„K¸\"´64{”AçNHé.ìâd°>Uë˜-‡«ğÂÊâh”:9ÍÛ	¯º¢ŠõÇzS3—NXğˆÇ¸xô‚’U\$‘q æõAFV^©9KË\ndøñ§S83µ8s2ø…^Š¸Íó,às1&ÄÚGOL­m>!&óEK.²¢°\\ËTnÓnoHÀQÜ¯‹÷<2=BeO§¹Oæ\\A´áLÔ õ\rO³Q/kNå7ÕeRQO&ñ\0ôøO•!S\"€åËåE”öY%á?Ò³@2»'1G(‚d¹%¤8‘q\"ç÷q%UIUÂpÈ¬›¥:óĞ(Ó'¦-Sª¢µ…?Så%|^5¥çe5C?mlR4^é°#0~ŞÌ»Y\$BüõK[Õ<Y\"ºàÇŞU9[SøY5u‚µÛN3N}åáDŠèu1e‰)t:¾‰ÉZû•>s}`.¤%,º»5™^ÕOa-%auaÑ‚('âÃ[a®ù\0É}ö6«öCö?OUŞ}õü1H½XnëYÔ1–[aõ*«udÈbô¤Y6W/-÷^µ+1ĞRfC>\0Äú©\$ò\0àùbcàË6ˆşÖi‰&˜æ-iÖ…j ×jvi\0@úL‚c/k>VÃ0WQ«eÒO8‡}¶Ûaö34PX»rf•î4–îpó\\µ;d6Ö‚Öı^‘gh'xU5T1P”Š¬¢‘Òèé6U‹4W r—\$Æ)ë8D?n376{loV\"4`Nd{o‡\\ZK>Õ[³”F‡ò·1Šr'í=\$EuãXì4‡ãq‚rvT¸‚¤•[W/DöésîÑy.Å²9<Qpåvv®#,°È¡EŞP †™\0ØqşpHô} !•±CèàÔO+ìëKwV}ÄÎ)\0ª\nŒÖH^<°±7nQA\$HêD®MG¬åM‚.Ú•fÂ áX\0V”£[÷bWˆÊÏR|oMô^Ñ~²–?+](çˆsô5i{{QçH¬\"O¥æØg¿GµuÎ„m-­\nĞ¥	õÙzq•ucroR\$á8w8ıE8xÈ#£èFŠ¿x\rp‘Nr1ú¿\nü_’pıÒVd<MGxÖqm‹ô‚ğ6öN>ªj#&2FR]mÓgL8¯&eK3¢hãK^¸çN[¨@‹à›&å,=>ıN˜_MÇ¹+˜ï%eJÉ5\"{hUTKjeQñGĞ°+Ç Ñõ£y¸6Y4WOTf‡H <Š«)x»Sı>swBÃ^ğQyE·	GL8lÆ[bÕ&(Æqpª1í'÷¨Úè9HD†ûˆ×9X´K»•rŒn¶æ©øGÄ51qù¤ür6ö\$ø[¸İ`ŞÆ,àäş€Ë™õ€ˆÛ”ïÀaÌZË¦4ûA "; break;
		case "ko": $compressed = "%ÌÂbÑ\nv£Äêò„‚%Ğ®µ\nqÖ“N©U˜ˆ¡ˆ¥«­ˆ“)ĞˆT2;±db4V:—\0”æB•ÂapØbÒ¡Z;ÊÈÚaØ§›;¨©–O)•‹CˆÈf4†ãÈ(ªs2œ„CÉÀÊs;jGjYJÓ‘iÇRÉAU“\"K”`üI7ÎFS\r¢zs Ëa±œV/|XTSÉ‡Z©vëHS’èŒ^ç+v&Òµšâ…¡­k„¥C¥”iáåÅ=#qA/iHXEÛlìKÈ¤˜ÅÅ;Fvì(»=ªv!È‰£VWj)qº–ÈÈÚsÉÜs]Š)Kqö{©®¥Ÿ…f„v!‘±­æûæ¾i<R¾o¨@”¡ˆY.H …±(u3 P¦0ÃHÜ3¸kÖN.\$³zKXvEJÌ7\rcpŞ;ÁÒ9\rã’V\"# Â11£(@2\rëxA\0æ:„c hæ;Æ#\"‚L’¯s‚„ŠğÎLáJ^ „G©«”4½„ÂT–(izŒé•åÂO4[M3„AV¥Š÷€QÖV7¤ñ äD**Ÿ>d\"èï5/\"p¨\nm!InÓ¸BZıCE%.¹«‰‹SéÙ/D…Lğé”ÔArBlÍ<D¤Ã]L»nyÛNSËû‘”“ò*uíé:×L¤	ÔZÄev…â(ŒÚöE”×# Û-1Üz9Çã|ƒ!È²8@0ŒcŞ9ÂÃ=×Æ±¼rãHè4\rã¬‚0„Hç%É¡`@`C3¡Ğ:ƒ€æáxï‹…Ã\r½pFQˆÎŒ£p_`ƒÈ„Iğ|6Æ1Ô,3F#lj4ãpxŒ!òW^Stì¶‚…ÚA“³	1¼Ó5'h…\nxv’/2ö×¶.¤Yé{?e¥)«ë%–¶•¬éS”äb.RR”Äú\0PJ2@åƒªZ;¶a/×4‘ØQ¡Ä%K<dy2‚Äì @B Ê3#¨Ù ÃØ:Œµ6…¢!©Q:“‰NH£q2úödLüU¾âõ’§Y@V.\$¼4DCÚ¥­Ïô\r½¡‰ueP«šëÕÜØµÆãdt9Œcİ	\n\"dU\"esÚĞ¯Œ?SUõOØuÁ­.5	ÚA‡YND|ûÒº}ŸwàvAÜ_Á^ï„R_2ÏDm9I3ÊŸ…¨¢-â‰ÁÚ j¡9ÆÈY(³\\âÏZnL­±\0±lšìkÈ\0èµShÚP“ƒ‚ÁÆ„'æúßkïsM\rR“à@C¨sH,!•°èC’-zK¬9 ÂY»9%pğ9ƒà†ÍC0iñ\0002Ä|”Ğ±Fa¤9¼Ôu\r‘Š.\rÑy)ÆlCÀtd+Áš™V*H¢¥raÈ¸¦aÚ%¡DYgæ¥²²J‘øb@Şá»Ò €:†ä†»Wxfr@€6ğÎ…ƒ›ÊE†ÎbÌYnpİ/àÊ\n˜)gk@	Y È{@'ĞZ—TâUÊg\n¡éµÈÌT9^ n­ğÜZX0c¡¤2.Sa¬=ˆ±6*ÅÃ»cs‡&>ÈAz<ŒaÑš2 D§3BñXÔCz!JqÏ…¬Q¶£Br%¼}%H¹—%±+ÛFil•„Ó‹S\$IŒ™€àLZN`ì%…Í6 Ä˜£cjc\\Ç™\"ªGNVPÊ‚Hm86²é’-_a½ºIÌƒ[.I¥™LpÜ'lï&%@Ô¨3¬—Rø©#e\$J­fÒ^#‚s%rí.ÉÈÍWÀi\r,1#¢Ü¦-n„3P„„dƒ’O.JÉtZ¾WÙk\r<0ÒĞ@bƒ,Í§á¥ËFáÚ”qç9Rà”€  °àRqÔPì&r{¢Şi\n*g¤İDğİ1ÃäBå®D\0äP«Îõå€¤uÉ*ÿ«h®¼šƒ6gLøŸåò@,º§\\ÌX ¶\09¤•İp‘jûeÁ¸8/æ’¨[tá 4†0Ñ);v¹#§£C-¶3†xĞ†ÂF#B¡¶'ˆRŸ‚’5seÛùhóú‚¢L‹b)	R2L‰¡6#jĞŒä´—)‚bò[YÉöOA#­Ÿà38)‘ûL‰˜•„’Wê;™–Å!2ku!‹.b\0ÌŒlÄ›k†å\"ĞÇ%Lrû¶	&Õ’° Â˜T@ï…ş‹Ä!(‡@@‹Ùô²[üWí<œ)L9„!ø9ã¨X©Ô²”Çe‘üˆaá×ˆüÄ„\"÷\0¦õHÑ’%äØ#@¡—ï\\µ/' VE®eÓ—Ğ¹¥T„£0	™ƒ)—E‘û/…@™¬[`§Ù“´;ñ_ãÀÄ¨ŒtG´M&á×ª^á9ù½D\nvØ,³XtÈÙ+&.\"¦¾™­?OÑ:#jB+QZRã+‡]·ÙÈo2BÂ•xíÊØÉ™m`ªé|2»ûg»uò¾Ö~ªĞĞ ÂZ|Õ-Äø”ëä€›4i@³’ÅÔ™7ro9ç\n›lwÙ§šw,G¯\n¥5‡´— Z†Êxü\n O˜5‡±5ı( 3±o¦ÇZ‹XŠ z\0CşìÂÀëÁJ9+p²B£P;6¨tÍ¦t'(\0ú¥KËmÍ.lNšiºØ\n	½ZÊVkqpz.5¯Àè\\m½ä·Dõ?Wß7u¡ğ6õk¨eírÂÈKÎ¡‡\r×}KüqÃ„O(ï¡øµ³òBà“Cl\"}®6ı¦´æŞ›%ı:æåğ\nf-İïÈAŞÍäç2ù7ˆ6¯ŒB&#ºF¦èƒÌuYÍ1ir5®Ú88.\"Ø‹\nŞßU&ïÉ¨‰V§ÿ\nÀPA\nP „01™i\r(ø0ÜY×HrôÆ _­lá€ Şt2Œ‚äxåÇêù½µÈ#Æ.ØU9Kœ°ÜŞ<±ámköXCt\\‹§ë#˜:Ëà1(zç²+Ó¾ü\"ü¬@üã6ı/ò/êÜ/ğüg ÔwdĞ%ÂXón„Tî(9TğücS\0P<U,òM.>(B\\–0\".ÈêÜÉâxFÄD‚x„ª)çû!J\$ÜÙ jGrÖolC.’3§Z\$£útg:l¡TUÊé\rÕíÜl†Ì–\$ \"ähNTı£š†Ğ±\n0\\8#‡\nÏÜæíf6Êİ-Öİ°ÒíğÖ:¬ÚkéïìLğä6PØ¨ğ~miñ\0öjÅ`UA.6¡NO¬¼5¤ GF\0à1ã(H;iàM*‘/ÂbÖm}®öpıÆÅ\rÔu.øo¯‚\r€ÎF%ô\r\0Úò@ŞràÚ‘Í~Ø1€Ü…ôœ¯tÛäXÎPİüÛgüÛÇ‚×hşFìA|7£G\0÷GÿŒ:m1°Á,µmÊĞçğñº†\"PÜñŒCqN‘Ò~ÏÎ#3ãQ'ß‘L±ì!t8ˆ‰bW#zö‘ì'¦èş1JğÑúÓ¢4~­ÎİM«!ò\"ñQõ!ÑÎİÒ Ór\$†ÉˆU1¾NƒT5p>„¸bjåÁ\"\"’¢lp¡2%ÃÊË&zTa¥D¤©Ğ€ª¢ıÎ2e	E—%’tŞi¢’)j—(0~ª!#2&X±ç1ÊïÆöôG5Û#pï›,Œ½‘#°Ó-Bw\$hR¾‘½&vâyî\0@©\rñ­qû/Ré-îÖh*o.úOÒHøøÃ~D\"ië1EDVââ#ğb#A6:ç¼=òrg3ÂøÌ³3DşS-ÒÓ5	\nqèú#g>ÒÒ¾OCÌ=“6w0N„L“u7È:0¯â@÷{7‚/Ú“M5î/s—L!62:H!:û!çpÒnŸÓ¦|Ó„ÚÁ8hE#\r±İ:é<¯q6\r•6SÔ(%#1%V©/|v›9:÷æÚñÀÊò	 1«¶¦BGq -ÄœòO/@%È«´\nrÊ¶´?à×A”@ªğò´'&\\+­B=ŒÊüò-9ó	O4ôFÃ³WDâƒE/8gÓ«=ógFC6æóDRó1s~3U0È,÷„Jp’+4¥+>4„UÅ,ÑÍEÔ“H’[1rM.À	®`ÈD#Ä<Œ@í:ĞˆKÌç>ÍTnpàE®Í#zT-\0B¢İB	2)éKÂœ#JƒX®’ ³÷MMpRaaNlNÓ'POÄ°¡\nB!p» †“`Øl~º´*«\0r§.GJl†êh\r Ì‘eô%`Œ«Dt‡j»ˆ²\n ¨ÀZÄ™Q|BâWQB48Êƒ@ƒA26²N(#¥p\\ŞÆˆÔív9ŸBâ#ç`Rahk#î7c{	Ãm°]YÌÖ)§d}¡R:šJÇÜö07Š’ßFLå2ÿçÜÂ&±•/-:ÃÚOÕæ£dOÿ	‰â)\"¡1K0ÜuPuÌLœÂpÕƒ«Õâ@ëğD²¯CR/õƒa£©Á0Âú!Ö3+°èjôüKÓgXv=«I\0¨‹ÅÈ—€ìGTE Ş	È[îÏA6E)Á×/4„èE)ì›a'PuC‘b'†(±FMÖ–9ñ‚S‚\"DsÊ8¶ŒD5Mâ&cÖâ~!\0Yuí3.aj€V£/6&\nŸbÁ4ÉÈ:.l8ÕÕâß±ˆä£¦pÈ'&Æ¹d§hS!\\’€vàÚÁ04*ïÊˆÍoÓGÄüQ@"; break;
		case "lt": $compressed = "%ÌÂ˜(œe8NÇ“Y¼@ÄWšÌ¦Ã¡¤@f0šM†ñp(ša5œÍ&Ó	°ês‹Æcb!äÈi”DS™\n:F•eã)”Îz˜¦óQ†: #!˜Ğj6 ¢±„ät7Ö\rLU˜‚+	4‚Š†“YÊ2?MÆ3‘–te™œæòªä>\"ÄK›\$s¡¥Š¡5MÆs¤ê:o9Læ“t–u¼Y„Ã)¸é¿,ã¥#)Âg¡ÅALEušşyÑ²&¶™C\\–MçQ¢p7C“´j|e”VS†{/^4L+ÆR:I¿œÌ'S=fÃĞPôºkéÊ¼ÄLœâ¢nxÏ\n‘±¶O«ã4÷¢íDXÖi:zE?FÄÄ²Ë–’ŒC\néŒ°*Ã[r;Á\0Ê9LB:\",,\n9®K€7#¢âDD„c˜îÄˆè\rï»Ş²¯Rú¿„€Ø¼\"£s2¸®hÒøŸ(†¢£¢˜ÖŒ˜„hÒŒPÃ\nî²hÌ–‚Šƒ*BÂ ¢ ì2C+\nÆ&5Œx2ãlÄÄ¨¯Ú‚2¤Ü'/±(*ª««*#)Í#ƒ\nb—Ãz_.¨spÜºÀ£Òğ\nÈÒÀ°m +²OÒT£’ ¢È6±šX7ÃñF8Dª¸@0Œc€‘0uzY¹‹x@;¿Cg5‘DU\0xÖ\rhÌ„C@è:˜t…ã½¬5J9Ì@Î²¡}…C ^&ğÚÄ.`ÌÄ\$ˆ¨Ş7xÂ#¢é\0\roÉD²³(¥C>¨%ö:ß¦)V9?LãÌĞ¢Ì£,‰¼\0VÏâ\0ìŒC#õª*PùŒ+û+ªÑó‚„£ A¿	Ğ–åèËÎ4æyv`:+Ú„6Ä°4Å\0P—6²¬*ÍP®Ô¶NšL¢2£ª)\$£¨Ê_WãD#0CXÇIõÎ¶¼ûâ\$Œ«úÑ7£˜ê9bË ÓˆI%¼\rI:cc”–	‰c,KO1sĞÊ²u\0°\rƒc–æ®˜Æ0ÏØ¢&8t\$Ğéº@T24³Ã“ê/ÎËİbí¹éZ»Fâ¢_ñ\"ÜĞÑ:?B¹¼}÷€õˆ˜mšÑÄºQãN´ˆÒŞ²ŒXÕ²Ò­d2aPª\n)Ë^ÃH…¾ò€’6Õ²à\"¤?Lÿáªø“Š2ˆ„ıŒ}üëëË\nhi'DKt–Ò„‹œUèP0‡•ê½Èì`ø!¯0Ìb›˜eàø¸—Ô\"M¹09†R˜„\$c\\Ä%;Ïü2‡ƒ.‰ó.Gìõä E¡²6…X¸ğÌ‹Ñ<_!Ğ‘)ãDy\$¸<œ8`UŠ³ÍXÉ†ó*û‰¦|Œ¡LÙr¤\rÍ42‚€æ\nHèJaD¡ÄSÑá¸(d9®¸f\r²\r%¼ïsÁ¢ŒHMb†8dê2Ç6«)f,å ´–¢ÖëamÅ¸·—\0nè\$Æ¯)4ôœtæ\r¤“€Ù	Aç/E½’†apiÀéFä~j81¬„å0©—,BAà8;,äRÉj’5g­¦µVºÙr\\9-õÃa|1“ë¥ó£¦¢e ÜMÁÑ_2ã„I‘}DèN%´™MK©	M%<ª¼ÜÈ°baGŒˆDcXú¯ry]‘§,‹ƒAQñ72àÂŒ¹ŞnQEÅ7,Fâ½œ‡!Ì†Ü€di)<6C3T%Ì’:G¤4†ó!J\ra<\"ÈĞÜ£qBLqdM¥Ô(€ QØ—G¨üHĞ ¨n'”?•ãto\rğg3¨åƒ”ª‰)óƒ&'wÈ±»&\nN7·âXòƒÌwwå´¾ÆÂBU”£x84ÚpŠW#.ç%üÀ³™ypœ…ÁÍ’pÊ‹ªõc0eğ!…0¤ªq1¥È'8KQ“™3.ğ‹¯ËYİ©Êu %•sÔGB±ñ4ér\0DI	1(2¡B\$ğÚ]R3@3Ôû¥¢a,Ô¹/%òÜãÂÒ@ÃÉ³%¡’eg1É%´8µ£ÄEĞ¨m2USX4\"ÈÚ9(„Õ\":1:’Ğ'…0©hQ°	½äÏ./•·¥-iÅÂ¾ç††è.Şê°•‚!™ê®4:¤(âµ²­ƒŠ¾n|‹›æ4“Á\0F\n”şË—×Ğ‰§UØM·<9'ƒ_ÌBd#'&0<ƒÌW•­p:K¶úcL±ì+ìÆ9ÀèÏš³A•táÜ<v¥Ë4ãB¹1l¦G\$Cc Ø4:OT ô˜õB>Çàı^‡Dçà¯ÃáÈ¿³ÜÌDˆ¡P‰Ğ<¹Õ.É¢	©±*Ğóß—–îºyJ00¼ÒPBLkv2Í–ó“£â™×öƒvúŠ¼ Öñ³´¾:MG?U\"Õs·Õ’ŠÌa…T‰€°„(ÖeP3ÿO0ƒ¨;B¥¥>¨¬7_âhök<¡%´Mm¯×Ö>Õ%„&cœ¬cÂcó¨/|4‡¦·_DdNÆR™Õ!©I.Ëp½lÕ4tuasE´Ê‡5}‘È9—±Yõèi}Di&ÕªhĞ;•F8gˆ.Ñbu´†êÖ~#İÙà´ˆ¥bÆLgnDbŠKPÚ!M\$‡ ¬ÁJé¶1×¹¨³/Óôİøc\$5 ¶¡›	ÊCYÿ1HM'–?˜Šñ‰ªL0Wğ^eÙĞyÛî(ŞàÃ¡1ØKîğ‚„eà®Cµ­'íø7V\nÑú!P*†Õæˆ~\\ÔDÜ•b&«.y™çB/'(mšòÍ#&läÖîqŞy®g¡UÑ&’Nkƒä%¦àwk<6.@'Âwt‹·Š5Zxâ‚E¼~ŞSÂºO+ü×Œó¤H šÏC›LÕ/2^*•ĞëéI9°LÓÛùoMæHg‹(İóŞ÷çgüOÁ53â{DW~HkøV£å'\n©ı4•â ËÁzÊèábŞiK‰ÂB%ıÖâÄñKJ§Õ\"Ä`<¼Y8ÒvŞ`ƒÆ\" Öÿ¢2àÒqDàé\"şc`Øun:êÂ`\$ãF4­ÀªÂşÈƒ,ŸãHòJpõƒÅP(ïGşnM.*éşàC.¨*pÏ\rRŞĞPb/¯Í¤/G¯pjyÌ¨ÉÇ\$p\"È/N8qàÖ/àÚ/\r‹‡,Œlº¨&ûçB à.\"ĞICâ<F\n¼¢J?cî@æGÄ¡\nĞ¼GÃt!LªGÇ\$3‚ÉĞágÆbÂ*9àÜâFşhƒ,²Æ)È\rª\râL\r¨`uŒ²L¥.É­ ª’%é<J-\$ylÑ­­v­y¤rNğÏ'“Í9ĞHxí­7c0fI'òN…‚ÜòŞÀ§bIñEGõ°äŞÀ¬şG²Ô:Á/€úO6–o^ÔĞ“/&Óç·¢úhÀï¦L¬/<wGQ~aoNõ/9©ãpr;D9NJµ‚2†qÀ@ÄÆ¢ù[aK§‡ê©şâ®.L‡;Ğh‚9KrÉC”€éòŸb,8B\"¤>+àŞ‹'!ê<s&~É@òL)î1&_ Ä@FŠÎ#\rê¬;Í42ªÓ\"ïô'â‚b^1Ãeú1âÆãCÆ%%RğäæÃÌ'‹\"f(ç¢ªÚ®QŞµ\rÿ±]¨g(&:/‘CÈfÒÍ0k„?GÆ!‹É)1Î#²®>ñhg+²Ègâ,ú\" êq,ïP!’ÒR%²¶¤Ré^@Ä ²º{‹ìSgZ;Cß'Q|{‘±mMëÎ{Q®¥1¼ŞÑ­0C%)ğrwO>\"æÂmCõ2@æ_ªÃ1şÒ×2„2Ã×*GâS“{£ôæ«k\rîh§ÀIsÜ02”Óe2`KQq&Œñçz¿³Fê³~üÖ,dQ6q^ğñ°pŸó1\0&‰3Ò¦+îNæj/Š2¢Y\nF‚Eª‘:Ãl•Âz\"(Bj¸å\0ÊåS¯<S´j];Ğô¢*@ædçr³Ä¸# ¨çNi+ò‹>³û’ #³Ÿ óøç’Õ:t- ˜JÃ,Dô*Î°OĞ,Èd?-üO‚´!ÒF‰“ı”3BfE@T0â ÒÀä4#1¬êå3LxdÆ4Fh7q|:Bvi¢ÍFcï”lÔ,g	”w3OGF³ùì_c§%ô#hÆAFhı¦â¥ÀGT–RÓıf\\ï1±¼ô¶ğ/D2q,ŸKteö\r€V¼\0Ó=EæVĞkJœ€\rDèš?nÃÈºê®@ª\n€Œ p„é¸`Î#±Š/¾Í‡QrØÎæï”Ğú#QË\"–M£X ~N\r ›NàÌè@¤\"şæ <¦0A@œ1ğ3ÅH<ƒ;U£Æ-°Ö026¤³5h<ÃĞ]nŒB¦<¢:	ÈŒ%ØVã\nÜpÎå \$åL¤PÒî0*ˆDº`ÊF¬0§Õí¦eàêÇïnÈmRÑuT0x¡U%]•Å	!©Š0\$ú†uç]ò´+î„d>6Ä¾°3´\råpÍ­;Õ÷\$2©)£®m¡Bmí3òk9C0‚L±H¿Bºq„Ê-Pì/±\nà\"2uÌ%i_Ğ&†ÂÂ* Æ ëÀŸ`åI[Î<tâÊÜ@‚1¢õfÔ°L`æu`©]\$zRÄ'RRØ´ŒÈ¬ıæ*ÍìĞ@V¬„\räğã2à5J,ƒJµŒ5hm.C<@ ä"; break;
		case "lv": $compressed = "%ÌÂ˜(œe4Œ†S³sL¦Èq‘ˆ“:ÆI°ê : †S‘ÚHaˆÑÃa„@m0šÎf“l:ZiˆBf©3”AÄ€J§2¦WˆŒ¦Y”àé”ˆCˆÈf4†ãÈ(™:éèT|èi8AEhà€©2ÌÄqÈÙ1šMĞã¡Ì~\n\$›Œg#)„æe¡å\$©Š¡:Úbq[ˆ‚8z»™LçL4¤Şr4±w©´ˆa:LPãÔ\\@n0„ÃÖ=))Lš\\é€†X,Pmˆƒ@n2e6Sm'‹’2š°Â	iŠÄ Ç›öf®ÜS0™ú·Îÿ‡ÆŒMÛ3©ÊÓ{ôq·[Í÷—ÅÜ¾H=q#·\n2ø\rcÚ7¾Ï;0¶\0PˆÖ’c›~¶\rƒxÈ0«Ïò2M!˜Yˆ^¥\\&”´íKV@LB”ÔCÙ%Ã€Â9\rëºR\$I‚ô7K:Š£ãsµ	k\r9ˆÚÄ¨Éb ¦&pr ïÀ#J^-Q(êæ¿N¢8™-cHä5©H(…\r4(*…X×F!D2ãhÊ:4“¾§\"éƒ¾’\$Í\n¯Îë5 ¢„gÍRÔO¸\0 ƒHß\"H‚`7-BÈ6¯I#^Â‘l^Î.h“¢ŒPÂçE‹ˆX‰pĞÂÁèD4ƒ à9‡Ax^;ØpÃOÔ#\\ÏŒáxÊ7ñæ9ìøÈ„J|;#ƒ’5¢#pÌÏ¦¤\nòé\"z:xÂFãÒI¢ˆÅ2BmM¢³j3C¨Ö:D8&£‹ÙVŒ¶°êÀG8Mü‚@-H%/Rr“¦Kè![Ñ‘-­A(ÈE²°æäÙB8³å™>R³«ØÌÀ*-(—:Ùë{I«\rÂ5°“°İ	Ã|Ş:mò^c’UÅ¸…çz¬ëŠTõ\rw;\rIŒì Î‡kØ:™zÀğHÔ2ŒC[Û§¿³º	kªÃ´ÂİN7Èğ­A\n’…P3ÆrÛMµz¤((#­ƒ¶îºpD]6Ò˜¢&U#(à90Ók˜‚µ‹~9¼ÏÃÑƒâX]ğÔ5MbÕGXUş4O¸~0õ%ÃŸhIÏÔsXÊ÷Sì)œ.3l[DÆ”°É‚ºoR5®kÈ(­1LšÖ’³íÕ\0006ş<\"¦^î¤‚Ç	0Ò2:øj=Nÿw?!:½1­(i~˜ŒÖ¶ÓÔO\\eƒ“œ+µw’—òÁğC\rëˆ4†sÚ` >1+…& ¢pwŒ3àgÁƒı`Ûõ¡à:,ògÌQåsŒtµÄğÅÑ\nOAÌ¡:GHŸšñRN1;€ÖÚHrš\"æ‰‘†Ò>Ò©}UáÈê#niåéA&Ê'T¹ÂSaä%\r0üP ˆ‚Kâ£>2#Ä’ Œ¢a™‰ñDIÅ58Ë™‹¶-ÅÔú£`‡Ñ\0‡Æ'#Z[¬¾7£8šDÎTPŠQR<Eh÷‹Ô~w„¤%C¡ƒ™5qLˆ‡¦ÆYÊB à˜B•\rÚ²C¤¸ ‚ÂA‹d‘Y­ÀÒÔ¹™\"™«El®Ò¼WËa,@î±–AJYK1g-Ğl<[\0ù›¤y\" ‰)\neÄ4®„½ÉÌLU¯n‡5fd k^ò¨é;‰V”á%,!@[a¯8¤-¸@]1·ªå]«Õ~°VÅXòîi‡%š³Öt\$„Á¦m-˜^â±hÅ<I£ÔïIz…7f%Y“u^„è);1,ˆ’tGÓ±a7sŠrS,©Qr0Ia¨‡(»MCšõ3h	8‡ŒKÄu.<Åuğ¥åÓ2QŠ2Fi\n”i\"å%!8ç†ŠR<‰OÄ¸†â @P5çiT¦g)2KH‰PÄT”AC4Ù¤sIL<:(ÄĞÒîhŸz{ÕüØ#P‡ØÉœ¬ni€ôDÉa,gJ`ä&m \nÆÄ«éå?F¸4«4jVñU•Ì 7Ahg\")… Œvò…­Ô¤‡8º—rˆÓz5\$ˆù„!!¦qhîfÔÑ1&k”›Úm\"Ò´´\$½VŒQ}7ì@ •ñ:J’‡'	reÂ®¢fdœÛ¿’*¹Ë™£/Û'2ê*—PØˆP	Ô\rğ:Ä„ğ¦)M:2¸Î4Ü—+0jN²68Äé\"¥.sGÄÀÖˆ#ˆSª1\"XFÄ~ PìĞ5·®iÊtï¹kFf¢š‘AEˆÄ#ÀË9‚6GHù!\$d”“¥Ó8ÉÉ£â¤Œ07’‘ˆ %ı¶U)¯³¼ŒÙaå?©	¼(’\$¾fmf¬\nü†÷HÀœ0\nQNrœ¹ÊçŞÉîæ€ z?—\\ëµ|ô][¢\$‰Œ˜ã!£í6‘:ùIX–Rß0e\r¦üƒÜ\n²êÑ3¿©º¥PÈƒ„)|³ù‘òëQC¯^D!ôn£ËPPy%Æ#ä6:AXA¤”dHï™Åôô‰!3´…A9\\+•{­öµò†pˆgG‹İ«·Ô¦k8y™²côÖw\r³_v]Ÿ£ˆËĞùÃÖkÑù ­`oÂ`oXæè¹‘èìAC™ƒ)u¸4=KV!}ä¡Ô'€Ò‹ˆI¾\"İ6*AB¦ğ%¦\0007‚HÊˆÒ4\r¼…”Ö ìX™zå¡Í¦)0ìX¶Fô‚Üœr÷Roáåm:åÔ‹â,Ç˜xCäÁ03j@céa­5š\0ÏÉôQÁYï§«uƒÉ%fAç=bÆCu<7„¬BÅëcPZ‚2ä/š>]xuí\n†,Œ< …@¨BHŸøÏ¹æIa)ú¦JX¨”·T@ˆ±“šğ¸}¹²Ğ^T™iÇBlÄãs#\rç<Ó‡˜ÓÃ%ÄeTËê³–»*%2Ò%\nĞt_ô~MÏªÿQ;¥/0ÅQfšûÏ =ö¾œ„^TÄ}d¿õÌq]ÃˆlˆIi–:é†¼brP\ne¸sá8i?:%ÂoÂ}ÕA÷É—á»sîc_1%\rÁùÈà´‘f‚—2#åİ¿SÒÃ\"‰Ã(Óì¦P\$((ä^‚l¤0ì¨s† úOì#,}`‚JCĞÜ„>D\$&\"şÃ˜n`¤_§T{ÚCÏbÖ\"ên0>jfB7ãr7k É#úæ‡Èùˆ”Õââ/¤o 5\rÕƒ€÷ÀÒ~°H¦­4÷íŞráK5	mâ%î~âC	‡âîğŸ\n­â),ì¼íBZ\"ÎWí>ifš#àŞ†¦j¤	Ã/ãÀBb(uI,ì¦¤M­Ïf¦z,ÿÇ¥\rJ|ƒJ	~÷P”0.fªrR`ØÜNÂ\rVB<\rªÜG`-\n¹°üH9¬£€ÜAGXQãä„Õğœâ‡|Õ£fŒdb.­	ñVE1ZÂ0®|âÅQFwñj%Ñâ€¦âpšA°îÑpUÖw.&œQ„õgdâìfÎ€Øg–lü!1b/Â ùo|öQ¨y‰K1¶çï”Ëpım„\rç•Q±Q´÷1Ì÷^¼P”ËìÂiãP©(¤%ÑQ)£BúÌ‘äõ’ÌÇ÷ Ñn×®(	‹Ï£\$¨jŠ¨êÚ\"¤”VcCl¤ŒD1æ%¦iëú6¢2æD¡ÎŞÔL½!LÅ!±ú`îtê äD(Ù'dë.„#2HmÄ©rvHRãÒ…ïbœN>”§Ì×­d×qb‡Y¨ÆûÒ‘G¯*¤¹Åü…2²Ğ2™ŸÊæ{	jĞ±,²´aà¦[ìòOŠÌ£P`Òà¨ÎtƒîDñ§ƒt`¢?*î)qÛÂ?+†01Ã0’Ãî)'F&Ç œ¦1Ğé1&\rÍzyàÜk³%-¢srù¦R†Â\"@ôa‘zdÆ)Q†Fã)±rºÓV¢S5G«(eıvSdLu62?³V-FpWó[SŠ'2¡)Åã,Æaó0}\"Ğé.¦¡#¦U¥_h\0à7BRé@Êé“¬\"°È\\\$“½“ª\r®á`@áN ƒ<è˜êSÂê€¨ë¥8èS?(äó•‚S?Î¯?rçšçãg?S-.-@¢Õ\"IÑ*B¥\n´mlE2“BËDPôçèRP”:,“•8Š)ê`g‘4'˜Rd®³%&!#23nÊöïy4,Zo€;Š,ônØbgGoQâçHœ	d\"/b0n€Ş.ŒpdÍ>õ|õ‘»¾aq½Ï×K/“‘Ò%4%7#çDxÀë¦;@†O\0Ø\0V.b&2¤ŞŒrDç„š#¤N¨àª\n€Œ pÇ,9Š˜{l´7Â\\ó/=K¸{ÕJ¯:9:*AQ\"0ÈNóC;\nZñ@D)KÒv7,ÎÇâ\n0d7´—T†'Læ©LÉ‰~‘ÎÔ—àOUÆğzÈÌ‘8)‚Õj	#&ÈäÕ\"DdH­P¦æze¦>R<…ÎçSÃ¦!0]73û'ŞÒıS°À:d„úÈ¬3•²r35Ë`AI¢ÎT\\”[Dºã‚Ô	¦Î7Ç6È‚ßÃH¤®è#ì`Ğ±J\nG-èLÆB!¬pÒ\$(ÿ5(zBbÏ-]®3I` .¤Z°\"Rc`.õŞ<£ªßSNM°^ÓJ?Í'=ä­/ñv×6M	 \\CëIÈÅü@sŒvL’[ÌJÒFKææ	Â."; break;
		case "ms": $compressed = "%ÌÂ˜(šu0ã	¤Ö 3CM‚9†*lŠpÓÔB\$ 6˜Mg3I´êmL&ã8€Èi1a‡#\\¬@a2M†@€Js!FHÑó¡¦s;MGS\$dX\nFC1 Ôl7AD©‘¤æ 8LŒæs¬0A7Nl~\n\$›Œg#-°Ë>9Æ`ğ˜\\64Äåæ‰Ô¬Ï¶\r ¢¡¦pa§ˆÀ(ªbA­‡S\\ÔİŒÇZ³*ôfÑj¢ÑäSiÂË*4ˆ\rfZõÚe;˜fÖS¦sW,Ö[\rf“vÇ\$dÊ8†˜ÉNJp•Æ¹óiÉºa6˜¬²Ó®`ÑÒÖõ&Òs=2§#©Ìİ*ƒL=<ùCm§–Ã(²¿¢¨Ü5Ãxîë=cŞ9#\"\"0³šî2\rã*¿¡O(à8AhS 9c¼¦ºIÚ)\0:éÚz9¡#«®ì»i²~¡#êJ—	Ã{³±ƒH5§®ª@ş#C£H–?.\$|ÅŠL2jÇ3â8Ë=-#¤¶µ€P 2§iS>';‚Â–,‹0—„	ØØ„>²s\në'#£ú&\rékÈÎS¤ìÍ¡íšj6,ã Úâ=it+ğÈë\rÃ©¸Æ1ÂÉ\"X›¥Ğ|\"»ãHè4\rãª1-‘Fã 4.ƒ0z\r è8aĞ^õ¨\\œPcsÖAc8^™ğıD9xDŸ‡Ãl»¸ƒ4¨£xÜã|ŒŠÓ ÁòĞÂè&‰²Úö\rmèäË;Ì‹&]	â×p:\"KÔ”4c¨+ÇÎ*X‚„£ @1/P~ßø,	c|Öü+*„¶ìø–™/É°ƒO\ràPœÀ¡³K\nÅÛ,S¾#;i\$´±\"Ë¢+93â–%ò¤+0¦ö¬ V\":½Œøœa+ 72Iblõ\$k:FÅ‰.ËøëLcªzã²‹8äıºÂ˜¢&NÒj\$>ËİÚÏ?¼S•Û¶iò„C;kú)îKZY´Nî&Ú¤­ÃtÏŠTóˆÆg”ó£-´‹hÒÜ®\rl:©89’Ju9`ˆ!#[8[Nú~=¨UMd.—3¬›GÚ¸ójZÈÏN9‡Â¤3\r+\"éØËÃ”ëF`Pˆ¯BbïÑÁa(1xİ÷“ÑŒ£ÃÔ7\$–’òbš»6HË:ÒÈBNªZ7ŒÃ3]#3Óe.!8¨õÍQs0÷¤KÖ1»»üÓ:Èo*—‰i\"ê’\"gğfğg6\$ä’ƒÈd\"¹SO¥ş4Ö—ùë2D¨Œ¾óHHM‘l[o­«¹v\"XĞç*	B%HX\\¡%~mœİ†ãzCÕ0 U•U*Å\\¬’´VÊâ«Àä¯–!‹D7,e’Lƒ!ë\$.ğæ¡cbz‘RiQjz1ÇößóëiÆ(Œ¦täÇ ¹ÖpĞ‚,.äÖkƒ .TªTªµZ«ÕŠ³V¡İ[ÂÅtƒê¿‰¯Eé½Xš±İ¡ÿ!†yŞ\0Æ _7†ú†TşgŸ¡Í\rá­iS¬„‰#öI¬—åÊC1ƒ¡ »†Ç™\r„L˜Ê%œºş!˜õ\"Tz‘ÁÔ‰†Äêq%Šš'ªl»¢Ão#‹¤Q†Æ86=scÑyÚhÆ|£	¶€H\n¤Ã\0POÁL¥rnN‚RıQ±L[ÈìM©<úË€)Åss;Š3õ%äÄ™Ê¦•((ªE¢Uµ˜WßĞRşwJÇZ DKX†€ÒÉêƒê¶ÑRï#Ïheƒ²l™\0†ÂF¡/Ö…Ç#œ‰Œ\$6ƒ‰ÎT\$ŸÍ‰;Dd!1É¿.Z:\\]Æİã%¶lL\"şŸt\rìÊ AMÌEnä\$ó¼”CŒ+5‡Âzrœ¥ô0W)È›µ*i+W*Õ•<¢áÑ…f®üæ<\$ĞxS\n“¹ø§ä…‘ôrbaŒÀtãXS’.«PĞ:sÔœšx4Ï}° ËH[}!ÄxÀÕv·9™1E’ú¦Xcb‚¤ãHG=FÚÔÖlK\n]%q”²ØÂn¤ƒ8\nl(ÄÀÂp \n¡@\"¨n=É&[Æ“	²aDìBÂŞ0h'}‰´6‹'’Ó¡:!Rz®éö„¥éí~‡øõ¿SfMÓ‘JM]>—s0nNøBM—ûU²vŞc<ï!ôÕo`fÓVjRA\r&HŞ†ŠÈafo\nUµTW/†“~noe‡(=à³÷\"¢Ò\n\\¡'àéß[`êlftö&Ó†q“ôòÿ¾¦`Š†œi‚˜e\"²ù¶™ƒ´³!!¥iµÖ ØiL 4µszİ\"ÊØW“€Ê¿ƒ‚kOQ°0ÊLƒšœr­ĞàÓ<OÂß’Z}±lM›ô­‰µpeg2 Œ¬4Ğ¯†d~ŒQi-œM\$AY¹Ù=dhşBVÖZq=\rvôØÂ\$å`ci!ºšOªCX\nœÉHÒ{5ö.G¤ö—\0İ¢”Ög`«yÃdü»ÙÀ‚úx#,ò_°êJ×P ³êzÃÌGê{;N©ØKÊM¸¥¬µ®ƒÈ\n`€¼n¥ühÊğ\nÚÛcmA³ KÉÙ#%dˆ•ªF	¼\nü½\rA”ÉMcCXZ½9L‡ï‚D‹\$]»İÀƒsoBb·´¥ß<C~çÓÃ÷Ø\ngû½%RöBˆe£0\nWrRğ\n(v——¥ãĞ‹\rÊ“ÇÁ)7~V~O\\¬šfF°¯qVòà<á«¢ô}Ÿ’L—	èÈ’ı(z	1ı^F²ÂE’^t	Èm İl”FÜ\$Üƒ™Ã8¤g²:>Í)r\rçš®®â_…—rÊı×	^23ÜÎOo mÀŒ¦@Õa2é‰}T?˜ûÊ2e¼ûCa	Èr[Ş[*%û²¸\n-Æcdí¦™-ÀpŒ¥ïÅl®•ı#éË8lè.eŒ¢Ióa³¼Æèøcúí‹Jšó¸6`|Eß%/xÂÜKü\\ò	|7ƒ0çœ‚0‡^&âa»ôO«Î‘³Ô÷t[òøóÎ¾í“šİg«ù°)@8µø7ôßÜ‘ÄØN0à‚Ø·‚äÔ«€ü” À–Ô‹~8‚Zş#½\0K|ÔĞü¯Ú&»bP<­4—Ä„E‹V&È`”ƒn5£^c®aÏ½äÂT,ôéK#9	°“†:ğ>o/°µZ%ãØ)b)IäaM90<Ïg&§Ü½­E’ÏoÀşLô„°üì/\nj’ûC´ıÇ,Tà>£Öo‰şıùÂqPÇåâ‹\r0´û®†Ä?¬<LPØqcú âväD Ş,’(¥¾¯È5gŒ-¤–<äÆ\$¸± Oì™É\nÎ%ã­ÌñÇ°)p,êé†_c,NÌæy¢ d£¢Ác\\æ+À@\nMxC>\n‡\neªÂª\rÃÙÙˆ;ğ²îÏÃæÖÑ„3ì/…ÌØ¯Y#N%ïQ\rğ¸êğNŞ1–!Q² ­P­T¯\"¾&*Z„ClÎ#1¿#	lŞug¸¡ÄIb¨2¥¬ÜÕÄH1â—ÊA\rbÙ-j=±rò/Ó My±‰\rmvÖÏÁLr ±¶×R&3DXdç\r£úÙ-¨û1‰²,DË\$ûÒ=\$²û†~Î,Ï\n´êqRAå\0`ŒL\rêı„(°˜Ã>`„\$‘æ®}d\$McØ§.D&BŠâm‚ì¯RàP\0zà†HÀØ`Æ1QÂì 8¯Háhl’ƒXÒÀÂF‹J6çR±L`Î’ÀŞH@ª\n€Œ p#-şä¯À¶‡(edBlñ&ÁMêæ;,ND;¤b3ìX‘E@Ø®].5er-R¸c£–‚‚pM,†„kÌ¢^I~£¢\$J<\\ëÊ®î!(‡Ù(ã¢ğtüj†Ónöı‡@{ïĞïóg„X|ópî‘£ Ózğ0(\ràà6ÂNïNèHF†|C¢eÊàûè;0j Šh‡À«ÎmS°œ¯^i3¶2\0Œ.€Ê?.J@¬•Àê»€š,S‰8Ãğq#3Ú›bt+Ñ´/Ã=³^&¢\n[3l<ëîÄŒûéöôƒë,C¾#gÎ@ŞÃÖí<³XPì-HÿÄ\$;G*Á@"; break;
		case "nl": $compressed = "%ÌÂ˜(n6›ÌæSa¤ÔkŒ§3¡„Üd¢©ÀØo0™¦áp(ša<M§SldŞe…›1£tF'‹œÌç#y¼éNb)Ì…%!MâÑƒq¤ÊtBÎÆø¼K%FC1 Ôl7ADs)ˆäu4šÌ§)Ñ–Df4ÓXj»\\Š2y8DÁEs->8 4_F[QÈ~\n\$›Œg#)„ç¢Ò)UY¬v?!‘Ğhvƒ¤,æc4mFÃ\$”Şr4™î7Óeû5ŠÄ†Ê°*’wµšÁEI}Na#×fu­©Vln¨SoĞ³i‡@tÔÆ\ròÙ2aÙ1hÌláÇÉ ÇœÊ-ãòöæ¹“µÖ×6Ü­FïG›×5©›!uYq—|ı¾¯P+-cº‘1 ¨íµ«\"Ì´7H:\$ù®Ã0Ş:‰(ˆ™»r6ªA Ê:¹ü;¹Ã@è;§£’˜§C-t´„ˆ@;;Ëö»£Âh9©Ã˜t¢l’(¥¦Ò:f1‹tŠ×\"­›\r‰`@ÌºPÈİ°ˆ£l¸Œ#K¨·ïhÜ‚‰Ê{âİ,šÔ T©±ã˜ó£h)­ohå,ëJÖ9ÎÜô+Å1\\[-Îc<(°Ú†\$O „†HÔæR”€È6µcAPhÔH¨!rÂ^3¢Ã:\\¨å:„ÕIQE|XÓŒ`@,3¡T:<Ğ^öh\\ÔrÖ4ã8^‘…îøæ9Å‘p^(¡õ&÷®0°äæº(xŒ!òJ²*I†Ç¶ƒ5·iêjŠ,÷°ê:Ïğ[²õ[O§îÒ-‚¸rœ:á#J5C»kcAˆ.0\0JŒCÊ4ãh<½	cz?=\n‹Äâ!ºt›‘ŠÒÀŞ\rÉe&Ô\rÃ<öœV#]gÉ%È P^Ú+y(¸Ò^ùC|ü0µ¢›O{¸Ì¯(»l2¯/”¸5¢iœ£T†’½hí›®16ˆ ÆóŠbˆ™Q åDê:ë€7¼´åYCØPİ†>‚›A<Ø00Ô=5.À¹¡›¨Q¼+?Ê P¤2¹î\0 kv\0½Oƒˆê‘ô¨(‹3ˆ(’6ºÖà£Çj9kS°†pÜ¼¦!O|[š\rz*‹Ë»-p±’¨äš»™öö<İwjKçaöX7.?Oì‡Ò\"‚š²>+BÑüÌåGQsmÇòÌ7 ~Zâ’‰ˆtD}`tOç‰\"WğÖÍY%kJLÒ\0¨` BÈ`§×‚¥©ªg\$_xD{€9L@ˆ—¤úÂÑ_èdÀóÚğ šé‚ÇªH2Æ`à ƒÁ¾*(DÃØHb„ÆeÂ¢hIB˜un´9Ÿ“´”á¤\$¡PĞ†æÉ9Hä>¨Uğc*‰+õ‚ÅXë%e¬Ğî³âúÒKP9-e°\n¡3\r%Qoƒèğ¡Nƒ9|„”*°”êÙBFå­®`@Ylo„£ºå”¡µ\$è‰\\(•v‹¤Xr‘¤’3, Ê±1?‹1g-C#°nZèùyG¹d¸ë³#Éä‘‘¢ÖùvyjL:€Ş]š&*\r„æÂr PâO‹mû’fHYÎ:¾P9\0Ôx‚ e:Óh§+V¨G‘R%òn©ViËğs‰N¶'CI2Mf\$ÁœD03bŒÌ)m^)wšô¯-9N8\$Ô˜†G\"€H\nú‘4’hJgÀ€PRIP rˆD^UFÈ\\\r!Ø4›CT_QqŞE¤ĞÍ3D`Lñ\"Ä”ƒĞÚ?Ô\r!t\r'„WáNŠ…g}t¾y”Ê*“êö¨\$µ@¶èÁ™™œ=² P©ä4†¤0¦‚1.>`¸R(¿Õ*˜•É|\$¡%\$ä¤•’ÓÔ”d\\\r&¤HÆ”3[K\\Æ!IÃ7>§k¦Åú–,¼´OyJ ·¥|L`©:§x0Á¥‚¬M<¦pÚ…\0Â -V\$Vš”vOl=!\rfĞ¸Ï£\0oMa\r\$bÙ¬ñ(lR®˜E1>RÀ#Ğe€€5Ê\rmÉğ n¡0‘\$EÈá\$ºÊ‚\0Œ(™™®EÚ;dÃM§µIõi´cNMI5-IÜF#gŠ¡	À€*…\0ˆB EÀ@€\"P˜pKCdÇN“§  ÏƒÄ-xTÂFÛHO¤‚…fLÈØt7ª¬àğÛrH0m<Ìê¸ä‹k!sÂô5h²~ÆÅ×›4C½pyÇ¼ä}ouªa/f}|M“’\"©T‚¼´ı\"Ã/ÔFôĞ‰‘‚­—†íXìÃ‰pÜ@8²ÿ®“vïÒ‘ôäğŸ6’’cd^™&˜%¼jİ¹ô	—Œ·C Ø`Ñ3é53Ì`èA±Ídsn2½2×|Hiƒ#°2‡v©s¹²1¸Àš‡c‰âz1\$ˆ<\rfLË<\0!Ãè	y­R.]Ÿ@}ïÍÔ¡ç'5\0†`CdAÆià@âö‰»5œ…–Í´jvîÍÖÚ³O‘¦8@PP-s6^‘²€PUbFù?ìuÎvôºsP@÷ÃL¤ T!\$U§è´í3'(¨+tD‰\r-öC;ƒ‡-ĞÈAx L„á²0pÎ\0Qu|ZtÓı:ê§!Î”˜èYÈ‹^Ì3“¨º«îTq¦j[HÄ²—\0Ş”I™}ÅzVCÅvdò‰dN's>O‘8÷äiÜ‚O>˜É¹/4EÒyÎ³€ˆq™\"M…¨›¾F¶Œ>™Y}ßDş_Iù\$³ÌÑÙ»±œè®'»	CTXC\0(\"Åh`…é¦*„æ¹Í”å*P‹ÙB`Á)2\$šõ³”9­˜4¦Û.\$¡%‰¼¼T™U_`O!h;êÃg,ô\$R)çdsM1ösš¶vRØ©hÇµ _\\‘>wŒJ[h–ñºYñĞÄŸCåÔÜ8¢ãÖËú2ª¹yRèÓ± Ğ³¡,Là)³ââ”6ÓD3\0¹œ›ëRÖDÉiĞêĞÜ%àÚâ/ì#o@÷\0ÖF0ûD}Góo\\â''°\n«Äc'*!gr\0Îâ-äöİ'HtÄÆéÍèå.>åm»€\\,¢t Ètî²éä\\æĞNçÈoor\$­ì¿\0Î¿M—Ïòó°vÙ+÷Oh¾Ë­”Ù'#l×ÀZJâj\rl&\r¦\\rÂ^¦„p&ªä0`ØŸ«DCÂ#i¼\rÅ|=é¬©#2./¶»Êt&§\0·Â\nÃ¿P¯#3\rP·âÓÃÜÇ‹iÕM‘	KöNM‚6\"@bÖ)mÂFN?£ş8\0¬aŒF1.Ô°€A#i*ÍP\rQ0ßÆ uézªë¯õJ>\"0ôºğ‡aIA	°\$±\\*\rt[gq±LËO¢gg\0001Üvæ¾EŞP¤ndÌT\ràÒc0@ÊPD#oñÂK±«1³0^LqpF&1J›¦\rQ¿\0†Ø¦ŒiÊRMÿa#èsç¤6TO`ÊàãÏ0!gW¯oQõ‡JL2:(‘ GX#r@S rš#veQXõò1(§#\"Ò1\"Ít 4/, Æôpòâ2FÚ#\0001Âl×(vòXÚK&@&£1­Ì±¥îÚòlÛí¸=ñ®ÿ@¨ÛmÃP(íÀß±E Ò™(eEÈ)œ!\rş\n‚TN1vßeTRŠó¤¨2®å°,mù*rŸ	ÂÉ\$ÃK`¤ñ|7ƒ\$o1(0† d(vu¤È\"‡\0…’ù1|¡‚o0\$42ÀÒt¢Öãb0·¬T§Fªï¦»1Â‚óîtôN¢bDò(r•à†€@ØkvFæ Ï†™¨Ÿi.òãr6 /Ñ¼Ö“ \n ¨ÀZ¤ŒDc%j!Ğä<&Cö\$®oÂ0!a†ëÚwnn31`›\0Ë9³—Ï: â´mç€(\r^Ñ\"ØgsĞşús\\lRĞF‚ìİâ óÖ\r`NTsV:ÓRÅ2 !Ğ4“JaE‚.\"dR¬ÓÂı@gD´¤ÀığDDdÃhÇD˜\"`ê€-ğÕ\"ló•:Ãh&TÏ5:£ğ-b=A°Å4@30ÎL¬(ÔR&´E#4>6T5Eb0\"Tj‚…:40Ï0¤¾\rJ ¦.¢j·«ŠVr¸g/|NfÌÃæÒÖïÀı¦ÓIÀ¬8g<ì.Ö9ÅşOLD:š!£1§¾€FL¦ P ØNôÑ6\"Ø#CZÃÄ\0¤4J&b†¤ò¦*¤ÇI%NƒÌv,%EÑ\"jE4†LĞãh<od b2@àZBF"; break;
		case "no": $compressed = "%ÌÂ˜(–u7Œ¢I¬×6NgHY¼àp&Áp(ša5œÍ&Ó©´@tÄN‘HÌn&Ã\\FSaÎe9§2t2›„Y	¦'8œC!ÆXè€0Œ†cA¨Øn8‚ˆG#¬<ät<œ'\0¢,äÈu‚CkÃğQ\$Üc™Ä¡s¹ôn,pˆÄÍ&ã=&Õ”%GHé¼äi3ŞÌ&Ëmòƒ'0˜¦†ÉÄt¤e2b,e3,®	ÆßhG#	“*\n\"Z\r¦æRs3•â\rÚ,æo“&wÃœg a©hfã\$ÌA¦„à29:t˜a3ÌÁ\\şŒTÏ¾¯Í³ÜÏ3}éu8Æş¿héŸ¡B¨ı>„Ìä\n)å%Ë‚k­W?Sq¬Ü7êp90Èèˆ0ŒŠäãã+zÿ¼ã˜ê‹°Ï8à‰c»2#¢²‚7\rŒB&OÓ†#²ZÊ8‰¢l…'Cšë%®Ld	É(Ú±Àã Ş<‰8Ò2>\rÒ‚ğ+KÙ†S:Bs::ÉÃğ#Œ­Ã:Ã¾Ê6¾	Ä¢÷«jêp7E#p(CÛQAI³¦ò:ëÜÌ('\rZ7<ãcÈ½BÈ6¯jHÉÁã|#	¬P1ŒprD¾E<Ø1Ac¸Ò:\rxêóŒ!.9Ã0ØX”èĞ™ŒÁèD4%c€æáxïY…Í-\0…As3…ê_OÔ#È„IØ|6°ÈZö30ÍZP7Áà^0‡ĞêhëŒ“`+(È#t®ƒ«Ş\"¸4É0K%Ê…ÃHÊ;ÇËºÒB¸Â9\rËØÎ‚„£\$H<¢€Mû¢ˆ(–7ÏNH*,#*ÜGsÅî±ˆ4ØŞ½i\":5(Ì0£cÎ;1c®\rƒ8#!	Õh3cÛˆãã¥ò¾>	ƒğ4Ì3n6¶i®8)Â«n‡Å¯†2Ö!øZˆ‰Åƒ;L	\r`ÉçcÈ]`Z9Œmcà(‰h×ˆjÃxàPˆº·SŠ…Û7:å¹8Ã˜Ö5­X'¢í„#½ï£×»&ÛÖù¿\n=ÚÊ1+¥MĞ/{85Ê#rmqó(£Ş\$´J¾±ˆ©Ep¬Ÿ®2m@…¥q|5¹~7©\$GRØÍ…Ä¡5‘Bd06•¨÷˜|!ÚĞÏq¾}³ñŒ£2,§ ß¨>ªæŞ·éÚ†<\"œĞÓhFâbÏ\\Ê&™…¬Z4&Ë¤,Ó]ò*3ÚÂ™7Œî½6ÂÃz?NËÈ„!%£ƒ3!	èÄ¹¥Fn—‘Ğ%íè°5n¦Ã((`¤…Pé i×J&<”³ÂÃCL7«0œ‡UuÓò·'\nŒ1¾D†RU( TìyU*Àè«•‚²VŠÙÊ« ä¯òƒL‹<7,@}BúyÄta‰€ÖyÔØl&Àó‚Ğ@æQ\"O)Àô)ã¸˜'#å9Õ€†Ê-ƒñlÂ*ELª!ê­UêÅY‡ujŸâ*»W±42¾‚H–‚ÄXÎ€¼7Ò‚çr'GhøÓ‡¡Âá¨˜cL…“©¬‘ñR+F¾	+!a´îd–ŞXÈu‹ø8:\rš‹‰€K5³\$§‰Ä,Gkø0†g<A¬T\$6@´öP”²˜\$òä9³\"\\Ä\r&-–ãDÚí4ÄÕ‡MãŠN9¶•âUIP €-œ0Şr#\0PNÁLqHL¥¾äB_HëÈ9!Œ”)|§œq@/²Ñ\n›Õº„A‚@b†pòÊNP}(ù„D™xn–æÌã-Õ:Ğºz*aeàà¦ÔòCK\n^€ÒÃ@ PV\"C.[.aV‹Q…ôS\nAö†ö\"ñœ8‘•Å8S:C\$ÙÍ#Ä€‘BLkK[ÄÑ›&T€—ÛÑŞªñyÌÏôFÎ‘m aåú™•ıDzşSt™‡LˆÃ2\$ĞÅÊÍ3ÓÑ6škuĞVoÈ™¿n³X¾IPÒA!«3 ¸rÎæÔ’	b-H ª3˜Nk/&\$ÎzÆc„NÛ3É1Š>C2Lâ¸iJ0œÔ‚\0ÖïKÉ,.èÈ“¦Ùi™«µN„`¨qt-OûIÒñH¤L­L0ÜÎp\n6îŒÃ³€\0U\n …@Šßï@D¡0\"ŞæÂSºeLëŞk9äÈ“0LHíL+¶U€Ÿìû¨Š2°ÙRÁØ@…\$@ŒßÀ\n¨”\\ïÚÚşÑîF¤œ†\$Bgƒ™ò\"”-…&İİK‚ÃÊQ78ĞŞâqKçdëŞmâc6‚W´‹äà9Òtô.` óÆyÎ;Nßá}<éÈ‚Œ”<ı.ö£€¨ÓÃ¥ãsËé…œ³fÃHzaÏ!ĞÁ…·˜àD@˜H&óJfğl°4‚¶g!ÔËÃuHUSVGHşc1y§<.ÕŞ¶×rğ\n™˜ÆêH¥á]‚1¦˜ºEÖgôã› Äm©„Ó‚c#s:a—˜3wLÅß,Æ3fÉ\neÄ–S€Ë®Y‡¥#UêÒ6IöHÒÄáKç€ÄS<¡Néä ´Ó.„™±áÉg8wu!úbP*†~Ùˆ9û`µ\r2££CŒ¬2ì{/tµ&\$ØÆ00^S˜¤Œ	p\"ÆÌ\\ÍSašNpÕ4¶¹v‡\"a\0Ok|/†‚Äx.w'2ÔRñ^.Gg1BlìÍ´”“¹Ç	‹û`vÿ‡NLxpKÜßp™ÁÍ¸ï#¡.N¦HÔ¹İ@Ÿ§¢/ps F4„ìğ®C›K÷'cå‰'QZE¸iEV27¤\rê”8İ˜Æ‡Îa—s&›C8k<f‰Ï	aÁK; \"sŠR}›]ğäO¢îİIÄ'ğx¤ÍS<œ¥¾”1ş7x-&×|™9(-Îùs™ß°‹ª•^wJ_ôÌ²	Y†H­Y{†D¾fQæsF%±4òHJOd¿~Û\"³ßíIG½s^™m“`çÑnF'Í›QòH›\rO¬§¸)„¸ò3ĞmsM¿°à~¨™‰›{üH‰1:ò¶ó¢øt½ÈÉÁ3Ñ£ù¦T\rû|Ï¡p^ş_@ù¯\nk2*®ò„ğv0\n'\"îÿp\0p8o8ènğg(Néôæ¾äæ–ƒ‚äË6'& ¯ó@éäp>\r.,ğN¤o«¼gBFôb|Ö\0ÈÖMhÿ0\0„ğ^fmcBhÿ0\0î†Ô€Ê‹Ä‚•JkÆÎ¿“	Ş †dGl<,ÄTi>§IZ•ân(BöÔ+]š¶0 \rÅFH+&6¬ª\$ÅP)f7­]\nFÇBgğa-gb<ÔåÎu­R<-X:í]	å mÅ¸]ğ#¤:Ò0\"ğlÒ<â“±\"Üèdm\",‡:ÿ1 Åb:±:ÿåÖ„ñD‘ğˆp0Œons¦ÿC<±\\\nd+ãàÏCæ	¾ø®Êd ØæëX±C\$dƒßeô>ğQ™£-ñRôQA‚2e¯…ƒêÆã0~FÆ'V#ûñ¼3Ñ²&ÀÉ3¥ÅrçÕÏÖ#±Üñ£Ñä(‘ß'‚ls	ñ¤ê‘üûf,Iõ/:a¯\r§#\0úQÆ<ñØ×Mn\roŞí Ê1ãŠ.ê)\"\"ÈS#	ÎdBŞmm#ò.§Ë×’:2H'-|Ø\0¨Ù+µ ñâØ#<yÒpØcw!q¤ğRÙr,r#(’|ad†j6EÆL\"¼ ’o*›r©’ğFªcfc‘–aÀ²ãê·J]Ãß&b‰\"ÃR†š`qÊQÄPÌRSq@ç’ŞT’E2ç –a `d|¬&82å8âP<ä°Xäîy.)1oct\r€V\rdB!ĞÆ1ï^Şã~;\0+¶é£\nI\\I…”½ ¨Àp}­X? Ş£ Î#³ŸBFtïçsù‚s6«¨„î–ÈfR9O0LÒ\"ò÷Ş;Îğ?\"º;£0ï\0Zj)*“<Óã9,†XEÜ»cDdv,Åµ;çĞ\nhn¼Lu)‡­&BÈ:Ì³Š!oÎÄÅ.S€ìlçFô“âıìJı\"2Áñ\"óÌÎÄoá>ƒ<làá,Óôóânïòo@DYA¯Ñ>¬\"%VD'7AlÏˆDÒ\"\n\njà²bu”6#ÅõLC:‰ÊúT»…ÜÃÏäDŠ&äÏq_BÍÔ@¦o‚O¤ÈÁíD2ä.=Äû>ä*Âc<ÕÃY&ü´c†A\"Æ\rä¾ãÁ ¨HjP\"\$æ;ƒ•D„H‚\r "; break;
		case "pl": $compressed = "%ÌÂ˜(®g9MÆ“(€àl4šÎ¢åŠ‚7ˆ!fSi½Š¼ˆÌ¢àQ4Âk9M¦a¸Â ;Ã\r†¸òmˆ‡‡D\"B¤dJs!I\n¨Ô0@i9#f©(@\nFC1 Ôl7AECÉÀò :ÇÏ'I¡Şk0‚ˆgªüºe³Çà¢ÔÅˆ™”„ù\$äy;Â¨Ğø‹\rfwS)3²	ˆ…1æêiˆËz=M0 Q\nkrÆÉ!Éc:šDCyÃªÏIÄ#,ĞädÃä›Ôá	³C¨A’2eÓ˜ÍF™á”Õ¡Ñšd…£	Â‘˜ÍB7N¯^ š‡“q×R äyW~çXçzæqµÜùu&îp7vúìÊ\n£šÂBBRî¶\rh0ò1!ƒô	È`ô—?(¢.ÇŒØÂ=%ísò1\n*7ŒCš.:ŒíJ4110¨CŞ÷®ëÄ›±.C(3£+Ôd==ã,2¸aÒö9¨J Ô°—Š:’¥©¢pê6ÈOT&ÿˆƒè—¹ŒŒZ­¶ªHŞ‘3£J\\92€P¦†&àP2ò è93`P¤ÖÃxÅ\r»bœ„‘PÄ²Lk8\$E¨¸ŞÎÃpëµ;ƒ`ß¹ã˜Òö?c4»Ët¨Ê9O`‚23L å\$]EN,f:>è›ŒŠO-²ËÔY!`@%ãCh3¡Ğ:ƒ€æáxïg…Ã\rI3HApŞ9ázmE£˜ïkŒxDŸ‡Ë±21¹Ê8Ü3Zé[”4‡xÂ(CzĞÖÓH§9£Xè:¡.€Êˆ.ŒÔˆâKm1ƒ8kxä9\r«Æ;½‚tÆ¯¶°È	êL†Œ¨ZÒŒ• Ô:9&Lñå9,÷“· P–7’š\\­«¹”ãËpp ©êVñ²0«~¶FJJ0IéŠ›'0Ú‹ijÒ8^Ã\n>Ã¨Ü5¹Oø‚„£8Ã°ÍCÕú×C8È=!‰ Ø˜¶³U¯µIXØç1ã&\r,ĞèÕÄñæŸ“\rşµ-ƒpÜšÍ¢ºT0ìËz/£:=>´qìE`ÚÆÿ˜¢&Cxè;°Ùô8÷AâÖâOcÔk=CF\ná8ƒœ@KPWÖ¯]ğÑàpbŸ¡Ö‹eóÜÀ\røÂàä,Ül^,k*Ï˜Š•Ğ2`\nû˜3kÛ™56©İ´rMt‡‘BHÛ¬i­H\"¥¦ñáu…œè`B‰©å¼4Ãbm„üĞê¥•øa\\è;‡#Ô¹8a\$@1—uâ¼É\"À\\Lğ†`ÒŠC‘©ƒàù#“_Lk}/P1š†\"`…Ì‚>\ré\0=\$(zçá u†Åğ8’vÃbs@ª}Ä´õRƒ12ÁI)B¥ÄÉÜ8©0Œ‘°êî‚ÂÎETÆ`•é7æ1v°çHØ C…í’ªV‚]Áp	:‘lÅÒÕ¼b€‚39ã6¯˜„c§>7¨RÙTv<qæ=¾úˆ¤D1®2HE'Ùˆ“’,‡9Cd„uLÍ*I÷<æS‹T%¤0’Fà]È‘LjqÈ—¤ÃûU¥HQ¥˜ğ°Â«c,…”³rĞZS9-e°¶ƒ`/8Ä¸:“&¸ñÆKg¬LÂ²HŸ•/áå³+Å|‡\"©çRÈÖF½BúÖ…ãpäá]‘r^È¹¹s2vÏà]fJÃX«d¬µš³ÍÔÓZ“Uk­•¶CÃ‰\rÊZo®0æ£!äqB¥å¼şa¸ÇÀĞÊOC\"5'êøÑø–Ë±8)„h—”–´ Šk’%m˜· èªQ+…§ª|åÂà \$-g’}5ä«İ\$ë<\$(—µ‰JÔÚy-%RòB˜\"&Ñé±bƒM¢\"æ¦ËÏŒ5•‘³>láÚA}éÔb‹µ¤e#•x‡ùƒ‚\n#ä„Íª!\\ZÄ82/T†Ï13`]­-Œ„ğRª*2®>ÂdüÑŠ a•”UÒ•¦›’ÁZ	‹{\rR‚¸Ú6q £42iD†=\"bQ\"_\$6º²û2ZÓU!¤ˆ54ø0ÅÃlr%Á©ğS»-›8j+ùÖ’û/iG ÈCIˆe·Á¦à‰ÖO³!)… AÂœ£<ó­^#…Ø pli…êz‘´cC[Ş2N>’rRŞ©`…¤È†)èa  .±`¼Âø–È‚¨k~tˆ&Îj•NfL¯—©ƒDãJ\r¡¬…œòzªq oÀê~`ÀôEj<–„fÍĞwdRÏ}HT\"e§Uk7!\"r\\ØtÌÇKa,'`\rœ<†¬#‡‚¾H\$äˆœÇL i¼\$;€ÂpsOé\nR¹¢ô‚¥ˆsL•R&ï(`m\$-šÙ:º\0Sô RşG’àÖÌB¼b-Ái¼ªvˆ6’+ºT‰Àö½ Ái	ÈlÀhÍŞ£‚•u¦)ª_\n\\ ‚E@C±ÀàÚ\raó§–Œü·»P\r1ÖÍ~§Ğ.7Ì‹ \r‚/MÒ%†^o`ÇjWò*ŒyÙ<ÉSâ/MS• Òõfl½›æ»uoJB[ŒA½t6bì²²úKkü‚…t…:R:F‘Í¬Ø;„‰ú‘•ÇhÄ’pßÀˆ(O¸mº5° µô\\†•9˜…CDÀÏúwÒê'†(8ìPÙ.26ïg 2\ryRİ€í=°ÒƒÒh'£ ê„-éÀ½—¯¼gtÚB3d@É9ê»¿ØŠş? ((u%Ôøñ£-2Ø†6ü(óšÃñ\rÁ‘0Ÿ§fº»!(;c\\Æ;Ú¢ÂwlÕšÓ^x4)zDŒí³3ÄŒ›ŞëÅèğâ¸\\–«ÀVOºë©×â›:âMÀä7W`3±[SAø¤‚B‘—±%à„eÚğRÊi×=Ş_(C‚Zk£sá*@‚ÂA¥¤l;±BŠhB‡”Ä:ÿdyû*å=•1•ÀËM™µ\\çè|ão½Î¯Ç¹aæ­ù‰cXHÜï`Ä.SGO¢Æ¤§Ùæ%­?Wå—á_j“æKõ?é+ß¯íıÒà¯â!ÄÊÏêüÏğ7+ĞÂ‹Vı¤(¸Î¨ûæª0!Oì—ö?ÈòâêÁ/ØûÜÁ†TøÏÖF\0Pÿ°?°DkA†\n¶5G\0pOÎ\\\r ÍÈâ0‚\$.‚Îbê‚ˆŒãˆz@RDâ°0pçã¨NbØ%c1\nĞÒ€È'`ÄBá2|^äš<bf#0ª1Ğ®;`Ş%®N§\$¼©ö~ãNo`=àò).lä/¤6ê®ÛÁ,\rÂ‹	&NĞæÏŠ°\"éx_Àê%€Òé£´\$BcNğî>Q+n>ÀPêKiı\r>S„aµj~%ìªz¯Ü\$²`q=‰zue®Áq2ÕMJa&æàæ\riü8Î4ifš&¨0UĞ6%£ş@&¼1äp'âæ\n˜h‹fs£üê‘Š=GÏXi±˜æà¯±pæà’ìnË¾şp·qD+®ĞL.~ ØG‚ş€ÚgFj\r£òKÚÖpÓÑlkÆêE®DBÛ±(Eñ,ÜL™mH%í¯µ‘>ü‚\\CHæÛ‘L,PAÑBÛE“RÏé\"'”xç™J°ô)Î€ª#‘\0h%¿\"ó\$24KR‘A%0ˆ°'ºŞƒËr*,Š\râÚ¬c\"ğ@şğû'½&æ\$‘ø`e'ƒ4ıĞ%ğ*ÿA²Š|!²•&r})ò*0ûdï-Å±#Å9\$Ã&NôNù%²Ú²ĞĞ’Õ,r[\$ÑRÅ‚HíÜ¸\$¸UÅĞRMJnFl»ÈvÌ\$¼H4l¢2­ä¨G‚x+ êWÆ°&\"6\rÄœ)DØ'Ìí æ§’ü`dÆ]3‚ŒZä6³\n¥­â#dpCÂXd„d.øËÂ›.C#,q5“?0ÒßèõcŸ4jX‹.ªaî¢bqKÒ-²ê¬êMK)	{10(SŸ9s.ÒQ ªxoaÆ êæ(ñ\0ò}È~‘ñ#-=Ç†ÌµRG²€Ü“Ş3ã!ÒNÁb›7Í…<¬²é×>¢ã\"1çA.ü!€ÆACÂ<æüè-	óü¯jºs\$†q,\0LÍ\0çòj{òq:RÍäc&Ò­91÷9r_4C(ÔU³ñ.ó›?ƒˆ|†¾œæa£E\0=“º§ÿGÍH¼ô{D@äS*lî<`à}\0Ö}C•F1+=põ\$ıJsd»S³!ñTœçÓK´­ Ò4mt¸}l—@'‹LtØ=‚¸ë:Œ™-ç.´Á/vasH-ÙJtïæiL³§ôMPo\rPÒ]!2ïÕKóó\"4Òğ¯\0=0/3ËKÎ†!6ò‘Q²áT4ò4õRraM/Tã#MÃT\$-d¾}³ş#e{\\,ôëQö\n¥{B#-Ò-WU„4GÕV%w'‡<?ô_E'¢8*N±|a£}ZĞ--óp(üÆ(´(ˆ{\\‚&æÕÌOõÂeUÆ,’Àƒ|'Gí^Jt[âcBÃÂÑƒÿ7Ò»\0Ò†R+Æ5*v\0¯›+ãªDæ\r€V\rbtÆ¢a‰ö¦‚*1*Z=\"z0\"nĞcZĞÿ^ÓP‚\npÚ¢-Àª\n€Œ p\$–cRÜ,ıç=*vjúr\nÅ¶p~lÿZ(óg¯®0ò\nG¶‚KhD!©\0QH®„H!3Z\"õÇBvpøñgÏcÇíXìºÂf¾\"cÒ]ö4Å¯èqj01nŸbúæî\\ÅĞ9íÔ\nîa`ÔÅfn«0AD‡ĞÚ-á@±Fov~U)8#ò)·•+ç4#j8Šssqè¢h:ÉAqÑ#rìÜ}sÃGt÷,€à<fõ·;W>;æ#8ÓÄ;g\$=æ×	ë—\r×\nŒ6´lçt„‘gd„ì‡c²TíÔäW‹\"PÒh°\rG|?ç\0Â¢_\0ãAPĞj\rmuQmCT¿#€¸tgâ\\é‡ìÔk|ÖÈÇq€Ò5M#hœVdÌ2„ôV­Õ}ã„ft:Çì%ÀÈvi‚:qìİ yë¢KÅ,?â6"; break;
		case "pt": $compressed = "%ÌÂ˜(œÃQ›Ä5H€ào9œØjÓ±”Ø 2›Æ“	ÈA\n3Lfƒ)¤äoŠ†i„Üh…XjÁ¤Û\n2H\$RI4* œÈR’4îK'¡£,Ôæt2ÊD\0¡€Äd3\rFÃqÀæTi‡œÄC,„ÜiœØhQÔèi6OFÉÊTe6ˆ\"”åP¹ÁD“qŒäe0œÌ´œ¤’m‡ßÌ,5=.Ç‹Èèñ¹ÌÃo;]2“yÈÒg4›Œ&È6Zši§ŞC	Š-‰”“MæCNf;ƒ7b´×h<&1N¨^púŠ|BRY7DƒV“\n8i£fÃ)œËb:[NLş,èhØlö½ÉIëò]½ßìbøo7[ÍŞøõìÊŞ2ŠXùO‹ìÔ¸I2>·\$àP¦êµ#8\"®#kRß‰-àŞ–B«<»\n£pÖ7\rã¸ÜŒI8ä”ˆŒûjÄ±iË¾ëˆ ê821¨àÄc»J2%\"ƒJÎ¬†:±A\0ê–¬lK¨8&k¨*\"Œ„Rfã¶\n‚Rƒ—CjüˆQkhû&Âk¢K5ˆMJfÓ\ràP¤ÿŒ°Ë\rŒ££M5·O\n<²`ËÉ«L¿#kª\r>Qrà¹.-—Î=òQB+ó5.ÂjğÒÒêŒº€P‚2BÃrü±ñœIq¼r½kê#¯Rkh1Pã¸Òóº1ª÷¢1ô\nâö40ã0z\r è8aĞ^öè\\ºU«ğ\\ÒŒáz2Ùì~„J|›°áR34©jŞüxÂ%\"š,2ŒcKğúÓ“#t§ÔÆ·³Pİ7ò<ïÏØfˆ7©ê©óXœ7ÈTîæ< P®¬ãpÎ‚„£ @1*€–åù‹¿6\rã`êÕ+è~q1?3à0ËÂ2ğ„\nİ\r¦kxÇ:ˆ‹(Â:‘«CO7ó¹€àk°À2šU;n(\$ãß/xÙ¶Õã`Ş1µ`PŸ£-úÉ¢këó<‰‰µ‡<¥ij^œ!J€ñ’ˆ0\nkãb/_»ˆ&è7gérr\nbˆ˜6®3¿İã;¢=CI4İ*‚ã–¸C‚ÿ‰}¤Ö\"÷#ªğ½ĞdcõàVĞ4»hcQ2m›ş›hó%ı¶˜eS\n ¢**üÌŒ|rßˆ£Ç¾»vÈâ=_¶ó(…	÷½øåË³\"‚\r²Dkg]Ì8é†ÄérôŠCy_+ì”¿€æ‚ø\$.ÃÀ0|c’'J©”Ø³üÒyNVçæSR€s`G”¸:AZ©v:H˜°Şƒ1qUÄ¤*åZ}LQ@AP7“\\RQÁªäï‡0ÌÕr5f'è€C8a50D 2åZ[ƒ((`¥~ÄS,T”7<‡Ü¼«UtÉA*:ˆˆ±/B<’â«\\!Éf4ó‚ZzÎ:kEi­U®¶VÚİë}V•Ä¹0nääÕ/yôŒ3èFEØtqŠhp\$Æ‰fˆ‘˜šŸxg]›“r<YLºL’cş–jÏ‹Qk-…´·òàÈ®D.x@Faø]‹¹ïÆ€ tLK—4‚ZÌZÃ!ĞØ—4rRK9\"MˆË‘RòÅ¬“4&65°Âäp XNI—˜²Ö£rbeÁ„3JˆÍâ9‰QMasfI!,1m²’8'!Å!A¡-Ëô¢ãÂr¨Io'Pé¶ŒÂ€H\nÙïBÄ\n\n)aAÑ-›ÀæCË9\r„¦†å]:ãH;/ ¸ÓşC(t,G¥Y—§\$‰eyƒ ¥¼ß8âjC±3.'’EâzKİ'œóEz¨t©Y(şu‚Fı\r:Õ³şµ3\r|ĞDá)… jH¡©d )â6}Iõj„uJJßÉHQ=ôN²dM	³À¬¦@ÎNô,[ËIÇ/¦ğ´Ë„G’+!?©-£ÁyÈHy:%Œ´#EN¦iè,F¸ôˆIËÌr—u‘†6ä’O9éGgy „ğ¦õŠ%°‰J‚\0Îš­AQ	Ü2RæGÏS†&,½YÆş¯Mãúy(û†èÚê1Œ9öË¤ àÜ‹¢½FÎ9ä‰MAw*Q·4¼¬a^HÔ3[Nˆª+M‡PŠ–&)rô› ­=ÉI3ğİ‚p \n¡@\"¨p€ &\\.£ÓM>ìé1üB¡bŠe ×\nK‰ƒv\r™±›Æ¸jˆñÙbÌš“wO€eJj/–'ªôw–\0i	Õ;“|ˆ`´ìT¢Ğhò‚0,NºÈ‘gj¨ô]kpŒúå¸™.eSOEBdÎ¡ËÎ\")+WÂfEMƒì6„äbüD1AP@Ï¦ª’*rÙşqs\0ËQš6P@SŠ.m§“}¤û5j•œÃ&e\\y+6Úx‚…2ä`I‹\n-­•\0È•O	½72ëÎbYo¦`\0èûÕpÊÄæSF\n©Ùq¡ß>›İ§5é0•9aÆÛ`†µ‰›»4GˆÛWMØÓàÚ™X¡©N»yÕ*¬m™ğCÀ†/’ìÃ·“\0\rdÛ‚ _	 c\rd«ràåIğ6|¿\r©«™«ÆšŠ¸üÄ“SÀĞy<Az™ö ñsLo-åÄ¹¨ì,C	\0¸dgm2µGæ „TRBUİV­U5³@^TÙ£ğ9¬Í—t#Ã¿Ç;Lå5€é9AF!SÑ>©P\\ioJÑ]7=†™ĞNëñ,]S=œâ\rD‹Î•sG`õ¶Ôm04İh‚/Ó;y¢>è§ªÌr£Ñ9ÓT6“g»~¸h‹Oï†Ó¿—ÃáË³±ıEW½NO©™'ˆ4\"£„£T±E3IaÍùòĞÃ¸eF1	‡!{ı\rÅ,\$Ù7HĞ¢ò\r`éú'Ë¯îS HK¸ìÓ4ı\"M²:7‹ÉkHSªØŸ9ø¿GPo¾›#~/ÍïO\r\r©·#G0e£§¾³cU0•~ĞaˆçìÄÏõj4ÖMÔ}5f•”ğŒÄL€ˆ\$àà1ƒöfœáC\"xÂ:-ìhÄéÊÔêÎ¦ä0â\nÛ-ÄFbnıFè²¦z'Ü,lO ‚\r€ÎTƒÎÜèg@Ú8 ñŒ`N¯æg\r¶‘Ì²u¬Ø3Œ¼ŒL°ÌĞvµb2OƒD%0€Q#8ş‹)FÊoÒu0–wƒ¯¤0¤wÄ™	O§\nç	ï°-ä%ƒ~ë/íÎœ<¾è®Â9¢ôÎªØ°é0ÊğÎó\r êNÇ\rÎÊœÂ:ÍêeB?ÏÉ	púŞ†ŞÑÏÖË\n¤l0´Ë	°ÄfvIÅTƒ¼Dç†)íÔ›£d8E`~ƒ¾&ãf,\0ŞY‚ÉÃAD–!a[b0”Bôñ&s¬·cô)‹ êN¢,¢ü#&¸±RŞ‘VÀQíîMoÎ»‚É„Htğ<7æÈÆãêÁ‘*Ù¦;P>Ë\0¬K±Ëğ—±³*XvëãÄ&{f‘\nŒÁ\nÑÔ&/®ıqÜz\"øOï¦İƒê{G¢M‘ÍG¸8&k‡ &ÄØ4Gœ\"Ò«Îê¢ìy«&z¸üqÀúr yé£\"ÆÓ#éHÓ<kâ•‘¡\ns#£y#1ø|ÄˆlLä\"%R\$d ©Åjä†Pƒ—ĞQg¨zÑƒ\"|‹²‚zâ÷’€§«)µ	rDTò2Z‡\0Ë*g±2‰\n¨i'@71âuQJgÒd-òÈà Ö:dœ6Â2,p-d€Àmõ-BÅ-‡új±.-òàRè²Úh£Ë.0N•4àN4î\r+qÚ†#Ğ;\n¦\n“¯ã\$2Â>³%1n3 ÎàÂî±‘Ó`ªäe(.³êÄ;4ÎJHsâS4¥'5¢=+à	\rì\rãôMjZ²n{Ç.ÆhÎ(Îq(m+8ÈŠ3\n°ønLpFhDƒg7†êöc~f‡/.ÈêÏ\0pcƒ\r°ŞìÀ†OÀØkl\r&à¾ ÊDf\0Ä^\"'Lrí¢0ù`Â¦äJÇ*~«\\6È¨\n ¨ÀZbiãxeBRì¿\n:% ó@¡B!¢Â„JTTìÆk¥{rNÀòÖ\r=ó^ÛóW6h\\âÄE)óîÈCVŒ:„­hŠÏø¸-#Fä\"xÂf‰dÖatj¢lÉƒ¿­(Cr&ÏÜIÆ*0¯\0QIæè*¸UBÅhÚ4¢XúÅ7ÚƒdfŞrü¶ Ş“JƒgĞÔ„‰K‰¿lÃL#¸,TÉ.Â>ŞBÓá.4´>Æ8ğn\$w¨*Pg^)\$KGNÿ,^pì¤xµºe	¸}„v2‚p ì0ï¨9¢\nÎ¦ÔIfíO-8ÏÃLbET5NH7äîÈã2Fvİ£tÈŠÈPÊlŠ|”ÏD¦@/Àî4Â UQ2ãa†§Kƒ/9ïÊtRM@"; break;
		case "pt-br": $compressed = "%ÌÂ˜(œÃQ›Ä5H€ào9œØjÓ±”Ø 2›Æ“	ÈA\nœN¦“±¼\\\n*M¦q¢ma¨O“l(É 9H¤£”äm4\r3x\\4Js!IÈ3™”@n„BŒ³3™ĞË'†Ìh5\rÇ™A¦s¦cIº‡‡E¡GS™Öbr4›ÁEcy†ªU¢ú¬z0ÁD“qŒäe0œÌ¢\n<œ’m‡œ†£iÉÈi·QÌÂb4›(&!†No¼í¦d?S4ÕL¸<ÙŠ-‘“‘Lš³–,İ’¼q`ğ˜ÅS Çìª§(„œ²o:ˆ\r­>yx›¦s- és8kjØFç§ñIÊ{C´tó6}cÙ3¼Ü¡\rÃª:Œ8lØÜ›¾ï¤É­®@Ò;£©£cpÎˆ°ÊÍ¸¢K†7¥`PªÓ8¢¨Ü5Ãxî7#“¨9\$â ÂŞ²,šnò»Ã¢:Ìt82#˜îØ‰8 Ø3¹\$.Œ˜ê•­,‹š«8æk2 R–)ÃRbæ·*²Nƒ—Cj|ˆC˜ÂŒ;M€ÂûÂKhÎ‚ˆKjî¼BÊµB8ËCkÄŞ)Ì-İŸ#i¼JºÎëĞ¦Œ»`P…´Šn‚ˆ¯øäû	ƒ{e'©úXŒªÒĞÈ \rÉòÒ7ÇLtºÇ­„t0Œl*#9[ÔÓÒƒº‚—µš ÈcÈäÖ41ã0z\r è8aĞ^öÈ\\ÖU	ğ\\Øáz2ÈŠ2á&¬x@¶ŒÍ‚V¡?Áà^0‡Ï”41¯#tÖÒàPšˆBP[§`´Ü´6*ÔÛ÷ƒ˜LŞ á‰ô¢HJ7©ÓÓ¥	/Bº:·BÈ(J2ò«9>S•¼ P†7ƒ­ü²¡ù•\0ÿÑ®3İÔˆPƒ`Ä	Š„şÃXÂ:áëšŒ¯½÷~¾Â6lÎÊÙˆ‚áëº\n(:ƒ\$â¤M¢EU\r‹ëh	ö22û-YøæÂ¿úˆ˜š(:ŠR•¥©³&«<µ¬cZ°Ø‹·¯1¥™Öø¢\nbˆ˜4îHÓ?/¶)†Àt›%JàG7‹aû<B!Ğ](ëCB(çÓWLŸ=FÍdÑ°\$JÙMß\0002:’D¿±¨å0Š¿óC/>Â(ñæ8½B87­ü0!u½W²Oøÿ>Ê8@6®‘Õ•u±øˆå%•Ì\\07µğ“üã˜}™Ë`Ï„Œ¿|eAm€ÄÜŸ&ø’CoN*çÄTÒ s_¡Ì“·rªğXaÅ:çd™0Şƒ1ªU'È0 òPÉy, ›UjyC˜fi€´©ğæ²”+!¢ÈË•BÁ  9‚˜HDÍk)7hìÂœ3ZIÂ¡ÛDå¡x‘âè®ÚİK şœr†ª–QÚY«=h­5ªµÖÈw[jœ¶­åÀ¸ƒp/&ëùzGD£š3NOù}nóĞy¬+ Ì¤”–G¡««®}ŸTÌİI8M2,5a®dˆsC‚O`¹d¬¸Æ´’ÔZËam-ÈÚŒ#zã‚f	åÒºŞYâ‘áÑÿ@Æ†És(<ïŒ0†´Éó|T‡É+#BA!ƒY-¨îT8v9©5dŞ(ƒBã¨ WÎ%åS3(!šJBåmaœ5€Jø:ƒØdÍ2f0†<µò8 ¡¶9¡/Ê¸¼ˆ2Iêé)Oâ\$×\0P	A¼ÃZZAG\$`ª¥ó†ÈyP\"„X“¿…S6Ä»€Ô4öC•”-\n¹\\¸tU&Ì\\õ(E±Æ8B[C±15Q<”MeÜ{“]Vˆ1NÚˆ•HR\\;ĞÇ;Š\0gZ3v£F–d¢€o—È°!…0¤—y§E¾N)’6€ÈTàiŠ4ïHåHUÀPQ@SÙ€2`L‰£±ªÆ`¸*€P”¹Í‰¥¾\nP”’‰)irÎÏ’@ÃÉØ-%\rÌ„{qlåU\$Ce<ïE´¹ùÚ{’äHÁ<)…D¢fÍ+‚NØu6Ê¸ OA:˜‚>{Ûá.#ğq'ÖõsdßI\$¯Quı1*m\0ÀTªÈ±6“YqÍ@gD&\$šJÓˆà ÁR†ÆPMòïGAšÏL€äEQƒ2;dT´Lëny\$	?®=P\0\0U\n …@ŠÛ0\0D¡0\"àf7<¢MûfÉ¼'(*èÃI™‚TK	sl\ru­7¾¿ˆõ#bÌ™“VÇR´¯8¾ø+Å	CÁzODÖø)%(Z”±p§@ìİ¶Š¼4«›S —ÓŞj„{\$dBA^“ÇîÕ€Áka)}†	 +ĞÒLH©¹{&mb|ÆHQGRÙ©ºr*fZk Æ~…PÂ”ì<(‡ôšç<şX‰´\r&!è³C8g˜Ñ)7Á±à¨@ôÔY™˜ˆMIï`È–²yÅS¦²“‡k\rœ»?•„¸:=—aXiÈ¡§d™#2}=ô]'#a”;—³rTpö“Mæ2Ÿ©g€PÃ¨4«fçñÙ›9¶†“ÈZIU-¥éÑ°åM³r˜ wÌÉºæ\n…ƒ,¾A”1†¼°cõ±„šÛ´”Èˆ+[Üêob|q¶.eà((LFá¡m\rh&\$&ğŒ¼‘™zFL,‘äF]…	xÛP*†g/âÙU`©€EEÄ)Cü]XboeÀ¼¬²ã¢˜N£-eÜé¦‡ñÌ-Sİ‹A,lxùÁ ÈmÛàÏÚgA6H3)ÎúG=-¦)tÇ@Œ„¼Äğç®¦Øá'eÑw TâEúh\\¹tËÓ»_/ê)I×÷ÏÑ(t}\$ª·œş‡4xGGP;W–¬¢'iia™¤ªïr‰6	h¦¿0r<Ñá”1@[áĞ[4|²Ÿ¾Âd[ò-Blé 0æÎÏY>:„\$&V^Cw35ÒÓjí×Yd	'&Å›5§Ècİ'ædÔf€Qˆš³ä±ó¨ø—MAºßíäm\$oş©5J£(yOÏ¥?I´s™~ô×øg™§˜W&·ªÒ€&£Î8¬Êm#-€€ä™D0:€à€Ã,Ää3/XòÌH ¯øvª#¢„¦Âö6ƒ`f -\r”ÚdpŠ¢T¥¯æ:¦pö-¤ÄÆ¢b€Î6~Ûff Ú8à3k‚/P6gM˜¯(Ê¬¸.ğ,şŒÈ,–É£E\$Ìı¬	¬®ı#ÿC]¬·	Ïâb¥6§uÍ_	0–ú A‡¶şï›.šûÔPĞ¿ª:;bV8§|xšêŠêã ùC¨W.–ú#gnä5®Œë¼.¸ú	òÈÍÆ;Êö­Ğ/ã	p·MÉ\r1,¿7f,ş‘&;­Ì¾íÓ/ñ\rÌŠ-\$_É–ƒÃb#\$Ø4¤\0oÂ*0cóÌbj0\"Ì\rå™ƒ&7\r´ŠÃ-›¥441\\fŒ'#@ÛDRM‚ç–Y¢|#%ø¶‘ˆL±)ÍÏŞşÇ@™Í¶Åã†¦‡4ÄÍÃæ&\0¬×ã™	0Á\n‘éÊÒù°”‡qõ¬á§*¼á—pÍ ·r¯­2\r2# ôÖ†­š@`‹\"&du2,h#Œ\"g¾@Œ&†dD´!MÔÎ˜Å0Q4·GòĞQ7&Ğõç'qå±W ±7\"<†B Ò\r¹(Âl\$ö&’I ò‘Ì+–bô\n‘‚R¦I€}#`E/(Qç†G‹\r­B”»`Ë,²Ï#õ,‚í,Ñ»PÃ(­ ™İ²Bzrõ.rÎû’´!ñ3!ĞÑ0r\$ÿ0ŞÛ¤(Fs ó/MÆİ€ÖšĞ7Ñi@ŞGªa2mÛ2È•ådŠİs>;s.±hgím4­ºŞS)0Mı(ñıâNù\0©6rí\róq7R‡#\"•)“rÚÍîÛğ(#Ó†óŠ\nƒvg2?\$âêÙ'3xÀ%ã\"ó7r+:S±11XÖ€2 ĞO2nfçğ‰­şyr8¦]*ÌÆı&ı‰¨çRšÌB#	Ü7‰‚6n¼fFÒÅ\0eÑª<ã66°\$|+Î9ğ“n›>†ò8ï˜Scì”£¤\$\r€V´€Òlâ\ràÄ]Â+¬Rì¤5`Zm\"O%'|‹87ÊÂÀ ¨ÀZ01 Ş8d,íQúğ½!>n¬—BEŞ¯ƒ5B©ô!B¥ü£b.{­ØÜ\$ÖşÑØ\r Ì\$ò7ğ2Rñ\0ª(O²ƒÎDôJ™ä\"k#‡aã×DH*\rë®]§j3mNpBà &%>Má†F&Œt<®öĞdA&\"ĞşÔ‡d\$fÇ¡â•Q#N7õ0lx(c€Ø#Ä-;3G3`Ò¦şµ&Ò•*ıFğ_Õ!SDqS³TE“6(B€ÒóKQDÙ PBéMp½HØB\n`pÃĞ~É&olA[²H:!FîÃK4…&ÄIæÙVM>ÌÜ/Ü8 ‚_ÃTD”³Lãí75>ÆÆµÌ¸f\nÈğºÊìhßæf°Æ\0äâğT ‚<„b]\"Õ>3øMèvMÀ"; break;
		case "ro": $compressed = "%ÌÂ˜(œuM¢Ôé0ÕÆãr1˜DcK!2i2œ¦Èa–	!;HEÀ¢4v?!‘ˆ\r¦Á¦a2M'1\0´@%9“åd”ætË¤!ºešÑâÒ±`(`1ÆƒQ°Üp9\r0Ó‘¤@d“C&ÃIèÂt7ÙAE3©¸èed&ìÇ3IœêrE…#ğQ&(r2˜Nrj­†ŠE£Dj9¥™M—î 4İ¤'©İLq¾èL&ÀV<Ü 1mÖy1’ß&§A.´ƒ¡„Åš2ÊÈ¦CMßeÂ›±yS×\"º»Dbg3–Bi•–MğƒA†SM7Ã,§kY”ÏF\\S†Û>t4Nı;ãgç«”ñĞsgçšA„À@1ë³B:¢ÌëˆŞ²¯ãıÀIšÌĞ¹lKşû¼ƒpÎÂî9<àP˜›6 P‚úÄ\"¨Ü5Ãxî×¤#’â•ˆ‹{|Å6£{Ñ©›Ø8.*@àÅcºâ2%b0ŞÍ¯‹ÊÌº£N#^¿¨²àÊ8èğ»¹CJÌÆ¥ŠR`’*	Øé­PÂ• òª.F­ì½)²ÖC£JD²¶(0ŒpøÊ°¬mƒT9B’ğM± P2ãhÊ::ËœÌ2«šP Ã­%>­KbÜ¸Qƒ)%Œ P„ÙJ-«näÒ@˜7ŒòÂJ9&cpÊ»9\nrĞ¤+<tGĞá JE\0¢kä4„¬ñ³bÅ„»Š4;vR\"HÃÈâ4410z\r è8aĞ^÷ˆ\\œ'UĞä.#8_]…ò›nŒxD Ãjâ“4#2â™ƒL\nã|•ŠnW£’ß¨é¿Õ\"hŞâ#ˆ¬\0Ş·ãbFdY Ó“@àÄUÜFµÎ£¬Ä<Cö+²ƒsB3 ¡(È\rø¸èèÚF/'B–7Œ#s±#›2ƒHë¨±õ(u#¿b Ê3l£b;5c­Š¾¸»ô–.£œ4ÄÃ;Z±Š,‡K\r:Êº­œƒÂ­Ò“T‘lâÄK¨Î›1/J ‡å[â‰'.´Œ‘¤™Cê‚##ræ(‰\0ã¹CÈÆŒo9¿Ã÷–9,Ø¶kE¢»å™{—ã­1TˆndğºƒE0ä\n~RfØ6C*Íá=Ô ñ¼¼¾ùïé#(È¿¬ĞÍ¼ïh#ñW˜E^*1¥#rò2˜EØ\"ş¼ÃDaCjÈLçì\"‡ˆØÑóe)À!7¬ªL[u8È\\  êQ×a`† ‡‡#^ÕHHrcAåˆ1\"V¸˜#R\rÄp¿ˆJ‰ù<40Å³èc	áœWpŞ´‚™\\Ú»/ˆ9’´FQÔnQh„+`Şƒ1lWD¬\$—‚>àB o<*ä<³”CÌo¬’·.r—”‹¡…ZÃ8fÓW©  9‚’†“V8¨„#èÂQ‰XT;¨¬0¢™Ú WËÔ®—KAL\\@r6µÎºWZí]ëÄ;¯5~½—Â _a¸†D\n[Øt¤@úSéP†¡|NÄÄÅ—Âê—–P	è·®dÈ\"	È&G†UcÂRg¤¬&˜¤>¶×úGKRìÄ\$ˆ3%,—]K±w/ä½÷_2}¥¸…*X	0ú©\"íáê’>ŒôÅ°ÂØBB\$*å›1BŒ×š§R”1RA0P\$Ë!¡yôˆJ•+Tµ’C~­ä2’há„3Løı£  ŒĞÍkÓDÂŸ:¯™›“WTé‰	i.ÅsÌñ&;Ô¸1´Ğƒf<1.’d}\0›@ ;5 Ü®¨’Ï=LÌ4DPùˆbA†K7¬ ØfÃ»æMŠy,¼%B[Kyœw!!£ ØF©È oÑêĞòBÂ	ái¢š^|@n!©	nM î|]©2/«¤Øk\0Ÿ ¹öaL)ië>\rzv\råò†À\\R”rf~Œ¦µ:›‰\$&dÔ…¼\"\$r©K{dí 4+;Nˆİ›YL4åYùš‹ŒÈ/PĞÂ0òvË8ihëÅ†æ|‘ˆuæx£\"\"ç\r'Wh\r&XãâOç™…0†¢bxS\n”ÈÏ³72LÌEŸ\n…”xğ„ˆ`uIÊéáÔfÊLŒ‘¸u´…);R€Ğ,,d	9í\\“D‰J_¦1?%0àfÉÂĞ§³\0)ºÂ’éÏ!ÁP(›œh \nA4 3]bN‰J÷j/H³D< Ğ%oIÒ„Â4Á™qp8ù9¡óÎà[QN'FWa…tßjYÀ“ÊÔĞg*†œÂ&oEéĞ¢clC:	µì™–æakÍÑ	¹›10Ap*ÈÊ\rUŸôúZ\r½~n°2lëYUqŸ¿ì*6\"BbÊW{@¨7›^­¿®H£‘¦fù`‰qAY®›ãÊ\\Ù¾›ĞôÌ”\0uCOœÉt\0‘‰J&…Jôı©õ‰€(Ê½¥gtÖ±ÑzÇŠ*ã€¥™«Z€1ÉMA'Ì‡ñ!D*ÌœUŒ/˜ \nqÑ	lHô“u~@˜>º²ÃnYMYm«2‡uos5.ÎbÀ³ô‹(t·Xä–€ğú.AA&‡pèƒ›Ô@eŞ;ãö¯›.a@*ƒãf¬~ÂÒa¬‚èüòò¸Úp˜Õ½û	šÒáx†`A6ˆ3äz”Z\$#Õ¤9““¾-XNÉ_€„ûj¬Øä'jÂ#ÓIJà·§_Ç³µgTzh …@¨BHuÎÀW–ysúA¬J9°‰“3i¯ºnÄ4Ğ^;kGµìèÌ´Îätí‰×AEØEÒ¡1œ t\\\r7¹÷–y9® '¾ü<½ğDÿ»ÛvY¼C%6õŒè„	;\"}ïú§É¶›Úü‘-à»»u¿_BZõFT›¨ÿL /‘VV˜¢â\nk²02ä¾Ôò\"K‹ñŠa3	ô§=Ï¤A?¥©¦‘{´5\"#UÌvv3Re1”®]”Í7À’QÍCÌ/äX¼œƒ‡ÎOÔõÛÆ¹¿¦f4ÏµºÈ¦'„oè<ÏìVÏòƒ^E£ŠÿÃ ş¦:K4¯+rƒ(\$&Ğ-ŒeBI\0bĞÎ†R&00&GŒó@éƒ5ĞóÃ+nDÉDôs&Q§:Fk-8Ã˜4,¥ÎĞm\$db0t­§ˆ~g@hPVSĞZ¯I¤¼ELcL¨kãà¢ÊtíÿO2eï6P„î¯Î\$NãP>2jFT\r£”FTË\$U\"Ä†T©Ä•%TUŒîÏ0>ĞÃ(çâVÎ­\${¥ Ğ‚š¥w	\rËĞîĞPN@pRÏĞì{ñp²ñCLz­Àl‘Oúf(ñÿJWm¾yq>ƒ'@‚è#ênàƒoFğ\$ÒâŠ|æ4ğo*îŠï¢‘ï±öOH©1d|ÆAÃ¤òËd!\"ñx²Lh®	ÌƒÄ!28Ñ6%n2{Q Cî/P@È1E*\\¦‚C°?-ËÆ¨WP¬)Š ¢÷`ÒQ¦(±V¡>,Ğfö1 .èÒ1à¨ÄLªg„\\£®\rÁŠd N‚ëÂ øìsc4 [!mh4ÑÄ\$Î,jğ*¯Nà/W¬¾âL›cQMâkŒ™ñx4ÒTf’Xs1©ĞøİòVŞr[qÑ#Gœ@e&RtPgøWgüs‘½Op©İ)]0µRŠhR›Ñ~'V°Tá§@\0Ë)†l+òŒCZ2Š°bæÁ†@ä3\r¼%©Œp ¦w/â|ààúppw|‘fd“¯ù&òı†5Ò_œFRÿ0ñÏ³+„}‚ôd2 f‡‡™'â72‚@ã3/Ö?f°&(8/î.,¦ø~#~…v/ Â3&ÃM5‰Œ~³a6S1-Ë6Ó\\~Á76!w1/S\$A3}76\"Çó4í93_9a9¢Ä,r¬ÿR•9i—\$NN¦²êVP°ÆiŒ\ri(1K»`É®‚IÌãsÒ'ƒ~»cVPSá3ä;³è«·ÆDéä\0Ö,\"Êå¯™&­4Èó¯Ó‹Kæ/´òy)™2îWA¥9<FOC…,HrÀ.À\n¦TNiAğ30P7DôR­IE‘¾ñ/WF×FBãF‘Ğ%`1G¤“|±h9\0 ŸnrVFeåÏôCåêÖÆ›hŠ ƒtŸI	\\3ô§1‚Œ²C6WFî5Ïä5o(,4´W±%B\$ğğgcƒJÔ±BÁK‘nğÃ°j%(\r€Væ&_=œ<,öj†xè\"ì\$E ‹sŠI#Ê‚È0¢èf\n ¨ÀZ:\"‡ŒCOLhàäµæ³E’õo[S¢'„&Å5B¡Êb!Kò!äzbÄå ÈgãXá§dQMƒ	§Ì8§µ¡f¨´1M&‰«ÍFj,lÂœs @~1'9vˆÆ¨hægƒÜU-¨JD6TÈÎU\"Fa¢f,*ºf ÊU¾-æVŞ£\nÔæóÓG6U]Cë]‘\$ğ#í^D³\nè0áj]^0+_5Úÿn^èn'ƒº;óê5àŞÀİSá_æ,Òƒ\\®ˆ\"æøfÊ€qMk¢j¦òG3­dx8Äúà–#âæHg@¤QæS†sO\r6P\0êSÀ	örfjnèTR„–×à‚•ÅD×òT2ƒuß:FX!ÎÒ!&ÜwòîÑ¶š€f´—fî\rìbíò{É\nÎ¯éf\$R1L	\0@š	 t\n`¦"; break;
		case "ru": $compressed = "%ÌÂ˜) h-D\rAhĞX4móEÑFxƒAfÑ@C#mÃE…¡#«˜”i{… a2‚Êf€A“‘ÕÔZHĞ^GWq†‚õ¢‹h.ahêŞhµh¢)-I¥ÓhyL®%0q ‚)Ì…9h(§‘HôR»–DÖèLÆÑDÌâè)¬Š ‚œˆCˆÈf4†ãÌÔ¸h/èñ¥ı²†Œ‘¯¦±	4&¾µ¤Á’Y9Ú¡LĞQ‘cğQ\$Üc9L'3-çhKÇcòlqu0hÊ®üÒÊésŸiózxÔr#’Ô^3Òõ…¢KBÛ!ú­A%XÖ¡Pèì¿TÑBİ/ğ»äGÃ’¡­\nô>#=¾Iiœ\\äÑ\"Ìê\"›\$¯ò„=i’9*JĞQ£I±`‹=I3(š@n:4Í<){ø‰µ)úh‘¬ë4¥@FƒßÊ:ĞP¢D0ªÀ¨Â\r\"¤,f—Æ¨ÊI¿o#4‰–Ğcü¬´± üA’%!1¼c)ˆŒx“%úú½£°\$±*J§)G1Û§Fìë”¿ÆÆ^ªåÔ\0¤0Ä¿³Ì‚8Ó@+ã¨h‘ğÚ¢Ÿ¼-ûªƒ*ˆ‹œIO2ô=L£´ò9R+!'Á,¬' ²ëA0Ÿ2²ˆ§!¥ÌÕ\r¼ò5=!q+óHNÈ¹&Ìì‚”²­\"¼]¡hl‹¢ä¤¨K…’ü“-<™.H1(ş¦ÕŒÔ(Ò“È-ùqg3lÒJCªÊDPü²2”Øÿš›<Í(3b˜’‘öähH¬ÔähFTø@ ÄÅ«@²,µF¶BSR%kû9NM´LF<·ĞÑ-•3òƒÓìºş£\0ã'Î¾\rvBÈ6#pÊ9R84‡¡ôªLBÒú‘èÑtğ<X„­‰=1S<[ÒTŞG…\0x0„Cb3¡Ğ:ƒ€æáxï­…ÃO”åapŞ9áxÊ7ãƒf9ûÈ„K(}¡TDeñ²\n2TŠ©Š&C'åÀxŒ!òĞÑÎ“´\r„­”4/®ÖÂDü.íåú‘İ±§!ÅoŠË£œÌ¯#²*z@lü ZÏä'€9ØEtı1iœtÒ.cÍ^!(É.	0ßx\nLá÷ú‚¾8¨5BÏ±Éù~ƒKbƒÑ@I¥lƒï˜­Âu}ÁbIkcÆèVšó²a ÜÏÿWIÕê·Q\\úJGNqW›E!\"%äõÀ¶4é»3ÂÌÎ@VÂ–ñ>^çƒˆæèPÒícO¹:~˜#]\n…û'R“Ò»ülŸ”\n·U9ğĞÔ¸,á TË4@ˆdWÕc.+èx¬9È€4Qh‹AL(„ÀZ‚)û\"ÄŠ—ÂzHDZw'É•*¹§¿\nµr)86|UO8…tmö9³°g…ãŸ ğ…×—c²hòiïâ”áÔtYN1!•UØŒWğ Â•3âR94\"®\$¹S&ÎIq!À€¡„*ä!>D	Í¾<eä3]¦GıUzñø ª< ˆ0­“dÊ[p¾a>Îlö´#áøDQê8±œJUNå•ˆHTŠuPanG,še„éò&J]Á¸RrÒæÙBeîI\$b19Añ¶B&Ø´1˜­FŒ‰TóPŸŸ\"ùJŸ)º«#jŸ†B+„¤eN»å[a°:\$æËf„B\"hò !Ğvœ9WbÄ‘)Lufo×dÅµú•R Cfà/ÑÒËGË/\"\0¸A¢[ª¥Täú\"!\"øİ3Dx…m¼—rN Áp	˜™òÒ’· e-%ô¼ò!:d£ÚÏ2TÙ{S…&uiél}p‘R5Jê5Mdl²<˜\\U¦ú.ªHÂªRˆ1UËeYB´ÀÎUùM+&IušV‰	OëeB†„Æ¸\nµ*Uv'óydQrï/	š³UQ	mWŠ:È`ÚÕª<ÎN6èsVyò :çæÉ™C*M&ƒ\"ƒ^Ò§[®`¶Mtc;ËKi­=¨µ6ªÕÚË[íu¯Û¦ÄÙ3h¼7ènãpno=_Ë{‡\n`¾v³µ(ñ¡âğ²‹¥¦DIÊ ºi6šã?Z‰'ú'ƒóÎÔ%ÈğCA;i¨Dõ5;Lº«f÷æ\\¦œZƒRjY¬5¦¸×­Ëalm•³¶`ğ8s¼\r¡¸‡6æ}\r†*‘ãCIáJ‹ùÚ6ÖğCØ¨MeÙrŒf‚Á2æ4ED|h*âñ—«ê.&©_šò^F2loè\rr\\%¡gÃ¦4¼ÏARŒÖŒÛ:]!Ÿ¤¢ «\nĞE`Kq}§5wN;Sc2dii_«ÚägNä.†¦„…¨2³¡	@\\§„“qÒÁ\r2¶*ƒ·œ/ôÒVJÒ„ªºé™7^ŒÌà{\n()-ø‡å£@Ÿ•<w¹ô)yûÕ¥¶5ş^¢‘xËHyš2Y±ò‘ØEÄ~†3Ï¡ÔĞÂ‰Âÿ¦–Ò:0b†À(bZÁì0F8yşi¯|D×ì£HSÇ}%khF€]ÛÅ]œmş£A9WtÜ*DvÊÑùzøímŸH\0C\naH#ÛoÍM<ÚŸìÚ†ŞÉ¦x#I„©••—4‚ä–qæR£ï}[ó3BØ?j]j¬G©ğ¹³iÙ¤šÓ3Âç…\n3—Ê˜í“¡Ã@Jæ¬ _cı.Í\$\\¤+O»â­°9òI\nö‰n»éRm Öå[¤\\Œ™®© ÊNõ™ãuPhT'Q\0P	áL*DõĞ8TPsÉÆÂ¬İ´QX¨DE:ÿÛé·Ê–S Ìzİ»®Û#°il3¢ØŞ‰ùXSJøúVR&‹IŠ\\Éó·JŒÕåî®ÅCP7Â–ObV}’&Dã‚®ß  ÁP(*¾”ÈôôµF2û\\Ê\\‡K	ó&‹î±J¶ õ—Ë2\$;˜[e6cr+Ë'j»û-•ú’u„½§\"úR„Dô>İ¦Â?{b?›r#?†!…›™ÃC.ß;˜âch÷‰—„ÆqŠæƒD¸ÿƒ6_f'\0M\0H>ÊÄ âæÄ”î„ÚA	¸)„ş`ÂQE¢ğ\nÀ-Í¦bE#¦EÇúN©¤t2Œ°B¬	\\˜HD¢©µŠ\"¬« “‹è&G›¨tMƒ¤ÉVbƒNµ\njéäB€ 	\0 àÍ4 çLÖH0,ª.[®æKG@t6Æ0hJŠÅĞÆ\rF‘Ï ÈÈn3o¶‘BÒ#§:™”éH4_ƒ¼QéôYÅäÌ;‡İ…gŞ7H<WÄÏÅ\$èÈ©¨”c`¬R\r\0Şá£6ÙÎF3…@(öoÌZ”2déÈèÌHêuÔäçÜúñS\r\"m\rd£\r£Üˆç\0‡\\\rV\r è`@Àä\ràÚrâ^whl(è×o+ßğâSä¸\"ªd'åîÉNM¨ÀîÇFM¥ÿNt‚løìŞVÏ”Õéò Ñ\\?ƒ”[P^«¨ÆİOˆ'¡1Ç´-¨ğã¨ÈKÄKßÂn5 İ@å€ÊwàÄ1ÑeÈêœB®\"eNj>§Q«¬”’WFø|L[†ÑÚ˜mÒ`C0¶¯|††^ ‚\n€¨ †	v*Ö@.‚«jhÔ&dÖÄ& BNÙ	ê æ„PÉæŠj ÊGˆãx†n[\" y³*q*Rq¢7.–ŠAêp*B¨«öOQ´Lâæ©rhYb0ª1+k+Ân*.ı,M<Œ\$B5/:y2Ò¢!q-’µ2ºŠ¿.rÆ¸’îÍĞ#ª A®hÑÆj¯ÎC¦ Úê<)Ì«gx'3-Â+0,Ê2ÌÎ¬üÍ¯\"Š£3s\0Z3>³0UK>`,ÜÓO/Séx&³*£SB-Ó^2³bCÊC2ÓAeĞ…‰HÒ§+Ú#Ñ\n\$oøõã†WˆdøB˜_do:*şeu:)å:nnN¢d_MD}ÒşØiTsc„)\"\$cU0Ö¥ï%bÔnª«>%¢EúÈë‚–F30qpÈIO6¦2³1RÔ#0G\$BPó—1b.GÏõ/·BSËA©ø„Â¾å¥T:‚Ğ-Qj˜ñn<\"8	t#D°İÅ£CÂå.²ËEç£DäYFtV9oBOÒ”§æˆÔQÚ2sHÄYIà°¨Ä`H\$Mâ(gòsHUFÿ‚Ê5ä+LƒGè Ğæ\$lƒI†üª\n‹°`İæaFŒ\"/Ş‹‰²mFĞ/F”ÓÑŠ3Èl™À‚\r€Îlqx\r†`Ş\r€ê\r Ü £È†#¨O5MGYQ@Ü‘xÅe+N[‹•ô>~ĞIG¡ğ6b/F…£7ÇM8äB×O‹Tõ=…!TTj‹tÌb4%U?UJÁENŠ¶3\r7t\$*>Y#2É£5H½2õ•Xõ’‘IO(e8/È„EG³rıTƒ	'/&\"“5(õÁ[\$ÏEê¤5Ú£5Å;Òj’‡O\\áa]3hArùXC‰Oß6Uã^b¯^©±5ğvõõ_§Bg`&¥²É[S€øËBÑ‘ÊÖ%T‚….g¥ÓKŠÁcµİ/Ãd1ÅcVKc•qõ ­Vhì¢H…Â÷\"ftŠuƒî`‚®DeÈ¬ul«öd0\$C/cyÇÖ×“@<m‚ÊC§XHÇ‘Œ\"Ë÷TP!ó(…¬Œ÷•~šÖr@%„}l¾Ú£ıe–2£0ÕÍaL†‡iNrI¢î Bó!é‹\rño(ÄqqõqøK,Í§mQc–\$Î6+`v.û¥¨vôELÃEkıE´G4ÑÑp÷'qUq7”#Gvôä|}	+s§ÙwHŞ„Eqw/Y„ÎÕœ•è>åYb•‹5·4˜\nØ¦ÏÙw6etW{1”Î÷xX˜1İ³ouÇÛRL8‡¨òÂ ×ª\"'uh23£?.Vm:‹dd‹³‰sh¥ag+G•†91Y·ĞF7Õ_Wå·0ôI~5Ê’·ésô}yßBWoâP“\"€i8vü­ë}5Í²G8*¨AˆUt”î™~x ;×˜Zc9o(àànzi>”0ôÑç\\ßÑ33¤¾•–=EÕ·‚5Æ²¨ë„ém…)I@ñ¯…¦n‰X'8NîXujxxİã\"1†SXÅ=wtÅ‡uXD•+•˜hr¥{ÉGŠ©M‰}‹)[I##†4Gv¶f.ƒ‹„–‡‰òì‹Øhx·¸érCu—T¸gÔ&’‰‰·yBQÓ;2‰WVgVx—	@ÍEQéèâAc´ÆVA’/\"-£™f9“\"Ó¹&+–c‘EË9å±“y)Ø1y¸ç”Y8\$æu¥\\‘[\$Ã3%8{’×ß”\n|Ú˜W%L#“×î*ÖW{¸¯%äY-t%-Kc(AVA}Pu_/à±ïÔ¤{ArÜÇ9ÃTò#|=,Š®µÉIÅ-•åXjZv2¿vyÙaLœ&C«`¹åC|Sy5ïŸ&-«bg4IÕ½óÂø}œP+\nG5GRÍòö@-»ŸŠ;¢Ôõ£/™árÖİ]E–æu¹2ê!@†¢ Øa 1®\\p‘òänÁö\"FäÈÚÌxÇÉ.È—ŠzW/`”–p56­g¹á\0@\n ¨à q‰ïto–™©±)ÉÙ*ö–b¶×¥_¡F~~º½Ç\$’Ø––Ã«y±’®^Íõ'£OÎXIôt–¸Ú&‰I‡=úŒNîĞc ªù·\"'šá%1±°qúéKJV<mâ¿¯‚ˆK~ÿÚt.Îˆ+å´O™3‰ˆ: 7bD#â#‘g/ûP7­ŠZîµo°ÑŠ‚¬æLAÖ’!¶–=²?‰I®ƒUDiãUFu°7çõ]âg¹M¸–ø4‚d¯Ó¬Ì·G;‹º>ğwß´÷¼²è-…vêïµYJÛ¯DÛÃl§÷RE7¡9˜¢+Üd÷FD&cŒû\0‰¾ìÎüÜuA\0á<Îú*ÇkWAdJBlÿR-èØÈûh[&(ãå„!¡yã\$\\›Ú÷´M¹\nø™Ê{£¨ˆ½û	-peÑ?òÔUçaPÉÜtK((vãy9……§hÈT–‹!|zİz¯£šû„sÇ(ÚStÖägd'Çõc¿xH/¹l–M@"; break;
		case "sk": $compressed = "%ÌÂ˜(¦Ã]ç(!„@n2œ\ræC	ÈÒl7ÃÌ&ƒ‘…Š¥‰¦Á¤ÚÃP›\rĞè‘ØŞl2›¥±•ˆ¾5›Îqø\$\"r:ˆ\rFQ\0”æBÁá0¸y”Ë%9´9€0Œ†cA¨Øn8‚Š¬Uó\rZv0&Ëã™­†©'È(œa7Œ&¡ø(’n1œŒ¦!»Ç%iA¸ÓD9Ï¡fó´?B¢Keó|†i3šfRSzi0™\"	ˆë75d%S„tìi‚ŠÑ‹&áK¥ÓêuqmNÇe“¨mB~×ÇQ%b	®¤a6ORƒ¦ƒj5#'Mn¾q²±oÛïI¿{<–ÍqÖ\"7)RÍ©‡PŒcCÚ÷¿(pìõ7ÁˆG»)B³,CXØÔ¦c˜ÂChÂ½7\"T6<mĞò1#­Èœ2M4@1Âˆ‹KZ”/Jj\$2\"J†\r(æŒ\$\"€ä,ÃjiŒ£“–¡µJô',(Çj(æ¤²Hb4ª*˜Ê˜„bÍÂâ\"P©HÈsêÂBÊÃcÊMP9\"3Üˆã(Ş6Œ££ˆƒ*ŠŞ6 ÒJj9B€Ş:¬‹2Ğµ&àP‘#\0TbòQ¤/!\r#@Ø”pL,\nƒ’ÑQ®\r­CQ”,+¢# ÚÑI1²'ËÁœVË£„ \"”äh¢QDyL!`@!ÈàÊ3¡Ğ:ƒ€æáxïo…ÍMnˆAr3…ëh^80ƒ˜î…ŒxD£Í¸Â1„\rÌ…¹.êÌã|ŠËœÆüX“<.-\rñ\$7ÁÔêë!\n1@1Éå8ó\rc®.˜Á(Ğ›µ+³(™¢OP×ŠÈ«îÇBá(ÈKØèæY¦l<çjğ²ˆ0èbC€Ê1Õ˜%Ïéˆé;Öƒ«Ä/ì‘§Q¬È—&\rx‰Ã{É;r#£pÖîÎbÎÁîãQdeóRüCXÈ2§)ºká”2=ƒÒt©È˜“\r#¥5¢,™Zâ§¼Cµiµîr4\"q`Ê˜\rMc\\Ø.¢˜¢&WÃ’Í•@VÄ—\"ÍÈë70Î@X±™B=cÔpõŒTĞ\r`£§aà\rá1_sN÷k¨ç#³¡ÌÈsÉŠô»âjÀnbª(Œ*#Jü6Ç<pŠ<}1ŒÏãO+a‚—äù°¸íƒXÂŒhu*+<0¯CT“ÁÔ\rÇP9ĞòÀ!ZĞ!†ğÜ\0u0P@%eóÌ::¤ ²·şv)\nWÆP7“s–ÃÒ,ÆèÁèQ\n¡E!„6—PØ šD\rá˜3’ HBxdTJö\$\"ÌvPq4-gÅ‡3(sJ¯TŒ€2¬âÚB\n‰Djh¤û‚jÎWR ¸Äá[Rd®*År2«¢Z±‹‘x·¨Â\\HÌd-˜‡FƒDÔ”muQ¼ÜGkÈ”u2±Ü‹G•dcãä`9q°È8ÎÌ×‘\"%+	.fLÉTDdÊ£“PëAgT‡#R\$¤)g0ÕZ®4’VrĞG†	j-e°¶–âŞ\\‰\\.UÎºCX/X´†˜,¼ò®mğ†X8F‰ Ã¤¸5”|©`²G6¨8ì,Ø³%Zu)V¤i‹Ôj„Ÿ•D‰©s‹-2N å;˜.€“\ri­U®¶VÚİ[áİp«iš¹ƒ’èaÌ¹Bc:rò^%ô0T¦ìßPˆ2Œ³hyWÓè‹‘’V •XoœÁÎ:–®¨\0€F¡š’xfšérçt‡O'ö”YAŒ\r6%1œ¤\$7­Åê·èN+€Æ‘CIûDP5+rBi¥yÃ/0ÈVÃC]Œº;šk ›Ó„Qz7G,¾q »\\Ğ}u«@€`RH±(pÕ*ªãnL=„¬0­%'ó,QİH3‚¤ã‡	õPjÁ„>í²µVÄ¾n¤!ªÔ˜ˆ\0C zI3¸ë²fIÅ¡dYü«óêh”}¥´óŠ@²bS\nA…D“ÜJ\\iD_aÊ\0«=ÉB%ôøùÂÑgì\0\$„˜”ªdDª…mrôXfRY¨PZ1‚õˆÊ‰è‘¡\n\rf ›I6Ó£¹î=mÜ—%R DÉÒ;1˜KÙ›;‹ÓïIQŠQ’¢ê…jmf'ÈØäNx \n<)…Fr‘x vÁ¹]œ^y¡0…AêıßÖ¾j›AJ0`3Á Ã|]„0LP³Ó(B‹Yn½.™ÔE`êOà\0Ã¸\"ù@\"6‰n*\ráG‚\0ŒÔ_/ …¹V@Ü1QVì„0ënL¸k&ä\"ø€«õŒ\\»®ê€ê`êvÓr|Úß¡¹v„r4ŠåE¢\0Æ…ô‹»Šë°Ò²:ãÅÓ‘e´_\"ê¨¡<–a²º5”|ÚHA<s†W=a¨OÌÓ\rŒzë0äL¥_XˆÉè¤a±¥2!XL‰áóhØ\"#Ì\rc;·ˆpÒ	yèÑC¢WUŞÊ>h.iŒP„ÒIU£¨<œ’xP	¦WJìaˆ* ’×£B†ıÜQ€PORv&™=\0õts\nÈóî“`NÈS¾4³CráİñÜÑ'gbØy©·Ì:šÜAC›V£îqÇ\"{LZj)h’2ÔqË†-Êô²&Hhon¬—…º2p…Ëú=\"ÁĞ9‚\0ÌÔAoaàºã4hºŸU‰5\0 †cÔ¯V7:¡„<îvêŞz,¢èú´¸, ‰yux¯%á®ÕAá\$êr-ªOË‚ïi\$¿î•vYö§I(hèO»˜DİaºœKeø™ÔÓ\n}İÓ0Ë…\$ZbššlH\n…@¨BH³äô]B´qÁ´}¤ëÁ¡©¦y-Ğ\ng ¼«3“‰4Ã[=«²¼Øü/~N\\´nu”úU^9ZgÎ,Àf—ß“øÎNÛ»Ûñ³å!¿˜\\àl?R»èı<'õN†)ûÓoÈŸ»`eOàùÖsâ— XÅõ\r™ı˜v\r£(­ã‚5IPuoH:jšƒ¢¼/œD¹†röä6‹ÄÂp%jƒ/FI©YĞ0°¯÷Oq\0\$DJ&ğqen†8¦‚1\":†£\")f,aŒäUI~ Ä²ƒÃ¦èR…„Œ0°p\"Â…LK(D*#üd\",EhY\$0a¢:İbx\r&k\nF. K†e`«|£r6ƒlA ìAÅ'.‚eBÙd\$4/CFH\"^ÁG%\r\n:îr°CĞÜğô@ì3şU\"^!ÍšPMŸ±èÑBhÌÿ1 è¦9\rñHì5ÃÒC*cgHBAÚ\rí\\kc6¨'8Èäà Èã¦2?\"Œ/ ¤/&öŠÂ†IsâêÓ.t9€Éîğ	ÀlBêêBÙB^­/ĞÚ’XN°)æ< Ø³.–\r‚­v\r®NQxÔ..Ñ@ëÈ,p‚Å4ÙwQ)¬@ı1SgtF°ô6ªÍÚ±ñg÷±!Í‹	kÂP)'˜EpõQ!'îPò\r‡aÒ\"/Í ï­\0´?ÑìmÂ{at#²2†¸ÑáBYÍ—²7úıˆxû’Bb²&/RN4rUòXı\"àıo´‡İ&rG&²<BrpüÏñ%¡z°Ò|ÿğLÎËr,Ä ÏnØ¯Í‘d½êìò¤íLù*ÒW.y+r¢í2¨>²¯ 2+,d0ë`Ş\rFN.m‚o.,Ò¤ä¨EJ!Ä4'Š¼%l¤Ú†N½\"äœ%fg.}0jÄÒ†ŠÒêÄ€#\"«mLw¦¹v¹‘ªÔò6°‚à{la/JˆKb¢>â?,Ñ*nÖí®¾-fN1E®Ä7îÈ'“hÆQÎ†gÏ©!{Ó{*òÄ2´è3‡%rÔ!sîgàÌ3z,GÆâó!±cÓÄ;©\$²Å\"¶|‚ym±-sÀ?£n'Œï;‰úós×<C#«-#äíÃ¢i\0–#Á|{r–@EXƒä‚Î5*Bô@#îqÄ„Ìkq&’K8±è²\$RIS¿ô{T<’\"jéÆÒs\rºx\n(F-9Çb? ÍD#ºÚí³D´&b¦<Ó ì#§*¹“\nQ Ö{ê™Aó¯r,\ng¼&4{C“Íô…Gtˆ¨”-!0/4H?#IGÁEÏ8ÎT©E±§8”!óƒKs•<³™-‡}J\"âiTPA4ri€Üîò'B-M”İ;Ôœ\$4äHq)HÔÇCÔÌîÔğcÅR4ø!’WNÙP/P“)BCQ-4Ä†‰Ä\$4>ğU=ÔR..õRäÂM“Ã:‚óÉtR}K’³L…R5F-r¯;ô¾QÕDRuYL4;Kò:\r<3@k4c(n‚kƒÙ#¢İãs³àJ4\n½&r6´JàªÒbt¼ğ2fu˜N@ç°ïXõ¦ÿOøu±À–#	:ÓsârQ\0«Y=•—ãzo’9€ì´ÀòzÏôÎ¥\r€VB@Ò\rc9c¦¾áZ>¢rYËzuBà@\$ä9oĞ7Ä±’õ*S*?#*-Áfšs2«`ª\n€Œ qÄ\$ÇÒ³U¾ÿÏ·YòudŒÛ4O&{•·e‹;33ÿeò\0\0íQ±()ä\"n\"ªjFÍ>t¢7Hb¢6. ë=š0Rv;àÊh~l\"ä…ipÆÓ<Iö›óR.ÌâZâÃòÕlj(“Dlbî>®*?ÊšBà§;ÔBâ\$Gëpç¼së˜¿vìø#2Jg¨P	ñ\"qôÖÀÜjR!HüÊÓæÊ®³Ag‘%Mô»;71œÙ€á	5³2öW<ìÔçÆìä3zaÒ@ÇDcƒ¸@@©M£;¶ÒáÂq;³6xH¨%nGÃÄM¯‰upÇ	/sâLÑto\0§OĞî-r\"V\r€óy·“kÃ~`·5VÕâd×ƒö¦Ú‘îï×ÀÖ#~i—&«ÆñW&BÎÆV\0Ü¶˜“­øMb\n: "; break;
		case "sl": $compressed = "%ÌÂ˜(œeMç#)´@n0›\rìUñ¤èi'CyĞÊk2‹ ÆQØÊÄFšŒ\"	1°Òk7œÎ‘˜Üv?5B§2ˆ‰5åfèA¼Å2’dB\0PÀb2£a¸àr\n*œ!f¸ÒÅPšãs¤SËY¦Pa¯ÁD“qa9Îr\"tDÂg¸Nf‘Êƒo¢BŒæùA”Üo‘BÍ&sL@Ù±×¦ÉVd³©k1:0v9L&9dŞu2hy¾Ôr4é\r†S9”æ §Õ¤èh4ïÎ•ÍÜˆ¦h9\\,ÜşxA•‡˜cFÃQÔÔ =p¡’£täÛgètºæéfÇ™YöyS=ÌôbÜX,Ä£)ê^¬+NˆÄ³\n£pÖÇï`Ê9HZ|«‹Â2\ríój™n™Àæ;¡c\"D(A£˜Ò6…®\"ÈCŞ%cxÈŒÆHÚ`;	ÜÂ#ñŠå¥IŠP#ÍĞÂ'ã ê‡;z\nÓ?ÀP„ÊŒ#’¼	¨b)+ˆã(Ş6Œ£ ä”Jâ›’<ëD´_+Ã\\\0­«ªúÂÛDã(.h”¤0R<H4¥M\"	;\nˆÔˆ6ôg#ˆ# ÚÊÁq¤,ÇÃÒ™Œ#ÆßDÃpÎ%n,\"¼„º,45ôÂÕDÉ‡‰[ 2ŒÁèD4ƒ à9‡Ax^;ØsµG¨ƒ\\…Œá{Ã£œ?…áv!HcÖ3!cj64±à^0‡É’ûÁhÂ´â¤\0P 7¨H¢0=îˆÈ1\rë:Ö†±ÌÈÄÍŞ¢ï0:ÊĞÎİŒˆ0ß^ôtÂ\nòª½O ¡,f1\$ƒ ‰ Ôåâø*4ã¸Î+ƒ¬èàØ8£*\r#€%Ëìs–9>HPÈ2Ğí²´“1£³;• Â:‰|6³µÇ}·\0PŒ:ÀÃ;C ä:İÒ*æ°%0ÖœI‚\\³ÒãMC¨ĞÔÆ³°™¢Ò8ˆ7—u ©FqŸéÚ Øà9ˆ@æ1®\nÂ„ğŠbˆ˜FKh9½3-ÿ|äCÕûÉºÜ‚zŠ©–|Ü ƒG6ô!©^(=NÑ#çMİIŠ88,K:>“.º\0ŞÅŒˆª;\ròÇŞJ<èéÏßWä!t–Ğ4Üšjv„©AXÚhd•+Tdªè6õÀ‘zƒ˜|!±3&:¡ğ}·îÄu\$9<Ø!aùyÿ¯2ğÜ‰ŒA{vAèˆ(ğÑY¡®6	T¢†`ÌKŠ!\"	á‘@‘‚òÊÃy¶[A¸<œ'şTÒœÍ#(ÿÑJbƒ¥ü0‡ÜŒÖ u#À 9‚”Fn	+z, °D\r’7ë`9%:§ÔjÄAh¤1ÀÒÍâ*±6\$1Z«ur®Õê¿X+\rH,e²ƒp/B„@‹˜…¢£ÛSÏ¨ç7³B\nÑA\r…(\"Bb‰Ãz)+F<;õ¢`©\$A4¼èŠ³Vz®~Áà²™ğ\\ôÕ”TVÊá]+Å|°ºÂQÑuc‡%’²ßãş€…i> ’C»`Ñõ?4ÀqÑ‘ÀƒA„t8‚àÛ2Dn4­V^LÕtÄXŒ%÷nÛ¼?tJ€1ÆSØ©ƒc_e©ÄDÀŒÃfÄ)µÛü&;\n˜ãœ	–Ã\n`Ø2³pÜEÈŒD0ğ¡EĞÉqF1ÏÑ®rÖEÃB4èá>ÇP\0('`¤‡‘…ïáO7Iš}%’‰7Fòv–¥.ûMú”TD7‡yÚ_)-P%Ûº²BÊİÖ9*€‚!Õ6zç12¡Â*µœ«Q˜w9€¨ğÎ®É8ä!Â=^AéQ³ŸdpÀ†ÂF‘ë¬°ÄZ&³DJ €°\"ätC¡|\$Œğ”‚À‘CIF”@œ•êVá80¼‘Cd#ª%éƒ’qÅKÒâ aä×‘˜…ÔnFg™ÀâÒ^fA¤:\$ÅÚr{T–Pß¡Ó{\réI›%ìé‡Do/£¨O\naRG…¨6‹ˆ¬‚†¸W%Î˜=#\\Ö·±²DrE¶¾N²àŠˆ‘Ú¦ˆ\rO¸™X+‰4„¬#J\\š'CrÚ°Yµ‰2ÆegxŒ³¸Zè\n>í™¾Ú<Ñ¨b;†:¶‡@~‰å¿²Á2`àáœêC§FWİ²G	¤M6:âÌÜÛQÙO3´ò£ÔCÒ#-š\"GKˆ‘¡—‚dÈêš[S~ãë¦O…)=º‚m“é¨¯ĞDû4Â0éÜãJ¸ı>/ÓûM¸o›Ì‘bWƒ²é-\rğ+ ³üJŸ`:~kÜ˜P\$lJè(P ï³¨ í^Oa0™h€ É2îAiRrÊÌÑ»,ï1;>IQ“°S3l¤0P´lÃ’Q	¼íQÙRa9pd0Ç9:A±}+—î‰mH26¹H|Ff2‡wZÀçif\n™å#—JbE¢›.vHø70F«7\rp]ßº'\\CRöÉÉ_C€wî;¤¢‰º Ç®è™w-5”¦ì›…+ŠmÂèÔï‡2×¶bmÖz¿B±òº¥ÁpfP”Ê¦åÆªœôh‡,å´å²Fİjè@™	Ğ4à›T`T\n!„€Ae×*–A‡7IÉ(D²<ÂL¼Ë3å1ŒƒTá\"íKšFy/u¼Zv±Hş`¡Øe‡©Ğ=qxæŠvöÃ®¤¬ÁqŞLjÊP	]|±yËúõ¡à¡İ›Ó^tå\nB9Ô|ı©î„ú'.TœÃos.•Í9°açbº¸#mÏ¸ù!êıg£Fş‘×º_5½7œ¯Œ;]»whäeğŒWph/Y¢Nw¤Çî¶B	€n-ñÏ©q^åÑz·„îÛ¨ªOâÈÃ+]d4ù»Ìñ¼òöİøß7ã×ßğ¾S£ú2#ÙçïXõ‹Ç–fŞ£Éš\ré?×®ãM‰…ò ‰‹™&Â^ÜŠ=;4Ôİ‡CğÓZm6·Cã²0q~A9j£\$Y—Zí\"¤t›b!Äš£¸`éQÖµÓT‚) SGÎÙ_Ûf\0ºÔ0¢f0B´¿(¿Jêÿ©^yãÃFÍ~ÕÜÇ äÇlhÕeöñƒî¦\0çp®x!¢xd«L©LòNÆ1ÊÇP-#PÃŒ,IØ.EÖ: ôfâ\\£Ç[Ğp\"ÂMj=‹Txdì\nÃ>1ïŞ.tDÏC®¯:efZ=p–Q€ØÄ…N<çÈi\0ÚàB†ê\"¢ \$cüÊ&1ä[€ÜÆ°:—ÌtÉ“\rÜ^äê‚àÆ€„Ænİç?Pò °.ìâ9çKíCœÓÛCqíĞœîìhü\"\\¾ –%Ç=ƒ@fÿ\0'^#îÆéåç®¦ãîƒîB‹ïà×.í\n†%nô5o:1Q.İ‘<\ræQî\$^ô1jSn­+Ÿjã±}ƒ%ò 1H%¨‡íÙKà±\r\rãÃ¿Qÿ‹×ëß.¹\0å\0£ìw„¬×@Ø2ÀÆR!)zN¢.:ãT£Ê:ÂÈ‘àGâ“)…\nƒàùf¤;ğ&FèÀàÒ #°(Â%R+fÖ¨AcL‹†ßQ¼Ù1Î­!BV9p8ØNìÛl3!†Ne-N`‘ÅIvÕf0P Çrg&Ó±º+\rø¸rhÁçp#ñ·0á†ç(`×&1…)	×Pöß‘ØÉ²RF’•'ÑÔúò#ìæ#(&^1]Ihu,p–’B\rc`\rç\"ü]²—&Ñ(éw!‘)‘¡…×.Ò‰'q’¨`0„f¢P.Øk‡/„ÜxGˆ¬ \rf¤mr±1±{àÒ¼ô‘üe§d\rghp’‹;3î‘×gf1Êo1DÆ“s3R.¯µ1¢ó3ÓT^¦YRå‘É72£R¦Öã3“Ès†eÀ†ÙÒ–6C~_iöjÑ|eDÙ­ª6\"ğ©)ö\")â“:³™;@ÚN &ŠR-¨Ùí„ÜÚíÄdÅ†0\r¾]ÍÂ%òñ	òzÚóå=rı4óá1=Cÿ8§@ç4\0MÁˆ‚ñ(De+‚²ßóè0àÉ=Îî3¤VN2âpQÇB”Bè8zĞâ`4ª^²÷Ì¢Ì‚pT!¢Ìf°ñTôÄ›Ñ1ŞQ¢óTf7ÑOFÔ`‚Ï`É)½ô]Fòï)ûGkğÂEã¾‚\nrÔC£¾bRÃEâI%óInşÄÂêÓ>Ôp9teKÃ—LÇHÊÿÑoL.ïHñ¦a)^`âDdÄ\r€V½Óè¡ğuË`}öÃ¢DË²z¤šc°\n ¨ÀZí6C@SÃ:w%KDè#R’‚ÔÉ±nwl)kğñµ/ÀT­Bu®öÓìÄ®>êĞx¬ôÂc@ÍÎ\nC6S%\0gcP@Mä«õ\0<\0°¬r!\n+XÊ”»Tú5BDÑk¤Z¤&B\0Z•\">¤Ôp­ƒÊHà†3r0Oæß(Ğpš\re·,y]/KTeş–î¥õàìÍ“Ëé]ğJô2o(õìp#~6Ci9ÃÙÂ.QçQ:uî§`‚Ì	&¸m±øFÈà6ÒMW`ĞÔğPÊ¢\\&¤·ë\\d–<µg¤×#àOËZµí¿!‚™ÎT\nÅ2¤–ÍÀáa'™Jê½\"Ì#-g&Î]bÖ>bÏ]‚H·-‚ÄÌB:ÄpjÍ 6Ì€ş¶—Eµôlë\n@î7<+ŞMÖšİBIËûâH"; break;
		case "sr": $compressed = "%ÌÂ˜) ¡h.ÚŠi µ4¶Š	 ¾ŠÃÚ¨|EzĞ\\4SÖŠ\r¢h/ãP¥ğºŠHÖPöŠn‰¯šv„Î0™GÖšÖ h¡ä\r\nâ)ŒE¨ÑÈ„Š:%9¥Í¥>/©Íé‘ÙM}ŒH×á`(`1ÆƒQ°Üp9ƒC£\nD¢?!¥GÊâË:™® ÕÚ'°a%eœ•£|¿ÁD“qŒäe0œÌ¢\nÅm=c£/\"í¬šmF¯°’:¬¢‡D\"UêŒj8°­Şék:]\nHÆ–øH²Á ±•Âër9æ«a ƒ(ÚhÍÿ‘ÊÂ_(Ó™ïHY7Dƒ	ÛFn7ˆ#IØÒl2™Ì§1Óâ: Â:4c Ğ4¿ƒ Â1?\nÚ†é+Ê†4¤ÂIœ(ˆ°k³¹¯+“<F‰\$š70²)pšE0‘k¸‹/ì’ñ¦x)½£HÜ3 Î£Ë©C°hHÊ2xÃKÊ¾¾\$1°*Ã[à;Á\0Ê9Cxä—Ğ´c Şı„€è‚@„½5\ræ;ËÃ\"¶N5hS^\"(ã…(°¬Š­9Åê\"ˆò))9ĞÒ6›´¬[x°QìjŠš¢)R1)-)¶HÈ‹M\$†P”ø(’#R‡ Å|ÈNû¡HŠNUFl… Äš4Fr\"B‘–U\0©;qQk\"Rä?¤ëG¶J[¬ƒ&ÉÔ–±·îûœ1´ÓwR!tªOØJµ^‰\nCÔÍÓbB`P‚2\r±ô¶LÓDÕ6på5Œ#ÆıqğÎ7äÄ1L\0î4ÀÏNùNS ä2\0y†øÌ„C@è:˜t…ã¾\\7Åõ/ËÃ8^2Á|â9Îs¨^+ğÛ/4qğÍ/\r°Ò7Áà^0‡Îšwrº\\¡LµhÈª\\ö+]Uƒ/	H«,@\$®Ír‰±ñª„í&GµÕ;Y¢I{W\r2HÍ×nŠãä7aH(J2\".„(Š<?˜ïÜoçò\n…êh/J:t¡UªYg»jüË Äƒ,¨©MÒ¾!„GG\nˆƒ(Ì0£dÖ;#`ê2È:›ªØH1I	#û)¤6‘S²Å¥ù¢ 7a¡tDµ/Y»Ø¼£–\".\"&ZÜòziÂˆ2t•9ÚÒDËGF'wÚFì’•wY¦ô¦ËFt¨ßäòal§‰øºÂYó¡%D)e£BHÛ‹à\naD&'ÓVj”*ã|ÄdŸ<¢„É1mP¶8õ¾IZÂˆ+Ï-v78HØRIÛn\"‘ñ¢C„Á„†\"ñ¸Š‚5!3S7K‰õ\"ºW\rY<'ÈÜƒ\nNq^‘00ÄğÄ\$rºN!|…XÅ7#Ê¶ìJ‰-èç<ç®bNcT%m¤\"\"\r•[ÙŠq©«‚°hui­´>«ƒa‰eÁ ÂZsP%Ñô9ƒà†Ó0iò2È|iâ>“	 ³ñ&^KAºO8ï(£¸e70–˜ÊÙ6Xê±Û#ÎzXaŸ”!˜3Åôiˆ’ê^¡P7Ÿ’ƒÈ ²´:°f¨ \r¼3£àæÇC r™!„3†5&œrù\rÁÕ€æ\nJÛs%©e¤bšÖf%ÁPö%SøÑÃ”Êa(ü/yÄ–Øèc•á¤2/¶>zÙ\$dÌ¡•2Æ\\Ùƒ2\rÉl3VnÎS4¤-œ‚ }FZR?’Ån-¤b†´VY“]FÌ²K2ÂmØëªJÄ¤\$mÍY±‹ä¸&š	>YÛ=cih<\0ÒgÓ³d%’²vRÊÙk/f3ıš&lÎ¼«gº´’Cø\r¬à:IiAXØ«ˆ?Ñà0†¶ŠœÜÇ¬‰İñ.Ct°\rY­&´ÕºÄFÆ‘a\\İh¬2<ÃA£`´m¦1#î\0b4aÁ-ÏŠÆâf§é²g3É¡d&œÕK,MÛA\rcx2ĞPİFİÌ°;é(§B¥ÉÌRPkŒ¦(r%\nf	|WpdˆP@@P¶«¿)P@\n\nÀ)QVİ–ÄóW(½%Ò<7Q0ÇFçåk>gÔûŸ›Z|“|™?‹ıÜÍ0ïkJÙ0(Ê9]¬³_rã:–!-´SøÃCšq`à‚M fŠƒ„äcñ8€îÃhI“YiÃ´4WÄ_DrªS\nA(€\\D^¡fo\r°È·jtOŒ „†¤”­â˜â4aŠ†PdF?ã&èNq\nF©¾›ñŞyA)Ãä“€DSÅúZÈÄòßg*UíI aæ\\JšCzk4Aºµ søgÃ‹»`€3%ĞÛ?h’ûÀÉd1Í3EiC?XÕ\\”S£ÌP	áL*\\Š¹EÖ'EwâÖ´ Ä\$4Ù»hw–‡uÀRäÙÅéAdİ\rŠ°Ä¥0hã#(ÙÈ£Ç0Îl²bÈÙH±†(²n[¬2–PÓf,2ÈÉ Aà¬‘“çÙ†„`©rƒdÀ•7àD×›C~o4AÈ÷%ô,Ù¾Cl¢dË«æ‘ÅğÓä;YDRÂp \n¡@\"¨n}2)iéjµ´sõÃEÄ8Xº\0D¡0\"îıã·™K*œX®’PôwìdjzËQ’ÃQÄ–Ø²k1ë¬Xbß´P{&çj¥¤­²/Z00İåhº'à¹4jëÅ»œ'ÀôùŒuC³¼ò@Ã²£ø±ŞÈ¾ï%×;ÜS¾ĞĞ­J©õÊ-ÈoK¶÷8È=0O8m3áæ¡©.)Î±oIê\ráCR‘\"cêW6#‚T”tBòÈ¯ş”®ÌS^\r†IÜ¼ßpœÖÌI„\nùU\"\$µÔ¥v5D`MÂ‹‘rß)\"Æş–°×išô¢á}â<¸ĞÛà?}¯ô–ûzJ¹ßÁvCÓ¼‘õ‚r;À¦OÅßµ±‡\"\$S°{1¼õ„:Ú\"\níe[\r†7ÃòÄ_ñßEáóSÚ~]Ï°¼çØ2‡wÃ1[õ¥Û™°İbO tù¹Ş‘÷¿’<.#P–(~÷öô’YÃ@S2„oæŠÆ²&&úzË²n­¶Û k`Ö  †3ä4l\"° ÃN0K˜)HºŠë˜‡#WGÔïÌı(¼àÄ%ZÑ¢Fìü\\‚Z.Ş\$îÊ8M\"V.pòGş‹¢`Œ­ÅÄîş\\ÍÜ\n€‚`ÒK`ÒM=ìÂ`@„ìW¥TÇ¨êÄ%eJì‡à@ğGmg\$<0Ênˆª{#µ*F0d&‰Ü\"î®'ªş(&:qÆ×\ræì8‚0Dã`À‹ĞÜÇc,pä,eªm>ms0Æ…êN,î­¢¿=Q¦°ßâÊ±±aÌG×…ÅÄLÉBZ¶bvÔlzıÆê°b–‰Pî]g¢¢]K°Òq:ğ?cPï0Óæüo°µIÙéğíÌ”8’,­&#i‹QFÇ\".ë±§	€Ò_\$ÖK î ¢(ñv)ÅÈ7¢Â{\$°‚øY¥Ø1‚ĞE ?ˆ>(‚41ğğ?'ü€Œ†ĞñèÎÈ.\"\0¹¨ĞŞG¶áH.{°®°¤Å\$˜éã,‡(>t%ª2¤K#na%Ô\"°0)\"ú0¯x:Å¤FÂŠÚäB&o(ú(æë.Šƒ#'Ë'Q´¥¦¦;ƒs2„oâï¢<,+oXëñá’záĞ(%Ú’«'’E«*R¸6ğø€b4†ò²Ã…¾)n4¹¦ŞçÇBYa\0èæ9Áxûëƒ\$dÃ°7Q\0-BJC„º	03Rıäô…2ñ1Oñ..–ü‡¤M@,ÒdZå¯à1ñ)²ÉBÖs£í1‚¦ƒåì\r€ÎKÆ(\r\0Û`Şw@Ú•¢B²\$e®»Œ¼±€Ü[Î”Ò®™'q·R|î€éÒÖ\\0êsˆ,(Ä÷Ä')A 93–íh(£u,Ó¤~…9S‚ˆ“‡;“=,¯+HàÈ¬js\$sÂù¨ã,¥s-“>SÙ+Ã«=¬æ´od?Æ¦QÂ›ÆĞ\$ÍbCÑMo£q.(15@HÅ(;@ãACI0Ï‹‘1<1BS«@±¶ltäT…ñSAªóAã¯âÛèO§Û³Úë°P¤FK‰F¸\$ÁFòâ€ñ'ÁkHè€“ì)‘ÈÿCPFvÉ\"?;¤ò§\"ÈñpX…´0¤9%h°3F!f;Jb€èè\"ÅKGXqñ²C§ğşËHãN|T\n‚\$1´Ø.·6Ë„+Ä;<†·(Ã—LÎ¹HÍ³I~)\"ÿ¢CLçóOQá&s@å>âªrn48XQ3õHr’»RñSÏçHSŞëuI!L<t—,óÁ-NŒDT:ü(üµ\0•CU+²+p<‹1ZCuC3ô‹Vç±X.U8³¼ìõ{\0pC£ÉX¶B®†\\¯§V#Xõ¦ûò`­/0XEE0B\r\"'ÒH‡î…+(q#'˜‰¨Ÿ7QG3¬‰ÔO^U‡?“âíBôHëN‰Ònƒ‡ˆÎM%RşUîB¾xãÆlèfìÓìåVE§‹Jv¤ˆ†ĞYaÂ1b	‚D•8omÆÚsÆôŠuæëU›Z¶YÌeÕ™GVW5òYuG`h^çqnâU¯\"°†óD%6['ôœT/4£Yt›Uv7vS4e\$YmL~\$jbSp\rcÖ4,,gù0«)\n°Ö¼=ƒø²VÄw*~¾¹m¿m`ËlKYpª'l_p+ïDĞ7hó‘i6ÿDWHU‰p2)pGUó¾Û6y\0\$:'WB8/–<íCq„V Ó(€…¯XÕ¤„G1%‚üáW>¶/R±auqô+dıu·Qi¶r±iâ/^îCÕ0¿#ìŠ„EV@î‚O4º6”­åd\"háô8BC º‘3zzNê¤ôJE·®·ccWĞ¶1ÕÂl—Ë§ç÷ÂB°ôQƒË9êpüÿ,0Ó~ˆ6´¹A×ê:ñy~ò(ĞUE·ûK¶› Ønui/äq0\"+]të+îòb\\ËÊôMk\$À„²\n ¨ÀZ	F L¼Gñ{CU]ÄZß4!\r1%z¸Zô·íTTBw™…Ô^ÃƒtñcYKOÆW\$ù-ÈÖì€š\rì¼ĞUoâ'0\$rhèWXëT¼Ê¢wK‘mŠGE¨«/Ó*Sc`p9€2ì¥‚0´Ñ[&³E“F0\r(SNVşh{/ú(ÃdW.Ænı§-âI3¯¤~mH•+oÆFô8ŞEVª÷ÒÅ‘¨†GB§Kë‘y‚9*ö\\ƒ1xüR©’îª·ÇA(ljp×“’Æ¥µkSô\nøæl9µÜ\$µRƒnÈn&’æ¸‡}'êE§îæ•í‘÷6îiN%^áäTâÃ …Q¿™3¶{!A\0000Ö@¬` ê³_oùRZÑo\$/1@t£ŒŞ‡ñh\$%IÖ~xèLmHsF\"qDØ®ÅyqšùÏ’2V‹£´5Y4;\0ŞÚ îı'y_…É¨H;8\$¹‚ U7@zTª"; break;
		case "sv": $compressed = "%ÌÂ˜(ˆe:ì5)È@i7¢	È 6EL†Ôàp&Ã)¸\\\n\$0ÖÆs™Ò8t‘›!‡CtrZo9I\rb’%9¤äi–C7áñ,œX\nFC1 Ôl7ADqÚznœ‡“”ä\na¡!ÆC¬zk³ÁD“qŒäe0œât\n<pŒÅÑ9†=‡NÒÚù7'œLñ	²ænÂˆ%Æ#)²Hr“”Œ¦L•˜Ã—3ğ¹|ÉÊ+f“‘-¦Œ5/2p9NÔ\\šC*Ä!7øÜK\\ 2QŒÑ‡9’ÉÊg6Ÿ§ÕÌf‰àèsğ¢+¾Ï¦uøı®äCS™7Oe½n¦ºÎT»ŞÄ0Ö­«ªøÈ	ãZ¾„ŒÌëb0³kÊŒÆÃ Ôß\"’0•/Crp2\$â²¾64£r)\0”‹\0@£1J:e¹#bô9&¡ø(‰\$D‘\rÊH@òŒ)0'£hÚ³ Ğd‚ì»lĞÓŠc äÆ°ÀP„ˆ1¤~ğË3ş#Œ£|‡'\r/Hò–Œ£kÒ³K+:¸¯EŒ2j6ÀP™\$°Ü‹ ¢cTÌ¢\0P ³\r±êJ„¢döÁ³\nØÈ6¢\rò`Ï&p¢˜ñ¡1èæ6©t”ÏDñŠç/(ê]&E´3\r…Á\0x\r¸Ì„CC„8aĞ^õÈ\\ŠÑƒs`œázJºƒ˜æ;ÃaxD Âm\$ÔT\n`7Áà^0‡Ğêz6\rNDÓŠÃHæ›IÃ¨Ö:«:Ò°8ìÛ:Ë]c#;1øò‚/ø­70’ˆJ‹ŒCÊ\$ßá‰ ¢XßLN\0P¨²XT…AÊ\"\r3/A—v¿Éå ×DïMF°Œ“`ääPñInFƒ¨İs6¤ˆ Œã:îÂÚsˆ¦•?éTÒÊ’°¦ï=6åË›+ãA‡£Àé³†BˆZ}ˆÃ­9w3Ã˜×éâ˜¢&£\\½@¬zĞK#é«øê­%ØSãò4O3L‚;s+­±ºœ\0ÑÁNõÀĞ3órİ¤êÎ)S4jÎ)Œ£\\²¤È‚/7+	#há¢5ÀPŠ<tkĞ)ğ‰{w ÂÓÆ4ófX hê–ÕC\r˜»İ„FŠMpók[	=Wfv¢D3İ#/FJR!êºC£ŠŞ¬Nè!ÏHñg½w·İÅæ®Œåq‰À ¬í’xŸ;#0ÍWÉ<ôÍHâ™F‡ÜÃ0GbÆ\rä1¶3’RÔ35dñl\0¶'h„ì†ÔdEÕê™À\$#@@ÛŒÌ	Rğ25\n¡×èr‚¡˜àA˜6yÑ^ƒîäˆ)“`A‹iÌ\rÅµLÁ\n‡pa°Xà’ØˆÓa`7ÁE¹h+Q8\$,¸äœv|oa	U`V†U^¬UšµVêä;«¸ª¯ÀXA¸†E¨ÆV¢ÊÑÚ8¦tƒ*Æf(ˆÚåJtŠè~Db8õÑ	'	Ô;PÈ©U;'!ÚÈyªceŒêÈ:+El®Ò¼rÀº9,6¢DŠLƒYK1Ğ˜Ê Í3Ó|è‰/†ò/\r º”!G¼I²Ê´T:EüïDÂ	 Cœƒ7ĞœR¦@@Ãå¤«µ“rQjbq5QL\\R£1ÆS¤bX!Ã¹4ká!Hğİ™\0s/æL3bN]9	}dõššÂ8Š`xP	@Ğ1HûQs‡H@2 PPK÷!Ï­­ˆ²I9\\Rêe)°Ø\rÁº7¤Ó@÷RKŒ;øB¤NŠR\$	8O2mÉOiğa'ÑŸ“øƒI´pˆA	HfyKÎCĞº¦N²jEMrF\\KcDS`ã£ÒHî§DŒ˜Tğ†ÂF¡¡¼¸`@ëL\r#‹Aİ\"·€CáÄ’%D±MD9ÇI‰š1†Œ¬„œÈ¿Y¹94à2©ïX¶[¨Ì'ÁèŸLÈ k®0¶uÎ`Ì“Å)…FöEEzo\0¥häÓ‰LÌàŒ‡2h‘´|xS\n€µO¢#™9ˆ#ñ4\0€&‘Å1HKè!ÖFÉ‡;xK»0K4â Z´zFÔ#ó¬›æÌÚ	'7dp#@ ’k¢t— ÚI“J>ÌvzÅ @¡a,íª¦3’ÌaHI€€*…\0 T¡H\$„à€pB	áH*@‚Âv\rÁì)†#ÂÃBvDSj”T¾‘°@eÒ×™è&YñK5¤×ÜùäiÎÚİWÔˆ~1û¬tò§·’ò‚ÓÁfF2æ™3¿KÚekw#WX\\.UqÇjh\"æÊL0i9Æ}‘\$b\nîLÑZ€éïCˆuB¨eÆE\0ËÈâ/DƒnlÎ2ìÁÖ\"ÂÈÍş7*\$üüÈ“IÁ:JE˜··%ö@?E‰9ó^EÌ\0u2e\$2ìI(s\r½+d:ÊBtó‡P\$œ& ËşeW-šëÔ+/]\nì£‡79>Ï¥ã.rCÃunå/`1¶F@PM!‹Á»/#OŒj{ë½¤öúÀûñLè\nnP†]õp p04­‡d;Kºr ›D˜½ÍÏ\\·N¹¿äI€/Tÿ2ñBè”ÿ…Pè´šxF'IÄ—[†¥o\n„†m´œœ\\UEJY(fv¨q\"h€Sàƒ‘s\\'&5åÁ{?Çî¼®G\$9ÁVÉéAM0´9Â>Z¹}¡æ3·šsnUyÈ¥çf¿ĞkVÚÚBÔ˜HÒK:\r¢œÎ@Â6Ç­æ\\ğ“„³¢Úb%\\hï•§,ú_ÑR4½È½4¦{àY¯ÕØÓğæ\\=ÒIÙ\\=­½te‚Cğ2&œÑŸhÍ-]©šˆ¯™9FÃN)ZNê_*Nòb5Ç|é/—q“.9Ôô‘'Cy|šrœ\$úİNzYèúo´¤ş¡Öú¿t›ÓˆmÔÉ&´ƒÉfù%‰9@„”ä10ĞDs_}Åì&‘gúßJA²Ÿ’œK¦ÀõŞ‰tßÃ+Ø€A\rœS]†„Ìópéj†9â-´µ\r#9q-éúâ\\óO˜É9Cj†ŠlÕ`!/€~æîº€ˆ[ïù\0‡Â÷®›ì’ƒjüV\nghÿÏ2ö6;Ã¥¯xËËmTU2öğNb&o‡+lkúæ¨vç	´énh„g(&dºĞjèÎØérKN˜(kèm¬…8ÉÿîM©	B&Úâà÷pW®š¾p¦i«	±pLõnÌe`Ø«Ğ€¡KæÀFĞÔšÃh…[Â\\,`à‚ª’Ø†¢ğ	¨\r‹øH(àF#ØGhÌ,ÍdSğŞBÊÈğ¤«í°2Í•\rpÚØMœİJİ…9æÖ¯ÇWĞïcFR^ğJõNšÊç\n^‡Lsğ=\0eåğ´\$ç=±S¥â8àÊsî\rÄOŠÒ€¦S¬_`äBd¤\r#89pÖ¼D– ‘!aÑc\ng§®öÑoñº6¯SšğÆ\\f©Ã7¸-‡.ààÍïõ‚9€Æ4eÒ-°MŸ0ìLç4Ô1³¬£ 6)Qwg3 ñlÙÏW¢m!mC'\n/RÓ†\$	\n‚°í#‘I2#/]r¢^bè3F Û`Ê‰chóm@SÀä\râ>4bO%’\\T#8EƒÈ‘'A'RbÜMÀŞŠC'¢Ücş-ÌMĞ\"qGQM)Şlbï!¯p¤2›)òG‰¹\$Íİ)ÍàárR1RªáFIqh\"\$\0Š\"Ã*jD@7bóş}ÄÖá2®É’ä¥Kˆ‘/\$Û/rã.o`³0L‰\0/#h@q¸N ¨&\"fGç24æÉ õÃ¤pêç®R¥gåŒ7Ì™3q‘ –PÆä~\"\\h(>Ü³92ÌîòÒù0Ä8ÈC“>\\bà¹s/È™7\"šÛDœ\r€V\rd@®O&Lë€Œ0ÙÃ!ú3ñ\"j¸‘ì\nŠÔ\"àd\$înÌ˜\r+ÔuR³3îO4ƒk<Ê^4ĞÈ‹ˆ\\Rgl\rG\\ĞÏ•\"ø3¨ğ\0<¯\"4óDÔ\0<í%†¨­©:Èšï’ºŒø\"È7Å>íÔ\$rä–4é¢%¢Q?‘ŸF‚ÕÄ¬÷SØ!Òû.‡¸mBÄ5ïfuçÀõÇöÇ°[D“}rh¥’²õ‹• r@º”hõÒh&‡G4eèH†vƒPé;PÚH€šøgê[ïæÅh¤¦NO È¥¾oÚI¢z#d‘Ì=8Ù¸2¤Æ=D>£Ñ)”TÆÀŒÇ4ÚDPÃÅ=÷N4dÏàáGqâxÎëR}ƒV\\\".(Â`@e|\r@"; break;
		case "ta": $compressed = "%ÌÂ˜)À®J¸è¸:ª†Â‘:º‡ƒ¬¢ğuŒ>8â@#\"°ñ\0 êp6Ì&ALQ\\š…! êøò¹_ FK£hÌâµƒ¯ã3XÒ½.ƒB!PÅt9_¦Ğ`ê™\$RT¡êmq?5MN%ÕurÎ¹@W DS™\n‘„Ââ4ûª;¢Ô(´pP°0Œ†cA¨Øn8ÒUUÉ¼†§_AìØårÂª®Z×.(‹…qg¤ª+S¤¿\\‹+²5¹€~\n\$›Œg#)„æeµœíô«•GKN@çr™ú|º,¯¼FÕÑİ,u]ÇFÉdò™X¦Giƒ§óST­rPÅå+ú_Ë5ÉÈ•Ê™ÆîÉaÊ^i6OCµ”Ìåq)ÕJ½·jÉ^E.QÅ@Ğ+°W@J„§êã,W(I{ø»¿ËÒ\$¤#xê\rìÜ\rÃx@8CHì4ƒ(Î2a\0é\$ã Â:7 Ğ4Æ# Â1E­ÛHµ%ú“!¤p¢°š”‹#%9nÚÒ—@P#xó;èj¹\"r\\ìÂK<ç´<‘2Jj°ï2èt ª8ª³³1ÍPd··Ï2âóN°x)ÄCHÜ3´(Q*Ú’ãÊÅ¢‚¤‘2Ó(š7¨L(\n£p×ãp@2CŞ9)\$o¶ÍÀÈ7ÆJ: ‰8áQÖƒl9õÉ\0´ïÄ—4‰’xÒÁéq\$ƒ²Òš~­Bpª7¿‹bJ2ÀÜ9#xÜ– ôŠ»I©p, ¸Ñ3è7kŠ™,Ï)u¼â*L¼@·drı´®ë‡2ÁR³\n8Êã˜¨93 —4NEÔ_ƒÔXa(P)‘MÁ\n2§	Qè>a>¶\\¾Ê»—ãòõ+ò†OeÊkÔ¼¤¢8Ê7£.*<ã2´\\&¯»ÕƒâcÈç\r¹Ë0®Šá3’àAZ3ÂõeÌ{TÉc˜ªÛê*Mõ”\\Ï3H¨4’³å5²šü\"øÉŒö\"FĞ°]£P²† Êa0tÂÊ5-^¿„L{›8›l.¾à ŒƒmP][WÖ#…f9VÆ1ÆÅ<mN1U!\0î4ÇP÷+ÖõÈä2\0yÎ\r Ì„C@è:˜t…ã¿|%|PİPÕÎŒ£p_[uÅt„K`|6ÔmÅ3TclolÁà^0‡ÕæÒ³«‹Ü¨åY*‚Ğ»BÅ}…JnQâ<Ø§KªV®W_´¹“\"`Ëğú?CğWÃƒRå¾‘ÂjÁJJ)Œ™Dú iËèŒ§Â´Ë—rÈLoÜ¯(ğ®CnPAœ‚‚PÈKŸÂs)à&C%²cmGœ§·@˜Ù[\$JD˜‚fÚòc9ÈUûÁÂ#àrç2m¾\0ˆC0a¡±X`Â¨e‚/ š¾µ€LI™ ÁÔÙG¸AR[v3Ñ•_‚€‚Ã9´ïmm4dNÏ)I^Â¹¥¯˜¦O„R‡­¡‡¶ølİ˜è®'bÅ¥2Y˜cû=„†#7¶~MN„EÀ\nõY[zG¶CØÖb2Gè´‚0Â“øQ	„º²\\ÏŒ‘ŒiBJIÆtÂ“BF‘£ÊÖôpÓºÄƒ\nA¢Ò|Î»ö”	Œãc™¦›ûš°A9&F4oŸô—Š3‚R-ÙFy–{~’ÈA;>83¦\\¨5³”ãÍ£~¯Î¼Ş†æ°õ/ùaÕ‡kÀøÏ1dÿAk6lÌÊ\r(Ò´¾<r8:e(£ípsvu·T+Û›d,ş9È¹%OT\0JäU;´Ğ’\\˜thÑBk0I¹MfP®€§‘òªá\$AsÅD ¤ØœÓútÀ47ãÈ}0\n3¾èÓVm­\r×¤m u….qOB€äCËŞ|!×½ †¶ƒ0iõ¨2× |nA‚7*•K3p´•Ÿ\rÖÀX•¤µ²Ø[FèÅBSÉèD=‹È!Ô?\n\rÀoÁ˜68³vZŸe§*@Ş‰Ğn ‚60êåÜÈf‹`€”†uİk7s‘îÁX(jğƒª8ÌÚyW8ée “&­øëQ0m\\È‰q¥ı“I‰ËIH\nˆ…M#°­£šàÄ<%@ëC“\r!‘Æ:ô@ì£¶wéŞ;àîğK‹Tä<¥WbãrÚz\0û½µ_’«årËÉFŸÜA_èVïO&\0Ë­š+Ğã(eîÏfá—a'™Á79„iH	¦Ù^‡˜óZŸÀ4›EwYo¼X¿.İÜ»·zïŞx¡Éã¼—µ^M’yOD9ƒênJ+5\rÁÒ¿Xjéaz3h5½ej¨-‹É˜F¯\nì&e0©XI6é+å•Kåe1;”DğäœfSı†U[€«Æn¶	SÎX\0Än‚ ¼ìÖ†ÍU¸y¶êXÛÛèÑÒ2\rà9†j\r˜e¾™l4Åû)ˆ!*¶C(¡\\±ÏY[™à¼Ÿâ&‚€H\n»9Éê¹¬\"Î‡T´è‚‚Ù®ÎIÿC`*»†ç†ãs›ÌH¡\"Ä]ª‘:´F(ñW÷+,xwÕI	Z¹5ŸÖ|LØÒ=¯´ã¯\n&ÓĞæÒÀ£:Õ³˜°*y=`Ü.K¨y®ª‡tvÃFc¯NÛG#Gª\"Ñ·))… ŒKL¦Pï8¾Õ…¯Ev5h<CJÊ¦Ššr›Ùytg*pKƒ®Š¥Î©¤ûÔõBCÅ‰ÌåÃs1©Ã„ÍŸ×Qsª´X­rY ŸçCÓ¶sJy‹ÑyçG¨\nğ¥Ezz£4Ç}é1_­.‚z®‡7°}ü–N—A5'è	Ã€’@ÃÊq·Ïr«n³\n;F&Ğ8ÆÈ•m½˜áŞ\n§ƒ)6ú‹r+d^nÒ+ò,qÆ“İ8¨NB€O\naS³OnÑfq­¦5—§„÷:êoxĞÊròeˆûœÍ~|Â*Îx›)Eñ\rÕæ½ÖıàÍ¦ØEA—]Á™ÇÏHzğRÙO2U¥tuzN]\nó-[ÁSaËx^iÂ´°*ÃÈ3BÓŞ\$Fµ—öopj_‚âlÃ”aÃ‡ 	À@\n  €‚\n€ŠP\0ˆ ˜° ‡Ímf4jê¤Ú•fª©Gâ¨F°2ƒ€âJ\nPÔÆlT\rv)ÇŞk0Ja)NµC ÿIëRÃhŠ*|”Š¤<¤¼™ãÔKŠº'j¶7…ÊÎÍQc”(¡a5Š^h’æ\\np¦cÉ™	h.šğ½NF‰Éêöiîö¥ˆmÏŠçL¤>â¨_®R(ƒÚ‚0«\r\$2öPN=I	ª\nÅLM	˜ì'æ²ã×éò‡h‚åè ®bKÇòéF}J\0äÊ¿±™‘m© ìdë…¼Y)àğËOvÚ0\0i\rÎš—ìPRä’5k·¥ÆÙ‚J\nÈPÕ¤€FŒ4Qn2ŠSÄ |Æ&GÊÒ¹€íÅ¿â´øáK\0GED Îâ-‚ØmhZç¸7+*Rl(ëT}1¤-ˆ:^n¤Iá~\$ ¤‹h`ĞhĞ?Âw\0Ír¥Ê†>Ğ~ë&è\n`ÒHÄ®êp¹(Ä\n`ÊE­°ÕFâ”ñN@à˜ûíÀ%¬·àmRÊ ĞÜÊ¤Ÿ±ÒO*e€İ¬9D¶öz5 P6(¼´¨^\nn Ë Â¹ ÆljÕ«K ^E`Êê²‚h6Æ†é8æ¨Š6-FE è¼Ï\"ªQ\$Oşê†\$6+è[)‘ö¤(ˆî‚K*E¨ú©¨ÿ á\$é>¥ÂğJEÏ­,D’oøºĞ<ë„Ï	nFûzmF`Ö  †6„n7_/J³,qø‡\$pê_-™0nk0Ïû.)Ø2æ4ŒTHÜ\0Ä*LÖ&ò!`?±øª‚ëKS0´\"d5p–Ë,Ù Œ{\$nĞòõéæ©'ö^J”1FˆL`à¨ †	\0@ñ³,U«@ğ§\"§-ÖÿDßl0B¦`Ÿ(‚`©‚4³xOÈj¡Ñ–…ò†Ù/ş†“ÄøNó)ït¢í:põ10ÜX,\\Ÿ›eû\rpQÎœ—ìFu¨k<r°œ1hìøRQNÈ^€Ù	76ĞÕ>0E\$ÓëÎÀ7ó^L?ÓÎˆ4œğŞòÖ9²;ìDÎót\r>ÄúÏé3°Î—”3?ímìĞ“@÷Í§?óÚB´<s¯EPFåLN”-D´j˜ïAT|ÿÑ=îQD4 Í´‹?T(ƒÓñBìúìT=TÍg>”B©¤šà˜şHDèà7…Ö›n¸8í£-ÔHJbL%¿*®Õ´\0+T‡	DÇFDüdE„;òCKCÃ.ê)Ğ.±¼ATâŸé\"wrë	ÇA pB\\§‰«HñZp¿1IŒ*5:¡JZ”–Å-îRÃVëÜïULí]´<¡	ÅÄ†B&\0†E¨PhÂÈN)ÙGD¼JÅÊ”çu)©K!÷Qô'Dñœ¼)EYô”èì?#Ô›D®µ»@ô5[%é[qI\\¤æ¦ÆˆÙğ“OÑ÷N\\Ôm]İ?t˜ˆ(Zor‡uë_LG_4)KæY_±g\na]–\\åç`•±<vÓ~©sƒV¶b4›b…=BêHmJĞhBÊWµ¶^°Bµ×^onÆJŠè²‹jt`.„J¡*\n<…Dr5ñÕ=3ïĞ±h3È÷i0ŞĞKVv…JKSçª]5Ön‰”åi19)ÊEgR¶ñ©ÌKWc›@µQcNY*ÓßCçòˆãù%6À\0@\n€òÒ&0 ØåFt€Ğ\r­¨\rèÀ\r¥¯!pûSbçLv¤n\rª‡H{ğõÔ…I)õ,°İÕÿlÔË`Ö7qğÑAÄ2Ÿ3x`÷j1+ltl³óC½a×7s©rWB2ö+§÷S4¹E¶^ös–·>“v4E+÷Qvõ¯c¶ÒŸŠg)Ì N¹rÖ]s—`r7y6¯#é­7ÉŞe‰åc­s7YxğLªuÄW¯tÖJ7SKµíI—À,‡)%’£,QJ;kÉÊÅ·Ÿ}\$£DÇeW&ôô8ºÖ¸ˆ—Ì§¶f	Ê<gP–Aj•~Nl£•ª¡—î¡U«`7sˆx^,('Oè‡UV•€«¡ÃPÇ#ÍÓyL0uNĞƒ1øCy±vÖ3xÄ¹Ëµ-í‘€–€×Ñx—½wW[7Î`²á†w±[“Û{uÉ{·£A#w,æÒiJ‡ê—€p?£.,’›•kò¦ú¦ÿ‡ÂÊ¯\\quWÖÏóöÚÅ@y'0…ğ8­v‡pyé·v¬ÿPTÍh“á.w~må£¯0~Ÿa\\cóíQ˜ÂíøVôw_ñ/’³„++)H/÷^Şèƒ³Æhò6æ  ­v×êƒY‘ÙON‰Âw®o‡X`Š8e“©…6;_„ñŠŒôëiéDxcT³€XÁ˜	Î©	RÓ{‡×pÓï'ÔI„Ù”Ø¡ux‰|%ˆéu©{˜1ms'¹É\rª“eª™8…Š5ñ˜€9Õ‘óv³…KJV4¢`i ŠDl¶×w†Ù¿¸¥3ougÚ¥Z	 Ök‡øK8q[Yü™š lfu¢lÔ×wg{W.\$7¡œÙz7¡ú¢RFù¤8I-™º#Õ£¶O¥õ¦:ŸV-Ÿ™«*2’ºƒzÕ¤hÍp-,dqI,#Í”ÚkKoI\0ñ]\rîMÎV¡:‰ù¯(\n1_Qäº‚ô±Z5úÅ~ƒ/›¸ƒ†ñ|ã­x\$ŞúG‰ºKzQzHÖ±»¢Úi>TW>¬›òµ°î@úúúEµ§×i‰Êœ•T¬&ªIzûb—³¯]W…™Võ!<h‘““Õ²©¿_Ùå®5|%š\nF‰Dñ®ùã¡Z0ìyë´V»yrA±	¤ú¶¶=m›ôçxY—¶Ù=´›…‰yábùª–Üù\0én¯©´Ûe¬ú3];Amû£nÛ¤›O¡v¡»³š:±Wû·º{u—Ê?˜ó#»»5!­©/ ÖDlâ§’q¶zÒ%w¾ Ë/[èF-¾è¾Æ³£/û¾dCÀ\0Ë¾íS/û÷o\r10 ×1“\nĞx“µ›©¬ØÙ¼;|@S	—Y~ÿúgx{Q{úU>ü?‘/ÄU¹z»wÕÃ—vKÜRæÜC¶ù¤MO¸¼Q1¼-‡»“½ÛcŸº§	IS¥jQ¥º>¨1^»Äà”÷ù7piÃ9ÑºÑ5³7¹ÉúÅÊU\rÚÜ9Ûf‡'ö}#wÉ±Ã4ñb5sx}<…¹œawÑ] 6ÍF—à!X!0ÚëµŠ§ö«tƒÏLğnõ³…I¥[&¬;\n\$ñº:ÑT´µ˜‡¥+Ã<Ùp××ícÂ‹v\$Şñ€B³µhióÒø:\$#vj(f›X%´ú’ÂJÈ/fk½cıÔY¦½)\nô›<;İ£¿Ì#P²Øí–7Ó\\Z„{­6³Ì‰€ö©ıª”¢6b Øo2\r;ı±§;%hÂFK`\r÷Ëft‚Í¼ÌjÆV\ràp\n€Œ p±Kãq…)Ÿ‹N¸’rĞ©Ù)‘×%/ârw°GáĞ\\ÇÄİ3ß¹Ÿ=T©4¿“Î¨·àú+H‚k~¯c›ib\\‚KÙ¨Øş84¨A¨Ë0WØ¸ÚFè	½Ì\r=Ñ[~+i½R…®Üõ²-m™Úz«{»ËÄ{v\$'[Y~èÌï—7©R™AWpREOu«î}!âVÌgªsÄ|^¹ßE	w)™…-Ğ›*nj¬œ\rßèdÇªG×(>GcÖ'eû\r»ş?ÓÔ*‰lGYäW5˜—›Õ°I×Óï·¥˜QYè\\X)×…,(¸±–É	Ê7ÛÆ^ğOËÊ¼7ƒ7¤\n‹F\$BD{ìSÀŞÜqRqğ×Yñqk5•U±QxE÷±”T¤Oóö¨‹}C\rÑ)K6HÖM Z°Ù ŠPg6—!ÓÑW€n¹nYÍp²€TÄ|\0Œ6ˆÅ¸ß{;fá@Æ ëoÕ`ÿ²®\$ ŸöÕ ¥ßÂ˜Ãfâ<ÅğËeo‹BeÔK²ÒÆ¤ƒ;µi¨Y^b¯À¨'Á%ühÏDg»Ÿéä?yÖ®¤\$³sP¨ıT0±¬˜T7WË†€\rãPºRÊ? Õ²4é,'fñFèôÔZ¨½‘Ã	r2(Š \$ @Mh€¦0"; break;
		case "th": $compressed = "%ÌÂáOZAS0U”/Z‚œ”\$CDAUPÈ´qp£‚¥ ªØ*Æ\n›‰  ª¸*–\n”‰ÅW	ùlM1—ÄÑ\"è’âT¸…®!«‰„R4\\K—3uÄmp¹‚¡ãPUÄåq\\-c8UR\n%bh9\\êÇEY—*uq2[ÈÄS™\ny8\\E×1›ÌBñH¥#'‚\0PÀb2£a¸às=”Gà«š\n ‡ASZ‚åg\\ZsÕòf{2ª®q4\rv÷ Œ®u´›Tq,É..+…h(’n1œŒ¦™–æs»®6t9òK'”Ùv”KüÖ—!ÎAvyOS•.lšU²†™äØ´t.}pšçûâTkî½p‚ü+næ†C®í“´³>„æË>èB¶¼¾i’ô¾\"êËXì È*~’-h+øæ#Ğ\0,ã¨@4#³Œ7\rá\0à9\r#°Ò6£8Ê9„¤R: Â:8Ã Ğ4Æƒ Â1FKô“=Šº\n[;Iì·+c¿:l¤¨Ö´p,,µCŠ éÃ‹ì…—\$45·¯Ëâ Ê0¨¥=È9s?ÂûB.j†Q@oëš‘BŠ™`P§#pÎ¥Ï“.(cæñ´OsÌ…B¨Ü5Å¸Ü£ä7Ià‰Èn#Œ2\rñ˜A\0ç%b88ƒ˜ïRKfæµ-ZÚñ-R6û6Ó\$§9®ì#O³ş×=Ö€@ Šld²ª›sg «â†Ù«	3Ö¶7‰(ã¹0Š´ú0	²İwÚ¨,ø§«s{jô¾3”FÀÈ+m\$L+A\rµY'¯äÛCCgH£ğ:jŸ ¤ƒKoC>,· ®lÄ¾c²İ¥{cîj~¡¦VºJ§®k(÷¿8ù®Nx¸É)iêÒİMÓ³,Ò+K˜ºRBœÍäê{?ª\nTõË®Ê“êor†§3«æe5QÅÂø˜lH)g>Ã™Î0õëz¼ÉìÓùFÃN# ÛDÔ!YWV•i[Æ1Æc3ğ»õP1UA\0î4ÇÑ\rb0ÅUÍv9`@sC„3¡Ğ:ƒ€æáxï×…Ãõ¾Tµ ÎŒ£p_\\uÕx„K |6Ô53Tƒlv4ãpxŒ!ô ­Ü0Ùë9Ü¼–¾mš²*EÊ~™9«e˜µ?øû#Fİëåï‚ºšÙ\rä0ì&Ú=e?Õ‹ı·³\\vØ¹l6Çuı?Sú½Î[õX†Ü—°Ãr\rÎ,9€ J!ô?D=Rd†jHB)F3âÜwD¡=]\$yh ƒpÃN©İ1vÎw’Ağ|EY{±ÏÒJIğZ\0 ˆC0a¡±X‡`Â¨ecê5ë\$Ò|KbX)ŒısÃM±ú9¦ØPšrÔPMÃ3\"àÔ¥ƒfK³[¦ä­…î[l7lä¡Æ^Šd^±Ø—ÈnlÔfB§õ¯r†m¹¤9Ê>±AFO[#gmEî”ÅB4K\rˆÕ!£dÃn‘PÜ§EÒ¢˜Q	í;ôœšãZR2Ñö ·Şıß\\’j“H7¶ÕãœÁBGÚ_¥I”!å8h¯(¾vÙ+Ã˜cšSP)Í©¦æÜ“-“·=™'1Å,ÕAI(\\	„MÊ_+ÔÓ0Êb‰ÀFl~•ã¾Ÿˆ)«M ‰Í–Ôqgo^u3Æµ\$Ÿ¡ş4.Ààˆ”4Şš‘Î*3¹v]\0m¡ÍXº„pƒ u‚NOÁäCËÏz\$ò“0|Şhf\r!•Zd8 Q+²#\$	Jq©¤Tº£Ô•H(xG¹Å<ÓÒ ;hd *&\$>ˆ\\)Â¼3`ØßJzë„+öàÍT\rè¥åàò­T®Ä†h–`oê\$9¹àè«¸aá†¡Ô8/HTHuG€ 9‚šÓG«aı­Âá™Ÿ¨t‚lu^ñ‰.?åôşIàTDŠm<€å^RŠ\rå½†åBçƒV\r!‘¾ºFèİ+§u.­Öºğîì]¶v¡ÉÛ»^«*`tyèë òÔU?'“ÙŞİ5³\nÎòOezÓ§³fÖ	mTo5Ø\rgSU¬¿O÷Ès×9<	§Z÷xïœâ À4œ%zçİ\rÀtÎ¡Õ:Ç\\ì•µT ¹Û;‡uTjUº\$†ĞàŒk¹”ş â+µ!!­ã+uC]qüj”MÂsÔ¬’<òÁÄİ;S4œ(cºO5É\"ùF0pT6»Á€Â¯ë‚¯ö¿J;`Ôû“GÈ× 0ÃˆAÁ¶ğ7](ŸUËÂ ©Ôı,rø·Sç–¨34 µÜTÖA®Ypp1‰Ä€H\n\0€@R¶gš)Å?@bE3Q/€ªp­¶BqhÕ¢´Z‹ÑcEJÙ\$^Ü¸l°!ß1œ™ÌÕWÃøAÈ-@%ófz¤µJ…ã#G0ÕÃˆ¨J}<`Ü,“™w®nt6¬€gtàƒ\$eÃ*)ezpz26vºì)… u™öÖ©÷\\mõ³›¤–¯Ü)|¥:+}•ö€Ë™K™[–âÚÖ/š6I\"†IœëQ¥¦!‚‚jKı=[Ù ƒg'o<½+\r:Nøk\$ã¨¨\$‡šÀ-Ş§V'7b„~øFjÄ3*0ÛlîK}Øª|1Ø‹—52¸FZºÒ—İb£tIöŞfõ¾ÃIùA(eŠ‚ÀkÛW!KdÔP½£!ƒ<vZ§€ZÅø1nêzLµt‘Pvà¸•—à¾EÂ~ïÂ–Û‹H\"E*ú8°.ÉÆ†*w,% §R™9€Œ4©ƒ§8G)â¹²²T(™RÈ«D¼™øëaÇ)ş‡;Á¹6Åğ¬pÂp \n¡@\"¨}±&_o	šöC}‹Â#]	V/Áß	×ÔvÊ‚â<?#Ğ›o#˜\r_´qêÑèeÇ·\nzŞŸGì.-¦¡ñÚ@LWí\\\"ÿìšÿ±ùeÄTTc5c,mµÌf¬)MÒ/ÖŸíÚœC’œ„’ŞNŒŞ„(ÖÆ.‡F_h¦û©&6pjNŠ\n</ÖœoùP4€ËÆüƒràîì7+à‘i<<´’„*™Ê¶[Å\0˜‹8‚ÄhãD tîpiîI.–à\"~ûìĞ\r8ê`e¼lˆêë£Ubè>b~74\rî'©>9£øŒk2õ¥0Lê\0?¦<\n`ÒH¤§<²H¤Ä`ÈLÆÿ#ä	€ŞvMDÃàêË*›#„w æ\r\rPI²›iÀ>ŒÔÖîŠCËö¿ Ò‰ğÊÔD\\ î'¨û0Àù¯ğ7é¤E€èµ®l“âgâ‚\")’‰|çâQ¢Øo“gº:B”î¯Üœïşoæ¼ö—‚ü™‘PıûîÊãâdóã®ô+ÜJ†¦î­,š@Æ\rh*c„Gm¯€Ö'¢°Y¤ÜâÆvúq€†‚,F¤Xäd«Ñ4T'(Ô@Ä0à;¢Ø\"±‹ftb±fÖ‡ú¡1d’j€Ä¾%¨³Â{j[Ğ8ıã6ôC>ÒÎhïl\n€‚`dE…\\‚\$NÕjDxW©V[É\\é­Ù(,ƒ\0^0‹~å\0€±}\rÂaF„:bdîi(¡KÊ`E²]ÂæƒHŸé2i8-Ğ²@2FèĞšlÒ@ô¨×âøfc\"kî&Pz%¢y%oC&ÒB-‘*Ê\n„òˆì2r Ã“**ŞB2© éÒ	(òåFè*gd‘Ğ¦íª©ä°AnmF HJ\nàÊCŠO8ñMA	Ö†å.I2õ.ã…OÆ5&É3ÇLôÜ-¼9ÆÔ­Hæ9¤ÒJ‡¾ÆƒÚÍb<a\n3âF2ß(†!>&Izş‘¸~ä±!\"d'åÊÒ+À…0Düs-Æ­&i,aO,­ölÌ‰\$üOé2ÓŒ]¢ ¤9ãÀ[2ºmHfÎÇîN9%nNîZs£ä§7	Â¢3œ|0\"µ6éÎ=Gì¾Cê63ÚN#’gó6CåO(c½ÏöÒOú{RdNmQ°O~=ÃL…Fâzù“\0cº‹ƒ\\'æÈT+,B˜+2Mo?iÂd¤ ‹DO’`ˆÌZQt™gİ©*Ä‘Qw>ó¿?éÖí)ÅFÑZ\"ğ\r€ÎT‡(\r\0ÚÒÀŞŠ\0Úª‰úÍÃ’]?DÑINZr‹¦iğ?/ÿ'ó‰­?cé~<”gp/mæ:Ôu,“Şşp=@’èğ¬¾s»QJ:tÉFˆ@piNp<©»S±8pNóİPi¿P­b{ÈµLUyUÆÔ™Tö=`ş³Ë5Mò@Ç.õÑù)¢İ;+@¾µA!Nôj2v\"zƒ#g%´½+Ò„pVOÃ“kL)‚eïH¡S…QµS¯C•|mÇï)%UStgSHS”Û8ï=W®ãXM2ºàRU#Ggï\n’™Ó¸Xs‰5}íÃtô(@Ü&ôEïLÔ\0í,gc¬A˜ücl‘¶î(ªïö-•ï•ñG&.fu\0bâINw”N’Oˆ:oSBoRóáíWuTô~_5ƒNã˜Æ¶;]¦Q6Ä	71»\\ö.„-Â„‘(cmYÒ^—±yZUé•ª91*‘u»<5ÃMÑâ€•k[Óá=”Ã\\S´ü§g	ùÚîê)–Š¾6ƒj¯SjğÃk5kc”õö}gj'k¨æŸ³™ƒ›lU‡Ö™bU;l–yZ'ámR:H`«Csk4ßRÕm–ÀõSÑ¼èĞ\nş\"œNô[Ó)ÉüKcä#DÌâBØ/—Œöü9¢œ€E0\nG,o•*O¶<·H²WMlvUl¶{o4kuWJ¶×NŸé­pVÕnÕ üwd€ @”#€ÈiUO6ïwÉxV\rçŠ{p\$;x*òÓG›zÄEEGy·~&¾mC0•ßĞ~íÖ	,60f©ìæIõvô¿hõ­})òØV—w3çk·àw7ån1³gõ3wõ©iôİ~÷×u×ùl×ıWÜPÉï~#~u~¸%Ó—]W¾+·8¶Õ%÷ºA+Ê‚ñLV\\K3s{†á{ÂzÅ§ƒXOƒˆ× ã;!&‹!f£`Ë¤F8¨§roÔ\"ÉR<q©‡\$hÉz‰ëúÕ¸m‡Iˆ¸xç‘£#¢x(˜o©½ \n‘ÃwÙX¶»‹Jw‹˜	oWûy7ÿ„4wŒÄo¸=[óÉww_xïé€é_QÇ…Xë‹eBcò¨Wj×>ëÉáhÒ«!BÒ)‰n¸ãw¸çŒØ¸cøi‘83~•Á’	†u[’˜\"'€8€Ğ«	ä¾¯ã8.ŸÎ\"(fa8¸ÎivéõbO²¤?N85º+nakMƒ9p ²ú=Ág\$å³]ów†“{rNäbñs–±9ÕA5»()7WXÃf]`†°àØnz\r8pdësg6âğ|dBxÓ¤JI‡m†ö@¨ÀZ\n–·.PQR¯'Ä©V—ôŸêkÙn²ÑvX7-z†±/:§Â&M(@š\rîPÑÔî4 èÂœ†äë^iÚ&ÓÍœ„\nReŠ=eb”•dMÓĞ'ÎÇ4ÔÎ’s–‡7‘”Ë|Oø^‚yOxŠÄGD„Zuå:IROì#Âr(ÀÜ2Õ61M6•é<vÓ‚-°÷§K-¶Q+•şOÖZ9y)Aq—úÅ?¸WM4”éXÚÍYº»u8¾¨ähD„M‡xz\rààºF÷Ò=¡j%hi1-A…†<bƒ­¢×GŒçGÔQ­™ª#É#É ÛôÕ_›\$&Z/²£û²ô~‡‚+4>ø;|_èåŸo²\nÇ ëI9ÁjD#‚e¼;¢°Ìç{â†Ğ[†ÊàD0Vnùîã9Æf©/­ğ/Cffìèõ%CÊ'¥£«ïÚş„‘N4ÿK”è’s6ş;®…™†úÈî@\rï8ñ8ŠDú†ãp(3+k<4•9u\\Ãì6{à-ÈR	\0@š	 t\n`¦"; break;
		case "tr": $compressed = "%ÌÂ˜(ˆo9L\";\rln2NF“a”Úi<›ÎBàS`z4›„h”PË\"2B!B¼òu:`ŒE‰ºhrš§2r	…›L§cÀAb'â‘Á\0(`1ÆƒQ°Üp9Î¦Ãa†l±1ÎNŒ5áÊ+bò(¹ÎBi=ÁD“qŒäe0œÌ³£œúU†Ãâ18¸€Êt5ÈhæZM,4š¤&`(¨a1\râÉ®}d=Iâ¶“^Œ–a<Í™Ã~xB™3©|2Éu2×\"ÆSX€ÒÃSâ8|Iºá¬×iÏ1¥gQÌ‘ŞÌš\rï‡‹;M¸no+¡\$‚ÍÇ#Ó†Ò™AE>y”ÉŒF½qH7Òµ\\¯Š¦ãY¸Ş;¤Hä'Ãd1/.Ş2aüÕc¨à8#MXà¼cº42#‰Ø@:Jğèš+©Âj2+É`Ò‰¸Á\0Ö­«ªøÜ¿(B:\$á„¢&ãØÔ–1+,0¢cC£;OÈˆ¸ïR<ÄƒH © P2êè´\ncHç\r¯Xê7ª*³+É¢ÂXB„ˆ7« P¬’„¼¯ÊÑXÒb“ízNó2o8ˆ#\"Ö‰(ğ\\ ”(95cÆ1Á‹pÜ3„\\QÁ!\0î4ƒ@Ş£!p¸çÃa`@!cBî3¡Ğ:ƒ€æáxï]…Ã\r¢Ar43…é8_SUÈ„IØ|6£KÚB3#Cl4¼Aà^0‡Èäæ‰ ƒ+€#àšH\r±m/¢R2—&JB•>‘\\¬‡MºÈ6¬Œ[z^ÂÌÿ5\rSô…DÒJ2%@èáxhòîÎ,²W\"4:Ì\"xé,ÚíèƒH&ó”ˆ9à²Sy]¢ÄSB3Ä5­ÈÈİ!	wÛrİÊo 47å8Å³ˆ’\"õ¦ì Ã)Ä1ˆ&\r(»š&°ìP…x&(éa>\"³.íÀìĞ@Ù<–°æš=bˆ™K)€ê7Öö¹¶ûÏNFIÍå=“÷®2b“È¶S^{ííÀpAà:Şƒ®3»llÄÒX„ç³ÍˆòA›İ¢Ì6íHRZ@³Bzº<ÈHó:#^Èƒ&“äˆœõ½|í±q|n2\"@WkKÕÇ\r£¬¿UŒ6rî˜IS\"<ƒÍµn#•eœ!¼C0Ò3ñÃ/¦/òD§Òóf½ÃÈÓ7|ÿÕ£Äp7-Ïû§ËÑÇH6K`Q†!Ê„ÄO“G0WÃÑäĞ5ÀGü!Íh Á„=×óâ™8XÅ†ãv[ŒâæDX‚æ Ã!å\r-Ìğ2YäPt2‡’ß],;h!Ù\"gPBÃ™âF0(UÑyø\"Ğ¤“HoŠGX5†¶0aŒÇU€WU`¬•¢¶W\né^+è°ƒ’ÄXÄ¥ö/SÄ³ò‰¢Å>Bàµ—IÓ‰ñ}ÀÜ÷ÈãqĞd9±„yÉHlÕUÂÈ29†È†'¨êB—95ˆ¯±Ê” Ì]Î1\"¥–C¨™Ap 4á±5”àÙ‰ËA1Qn.Åõf­Uº¹Waİ^«ø<FÖÅ\rËùwë0ÖhsÏ÷ù™ú.MÊ@Ã£»iÜ8®(6-iX,a!kMp‹*£PWÑsQJ&õ¡†ÀÎIˆ›¨/pğO»1cà¼ƒ;mÄSÂ8l¥â<„0‰:³ÒÀ™R¹‘ÀªˆCr#r†tƒ È`E`ñ\"\n (Š0½K9!\"\$ˆ°SC\\ì¢š¶ö\"(yDÁŒÊ@É¾ÃJJ1¢H8Ö›\$Kˆ\\rèÆ<²àb‰%	\"äfq%wV‹pz!¶u¢©İ<I\n ‰-\$º”|M®> àQ–BYJy 4ŒEÃ:³%…íP—ºhñƒ)©Âœ‰U@\nS\nA“†£x¥@ fAÉâ¶3y%Éø#j¤Z0úà”‚Í*Q2—0¦Säş„ €&Hù\"øĞk,,Ä•¤J\$‰‘™¯&OäÆ A–\0rôu\$Pxy/G©\"dÊÌ]-ÍÑ+bÿàˆb,ä²Ğ Â˜T:\$L·*ª’F<2—:”r‡BD3„:_¤Õ,yÃ¨y{„‰8\$CË‘!^œ18`©Glñ‚×H^]Ay; SmJ\$ YüzNíh‡¦BŠŠ	A/g•²ƒáT!¹>€Šeö+4ˆ›˜r´,©zw€Âp \n¡@\"¨køH=Ñzs”j\0D¶­ñ¿aòô\n].1&\\oqØfÇ²©”š´º”N5P¹ %±W	möKEİLæ\n˜Ã`u\rg­—’sxé\rE1QãÔsTör8­)»(çà*lÊé[6â\n¥wÜŠw&‹x’·Š~åvX=ú1p7‚rğ™ƒÒÎQÜ·×8ê	Evê*¿Äøk:®î}\0¤=(×È¼\r è¾*¤=Fèîo\$ëÖm\\ƒo©ä7³¼‰ÉxDb\"C4&™\"b²»‹EŒinß`šö°±Ó(	Å—E”´L\rêø÷\r¶g{ôx%Ø2ÌeDskİ}8îYkÀš‰)”.”#¯ˆú˜wè¶Ö!¹}®ã4\\Ãu\\\"aÑÉô²Ö­ó4t„{ˆ‡:Bá2\"ö9O³5ã¢\"µ;…oÎÂ:	üHq43×@(!ÕÀÆÍ9awß\0‚·^^eš©éc!SœçnÂ•åAäçMô×N*\"m,XºæĞ¥’anÉ%Jİ9š]ÒS\nP „0xcI[ÏDEvİRĞ!ëXaÕEÄÒØˆ/)¬E¹¤Äw!«î¬)ö¶WD8×’ÚûE­˜gy`Úmb!V@O}ì=şzÃï)ü&Ğa0“ÄfSâòÄñÚNù–úMïÍ47¡­¶š³:‡XdAòsÒ{O7üÕ¢„İàè{'¹å=¯—ôŞdh¿Kî=>Ñ=jhÚF¢¨‘?%fß1¦˜Ó/l?(x;†PÄ£p³Æ^%ıÌbşº}¼µûc·ÍÇ'Üs((ú|öÄ iöz0+±Œ×².Op\"è*=@¨_hú(ã¨sƒœñ\"¤J³l€!¨üRƒtñâ8Çáb‡àìÏHÚ‚>,àÃ6oj\":6c6Ó¨0”êµÊm cçdæRF'\"GHÊâø.LâÍ\n‚!ÉÖCFP€_lá­eäØÈB“Úâ‚\"âE‡¡/I\nâN¦0,„¦Ã T\$Â{Ò\r­ÔDĞ¢’må\nåƒÄ=èÜ\rÄäN7í%í4rFò&£|c\nŒŠÛí(nğğ•Î°ÓÀä+i-rn1±\0wÇƒ'~!pTpYÄõçş(æc'<YC´)/6&:ñN÷/£\r&1FE·á?!bó±Z5oEŒF+ÎPléÌDä®Nå1\n°Mñ‚Ä±‰ñ.6¾ãÀ«>mƒ4fëÔœârÑÆ,£n\")¸›ÑT‹Œ£H„HÚ=ç6%‚¶LqÌrƒÀ€íRÑ\nX(É^ğ‘±„å-±&Â\rîBã&2Ë±¶çÎàe÷pşô’ßñ‹2&ÒQ•îàR)GJtò*ô‡L5!²I\$R52J,†¡C\$Ò@@Âk†–ï°àÇHfD%­¾t’m‘–ÙqKoI(Ã·­ò”ıÇu#kúfb[É\$ñD½„À‘_\n¦c*ƒâ¾‘\ny…É+ME\$®\\¦!âÈ±ÂÖ#ê\$Gê*\r*Rã+Q\"2è\$m+ó.ÒÉ\0¢Ë.j´ŠòW&/Ay0†r@å®l7¥A Äİ¢P@d&=Â92N^íÓ-32ŠºCXåÓ(/*æG-ØßL3H†njæîx^£{)6¨W+š«N{1297I³)q6ÓÓà´I0á%®¨F.­0Óšrn)“{:\"¾èó€#€H’zˆfÑ)EÈô@@[óÂ1|2‰ÂÕÆo†º…³¼Ú/t„ï<5sØÔLé=ñqàŞN&>ÛqJanÕˆ–ùNèÊ±^\$ñb÷?t. †kU/NDEb„7©d\$C¨E‚öd¶\r€V¢CJe+búB\n ¨ÀZØ‚»(Œå¯œ/`ô\\ÃEÀÃ‰ì‘M>QYAp¼']Gm“Í0\$Ë€ú04b<J\$øJÚCCP˜äé)”.Š‚%–7n§H‰Âr‰3œ“Bå‚h*!mÁãF+ª£âúÃP‚g‚bÓ;lè¥ÆÚ„J%€ŞvGø÷PEOãdÅt¥ßOïY?‹9H°QOóqQC5Oär¾+æíÂ* ’ùR‚Hã!Ğ„]´ı”ØN‹ì@š\rğe%ÀÌæo\ràâ\"dPÏ\r-¬®§\0èH@¬<¦8N ë\0Bö£šÈH=WãÔâP\0ƒ´h••Wiêƒ_&­STí+bj…õ¸m.ÏŒ»QÄâ\rê’îZ€ÖÉÔJFì²òx¿F–P\$€@"; break;
		case "uk": $compressed = "%ÌÂ˜) h-ZÆ‚ù ¶h.Ú†‚Ê h-Ú¬m ½h £ÑÄ†& h¡#Ë˜ˆºœ.š(œ.<»h£#ñv‚ÒĞ_´Ps94R\\ÊøÒñ¢–h %¨ä²pƒ	NmŒ¹ ¤•ÄcØL¢¡4PÒ’á\0(`1ÆƒQ°Üp9ƒ(¦«ù;Au\r¨Äèˆ*u`ÑCÓâ°d•ö-|…E¬©X~\n\$›Œg#)„æe¬œëÉxôZ9 ‘G\"HûES°ÎÑXÄj8±ÀRáÙ9ÚÖ½|_b#rkü:-HƒB!PÅ„£RĞÜD¤¨iÍyA	Ç–x]5ƒÒà¤KOc™J×vf[5•{¸±ÙfØt¤™k Òâ‹,TIjh´…’0Ÿ'\rz~²8È‹°²\$\ry¢ê*©.ç#Î‹4n‚À¡NÛÆƒ4Ãş¥Ãª*Ìü0(r}¤‘48ì£ÙÃ'plA\rDnÄ<“©èÃø@¤Èã#)ÛŒ¡Fñ^ÕÆ­sš§Èã¤ï	„X Äó À°ğúì?œVù¿	ú/å‚¼H£Í´,‰)\nø¾êZ\$,\nŒ¡\$¤ÊÃ·H‹ªƒ,,ğF#“šM!d|š¸³ÓÁ#ÆeËìŒEMëj¥)†‰ÁDm­+Ëª±)é›Zµ+Å;šQH1(áµ1;…E ÅÒŸ/ï!¡YÌ&‚Xâ¢ªz_\rÔ(„°hnÂ†?ì!T‘è1CW¾Ó\"¹6¯µ›NÁ¶µÄh14ÜÛ\$fîè1>áĞ)ÁB[l`ÎH1Gb¶t´½w£…àN×H1rhÿÈ\n# Ú4Ã(å:Eòı!säüì¸2Ô Måò0Ü#ˆÓá{Î&Œç#3üSAôpx0„C83¡Ğ:ƒ€æáxï¡…ÃˆbApŞ9áxÊ7ãƒ<9úXÈ„JĞ}-¶w•<²±ITà©ndT’áà^0‡ÏrjXË’\$¢Ö’×r4È²ìy*,*euo¤n§6yKÇ\n6ğS¦háfƒIÂ_#hy‚zòÁ*Cj/‰)»!Ì„^ŸIdœŒ™V=GTªÈ=oS\rv#Ùs'è“v¿#kÊ÷ÜYğ^iî¦!b¤^ô’ôÔ‹¸k©Î¾ÖFÜrvÃù¾£]ÍŞÍ²m#±7\\àÖ‘è\\Šn\n_–ƒŒ*È™B\rã»u*Ãş6©¡w¸\$FüA›‚3Pà´ì‹UÄÙËâ}e\0‹ª¢øG›¦q©hÜ-‚ß\"²'äX’=t(B‰“|pJıj::dÉBŒNqñ ×†’šAL(„Ç¹	(‚ Ë^)\$¼F’SöJ=°\"úÉÉBMë}|½g á›Û*<É h\nLE›\$F>BÅ8G¢éË#n½\"²x\"¸rz_ˆ(ÿ‰t’¸ÈS¥G©Øİ ó°\nút\n¨Ù1ÈN`ãÅ„õ´aM}cçu/	RıÉ!ZÑ]tC”xsĞ³‹aa!I8Â|áÛon(|­†ÌŸ™›Zˆ%–”ˆÙ‰¡ûmM°—JæÖÈBR8oúE«B',Áñ¡%aR>rl_ÌªyTaÌŒ8–DwQ™\n¤Â(†^-Úù%T.˜×ÊT*Eæ,U‡\n©^€ Ø“çS¥*qzÛŠDC|p@“2'hå%ôö7„Y¹¥4ç™x±GÑI \\F¹ù'ğa)tŠ‚)X8Hä…r²êb““:J¬\0—¬·ç£ÉŒŞz§B;?W)”.ÅÚ\n è<â¡D:†/ãäF¨ü¢OeÑg]FI­N4v½y	H§½\$©í{ÏêULÎ…-'ñâ˜Ğšai²u§n%Q:{EÉD]£DÖwÎªNMÉã“RÊ|é)µTç–ÉG+ÇÕò“\"Ô÷ÔpÑ\$ñHŸ£Æ±DQ5‡±ä£¥\\„Fg2†’\$ëLÁ3f¬İœ³¶zÏÚCí£Øf”ÓsP¼7èm#XkfI\r“B.¦^Zì!jCsÖk^iQV\nÄPÅ¢N*åu˜¢X\n„ÕGÔª¸Xæ2V(}\\¦ùÄôÚ%3«cË!—/¶Mˆ¡i\"´É3`ÊÎÓ<gÍ¡4FaZKKi­=§€èÓÃ¨j\rf[®B’‘Š›\nTûL²´j™¡¯5îšZˆÁ,!ÎV{H™°—Á¶V¶ìX—&é„³#O?¨±¥s¶©n'&±/Œ[ –va0èPSôC\nU*_rëyà9q)–”[ÀF²˜†)b92ğÍr<±ˆæÍy²‹ÉóÍLÂ†Z@P¹YOÍ§„¢AZ8,cZŠqCÓ(¢3(Ç+ú3/†L¯	ôîï	Æs{)5\"´PÆ	şÃ	]È²¹´İ±´o(j,»³X/ŠñÊ¥i\n “–é”®>±ExQÒÜÿD3İw(õ>èzkÍu&¥%ïÁtë\0R%\ny•Àí3î2ªN+0¦‚2µè.˜Ë™	”É'&Â,êšôØQ®r\0ô¹Y\\­Oœ–;…yAéôIÑë¯ê#<B(Êtv«=”ÅBÀ-§;g!±\0ş½'ı´¦Y^ò?(öO—°µA­nC­h÷œé!Sv¤X{mªÉ(:‚:ÏVúád}Ó¨¥’Ö“±FÕîÄ¡l\rY‹À@xS\n3§*bãŞÿ™Y(XòÂ3t£›z\nF)é©½àz\\&ËD¨¦üÍ21Ç˜NğİÎÂ(Œ›‹ñóÂìãWCñ¦5§=âB,B¹F³6\nœ]‚i\rªò¡¢Á*Y¤D‘—¯/“ü{‘X9ìéq ™“ã™Ğ&!cc•‹vôæŠ›Øë‰‚0i	„‡!Ê\r,4h˜Wïh	±¡#ˆG›<¬”Ãq×’GJ	Ô[;F8 òĞbî÷Ñ¢Íx²Ò×±¾õ\"ê÷ŸÜp(ˆÏo˜éñ!ï¢Ÿ-U¹,2-æ5(’ÊúA¿}Ís(\\	­nFgÆœ±3­¤tŠRƒ(SÊô”q6äNjÊ¤™\"‹ûú2_ìüïğù*(Ú\"üÕÃ‚á¢ˆvèœˆ£*ÿ%hO‡>ô‘'¶+@têj,ÌÄÌ…@ƒçâ´I _¦¾­£ÒP/! KJr‹EÄÍÄ~H\":òH2ôq(bXËü)†2*\0K\$¶·(ùĞ\$}•°Š©°ˆ484ğdLB…rˆŠ<€æ3`Ê¿\0Ğ\ràèÊeø0.Hã©>9‚¼&ôDcÀ#f#MØmhrg*@§/	0âWg}.IÃ2\r\$\r è™€Ì@Ş\r©'b|ê	bppªEÎAíÄ……¾z¥fëåBwBÍúËHˆä¶ƒE2ï„ŠğÅRñú#qƒnĞL#œ¨ª’ˆ²ûdËeR)jì/(Ôr¤rWÅgä\\ZBkB¢3\0İ€åÀÊu Ä(öÂâXá	\0áQ\"IĞŠeMNh#BÀÚ¥hƒPœx¨¬ÿI*HŠú]1 Væ\n€¨ †	â yÌÜO€OläÎ„€ìúLÎM+–Òn\"vÀuÀ^-ª~ZgÊLatvCÑ!'xo²¶ádFâ9 .êŠŒAp&Œjkäßpz7¤Œ¬\$Át¨˜œa#,`|i(r=d\$HƒE åw!òTsƒÅ%Ä&:NÍ¡\$'7&í/\$Ò=C=°¼<ò\0<Ršd2€wq§Rvqà‡2«#h&R‰\$£§]*1dı’4ä’KÂÇrÇ!’ÌO#Ù+²Õ#’Ù#òÜÓ	%è‚–ÖÍ	!²™RVìCP\$l ØD\nTG/}GØ6.§%ÈKo)sRV(2d(¤\\*Œ0d@0§:r#Æ°M\\dã€¼†O’ÙoüQ2L§‚·RÅ&ãñ¨Ã„¹°ˆ¿åîFCrETù6UïÒAIìroÜHÄ3\"ó”Å“™(èŠÒó¢œa›ÊwJòg'½èa.9:“½-AÆ›èböÓºöÂ›:ç¯=°ğqd\r>,ª»oö›b6×ïX4È^“&În§èÅúFîH¢so(n!‡Á\r|Ğ’*ó~DÂ´2ğv!Â4¹×\"W0yCÃ¤õd‹@I®Ùå„cIAmòa âÉÈCBI¹9sÓ>nA;|\"~ïf;jp@‚\r€Îipü\r`Ş\r€ê\r Ü ¨üƒDÏjK˜\rÀÉËô^“bÔ\nS=LıªÑG…·KïÄıÄÂ¯”6h-¥¿/TÎ‰+ şæW>)Ÿ?…ÑLÄÙMíGyGRI=‰<‹Õ9¤¡L³¡P¬†ºğõÂ:‹4ÌŒ\rMs­Gu	/R	|WÍê²€í*A\0Èì!HğK…,²¶&uRõ-â¸0¥T59TjW\0í€-ô¬â–5ZH²‘:\n=Sõg-6\"†ñ2ŠgT«UyUfÒUXéP‡­RŠ¸ÑLPMWóQU¬4•±\$´ĞPõPCy\\(6n•ÉµÍ[µ,<û<PA/…\n3VÃí>>q~'â’UFíLGôÒeJòBˆ«ŒBRoa@³m-@ı-rE(‹F…U\r­Y\n1²Ë0Nn7ÎiªÎ¶#_BU\"ÑãÆğ¨O&ƒ*åQNĞQ/6„¯@t>öQ„©¯Špö1ä6:Œ´=éñAi]2G]rÆ˜Ğë!5½Q)uLÖ‘j\"{UµéOu35\"ÿ†O,ˆL¹jtÉjµ2vÍ<õ§]Uí5íŸlõçOU%S174Âû5vÛÕë,öøU'…n¢ü\\Ó®lì´\\î¼HÏ,l@¨o3%>{Dˆá\n…ÿbÎY¥§QÕeLp•LÌü·;T÷?i•ƒZ×J0NO—Sn±`ËŸv}hA}É~'o(¡5W9W'oÑÇwt~x‘cb·MxW?G¢€Í”Ø•ÀCvO1›\0„Æ2VXçu-=UÁv·Vÿ\\føİ•kvïu‘¢··]×ÏuU«v·}.}ÕÚ™7ÏyVÁ6Èù|¶b,é•z\rZu¿mU¬/T}kVív”å~±e`·ø¯Ö„<bD_‘lÈ˜ÅÙ|w‡»i¨0MX0T84ä5!^´Í‚A„ZŠE+¹{ø;”å†f1B¥{öáLØu8k}8?Ø„˜#©ğ\\±_W—‚¸i‰…\\WålÇõq…\$İÅÙ>CQÓƒ×k‹\nı‹C†y¥ímøILØÅÓeŒØvxŒ+ÁJCe?\r´BwƒT×`÷–€ö¢Jd½%cpÒ¶õSØ»rp'r²K,µ—¨•‰P#Ã\r#™*ß’Òw(\$Ù’Çg‘¬8ŞgpwEW8©Hvpb'L'ó‘7í“#’)í‡ù+R|sÙ4ıY%uiÁ—¤sÂ'—s«Z•1”nâ5£)À†\0ØuÌ6i¦àã„t]ÍXÁŠ£˜Ù\nï¹[NEM46R×ƒ)bÍ[j^F–&\n ¨ÀZ]'RµöµMÉV‘UÛN2È£–Wˆé£í!Ÿ5…SZZÍpÓ\rv­5B.£h±#'ÓS¶u7{‰	äF|¢4œ§F;V\\\"r÷k¶R‹>VF³Úì›—~ãJnzô›“³0ĞŸ1/Ô4<0œì™°V¼@M\"c°eF¶‰¬îUdzCR’Pm=Ğó\n5¦80„øõ3<”B~tSüaã«–vxĞXŒÜÂYÕvºÉ:­t6Ãj˜<şï<§­ùxdçBÈÇ!\r™Õ®š§>ö vÅiZHPoÚ,e	¢ğòğWB†ßå…zO®`åÃc çÎ]bô…#ôhËÇ(W2w\"„#Šì0¡yƒ‚Úû¤o¹s8S¯I-HúÀyQ°ö¡M9·S}K··O¥v‹hÄl&¾8\$&æML›¹:Â<}­·å}¬±Z;DT8Ğ=lAº‰p°\"2 "; break;
		case "uz": $compressed = "%ÌÂ˜(Œa<›\rÆ‘äêk6LB¼Nl6˜L†‘p(ša5œÍ1“`€äu<Ì'A”èi6Ì&áš%4MFØ`”æBÁá\"ÉØÔu2Kc'8è€0Œ†cA¨Øn8Áç“!†\"n:ŒfˆaĞêrˆœ ¢IÌĞo7XÍ&ã9¤ô 5›ç‘ƒHşÙq9L'3(‚}A›Ãañp‚-rµLfqÜ°J«Ö˜lXã*M«FĞ\n%šmRûp(£+7éNYÑ>|B:ˆ\rYšô.3ºã\r­Œë4‘«¢AÔãÎÎsÑÒ™„ãÂuzúah@tÑi8[êéÚõ-:KíZŞ×ºa¼O7;¬‹|kÕušlÌÚ7*è'„ì€ÒÖŠ‰‚´®+ÉœÓ‰ËĞÂ‹£h@<6`Ò5£¨ü(ÿŒàÄ0L8#Ş…!ƒŠ,6\"#ZçÌÔB0£*â8¶\r‰{ş9…Šè„‰\$R®¦ƒòß'Éª¶®«ï£¡¨ª8ÈŠN´p´»)›àï3Cˆè%ct¸\$oÄ45Mäÿ P2¯IrÔ<Œ3jì‘»#(ä;OĞ\\ ™¬+@7/KX¡Ås\$>7ÆíœG¤MCòÖˆƒzF4+Å.Óˆ#\"x7OÁQ\rJ‰	­!D‘2eO=cC}\"ñŒf°ÆÈŒrÈ!ãàÑŒ£0z\rHà9‡Ax^;Úr5QÏÁsb3…ã(ÜÅ˜æ;¶# ^'ÁòòPãUÑã xŒ!ô;VUÔ‹|ğLÍ4Ú:I„û¢Ã’Tı²íäXÇãÒd9=P{Q€®wú‹aQNÖ‹.2ƒ(ÛmLbxÎ7\nş/’Å PJ¤ÄcÈèåh¤‰.\n£šÌ7_c’Ø=d€P‚;ƒzğ‹E›Ù|+¸`@8ÓˆÌÆĞß-hŒã¤4ä#;™‰ğ×\r(İj'#j2İIóËª9A­h×!z(‹>kò!* 7Ó èš_<4ºçÉ\$Û7[v23I(ç·Ô+Ø(‰ˆ ê<¤ã8Nµë“Z•;ml=VÄ…)ˆá®O»#Æ=u8^OÖõíşÙ‚tw¯M r–µ‰#j^éRà¡k£(È³êíB\\Œ…£Z]5Ë‚@Ş¥S8Ú863Šû˜fÆ+İ²W‡	¢hŒWÃ±&ØW*–‘Ú\\‰ÁãMßx¾;d8Êƒš‡'°4—#–üC˜>a½âœö¼ aºÓğa 	Ò‚é5é\$6\\‘‹Q¾:M0º%†è†ˆ\nG¬è)—cˆˆy\rÅtä‘ÖğâXpJ)Ás‡ˆXCf&Í1<5Ø\\YØ‰n-)˜•Ö˜HÑ3 „Z\nÅ\\|\"A„ &¸{ÌÔB:ÍZ#x²ƒâY†)19ÄÅ‘;öŠç¥\"=#D%hh\$}±T+PÙÃBÌZ’BNA…|™E¢ë14m¡§,†`V2ÈYK1g-\0î´•sZ«]l­°È\\9p\\`ù9†¬Ò×ñn!p=„’\$ĞÁ<!çP2­é^JA]%ç|:“%€	‚>\ré9i‚kÊ™FY€&e˜¤h\0ªyÑ<S­1\rÓŠsI¢M¡Ì:¡£|°d˜Xë\$:,µš³ÖŠÓ”AÉk%°¶–Èx‹h‘J¥É[’1Å¹%†x/%òxih½]#Eziˆd#&5¹‘wòÿI‚\$„PÕ!r`»\"¯E4¯Æƒã\$|?\nÖDÀ°OhJ³™eÈÍÂ:ÖÑ2ƒçÀéS]E’»F±í/º8@PHU	\"µv#AA>'02–:HZ•YF)(Á‘×‚ğàd||*EYÎÔ[•™'%\$¬Š'â`FdiB-¹ªÀFWii§çä—™\"h©ÑYË\" œÆ†¢¶ÜÃ’AFä•\"2'bç)d‡á´üêô¤µ~!)… ]IŠ›¡Lœ‘nıÌQ—I`”03ÑFâ’¿.r›êvH›Ò„l\0’IË‰ßPËfÏ˜[–Šrå¾é>äI¶TrÌ¶4’ë\rL‚ ÒM¡oÚ.ÃÂW3‚€O\naR”;§ñq]Ë¥E'İ¸2ê C\rÁ˜4†w™¨“boD¼Íf's£°K½çÀ)¹BjMİ@F\n•HØÅøCˆ¼&øÂ'ôı\"Ik–f@yg™ 4Í0¼ãô—Èd;d „àBf=øô\"	ÂH	!K d0‘@S5fêD€¥Ûøt6j eôàØ\n\nÏà™Âš€|Ë¥ªVäZ†1ƒœlp,<1ôñ‡(¶l¬×:5¡=\r3Á—Õ{´2ä†X\nR¤Y“§^v•©:­ó l¸Š;C»¼wÉ™Ø¿4©I+.¼9E\$\"İÒ´T	ÃÌ(-hÕ(RÈõQY+‚*5bD À ªJÕG®pî6;0º™ÈÓ“®	ğ\nc9¤ì·®vÃäF¤\$ Ú‚B\ráˆì5	Lò>z4ádÅœ¤‰u_,è9†3AzcĞU÷B;×^‡^¹S«:šİ¡Y­Ú.4¿·“W\0C2*Úá&· æ™øi!-6ù„»'XGÃr#LÄ•Lê™¡-ØÑ·¡¢X®3i§\nœ;:huÊÉ-8zó ‚ÔÃ:~KS›ó“gÊ!­áœı<¢(@R™Sjtœb\"‚sç7ei7ë-7\nB3i+úºŒê6B T9&é¢ ìZQmHFTZ¦å~Â›o‡.\"¼ÂÌAyNf1A ‡&`R{á±KÆê?Yi„EˆŒİ™ŞÍ³bœ[ÇÀá;5.4Ä†xÇñ¤ƒLLğDÎy6,~}DëÏÍË¸åÉ9Û6uÛ3mæ|=/Œ~+ÎúÏ*»ÿeo†sÜù¿xÌ¼i0Ò~¯äšÓ_ßx™,mèì\"KˆÀSOÚÑ\$f ÊŒ±.´Âä&bnoz³,Ø@Ö…ƒ`rëå_D¾!t3~Èfü`l97ğpäJ;\rªlÄL^ãÂ5C°¦:càÜ%Œæõ\r'F<d~Ù‚g0d8°”Gn|°÷çÆq§bôğEï®ëĞ:#°PÊŒºqŒâ c,\nfbÉ`&œK„šâƒ DÀô'‚üG1¦>g†qJ<\r¤¹	4BÌ¸-`¢iª˜gNô®+ĞV/ğ¬\$)ˆ-F|&,à‰(@†Ed*\rÂ\np\$ÌÌ\$\"Ë°Î†cô\r\"àÒ‹ìÓ§NÒ Gn®ÒÄZ¶¸+í'­ÓÎºÔ/SËõxôÇW@¦wÃá0·'jæ‹\nÒq,lña±oÆlgRyfØ¢ri*ôGìï°KğV1Fy1L>íP;6Q_Í&ä@ĞäŒmÄ\nÒ1~Æoóq{ldäq’äÇÍ0ø&dF=åVã‚48†lpGğ«êLšÅèD¾Î\"ZVk0£ç¤Ä‚#1°))ÖfâÒ9bç¨ê¤\0×ÎšÖ#8œƒè·1ÂÓŠÂJÊ:Å‘ñ±ƒãôãn:7FÕp³ZÊC¢kè#Nßíú8q;†àå‘#H’æpU\$f à2LÔ#ZzÇ±q‹#Ò\\@ò`ädzòi0·&bTâ’llrx¨T5°„Ğ›fÚ¥\$5M”\$¸HhfŠJl‚§@;g§™PE*ç•+2;+g‘+O¶j`Üj¤ñ'Ï(Î²Ã+¦Ø\rÒÕF¤j†½(RX4ò¹²tùÇîV±ÆyÊªz'¦Crµj0+„Mru\$g3D3	+ó\r1³Mrã\"R32“0’É(ï%&0D\n‚Ï1q~,ó.ä3G3®Tå…?0±æÙ#’#Äw6e4“k5…k4çXçŒ\nçÓ]îzT³%5ó†örO8S~OÓxls|çK5LÄ:ÄÇ(%A¢À,K5\$M&ë)P“´bsg#Ó¼Ps²PÒÉ °—¦Q*­-±j6êí(†ô6,VaÆbñ­H&â<¤øòÔc¤İc¾\"Ïå?ãP\rJàDp'®<\n”\r?Óğ)&9po~ø4+8­'\n0pL³Å'æäœò*l´\r€V\"£ì—oÌ(`ª\n€Œ phlt4Ëk5*BeOGÌR{³ïTGï¥t‚iQıHF7¢ÊÓ)n»©¸ãòÖtC™b%\ngÛ\0ç\$4ãqæ9´J’-04jKcl(¤Î´AÈK#åÒ@ëO°_MÈÄ.Ü@:¢È&À\ràğ5G7¦\rÎ¤O0PDLØ'4Q*VÏ1Æ3‚Àd1U\$ÚP°@ÑQÏ\$|3cM6YRjV;Íù\$¤¹SÃ–ĞĞîVæÂu€ˆĞbc‘ Br'g€â(W,»	‰ (Rùôåèm£N	õ@\$uDAtÿƒDCBKÄBbyK£oQnkKÊvŠç M\$Lñı\\„ u._H¬™ÇˆÔM¥>KRÛâhL+†O/h"; break;
		case "vi": $compressed = "%ÌÂ˜(–ha­\rÆqĞĞá] á®ÒŒÓ]¡Îc\rTnA˜jÓ¢hc,\"	³b5HÅØ‰q† 	Nd)	R!/5Â!PÃ¤A&n‰®”&™°0Œ†cA¨Øn8Á1Ö0±Lâ³tšhb*L ¢QCH1°Öb	,Q^cMÆ3‘”Âs2ÎNr=v©–›˜ˆ8]&-.Çcö‹\rF 1Xî‘E)¶C™ŠÒñâ	ÆÜnz4İ77ÈJqm¬©U`Ô-MÈ@da¬±¦H‚¾9[Œ×µê\r²İH ê!š¡Äêyˆ i=¤×Y®›d\$ÉIÔäXWÓxmmt¿ÑWjYoqwµóùD¹Ä:<6½¨£à\ncì4¡`P°7˜e'í@@…¸°#hß¢,*ÃXÜ7ê@Ê9Cxäˆƒè0ŒKû2\rã(ç	ã¢:„Møæ;ÄÃ#Šê@à‡¢¨»€\\±£j LÆÃ¤JŞ”)l\")qvO„”Æcê0Iê~¦l²èßKÅÙ&¦BÅÙ#Â%\0òîA:0£ª K P›5\r°hóA9ã8*\rës)'BB~“´MPÎë©vSÑhò…¥(;%re&¢…ll ¢…KÌ!˜Å\nˆK!vã\rAó„ƒ×IhàîCŒ™×èbËT£rÔ¡Juğ4Œ#p Œƒl·ñŒgÆÃœqQ°Â1Œqç!‰¨ÉE‘p@;(pŞ:ÜÁz9ÇòX‰¨Ğ¾ŒÁèD4ƒ à9‡Ax^;âpÃlÛq<L3…ã(Üßwèä2át\r±3Ñ0Û\r#xÜã|‹,[WÓÌ\$WKƒ8¯JFt¼6o»¼Š5´VŠÚ„\në`ee¬Û¿àP®0CuØ‚„£#0<ƒ(P9…3ä:<ôj•@°ºN‘¤´]’¯ÑåÙ3ˆƒ(Í1\r‘°ì0ƒ¨ËfÓä–ÓLD'dÑh*Æ°Å†*j0\0Ö÷íÓ@RÆÃ T²†Dó6eÑ^‡<é¼±b9Ú/\$-óàĞä*ó€P\"ãµ~Ìi°›¬»®ïE\n#©†TÊ0a@¤3;·€ĞÑBD¯\r‰ÿŠ)Š\"`<p£cËEî±dª.!Šb'¼ÛüÛ¯Ô‚\$¹³l·\rÿ ÑûlÅÑJĞ“B%¯±÷\0§’vÙÚ!¡Èë™±t°€PR^áÑmPˆe“¹öNm°9w´ƒÖ	U‚éW ¤üÿ›A>)Oäï¨ ˆ#õeo}Â“ @C¨sFÌ‘—ĞèZÈ Z±	>f`Ì‰<`ø!²àÌC<@± @‚(\n¥0† Ø`a²&iEø­¡°e\r„7.¶\\`âÒÕ\$ÁÍÃ†€ßC\n+Ş\"Şƒ2¹\rÁ”†„([Am)Fˆa‹RH¸t&ä\\ ¶£Zu^R²,®áì%…şBw!È]ì¶¹Wšêú!éãŠI\n½MKÙ£`ğËƒ<…Z¡­²ä!ÊìamH0ä¿Ão\r!‘n0@ÀÛÓa)†0æ Ø“˜À¹‹±–6Œs,eÌ„NXBâ¡!\nİOa@€9?%©¤Ë¤~\$ü2ÍeÀó\"[“‚¨ê­\$*çC=P6v„€0&	4˜Ka¬=ˆ±9ŠˆæàrciŒÆ¶5˜Û\"‰fİ—ªIˆìïŠÔ‰:ÅnfÓ“u,¡!‹¤‹*šA4\n¨‚.¦ZH!\\ğQ—/0Òa˜0!ÁËàÚZğaÍ†_®%Ğºƒ0u©!°7§’½q'0)†§—Bû3tpÀÓ”4Ö\\Ò½6K*Ñ.¥õ\"¤¹qwŠ<]tl@P\$î`¯a/È—\$¯äF] Ã1¡Ñü‡\0äP<_õA}#´g¢æ©‚¨Ù&Ód3Á‘éÀÛ¢6NŒÉ¨sG«¦+”‚ÉÃpp_é\$>¼İ¹%†ñA„TªÄ]€\$*ËÁâ\"€‘5}H#Z¹ó(Pœ!#”\0Ì‚»\rÈ «²Ì#iJ°¢³®D5¹Ÿãr\0(–F\\;*v(Ñµ.vS<çšH}„í“4„ÈšI\r*YñJd1g#`ÅwŠòNWˆ’’@ÃÌ|“.ĞÓĞÜ×ˆu /§Y#`Ì‰PÄbµˆ¸UÃ`Ñš=³h âœr~Ğ)É6#à¼(ğ¦\$Â\r»bìMĞÇ‡varÍÀ“ëìK„ÏmBÜ–‡FçzŸüÏj/;w´RJY‚Â§:EÖõ DŠ*IÊ&H¤\$³‚¥‚?—ef³`]Š³Khb—«œna=¨èL3¶¢–¡l72Æì‹Ş«Ñ?UVŒİ©Ó&ç›´)|¨\r\0¥z“Íü“Qiø]—54#Z™¸ºaa§Øc1Õ¶€\$}Ûr¸RÆ¤''¨¥` Lµz*‚\rƒAŞ\0»zNC˜C\rª63í}ïãhÀTRñc¥'@'¿Ye¸ìœÃSKÒ,¢:ÃLí;æ	2C`Q`,j”ƒv¶@³¸d×¤5OoSÎMĞZvG9é(QFá¶,-\$D“&’‚U‘Ã”¥•Û\rP‚¤;ibvp88¼AC™|´~C6€§‰©Ñ­ÎF‘›–Š‘ÔW!êÎ pÊÍÛOYD!©fF’^K/1’÷qQv\"oóh‡w–¢tÔ…O	˜œOÃT7:!½ÛØc—‰ğ¾ó{\nv©Ô”§ÈéÁ¡Òe» ?».ØZğbPá3t™0×Bi€‘¦\\P%ø¬Ò:©cx§rE¬kŸK…z\0*ELµÂ T!\$IbŒ£î\\HåÚW\\ŞÈ»P.\\&·GÄ?• ¼\0¨lpŠ¾k@X’Ü¼FuÄ¸_ï“påhcKÜ'-{Ü½œE™½.hDVRÏyï™è!\$2¤¸úÿSìI4ôé¥*Q‡I‹‰C¤CŞÌözyÃJÚFÈ”;XTcÏ‘/Í‘iRà\"2ÁIÚP¥0é¥6q¯şpPĞãâ¯È2aV1¢‚Ñ£|õ%\0èÎ÷ïbÈp2	‚Æ¸Cì®0Ò¦–&¬ÒsˆàïvÒB–.¬±LêÒ\0é	fÕKçÏ2£.z-—ÂB¯wçâ}Õ'‚!Ä&kìlªL2nlò.£Ş#ƒôJD*X¬…M”¼\núa\"ñHì„J#btîÔ!D.nhát\$gD)R)Fârƒ&HğêÊBÜn0¸VP¼p4é#èÙ˜áğœÏCj<n¿\0C\"ğ ØĞ®î\rt‰§\r¨ÚÓĞÆzGvP0ôZÈšÃåê§äøÚM‘Í˜A°l2ğŒ’)ÚØ1^Ìó«‡ÏççZĞnBfW¬Ò'ó'í‡Ëg\"ñ‘eˆâÅ÷p­\0dJ_¶_â±x\"éRù©r7mëË¸üQYÄë­°x2š>£º\$\"qÖ”Ğ–1ßÄ†Ó\$ğ4‚ZÎm0YäÒáâ1ââTÆšÂş®#ù*ıÊ¬¦2²ÎfpÆÃ’\$àÂ@J°’\n’\"Œ¶ÊI\rN¿P•\"ĞógåMU\"†Ğ ît»,·ñQàhÒM&¢Ë&ñÌ3QÑ&† jRq1Š9¨UËQ'ÎxZÑ~ÈCìƒ¦{Q¹¬õ*‡µ©*r+n°¶/Ù+ï€òR™+Ëœö,Èälù!N<Ï\n=ä¢•bğUTP\"’\0?Æ†_àÈ¦‚‘r2ğ\\K¨&[r­‡Ò\"ñ®²I	0qm0Ñ°iu1GæüÃt~,’#\rƒäê2–31,Çòûî)+ğ´\"£ö;/ø»5r„Ù³!)-C6I°F]ÉôƒS+%ónƒ#I4“‚ÏDç*%d¹Â»+'9KÇ,2È»ò2òw8“œvÎĞ\ri/ê”cCê˜HS°ìS¶F`Ääbp\n¨ôÃ;3Ë;¢àì®ğ\$1(ªÎí3‘ ­p-Óté’?b•9’™?îç@3¡@ÚAÓC?TACZDòZ¤ã+RË?%@áå¤ÍÑÂ÷svò”0òÏ6­©BåEDÂ¹Œ¤Ò§†íù X¡J#c¬´Ql%´f;%¬•((@MºjOÃ¥mqÂ@§ò\$¬öÈ,\"ˆöÆ¼®*nüQ·3bB	ÄCMŒJJ’Îg6îO0ºM­–}%7dÍ\0ò\"î\rE%d4ä:`Ğ„áR)\0ª\n€Œ q­!\r\rB÷,øg,ş7c¤Ìí&fÔÿ#S«è¨†0ÁSvÿ’5éY‚kL’93e§L#49cÄp‹¡&3¦gNªZ¥”-Å†º\$muGœz\r@ïòä“RVMQX¥\$\0M¡/D5m	©(òt‘ô!R\rˆ%­(0¿mXK	ää\"ãËX’ÎÂpiÒŠjg++ãŞuÌúgOXJ••	\$ù\n©íÓ5!^ák„ÉÎÊFÆõÜá´o\" /\0ˆñõÅÍviKı\"Ú'&À`@|`–»Ë B×ÛE¦ãğâÖ	\0®¯TA-\\Öâ0¿p¨VCìVµøÆëPpíjÕâ&eHÌ\rI ŞÄFîîpm>¯«Í(ìß˜Z ZpĞ'Ğ¦`åB"; break;
		case "zh": $compressed = "%ÌÂ:\$\nr.®„öŠr/d²È»[8Ğ S™8€r©NT*Ğ®\\9ÓHH¤Z1!S¹VøJè@%9£QÉl]m	F¹U©‡*qQ;CˆÈf4†ãÈ)Î”T9‘w:ÅvåO\"ã¨%CB®r«¤i»½xŸMÆ3‘”Âs2ÍÎbèìV}ˆ¨\n%[L«Ñã`§*9>åSØœË%yèPâ£uâYĞ¾HÇQé)\"–:—‰¥Vdjºæ²dò©ÄK™:ƒt¦RdÚÒ(°t/Ó0•Vc5_§hIG*†å\\­œëµ?M[œÏh9¼¾‚ÙÍ£ÒÂQp”·C„«qˆ…åH\nt+Õ®…B½_âc©ÅS>R\$¡2ø•í{TÖ-Ñ& Ä¡^s„	ÊWÇ9@@©‰nr?JHŸ—kÈâÄIRr–\$ªÜM'\rzü—–å“¸£@Å‚„K´å*<OœÄ¹lt’å£V’ç9XS!%’]	6r‘²¬^’.™8Æå8Jœ¤Ù|r—¥¬Y—nšà(\$QBr“%B¬¬+E¹ÊH¬d)(ìÖœ°R¬L«åé*æÂE0PbÈ6#pÊ9%Ù0“”dB¤åü€W6L´'\nÂéRY=Á\$šÜFƒ@4.£0z\r è8aĞ^õÈ\\0Ñtm\rãÎŒ£p^8.ã˜ï`ŒxDœÏ‰Lsd)ĞS¤ÎÒCÕxÂ)I\0Ú‘eIÍR#¤Á|s”…Ó¦IœÄñäS„¡rã^mîSÒé|¾ÆÅÙusµmkàV”Ç)\"EA(ÈFçAJÂº¯¬(éfT[xaĞC•G)T¥=m‘àU#I•Å!Ê@™UÆs\\²Á{‚eDİúHé Nå¤Ä‹—g1š¤iÎ^•ÈéÒYKr ê/AË²Z‡5ÕvMÉ!(JYQJé!PãD¥[¿”Šbˆ™…!ÅÛß’g)xûK—|ŞØ\$>’|rsYy”Ê~ÄqYO>AG1LA::ÛìR s•\\ûÆj8¬–ñ£±xÒõG)]=|qu|ru*;ÄïZ–gš§\0Ú:c ATÚ¨è:Cp@0ùCä9#ÈyoËçˆ9‡ÂŞ7ÃHÏä¾¾½jô¾0ÌB±ò+D´æW±?aK}‘4PJ©­ğ6ƒ’ÆYE2eBür¡R:‡uéı@¥ÁH,G@´GB°Êš£‚!ÉšTèh›°ô !‡H’1â@G-²+ßÁ‹+MŒE	øWİy¯‚|tD‡ÉXQJ1G(6+Å¸èt	‘\"MJÔGb±TªµZ«ÕŠ³Vªİ\\‡uv¯aêÀXKcG¶C i{k8Ãv+ñn E‹Z:Eµ\"tQáø—‹\"\"høœ¤cÑÜ?ğ˜ßÂõRI_Nè\\rÇñ~!ÑÙ;‰ª°2ªå`¬•¢¶W\né^CÅ~°VÅXà:,Pç–2Ï{i¢tS`–pâÌJ’tN+„æàr‘\n9D`–\"üBÇ¾\$Xqõ ô\"kÅh¤ÂLF¶‚d¿J4+dB~‘Î&ÅÂsŠ‰×\nÔ!”sÈ”S™kÌ¡–•¯ÜÏi3T*¢@\$\0A<ÑHåó\$TF¢q¤A\n\$„˜”<9„p¥JÀsˆ—mÇ8«ôQšóP¹ÄDåA¼²öò!E,ä‘bô™dF.è©`b€^A³…@…yfâbŠñxÇÅ¢›\"ˆLNC—E5\" 0¦‚0 \"â’ŠÎò6†¤ –FÂ<H®\"LfG(…\$pte­#Èñ \$T,“’’VÇ0®¬”´4ÉDó\"éü¹uâ'\rƒ\$:*tÚqÕğr Ut@h€9…ˆ‚DY¤	Í4c„r… (ğ¦'ú¤\$,„R\nzíp&BEÚ¾Ø…/Ù5z|ƒ¤B´¦˜İ*\$d”‹ \$xF\n@À\"`X[q	„ô—škM¨MÕÒ‡ò)²i£˜P&±^.@PO	À€*…\0ˆB E¼7Œ\"P˜oJCõÄZ§4ë@„¾Ì¨J‹ôú)™‰€ñIÜwÍ%(!àEQw‚Ä^*Âœ]ãORİ«„(Å(Ñ34­\n\\.ísb\nãG+ÄÇÀ_‰·@è„@—_L2‚¬\"õa¡Ì‚‘×]‚2©!Ò±?…\0¥ê(ƒœrs=bÜß‘Ûº„ï3´¤É”’qB9År8‘¢Éq“Òzğñï	½^V`uê7)2èe0h\ráÑ•	Ú-ƒ¹Ú`­uú—‚ü_ÇÀÌ—Üxi˜ğ3 ŞJ³BoJ\r¾¯QrĞE›#¢èHa6§\0‡pÄt[•ñ(	\rÙ4!é\0ÆòC.±€¼†=lôK	«‰±7b†µö‘Øš;H-%›\0b&0M‹¢Ò&‡H“Ô@ÓRg\\ì.ã«qié‚´İŒ»“[Áb6|!ĞC	˜ÿŸDk÷¨¯Ô.½\0‰–ÃÁx ßà‚wİò(§dî-\"0sB…NÃ¸ ¡”K¦b9f¤ğ6b&Ü7cÛ„LíâE#ˆc+ÁóÚ…¼'‘Â®CÁ[dÀæ¨¾’ä@D“›¬|U]Õ²Â¸e\\úé”^-²aÁ£¥†Â€‘è¶¢À\nÖ†.Ä+^]k·~ C#—èIC˜\\L\\>UŒéŸÙB=‹‡aîÀs|×¾RoMÿsÔølY`ƒKŞ…r-îİÃ4œnù§W×†ğhÈD;/Y+t1‚ğJÍ1Ê”’”eìÇâôÏZı‹¸¯\\C0„õïÍ˜Äcâ¶ Óp#MyF/é‚lëI0ö³ˆm\rÙH6,PÎAŒN„X\0§´ƒ&’•»Æfœ3ˆ¼\\&n—Ûë‰>ù¹şï‡ú{ğø¿;‡ô‹ûş7°7qú¼_°pÿ¼G¡‘` ÏÑBsî…Ññøjÿ°áæÎ>âÎ0¯ÖÍ-b»OÄÓĞÖoÖvPî¢xÔ†*¡\$œmN»ˆ0Æ!Ê˜‰Œ\$Œ¨6‰¾œ! œbVv¯n,%N(¢ËmRb¢éŠ&Š°¡p<ª`öO(;¢6›ĞR´PAÄf8ğ&ÔÍPåğşC¶-^tÌÆşM_Ğ=ĞªKĞ\"ı°²ÇòıÑMÌoÎR¡tu2ş/Æ_H\r¶`PÚ'É\reí'RÜğìl¦ö2Ä]ãlLá^q\\álhH\"éª9!Î­ıâÃïôÅçG¥õ/øj0ŞÚÁZÿgEõruÆnæ¨‘ì^v/ÛFpÜè/ãƒ6ĞV,QGJÆ12tf+…íQqğìÅ\r­O©±g1“õqP`QœÖ ÊÖåV.í¢Ï”,\0àÍÂùQ¨\r¬Q°ygøQñ¼ùÍ¬y…Šî×€È/r” å\rñ±m‰…öÙ1şüÑ‰­†M°æÓÒÙDÛçfò!®¢H…1ôÍ,+r#!oMÓ#£	îÒ\0ÈsÑ\$jª Çãn1tì,âˆIÌ^ãã¦Çî¬ºâ‚J#¨ ÁBƒ¢¬rlLR`¦\0âŠ'\0Î2…AÌÁzà† Ø;ªÂKh¡>ˆódœÏäEÎm‰Œ›¢°\n ¨ÀZ°OI\0n%Š|4ª!bÌ¦h#âçm	Ë(†ú2¡îĞ;#C,‚Êí#@Û-¶¢0¨ê\$fòÕáĞ¦n7¨2v£2“,Ìë2dÄ­)Ô#¡-Á<m\$bì\nêdñï©2¡XÁÓaI'!m6¯	2ğáx(7£8Ğ0I°Ä_ğ\\8ÊZgíA3M6òÍÈAÒ¿«şÓ-Bò“¦¿ÁtÀT¦D€INfù¥ÎÎ@¬ Æ ê\r¨ì!~˜,#¡G4Ãşô%âÔ­:\$\$ë5“\\vdÁÒ’®¤—º>{6l(1“nlƒàÄ*sæÿó«Ä ƒÕ\"d‚ùAL"; break;
		case "zh-tw": $compressed = "%ÌÂ:\$\ns¡.ešUÈ¸E9PK72©(æP¢h)Ê…@º:i	‹Æaè§Je åR)Ü«{º	Nd(ÜvQDCÑ®UjaÊœTOABÀPÀb2£a¸àr\nr/Wît¢¡Ğ€BºT)ç*yX^¨ê%Ó•\\šr¥ÑÎõâ|I7ÎFS	ÌË99‹SùTB\$³r­ÖNu²MĞ¢U¹P)Êå&9G'Üª{;™d’s'.…šÌ–Lº9hëo^^+ğieƒ•DÁçô:=.ŸR¡FRœÈ%F{A¢‘,\\¨õ{™XŠs&Öšuƒ¥\0™r zM6£U¬!TDÇ‡ÇE‡©œë•ãtŒ×l6N_“ÓÔÛ'¡è¸zÎVÊÁ~N¾ÅÁZRZRGATO\$DĞ­«¬8UäùJt‘…|R)Nã|r—EZ„siZ¥yµ—‘	Vş+L«ör‘º>[!åkúì‘g1'¤)ÌT'9jB0, 1/:Œ¤8D©p¢ì.R´\$ùÌLGI,I£Åi.‡–JÃŞëJÉ„Å‘Ğ[¾eÉ|¬’°kzÔD…YÎY—rQb¬…ÓB¡“%B<\\gA2»E±yD^ONë±¡Év”¬`\\…É\nsÓTà ŒƒhÒ7£‘ÒP	|\\±DTZ\$EQÊJ‘î3wHÆ	)	O‘*èFƒ@4/c0z\r è8aĞ^öÈ\\0Õ5]Z\rãÎŒ£p^8/£˜ïpŒxDÇARŠd)ĞSn³RQ”!à^0‡ÊQÒ@—b RÄWœå!u%39ÑAs„;ø¦,Š”W¹-Œ¿\r½_Q“etV”Ç)\"EA(ÈCÈè2…˜RÁ•„\$İ…,k*Î‘gANQÁ¤=tE’¸ÈÛáU…dìÒ˜\nIèñ^œ¥á…d1ÊH N&k9È]—g1GÍÇ9{“Äq%kij†CA\0Q¥ÅÁ7›¥©zŞ\nƒ(ğ:I*[Ä…2=¤+\$â<B«ÓÛ~â‘ÄÌGëb˜¢&Q{³Á‡1IÌK;é)cD¦/ËO½o_K;ø†%Õx|gŒ5}¶'„O½ó:çğ(APEîˆñtGÇÉÈ]?…ÙlCµ1ËêåÒ=Üœıß-ŒøÇxTxXV	ƒe£˜@6£ŸdŞØè:Cp@0şÃä9#ÍüÀğ`ø!†ğÜƒHg~•ÿƒã\0Y€0CœS+Ç0ˆÊìOÁrt È–ƒ	¤d’„!Jy¬d6@ä`Î ébˆ×	§!ŠR¤SoeL‹´8!	€±ÂTKœâ•˜&ic„fNYz8Ã¤Ha|#H‡«ÈS!KÄTT¯dáA>:`+(PT*…T«”MwbÍQ\\M”0ˆ'dH¬•–³VzÑZkUk­î¶Öìp\\‰r.`Éƒt\r0wáÌ#Xˆ¬ÅĞD„°‡1‚ÉWˆè\"`iJ\na”9‡9&ı’ŒIK±É|Å£©”áJ!êm#	b Dr]'±ùfUœ´’ÔZËam-ÈŞ·×\nã\\«‘Ã®Y]%|‰É+`À–\$0J\\\$ËsšSê„B½q-*¥d×–ü°Š	:d á•8B´R\$!äI˜‡(ç/XÃÌ¨çi\\HL9„0‘HBjCñËÇ(Ÿæ¨à™rBá\"EHé%%›“\"[8»O\nÙÍ‹E„€H\n‚ÂjH!\$á:”òéZòBHÎ°£n\"•Î\"gÏ£\"‚¨†\"# Vˆ“„q@¼JâR¬%€¤Ñé»GçhsŠ•(âÇ_G-1z9„À¯&b8ttX¯ˆdr‰º™Gªp•ª˜0¦‚0 ¼5®R{6âÊµ6Š^bBjÇ(–È„[OM^LÃ˜WX†.\"É5¡âp@d ‰›gæƒ\nšeÇ(¦C˜O	Ã†*¹º4T\\OFå¼¬¥–›c˜Xˆ)h:D¸œLB\$sOÉJ:Dè¢)A@'…0¨\0¤WDÄ·Ñ6Lê›–„%~v›{kÖO-íB«ŠŠØÙĞØ€Q\$#@ JÌ=®gÎx&ƒÓq\"…Pã•D×9sn –‚ú)ˆÑj`Å\0¡&i¼'„à@B€D!P\"€¬: E	3†t/J£œ‘‘áæ’³_ÆxÕÅ8Átãš“#6fÔÛŸ³úĞ	+ ¢ì¬’È@§‡ÂeQúŸ|]‚%D9dÈ˜¤p‚²£|íQğŠ·¶îŞ\\A¢dJq ‹Ş;t…f­Šã •ˆ@‹˜B–x?QÄA¡…ĞMAó¬#âM0¦TÑ\"ShP`!ì?HY©VDJ¦Øg\r©S|Æp4A¢¢¨\nä³/ÕAÀŞ·+/°:†uVÀPs/A–k€ŞE°k\rï»ÑjÄLä9\"¦w1â%Œªc¢™çGÆ]ÃphU¡¤:> Ìƒxm!ĞQ5”ò9ûuÃŸrnb±…0º!’¤vº7P¹ÈvÓá<+…ÂÚa¯\\2÷\$KøcàA­Šqqe0bŠ¯qİğî ¢vÆÚ[sX³\0ò§•¢¨,AÕ¡Ë4Ø¼k-m„fwº#eìÉªÕÒÜFC	™\r/+´eùà¥çÖP®	ô8‡…ReÀ€‚ZËèá£Ts óà@ÂZÇétpå£¡z,GHŸç\nái‘\"èÅØ¤­”fœTb•{^3©œ èN×U<üöÎ¢àQÀ¶ê™àÂDÈğ¾?X(N(…(å.	PŞ7ˆø]!ß…jÂë£¥ñd­ğ­Dyz°¶çê\$G@@›-Ø !ùƒ‡*ƒ_Vk“”\"\rE/)H( ®J&<È ”ÛÅ‘Ñ:fÑ‘Ğ«7nS!ß5’Û~úbŒdèøÙQñå·ifVøbß{OşÇÅ_ä;ÖccE¹’iªÄT\nÖ¡ªĞY€è!¯ß`g¢ÌÿÊÿĞÿÌtıÂ<¶<ü-ÒbÍØÜ¡x¨rİ°\",BÉ\0 ‚\r€Î\\-¸\r\rÄ€­h\r Ü×zq¢<ı¬j€ Ü¸•í<X¬º<o¶D ”‰l6‡DÇLÊù&Íw¯ÄuĞ~úğdÕ‡ÆxPxıØçnù‚voÊ|˜Ù+p.TìÌÆ‡yo†Ÿê´ê€øÊëîÂìpjËä&\$ÂÒÂĞ§]\rı\rák	,Ñİ.ßÍâ%är¡\$>\rê±)èUá#án7j¡Šôa,gÅ oœŞÇšÀÊÄZ¬(!ÁpÂÌ¡\"Lx7bŠ hTß°öÂñ/Í®œÃ :Mö6ãœÅäŞJ0c†<;ğyq€Ú‘…\r/Ñ­ª<zåñ”Ô½gz!m-ófÏ±Í°zIuAs§§¤gFÇbgl¢ìÁ^kj-Áƒ\"^£ê(°¤FéÆ,(!‡—_ ñ¾ß.Nk´vE^%â‹ î\\Frår&_%`<’\$¯¢È\"q'\0\$£\rL¨ÎDs ’)’KÁ\$/!1ÿ%\rNıY2D’>ı1¶åòo%Ç]&ò/'Î®Ybúf\rx~ÀÈÜ\0à\rb0Rƒ(`Ñ( Å(çî…¥[) )à×(‡Õ*eÊ\rvá\0È0P2f`å,&fhN.TQ1ıN--Î#\$Ã·.rŞ<Ÿ-.âP•/\"0dÀLDÉÒCDj+«Ù.1Ã1\"¼,ì¡2.	úÛ@ÈxĞ¶kfâĞ#{aDAnï¦\rÀiâ	X+À2”·oDD!H`áF¢°¡bîä€!k6Féd€ú„ätkÎÄ8A¥zà†…ÀØ>óš?£öç‚^;F MŠ9	äÑW2AP\n ¨ÀZâFxzª¸îê8©¢!¦¤`Ï|ÚğaK¢ZöÃäBjöãbämßÓ¼2í0S”‰n[@ê¸G¦š\$,µ¨®?©J0aÏcDvË{&úTjÌû°jz,—D:|d¢û!Ñ6aÑ6¯û*EgWAcíŠ<QqÎŠÌ¡ÌlfÊ#Ğ\"ş0^0JÇíÇ’qpLa\nÊó, ïª\\Íé8\nÀÂ`ê Ú”ô\"+#Fí¢N‚ÿ‹xŞmğ¢áRöT<{TEDŒ¤¨¤v,ÊA´FÊW6“l^á&3¦åB<)ÌŠ-ï=0NÀ"; break;
	}
	$translations = array();
	foreach (explode("\n", lzw_decompress($compressed)) as $val) {
		$translations[] = (strpos($val, "\t") ? explode("\t", $val) : $val);
	}
	return $translations;
}

?>
<?php
// this could be interface when "Db extends \mysqli" can have compatible type declarations (PHP 7)
// interfaces can include properties only since PHP 8.4
abstract class SqlDb {
	/** @var Db */ static $instance;

	/** @var string */ public $extension; // extension name
	/** @var string */ public $flavor = ''; // different vendor with the same API, e.g. MariaDB; usually stays empty
	/** @var string */ public $server_info; // server version
	/** @var int */ public $affected_rows = 0; // number of affected rows
	/** @var string */ public $info = ''; // see https://php.net/mysql_info
	/** @var int */ public $errno = 0; // last error code
	/** @var string */ public $error = ''; // last error message
	/** @var Result|bool */ protected $multi; // used for multiquery

	/** Connect to server
	* @return string error message
	*/
	abstract function attach(string $server, string $username, string $password): string;

	/** Quote string to use in SQL
	* @return string escaped string enclosed in '
	*/
	abstract function quote(string $string): string;

	/** Select database
	* @return bool boolish
	*/
	abstract function select_db(string $database);

	/** Send query
	* @return Result|bool
	*/
	abstract function query(string $query, bool $unbuffered = false);

	/** Send query with more resultsets
	* @return Result|bool
	*/
	function multi_query(string $query) {
		return $this->multi = $this->query($query);
	}

	/** Get current resultset
	* @return Result|bool
	*/
	function store_result() {
		return $this->multi;
	}

	/** Fetch next resultset */
	function next_result(): bool {
		return false;
	}
}

?>
<?php
// PDO can be used in several database drivers
if (extension_loaded('pdo')) {
	abstract class PdoDb extends SqlDb {
		protected \PDO $pdo;

		/** Connect to server using DSN
		* @param mixed[] $options
		* @return string error message
		*/
		function dsn(string $dsn, string $username, string $password, array $options = array()): string {
			$options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_SILENT;
			$options[\PDO::ATTR_STATEMENT_CLASS] = array('Adminer\PdoResult');
			try {
				$this->pdo = new \PDO($dsn, $username, $password, $options);
			} catch (\Exception $ex) {
				return $ex->getMessage();
			}
			$this->server_info = @$this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
			return '';
		}

		function quote(string $string): string {
			return $this->pdo->quote($string);
		}

		function query(string $query, bool $unbuffered = false) {
			/** @var Result|bool */
			$result = $this->pdo->query($query);
			$this->error = "";
			if (!$result) {
				list(, $this->errno, $this->error) = $this->pdo->errorInfo();
				if (!$this->error) {
					$this->error = lang(23);
				}
				return false;
			}
			$this->store_result($result);
			return $result;
		}

		function store_result($result = null) {
			if (!$result) {
				$result = $this->multi;
				if (!$result) {
					return false;
				}
			}
			if ($result->columnCount()) {
				$result->num_rows = $result->rowCount(); // is not guaranteed to work with all drivers
				return $result;
			}
			$this->affected_rows = $result->rowCount();
			return true;
		}

		function next_result(): bool {
			/** @var PdoResult|bool */
			$result = $this->multi;
			if (!is_object($result)) {
				return false;
			}
			$result->_offset = 0;
			return @$result->nextRowset(); // @ - PDO_PgSQL doesn't support it
		}
	}

	class PdoResult extends \PDOStatement {
		public $_offset = 0, $num_rows;

		function fetch_assoc() {
			return $this->fetch_array(\PDO::FETCH_ASSOC);
		}

		function fetch_row() {
			return $this->fetch_array(\PDO::FETCH_NUM);
		}

		private function fetch_array(int $mode) {
			$return = $this->fetch($mode);
			return ($return ? array_map(array($this, 'unresource'), $return) : $return);
		}

		private function unresource($val) {
			return (is_resource($val) ? stream_get_contents($val) : $val);
		}

		function fetch_field(): \stdClass {
			$row = (object) $this->getColumnMeta($this->_offset++);
			$type = $row->pdo_type;
			$row->type = ($type == \PDO::PARAM_INT ? 0 : 15);
			$row->charsetnr = ($type == \PDO::PARAM_LOB || (isset($row->flags) && in_array("blob", (array) $row->flags)) ? 63 : 0);
			return $row;
		}

		function seek($offset) {
			for ($i=0; $i < $offset; $i++) {
				$this->fetch();
			}
		}
	}
}

?>
<?php
/** Add or overwrite a driver */
function add_driver(string $id, string $name): void {
	SqlDriver::$drivers[$id] = $name;
}

/** Get driver name */
function get_driver(string $id): ?string {
	return SqlDriver::$drivers[$id];
}

abstract class SqlDriver {
	/** @var Driver */ static $instance;
	/** @var string[] */ static $drivers = array(); // all available drivers
	/** @var list<string> */ static $extensions = array(); // possible extensions in the current driver
	/** @var string */ static $jush; // JUSH identifier

	/** @var Db */ protected $conn;
	/** @var int[][] */ protected $types = array(); // [$group => [$type => $maximum_unsigned_length, ...], ...]
	/** @var string[] */ public $insertFunctions = array(); // ["$type|$type2" => "$function/$function2"] functions used in edit and insert
	/** @var string[] */ public $editFunctions = array(); // ["$type|$type2" => "$function/$function2"] functions used in edit only
	/** @var list<string> */ public $unsigned = array(); // number variants
	/** @var list<string> */ public $operators = array(); // operators used in select
	/** @var list<string> */ public $functions = array(); // functions used in select
	/** @var list<string> */ public $grouping = array(); // grouping functions used in select
	/** @var string */ public $onActions = "RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT"; // used in foreign_keys()
	/** @var list<string> */ public $partitionBy = array(); // supported partitioning types
	/** @var string */ public $inout = "IN|OUT|INOUT"; // used in routines
	/** @var string */ public $enumLength = "'(?:''|[^'\\\\]|\\\\.)*'"; // regular expression for parsing enum lengths
	/** @var list<string> */ public $generated = array(); // allowed types of generated columns

	/** Connect to the database
	* @return Db|string string for error
	*/
	static function connect(string $server, string $username, string $password) {
		$connection = new Db;
		return ($connection->attach($server, $username, $password) ?: $connection);
	}

	/** Create object for performing database operations */
	function __construct(Db $connection) {
		$this->conn = $connection;
	}

	/** Get all types
	* @return int[] [$type => $maximum_unsigned_length, ...]
	*/
	function types(): array {
		return call_user_func_array('array_merge', array_values($this->types));
	}

	/** Get structured types
	* @return list<string>[]|list<string> [$description => [$type, ...], ...]
	*/
	function structuredTypes(): array {
		return array_map('array_keys', $this->types);
	}

	/** Get enum values
	* @param Field $field
	* @return string|void
	*/
	function enumLength(array $field) {
	}

	/** Function used to convert the value inputted by user
	* @param Field $field
	* @return string|void
	*/
	function unconvertFunction(array $field) {
	}

	/** Select data from table
	* @param list<string> $select result of adminer()->selectColumnsProcess()[0]
	* @param list<string> $where result of adminer()->selectSearchProcess()
	* @param list<string> $group result of adminer()->selectColumnsProcess()[1]
	* @param list<string> $order result of adminer()->selectOrderProcess()
	* @param int $limit result of adminer()->selectLimitProcess()
	* @param int $page index of page starting at zero
	* @param bool $print whether to print the query
	* @return Result|false
	*/
	function select(string $table, array $select, array $where, array $group, array $order = array(), int $limit = 1, ?int $page = 0, bool $print = false) {
		$is_group = (count($group) < count($select));
		$query = adminer()->selectQueryBuild($select, $where, $group, $order, $limit, $page);
		if (!$query) {
			$query = "SELECT" . limit(
				($_GET["page"] != "last" && $limit && $group && $is_group && JUSH == "sql" ? "SQL_CALC_FOUND_ROWS " : "") . implode(", ", $select) . "\nFROM " . table($table),
				($where ? "\nWHERE " . implode(" AND ", $where) : "") . ($group && $is_group ? "\nGROUP BY " . implode(", ", $group) : "") . ($order ? "\nORDER BY " . implode(", ", $order) : ""),
				$limit,
				($page ? $limit * $page : 0),
				"\n"
			);
		}
		$start = microtime(true);
		$return = $this->conn->query($query);
		if ($print) {
			echo adminer()->selectQuery($query, $start, !$return);
		}
		return $return;
	}

	/** Delete data from table
	* @param string $queryWhere " WHERE ..."
	* @param int $limit 0 or 1
	* @return Result|bool
	*/
	function delete(string $table, string $queryWhere, int $limit = 0) {
		$query = "FROM " . table($table);
		return queries("DELETE" . ($limit ? limit1($table, $query, $queryWhere) : " $query$queryWhere"));
	}

	/** Update data in table
	* @param string[] $set escaped columns in keys, quoted data in values
	* @param string $queryWhere " WHERE ..."
	* @param int $limit 0 or 1
	* @return Result|bool
	*/
	function update(string $table, array $set, string $queryWhere, int $limit = 0, string $separator = "\n") {
		$values = array();
		foreach ($set as $key => $val) {
			$values[] = "$key = $val";
		}
		$query = table($table) . " SET$separator" . implode(",$separator", $values);
		return queries("UPDATE" . ($limit ? limit1($table, $query, $queryWhere, $separator) : " $query$queryWhere"));
	}

	/** Insert data into table
	* @param string[] $set escaped columns in keys, quoted data in values
	* @return Result|bool
	*/
	function insert(string $table, array $set) {
		return queries("INSERT INTO " . table($table) . ($set
			? " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")"
			: " DEFAULT VALUES"
		) . $this->insertReturning($table));
	}

	/** Get RETURNING clause for INSERT queries (PostgreSQL specific) */
	function insertReturning(string $table): string {
		return "";
	}

	/** Insert or update data in table
	* @param list<string[]> $rows of arrays with escaped columns in keys and quoted data in values
	* @param int[] $primary column names in keys
	* @return Result|bool
	*/
	function insertUpdate(string $table, array $rows, array $primary) {
		return false;
	}

	/** Begin transaction
	* @return Result|bool
	*/
	function begin() {
		return queries("BEGIN");
	}

	/** Commit transaction
	* @return Result|bool
	*/
	function commit() {
		return queries("COMMIT");
	}

	/** Rollback transaction
	* @return Result|bool
	*/
	function rollback() {
		return queries("ROLLBACK");
	}

	/** Return query with a timeout
	* @param int $timeout seconds
	* @return string|void null if the driver doesn't support query timeouts
	*/
	function slowQuery(string $query, int $timeout) {
	}

	/** Convert column to be searchable
	* @param string $idf escaped column name
	* @param array{op:string, val:string} $val
	* @param Field $field
	*/
	function convertSearch(string $idf, array $val, array $field): string {
		return $idf;
	}

	/** Convert value returned by database to actual value
	* @param Field $field
	*/
	function value(?string $val, array $field): ?string {
		return (method_exists($this->conn, 'value') ? $this->conn->value($val, $field) : $val);
	}

	/** Quote binary string */
	function quoteBinary(string $s): string {
		return q($s);
	}

	/** Get warnings about the last command
	* @return string|void HTML
	*/
	function warnings() {
	}

	/** Get help link for table
	* @return string|void relative URL
	*/
	function tableHelp(string $name, bool $is_view = false) {
	}

	/** Get tables this table inherits from
	* @return list<string>
	*/
	function inheritsFrom(string $table): array {
		return array();
	}

	/** Get inherited tables
	* @return list<string>
	*/
	function inheritedTables(string $table): array {
		return array();
	}

	/** Get partitions info
	* @return Partitions
	*/
	function partitionsInfo(string $table): array {
		return array();
	}

	/** Check if C-style escapes are supported */
	function hasCStyleEscapes(): bool {
		return false;
	}

	/** Get supported engines
	* @return list<string>
	*/
	function engines(): array {
		return array();
	}

	/** Check whether table supports indexes
	* @param TableStatus $table_status
	*/
	function supportsIndex(array $table_status): bool {
		return !is_view($table_status);
	}

	/** Return list of supported index algorithms, first one is default
	 * @param TableStatus $tableStatus
	 * @return list<string>
	 */
	function indexAlgorithms(array $tableStatus): array {
		return array();
	}

	/** Get defined check constraints
	* @return string[] [$name => $clause]
	*/
	function checkConstraints(string $table): array {
		// MariaDB contains CHECK_CONSTRAINTS.TABLE_NAME, MySQL and PostrgreSQL not
		return get_key_vals("SELECT c.CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS c
JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME
WHERE c.CONSTRAINT_SCHEMA = " . q($_GET["ns"] != "" ? $_GET["ns"] : DB) . "
AND t.TABLE_NAME = " . q($table) . "
AND CHECK_CLAUSE NOT LIKE '% IS NOT NULL'", $this->conn); // ignore default IS NOT NULL checks in PostrgreSQL
	}

	/** Get all fields in the current schema
	* @return array<list<array{field:string, null:bool, type:string, length:?numeric-string}>> optionally also 'primary'
	*/
	function allFields(): array {
		$return = array();
		if (DB != "") {
			foreach (
				get_rows("SELECT TABLE_NAME AS tab, COLUMN_NAME AS field, IS_NULLABLE AS nullable, DATA_TYPE AS type, CHARACTER_MAXIMUM_LENGTH AS length" . (JUSH == 'sql' ? ", COLUMN_KEY = 'PRI' AS `primary`" : "") . "
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = " . q($_GET["ns"] != "" ? $_GET["ns"] : DB) . "
ORDER BY TABLE_NAME, ORDINAL_POSITION", $this->conn) as $row
			) {
				$row["null"] = ($row["nullable"] == "YES");
				$return[$row["tab"]][] = $row;
			}
		}
		return $return;
	}
}

?>
<?php
add_driver("sqlite", "SQLite");

if (isset($_GET["sqlite"])) {
	define('Adminer\DRIVER', "sqlite");

	if (class_exists("SQLite3") && $_GET["ext"] != "pdo") {
		abstract class SqliteDb extends SqlDb {
			public $extension = "SQLite3";
			private $link;

			function attach(string $filename, string $username, string $password): string {
				$this->link = new \SQLite3($filename);
				$version = $this->link->version();
				$this->server_info = $version["versionString"];
				return '';
			}

			function query(string $query, bool $unbuffered = false) {
				$result = @$this->link->query($query);
				$this->error = "";
				if (!$result) {
					$this->errno = $this->link->lastErrorCode();
					$this->error = $this->link->lastErrorMsg();
					return false;
				} elseif ($result->numColumns()) {
					return new Result($result);
				}
				$this->affected_rows = $this->link->changes();
				return true;
			}

			function quote(string $string): string {
				return (is_utf8($string)
					? "'" . $this->link->escapeString($string) . "'"
					: "x'" . first(unpack('H*', $string)) . "'"
				);
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 0;

			function __construct($result) {
				$this->result = $result;
			}

			function fetch_assoc() {
				return $this->result->fetchArray(SQLITE3_ASSOC);
			}

			function fetch_row() {
				return $this->result->fetchArray(SQLITE3_NUM);
			}

			function fetch_field(): \stdClass {
				$column = $this->offset++;
				$type = $this->result->columnType($column);
				return (object) array(
					"name" => $this->result->columnName($column),
					"type" => ($type == SQLITE3_TEXT ? 15 : 0),
					"charsetnr" => ($type == SQLITE3_BLOB ? 63 : 0), // 63 - binary
				);
			}

			function __destruct() {
				$this->result->finalize();
			}
		}

	} elseif (extension_loaded("pdo_sqlite")) {
		abstract class SqliteDb extends PdoDb {
			public $extension = "PDO_SQLite";

			function attach(string $filename, string $username, string $password): string {
				return $this->dsn(DRIVER . ":$filename", "", "");
			}
		}

	}

	if (class_exists('Adminer\SqliteDb')) {
		class Db extends SqliteDb {
			function attach(string $filename, string $username, string $password): string {
				parent::attach($filename, $username, $password);
				$this->query("PRAGMA foreign_keys = 1");
				$this->query("PRAGMA busy_timeout = 500");
				return '';
			}

			function select_db(string $filename): bool {
				if (is_readable($filename) && $this->query("ATTACH " . $this->quote(preg_match("~(^[/\\\\]|:)~", $filename) ? $filename : dirname($_SERVER["SCRIPT_FILENAME"]) . "/$filename") . " AS a")) {
					return !self::attach($filename, '', '');
				}
				return false;
			}
		}
	}



	class Driver extends SqlDriver {
		static $extensions = array("SQLite3", "PDO_SQLite");
		static $jush = "sqlite";

		protected $types = array(array("integer" => 0, "real" => 0, "numeric" => 0, "text" => 0, "blob" => 0));

		public $insertFunctions = array(); // "text" => "date('now')/time('now')/datetime('now')",
		public $editFunctions = array(
			"integer|real|numeric" => "+/-",
			// "text" => "date/time/datetime",
			"text" => "||",
		);

		public $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL"); // REGEXP can be user defined function
		public $functions = array("hex", "length", "lower", "round", "unixepoch", "upper");
		public $grouping = array("avg", "count", "count distinct", "group_concat", "max", "min", "sum");

		static function connect(string $server, string $username, string $password) {
			if ($password != "") {
				return lang(24);
			}
			return parent::connect(":memory:", "", "");
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			if (min_version(3.31, 0, $connection)) {
				$this->generated = array("STORED", "VIRTUAL");
			}
		}

		function structuredTypes(): array {
			return array_keys($this->types[0]);
		}

		function insertUpdate(string $table, array $rows, array $primary) {
			$values = array();
			foreach ($rows as $set) {
				$values[] = "(" . implode(", ", $set) . ")";
			}
			return queries("REPLACE INTO " . table($table) . " (" . implode(", ", array_keys(reset($rows))) . ") VALUES\n" . implode(",\n", $values));
		}

		function tableHelp(string $name, bool $is_view = false) {
			if ($name == "sqlite_sequence") {
				return "fileformat2.html#seqtab";
			}
			if ($name == "sqlite_master") {
				return "fileformat2.html#$name";
			}
		}

		function checkConstraints(string $table): array {
			preg_match_all('~ CHECK *(\( *(((?>[^()]*[^() ])|(?1))*) *\))~', get_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table), 0, $this->conn), $matches); //! could be inside a comment
			return array_combine($matches[2], $matches[2]);
		}

		function allFields(): array {
			$return = array();
			foreach (tables_list() as $table => $type) {
				foreach (fields($table) as $field) {
					$return[$table][] = $field;
				}
			}
			return $return;
		}
	}



	function idf_escape($idf) {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function get_databases($flush) {
		return array();
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return (preg_match('~^INTO~', $query) || get_val("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')")
			? limit($query, $where, 1, 0, $separator)
			: " $query WHERE rowid = (SELECT rowid FROM " . table($table) . $where . $separator . "LIMIT 1)" //! use primary key in tables with WITHOUT rowid
		);
	}

	function db_collation($db, $collations) {
		return get_val("PRAGMA encoding"); // there is no database list so $db == DB
	}

	function logged_user() {
		return get_current_user(); // should return effective user
	}

	function tables_list() {
		return get_key_vals("SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view') ORDER BY (name = 'sqlite_sequence'), name");
	}

	function count_tables($databases) {
		return array();
	}

	function table_status($name = "") {
		$return = array();
		foreach (get_rows("SELECT name AS Name, type AS Engine, 'rowid' AS Oid, '' AS Auto_increment FROM sqlite_master WHERE type IN ('table', 'view') " . ($name != "" ? "AND name = " . q($name) : "ORDER BY name")) as $row) {
			$row["Rows"] = get_val("SELECT COUNT(*) FROM " . idf_escape($row["Name"]));
			$return[$row["Name"]] = $row;
		}
		foreach (get_rows("SELECT * FROM sqlite_sequence" . ($name != "" ? " WHERE name = " . q($name) : ""), null, "") as $row) {
			$return[$row["name"]]["Auto_increment"] = $row["seq"];
		}
		return $return;
	}

	function is_view($table_status) {
		return $table_status["Engine"] == "view";
	}

	function fk_support($table_status) {
		return !get_val("SELECT sqlite_compileoption_used('OMIT_FOREIGN_KEY')");
	}

	function fields($table) {
		$return = array();
		$primary = "";
		foreach (get_rows("PRAGMA table_" . (min_version(3.31) ? "x" : "") . "info(" . table($table) . ")") as $row) {
			$name = $row["name"];
			$type = strtolower($row["type"]);
			$default = $row["dflt_value"];
			$return[$name] = array(
				"field" => $name,
				"type" => (preg_match('~int~i', $type) ? "integer" : (preg_match('~char|clob|text~i', $type) ? "text" : (preg_match('~blob~i', $type) ? "blob" : (preg_match('~real|floa|doub~i', $type) ? "real" : "numeric")))),
				"full_type" => $type,
				"default" => (preg_match("~^'(.*)'$~", $default, $match) ? str_replace("''", "'", $match[1]) : ($default == "NULL" ? null : $default)),
				"null" => !$row["notnull"],
				"privileges" => array("select" => 1, "insert" => 1, "update" => 1, "where" => 1, "order" => 1),
				"primary" => $row["pk"],
			);
			if ($row["pk"]) {
				if ($primary != "") {
					$return[$primary]["auto_increment"] = false;
				} elseif (preg_match('~^integer$~i', $type)) {
					$return[$name]["auto_increment"] = true;
				}
				$primary = $name;
			}
		}
		$sql = get_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table));
		$idf = '(("[^"]*+")+|[a-z0-9_]+)';
		preg_match_all('~' . $idf . '\s+text\s+COLLATE\s+(\'[^\']+\'|\S+)~i', $sql, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
			if ($return[$name]) {
				$return[$name]["collation"] = trim($match[3], "'");
			}
		}
		preg_match_all('~' . $idf . '\s.*GENERATED ALWAYS AS \((.+)\) (STORED|VIRTUAL)~i', $sql, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
			$return[$name]["default"] = $match[3];
			$return[$name]["generated"] = strtoupper($match[4]);
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$connection2 = connection($connection2);
		$return = array();
		$sql = get_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table), 0, $connection2);
		if (preg_match('~\bPRIMARY\s+KEY\s*\((([^)"]+|"[^"]*"|`[^`]*`)++)~i', $sql, $match)) {
			$return[""] = array("type" => "PRIMARY", "columns" => array(), "lengths" => array(), "descs" => array());
			preg_match_all('~((("[^"]*+")+|(?:`[^`]*+`)+)|(\S+))(\s+(ASC|DESC))?(,\s*|$)~i', $match[1], $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$return[""]["columns"][] = idf_unescape($match[2]) . $match[4];
				$return[""]["descs"][] = (preg_match('~DESC~i', $match[5]) ? '1' : null);
			}
		}
		if (!$return) {
			foreach (fields($table) as $name => $field) {
				if ($field["primary"]) {
					$return[""] = array("type" => "PRIMARY", "columns" => array($name), "lengths" => array(), "descs" => array(null));
				}
			}
		}
		$sqls = get_key_vals("SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = " . q($table), $connection2);
		foreach (get_rows("PRAGMA index_list(" . table($table) . ")", $connection2) as $row) {
			$name = $row["name"];
			$index = array("type" => ($row["unique"] ? "UNIQUE" : "INDEX"));
			$index["lengths"] = array();
			$index["descs"] = array();
			foreach (get_rows("PRAGMA index_info(" . idf_escape($name) . ")", $connection2) as $row1) {
				$index["columns"][] = $row1["name"];
				$index["descs"][] = null;
			}
			if (preg_match('~^CREATE( UNIQUE)? INDEX ' . preg_quote(idf_escape($name) . ' ON ' . idf_escape($table), '~') . ' \((.*)\)$~i', $sqls[$name], $regs)) {
				preg_match_all('/("[^"]*+")+( DESC)?/', $regs[2], $matches);
				foreach ($matches[2] as $key => $val) {
					if ($val) {
						$index["descs"][$key] = '1';
					}
				}
			}
			if (!$return[""] || $index["type"] != "UNIQUE" || $index["columns"] != $return[""]["columns"] || $index["descs"] != $return[""]["descs"] || !preg_match("~^sqlite_~", $name)) {
				$return[$name] = $index;
			}
		}
		return $return;
	}

	function foreign_keys($table) {
		$return = array();
		foreach (get_rows("PRAGMA foreign_key_list(" . table($table) . ")") as $row) {
			$foreign_key = &$return[$row["id"]];
			if (!$foreign_key) {
				$foreign_key = $row;
			}
			$foreign_key["source"][] = $row["from"];
			$foreign_key["target"][] = $row["to"];
		}
		return $return;
	}

	function view($name) {
		return array("select" => preg_replace('~^(?:[^`"[]+|`[^`]*`|"[^"]*")* AS\s+~iU', '', get_val("SELECT sql FROM sqlite_master WHERE type = 'view' AND name = " . q($name)))); //! identifiers may be inside []
	}

	function collations() {
		return (isset($_GET["create"]) ? get_vals("PRAGMA collation_list", 1) : array());
	}

	function information_schema($db) {
		return false;
	}

	function error() {
		return h(connection()->error);
	}

	function check_sqlite_name($name) {
		// avoid creating PHP files on unsecured servers
		$extensions = "db|sdb|sqlite";
		if (!preg_match("~^[^\\0]*\\.($extensions)\$~", $name)) {
			connection()->error = lang(25, str_replace("|", ", ", $extensions));
			return false;
		}
		return true;
	}

	function create_database($db, $collation) {
		if (file_exists($db)) {
			connection()->error = lang(26);
			return false;
		}
		if (!check_sqlite_name($db)) {
			return false;
		}
		try {
			$link = new Db();
			$link->attach($db, '', '');
		} catch (\Exception $ex) {
			connection()->error = $ex->getMessage();
			return false;
		}
		$link->query('PRAGMA encoding = "UTF-8"');
		$link->query('CREATE TABLE adminer (i)'); // otherwise creates empty file
		$link->query('DROP TABLE adminer');
		return true;
	}

	function drop_databases($databases) {
		connection()->attach(":memory:", '', ''); // to unlock file, doesn't work in PDO on Windows
		foreach ($databases as $db) {
			if (!@unlink($db)) {
				connection()->error = lang(26);
				return false;
			}
		}
		return true;
	}

	function rename_database($name, $collation) {
		if (!check_sqlite_name($name)) {
			return false;
		}
		connection()->attach(":memory:", '', '');
		connection()->error = lang(26);
		return @rename(DB, $name);
	}

	function auto_increment() {
		return " PRIMARY KEY AUTOINCREMENT";
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$use_all_fields = ($table == "" || $foreign);
		foreach ($fields as $field) {
			if ($field[0] != "" || !$field[1] || $field[2]) {
				$use_all_fields = true;
				break;
			}
		}
		$alter = array();
		$originals = array();
		foreach ($fields as $field) {
			if ($field[1]) {
				$alter[] = ($use_all_fields ? $field[1] : "ADD " . implode($field[1]));
				if ($field[0] != "") {
					$originals[$field[0]] = $field[1][0];
				}
			}
		}
		if (!$use_all_fields) {
			foreach ($alter as $val) {
				if (!queries("ALTER TABLE " . table($table) . " $val")) {
					return false;
				}
			}
			if ($table != $name && !queries("ALTER TABLE " . table($table) . " RENAME TO " . table($name))) {
				return false;
			}
		} elseif (!recreate_table($table, $name, $alter, $originals, $foreign, $auto_increment)) {
			return false;
		}
		if ($auto_increment) {
			queries("BEGIN");
			queries("UPDATE sqlite_sequence SET seq = $auto_increment WHERE name = " . q($name)); // ignores error
			if (!connection()->affected_rows) {
				queries("INSERT INTO sqlite_sequence (name, seq) VALUES (" . q($name) . ", $auto_increment)");
			}
			queries("COMMIT");
		}
		return true;
	}

	/** Recreate table
	* @param string $table original name
	* @param string $name new name
	* @param list<list<string>> $fields [process_field()], empty to preserve
	* @param string[] $originals [$original => idf_escape($new_column)], empty to preserve
	* @param string[] $foreign [format_foreign_key()], empty to preserve
	* @param numeric-string|'' $auto_increment set auto_increment to this value, "" to preserve
	* @param list<array{string, string, list<string>|'DROP'}> $indexes [[$type, $name, $columns]], empty to preserve
	* @param string $drop_check CHECK constraint to drop
	* @param string $add_check CHECK constraint to add
	*/
	function recreate_table(string $table, string $name, array $fields, array $originals, array $foreign, string $auto_increment = "", $indexes = array(), string $drop_check = "", string $add_check = ""): bool {
		if ($table != "") {
			if (!$fields) {
				foreach (fields($table) as $key => $field) {
					if ($indexes) {
						$field["auto_increment"] = 0;
					}
					$fields[] = process_field($field, $field);
					$originals[$key] = idf_escape($key);
				}
			}
			$primary_key = false;
			foreach ($fields as $field) {
				if ($field[6]) {
					$primary_key = true;
				}
			}
			$drop_indexes = array();
			foreach ($indexes as $key => $val) {
				if ($val[2] == "DROP") {
					$drop_indexes[$val[1]] = true;
					unset($indexes[$key]);
				}
			}
			foreach (indexes($table) as $key_name => $index) {
				$columns = array();
				foreach ($index["columns"] as $key => $column) {
					if (!$originals[$column]) {
						continue 2;
					}
					$columns[] = $originals[$column] . ($index["descs"][$key] ? " DESC" : "");
				}
				if (!$drop_indexes[$key_name]) {
					if ($index["type"] != "PRIMARY" || !$primary_key) {
						$indexes[] = array($index["type"], $key_name, $columns);
					}
				}
			}
			foreach ($indexes as $key => $val) {
				if ($val[0] == "PRIMARY") {
					unset($indexes[$key]);
					$foreign[] = "  PRIMARY KEY (" . implode(", ", $val[2]) . ")";
				}
			}
			foreach (foreign_keys($table) as $key_name => $foreign_key) {
				foreach ($foreign_key["source"] as $key => $column) {
					if (!$originals[$column]) {
						continue 2;
					}
					$foreign_key["source"][$key] = idf_unescape($originals[$column]);
				}
				if (!isset($foreign[" $key_name"])) {
					$foreign[] = " " . format_foreign_key($foreign_key);
				}
			}
			queries("BEGIN");
		}
		$changes = array();
		foreach ($fields as $field) {
			if (preg_match('~GENERATED~', $field[3])) {
				unset($originals[array_search($field[0], $originals)]);
			}
			$changes[] = "  " . implode($field);
		}
		$changes = array_merge($changes, array_filter($foreign));
		foreach (driver()->checkConstraints($table) as $check) {
			if ($check != $drop_check) {
				$changes[] = "  CHECK ($check)";
			}
		}
		if ($add_check) {
			$changes[] = "  CHECK ($add_check)";
		}
		$temp_name = ($table == $name ? "adminer_$name" : $name);
		if (!queries("CREATE TABLE " . table($temp_name) . " (\n" . implode(",\n", $changes) . "\n)")) {
			// implicit ROLLBACK to not overwrite connection()->error
			return false;
		}
		if ($table != "") {
			if ($originals && !queries("INSERT INTO " . table($temp_name) . " (" . implode(", ", $originals) . ") SELECT " . implode(", ", array_map('Adminer\idf_escape', array_keys($originals))) . " FROM " . table($table))) {
				return false;
			}
			$triggers = array();
			foreach (triggers($table) as $trigger_name => $timing_event) {
				$trigger = trigger($trigger_name, $table);
				$triggers[] = "CREATE TRIGGER " . idf_escape($trigger_name) . " " . implode(" ", $timing_event) . " ON " . table($name) . "\n$trigger[Statement]";
			}
			$auto_increment = $auto_increment ? "" : get_val("SELECT seq FROM sqlite_sequence WHERE name = " . q($table)); // if $auto_increment is set then it will be updated later
			if (
				!queries("DROP TABLE " . table($table)) // drop before creating indexes and triggers to allow using old names
				|| ($table == $name && !queries("ALTER TABLE " . table($temp_name) . " RENAME TO " . table($name)))
				|| !alter_indexes($name, $indexes)
			) {
				return false;
			}
			if ($auto_increment) {
				queries("UPDATE sqlite_sequence SET seq = $auto_increment WHERE name = " . q($name)); // ignores error
			}
			foreach ($triggers as $trigger) {
				if (!queries($trigger)) {
					return false;
				}
			}
			queries("COMMIT");
		}
		return true;
	}

	function index_sql($table, $type, $name, $columns) {
		return "CREATE $type " . ($type != "INDEX" ? "INDEX " : "")
			. idf_escape($name != "" ? $name : uniqid($table . "_"))
			. " ON " . table($table)
			. " $columns"
		;
	}

	function alter_indexes($table, $alter) {
		foreach ($alter as $primary) {
			if ($primary[0] == "PRIMARY") {
				return recreate_table($table, $table, array(), array(), array(), "", $alter);
			}
		}
		foreach (array_reverse($alter) as $val) {
			if (
				!queries($val[2] == "DROP"
				? "DROP INDEX " . idf_escape($val[1])
				: index_sql($table, $val[0], $val[1], "(" . implode(", ", $val[2]) . ")"))
			) {
				return false;
			}
		}
		return true;
	}

	function truncate_tables($tables) {
		return apply_queries("DELETE FROM", $tables);
	}

	function drop_views($views) {
		return apply_queries("DROP VIEW", $views);
	}

	function drop_tables($tables) {
		return apply_queries("DROP TABLE", $tables);
	}

	function move_tables($tables, $views, $target) {
		return false;
	}

	function trigger($name, $table) {
		if ($name == "") {
			return array("Statement" => "BEGIN\n\t;\nEND");
		}
		$idf = '(?:[^`"\s]+|`[^`]*`|"[^"]*")+';
		$trigger_options = trigger_options();
		preg_match(
			"~^CREATE\\s+TRIGGER\\s*$idf\\s*(" . implode("|", $trigger_options["Timing"]) . ")\\s+([a-z]+)(?:\\s+OF\\s+($idf))?\\s+ON\\s*$idf\\s*(?:FOR\\s+EACH\\s+ROW\\s)?(.*)~is",
			get_val("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = " . q($name)),
			$match
		);
		$of = $match[3];
		return array(
			"Timing" => strtoupper($match[1]),
			"Event" => strtoupper($match[2]) . ($of ? " OF" : ""),
			"Of" => idf_unescape($of),
			"Trigger" => $name,
			"Statement" => $match[4],
		);
	}

	function triggers($table) {
		$return = array();
		$trigger_options = trigger_options();
		foreach (get_rows("SELECT * FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . q($table)) as $row) {
			preg_match('~^CREATE\s+TRIGGER\s*(?:[^`"\s]+|`[^`]*`|"[^"]*")+\s*(' . implode("|", $trigger_options["Timing"]) . ')\s*(.*?)\s+ON\b~i', $row["sql"], $match);
			$return[$row["name"]] = array($match[1], $match[2]);
		}
		return $return;
	}

	function trigger_options() {
		return array(
			"Timing" => array("BEFORE", "AFTER", "INSTEAD OF"),
			"Event" => array("INSERT", "UPDATE", "UPDATE OF", "DELETE"),
			"Type" => array("FOR EACH ROW"),
		);
	}

	function begin() {
		return queries("BEGIN");
	}

	function last_id($result) {
		return get_val("SELECT LAST_INSERT_ROWID()");
	}

	function explain($connection, $query) {
		return $connection->query("EXPLAIN QUERY PLAN $query");
	}

	function found_rows($table_status, $where) {
	}

	function types(): array {
		return array();
	}

	function create_sql($table, $auto_increment, $style) {
		$return = get_val("SELECT sql FROM sqlite_master WHERE type IN ('table', 'view') AND name = " . q($table));
		foreach (indexes($table) as $name => $index) {
			if ($name == '') {
				continue;
			}
			$return .= ";\n\n" . index_sql($table, $index['type'], $name, "(" . implode(", ", array_map('Adminer\idf_escape', $index['columns'])) . ")");
		}
		return $return;
	}

	function truncate_sql($table) {
		return "DELETE FROM " . table($table);
	}

	function use_sql($database, $style = "") {
	}

	function trigger_sql($table) {
		return implode(get_vals("SELECT sql || ';;\n' FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . q($table)));
	}

	function show_variables() {
		$return = array();
		foreach (get_rows("PRAGMA pragma_list") as $row) {
			$name = $row["name"];
			if ($name != "pragma_list" && $name != "compile_options") {
				$return[$name] = array($name, '');
				foreach (get_rows("PRAGMA $name") as $row) {
					$return[$name][1] .= implode(", ", $row) . "\n";
				}
			}
		}
		return $return;
	}

	function show_status() {
		$return = array();
		foreach (get_vals("PRAGMA compile_options") as $option) {
			$return[] = explode("=", $option, 2) + array('', '');
		}
		return $return;
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function support($feature) {
		return preg_match('~^(check|columns|database|drop_col|dump|indexes|descidx|move_col|sql|status|table|trigger|variables|view|view_trigger)$~', $feature);
	}
}

?>
<?php
add_driver("pgsql", "PostgreSQL");

if (isset($_GET["pgsql"])) {
	define('Adminer\DRIVER', "pgsql");

	if (extension_loaded("pgsql") && $_GET["ext"] != "pdo") {
		class PgsqlDb extends SqlDb {
			public $extension = "PgSQL";
			public $timeout = 0;
			private $link, $string, $database = true;

			function _error($errno, $error) {
				if (ini_bool("html_errors")) {
					$error = html_entity_decode(strip_tags($error));
				}
				$error = preg_replace('~^[^:]*: ~', '', $error);
				$this->error = $error;
			}

			function attach(string $server, string $username, string $password): string {
				$db = adminer()->database();
				set_error_handler(array($this, '_error'));
				list($host, $port) = host_port(addcslashes($server, "'\\"));
				$this->string = "host='$host'" . ($port ? " port='$port'" : "") . " user='" . addcslashes($username, "'\\") . "' password='" . addcslashes($password, "'\\") . "'";
				$ssl = adminer()->connectSsl();
				if (isset($ssl["mode"])) {
					$this->string .= " sslmode='" . $ssl["mode"] . "'";
				}
				$this->link = @pg_connect("$this->string dbname='" . ($db != "" ? addcslashes($db, "'\\") : "postgres") . "'", PGSQL_CONNECT_FORCE_NEW);
				if (!$this->link && $db != "") {
					// try to connect directly with database for performance
					$this->database = false;
					$this->link = @pg_connect("$this->string dbname='postgres'", PGSQL_CONNECT_FORCE_NEW);
				}
				restore_error_handler();
				if ($this->link) {
					pg_set_client_encoding($this->link, "UTF8");
				}
				return ($this->link ? '' : $this->error);
			}

			function quote(string $string): string {
				return (function_exists('pg_escape_literal')
					? pg_escape_literal($this->link, $string) // available since PHP 5.4.4
					: "'" . pg_escape_string($this->link, $string) . "'"
				);
			}

			function value(?string $val, array $field): ?string {
				return ($field["type"] == "bytea" && $val !== null ? pg_unescape_bytea($val) : $val);
			}

			function select_db(string $database) {
				if ($database == adminer()->database()) {
					return $this->database;
				}
				$return = @pg_connect("$this->string dbname='" . addcslashes($database, "'\\") . "'", PGSQL_CONNECT_FORCE_NEW);
				if ($return) {
					$this->link = $return;
				}
				return $return;
			}

			function close() {
				$this->link = @pg_connect("$this->string dbname='postgres'");
			}

			function query(string $query, bool $unbuffered = false) {
				$result = @pg_query($this->link, $query);
				$this->error = "";
				if (!$result) {
					$this->error = pg_last_error($this->link);
					$return = false;
				} elseif (!pg_num_fields($result)) {
					$this->affected_rows = pg_affected_rows($result);
					$return = true;
				} else {
					$return = new Result($result);
				}
				if ($this->timeout) {
					$this->timeout = 0;
					$this->query("RESET statement_timeout");
				}
				return $return;
			}

			function warnings() {
				return h(pg_last_notice($this->link)); // second parameter is available since PHP 7.1.0
			}

			/** Copy from array into a table
			* @param list<string> $rows
			*/
			function copyFrom(string $table, array $rows): bool {
				$this->error = '';
				set_error_handler(function (int $errno, string $error): bool {
					$this->error = (ini_bool('html_errors') ? html_entity_decode($error) : $error);
					return true;
				});
				$return = pg_copy_from($this->link, $table, $rows);
				restore_error_handler();
				return $return;
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 0;

			function __construct($result) {
				$this->result = $result;
				$this->num_rows = pg_num_rows($result);
			}

			function fetch_assoc() {
				return pg_fetch_assoc($this->result);
			}

			function fetch_row() {
				return pg_fetch_row($this->result);
			}

			function fetch_field(): \stdClass {
				$column = $this->offset++;
				$return = new \stdClass;
				$return->orgtable = pg_field_table($this->result, $column);
				$return->name = pg_field_name($this->result, $column);
				$type = pg_field_type($this->result, $column);
				$return->type = (preg_match(number_type(), $type) ? 0 : 15);
				$return->charsetnr = ($type == "bytea" ? 63 : 0); // 63 - binary
				return $return;
			}

			function __destruct() {
				pg_free_result($this->result);
			}
		}

	} elseif (extension_loaded("pdo_pgsql")) {
		class PgsqlDb extends PdoDb {
			public $extension = "PDO_PgSQL";
			public $timeout = 0;

			function attach(string $server, string $username, string $password): string {
				$db = adminer()->database();
				list($host, $port) = host_port(addcslashes($server, "'\\"));
				//! client_encoding is supported since 9.1, but we can't yet use min_version here
				$dsn = "pgsql:host='$host'" . ($port ? " port='$port'" : "") . " client_encoding=utf8 dbname='" . ($db != "" ? addcslashes($db, "'\\") : "postgres") . "'";
				$ssl = adminer()->connectSsl();
				if (isset($ssl["mode"])) {
					$dsn .= " sslmode='" . $ssl["mode"] . "'";
				}
				return $this->dsn($dsn, $username, $password);
			}

			function select_db(string $database) {
				return (adminer()->database() == $database);
			}

			function query(string $query, bool $unbuffered = false) {
				$return = parent::query($query, $unbuffered);
				if ($this->timeout) {
					$this->timeout = 0;
					parent::query("RESET statement_timeout");
				}
				return $return;
			}

			function warnings() {
				// not implemented in PDO_PgSQL as of PHP 7.2.1
			}

			function copyFrom(string $table, array $rows): bool {
				$return = $this->pdo->pgsqlCopyFromArray($table, $rows);
				$this->error = idx($this->pdo->errorInfo(), 2) ?: '';
				return $return;
			}

			function close() {
			}
		}

	}



	if (class_exists('Adminer\PgsqlDb')) {
		class Db extends PgsqlDb {
			function multi_query(string $query) {
				if (preg_match('~\bCOPY\s+(.+?)\s+FROM\s+stdin;\n?(.*)\n\\\\\.$~is', str_replace("\r\n", "\n", $query), $match)) { // no ^ to allow leading comments
					$rows = explode("\n", $match[2]);
					$this->affected_rows = count($rows);
					return $this->copyFrom($match[1], $rows);
				}
				return parent::multi_query($query);
			}
		}
	}



	class Driver extends SqlDriver {
		static $extensions = array("PgSQL", "PDO_PgSQL");
		static $jush = "pgsql";

		public $operators = array("=", "<", ">", "<=", ">=", "!=", "~", "!~", "LIKE", "LIKE %%", "ILIKE", "ILIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT ILIKE", "NOT IN", "IS NOT NULL", "SQL"); //! SQL - same-site CSRF
		public $functions = array("char_length", "lower", "round", "to_hex", "to_timestamp", "upper");
		public $grouping = array("avg", "count", "count distinct", "max", "min", "sum");

		public string $nsOid = "(SELECT oid FROM pg_namespace WHERE nspname = current_schema())";

		static function connect(string $server, string $username, string $password) {
			$connection = parent::connect($server, $username, $password);
			if (is_string($connection)) {
				return $connection;
			}
			$version = get_val("SELECT version()", 0, $connection);
			$connection->flavor = (preg_match('~CockroachDB~', $version) ? 'cockroach' : '');
			$connection->server_info = preg_replace('~^\D*([\d.]+[-\w]*).*~', '\1', $version);
			if (min_version(9, 0, $connection)) {
				$connection->query("SET application_name = 'Adminer'");
			}
			if ($connection->flavor == 'cockroach') { // we don't use "PostgreSQL / CockroachDB" by default because it's too long
				add_driver(DRIVER, "CockroachDB");
			}
			return $connection;
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array( //! arrays
				lang(27) => array("smallint" => 5, "integer" => 10, "bigint" => 19, "boolean" => 1, "numeric" => 0, "real" => 7, "double precision" => 16, "money" => 20),
				lang(28) => array("date" => 13, "time" => 17, "timestamp" => 20, "timestamptz" => 21, "interval" => 0),
				lang(29) => array("character" => 0, "character varying" => 0, "text" => 0, "tsquery" => 0, "tsvector" => 0, "uuid" => 0, "xml" => 0),
				lang(30) => array("bit" => 0, "bit varying" => 0, "bytea" => 0),
				lang(31) => array("cidr" => 43, "inet" => 43, "macaddr" => 17, "macaddr8" => 23, "txid_snapshot" => 0),
				lang(32) => array("box" => 0, "circle" => 0, "line" => 0, "lseg" => 0, "path" => 0, "point" => 0, "polygon" => 0),
			);
			if (min_version(9.2, 0, $connection)) {
				$this->types[lang(29)]["json"] = 4294967295;
				if (min_version(9.4, 0, $connection)) {
					$this->types[lang(29)]["jsonb"] = 4294967295;
				}
			}
			$this->insertFunctions = array(
				"char" => "md5",
				"date|time" => "now",
			);
			$this->editFunctions = array(
				number_type() => "+/-",
				"date|time" => "+ interval/- interval", //! escape
				"char|text" => "||",
			);
			if (min_version(12, 0, $connection)) {
				$this->generated = array("STORED");
			}
			$this->partitionBy = array("RANGE", "LIST");
			if (!$connection->flavor) {
				$this->partitionBy[] = "HASH";
			}
		}

		function enumLength(array $field) {
			$enum = $this->types[lang(6)][$field["type"]];
			return ($enum ? type_values($enum) : "");
		}

		function setUserTypes($types) {
			$this->types[lang(6)] = array_flip($types);
		}

		function insertReturning(string $table): string {
			$auto_increment = array_filter(fields($table), function ($field) {
				return $field['auto_increment'];
			});
			return (count($auto_increment) == 1 ? " RETURNING " . idf_escape(key($auto_increment)) : "");
		}

		function insertUpdate(string $table, array $rows, array $primary) {
			foreach ($rows as $set) {
				$update = array();
				$where = array();
				foreach ($set as $key => $val) {
					$update[] = "$key = $val";
					if (isset($primary[idf_unescape($key)])) {
						$where[] = "$key = $val";
					}
				}
				if (
					!(($where && queries("UPDATE " . table($table) . " SET " . implode(", ", $update) . " WHERE " . implode(" AND ", $where)) && $this->conn->affected_rows)
					|| queries("INSERT INTO " . table($table) . " (" . implode(", ", array_keys($set)) . ") VALUES (" . implode(", ", $set) . ")"))
				) {
					return false;
				}
			}
			return true;
		}

		function slowQuery(string $query, int $timeout) {
			$this->conn->query("SET statement_timeout = " . (1000 * $timeout));
			$this->conn->timeout = 1000 * $timeout;
			return $query;
		}

		function convertSearch(string $idf, array $val, array $field): string {
			$textTypes = "char|text";
			if (strpos($val["op"], "LIKE") === false) {
				$textTypes .= "|date|time(stamp)?|boolean|uuid|inet|cidr|macaddr|" . number_type();
			}

			return (preg_match("~$textTypes~", $field["type"]) ? $idf : "CAST($idf AS text)");
		}

		function quoteBinary(string $s): string {
			return "'\\x" . bin2hex($s) . "'"; // available since PostgreSQL 8.1
		}

		function warnings() {
			return $this->conn->warnings();
		}

		function tableHelp(string $name, bool $is_view = false) {
			$links = array(
				"information_schema" => "infoschema",
				"pg_catalog" => ($is_view ? "view" : "catalog"),
			);
			$link = $links[$_GET["ns"]];
			if ($link) {
				return "$link-" . str_replace("_", "-", $name) . ".html";
			}
		}

		function inheritsFrom(string $table): array {
			return get_vals("SELECT relname FROM pg_class JOIN pg_inherits ON inhparent = oid WHERE inhrelid = " . $this->tableOid($table) . " ORDER BY 1");
		}

		function inheritedTables(string $table): array {
			return get_vals("SELECT relname FROM pg_inherits JOIN pg_class ON inhrelid = oid WHERE inhparent = " . $this->tableOid($table) . " ORDER BY 1");
		}

		function partitionsInfo(string $table): array {
			$row = (min_version(10) ? $this->conn->query("SELECT * FROM pg_partitioned_table WHERE partrelid = " . $this->tableOid($table))->fetch_assoc() : null);
			if ($row) {
				$attrs = get_vals("SELECT attname FROM pg_attribute WHERE attrelid = $row[partrelid] AND attnum IN (" . str_replace(" ", ", ", $row["partattrs"]) . ")"); //! ordering
				$by = array('h' => 'HASH', 'l' => 'LIST', 'r' => 'RANGE');
				return array(
					"partition_by" => $by[$row["partstrat"]],
					"partition" => implode(", ", array_map('Adminer\idf_escape', $attrs)),
				);
			}
			return array();
		}

		function tableOid(string $table): string {
			return "(SELECT oid FROM pg_class WHERE relnamespace = $this->nsOid AND relname = " . q($table) . " AND relkind IN ('r', 'm', 'v', 'f', 'p'))";
		}

		function indexAlgorithms(array $tableStatus): array {
			static $return = array();
			if (!$return) {
				$return = get_vals("SELECT amname FROM pg_am" . (min_version(9.6) ? " WHERE amtype = 'i'" : "") . " ORDER BY amname = '" . ($this->conn->flavor == 'cockroach' ? "prefix" : "btree") . "' DESC, amname");
			}
			return $return;
		}

		function supportsIndex(array $table_status): bool {
			// returns true for "materialized view"
			return $table_status["Engine"] != "view";
		}

		function hasCStyleEscapes(): bool {
			static $c_style;
			if ($c_style === null) {
				$c_style = (get_val("SHOW standard_conforming_strings", 0, $this->conn) == "off");
			}
			return $c_style;
		}
	}



	function idf_escape($idf) {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function get_databases($flush) {
		return get_vals("SELECT datname FROM pg_database
WHERE datallowconn = TRUE AND has_database_privilege(datname, 'CONNECT')
ORDER BY datname");
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return (preg_match('~^INTO~', $query)
			? limit($query, $where, 1, 0, $separator)
			: " $query" . (is_view(table_status1($table)) ? $where : $separator . "WHERE ctid = (SELECT ctid FROM " . table($table) . $where . $separator . "LIMIT 1)")
		);
	}

	function db_collation($db, $collations) {
		return get_val("SELECT datcollate FROM pg_database WHERE datname = " . q($db));
	}

	function logged_user() {
		return get_val("SELECT user");
	}

	function tables_list() {
		$query = "SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = current_schema()";
		if (support("materializedview")) {
			$query .= "
UNION ALL
SELECT matviewname, 'MATERIALIZED VIEW'
FROM pg_matviews
WHERE schemaname = current_schema()";
		}
		$query .= "
ORDER BY 1";
		return get_key_vals($query);
	}

	function count_tables($databases) {
		$return = array();
		foreach ($databases as $db) {
			if (connection()->select_db($db)) {
				$return[$db] = count(tables_list());
			}
		}
		return $return;
	}

	function table_status($name = "") {
		static $has_size;
		if ($has_size === null) {
			// https://github.com/cockroachdb/cockroach/issues/40391
			$has_size = get_val("SELECT 'pg_table_size'::regproc");
		}
		$return = array();
		foreach (
			get_rows("SELECT
	relname AS \"Name\",
	CASE relkind WHEN 'v' THEN 'view' WHEN 'm' THEN 'materialized view' ELSE 'table' END AS \"Engine\"" . ($has_size ? ",
	pg_table_size(c.oid) AS \"Data_length\",
	pg_indexes_size(c.oid) AS \"Index_length\"" : "") . ",
	obj_description(c.oid, 'pg_class') AS \"Comment\",
	" . (min_version(12) ? "''" : "CASE WHEN relhasoids THEN 'oid' ELSE '' END") . " AS \"Oid\",
	reltuples AS \"Rows\",
	" . (min_version(10) ? "relispartition::int AS partition," : "") . "
	current_schema() AS nspname
FROM pg_class c
WHERE relkind IN ('r', 'm', 'v', 'f', 'p')
AND relnamespace = " . driver()->nsOid . "
" . ($name != "" ? "AND relname = " . q($name) : "ORDER BY relname")) as $row //! Auto_increment
		) {
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	function is_view($table_status) {
		return in_array($table_status["Engine"], array("view", "materialized view"));
	}

	function fk_support($table_status) {
		return true;
	}

	function fields($table) {
		$return = array();
		$aliases = array(
			'timestamp without time zone' => 'timestamp',
			'timestamp with time zone' => 'timestamptz',
		);
		foreach (
			get_rows("SELECT
	a.attname AS field,
	format_type(a.atttypid, a.atttypmod) AS full_type,
	pg_get_expr(d.adbin, d.adrelid) AS default,
	a.attnotnull::int,
	col_description(a.attrelid, a.attnum) AS comment" . (min_version(10) ? ",
	a.attidentity" . (min_version(12) ? ",
	a.attgenerated" : "") : "") . "
FROM pg_attribute a
LEFT JOIN pg_attrdef d ON a.attrelid = d.adrelid AND a.attnum = d.adnum
WHERE a.attrelid = " . driver()->tableOid($table) . "
AND NOT a.attisdropped
AND a.attnum > 0
ORDER BY a.attnum") as $row
		) {
			//! collation, primary
			preg_match('~([^([]+)(\((.*)\))?([a-z ]+)?((\[[0-9]*])*)$~', $row["full_type"], $match);
			list(, $type, $length, $row["length"], $addon, $array) = $match;
			$row["length"] .= $array;
			$check_type = $type . $addon;
			if (isset($aliases[$check_type])) {
				$row["type"] = $aliases[$check_type];
				$row["full_type"] = $row["type"] . $length . $array;
			} else {
				$row["type"] = $type;
				$row["full_type"] = $row["type"] . $length . $addon . $array;
			}
			if (in_array($row['attidentity'], array('a', 'd'))) {
				$row['default'] = 'GENERATED ' . ($row['attidentity'] == 'd' ? 'BY DEFAULT' : 'ALWAYS') . ' AS IDENTITY';
			}
			$row["generated"] = ($row["attgenerated"] == "s" ? "STORED" : "");
			$row["null"] = !$row["attnotnull"];
			$row["auto_increment"] = $row['attidentity'] || preg_match('~^nextval\(~i', $row["default"])
				|| preg_match('~^unique_rowid\(~', $row["default"]); // CockroachDB
			$row["privileges"] = array("insert" => 1, "select" => 1, "update" => 1, "where" => 1, "order" => 1);
			if (preg_match('~(.+)::[^,)]+(.*)~', $row["default"], $match)) {
				$row["default"] = ($match[1] == "NULL" ? null : idf_unescape($match[1]) . $match[2]);
			}
			$return[$row["field"]] = $row;
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$connection2 = connection($connection2);
		$return = array();
		$table_oid = driver()->tableOid($table);
		$columns = get_key_vals("SELECT attnum, attname FROM pg_attribute WHERE attrelid = $table_oid AND attnum > 0", $connection2);
		foreach (
			get_rows("SELECT relname, indisunique::int, indisprimary::int, indkey, indoption, amname, pg_get_expr(indpred, indrelid, true) AS partial, pg_get_expr(indexprs, indrelid) AS indexpr
FROM pg_index
JOIN pg_class ON indexrelid = oid
JOIN pg_am ON pg_am.oid = pg_class.relam
WHERE indrelid = $table_oid
ORDER BY indisprimary DESC, indisunique DESC", $connection2) as $row
		) {
			$relname = $row["relname"];
			$return[$relname]["type"] = ($row["partial"] ? "INDEX" : ($row["indisprimary"] ? "PRIMARY" : ($row["indisunique"] ? "UNIQUE" : "INDEX")));
			$return[$relname]["columns"] = array();
			$return[$relname]["descs"] = array();
			$return[$relname]["algorithm"] = $row["amname"];
			$return[$relname]["partial"] = $row["partial"];
			$indexpr = preg_split('~(?<=\)), (?=\()~', $row["indexpr"]); //! '), (' used in expression
			foreach (explode(" ", $row["indkey"]) as $indkey) {
				$return[$relname]["columns"][] = ($indkey ? $columns[$indkey] : array_shift($indexpr));
			}
			foreach (explode(" ", $row["indoption"]) as $indoption) {
				$return[$relname]["descs"][] = (intval($indoption) & 1 ? '1' : null); // 1 - INDOPTION_DESC
			}
			$return[$relname]["lengths"] = array();
		}
		return $return;
	}

	function foreign_keys($table) {
		$return = array();
		foreach (
			get_rows("SELECT conname, condeferrable::int AS deferrable, pg_get_constraintdef(oid) AS definition
FROM pg_constraint
WHERE conrelid = " . driver()->tableOid($table) . "
AND contype = 'f'::char
ORDER BY conkey, conname") as $row
		) {
			if (preg_match('~FOREIGN KEY\s*\((.+)\)\s*REFERENCES (.+)\((.+)\)(.*)$~iA', $row['definition'], $match)) {
				$row['source'] = array_map('Adminer\idf_unescape', array_map('trim', explode(',', $match[1])));
				if (preg_match('~^(("([^"]|"")+"|[^"]+)\.)?"?("([^"]|"")+"|[^"]+)$~', $match[2], $match2)) {
					$row['ns'] = idf_unescape($match2[2]);
					$row['table'] = idf_unescape($match2[4]);
				}
				$row['target'] = array_map('Adminer\idf_unescape', array_map('trim', explode(',', $match[3])));
				$row['on_delete'] = (preg_match("~ON DELETE (" . driver()->onActions . ")~", $match[4], $match2) ? $match2[1] : 'NO ACTION');
				$row['on_update'] = (preg_match("~ON UPDATE (" . driver()->onActions . ")~", $match[4], $match2) ? $match2[1] : 'NO ACTION');
				$return[$row['conname']] = $row;
			}
		}
		return $return;
	}

	function view($name) {
		return array("select" => trim(get_val("SELECT pg_get_viewdef(" . driver()->tableOid($name) . ")")));
	}

	function collations() {
		//! supported in CREATE DATABASE
		return array();
	}

	function information_schema($db) {
		return get_schema() == "information_schema";
	}

	function error() {
		$return = h(connection()->error);
		if (preg_match('~^(.*\n)?([^\n]*)\n( *)\^(\n.*)?$~s', $return, $match)) {
			$return = $match[1] . preg_replace('~((?:[^&]|&[^;]*;){' . strlen($match[3]) . '})(.*)~', '\1<b>\2</b>', $match[2]) . $match[4];
		}
		return nl_br($return);
	}

	function create_database($db, $collation) {
		return queries("CREATE DATABASE " . idf_escape($db) . ($collation ? " ENCODING " . idf_escape($collation) : ""));
	}

	function drop_databases($databases) {
		connection()->close();
		return apply_queries("DROP DATABASE", $databases, 'Adminer\idf_escape');
	}

	function rename_database($name, $collation) {
		connection()->close();
		return queries("ALTER DATABASE " . idf_escape(DB) . " RENAME TO " . idf_escape($name));
	}

	function auto_increment() {
		return "";
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$alter = array();
		$queries = array();
		if ($table != "" && $table != $name) {
			$queries[] = "ALTER TABLE " . table($table) . " RENAME TO " . table($name);
		}
		$sequence = "";
		foreach ($fields as $field) {
			$column = idf_escape($field[0]);
			$val = $field[1];
			if (!$val) {
				$alter[] = "DROP $column";
			} else {
				$val5 = $val[5];
				unset($val[5]);
				if ($field[0] == "") {
					if (isset($val[6])) { // auto_increment
						$val[1] = ($val[1] == " bigint" ? " big" : ($val[1] == " smallint" ? " small" : " ")) . "serial";
					}
					$alter[] = ($table != "" ? "ADD " : "  ") . implode($val);
					if (isset($val[6])) {
						$alter[] = ($table != "" ? "ADD" : " ") . " PRIMARY KEY ($val[0])";
					}
				} else {
					if ($column != $val[0]) {
						$queries[] = "ALTER TABLE " . table($name) . " RENAME $column TO $val[0]";
					}
					$alter[] = "ALTER $column TYPE$val[1]";
					$sequence_name = $table . "_" . idf_unescape($val[0]) . "_seq";
					$alter[] = "ALTER $column " . ($val[3] ? "SET" . preg_replace('~GENERATED ALWAYS(.*) STORED~', 'EXPRESSION\1', $val[3])
						: (isset($val[6]) ? "SET DEFAULT nextval(" . q($sequence_name) . ")"
						: "DROP DEFAULT" //! change to DROP EXPRESSION with generated columns
					));
					if (isset($val[6])) {
						$sequence = "CREATE SEQUENCE IF NOT EXISTS " . idf_escape($sequence_name) . " OWNED BY " . idf_escape($table) . ".$val[0]";
					}
					$alter[] = "ALTER $column " . ($val[2] == " NULL" ? "DROP NOT" : "SET") . $val[2];
				}
				if ($field[0] != "" || $val5 != "") {
					$queries[] = "COMMENT ON COLUMN " . table($name) . ".$val[0] IS " . ($val5 != "" ? substr($val5, 9) : "''");
				}
			}
		}
		$alter = array_merge($alter, $foreign);
		if ($table == "") {
			$status = "";
			if ($partitioning) {
				$cockroach = (connection()->flavor == 'cockroach');
				$status = " PARTITION BY $partitioning[partition_by]($partitioning[partition])";
				if ($partitioning["partition_by"] == 'HASH') {
					$partitions = +$partitioning["partitions"];
					for ($i=0; $i < $partitions; $i++) {
						$queries[] = "CREATE TABLE " . idf_escape($name . "_$i") . " PARTITION OF " . idf_escape($name) . " FOR VALUES WITH (MODULUS $partitions, REMAINDER $i)";
					}
				} else {
					$prev = "MINVALUE";
					foreach ($partitioning["partition_names"] as $i => $val) {
						$value = $partitioning["partition_values"][$i];
						$partition = " VALUES " . ($partitioning["partition_by"] == 'LIST' ? "IN ($value)" : "FROM ($prev) TO ($value)");
						if ($cockroach) {
							$status .= ($i ? "," : " (") . "\n  PARTITION " . (preg_match('~^DEFAULT$~i', $val) ? $val : idf_escape($val)) . "$partition";
						} else {
							$queries[] = "CREATE TABLE " . idf_escape($name . "_$val") . " PARTITION OF " . idf_escape($name) . " FOR$partition";
						}
						$prev = $value;
					}
					$status .= ($cockroach ? "\n)" : "");
				}
			}
			array_unshift($queries, "CREATE TABLE " . table($name) . " (\n" . implode(",\n", $alter) . "\n)$status");
		} elseif ($alter) {
			array_unshift($queries, "ALTER TABLE " . table($table) . "\n" . implode(",\n", $alter));
		}
		if ($sequence) {
			array_unshift($queries, $sequence);
		}
		if ($comment !== null) {
			$queries[] = "COMMENT ON TABLE " . table($name) . " IS " . q($comment);
		}
		// if ($auto_increment != "") {
			//! $queries[] = "SELECT setval(pg_get_serial_sequence(" . q($name) . ", ), $auto_increment)";
		// }
		foreach ($queries as $query) {
			if (!queries($query)) {
				return false;
			}
		}
		return true;
	}

	function alter_indexes($table, $alter) {
		$create = array();
		$drop = array();
		$queries = array();
		foreach ($alter as $val) {
			if ($val[0] != "INDEX") {
				//! descending UNIQUE indexes result in syntax error
				$create[] = ($val[2] == "DROP"
					? "\nDROP CONSTRAINT " . idf_escape($val[1])
					: "\nADD" . ($val[1] != "" ? " CONSTRAINT " . idf_escape($val[1]) : "") . " $val[0] " . ($val[0] == "PRIMARY" ? "KEY " : "") . "(" . implode(", ", $val[2]) . ")"
				);
			} elseif ($val[2] == "DROP") {
				$drop[] = idf_escape($val[1]);
			} else {
				$queries[] = "CREATE INDEX " . idf_escape($val[1] != "" ? $val[1] : uniqid($table . "_"))
					. " ON " . table($table)
					. ($val[3] ? " USING $val[3]" : "")
					. " (" . implode(", ", $val[2]) . ")"
					. ($val[4] ? " WHERE $val[4]" : "")
				;
			}
		}
		if ($create) {
			array_unshift($queries, "ALTER TABLE " . table($table) . implode(",", $create));
		}
		if ($drop) {
			array_unshift($queries, "DROP INDEX " . implode(", ", $drop));
		}
		foreach ($queries as $query) {
			if (!queries($query)) {
				return false;
			}
		}
		return true;
	}

	function truncate_tables($tables) {
		return queries("TRUNCATE " . implode(", ", array_map('Adminer\table', $tables)));
	}

	function drop_views($views) {
		return drop_tables($views);
	}

	function drop_tables($tables) {
		foreach ($tables as $table) {
			$status = table_status1($table);
			if (!queries("DROP " . strtoupper($status["Engine"]) . " " . table($table))) {
				return false;
			}
		}
		return true;
	}

	function move_tables($tables, $views, $target) {
		foreach (array_merge($tables, $views) as $table) {
			$status = table_status1($table);
			if (!queries("ALTER " . strtoupper($status["Engine"]) . " " . table($table) . " SET SCHEMA " . idf_escape($target))) {
				return false;
			}
		}
		return true;
	}

	function trigger($name, $table) {
		if ($name == "") {
			return array("Statement" => "EXECUTE PROCEDURE ()");
		}
		$columns = array();
		$where = "WHERE trigger_schema = current_schema() AND event_object_table = " . q($table) . " AND trigger_name = " . q($name);
		foreach (get_rows("SELECT * FROM information_schema.triggered_update_columns $where") as $row) {
			$columns[] = $row["event_object_column"];
		}
		$return = array();
		foreach (
			get_rows('SELECT trigger_name AS "Trigger", action_timing AS "Timing", event_manipulation AS "Event", \'FOR EACH \' || action_orientation AS "Type", action_statement AS "Statement"
FROM information_schema.triggers' . "
$where
ORDER BY event_manipulation DESC") as $row
		) {
			if ($columns && $row["Event"] == "UPDATE") {
				$row["Event"] .= " OF";
			}
			$row["Of"] = implode(", ", $columns);
			if ($return) {
				$row["Event"] .= " OR $return[Event]";
			}
			$return = $row;
		}
		return $return;
	}

	function triggers($table) {
		$return = array();
		foreach (get_rows("SELECT * FROM information_schema.triggers WHERE trigger_schema = current_schema() AND event_object_table = " . q($table)) as $row) {
			$trigger = trigger($row["trigger_name"], $table);
			$return[$trigger["Trigger"]] = array($trigger["Timing"], $trigger["Event"]);
		}
		return $return;
	}

	function trigger_options() {
		return array(
			"Timing" => array("BEFORE", "AFTER"),
			"Event" => array("INSERT", "UPDATE", "UPDATE OF", "DELETE", "INSERT OR UPDATE", "INSERT OR UPDATE OF", "DELETE OR INSERT", "DELETE OR UPDATE", "DELETE OR UPDATE OF", "DELETE OR INSERT OR UPDATE", "DELETE OR INSERT OR UPDATE OF"),
			"Type" => array("FOR EACH ROW", "FOR EACH STATEMENT"),
		);
	}

	function routine($name, $type) {
		$rows = get_rows('SELECT routine_definition AS definition, LOWER(external_language) AS language, *
FROM information_schema.routines
WHERE routine_schema = current_schema() AND specific_name = ' . q($name));
		$return = idx($rows, 0, array());
		$return["returns"] = array("type" => $return["type_udt_name"]);
		$return["fields"] = get_rows('SELECT COALESCE(parameter_name, ordinal_position::text) AS field, data_type AS type, character_maximum_length AS length, parameter_mode AS inout
FROM information_schema.parameters
WHERE specific_schema = current_schema() AND specific_name = ' . q($name) . '
ORDER BY ordinal_position');
		return $return;
	}

	function routines() {
		return get_rows('SELECT specific_name AS "SPECIFIC_NAME", routine_type AS "ROUTINE_TYPE", routine_name AS "ROUTINE_NAME", type_udt_name AS "DTD_IDENTIFIER"
FROM information_schema.routines
WHERE routine_schema = current_schema()
ORDER BY SPECIFIC_NAME');
	}

	function routine_languages() {
		return get_vals("SELECT LOWER(lanname) FROM pg_catalog.pg_language");
	}

	function routine_id($name, $row) {
		$return = array();
		foreach ($row["fields"] as $field) {
			$length = $field["length"];
			$return[] = $field["type"] . ($length ? "($length)" : "");
		}
		return idf_escape($name) . "(" . implode(", ", $return) . ")";
	}

	function last_id($result) {
		$row = (is_object($result) ? $result->fetch_row() : array());
		return ($row ? $row[0] : 0);
	}

	function explain($connection, $query) {
		return $connection->query("EXPLAIN $query");
	}

	function found_rows($table_status, $where) {
		if (preg_match("~ rows=([0-9]+)~", get_val("EXPLAIN SELECT * FROM " . idf_escape($table_status["Name"]) . ($where ? " WHERE " . implode(" AND ", $where) : "")), $regs)) {
			return $regs[1];
		}
	}

	function types(): array {
		return get_key_vals(
			"SELECT oid, typname
FROM pg_type
WHERE typnamespace = " . driver()->nsOid . "
AND typtype IN ('b','d','e')
AND typelem = 0"
		);
	}

	function type_values($id) {
		// to get values from type string: unnest(enum_range(NULL::"$type"))
		$enums = get_vals("SELECT enumlabel FROM pg_enum WHERE enumtypid = $id ORDER BY enumsortorder");
		return ($enums ? "'" . implode("', '", array_map('addslashes', $enums)) . "'" : "");
	}

	function schemas() {
		return get_vals("SELECT nspname FROM pg_namespace ORDER BY nspname");
	}

	function get_schema() {
		return get_val("SELECT current_schema()");
	}

	function set_schema($schema, $connection2 = null) {
		if (!$connection2) {
			$connection2 = connection();
		}
		$return = $connection2->query("SET search_path TO " . idf_escape($schema));
		driver()->setUserTypes(types()); //! get types from current_schemas('t')
		return $return;
	}

	// create_sql() produces CREATE TABLE without FK CONSTRAINTs
	// foreign_keys_sql() produces all FK CONSTRAINTs as ALTER TABLE ... ADD CONSTRAINT
	// so that all FKs can be added after all tables have been created, avoiding any need to reorder CREATE TABLE statements in order of their FK dependencies
	function foreign_keys_sql($table) {
		$return = "";

		$status = table_status1($table);
		$fkeys = foreign_keys($table);
		ksort($fkeys);

		foreach ($fkeys as $fkey_name => $fkey) {
			$return .= "ALTER TABLE ONLY " . idf_escape($status['nspname']) . "." . idf_escape($status['Name']) . " ADD CONSTRAINT " . idf_escape($fkey_name) . " $fkey[definition] " . ($fkey['deferrable'] ? 'DEFERRABLE' : 'NOT DEFERRABLE') . ";\n";
		}

		return ($return ? "$return\n" : $return);
	}

	function create_sql($table, $auto_increment, $style) {
		$return_parts = array();
		$sequences = array();

		$status = table_status1($table);
		if (is_view($status)) {
			$view = view($table);
			return rtrim("CREATE VIEW " . idf_escape($table) . " AS $view[select]", ";");
		}
		$fields = fields($table);

		if (count($status) < 2 || empty($fields)) {
			return false;
		}

		$return = "CREATE TABLE " . idf_escape($status['nspname']) . "." . idf_escape($status['Name']) . " (\n    ";

		// fields' definitions
		foreach ($fields as $field) {
			$part = idf_escape($field['field']) . ' ' . $field['full_type']
				. default_value($field)
				. ($field['null'] ? "" : " NOT NULL");
			$return_parts[] = $part;

			// sequences for fields
			if (preg_match('~nextval\(\'([^\']+)\'\)~', $field['default'], $matches)) {
				$sequence_name = $matches[1];
				$sq = first(get_rows((min_version(10)
					? "SELECT *, cache_size AS cache_value FROM pg_sequences WHERE schemaname = current_schema() AND sequencename = " . q(idf_unescape($sequence_name))
					: "SELECT * FROM $sequence_name"
				), null, "-- "));
				$sequences[] = ($style == "DROP+CREATE" ? "DROP SEQUENCE IF EXISTS $sequence_name;\n" : "")
					. "CREATE SEQUENCE $sequence_name INCREMENT $sq[increment_by] MINVALUE $sq[min_value] MAXVALUE $sq[max_value]"
					. ($auto_increment && $sq['last_value'] ? " START " . ($sq["last_value"] + 1) : "")
					. " CACHE $sq[cache_value];"
				;
			}
		}

		// adding sequences before table definition
		if (!empty($sequences)) {
			$return = implode("\n\n", $sequences) . "\n\n$return";
		}

		$primary = "";
		foreach (indexes($table) as $index_name => $index) {
			if ($index['type'] == 'PRIMARY') {
				$primary = $index_name;
				$return_parts[] = "CONSTRAINT " . idf_escape($index_name) . " PRIMARY KEY (" . implode(', ', array_map('Adminer\idf_escape', $index['columns'])) . ")";
			}
		}

		foreach (driver()->checkConstraints($table) as $conname => $consrc) {
			$return_parts[] = "CONSTRAINT " . idf_escape($conname) . " CHECK $consrc";
		}
		$return .= implode(",\n    ", $return_parts) . "\n)";

		$partition = driver()->partitionsInfo($status['Name']);
		if ($partition) {
			$return .= "\nPARTITION BY $partition[partition_by]($partition[partition])";
		}
		//! parse pg_class.relpartbound to create PARTITION OF
		//! don't insert partitioned data twice

		$return .= "\nWITH (oids = " . ($status['Oid'] ? 'true' : 'false') . ");";

		// comments for table & fields
		if ($status['Comment']) {
			$return .= "\n\nCOMMENT ON TABLE " . idf_escape($status['nspname']) . "." . idf_escape($status['Name']) . " IS " . q($status['Comment']) . ";";
		}

		foreach ($fields as $field_name => $field) {
			if ($field['comment']) {
				$return .= "\n\nCOMMENT ON COLUMN " . idf_escape($status['nspname']) . "." . idf_escape($status['Name']) . "." . idf_escape($field_name) . " IS " . q($field['comment']) . ";";
			}
		}

		foreach (get_rows("SELECT indexdef FROM pg_catalog.pg_indexes WHERE schemaname = current_schema() AND tablename = " . q($table) . ($primary ? " AND indexname != " . q($primary) : ""), null, "-- ") as $row) {
			$return .= "\n\n$row[indexdef];";
		}

		return rtrim($return, ';');
	}

	function truncate_sql($table) {
		return "TRUNCATE " . table($table);
	}

	function trigger_sql($table) {
		$status = table_status1($table);
		$return = "";
		foreach (triggers($table) as $trg_id => $trg) {
			$trigger = trigger($trg_id, $status['Name']);
			$return .= "\nCREATE TRIGGER " . idf_escape($trigger['Trigger']) . " $trigger[Timing] $trigger[Event] ON " . idf_escape($status["nspname"]) . "." . idf_escape($status['Name']) . " $trigger[Type] $trigger[Statement];;\n";
		}
		return $return;
	}


	function use_sql($database, $style = "") {
		$name = idf_escape($database);
		$return = "";
		if (preg_match('~CREATE~', $style)) {
			if ($style == "DROP+CREATE") {
				$return = "DROP DATABASE IF EXISTS $name;\n";
			}
			$return .= "CREATE DATABASE $name;\n"; //! get info from pg_database
		}
		return "$return\\connect $name";
	}

	function show_variables() {
		return get_rows("SHOW ALL");
	}

	function process_list() {
		return get_rows("SELECT * FROM pg_stat_activity ORDER BY " . (min_version(9.2) ? "pid" : "procpid"));
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function support($feature) {
		return preg_match('~^(check|columns|comment|database|drop_col|dump|descidx|indexes|kill|partial_indexes|routine|scheme|sequence|sql|table|trigger|type|variables|view'
			. (min_version(9.3) ? '|materializedview' : '')
			. (min_version(11) ? '|procedure' : '')
			. (connection()->flavor == 'cockroach' ? '' : '|processlist') // https://github.com/cockroachdb/cockroach/issues/24745
			. ')$~', $feature)
		;
	}

	function kill_process($val) {
		return queries("SELECT pg_terminate_backend(" . number($val) . ")");
	}

	function connection_id() {
		return "SELECT pg_backend_pid()";
	}

	function max_connections() {
		return get_val("SHOW max_connections");
	}
}

?>
<?php
add_driver("oracle", "Oracle (beta)");

if (isset($_GET["oracle"])) {
	define('Adminer\DRIVER', "oracle");

	if (extension_loaded("oci8") && $_GET["ext"] != "pdo") {
		class Db extends SqlDb {
			public $extension = "oci8";
			public $_current_db;
			private $link;

			function _error($errno, $error) {
				if (ini_bool("html_errors")) {
					$error = html_entity_decode(strip_tags($error));
				}
				$error = preg_replace('~^[^:]*: ~', '', $error);
				$this->error = $error;
			}

			function attach(string $server, string $username, string $password): string {
				$this->link = @oci_new_connect($username, $password, $server, "AL32UTF8");
				if ($this->link) {
					$this->server_info = oci_server_version($this->link);
					return '';
				}
				$error = oci_error();
				return $error["message"];
			}

			function quote(string $string): string {
				return "'" . str_replace("'", "''", $string) . "'";
			}

			function select_db(string $database) {
				$this->_current_db = $database;
				return true;
			}

			function query(string $query, bool $unbuffered = false) {
				$result = oci_parse($this->link, $query);
				$this->error = "";
				if (!$result) {
					$error = oci_error($this->link);
					$this->errno = $error["code"];
					$this->error = $error["message"];
					return false;
				}
				set_error_handler(array($this, '_error'));
				$return = @oci_execute($result);
				restore_error_handler();
				if ($return) {
					if (oci_num_fields($result)) {
						return new Result($result);
					}
					$this->affected_rows = oci_num_rows($result);
					oci_free_statement($result);
				}
				return $return;
			}

			function timeout(int $ms): bool {
				return oci_set_call_timeout($this->link, $ms);
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 1;

			function __construct($result) {
				$this->result = $result;
			}

			private function convert($row) {
				foreach ((array) $row as $key => $val) {
					if (is_a($val, 'OCILob') || is_a($val, 'OCI-Lob')) {
						$row[$key] = $val->load();
					}
				}
				return $row;
			}

			function fetch_assoc() {
				return $this->convert(oci_fetch_assoc($this->result));
			}

			function fetch_row() {
				return $this->convert(oci_fetch_row($this->result));
			}

			function fetch_field(): \stdClass {
				$column = $this->offset++;
				$return = new \stdClass;
				$return->name = oci_field_name($this->result, $column);
				$return->type = oci_field_type($this->result, $column); //! map to MySQL numbers
				$return->charsetnr = (preg_match("~raw|blob|bfile~", $return->type) ? 63 : 0); // 63 - binary
				return $return;
			}

			function __destruct() {
				oci_free_statement($this->result);
			}
		}

	} elseif (extension_loaded("pdo_oci")) {
		class Db extends PdoDb {
			public $extension = "PDO_OCI";
			public $_current_db;

			function attach(string $server, string $username, string $password): string {
				return $this->dsn("oci:dbname=//$server;charset=AL32UTF8", $username, $password);
			}

			function select_db(string $database) {
				$this->_current_db = $database;
				return true;
			}
		}

	}



	class Driver extends SqlDriver {
		static $extensions = array("OCI8", "PDO_OCI");
		static $jush = "oracle";

		public $insertFunctions = array( //! no parentheses
			"date" => "current_date",
			"timestamp" => "current_timestamp",
		);
		public $editFunctions = array(
			"number|float|double" => "+/-",
			"date|timestamp" => "+ interval/- interval",
			"char|clob" => "||",
		);

		public $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL");
		public $functions = array("length", "lower", "round", "upper");
		public $grouping = array("avg", "count", "count distinct", "max", "min", "sum");

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array(
				lang(27) => array("number" => 38, "binary_float" => 12, "binary_double" => 21),
				lang(28) => array("date" => 10, "timestamp" => 29, "interval year" => 12, "interval day" => 28), //! year(), day() to second()
				lang(29) => array("char" => 2000, "varchar2" => 4000, "nchar" => 2000, "nvarchar2" => 4000, "clob" => 4294967295, "nclob" => 4294967295),
				lang(30) => array("raw" => 2000, "long raw" => 2147483648, "blob" => 4294967295, "bfile" => 4294967296),
			);
		}

		//! support empty $set in insert()

		function begin() {
			return true; // automatic start
		}

		function insertUpdate(string $table, array $rows, array $primary) {
			foreach ($rows as $set) {
				$update = array();
				$where = array();
				foreach ($set as $key => $val) {
					$update[] = "$key = $val";
					if (isset($primary[idf_unescape($key)])) {
						$where[] = "$key = $val";
					}
				}
				if (
					!(($where && queries("UPDATE " . table($table) . " SET " . implode(", ", $update) . " WHERE " . implode(" AND ", $where)) && $this->conn->affected_rows)
					|| queries("INSERT INTO " . table($table) . " (" . implode(", ", array_keys($set)) . ") VALUES (" . implode(", ", $set) . ")"))
				) {
					return false;
				}
			}
			return true;
		}

		function hasCStyleEscapes(): bool {
			return true;
		}
	}



	function idf_escape($idf) {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function get_databases($flush) {
		return get_vals(
			"SELECT DISTINCT tablespace_name FROM (
SELECT tablespace_name FROM user_tablespaces
UNION SELECT tablespace_name FROM all_tables WHERE tablespace_name IS NOT NULL
)
ORDER BY 1"
		);
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return ($offset ? " * FROM (SELECT t.*, rownum AS rnum FROM (SELECT $query$where) t WHERE rownum <= " . ($limit + $offset) . ") WHERE rnum > $offset"
			: ($limit ? " * FROM (SELECT $query$where) WHERE rownum <= " . ($limit + $offset)
			: " $query$where"
		));
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return " $query$where"; //! limit
	}

	function db_collation($db, $collations) {
		return get_val("SELECT value FROM nls_database_parameters WHERE parameter = 'NLS_CHARACTERSET'"); //! respect $db
	}

	function logged_user() {
		return get_val("SELECT USER FROM DUAL");
	}

	function get_current_db() {
		$db = connection()->_current_db ?: DB;
		unset(connection()->_current_db);
		return $db;
	}

	function where_owner($prefix, $owner = "owner") {
		if (!$_GET["ns"]) {
			return '';
		}
		return "$prefix$owner = sys_context('USERENV', 'CURRENT_SCHEMA')";
	}

	function views_table($columns) {
		$owner = where_owner('');
		return "(SELECT $columns FROM all_views WHERE " . ($owner ?: "rownum < 0") . ")";
	}

	function tables_list() {
		$view = views_table("view_name");
		$owner = where_owner(" AND ");
		return get_key_vals(
			"SELECT table_name, 'table' FROM all_tables WHERE tablespace_name = " . q(DB) . "$owner
UNION SELECT view_name, 'view' FROM $view
ORDER BY 1"
		); //! views don't have schema
	}

	function count_tables($databases) {
		$return = array();
		foreach ($databases as $db) {
			$return[$db] = get_val("SELECT COUNT(*) FROM all_tables WHERE tablespace_name = " . q($db));
		}
		return $return;
	}

	function table_status($name = "") {
		$return = array();
		$search = q($name);
		$db = get_current_db();
		$view = views_table("view_name");
		$owner = where_owner(" AND ");
		foreach (
			get_rows('SELECT table_name "Name", \'table\' "Engine", avg_row_len * num_rows "Data_length", num_rows "Rows" FROM all_tables WHERE tablespace_name = ' . q($db) . $owner . ($name != "" ? " AND table_name = $search" : "") . "
UNION SELECT view_name, 'view', 0, 0 FROM $view" . ($name != "" ? " WHERE view_name = $search" : "") . "
ORDER BY 1") as $row
		) {
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	function is_view($table_status) {
		return $table_status["Engine"] == "view";
	}

	function fk_support($table_status) {
		return true;
	}

	function fields($table) {
		$return = array();
		$owner = where_owner(" AND ");
		foreach (get_rows("SELECT * FROM all_tab_columns WHERE table_name = " . q($table) . "$owner ORDER BY column_id") as $row) {
			$type = $row["DATA_TYPE"];
			$length = "$row[DATA_PRECISION],$row[DATA_SCALE]";
			if ($length == ",") {
				$length = $row["CHAR_COL_DECL_LENGTH"];
			} //! int
			$return[$row["COLUMN_NAME"]] = array(
				"field" => $row["COLUMN_NAME"],
				"full_type" => $type . ($length ? "($length)" : ""),
				"type" => strtolower($type),
				"length" => $length,
				"default" => $row["DATA_DEFAULT"],
				"null" => ($row["NULLABLE"] == "Y"),
				//! "auto_increment" => false,
				//! "collation" => $row["CHARACTER_SET_NAME"],
				"privileges" => array("insert" => 1, "select" => 1, "update" => 1, "where" => 1, "order" => 1),
				//! "comment" => $row["Comment"],
				//! "primary" => ($row["Key"] == "PRI"),
			);
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$return = array();
		$owner = where_owner(" AND ", "aic.table_owner");
		foreach (
			get_rows("SELECT aic.*, ac.constraint_type, atc.data_default
FROM all_ind_columns aic
LEFT JOIN all_constraints ac ON aic.index_name = ac.constraint_name AND aic.table_name = ac.table_name AND aic.index_owner = ac.owner
LEFT JOIN all_tab_cols atc ON aic.column_name = atc.column_name AND aic.table_name = atc.table_name AND aic.index_owner = atc.owner
WHERE aic.table_name = " . q($table) . "$owner
ORDER BY ac.constraint_type, aic.column_position", $connection2) as $row
		) {
			$index_name = $row["INDEX_NAME"];
			$column_name = $row["DATA_DEFAULT"];
			$column_name = ($column_name ? trim($column_name, '"') : $row["COLUMN_NAME"]); // trim - possibly wrapped in quotes but never contains quotes inside
			$return[$index_name]["type"] = ($row["CONSTRAINT_TYPE"] == "P" ? "PRIMARY" : ($row["CONSTRAINT_TYPE"] == "U" ? "UNIQUE" : "INDEX"));
			$return[$index_name]["columns"][] = $column_name;
			$return[$index_name]["lengths"][] = ($row["CHAR_LENGTH"] && $row["CHAR_LENGTH"] != $row["COLUMN_LENGTH"] ? $row["CHAR_LENGTH"] : null);
			$return[$index_name]["descs"][] = ($row["DESCEND"] && $row["DESCEND"] == "DESC" ? '1' : null);
		}
		return $return;
	}

	function view($name) {
		$view = views_table("view_name, text");
		$rows = get_rows('SELECT text "select" FROM ' . $view . ' WHERE view_name = ' . q($name));
		return reset($rows);
	}

	function collations() {
		return array(); //!
	}

	function information_schema($db) {
		return get_schema() == "INFORMATION_SCHEMA";
	}

	function error() {
		return h(connection()->error); //! highlight sqltext from offset
	}

	function explain($connection, $query) {
		$connection->query("EXPLAIN PLAN FOR $query");
		return $connection->query("SELECT * FROM plan_table");
	}

	function found_rows($table_status, $where) {
	}

	function auto_increment() {
		return "";
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$alter = $drop = array();
		$orig_fields = ($table ? fields($table) : array());
		foreach ($fields as $field) {
			$val = $field[1];
			if ($val && $field[0] != "" && idf_escape($field[0]) != $val[0]) {
				queries("ALTER TABLE " . table($table) . " RENAME COLUMN " . idf_escape($field[0]) . " TO $val[0]");
			}
			$orig_field = $orig_fields[$field[0]];
			if ($val && $orig_field) {
				$old = process_field($orig_field, $orig_field);
				if ($val[2] == $old[2]) {
					$val[2] = "";
				}
			}
			if ($val) {
				$alter[] = ($table != "" ? ($field[0] != "" ? "MODIFY (" : "ADD (") : "  ") . implode($val) . ($table != "" ? ")" : ""); //! error with name change only
			} else {
				$drop[] = idf_escape($field[0]);
			}
		}
		if ($table == "") {
			return queries("CREATE TABLE " . table($name) . " (\n" . implode(",\n", $alter) . "\n)");
		}
		return (!$alter || queries("ALTER TABLE " . table($table) . "\n" . implode("\n", $alter)))
			&& (!$drop || queries("ALTER TABLE " . table($table) . " DROP (" . implode(", ", $drop) . ")"))
			&& ($table == $name || queries("ALTER TABLE " . table($table) . " RENAME TO " . table($name)))
		;
	}

	function alter_indexes($table, $alter) {
		$drop = array();
		$queries = array();
		foreach ($alter as $val) {
			if ($val[0] != "INDEX") {
				$val[2] = preg_replace('~ DESC$~', '', $val[2]);
				$create = ($val[2] == "DROP"
					? "\nDROP CONSTRAINT " . idf_escape($val[1])
					: "\nADD" . ($val[1] != "" ? " CONSTRAINT " . idf_escape($val[1]) : "") . " $val[0] " . ($val[0] == "PRIMARY" ? "KEY " : "") . "(" . implode(", ", $val[2]) . ")"
				);
				array_unshift($queries, "ALTER TABLE " . table($table) . $create);
			} elseif ($val[2] == "DROP") {
				$drop[] = idf_escape($val[1]);
			} else {
				$queries[] = "CREATE INDEX " . idf_escape($val[1] != "" ? $val[1] : uniqid($table . "_")) . " ON " . table($table) . " (" . implode(", ", $val[2]) . ")";
			}
		}
		if ($drop) {
			array_unshift($queries, "DROP INDEX " . implode(", ", $drop));
		}
		foreach ($queries as $query) {
			if (!queries($query)) {
				return false;
			}
		}
		return true;
	}

	function foreign_keys($table) {
		$return = array();
		$query = "SELECT c_list.CONSTRAINT_NAME as NAME,
c_src.COLUMN_NAME as SRC_COLUMN,
c_dest.OWNER as DEST_DB,
c_dest.TABLE_NAME as DEST_TABLE,
c_dest.COLUMN_NAME as DEST_COLUMN,
c_list.DELETE_RULE as ON_DELETE
FROM ALL_CONSTRAINTS c_list, ALL_CONS_COLUMNS c_src, ALL_CONS_COLUMNS c_dest
WHERE c_list.CONSTRAINT_NAME = c_src.CONSTRAINT_NAME
AND c_list.R_CONSTRAINT_NAME = c_dest.CONSTRAINT_NAME
AND c_list.CONSTRAINT_TYPE = 'R'
AND c_src.TABLE_NAME = " . q($table);
		foreach (get_rows($query) as $row) {
			$return[$row['NAME']] = array(
				"db" => $row['DEST_DB'],
				"table" => $row['DEST_TABLE'],
				"source" => array($row['SRC_COLUMN']),
				"target" => array($row['DEST_COLUMN']),
				"on_delete" => $row['ON_DELETE'],
				"on_update" => null,
			);
		}
		return $return;
	}

	function truncate_tables($tables) {
		return apply_queries("TRUNCATE TABLE", $tables);
	}

	function drop_views($views) {
		return apply_queries("DROP VIEW", $views);
	}

	function drop_tables($tables) {
		return apply_queries("DROP TABLE", $tables);
	}

	function last_id($result) {
		return 0; //!
	}

	function schemas() {
		$return = get_vals("SELECT DISTINCT owner FROM dba_segments WHERE owner IN (SELECT username FROM dba_users WHERE default_tablespace NOT IN ('SYSTEM','SYSAUX')) ORDER BY 1");
		return ($return ?: get_vals("SELECT DISTINCT owner FROM all_tables WHERE tablespace_name = " . q(DB) . " ORDER BY 1"));
	}

	function get_schema() {
		return get_val("SELECT sys_context('USERENV', 'SESSION_USER') FROM dual");
	}

	function set_schema($scheme, $connection2 = null) {
		if (!$connection2) {
			$connection2 = connection();
		}
		return $connection2->query("ALTER SESSION SET CURRENT_SCHEMA = " . idf_escape($scheme));
	}

	function show_variables() {
		return get_rows('SELECT name, display_value FROM v$parameter');
	}

	function show_status() {
		$return = array();
		$rows = get_rows('SELECT * FROM v$instance');
		foreach (reset($rows) as $key => $val) {
			$return[] = array($key, $val);
		}
		return $return;
	}

	function process_list() {
		return get_rows('SELECT
	sess.process AS "process",
	sess.username AS "user",
	sess.schemaname AS "schema",
	sess.status AS "status",
	sess.wait_class AS "wait_class",
	sess.seconds_in_wait AS "seconds_in_wait",
	sql.sql_text AS "sql_text",
	sess.machine AS "machine",
	sess.port AS "port"
FROM v$session sess LEFT OUTER JOIN v$sql sql
ON sql.sql_id = sess.sql_id
WHERE sess.type = \'USER\'
ORDER BY PROCESS
');
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function support($feature) {
		return preg_match('~^(columns|database|drop_col|indexes|descidx|processlist|scheme|sql|status|table|variables|view)$~', $feature); //!
	}
}

?>
<?php
/**
* @author Jakub Cernohuby
* @author Vladimir Stastka
* @author Jakub Vrana
*/

add_driver("mssql", "MS SQL");

if (isset($_GET["mssql"])) {
	define('Adminer\DRIVER', "mssql");

	if (extension_loaded("sqlsrv") && $_GET["ext"] != "pdo") {
		class Db extends SqlDb {
			public $extension = "sqlsrv";
			private $link, $result;

			private function get_error() {
				$this->error = "";
				foreach (sqlsrv_errors() as $error) {
					$this->errno = $error["code"];
					$this->error .= "$error[message]\n";
				}
				$this->error = rtrim($this->error);
			}

			function attach(string $server, string $username, string $password): string {
				$connection_info = array("UID" => $username, "PWD" => $password, "CharacterSet" => "UTF-8");
				$ssl = adminer()->connectSsl();
				if (isset($ssl["Encrypt"])) {
					$connection_info["Encrypt"] = $ssl["Encrypt"];
				}
				if (isset($ssl["TrustServerCertificate"])) {
					$connection_info["TrustServerCertificate"] = $ssl["TrustServerCertificate"];
				}
				$db = adminer()->database();
				if ($db != "") {
					$connection_info["Database"] = $db;
				}
				list($host, $port) = host_port($server);
				$this->link = @sqlsrv_connect($host . ($port ? ",$port" : ""), $connection_info);
				if ($this->link) {
					$info = sqlsrv_server_info($this->link);
					$this->server_info = $info['SQLServerVersion'];
				} else {
					$this->get_error();
				}
				return ($this->link ? '' : $this->error);
			}

			function quote(string $string): string {
				$unicode = strlen($string) != strlen(utf8_decode($string));
				return ($unicode ? "N" : "") . "'" . str_replace("'", "''", $string) . "'";
			}

			function select_db(string $database) {
				return $this->query(use_sql($database));
			}

			function query(string $query, bool $unbuffered = false) {
				$result = sqlsrv_query($this->link, $query); //! , array(), ($unbuffered ? array() : array("Scrollable" => "keyset"))
				$this->error = "";
				if (!$result) {
					$this->get_error();
					return false;
				}
				return $this->store_result($result);
			}

			function multi_query(string $query) {
				$this->result = sqlsrv_query($this->link, $query);
				$this->error = "";
				if (!$this->result) {
					$this->get_error();
					return false;
				}
				return true;
			}

			function store_result($result = null) {
				if (!$result) {
					$result = $this->result;
				}
				if (!$result) {
					return false;
				}
				if (sqlsrv_field_metadata($result)) {
					return new Result($result);
				}
				$this->affected_rows = sqlsrv_rows_affected($result);
				return true;
			}

			function next_result(): bool {
				return $this->result ? !!sqlsrv_next_result($this->result) : false;
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 0, $fields;

			function __construct($result) {
				$this->result = $result;
				// $this->num_rows = sqlsrv_num_rows($result); // available only in scrollable results
			}

			private function convert($row) {
				foreach ((array) $row as $key => $val) {
					if (is_a($val, 'DateTime')) {
						$row[$key] = $val->format("Y-m-d H:i:s");
					}
					//! stream
				}
				return $row;
			}

			function fetch_assoc() {
				return $this->convert(sqlsrv_fetch_array($this->result, SQLSRV_FETCH_ASSOC));
			}

			function fetch_row() {
				return $this->convert(sqlsrv_fetch_array($this->result, SQLSRV_FETCH_NUMERIC));
			}

			function fetch_field(): \stdClass {
				if (!$this->fields) {
					$this->fields = sqlsrv_field_metadata($this->result);
				}
				$field = $this->fields[$this->offset++];
				$return = new \stdClass;
				$return->name = $field["Name"];
				$return->type = ($field["Type"] == 1 ? 254 : 15);
				$return->charsetnr = 0;
				return $return;
			}

			function seek($offset) {
				for ($i=0; $i < $offset; $i++) {
					sqlsrv_fetch($this->result); // SQLSRV_SCROLL_ABSOLUTE added in sqlsrv 1.1
				}
			}

			function __destruct() {
				sqlsrv_free_stmt($this->result);
			}
		}

		function last_id($result) {
			return get_val("SELECT SCOPE_IDENTITY()"); // @@IDENTITY can return trigger INSERT
		}

		function explain($connection, $query) {
			$connection->query("SET SHOWPLAN_ALL ON");
			$return = $connection->query($query);
			$connection->query("SET SHOWPLAN_ALL OFF"); // connection is used also for indexes
			return $return;
		}

	} else {
		abstract class MssqlDb extends PdoDb {
			function select_db(string $database) {
				// database selection is separated from the connection so dbname in DSN can't be used
				return $this->query(use_sql($database));
			}

			function lastInsertId() {
				return $this->pdo->lastInsertId();
			}
		}

		function last_id($result) {
			return connection()->lastInsertId();
		}

		function explain($connection, $query) {
		}

		if (extension_loaded("pdo_sqlsrv")) {
			class Db extends MssqlDb {
				public $extension = "PDO_SQLSRV";

				function attach(string $server, string $username, string $password): string {
					list($host, $port) = host_port($server);
					return $this->dsn("sqlsrv:Server=$host" . ($port ? ",$port" : ""), $username, $password);
				}
			}

		} elseif (extension_loaded("pdo_dblib")) {
			class Db extends MssqlDb {
				public $extension = "PDO_DBLIB";

				function attach(string $server, string $username, string $password): string {
					list($host, $port) = host_port($server);
					return $this->dsn("dblib:charset=utf8;host=$host" . ($port ? (is_numeric($port) ? ";port=" : ";unix_socket=") . $port : ""), $username, $password);
				}
			}
		}
	}


	class Driver extends SqlDriver {
		static $extensions = array("SQLSRV", "PDO_SQLSRV", "PDO_DBLIB");
		static $jush = "mssql";

		public $insertFunctions = array("date|time" => "getdate");
		public $editFunctions = array(
			"int|decimal|real|float|money|datetime" => "+/-",
			"char|text" => "+",
		);

		public $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL");
		public $functions = array("len", "lower", "round", "upper");
		public $grouping = array("avg", "count", "count distinct", "max", "min", "sum");
		public $generated = array("PERSISTED", "VIRTUAL");
		public $onActions = "NO ACTION|CASCADE|SET NULL|SET DEFAULT";

		static function connect(string $server, string $username, string $password) {
			if ($server == "") {
				$server = "localhost:1433";
			}
			return parent::connect($server, $username, $password);
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array( //! use sys.types
				lang(27) => array("tinyint" => 3, "smallint" => 5, "int" => 10, "bigint" => 20, "bit" => 1, "decimal" => 0, "real" => 12, "float" => 53, "smallmoney" => 10, "money" => 20),
				lang(28) => array("date" => 10, "smalldatetime" => 19, "datetime" => 19, "datetime2" => 19, "time" => 8, "datetimeoffset" => 10),
				lang(29) => array("char" => 8000, "varchar" => 8000, "text" => 2147483647, "nchar" => 4000, "nvarchar" => 4000, "ntext" => 1073741823),
				lang(30) => array("binary" => 8000, "varbinary" => 8000, "image" => 2147483647),
			);
		}

		function insertUpdate(string $table, array $rows, array $primary) {
			$fields = fields($table);
			$update = array();
			$where = array();
			$set = reset($rows);
			$columns = "c" . implode(", c", range(1, count($set)));
			$c = 0;
			$insert = array();
			foreach ($set as $key => $val) {
				$c++;
				$name = idf_unescape($key);
				if (!$fields[$name]["auto_increment"]) {
					$insert[$key] = "c$c";
				}
				if (isset($primary[$name])) {
					$where[] = "$key = c$c";
				} else {
					$update[] = "$key = c$c";
				}
			}
			$values = array();
			foreach ($rows as $set) {
				$values[] = "(" . implode(", ", $set) . ")";
			}
			if ($where) {
				$identity = queries("SET IDENTITY_INSERT " . table($table) . " ON");
				$return = queries(
					"MERGE " . table($table) . " USING (VALUES\n\t" . implode(",\n\t", $values) . "\n) AS source ($columns) ON " . implode(" AND ", $where) //! source, c1 - possible conflict
					. ($update ? "\nWHEN MATCHED THEN UPDATE SET " . implode(", ", $update) : "")
					. "\nWHEN NOT MATCHED THEN INSERT (" . implode(", ", array_keys($identity ? $set : $insert)) . ") VALUES (" . ($identity ? $columns : implode(", ", $insert)) . ");" // ; is mandatory
				);
				if ($identity) {
					queries("SET IDENTITY_INSERT " . table($table) . " OFF");
				}
			} else {
				$return = queries("INSERT INTO " . table($table) . " (" . implode(", ", array_keys($set)) . ") VALUES\n" . implode(",\n", $values));
			}
			return $return;
		}

		function begin() {
			return queries("BEGIN TRANSACTION");
		}

		function tableHelp(string $name, bool $is_view = false) {
			$links = array(
				"sys" => "catalog-views/sys-",
				"INFORMATION_SCHEMA" => "information-schema-views/",
			);
			$link = $links[get_schema()];
			if ($link) {
				return "relational-databases/system-$link" . preg_replace('~_~', '-', strtolower($name)) . "-transact-sql";
			}
		}
	}



	function idf_escape($idf) {
		return "[" . str_replace("]", "]]", $idf) . "]";
	}

	function table($idf) {
		return ($_GET["ns"] != "" ? idf_escape($_GET["ns"]) . "." : "") . idf_escape($idf);
	}

	function get_databases($flush) {
		return get_vals("SELECT name FROM sys.databases WHERE name NOT IN ('master', 'tempdb', 'model', 'msdb')");
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return ($limit ? " TOP (" . ($limit + $offset) . ")" : "") . " $query$where"; // seek later
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return limit($query, $where, 1, 0, $separator);
	}

	function db_collation($db, $collations) {
		return get_val("SELECT collation_name FROM sys.databases WHERE name = " . q($db));
	}

	function logged_user() {
		return get_val("SELECT SUSER_NAME()");
	}

	function tables_list() {
		return get_key_vals("SELECT name, type_desc FROM sys.all_objects WHERE schema_id = SCHEMA_ID(" . q(get_schema()) . ") AND type IN ('S', 'U', 'V') ORDER BY name");
	}

	function count_tables($databases) {
		$return = array();
		foreach ($databases as $db) {
			connection()->select_db($db);
			$return[$db] = get_val("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES");
		}
		return $return;
	}

	function table_status($name = "") {
		$return = array();
		foreach (
			get_rows("SELECT ao.name AS Name, ao.type_desc AS Engine, (SELECT value FROM fn_listextendedproperty(default, 'SCHEMA', schema_name(schema_id), 'TABLE', ao.name, null, null)) AS Comment
FROM sys.all_objects AS ao
WHERE schema_id = SCHEMA_ID(" . q(get_schema()) . ") AND type IN ('S', 'U', 'V') " . ($name != "" ? "AND name = " . q($name) : "ORDER BY name")) as $row
		) {
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	function is_view($table_status) {
		return $table_status["Engine"] == "VIEW";
	}

	function fk_support($table_status) {
		return true;
	}

	function fields($table) {
		$comments = get_key_vals("SELECT objname, cast(value as varchar(max)) FROM fn_listextendedproperty('MS_DESCRIPTION', 'schema', " . q(get_schema()) . ", 'table', " . q($table) . ", 'column', NULL)");
		$return = array();
		$table_id = get_val("SELECT object_id FROM sys.all_objects WHERE schema_id = SCHEMA_ID(" . q(get_schema()) . ") AND type IN ('S', 'U', 'V') AND name = " . q($table));
		foreach (
			get_rows("SELECT c.max_length, c.precision, c.scale, c.name, c.is_nullable, c.is_identity, c.collation_name, t.name type, d.definition [default], d.name default_constraint, i.is_primary_key
FROM sys.all_columns c
JOIN sys.types t ON c.user_type_id = t.user_type_id
LEFT JOIN sys.default_constraints d ON c.default_object_id = d.object_id
LEFT JOIN sys.index_columns ic ON c.object_id = ic.object_id AND c.column_id = ic.column_id
LEFT JOIN sys.indexes i ON ic.object_id = i.object_id AND ic.index_id = i.index_id
WHERE c.object_id = " . q($table_id)) as $row
		) {
			$type = $row["type"];
			$length = (preg_match("~char|binary~", $type)
				? intval($row["max_length"]) / ($type[0] == 'n' ? 2 : 1)
				: ($type == "decimal" ? "$row[precision],$row[scale]" : "")
			);
			$return[$row["name"]] = array(
				"field" => $row["name"],
				"full_type" => $type . ($length ? "($length)" : ""),
				"type" => $type,
				"length" => $length,
				"default" => (preg_match("~^\('(.*)'\)$~", $row["default"], $match) ? str_replace("''", "'", $match[1]) : $row["default"]),
				"default_constraint" => $row["default_constraint"],
				"null" => $row["is_nullable"],
				"auto_increment" => $row["is_identity"],
				"collation" => $row["collation_name"],
				"privileges" => array("insert" => 1, "select" => 1, "update" => 1, "where" => 1, "order" => 1),
				"primary" => $row["is_primary_key"],
				"comment" => $comments[$row["name"]],
			);
		}
		foreach (get_rows("SELECT * FROM sys.computed_columns WHERE object_id = " . q($table_id)) as $row) {
			$return[$row["name"]]["generated"] = ($row["is_persisted"] ? "PERSISTED" : "VIRTUAL");
			$return[$row["name"]]["default"] = $row["definition"];
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$return = array();
		// sp_statistics doesn't return information about primary key
		foreach (
			get_rows("SELECT i.name, key_ordinal, is_unique, is_primary_key, c.name AS column_name, is_descending_key
FROM sys.indexes i
INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
WHERE OBJECT_NAME(i.object_id) = " . q($table), $connection2) as $row
		) {
			$name = $row["name"];
			$return[$name]["type"] = ($row["is_primary_key"] ? "PRIMARY" : ($row["is_unique"] ? "UNIQUE" : "INDEX"));
			$return[$name]["lengths"] = array();
			$return[$name]["columns"][$row["key_ordinal"]] = $row["column_name"];
			$return[$name]["descs"][$row["key_ordinal"]] = ($row["is_descending_key"] ? '1' : null);
		}
		return $return;
	}

	function view($name) {
		return array("select" => preg_replace('~^(?:[^[]|\[[^]]*])*\s+AS\s+~isU', '', get_val("SELECT VIEW_DEFINITION FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = SCHEMA_NAME() AND TABLE_NAME = " . q($name))));
	}

	function collations() {
		$return = array();
		foreach (get_vals("SELECT name FROM fn_helpcollations()") as $collation) {
			$return[preg_replace('~_.*~', '', $collation)][] = $collation;
		}
		return $return;
	}

	function information_schema($db) {
		return get_schema() == "INFORMATION_SCHEMA";
	}

	function error() {
		return nl_br(h(preg_replace('~^(\[[^]]*])+~m', '', connection()->error)));
	}

	function create_database($db, $collation) {
		return queries("CREATE DATABASE " . idf_escape($db) . (preg_match('~^[a-z0-9_]+$~i', $collation) ? " COLLATE $collation" : ""));
	}

	function drop_databases($databases) {
		return queries("DROP DATABASE " . implode(", ", array_map('Adminer\idf_escape', $databases)));
	}

	function rename_database($name, $collation) {
		if (preg_match('~^[a-z0-9_]+$~i', $collation)) {
			queries("ALTER DATABASE " . idf_escape(DB) . " COLLATE $collation");
		}
		queries("ALTER DATABASE " . idf_escape(DB) . " MODIFY NAME = " . idf_escape($name));
		return true; //! false negative "The database name 'test2' has been set."
	}

	function auto_increment() {
		return " IDENTITY" . ($_POST["Auto_increment"] != "" ? "(" . number($_POST["Auto_increment"]) . ",1)" : "") . " PRIMARY KEY";
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$alter = array();
		$comments = array();
		$orig_fields = fields($table);
		foreach ($fields as $field) {
			$column = idf_escape($field[0]);
			$val = $field[1];
			if (!$val) {
				$alter["DROP"][] = " COLUMN $column";
			} else {
				$val[1] = preg_replace("~( COLLATE )'(\\w+)'~", '\1\2', $val[1]);
				$comments[$field[0]] = $val[5];
				unset($val[5]);
				if (preg_match('~ AS ~', $val[3])) {
					unset($val[1], $val[2]);
				}
				if ($field[0] == "") {
					$alter["ADD"][] = "\n  " . implode("", $val) . ($table == "" ? substr($foreign[$val[0]], 16 + strlen($val[0])) : ""); // 16 - strlen("  FOREIGN KEY ()")
				} else {
					$default = $val[3];
					unset($val[3]); // default values are set separately
					unset($val[6]); //! identity can't be removed
					if ($column != $val[0]) {
						queries("EXEC sp_rename " . q(table($table) . ".$column") . ", " . q(idf_unescape($val[0])) . ", 'COLUMN'");
					}
					$alter["ALTER COLUMN " . implode("", $val)][] = "";
					$orig_field = $orig_fields[$field[0]];
					if (default_value($orig_field) != $default) {
						if ($orig_field["default"] !== null) {
							$alter["DROP"][] = " " . idf_escape($orig_field["default_constraint"]);
						}
						if ($default) {
							$alter["ADD"][] = "\n $default FOR $column";
						}
					}
				}
			}
		}
		if ($table == "") {
			return queries("CREATE TABLE " . table($name) . " (" . implode(",", (array) $alter["ADD"]) . "\n)");
		}
		if ($table != $name) {
			queries("EXEC sp_rename " . q(table($table)) . ", " . q($name));
		}
		if ($foreign) {
			$alter[""] = $foreign;
		}
		foreach ($alter as $key => $val) {
			if (!queries("ALTER TABLE " . table($name) . " $key" . implode(",", $val))) {
				return false;
			}
		}
		foreach ($comments as $key => $val) {
			$comment = substr($val, 9); // 9 - strlen(" COMMENT ")
			queries("EXEC sp_dropextendedproperty @name = N'MS_Description', @level0type = N'Schema', @level0name = " . q(get_schema()) . ", @level1type = N'Table', @level1name = " . q($name) . ", @level2type = N'Column', @level2name = " . q($key));
			queries("EXEC sp_addextendedproperty
@name = N'MS_Description',
@value = $comment,
@level0type = N'Schema',
@level0name = " . q(get_schema()) . ",
@level1type = N'Table',
@level1name = " . q($name) . ",
@level2type = N'Column',
@level2name = " . q($key))
			;
		}
		return true;
	}

	function alter_indexes($table, $alter) {
		$index = array();
		$drop = array();
		foreach ($alter as $val) {
			if ($val[2] == "DROP") {
				if ($val[0] == "PRIMARY") { //! sometimes used also for UNIQUE
					$drop[] = idf_escape($val[1]);
				} else {
					$index[] = idf_escape($val[1]) . " ON " . table($table);
				}
			} elseif (
				!queries(($val[0] != "PRIMARY"
					? "CREATE $val[0] " . ($val[0] != "INDEX" ? "INDEX " : "") . idf_escape($val[1] != "" ? $val[1] : uniqid($table . "_")) . " ON " . table($table)
					: "ALTER TABLE " . table($table) . " ADD PRIMARY KEY"
				) . " (" . implode(", ", $val[2]) . ")")
			) {
				return false;
			}
		}
		return (!$index || queries("DROP INDEX " . implode(", ", $index)))
			&& (!$drop || queries("ALTER TABLE " . table($table) . " DROP " . implode(", ", $drop)))
		;
	}

	function found_rows($table_status, $where) {
	}

	function foreign_keys($table) {
		$return = array();
		$on_actions = array("CASCADE", "NO ACTION", "SET NULL", "SET DEFAULT");
		foreach (get_rows("EXEC sp_fkeys @fktable_name = " . q($table) . ", @fktable_owner = " . q(get_schema())) as $row) {
			$foreign_key = &$return[$row["FK_NAME"]];
			$foreign_key["db"] = $row["PKTABLE_QUALIFIER"];
			$foreign_key["ns"] = $row["PKTABLE_OWNER"];
			$foreign_key["table"] = $row["PKTABLE_NAME"];
			$foreign_key["on_update"] = $on_actions[$row["UPDATE_RULE"]];
			$foreign_key["on_delete"] = $on_actions[$row["DELETE_RULE"]];
			$foreign_key["source"][] = $row["FKCOLUMN_NAME"];
			$foreign_key["target"][] = $row["PKCOLUMN_NAME"];
		}
		return $return;
	}

	function truncate_tables($tables) {
		return apply_queries("TRUNCATE TABLE", $tables);
	}

	function drop_views($views) {
		return queries("DROP VIEW " . implode(", ", array_map('Adminer\table', $views)));
	}

	function drop_tables($tables) {
		return queries("DROP TABLE " . implode(", ", array_map('Adminer\table', $tables)));
	}

	function move_tables($tables, $views, $target) {
		return apply_queries("ALTER SCHEMA " . idf_escape($target) . " TRANSFER", array_merge($tables, $views));
	}

	function trigger($name, $table) {
		if ($name == "") {
			return array();
		}
		$rows = get_rows(
			"SELECT s.name [Trigger],
CASE WHEN OBJECTPROPERTY(s.id, 'ExecIsInsertTrigger') = 1 THEN 'INSERT' WHEN OBJECTPROPERTY(s.id, 'ExecIsUpdateTrigger') = 1 THEN 'UPDATE' WHEN OBJECTPROPERTY(s.id, 'ExecIsDeleteTrigger') = 1 THEN 'DELETE' END [Event],
CASE WHEN OBJECTPROPERTY(s.id, 'ExecIsInsteadOfTrigger') = 1 THEN 'INSTEAD OF' ELSE 'AFTER' END [Timing],
c.text
FROM sysobjects s
JOIN syscomments c ON s.id = c.id
WHERE s.xtype = 'TR' AND s.name = " . q($name)
		); // triggers are not schema-scoped
		$return = reset($rows);
		if ($return) {
			$return["Statement"] = preg_replace('~^.+\s+AS\s+~isU', '', $return["text"]); //! identifiers, comments
		}
		return $return;
	}

	function triggers($table) {
		$return = array();
		foreach (
			get_rows("SELECT sys1.name,
CASE WHEN OBJECTPROPERTY(sys1.id, 'ExecIsInsertTrigger') = 1 THEN 'INSERT' WHEN OBJECTPROPERTY(sys1.id, 'ExecIsUpdateTrigger') = 1 THEN 'UPDATE' WHEN OBJECTPROPERTY(sys1.id, 'ExecIsDeleteTrigger') = 1 THEN 'DELETE' END [Event],
CASE WHEN OBJECTPROPERTY(sys1.id, 'ExecIsInsteadOfTrigger') = 1 THEN 'INSTEAD OF' ELSE 'AFTER' END [Timing]
FROM sysobjects sys1
JOIN sysobjects sys2 ON sys1.parent_obj = sys2.id
WHERE sys1.xtype = 'TR' AND sys2.name = " . q($table)) as $row
		) { // triggers are not schema-scoped
			$return[$row["name"]] = array($row["Timing"], $row["Event"]);
		}
		return $return;
	}

	function trigger_options() {
		return array(
			"Timing" => array("AFTER", "INSTEAD OF"),
			"Event" => array("INSERT", "UPDATE", "DELETE"),
			"Type" => array("AS"),
		);
	}

	function schemas() {
		return get_vals("SELECT name FROM sys.schemas");
	}

	function get_schema() {
		if ($_GET["ns"] != "") {
			return $_GET["ns"];
		}
		return get_val("SELECT SCHEMA_NAME()");
	}

	function set_schema($schema) {
		$_GET["ns"] = $schema;
		return true; // ALTER USER is permanent
	}

	function create_sql($table, $auto_increment, $style) {
		if (is_view(table_status1($table))) {
			$view = view($table);
			return "CREATE VIEW " . table($table) . " AS $view[select]";
		}
		$fields = array();
		$primary = false;
		foreach (fields($table) as $name => $field) {
			$val = process_field($field, $field);
			if ($val[6]) {
				$primary = true;
			}
			$fields[] = implode("", $val);
		}
		foreach (indexes($table) as $name => $index) {
			if (!$primary || $index["type"] != "PRIMARY") {
				$columns = array();
				foreach ($index["columns"] as $key => $val) {
					$columns[] = idf_escape($val) . ($index["descs"][$key] ? " DESC" : "");
				}
				$name = idf_escape($name);
				$fields[] = ($index["type"] == "INDEX" ? "INDEX $name" : "CONSTRAINT $name " . ($index["type"] == "UNIQUE" ? "UNIQUE" : "PRIMARY KEY")) . " (" . implode(", ", $columns) . ")";
			}
		}
		foreach (driver()->checkConstraints($table) as $name => $check) {
			$fields[] = "CONSTRAINT " . idf_escape($name) . " CHECK ($check)";
		}
		return "CREATE TABLE " . table($table) . " (\n\t" . implode(",\n\t", $fields) . "\n)";
	}

	function foreign_keys_sql($table) {
		$fields = array();
		foreach (foreign_keys($table) as $foreign) {
			$fields[] = ltrim(format_foreign_key($foreign));
		}
		return ($fields ? "ALTER TABLE " . table($table) . " ADD\n\t" . implode(",\n\t", $fields) . ";\n\n" : "");
	}

	function truncate_sql($table) {
		return "TRUNCATE TABLE " . table($table);
	}

	function use_sql($database, $style = "") {
		return "USE " . idf_escape($database);
	}

	function trigger_sql($table) {
		$return = "";
		foreach (triggers($table) as $name => $trigger) {
			$return .= create_trigger(" ON " . table($table), trigger($name, $table)) . ";";
		}
		return $return;
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function support($feature) {
		return preg_match('~^(check|comment|columns|database|drop_col|dump|indexes|descidx|scheme|sql|table|trigger|view|view_trigger)$~', $feature); //! routine|
	}
}

?>
<?php
// any method change in this file should be transferred to editor/include/adminer.inc.php

/** Default Adminer plugin; it should call methods via adminer()->f() instead of $this->f() to give chance to other plugins */
class Adminer {
	/** @var Adminer|Plugins */ static $instance;
	/** @visibility protected(set) */ public string $error = ''; // HTML

	/** Name in title and navigation
	* @return string HTML code
	*/
	function name(): string {
		return "<a href='https://www.adminer.org/'" . target_blank() . " id='h1'><img src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=logo.png&version=5.4.1") . "' width='24' height='24' alt='' id='logo'>Adminer</a>";
	}

	/** Connection parameters
	* @return array{string, string, string}
	*/
	function credentials(): array {
		return array(SERVER, $_GET["username"], get_password());
	}

	/** Get SSL connection options
	* @return string[]|void
	*/
	function connectSsl() {
	}

	/** Get key used for permanent login
	* @return string cryptic string which gets combined with password or '' in case of an error
	*/
	function permanentLogin(bool $create = false): string {
		return password_file($create);
	}

	/** Return key used to group brute force attacks; behind a reverse proxy, you want to return the last part of X-Forwarded-For */
	function bruteForceKey(): string {
		return $_SERVER["REMOTE_ADDR"];
	}

	/** Get server name displayed in breadcrumbs
	* @return string HTML code or null
	*/
	function serverName(?string $server): string {
		return h($server);
	}

	/** Identifier of selected database */
	function database(): ?string {
		// should be used everywhere instead of DB
		return DB;
	}

	/** Get cached list of databases
	* @return list<string>
	*/
	function databases(bool $flush = true): array {
		return get_databases($flush);
	}

	/** Print links after list of plugins */
	function pluginsLinks(): void {
	}

	/** Operators used in select
	* @return list<string> operators
	*/
	function operators(): array {
		return driver()->operators;
	}

	/** Get list of schemas
	* @return list<string>
	*/
	function schemas(): array {
		return schemas();
	}

	/** Specify limit for waiting on some slow queries like DB list
	* @return float number of seconds
	*/
	function queryTimeout(): float {
		return 2;
	}

	/** Called after connecting and selecting a database */
	function afterConnect(): void {
	}

	/** Headers to send before HTML output */
	function headers(): void {
	}

	/** Get Content Security Policy headers
	* @param list<string[]> $csp of arrays with directive name in key, allowed sources in value
	* @return list<string[]> same as $csp
	*/
	function csp(array $csp): array {
		return $csp;
	}

	/** Print HTML code inside <head>
	* @param bool $dark dark CSS: false to disable, true to force, null to base on user preferences
	* @return bool true to link favicon.ico
	*/
	function head(?bool $dark = null): bool {
		// this is matched by compile.php
		
		
		return true;
	}

	/** Print extra classes in <body class>; must start with a space */
	function bodyClass(): void {
		echo " adminer";
	}

	/** Get URLs of the CSS files
	* @return string[] key is URL, value is either 'light' (supports only light color scheme), 'dark' or '' (both)
	*/
	function css(): array {
		$return = array();
		foreach (array("", "-dark") as $mode) {
			$filename = "adminer$mode.css";
			if (file_exists($filename)) {
				$file = file_get_contents($filename);
				$return["$filename?v=" . crc32($file)] = ($mode
					? "dark"
					: (preg_match('~prefers-color-scheme:\s*dark~', $file) ? '' : 'light')
				);
			}
		}
		return $return;
	}

	/** Print login form */
	function loginForm(): void {
		echo "<table class='layout'>\n";
		// this is matched by compile.php
		echo adminer()->loginFormField('driver', '<tr><th>' . lang(33) . '<td>', html_select("auth[driver]", SqlDriver::$drivers, DRIVER, "loginDriver(this);"));
		echo adminer()->loginFormField('server', '<tr><th>' . lang(34) . '<td>', '<input name="auth[server]" value="' . h(SERVER) . '" title="hostname[:port]" placeholder="localhost" autocapitalize="off">');
		// this is matched by compile.php
		echo adminer()->loginFormField('username', '<tr><th>' . lang(35) . '<td>', '<input name="auth[username]" id="username" autofocus value="' . h($_GET["username"]) . '" autocomplete="username" autocapitalize="off">' . script("const authDriver = qs('#username').form['auth[driver]']; authDriver && authDriver.onchange();"));
		echo adminer()->loginFormField('password', '<tr><th>' . lang(36) . '<td>', '<input type="password" name="auth[password]" autocomplete="current-password">');
		echo adminer()->loginFormField('db', '<tr><th>' . lang(37) . '<td>', '<input name="auth[db]" value="' . h($_GET["db"]) . '" autocapitalize="off">');
		echo "</table>\n";
		echo "<p><input type='submit' value='" . lang(38) . "'>\n";
		echo checkbox("auth[permanent]", 1, $_COOKIE["adminer_permanent"], lang(39)) . "\n";
	}

	/** Get login form field
	* @param string $heading HTML
	* @param string $value HTML
	*/
	function loginFormField(string $name, string $heading, string $value): string {
		return $heading . $value . "\n";
	}

	/** Authorize the user
	* @return mixed true for success, string for error message, false for unknown error
	*/
	function login(string $login, string $password) {
		if ($password == "") {
			return lang(40, target_blank());
		}
		return true;
	}

	/** Table caption used in navigation and headings
	* @param TableStatus $tableStatus
	* @return string HTML code, "" to ignore table
	*/
	function tableName(array $tableStatus): string {
		return h($tableStatus["Name"]);
	}

	/** Field caption used in select and edit
	* @param Field|RoutineField $field
	* @param int $order order of column in select
	* @return string HTML code, "" to ignore field
	*/
	function fieldName(array $field, int $order = 0): string {
		$type = $field["full_type"];
		$comment = $field["comment"];
		return '<span title="' . h($type . ($comment != "" ? ($type ? ": " : "") . $comment : '')) . '">' . h($field["field"]) . '</span>';
	}

	/** Print links after select heading
	* @param TableStatus $tableStatus
	* @param ?string $set new item options, NULL for no new item
	*/
	function selectLinks(array $tableStatus, ?string $set = ""): void {
		$name = $tableStatus["Name"];
		echo '<p class="links">';
		$links = array("select" => lang(41));
		if (support("table") || support("indexes")) {
			$links["table"] = lang(42);
		}
		$is_view = false;
		if (support("table")) {
			$is_view = is_view($tableStatus);
			if (!$is_view) {
				$links["create"] = lang(43);
			} elseif (support("view")) {
				$links["view"] = lang(44);
			}
		}
		if ($set !== null) {
			$links["edit"] = lang(45);
		}
		foreach ($links as $key => $val) {
			echo " <a href='" . h(ME) . "$key=" . urlencode($name) . ($key == "edit" ? $set : "") . "'" . bold(isset($_GET[$key])) . ">$val</a>";
		}
		echo doc_link(array(JUSH => driver()->tableHelp($name, $is_view)), "?");
		echo "\n";
	}

	/** Get foreign keys for table
	* @return ForeignKey[] same format as foreign_keys()
	*/
	function foreignKeys(string $table): array {
		return foreign_keys($table);
	}

	/** Find backward keys for table
	* @return BackwardKey[]
	*/
	function backwardKeys(string $table, string $tableName): array {
		return array();
	}

	/** Print backward keys for row
	* @param BackwardKey[] $backwardKeys
	* @param string[] $row
	*/
	function backwardKeysPrint(array $backwardKeys, array $row): void {
	}

	/** Query printed in select before execution
	* @param string $query query to be executed
	* @param float $start start time of the query
	*/
	function selectQuery(string $query, float $start, bool $failed = false): string {
		$return = "</p>\n"; // required for IE9 inline edit
		if (!$failed && ($warnings = driver()->warnings())) {
			$id = "warnings";
			$return = ", <a href='#$id'>" . lang(46) . "</a>" . script("qsl('a').onclick = partial(toggle, '$id');", "")
				. "$return<div id='$id' class='hidden'>\n$warnings</div>\n"
			;
		}
		return "<p><code class='jush-" . JUSH . "'>" . h(str_replace("\n", " ", $query)) . "</code> <span class='time'>(" . format_time($start) . ")</span>"
			. (support("sql") ? " <a href='" . h(ME) . "sql=" . urlencode($query) . "'>" . lang(12) . "</a>" : "")
			. $return
		;
	}

	/** Query printed in SQL command before execution
	* @param string $query query to be executed
	* @return string escaped query to be printed
	*/
	function sqlCommandQuery(string $query): string {
		return shorten_utf8(trim($query), 1000);
	}

	/** Print HTML code just before the Execute button in SQL command */
	function sqlPrintAfter(): void {
	}

	/** Description of a row in a table
	* @return string SQL expression, empty string for no description
	*/
	function rowDescription(string $table): string {
		return "";
	}

	/** Get descriptions of selected data
	* @param list<string[]> $rows all data to print
	* @param list<ForeignKey>[] $foreignKeys
	* @return list<string[]>
	*/
	function rowDescriptions(array $rows, array $foreignKeys): array {
		return $rows;
	}

	/** Get a link to use in select table
	* @param string $val raw value of the field
	* @param Field $field
	* @return string|void null to create the default link
	*/
	function selectLink(?string $val, array $field) {
	}

	/** Value printed in select table
	* @param ?string $val HTML-escaped value to print
	* @param ?string $link link to foreign key
	* @param Field $field
	* @param string $original original value before applying editVal() and escaping
	*/
	function selectVal(?string $val, ?string $link, array $field, ?string $original): string {
		$return = ($val === null ? "<i>NULL</i>"
			: (preg_match("~char|binary|boolean~", $field["type"]) && !preg_match("~var~", $field["type"]) ? "<code>$val</code>"
			: (preg_match('~json~', $field["type"]) ? "<code class='jush-js'>$val</code>"
			: $val)
		));
		if (is_blob($field) && !is_utf8($val)) {
			$return = "<i>" . lang(47, strlen($original)) . "</i>";
		}
		return ($link ? "<a href='" . h($link) . "'" . (is_url($link) ? target_blank() : "") . ">$return</a>" : $return);
	}

	/** Value conversion used in select and edit
	* @param Field $field
	*/
	function editVal(?string $val, array $field): ?string {
		return $val;
	}

	/** Get configuration options for AdminerConfig
	* @return string[] key is config description, value is HTML
	*/
	function config(): array {
		return array();
	}

	/** Print table structure in tabular format
	* @param Field[] $fields
	* @param TableStatus $tableStatus
	*/
	function tableStructurePrint(array $fields, ?array $tableStatus = null): void {
		echo "<div class='scrollable'>\n";
		echo "<table class='nowrap odds'>\n";
		echo "<thead><tr><th>" . lang(48) . "<td>" . lang(49) . (support("comment") ? "<td>" . lang(50) : "") . "</thead>\n";
		$structured_types = driver()->structuredTypes();
		foreach ($fields as $field) {
			echo "<tr><th>" . h($field["field"]);
			$type = h($field["full_type"]);
			$collation = h($field["collation"]);
			echo "<td><span title='$collation'>"
				. (in_array($type, (array) $structured_types[lang(6)])
					? "<a href='" . h(ME . 'type=' . urlencode($type)) . "'>$type</a>"
					: $type . ($collation && isset($tableStatus["Collation"]) && $collation != $tableStatus["Collation"] ? " $collation" : ""))
				. "</span>"
			;
			echo ($field["null"] ? " <i>NULL</i>" : "");
			echo ($field["auto_increment"] ? " <i>" . lang(51) . "</i>" : "");
			$default = h($field["default"]);
			echo (isset($field["default"]) ? " <span title='" . lang(52) . "'>[<b>" . ($field["generated"] ? "<code class='jush-" . JUSH . "'>$default</code>" : $default) . "</b>]</span>" : "");
			echo (support("comment") ? "<td>" . h($field["comment"]) : "");
			echo "\n";
		}
		echo "</table>\n";
		echo "</div>\n";
	}

	/** Print list of indexes on table in tabular format
	* @param Index[] $indexes
	* @param TableStatus $tableStatus
	*/
	function tableIndexesPrint(array $indexes, array $tableStatus): void {
		$partial = false;
		foreach ($indexes as $name => $index) {
			$partial |= !!$index["partial"];
		}
		echo "<table>\n";
		$default_algorithm = first(driver()->indexAlgorithms($tableStatus));
		foreach ($indexes as $name => $index) {
			ksort($index["columns"]); // enforce correct columns order
			$print = array();
			foreach ($index["columns"] as $key => $val) {
				$print[] = "<i>" . h($val) . "</i>"
					. ($index["lengths"][$key] ? "(" . $index["lengths"][$key] . ")" : "")
					. ($index["descs"][$key] ? " DESC" : "")
				;
			}

			echo "<tr title='" . h($name) . "'>";
			echo "<th>$index[type]" . ($default_algorithm && $index['algorithm'] != $default_algorithm ? " ($index[algorithm])" : "");
			echo "<td>" . implode(", ", $print);
			if ($partial) {
				echo "<td>" . ($index['partial'] ? "<code class='jush-" . JUSH . "'>WHERE " . h($index['partial']) : "");
			}
			echo "\n";
		}
		echo "</table>\n";
	}

	/** Print columns box in select
	* @param list<string> $select result of selectColumnsProcess()[0]
	* @param string[] $columns selectable columns
	*/
	function selectColumnsPrint(array $select, array $columns): void {
		print_fieldset("select", lang(53), $select);
		$i = 0;
		$select[""] = array();
		foreach ($select as $key => $val) {
			$val = idx($_GET["columns"], $key, array());
			$column = select_input(
				" name='columns[$i][col]'",
				$columns,
				$val["col"],
				($key !== "" ? "selectFieldChange" : "selectAddRow")
			);
			echo "<div>" . (driver()->functions || driver()->grouping ? html_select("columns[$i][fun]", array(-1 => "") + array_filter(array(lang(54) => driver()->functions, lang(55) => driver()->grouping)), $val["fun"])
				. on_help("event.target.value && event.target.value.replace(/ |\$/, '(') + ')'", 1)
				. script("qsl('select').onchange = function () { helpClose();" . ($key !== "" ? "" : " qsl('select, input', this.parentNode).onchange();") . " };", "")
				. "($column)" : $column) . "</div>\n";
			$i++;
		}
		echo "</div></fieldset>\n";
	}

	/** Print search box in select
	* @param list<string> $where result of selectSearchProcess()
	* @param string[] $columns selectable columns
	* @param Index[] $indexes
	*/
	function selectSearchPrint(array $where, array $columns, array $indexes): void {
		print_fieldset("search", lang(56), $where);
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT") {
				echo "<div>(<i>" . implode("</i>, <i>", array_map('Adminer\h', $index["columns"])) . "</i>) AGAINST";
				echo " <input type='search' name='fulltext[$i]' value='" . h(idx($_GET["fulltext"], $i)) . "'>";
				echo script("qsl('input').oninput = selectFieldChange;", "");
				echo checkbox("boolean[$i]", 1, isset($_GET["boolean"][$i]), "BOOL");
				echo "</div>\n";
			}
		}
		$change_next = "this.parentNode.firstChild.onchange();";
		foreach (array_merge((array) $_GET["where"], array(array())) as $i => $val) {
			if (!$val || ("$val[col]$val[val]" != "" && in_array($val["op"], adminer()->operators()))) {
				echo "<div>" . select_input(
					" name='where[$i][col]'",
					$columns,
					$val["col"],
					($val ? "selectFieldChange" : "selectAddRow"),
					"(" . lang(57) . ")"
				);
				echo html_select("where[$i][op]", adminer()->operators(), $val["op"], $change_next);
				echo "<input type='search' name='where[$i][val]' value='" . h($val["val"]) . "'>";
				echo script("mixin(qsl('input'), {oninput: function () { $change_next }, onkeydown: selectSearchKeydown, onsearch: selectSearchSearch});", "");
				echo "</div>\n";
			}
		}
		echo "</div></fieldset>\n";
	}

	/** Print order box in select
	* @param list<string> $order result of selectOrderProcess()
	* @param string[] $columns selectable columns
	* @param Index[] $indexes
	*/
	function selectOrderPrint(array $order, array $columns, array $indexes): void {
		print_fieldset("sort", lang(58), $order);
		$i = 0;
		foreach ((array) $_GET["order"] as $key => $val) {
			if ($val != "") {
				echo "<div>" . select_input(" name='order[$i]'", $columns, $val, "selectFieldChange");
				echo checkbox("desc[$i]", 1, isset($_GET["desc"][$key]), lang(59)) . "</div>\n";
				$i++;
			}
		}
		echo "<div>" . select_input(" name='order[$i]'", $columns, "", "selectAddRow");
		echo checkbox("desc[$i]", 1, false, lang(59)) . "</div>\n";
		echo "</div></fieldset>\n";
	}

	/** Print limit box in select */
	function selectLimitPrint(int $limit): void {
		echo "<fieldset><legend>" . lang(60) . "</legend><div>"; // <div> for easy styling
		echo "<input type='number' name='limit' class='size' value='" . intval($limit) . "'>";
		echo script("qsl('input').oninput = selectFieldChange;", "");
		echo "</div></fieldset>\n";
	}

	/** Print text length box in select
	* @param numeric-string $text_length result of selectLengthProcess()
	*/
	function selectLengthPrint(string $text_length): void {
		if ($text_length !== null) {
			echo "<fieldset><legend>" . lang(61) . "</legend><div>";
			echo "<input type='number' name='text_length' class='size' value='" . h($text_length) . "'>";
			echo "</div></fieldset>\n";
		}
	}

	/** Print action box in select
	* @param Index[] $indexes
	*/
	function selectActionPrint(array $indexes): void {
		echo "<fieldset><legend>" . lang(62) . "</legend><div>";
		echo "<input type='submit' value='" . lang(53) . "'>";
		echo " <span id='noindex' title='" . lang(63) . "'></span>";
		echo "<script" . nonce() . ">\n";
		echo "const indexColumns = ";
		$columns = array();
		foreach ($indexes as $index) {
			$current_key = reset($index["columns"]);
			if ($index["type"] != "FULLTEXT" && $current_key) {
				$columns[$current_key] = 1;
			}
		}
		$columns[""] = 1;
		foreach ($columns as $key => $val) {
			json_row($key);
		}
		echo ";\n";
		echo "selectFieldChange.call(qs('#form')['select']);\n";
		echo "</script>\n";
		echo "</div></fieldset>\n";
	}

	/** Print command box in select
	* @return bool whether to print default commands
	*/
	function selectCommandPrint(): bool {
		return !information_schema(DB);
	}

	/** Print import box in select
	* @return bool whether to print default import
	*/
	function selectImportPrint(): bool {
		return !information_schema(DB);
	}

	/** Print extra text in the end of a select form
	* @param string[] $emailFields fields holding e-mails
	* @param string[] $columns selectable columns
	*/
	function selectEmailPrint(array $emailFields, array $columns): void {
	}

	/** Process columns box in select
	* @param string[] $columns selectable columns
	* @param Index[] $indexes
	* @return list<list<string>> [[select_expressions], [group_expressions]]
	*/
	function selectColumnsProcess(array $columns, array $indexes): array {
		$select = array(); // select expressions, empty for *
		$group = array(); // expressions without aggregation - will be used for GROUP BY if an aggregation function is used
		foreach ((array) $_GET["columns"] as $key => $val) {
			if ($val["fun"] == "count" || ($val["col"] != "" && (!$val["fun"] || in_array($val["fun"], driver()->functions) || in_array($val["fun"], driver()->grouping)))) {
				$select[$key] = apply_sql_function($val["fun"], ($val["col"] != "" ? idf_escape($val["col"]) : "*"));
				if (!in_array($val["fun"], driver()->grouping)) {
					$group[] = $select[$key];
				}
			}
		}
		return array($select, $group);
	}

	/** Process search box in select
	* @param Field[] $fields
	* @param Index[] $indexes
	* @return list<string> expressions to join by AND
	*/
	function selectSearchProcess(array $fields, array $indexes): array {
		$return = array();
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT" && idx($_GET["fulltext"], $i) != "") {
				$return[] = "MATCH (" . implode(", ", array_map('Adminer\idf_escape', $index["columns"])) . ") AGAINST (" . q($_GET["fulltext"][$i]) . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
			}
		}
		foreach ((array) $_GET["where"] as $key => $val) {
			$col = $val["col"];
			if ("$col$val[val]" != "" && in_array($val["op"], adminer()->operators())) {
				$conds = array();
				foreach (($col != "" ? array($col => $fields[$col]) : $fields) as $name => $field) {
					$prefix = "";
					$cond = " $val[op]";
					if (preg_match('~IN$~', $val["op"])) {
						$in = process_length($val["val"]);
						$cond .= " " . ($in != "" ? $in : "(NULL)");
					} elseif ($val["op"] == "SQL") {
						$cond = " $val[val]"; // SQL injection
					} elseif (preg_match('~^(I?LIKE) %%$~', $val["op"], $match)) {
						$cond = " $match[1] " . adminer()->processInput($field, "%$val[val]%");
					} elseif ($val["op"] == "FIND_IN_SET") {
						$prefix = "$val[op](" . q($val["val"]) . ", ";
						$cond = ")";
					} elseif (!preg_match('~NULL$~', $val["op"])) {
						$cond .= " " . adminer()->processInput($field, $val["val"]);
					}
					if ($col != "" || ( // find anywhere
						isset($field["privileges"]["where"])
						&& (preg_match('~^[-\d.' . (preg_match('~IN$~', $val["op"]) ? ',' : '') . ']+$~', $val["val"]) || !preg_match('~' . number_type() . '|bit~', $field["type"]))
						&& (!preg_match("~[\x80-\xFF]~", $val["val"]) || preg_match('~char|text|enum|set~', $field["type"]))
						&& (!preg_match('~date|timestamp~', $field["type"]) || preg_match('~^\d+-\d+-\d+~', $val["val"]))
					)) {
						$conds[] = $prefix . driver()->convertSearch(idf_escape($name), $val, $field) . $cond;
					}
				}
				$return[] =
					(count($conds) == 1 ? $conds[0] :
					($conds ? "(" . implode(" OR ", $conds) . ")" :
					"1 = 0"
				));
			}
		}
		return $return;
	}

	/** Process order box in select
	* @param Field[] $fields
	* @param Index[] $indexes
	* @return list<string> expressions to join by comma
	*/
	function selectOrderProcess(array $fields, array $indexes): array {
		$return = array();
		foreach ((array) $_GET["order"] as $key => $val) {
			if ($val != "") {
				$return[] = (preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~', $val) ? $val : idf_escape($val)) //! MS SQL uses []
					. (isset($_GET["desc"][$key]) ? " DESC" : "")
				;
			}
		}
		return $return;
	}

	/** Process limit box in select */
	function selectLimitProcess(): int {
		return (isset($_GET["limit"]) ? intval($_GET["limit"]) : 50);
	}

	/** Process length box in select
	* @return numeric-string number of characters to shorten texts, will be escaped, empty string means unlimited
	*/
	function selectLengthProcess(): string {
		return (isset($_GET["text_length"]) ? "$_GET[text_length]" : "100");
	}

	/** Process extras in select form
	* @param string[] $where AND conditions
	* @param list<ForeignKey>[] $foreignKeys
	* @return bool true if processed, false to process other parts of form
	*/
	function selectEmailProcess(array $where, array $foreignKeys): bool {
		return false;
	}

	/** Build SQL query used in select
	* @param list<string> $select result of selectColumnsProcess()[0]
	* @param list<string> $where result of selectSearchProcess()
	* @param list<string> $group result of selectColumnsProcess()[1]
	* @param list<string> $order result of selectOrderProcess()
	* @param int $limit result of selectLimitProcess()
	* @param int $page index of page starting at zero
	* @return string empty string to use default query
	*/
	function selectQueryBuild(array $select, array $where, array $group, array $order, int $limit, ?int $page): string {
		return "";
	}

	/** Query printed after execution in the message
	* @param string $query executed query
	* @param string $time elapsed time
	*/
	function messageQuery(string $query, string $time, bool $failed = false): string {
		restart_session();
		$history = &get_session("queries");
		if (!idx($history, $_GET["db"])) {
			$history[$_GET["db"]] = array();
		}
		if (strlen($query) > 1e6) {
			$query = preg_replace('~[\x80-\xFF]+$~', '', substr($query, 0, 1e6)) . "\nâ€¦"; // [\x80-\xFF] - valid UTF-8, \n - can end by one-line comment
		}
		$history[$_GET["db"]][] = array($query, time(), $time); // not DB - $_GET["db"] is changed in database.inc.php //! respect $_GET["ns"]
		$sql_id = "sql-" . count($history[$_GET["db"]]);
		$return = "<a href='#$sql_id' class='toggle'>" . lang(64) . "</a> <a href='' class='jsonly copy'>ğŸ—</a>\n";
		if (!$failed && ($warnings = driver()->warnings())) {
			$id = "warnings-" . count($history[$_GET["db"]]);
			$return = "<a href='#$id' class='toggle'>" . lang(46) . "</a>, $return<div id='$id' class='hidden'>\n$warnings</div>\n";
		}
		return " <span class='time'>" . @date("H:i:s") . "</span>" // @ - time zone may be not set
			. " $return<div id='$sql_id' class='hidden'><pre><code class='jush-" . JUSH . "'>" . shorten_utf8($query, 1000) . "</code></pre>"
			. ($time ? " <span class='time'>($time)</span>" : '')
			. (support("sql") ? '<p><a href="' . h(str_replace("db=" . urlencode(DB), "db=" . urlencode($_GET["db"]), ME) . 'sql=&history=' . (count($history[$_GET["db"]]) - 1)) . '">' . lang(12) . '</a>' : '')
			. '</div>'
		;
	}

	/** Print before edit form
	* @param Field[] $fields
	* @param mixed $row
	*/
	function editRowPrint(string $table, array $fields, $row, ?bool $update): void {
	}

	/** Functions displayed in edit form
	* @param Field|array{null:bool} $field
	* @return string[]
	*/
	function editFunctions(array $field): array {
		$return = ($field["null"] ? "NULL/" : "");
		$update = isset($_GET["select"]) || where($_GET);
		foreach (array(driver()->insertFunctions, driver()->editFunctions) as $key => $functions) {
			if (!$key || (!isset($_GET["call"]) && $update)) { // relative functions
				foreach ($functions as $pattern => $val) {
					if (!$pattern || preg_match("~$pattern~", $field["type"])) {
						$return .= "/$val";
					}
				}
			}
			if ($key && $functions && !preg_match('~set|bool~', $field["type"]) && !is_blob($field)) {
				$return .= "/SQL";
			}
		}
		if ($field["auto_increment"] && !$update) {
			$return = lang(51);
		}
		return explode("/", $return);
	}

	/** Get options to display edit field
	* @param ?string $table null in call.inc.php
	* @param Field $field
	* @param string $attrs attributes to use inside the tag
	* @param string|string[]|false|null $value false means original value
	* @return string custom input field or empty string for default
	*/
	function editInput(?string $table, array $field, string $attrs, $value): string {
		if ($field["type"] == "enum") {
			return (isset($_GET["select"]) ? "<label><input type='radio'$attrs value='orig' checked><i>" . lang(10) . "</i></label> " : "")
				. enum_input("radio", $attrs, $field, $value, "NULL")
			;
		}
		return "";
	}

	/** Get hint for edit field
	* @param ?string $table null in call.inc.php
	* @param Field $field
	*/
	function editHint(?string $table, array $field, ?string $value): string {
		return "";
	}

	/** Process sent input
	* @param Field $field
	* @return string expression to use in a query
	*/
	function processInput(array $field, string $value, ?string $function = ""): string {
		if ($function == "SQL") {
			return $value; // SQL injection
		}
		$name = $field["field"];
		$return = q($value);
		if (preg_match('~^(now|getdate|uuid)$~', $function)) {
			$return = "$function()";
		} elseif (preg_match('~^current_(date|timestamp)$~', $function)) {
			$return = $function;
		} elseif (preg_match('~^([+-]|\|\|)$~', $function)) {
			$return = idf_escape($name) . " $function $return";
		} elseif (preg_match('~^[+-] interval$~', $function)) {
			$return = idf_escape($name) . " $function " . (preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i", $value) && JUSH != "pgsql" ? $value : $return);
		} elseif (preg_match('~^(addtime|subtime|concat)$~', $function)) {
			$return = "$function(" . idf_escape($name) . ", $return)";
		} elseif (preg_match('~^(md5|sha1|password|encrypt)$~', $function)) {
			$return = "$function($return)";
		}
		return unconvert_field($field, $return);
	}

	/** Return export output options
	* @return string[]
	*/
	function dumpOutput(): array {
		$return = array('text' => lang(65), 'file' => lang(66));
		if (function_exists('gzencode')) {
			$return['gz'] = 'gzip';
		}
		return $return;
	}

	/** Return export format options
	* @return string[] empty to disable export
	*/
	function dumpFormat(): array {
		return (support("dump") ? array('sql' => 'SQL') : array()) + array('csv' => 'CSV,', 'csv;' => 'CSV;', 'tsv' => 'TSV');
	}

	/** Export database structure
	* @return void prints data
	*/
	function dumpDatabase(string $db): void {
	}

	/** Export table structure
	* @param int $is_view 0 table, 1 view, 2 temporary view table
	* @return void prints data
	*/
	function dumpTable(string $table, string $style, int $is_view = 0): void {
		if ($_POST["format"] != "sql") {
			echo "\xef\xbb\xbf"; // UTF-8 byte order mark
			if ($style) {
				dump_csv(array_keys(fields($table)));
			}
		} else {
			if ($is_view == 2) {
				$fields = array();
				foreach (fields($table) as $name => $field) {
					$fields[] = idf_escape($name) . " $field[full_type]";
				}
				$create = "CREATE TABLE " . table($table) . " (" . implode(", ", $fields) . ")";
			} else {
				$create = create_sql($table, $_POST["auto_increment"], $style);
			}
			set_utf8mb4($create);
			if ($style && $create) {
				if ($style == "DROP+CREATE" || $is_view == 1) {
					echo "DROP " . ($is_view == 2 ? "VIEW" : "TABLE") . " IF EXISTS " . table($table) . ";\n";
				}
				if ($is_view == 1) {
					$create = remove_definer($create);
				}
				echo "$create;\n\n";
			}
		}
	}

	/** Export table data
	* @return void prints data
	*/
	function dumpData(string $table, string $style, string $query): void {
		if ($style) {
			$max_packet = (JUSH == "sqlite" ? 0 : 1048576); // default, minimum is 1024
			$fields = array();
			$identity_insert = false;
			if ($_POST["format"] == "sql") {
				if ($style == "TRUNCATE+INSERT") {
					echo truncate_sql($table) . ";\n";
				}
				$fields = fields($table);
				if (JUSH == "mssql") {
					foreach ($fields as $field) {
						if ($field["auto_increment"]) {
							echo "SET IDENTITY_INSERT " . table($table) . " ON;\n";
							$identity_insert = true;
							break;
						}
					}
				}
			}
			$result = connection()->query($query, 1); // 1 - MYSQLI_USE_RESULT
			if ($result) {
				$insert = "";
				$buffer = "";
				$keys = array();
				$generated = array();
				$suffix = "";
				$fetch_function = ($table != '' ? 'fetch_assoc' : 'fetch_row');
				$count = 0;
				while ($row = $result->$fetch_function()) {
					if (!$keys) {
						$values = array();
						foreach ($row as $val) {
							$field = $result->fetch_field();
							if (idx($fields[$field->name], 'generated')) {
								$generated[$field->name] = true;
								continue;
							}
							$keys[] = $field->name;
							$key = idf_escape($field->name);
							$values[] = "$key = VALUES($key)";
						}
						$suffix = ($style == "INSERT+UPDATE" ? "\nON DUPLICATE KEY UPDATE " . implode(", ", $values) : "") . ";\n";
					}
					if ($_POST["format"] != "sql") {
						if ($style == "table") {
							dump_csv($keys);
							$style = "INSERT";
						}
						dump_csv($row);
					} else {
						if (!$insert) {
							$insert = "INSERT INTO " . table($table) . " (" . implode(", ", array_map('Adminer\idf_escape', $keys)) . ") VALUES";
						}
						foreach ($row as $key => $val) {
							if ($generated[$key]) {
								unset($row[$key]);
								continue;
							}
							$field = $fields[$key];
							$row[$key] = ($val !== null
								? unconvert_field($field, preg_match(number_type(), $field["type"]) && !preg_match('~\[~', $field["full_type"]) && is_numeric($val) ? $val : q(($val === false ? 0 : $val)))
								: "NULL"
							);
						}
						$s = ($max_packet ? "\n" : " ") . "(" . implode(",\t", $row) . ")";
						if (!$buffer) {
							$buffer = $insert . $s;
						} elseif (JUSH == 'mssql'
							? $count % 1000 != 0 // https://learn.microsoft.com/en-us/sql/t-sql/queries/table-value-constructor-transact-sql#limitations-and-restrictions
							: strlen($buffer) + 4 + strlen($s) + strlen($suffix) < $max_packet // 4 - length specification
						) {
							$buffer .= ",$s";
						} else {
							echo $buffer . $suffix;
							$buffer = $insert . $s;
						}
					}
					$count++;
				}
				if ($buffer) {
					echo $buffer . $suffix;
				}
			} elseif ($_POST["format"] == "sql") {
				echo "-- " . str_replace("\n", " ", connection()->error) . "\n";
			}
			if ($identity_insert) {
				echo "SET IDENTITY_INSERT " . table($table) . " OFF;\n";
			}
		}
	}

	/** Set export filename
	* @return string filename without extension
	*/
	function dumpFilename(string $identifier): string {
		return friendly_url($identifier != "" ? $identifier : (SERVER ?: "localhost"));
	}

	/** Send headers for export
	* @return string extension
	*/
	function dumpHeaders(string $identifier, bool $multi_table = false): string {
		$output = $_POST["output"];
		$ext = (preg_match('~sql~', $_POST["format"]) ? "sql" : ($multi_table ? "tar" : "csv")); // multiple CSV packed to TAR
		header("Content-Type: " .
			($output == "gz" ? "application/x-gzip" :
			($ext == "tar" ? "application/x-tar" :
			($ext == "sql" || $output != "file" ? "text/plain" : "text/csv") . "; charset=utf-8"
		)));
		if ($output == "gz") {
			ob_start(function ($string) {
				// ob_start() callback receives an optional parameter $phase but gzencode() accepts optional parameter $level
				return gzencode($string);
			}, 1e6);
		}
		return $ext;
	}

	/** Print text after export
	* @return void prints data
	*/
	function dumpFooter(): void {
		if ($_POST["format"] == "sql") {
			echo "-- " . gmdate("Y-m-d H:i:s e") . "\n";
		}
	}

	/** Set the path of the file for webserver load
	* @return string path of the sql dump file
	*/
	function importServerPath(): string {
		return "adminer.sql";
	}

	/** Print homepage
	* @return bool whether to print default homepage
	*/
	function homepage(): bool {
		echo '<p class="links">' . ($_GET["ns"] == "" && support("database") ? '<a href="' . h(ME) . 'database=">' . lang(67) . "</a>\n" : "");
		echo (support("scheme") ? "<a href='" . h(ME) . "scheme='>" . ($_GET["ns"] != "" ? lang(68) : lang(69)) . "</a>\n" : "");
		echo ($_GET["ns"] !== "" ? '<a href="' . h(ME) . 'schema=">' . lang(70) . "</a>\n" : "");
		echo (support("privileges") ? "<a href='" . h(ME) . "privileges='>" . lang(71) . "</a>\n" : "");
		if ($_GET["ns"] !== "") {
			echo (support("routine") ? "<a href='#routines'>" . lang(72) . "</a>\n" : "");
			echo (support("sequence") ? "<a href='#sequences'>" . lang(73) . "</a>\n" : "");
			echo (support("type") ? "<a href='#user-types'>" . lang(6) . "</a>\n" : "");
			echo (support("event") ? "<a href='#events'>" . lang(74) . "</a>\n" : "");
		}
		return true;
	}

	/** Print navigation after Adminer title
	* @param string $missing can be "auth" if there is no database connection, "db" if there is no database selected, "ns" with invalid schema
	*/
	function navigation(string $missing): void {
		echo "<h1>" . adminer()->name() . " <span class='version'>" . VERSION;
		$new_version = $_COOKIE["adminer_version"];
		echo " <a href='https://www.adminer.org/#download'" . target_blank() . " id='version'>" . (version_compare(VERSION, $new_version) < 0 ? h($new_version) : "") . "</a>";
		echo "</span></h1>\n";
		// this is matched by compile.php
		switch_lang();
		if ($missing == "auth") {
			$output = "";
			foreach ((array) $_SESSION["pwds"] as $vendor => $servers) {
				foreach ($servers as $server => $usernames) {
					$name = h(get_setting("vendor-$vendor-$server") ?: get_driver($vendor));
					foreach ($usernames as $username => $password) {
						if ($password !== null) {
							$dbs = $_SESSION["db"][$vendor][$server][$username];
							foreach (($dbs ? array_keys($dbs) : array("")) as $db) {
								$output .= "<li><a href='" . h(auth_url($vendor, $server, $username, $db)) . "'>($name) " . h("$username@" . ($server != "" ? adminer()->serverName($server) : "") . ($db != "" ? " - $db" : "")) . "</a>\n";
							}
						}
					}
				}
			}
			if ($output) {
				echo "<ul id='logins'>\n$output</ul>\n" . script("mixin(qs('#logins'), {onmouseover: menuOver, onmouseout: menuOut});");
			}
		} else {
			$tables = array();
			if ($_GET["ns"] !== "" && !$missing && DB != "") {
				connection()->select_db(DB);
				$tables = table_status('', true);
			}
			adminer()->syntaxHighlighting($tables);
			adminer()->databasesPrint($missing);
			$actions = array();
			if (DB == "" || !$missing) {
				if (support("sql")) {
					$actions[] = "<a href='" . h(ME) . "sql='" . bold(isset($_GET["sql"]) && !isset($_GET["import"])) . ">" . lang(64) . "</a>";
					$actions[] = "<a href='" . h(ME) . "import='" . bold(isset($_GET["import"])) . ">" . lang(75) . "</a>";
				}
				$actions[] = "<a href='" . h(ME) . "dump=" . urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]) . "' id='dump'" . bold(isset($_GET["dump"])) . ">" . lang(76) . "</a>";
			}
			$in_db = $_GET["ns"] !== "" && !$missing && DB != "";
			if ($in_db) {
				$actions[] = '<a href="' . h(ME) . 'create="' . bold($_GET["create"] === "") . ">" . lang(77) . "</a>";
			}
			echo ($actions ? "<p class='links'>\n" . implode("\n", $actions) . "\n" : "");
			if ($in_db) {
				if ($tables) {
					adminer()->tablesPrint($tables);
				} else {
					echo "<p class='message'>" . lang(11) . "</p>\n";
				}
			}
		}
	}

	/** Set up syntax highlight for code and <textarea>
	* @param TableStatus[] $tables
	*/
	function syntaxHighlighting(array $tables): void {
		// this is matched by compile.php
		echo script_src(preg_replace("~\\?.*~", "", ME) . "?file=jush.js&version=5.4.1", true);
		if (support("sql")) {
			echo "<script" . nonce() . ">\n";
			if ($tables) {
				$links = array();
				foreach ($tables as $table => $type) {
					$links[] = preg_quote($table, '/');
				}
				echo "var jushLinks = { " . JUSH . ":";
				json_row(js_escape(ME) . (support("table") ? "table" : "select") . '=$&', '/\b(' . implode('|', $links) . ')\b/g', false);
				if (support('routine')) {
					foreach (routines() as $row) {
						json_row(js_escape(ME) . 'function=' . urlencode($row["SPECIFIC_NAME"]) . '&name=$&', '/\b' . preg_quote($row["ROUTINE_NAME"], '/') . '(?=["`]?\()/g', false);
					}
				}
				json_row('');
				echo "};\n";
				foreach (array("bac", "bra", "sqlite_quo", "mssql_bra") as $val) {
					echo "jushLinks.$val = jushLinks." . JUSH . ";\n";
				}
				if (isset($_GET["sql"]) || isset($_GET["trigger"]) || isset($_GET["check"])) {
					$tablesColumns = array_fill_keys(array_keys($tables), array());
					foreach (driver()->allFields() as $table => $fields) {
						foreach ($fields as $field) {
							$tablesColumns[$table][] = $field["field"];
						}
					}
					echo "addEventListener('DOMContentLoaded', () => { autocompleter = jush.autocompleteSql('" . idf_escape("") . "', " . json_encode($tablesColumns) . "); });\n";
				}
			}
			echo "</script>\n";
		}
		echo script("syntaxHighlighting('" . preg_replace('~^(\d\.?\d).*~s', '\1', connection()->server_info) . "', '" . connection()->flavor . "');");
	}

	/** Print databases list in menu */
	function databasesPrint(string $missing): void {
		$databases = adminer()->databases();
		if (DB && $databases && !in_array(DB, $databases)) {
			array_unshift($databases, DB);
		}
		echo "<form action=''>\n<p id='dbs'>\n";
		hidden_fields_get();
		$db_events = script("mixin(qsl('select'), {onmousedown: dbMouseDown, onchange: dbChange});");
		echo "<label title='" . lang(37) . "'>" . lang(78) . ": " . ($databases
			? html_select("db", array("" => "") + $databases, DB) . $db_events
			: "<input name='db' value='" . h(DB) . "' autocapitalize='off' size='19'>\n"
		) . "</label>";
		echo "<input type='submit' value='" . lang(22) . "'" . ($databases ? " class='hidden'" : "") . ">\n";
		if (support("scheme")) {
			if ($missing != "db" && DB != "" && connection()->select_db(DB)) {
				echo "<br><label>" . lang(79) . ": " . html_select("ns", array("" => "") + adminer()->schemas(), $_GET["ns"]) . "$db_events</label>";
				if ($_GET["ns"] != "") {
					set_schema($_GET["ns"]);
				}
			}
		}
		foreach (array("import", "sql", "schema", "dump", "privileges") as $val) {
			if (isset($_GET[$val])) {
				echo input_hidden($val);
				break;
			}
		}
		echo "</p></form>\n";
	}

	/** Print table list in menu
	* @param TableStatus[] $tables
	*/
	function tablesPrint(array $tables): void {
		echo "<ul id='tables'>" . script("mixin(qs('#tables'), {onmouseover: menuOver, onmouseout: menuOut});");
		foreach ($tables as $table => $status) {
			$table = "$table"; // do not highlight "0" as active everywhere
			$name = adminer()->tableName($status);
			if ($name != "" && !$status["partition"]) {
				echo '<li><a href="' . h(ME) . 'select=' . urlencode($table) . '"'
					. bold($_GET["select"] == $table || $_GET["edit"] == $table, "select")
					. " title='" . lang(41) . "'>" . lang(80) . "</a> "
				;
				echo (support("table") || support("indexes")
					? '<a href="' . h(ME) . 'table=' . urlencode($table) . '"'
						. bold(in_array($table, array($_GET["table"], $_GET["create"], $_GET["indexes"], $_GET["foreign"], $_GET["trigger"], $_GET["check"], $_GET["view"])), (is_view($status) ? "view" : "structure"))
						. " title='" . lang(42) . "'>$name</a>"
					: "<span>$name</span>"
				) . "\n";
			}
		}
		echo "</ul>\n";
	}

	/** Get process list
	* @return list<string[]> [$row]
	*/
	function processList(): array {
		return process_list();
	}

	/** Kill a process
	* @param numeric-string $id
	* @return Result|bool
	*/
	function killProcess(string $id) {
		return kill_process($id);
	}
}

?>
<?php
class Plugins {
	/** @var true[] */ private static array $append = array('dumpFormat' => true, 'dumpOutput' => true, 'editRowPrint' => true, 'editFunctions' => true, 'config' => true); // these hooks expect the value to be appended to the result

	/** @var list<object> @visibility protected(set) */ public array $plugins;
	/** @visibility protected(set) */ public string $error = ''; // HTML
	/** @var list<object>[] */ private array $hooks = array();

	/** Register plugins
	* @param ?list<object> $plugins object instances or null to autoload plugins from adminer-plugins/
	*/
	function __construct(?array $plugins) {
		if ($plugins === null) {
			$plugins = array();
			$basename = "adminer-plugins";
			if (is_dir($basename)) {
				foreach (glob("$basename/*.php") as $filename) {
					$include = include_once "./$filename";
				}
			}
			$help = " href='https://www.adminer.org/plugins/#use'" . target_blank();
			if (file_exists("$basename.php")) {
				$include = include_once "./$basename.php"; // example: return array(new AdminerLoginOtp($secret))
				if (is_array($include)) {
					foreach ($include as $plugin) {
						$plugins[get_class($plugin)] = $plugin;
					}
				} else {
					$this->error .= lang(81, "<b>$basename.php</b>", $help) . "<br>";
				}
			}
			foreach (get_declared_classes() as $class) {
				if (!$plugins[$class] && preg_match('~^Adminer\w~i', $class)) {
					// we need to use reflection because PHP 7.1 throws ArgumentCountError for missing arguments but older versions issue a warning
					$reflection = new \ReflectionClass($class);
					$constructor = $reflection->getConstructor();
					if ($constructor && $constructor->getNumberOfRequiredParameters()) {
						$this->error .= lang(82, $help, "<b>$class</b>", "<b>$basename.php</b>") . "<br>";
					} else {
						$plugins[$class] = new $class;
					}
				}
			}
		}
		$this->plugins = $plugins;

		$adminer = new Adminer;
		$plugins[] = $adminer;
		$reflection = new \ReflectionObject($adminer);
		foreach ($reflection->getMethods() as $method) {
			foreach ($plugins as $plugin) {
				$name = $method->getName();
				if (method_exists($plugin, $name)) {
					$this->hooks[$name][] = $plugin;
				}
			}
		}
	}

	/**
	* @param literal-string $name
	* @param mixed[] $params
	* @return mixed
	*/
	function __call(string $name, array $params) {
		$args = array();
		foreach ($params as $key => $val) {
			// some plugins accept params by reference - we don't need to propagate it outside, just to the other plugins
			$args[] = &$params[$key];
		}
		$return = null;
		foreach ($this->hooks[$name] as $plugin) {
			$value = call_user_func_array(array($plugin, $name), $args);
			if ($value !== null) {
				if (!self::$append[$name]) { // non-null value from non-appending method short-circuits the other plugins
					return $value;
				}
				$return = $value + (array) $return;
			}
		}
		return $return;
	}
}

?>
<?php
// the overridable methods don't use return type declarations so that plugins can be compatible with PHP 5
abstract class Plugin {
	/** @var array<literal-string, string|list<string>>[] */ protected $translations = array(); // key is language code

	/** Get plain text plugin description; empty string means to use the first line of class doc-comment
	* @return string
	*/
	function description() {
		return $this->lang('');
	}

	/** Get URL of plugin screenshot
	* @return string
	*/
	function screenshot() {
		return "";
	}

	/** Translate a string from $this->translations; Adminer\lang() doesn't work for single language versions
	* @param literal-string $idf
	* @param float|string $number
	*/
	protected function lang(string $idf, $number = null): string {
		$args = func_get_args();
		$args[0] = idx($this->translations[LANG], $idf) ?: $idf;
		return call_user_func_array('Adminer\lang_format', $args);
	}
}


Adminer::$instance =
	(function_exists('adminer_object') ? adminer_object() :
	(is_dir("adminer-plugins") || file_exists("adminer-plugins.php") ? new Plugins(null) :
	new Adminer
));

// this is matched by compile.php
?>
<?php
SqlDriver::$drivers = array("server" => "MySQL / MariaDB") + SqlDriver::$drivers;

if (!defined('Adminer\DRIVER')) {
	define('Adminer\DRIVER', "server"); // server - backwards compatibility

	// MySQLi supports everything, MySQL doesn't support multiple result sets, PDO_MySQL doesn't support orgtable
	if (extension_loaded("mysqli") && $_GET["ext"] != "pdo") {
		class Db extends \MySQLi {
			/** @var Db */ static $instance;
			public $extension = "MySQLi", $flavor = '';

			function __construct() {
				parent::init();
			}

			function attach(string $server, string $username, string $password): string {
				mysqli_report(MYSQLI_REPORT_OFF); // stays between requests, not required since PHP 5.3.4
				list($host, $port) = host_port($server);
				$ssl = adminer()->connectSsl();
				if ($ssl) {
					$this->ssl_set($ssl['key'], $ssl['cert'], $ssl['ca'], '', '');
				}
				$return = @$this->real_connect(
					($server != "" ? $host : ini_get("mysqli.default_host")),
					($server . $username != "" ? $username : ini_get("mysqli.default_user")),
					($server . $username . $password != "" ? $password : ini_get("mysqli.default_pw")),
					null,
					(is_numeric($port) ? intval($port) : ini_get("mysqli.default_port")),
					(is_numeric($port) ? null : $port),
					($ssl ? ($ssl['verify'] !== false ? 2048 : 64) : 0) // 2048 - MYSQLI_CLIENT_SSL, 64 - MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT (not available before PHP 5.6.16)
				);
				$this->options(MYSQLI_OPT_LOCAL_INFILE, 0);
				return ($return ? '' : $this->error);
			}

			function set_charset($charset) {
				if (parent::set_charset($charset)) {
					return true;
				}
				// the client library may not support utf8mb4
				parent::set_charset('utf8');
				return $this->query("SET NAMES $charset");
			}

			function next_result() {
				return self::more_results() && parent::next_result(); // triggers E_STRICT on PHP < 7.4 otherwise
			}

			function quote(string $string): string {
				return "'" . $this->escape_string($string) . "'";
			}
		}

	} elseif (extension_loaded("mysql") && !((ini_bool("sql.safe_mode") || ini_bool("mysql.allow_local_infile")) && extension_loaded("pdo_mysql"))) {
		class Db extends SqlDb {
			/** @var resource */ private $link;

			function attach(string $server, string $username, string $password): string {
				if (ini_bool("mysql.allow_local_infile")) {
					return lang(83, "'mysql.allow_local_infile'", "MySQLi", "PDO_MySQL");
				}
				$this->link = @mysql_connect(
					($server != "" ? $server : ini_get("mysql.default_host")),
					($server . $username != "" ? $username : ini_get("mysql.default_user")),
					($server . $username . $password != "" ? $password : ini_get("mysql.default_password")),
					true,
					131072 // CLIENT_MULTI_RESULTS for CALL
				);
				if (!$this->link) {
					return mysql_error();
				}
				$this->server_info = mysql_get_server_info($this->link);
				return '';
			}

			/** Set the client character set */
			function set_charset(string $charset): bool {
				if (function_exists('mysql_set_charset')) {
					if (mysql_set_charset($charset, $this->link)) {
						return true;
					}
					// the client library may not support utf8mb4
					mysql_set_charset('utf8', $this->link);
				}
				return $this->query("SET NAMES $charset");
			}

			function quote(string $string): string {
				return "'" . mysql_real_escape_string($string, $this->link) . "'";
			}

			function select_db(string $database) {
				return mysql_select_db($database, $this->link);
			}

			function query(string $query, bool $unbuffered = false) {
				$result = @($unbuffered ? mysql_unbuffered_query($query, $this->link) : mysql_query($query, $this->link)); // @ - mute mysql.trace_mode
				$this->error = "";
				if (!$result) {
					$this->errno = mysql_errno($this->link);
					$this->error = mysql_error($this->link);
					return false;
				}
				if ($result === true) {
					$this->affected_rows = mysql_affected_rows($this->link);
					$this->info = mysql_info($this->link);
					return true;
				}
				return new Result($result);
			}
		}

		class Result {
			public $num_rows; // number of rows in the result
			/** @var resource */ private $result;
			private int $offset = 0;

			/** @param resource $result */
			function __construct($result) {
				$this->result = $result;
				$this->num_rows = mysql_num_rows($result);
			}

			/** Fetch next row as associative array
			* @return array<?string>|false
			*/
			function fetch_assoc() {
				return mysql_fetch_assoc($this->result);
			}

			/** Fetch next row as numbered array
			* @return list<?string>|false
			*/
			function fetch_row() {
				return mysql_fetch_row($this->result);
			}

			/** Fetch next field
			* @return \stdClass properties: name, type (0 number, 15 varchar, 254 char), charsetnr (63 binary); optionally: table, orgtable, orgname
			*/
			function fetch_field(): \stdClass {
				$return = mysql_fetch_field($this->result, $this->offset++); // offset required under certain conditions
				$return->orgtable = $return->table;
				$return->charsetnr = ($return->blob ? 63 : 0);
				return $return;
			}

			/** Free result set */
			function __destruct() {
				mysql_free_result($this->result);
			}
		}

	} elseif (extension_loaded("pdo_mysql")) {
		class Db extends PdoDb {
			public $extension = "PDO_MySQL";

			function attach(string $server, string $username, string $password): string {
				$options = array(\PDO::MYSQL_ATTR_LOCAL_INFILE => false);
				$ssl = adminer()->connectSsl();
				if ($ssl) {
					if ($ssl['key']) {
						$options[\PDO::MYSQL_ATTR_SSL_KEY] = $ssl['key'];
					}
					if ($ssl['cert']) {
						$options[\PDO::MYSQL_ATTR_SSL_CERT] = $ssl['cert'];
					}
					if ($ssl['ca']) {
						$options[\PDO::MYSQL_ATTR_SSL_CA] = $ssl['ca'];
					}
					if (isset($ssl['verify'])) {
						$options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $ssl['verify'];
					}
				}
				list($host, $port) = host_port($server);
				return $this->dsn(
					"mysql:charset=utf8;host=$host" . ($port ? (is_numeric($port) ? ";port=" : ";unix_socket=") . $port : ""),
					$username,
					$password,
					$options
				);
			}

			function set_charset($charset) {
				return $this->query("SET NAMES $charset"); // charset in DSN is ignored before PHP 5.3.6
			}

			function select_db(string $database) {
				// database selection is separated from the connection so dbname in DSN can't be used
				return $this->query("USE " . idf_escape($database));
			}

			function query(string $query, bool $unbuffered = false) {
				$this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, !$unbuffered);
				return parent::query($query, $unbuffered);
			}
		}

	}



	class Driver extends SqlDriver {
		static $extensions = array("MySQLi", "MySQL", "PDO_MySQL");
		static $jush = "sql"; // JUSH identifier

		public $unsigned = array("unsigned", "zerofill", "unsigned zerofill");
		public $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "REGEXP", "IN", "FIND_IN_SET", "IS NULL", "NOT LIKE", "NOT REGEXP", "NOT IN", "IS NOT NULL", "SQL");
		public $functions = array("char_length", "date", "from_unixtime", "lower", "round", "floor", "ceil", "sec_to_time", "time_to_sec", "upper");
		public $grouping = array("avg", "count", "count distinct", "group_concat", "max", "min", "sum");

		static function connect(string $server, string $username, string $password) {
			$connection = parent::connect($server, $username, $password);
			if (is_string($connection)) {
				if (function_exists('iconv') && !is_utf8($connection) && strlen($s = iconv("windows-1250", "utf-8", $connection)) > strlen($connection)) { // windows-1250 - most common Windows encoding
					$connection = $s;
				}
				return $connection;
			}
			$connection->set_charset(charset($connection));
			$connection->query("SET sql_quote_show_create = 1, autocommit = 1");
			$connection->flavor = (preg_match('~MariaDB~', $connection->server_info) ? 'maria' : 'mysql');
			add_driver(DRIVER, ($connection->flavor == 'maria' ? "MariaDB" : "MySQL"));
			return $connection;
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array(
				lang(27) => array("tinyint" => 3, "smallint" => 5, "mediumint" => 8, "int" => 10, "bigint" => 20, "decimal" => 66, "float" => 12, "double" => 21),
				lang(28) => array("date" => 10, "datetime" => 19, "timestamp" => 19, "time" => 10, "year" => 4),
				lang(29) => array("char" => 255, "varchar" => 65535, "tinytext" => 255, "text" => 65535, "mediumtext" => 16777215, "longtext" => 4294967295),
				lang(84) => array("enum" => 65535, "set" => 64),
				lang(30) => array("bit" => 20, "binary" => 255, "varbinary" => 65535, "tinyblob" => 255, "blob" => 65535, "mediumblob" => 16777215, "longblob" => 4294967295),
				lang(32) => array("geometry" => 0, "point" => 0, "linestring" => 0, "polygon" => 0, "multipoint" => 0, "multilinestring" => 0, "multipolygon" => 0, "geometrycollection" => 0),
			);
			$this->insertFunctions = array(
				"char" => "md5/sha1/password/encrypt/uuid",
				"binary" => "md5/sha1",
				"date|time" => "now",
			);
			$this->editFunctions = array(
				number_type() => "+/-",
				"date" => "+ interval/- interval",
				"time" => "addtime/subtime",
				"char|text" => "concat",
			);
			if (min_version('5.7.8', 10.2, $connection)) {
				$this->types[lang(29)]["json"] = 4294967295;
			}
			if (min_version('', 10.7, $connection)) {
				$this->types[lang(29)]["uuid"] = 128;
				$this->insertFunctions['uuid'] = 'uuid';
			}
			if (min_version(9, '', $connection)) {
				$this->types[lang(27)]["vector"] = 16383;
				$this->insertFunctions['vector'] = 'string_to_vector';
			}
			if (min_version(5.1, '', $connection)) {
				$this->partitionBy = array("HASH", "LINEAR HASH", "KEY", "LINEAR KEY", "RANGE", "LIST");
			}
			if (min_version(5.7, 10.2, $connection)) {
				$this->generated = array("STORED", "VIRTUAL");
			}
		}

		function unconvertFunction(array $field) {
			return (preg_match("~binary~", $field["type"]) ? "<code class='jush-sql'>UNHEX</code>"
				: ($field["type"] == "bit" ? doc_link(array('sql' => 'bit-value-literals.html'), "<code>b''</code>")
				: (preg_match("~geometry|point|linestring|polygon~", $field["type"]) ? "<code class='jush-sql'>GeomFromText</code>"
				: "")));
		}

		function insert(string $table, array $set) {
			return ($set ? parent::insert($table, $set) : queries("INSERT INTO " . table($table) . " ()\nVALUES ()"));
		}

		function insertUpdate(string $table, array $rows, array $primary) {
			$columns = array_keys(reset($rows));
			$prefix = "INSERT INTO " . table($table) . " (" . implode(", ", $columns) . ") VALUES\n";
			$values = array();
			foreach ($columns as $key) {
				$values[$key] = "$key = VALUES($key)";
			}
			$suffix = "\nON DUPLICATE KEY UPDATE " . implode(", ", $values);
			$values = array();
			$length = 0;
			foreach ($rows as $set) {
				$value = "(" . implode(", ", $set) . ")";
				if ($values && (strlen($prefix) + $length + strlen($value) + strlen($suffix) > 1e6)) { // 1e6 - default max_allowed_packet
					if (!queries($prefix . implode(",\n", $values) . $suffix)) {
						return false;
					}
					$values = array();
					$length = 0;
				}
				$values[] = $value;
				$length += strlen($value) + 2; // 2 - strlen(",\n")
			}
			return queries($prefix . implode(",\n", $values) . $suffix);
		}

		function slowQuery(string $query, int $timeout) {
			if (min_version('5.7.8', '10.1.2')) {
				if ($this->conn->flavor == 'maria') {
					return "SET STATEMENT max_statement_time=$timeout FOR $query";
				} elseif (preg_match('~^(SELECT\b)(.+)~is', $query, $match)) {
					return "$match[1] /*+ MAX_EXECUTION_TIME(" . ($timeout * 1000) . ") */ $match[2]";
				}
			}
		}

		function convertSearch(string $idf, array $val, array $field): string {
			return (preg_match('~char|text|enum|set~', $field["type"]) && !preg_match("~^utf8~", $field["collation"]) && preg_match('~[\x80-\xFF]~', $val['val'])
				? "CONVERT($idf USING " . charset($this->conn) . ")"
				: $idf
			);
		}

		function warnings() {
			$result = $this->conn->query("SHOW WARNINGS");
			if ($result && $result->num_rows) {
				ob_start();
				print_select_result($result); // print_select_result() usually needs to print a big table progressively
				return ob_get_clean();
			}
		}

		function tableHelp(string $name, bool $is_view = false) {
			$maria = ($this->conn->flavor == 'maria');
			if (information_schema(DB)) {
				return strtolower("information-schema-" . ($maria ? "$name-table/" : str_replace("_", "-", $name) . "-table.html"));
			}
			if (DB == "mysql") {
				return ($maria ? "mysql$name-table/" : "system-schema.html"); //! more precise link
			}
		}

		function partitionsInfo(string $table): array {
			$from = "FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = " . q(DB) . " AND TABLE_NAME = " . q($table);
			$result = $this->conn->query("SELECT PARTITION_METHOD, PARTITION_EXPRESSION, PARTITION_ORDINAL_POSITION $from ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");
			$return = array();
			list($return["partition_by"], $return["partition"], $return["partitions"]) = $result->fetch_row();
			$partitions = get_key_vals("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $from AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");
			$return["partition_names"] = array_keys($partitions);
			$return["partition_values"] = array_values($partitions);
			return $return;
		}

		function hasCStyleEscapes(): bool {
			static $c_style;
			if ($c_style === null) {
				$sql_mode = get_val("SHOW VARIABLES LIKE 'sql_mode'", 1, $this->conn);
				$c_style = (strpos($sql_mode, 'NO_BACKSLASH_ESCAPES') === false);
			}
			return $c_style;
		}

		function engines(): array {
			$return = array();
			foreach (get_rows("SHOW ENGINES") as $row) {
				if (preg_match("~YES|DEFAULT~", $row["Support"])) {
					$return[] = $row["Engine"];
				}
			}
			return $return;
		}

		function indexAlgorithms(array $tableStatus): array {
			return (preg_match('~^(MEMORY|NDB)$~', $tableStatus["Engine"]) ? array("HASH", "BTREE") : array());
		}
	}



	/** Escape database identifier */
	function idf_escape(string $idf): string {
		return "`" . str_replace("`", "``", $idf) . "`";
	}

	/** Get escaped table name */
	function table(string $idf): string {
		return idf_escape($idf);
	}

	/** Get cached list of databases
	* @return list<string>
	*/
	function get_databases(bool $flush): array {
		// SHOW DATABASES can take a very long time so it is cached
		$return = get_session("dbs");
		if ($return === null) {
			$query = "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME"; // SHOW DATABASES can be disabled by skip_show_database
			$return = ($flush ? slow_query($query) : get_vals($query));
			restart_session();
			set_session("dbs", $return);
			stop_session();
		}
		return $return;
	}

	/** Formulate SQL query with limit
	* @param string $query everything after SELECT
	* @param string $where including WHERE
	*/
	function limit(string $query, string $where, int $limit, int $offset = 0, string $separator = " "): string {
		return " $query$where" . ($limit ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	/** Formulate SQL modification query with limit 1
	* @param string $query everything after UPDATE or DELETE
	*/
	function limit1(string $table, string $query, string $where, string $separator = "\n"): string {
		return limit($query, $where, 1, 0, $separator);
	}

	/** Get database collation
	* @param string[][] $collations result of collations()
	*/
	function db_collation(string $db, array $collations): ?string {
		$return = null;
		$create = get_val("SHOW CREATE DATABASE " . idf_escape($db), 1);
		if (preg_match('~ COLLATE ([^ ]+)~', $create, $match)) {
			$return = $match[1];
		} elseif (preg_match('~ CHARACTER SET ([^ ]+)~', $create, $match)) {
			// default collation
			$return = $collations[$match[1]][-1];
		}
		return $return;
	}

	/** Get logged user */
	function logged_user(): string {
		return get_val("SELECT USER()");
	}

	/** Get tables list
	* @return string[] [$name => $type]
	*/
	function tables_list(): array {
		return get_key_vals("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
	}

	/** Count tables in all databases
	* @param list<string> $databases
	* @return int[] [$db => $tables]
	*/
	function count_tables(array $databases): array {
		$return = array();
		foreach ($databases as $db) {
			$return[$db] = count(get_vals("SHOW TABLES IN " . idf_escape($db)));
		}
		return $return;
	}

	/** Get table status
	* @param bool $fast return only "Name", "Engine" and "Comment" fields
	* @return array<string, TableStatus>
	*/
	function table_status(string $name = "", bool $fast = false): array {
		$return = array();
		foreach (
			get_rows(
				$fast
				? "SELECT TABLE_NAME AS Name, ENGINE AS Engine, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() " . ($name != "" ? "AND TABLE_NAME = " . q($name) : "ORDER BY Name")
				: "SHOW TABLE STATUS" . ($name != "" ? " LIKE " . q(addcslashes($name, "%_\\")) : "")
			) as $row
		) {
			if ($row["Engine"] == "InnoDB") {
				// ignore internal comment, unnecessary since MySQL 5.1.21
				$row["Comment"] = preg_replace('~(?:(.+); )?InnoDB free: .*~', '\1', $row["Comment"]);
			}
			if (!isset($row["Engine"])) {
				$row["Comment"] = "";
			}
			if ($name != "") {
				// MariaDB: Table name is returned as lowercase on macOS, so we fix it here.
				$row["Name"] = $name;
			}
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	/** Find out whether the identifier is view
	* @param TableStatus $table_status
	*/
	function is_view(array $table_status): bool {
		return $table_status["Engine"] === null;
	}

	/** Check if table supports foreign keys
	* @param TableStatus $table_status
	*/
	function fk_support(array $table_status): bool {
		return preg_match('~InnoDB|IBMDB2I' . (min_version(5.6) ? '|NDB' : '') . '~i', $table_status["Engine"]);
	}

	/** Get information about fields
	* @return Field[]
	*/
	function fields(string $table): array {
		$maria = (connection()->flavor == 'maria');
		$return = array();
		foreach (get_rows("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . q($table) . " ORDER BY ORDINAL_POSITION") as $row) {
			$field = $row["COLUMN_NAME"];
			$type = $row["COLUMN_TYPE"];
			$generation = $row["GENERATION_EXPRESSION"];
			$extra = $row["EXTRA"];
			// https://mariadb.com/kb/en/library/show-columns/, https://github.com/vrana/adminer/pull/359#pullrequestreview-276677186
			preg_match('~^(VIRTUAL|PERSISTENT|STORED)~', $extra, $generated);
			preg_match('~^([^( ]+)(?:\((.+)\))?( unsigned)?( zerofill)?$~', $type, $match_type);
			$default = $row["COLUMN_DEFAULT"];
			if ($default != "") {
				$is_text = preg_match('~text|json~', $match_type[1]);
				if (!$maria && $is_text) {
					// default value a'b of text column is stored as _utf8mb4\'a\\\'b\' in MySQL
					$default = preg_replace("~^(_\w+)?('.*')$~", '\2', stripslashes($default));
				}
				if ($maria || $is_text) {
					$default = ($default == "NULL" ? null : preg_replace_callback("~^'(.*)'$~", function ($match) {
						return stripslashes(str_replace("''", "'", $match[1]));
					}, $default));
				}
				if (!$maria && preg_match('~binary~', $match_type[1]) && preg_match('~^0x(\w*)$~', $default, $match)) {
					$default = pack("H*", $match[1]);
				}
			}
			$return[$field] = array(
				"field" => $field,
				"full_type" => $type,
				"type" => $match_type[1],
				"length" => $match_type[2],
				"unsigned" => ltrim($match_type[3] . $match_type[4]),
				"default" => ($generated
					? ($maria ? $generation : stripslashes($generation))
					: $default
				),
				"null" => ($row["IS_NULLABLE"] == "YES"),
				"auto_increment" => ($extra == "auto_increment"),
				"on_update" => (preg_match('~\bon update (\w+)~i', $extra, $match) ? $match[1] : ""), //! available since MySQL 5.1.23
				"collation" => $row["COLLATION_NAME"],
				"privileges" => array_flip(explode(",", "$row[PRIVILEGES],where,order")),
				"comment" => $row["COLUMN_COMMENT"],
				"primary" => ($row["COLUMN_KEY"] == "PRI"),
				"generated" => ($generated[1] == "PERSISTENT" ? "STORED" : $generated[1]),
			);
		}
		return $return;
	}

	/** Get table indexes
	* @return Index[]
	*/
	function indexes(string $table, ?Db $connection2 = null): array {
		$return = array();
		foreach (get_rows("SHOW INDEX FROM " . table($table), $connection2) as $row) {
			$name = $row["Key_name"];
			$return[$name]["type"] = ($name == "PRIMARY" ? "PRIMARY" : ($row["Index_type"] == "FULLTEXT" ? "FULLTEXT" : ($row["Non_unique"] ? ($row["Index_type"] == "SPATIAL" ? "SPATIAL" : "INDEX") : "UNIQUE")));
			$return[$name]["columns"][] = $row["Column_name"];
			$return[$name]["lengths"][] = ($row["Index_type"] == "SPATIAL" ? null : $row["Sub_part"]);
			$return[$name]["descs"][] = null;
			$return[$name]["algorithm"] = $row["Index_type"];
		}
		return $return;
	}

	/** Get foreign keys in table
	* @return ForeignKey[]
	*/
	function foreign_keys(string $table): array {
		static $pattern = '(?:`(?:[^`]|``)+`|"(?:[^"]|"")+")';
		$return = array();
		$create_table = get_val("SHOW CREATE TABLE " . table($table), 1);
		if ($create_table) {
			preg_match_all(
				"~CONSTRAINT ($pattern) FOREIGN KEY ?\\(((?:$pattern,? ?)+)\\) REFERENCES ($pattern)(?:\\.($pattern))? \\(((?:$pattern,? ?)+)\\)(?: ON DELETE (" . driver()->onActions . "))?(?: ON UPDATE (" . driver()->onActions . "))?~",
				$create_table,
				$matches,
				PREG_SET_ORDER
			);
			foreach ($matches as $match) {
				preg_match_all("~$pattern~", $match[2], $source);
				preg_match_all("~$pattern~", $match[5], $target);
				$return[idf_unescape($match[1])] = array(
					"db" => idf_unescape($match[4] != "" ? $match[3] : $match[4]),
					"table" => idf_unescape($match[4] != "" ? $match[4] : $match[3]),
					"source" => array_map('Adminer\idf_unescape', $source[0]),
					"target" => array_map('Adminer\idf_unescape', $target[0]),
					"on_delete" => ($match[6] ?: "RESTRICT"),
					"on_update" => ($match[7] ?: "RESTRICT"),
				);
			}
		}
		return $return;
	}

	/** Get view SELECT
	* @return array{select:string}
	*/
	function view(string $name): array {
		return array("select" => preg_replace('~^(?:[^`]|`[^`]*`)*\s+AS\s+~isU', '', get_val("SHOW CREATE VIEW " . table($name), 1)));
	}

	/** Get sorted grouped list of collations
	* @return string[][]
	*/
	function collations(): array {
		$return = array();
		foreach (get_rows("SHOW COLLATION") as $row) {
			if ($row["Default"]) {
				$return[$row["Charset"]][-1] = $row["Collation"];
			} else {
				$return[$row["Charset"]][] = $row["Collation"];
			}
		}
		ksort($return);
		foreach ($return as $key => $val) {
			sort($return[$key]);
		}
		return $return;
	}

	/** Find out if database is information_schema */
	function information_schema(?string $db): bool {
		return ($db == "information_schema")
			|| (min_version(5.5) && $db == "performance_schema");
	}

	/** Get escaped error message */
	function error(): string {
		return h(preg_replace('~^You have an error.*syntax to use~U', "Syntax error", connection()->error));
	}

	/** Create database
	* @return Result
	*/
	function create_database(string $db, string $collation) {
		return queries("CREATE DATABASE " . idf_escape($db) . ($collation ? " COLLATE " . q($collation) : ""));
	}

	/** Drop databases
	* @param list<string> $databases
	*/
	function drop_databases(array $databases): bool {
		$return = apply_queries("DROP DATABASE", $databases, 'Adminer\idf_escape');
		restart_session();
		set_session("dbs", null);
		return $return;
	}

	/** Rename database from DB
	* @param string $name new name
	*/
	function rename_database(string $name, string $collation): bool {
		$return = false;
		if (create_database($name, $collation)) {
			$tables = array();
			$views = array();
			foreach (tables_list() as $table => $type) {
				if ($type == 'VIEW') {
					$views[] = $table;
				} else {
					$tables[] = $table;
				}
			}
			$return = (!$tables && !$views) || move_tables($tables, $views, $name);
			drop_databases($return ? array(DB) : array());
		}
		return $return;
	}

	/** Generate modifier for auto increment column */
	function auto_increment(): string {
		$auto_increment_index = " PRIMARY KEY";
		// don't overwrite primary key by auto_increment
		if ($_GET["create"] != "" && $_POST["auto_increment_col"]) {
			foreach (indexes($_GET["create"]) as $index) {
				if (in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"], $index["columns"], true)) {
					$auto_increment_index = "";
					break;
				}
				if ($index["type"] == "PRIMARY") {
					$auto_increment_index = " UNIQUE";
				}
			}
		}
		return " AUTO_INCREMENT$auto_increment_index";
	}

	/** Run commands to create or alter table
	* @param string $table "" to create
	* @param string $name new name
	* @param list<array{string, list<string>, string}> $fields of [$orig, $process_field, $after]
	* @param string[] $foreign
	* @param numeric-string|'' $auto_increment
	* @param ?Partitions $partitioning null means remove partitioning
	* @return Result|bool
	*/
	function alter_table(string $table, string $name, array $fields, array $foreign, ?string $comment, string $engine, string $collation, string $auto_increment, ?array $partitioning) {
		$alter = array();
		foreach ($fields as $field) {
			if ($field[1]) {
				$default = $field[1][3];
				if (preg_match('~ GENERATED~', $default)) {
					// swap default and null
					$field[1][3] = (connection()->flavor == 'maria' ? "" : $field[1][2]); // MariaDB doesn't support NULL on virtual columns
					$field[1][2] = $default;
				}
				$alter[] = ($table != "" ? ($field[0] != "" ? "CHANGE " . idf_escape($field[0]) : "ADD") : " ") . " " . implode($field[1]) . ($table != "" ? $field[2] : "");
			} else {
				$alter[] = "DROP " . idf_escape($field[0]);
			}
		}
		$alter = array_merge($alter, $foreign);
		$status = ($comment !== null ? " COMMENT=" . q($comment) : "")
			. ($engine ? " ENGINE=" . q($engine) : "")
			. ($collation ? " COLLATE " . q($collation) : "")
			. ($auto_increment != "" ? " AUTO_INCREMENT=$auto_increment" : "")
		;

		if ($partitioning) {
			$partitions = array();
			if ($partitioning["partition_by"] == 'RANGE' || $partitioning["partition_by"] == 'LIST') {
				foreach ($partitioning["partition_names"] as $key => $val) {
					$value = $partitioning["partition_values"][$key];
					$partitions[] = "\n  PARTITION " . idf_escape($val) . " VALUES " . ($partitioning["partition_by"] == 'RANGE' ? "LESS THAN" : "IN") . ($value != "" ? " ($value)" : " MAXVALUE"); //! SQL injection
				}
			}
			// $partitioning["partition"] can be expression, not only column
			$status .= "\nPARTITION BY $partitioning[partition_by]($partitioning[partition])";
			if ($partitions) {
				$status .= " (" . implode(",", $partitions) . "\n)";
			} elseif ($partitioning["partitions"]) {
				$status .= " PARTITIONS " . (+$partitioning["partitions"]);
			}
		} elseif ($partitioning === null) {
			$status .= "\nREMOVE PARTITIONING";
		}

		if ($table == "") {
			return queries("CREATE TABLE " . table($name) . " (\n" . implode(",\n", $alter) . "\n)$status");
		}
		if ($table != $name) {
			$alter[] = "RENAME TO " . table($name);
		}
		if ($status) {
			$alter[] = ltrim($status);
		}
		return ($alter ? queries("ALTER TABLE " . table($table) . "\n" . implode(",\n", $alter)) : true);
	}

	/** Run commands to alter indexes
	* @param string $table escaped table name
	* @param list<array{string, string, 'DROP'|list<string>, 3?: string, 4?: string}> $alter of ["index type", "name", ["column definition", ...], "algorithm", "condition"] or ["index type", "name", "DROP"]
	* @return Result|bool
	*/
	function alter_indexes(string $table, $alter) {
		$changes = array();
		foreach ($alter as $val) {
			$changes[] = ($val[2] == "DROP"
				? "\nDROP INDEX " . idf_escape($val[1])
				: "\nADD $val[0] " . ($val[0] == "PRIMARY" ? "KEY " : "") . ($val[1] != "" ? idf_escape($val[1]) . " " : "") . "(" . implode(", ", $val[2]) . ")"
			);
		}
		return queries("ALTER TABLE " . table($table) . implode(",", $changes));
	}

	/** Run commands to truncate tables
	* @param list<string> $tables
	*/
	function truncate_tables(array $tables): bool {
		return apply_queries("TRUNCATE TABLE", $tables);
	}

	/** Drop views
	* @param list<string> $views
	* @return Result|bool
	*/
	function drop_views(array $views) {
		return queries("DROP VIEW " . implode(", ", array_map('Adminer\table', $views)));
	}

	/** Drop tables
	* @param list<string> $tables
	* @return Result|bool
	*/
	function drop_tables(array $tables) {
		return queries("DROP TABLE " . implode(", ", array_map('Adminer\table', $tables)));
	}

	/** Move tables to other schema
	* @param list<string> $tables
	* @param list<string> $views
	*/
	function move_tables(array $tables, array $views, string $target): bool {
		$rename = array();
		foreach ($tables as $table) {
			$rename[] = table($table) . " TO " . idf_escape($target) . "." . table($table);
		}
		if (!$rename || queries("RENAME TABLE " . implode(", ", $rename))) {
			$definitions = array();
			foreach ($views as $table) {
				$definitions[table($table)] = view($table);
			}
			connection()->select_db($target);
			$db = idf_escape(DB);
			foreach ($definitions as $name => $view) {
				if (!queries("CREATE VIEW $name AS " . str_replace(" $db.", " ", $view["select"])) || !queries("DROP VIEW $db.$name")) {
					return false;
				}
			}
			return true;
		}
		//! move triggers
		return false;
	}

	/** Copy tables to other schema
	* @param list<string> $tables
	* @param list<string> $views
	*/
	function copy_tables(array $tables, array $views, string $target): bool {
		queries("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
		foreach ($tables as $table) {
			$name = ($target == DB ? table("copy_$table") : idf_escape($target) . "." . table($table));
			if (
				($_POST["overwrite"] && !queries("\nDROP TABLE IF EXISTS $name"))
				|| !queries("CREATE TABLE $name LIKE " . table($table))
				|| !queries("INSERT INTO $name SELECT * FROM " . table($table))
			) {
				return false;
			}
			foreach (get_rows("SHOW TRIGGERS LIKE " . q(addcslashes($table, "%_\\"))) as $row) {
				$trigger = $row["Trigger"];
				if (!queries("CREATE TRIGGER " . ($target == DB ? idf_escape("copy_$trigger") : idf_escape($target) . "." . idf_escape($trigger)) . " $row[Timing] $row[Event] ON $name FOR EACH ROW\n$row[Statement];")) {
					return false;
				}
			}
		}
		foreach ($views as $table) {
			$name = ($target == DB ? table("copy_$table") : idf_escape($target) . "." . table($table));
			$view = view($table);
			if (
				($_POST["overwrite"] && !queries("DROP VIEW IF EXISTS $name"))
				|| !queries("CREATE VIEW $name AS $view[select]") //! USE to avoid db.table
			) {
				return false;
			}
		}
		return true;
	}

	/** Get information about trigger
	* @param string $name trigger name
	* @return Trigger
	*/
	function trigger(string $name, string $table): array {
		if ($name == "") {
			return array();
		}
		$rows = get_rows("SHOW TRIGGERS WHERE `Trigger` = " . q($name));
		return reset($rows);
	}

	/** Get defined triggers
	* @return array{string, string}[]
	*/
	function triggers(string $table): array {
		$return = array();
		foreach (get_rows("SHOW TRIGGERS LIKE " . q(addcslashes($table, "%_\\"))) as $row) {
			$return[$row["Trigger"]] = array($row["Timing"], $row["Event"]);
		}
		return $return;
	}

	/** Get trigger options
	* @return array{Timing: list<string>, Event: list<string>, Type: list<string>}
	*/
	function trigger_options(): array {
		return array(
			"Timing" => array("BEFORE", "AFTER"),
			"Event" => array("INSERT", "UPDATE", "DELETE"),
			"Type" => array("FOR EACH ROW"),
		);
	}

	/** Get information about stored routine
	* @param 'FUNCTION'|'PROCEDURE' $type
	* @return Routine
	*/
	function routine(string $name, string $type): array {
		$aliases = array("bool", "boolean", "integer", "double precision", "real", "dec", "numeric", "fixed", "national char", "national varchar");
		$space = "(?:\\s|/\\*[\s\S]*?\\*/|(?:#|-- )[^\n]*\n?|--\r?\n)";
		$enum = driver()->enumLength;
		$type_pattern = "((" . implode("|", array_merge(array_keys(driver()->types()), $aliases)) . ")\\b(?:\\s*\\(((?:[^'\")]|$enum)++)\\))?"
			. "\\s*(zerofill\\s*)?(unsigned(?:\\s+zerofill)?)?)(?:\\s*(?:CHARSET|CHARACTER\\s+SET)\\s*['\"]?([^'\"\\s,]+)['\"]?)?(?:\\s*COLLATE\\s*['\"]?[^'\"\\s,]+['\"]?)?"; //! store COLLATE
		$pattern = "$space*(" . ($type == "FUNCTION" ? "" : driver()->inout) . ")?\\s*(?:`((?:[^`]|``)*)`\\s*|\\b(\\S+)\\s+)$type_pattern";
		$create = get_val("SHOW CREATE $type " . idf_escape($name), 2);
		preg_match("~\\(((?:$pattern\\s*,?)*)\\)\\s*" . ($type == "FUNCTION" ? "RETURNS\\s+$type_pattern\\s+" : "") . "(.*)~is", $create, $match);
		$fields = array();
		preg_match_all("~$pattern\\s*,?~is", $match[1], $matches, PREG_SET_ORDER);
		foreach ($matches as $param) {
			$fields[] = array(
				"field" => str_replace("``", "`", $param[2]) . $param[3],
				"type" => strtolower($param[5]),
				"length" => preg_replace_callback("~$enum~s", 'Adminer\normalize_enum', $param[6]),
				"unsigned" => strtolower(preg_replace('~\s+~', ' ', trim("$param[8] $param[7]"))),
				"null" => true,
				"full_type" => $param[4],
				"inout" => strtoupper($param[1]),
				"collation" => strtolower($param[9]),
			);
		}
		return array(
			"fields" => $fields,
			"comment" => get_val("SELECT ROUTINE_COMMENT FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = " . q($name)),
		) + ($type != "FUNCTION" ? array("definition" => $match[11]) : array(
			"returns" => array("type" => $match[12], "length" => $match[13], "unsigned" => $match[15], "collation" => $match[16]),
			"definition" => $match[17],
			"language" => "SQL", // available in information_schema.ROUTINES.BODY_STYLE
		));
	}

	/** Get list of routines
	* @return list<string[]> ["SPECIFIC_NAME" => , "ROUTINE_NAME" => , "ROUTINE_TYPE" => , "DTD_IDENTIFIER" => ]
	*/
	function routines(): array {
		return get_rows("SELECT SPECIFIC_NAME, ROUTINE_NAME, ROUTINE_TYPE, DTD_IDENTIFIER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()");
	}

	/** Get list of available routine languages
	* @return list<string>
	*/
	function routine_languages(): array {
		return array(); // "SQL" not required
	}

	/** Get routine signature
	* @param Routine $row
	*/
	function routine_id(string $name, array $row): string {
		return idf_escape($name);
	}

	/** Get last auto increment ID
	* @param Result|bool $result
	*/
	function last_id($result): string {
		return get_val("SELECT LAST_INSERT_ID()"); // mysql_insert_id() truncates bigint
	}

	/** Explain select
	* @return Result
	*/
	function explain(Db $connection, string $query) {
		return $connection->query("EXPLAIN " . (min_version(5.1) && !min_version(5.7) ? "PARTITIONS " : "") . $query);
	}

	/** Get approximate number of rows
	* @param TableStatus $table_status
	* @param list<string> $where
	* @return numeric-string|null null if approximate number can't be retrieved
	*/
	function found_rows(array $table_status, array $where) {
		return ($where || $table_status["Engine"] != "InnoDB" ? null : $table_status["Rows"]);
	}

	/** Get SQL command to create table */
	function create_sql(string $table, ?bool $auto_increment, string $style): string {
		$return = get_val("SHOW CREATE TABLE " . table($table), 1);
		if (!$auto_increment) {
			$return = preg_replace('~ AUTO_INCREMENT=\d+~', '', $return); //! skip comments
		}
		return $return;
	}

	/** Get SQL command to truncate table */
	function truncate_sql(string $table): string {
		return "TRUNCATE " . table($table);
	}

	/** Get SQL command to change database */
	function use_sql(string $database, string $style = ""): string {
		$name = idf_escape($database);
		$return = "";
		if (preg_match('~CREATE~', $style) && ($create = get_val("SHOW CREATE DATABASE $name", 1))) {
			set_utf8mb4($create);
			if ($style == "DROP+CREATE") {
				$return = "DROP DATABASE IF EXISTS $name;\n";
			}
			$return .= "$create;\n";
		}
		return $return . "USE $name";
	}

	/** Get SQL commands to create triggers */
	function trigger_sql(string $table): string {
		$return = "";
		foreach (get_rows("SHOW TRIGGERS LIKE " . q(addcslashes($table, "%_\\")), null, "-- ") as $row) {
			$return .= "\nCREATE TRIGGER " . idf_escape($row["Trigger"]) . " $row[Timing] $row[Event] ON " . table($row["Table"]) . " FOR EACH ROW\n$row[Statement];;\n";
		}
		return $return;
	}

	/** Get server variables
	* @return list<string[]> [[$name, $value]]
	*/
	function show_variables(): array {
		return get_rows("SHOW VARIABLES");
	}

	/** Get status variables
	* @return list<string[]> [[$name, $value]]
	*/
	function show_status(): array {
		return get_rows("SHOW STATUS");
	}

	/** Get process list
	* @return list<string[]> [$row]
	*/
	function process_list(): array {
		return get_rows("SHOW FULL PROCESSLIST");
	}

	/** Convert field in select and edit
	* @param Field $field
	* @return string|void
	*/
	function convert_field(array $field) {
		if (preg_match("~binary~", $field["type"])) {
			return "HEX(" . idf_escape($field["field"]) . ")";
		}
		if ($field["type"] == "bit") {
			return "BIN(" . idf_escape($field["field"]) . " + 0)"; // + 0 is required outside MySQLnd
		}
		if (preg_match("~geometry|point|linestring|polygon~", $field["type"])) {
			return (min_version(8) ? "ST_" : "") . "AsWKT(" . idf_escape($field["field"]) . ")";
		}
	}

	/** Convert value in edit after applying functions back
	* @param Field $field
	* @param string $return SQL expression
	*/
	function unconvert_field(array $field, string $return): string {
		if (preg_match("~binary~", $field["type"])) {
			$return = "UNHEX($return)";
		}
		if ($field["type"] == "bit") {
			$return = "CONVERT(b$return, UNSIGNED)";
		}
		if (preg_match("~geometry|point|linestring|polygon~", $field["type"])) {
			$prefix = (min_version(8) ? "ST_" : "");
			$return = $prefix . "GeomFromText($return, $prefix" . "SRID($field[field]))";
		}
		return $return;
	}

	/** Check whether a feature is supported
	* @param literal-string $feature check|comment|columns|copy|database|descidx|drop_col|dump|event|indexes|kill|materializedview
	* |move_col|privileges|procedure|processlist|routine|scheme|sequence|sql|status|table|trigger|type|variables|view|view_trigger
	*/
	function support(string $feature): bool {
		return preg_match(
			'~^(comment|columns|copy|database|drop_col|dump|indexes|kill|privileges|move_col|procedure|processlist|routine|sql|status|table|trigger|variables|view'
				. (min_version(5.1) ? '|event' : '')
				. (min_version(8) ? '|descidx' : '')
				. (min_version('8.0.16', '10.2.1') ? '|check' : '')
				. ')$~',
			$feature
		);
	}

	/** Kill a process
	* @param numeric-string $id
	* @return Result|bool
	*/
	function kill_process(string $id) {
		return queries("KILL " . number($id));
	}

	/** Return query to get connection ID */
	function connection_id(): string {
		return "SELECT CONNECTION_ID()";
	}

	/** Get maximum number of connections
	* @return numeric-string
	*/
	function max_connections(): string {
		return get_val("SELECT @@max_connections");
	}

	// Not used is MySQL but checked in compile.php:

	/** Get user defined types
	* @return string[] [$id => $name]
	*/
	function types(): array {
		return array();
	}

	/** Get values of user defined type */
	function type_values(int $id): string {
		return "";
	}

	/** Get existing schemas
	* @return list<string>
	*/
	function schemas(): array {
		return array();
	}

	/** Get current schema */
	function get_schema(): string {
		return "";
	}

	/** Set current schema
	*/
	function set_schema(string $schema, ?Db $connection2 = null): bool {
		return true;
	}
}
 // must be included as last driver

define('Adminer\JUSH', Driver::$jush);
define('Adminer\SERVER', "" . $_GET[DRIVER]); // read from pgsql=localhost, '' means default server
define('Adminer\DB', "$_GET[db]"); // for the sake of speed and size
define(
	'Adminer\ME',
	preg_replace('~\?.*~', '', relative_uri()) . '?'
		. (sid() ? SID . '&' : '')
		. (SERVER !== null ? DRIVER . "=" . urlencode(SERVER) . '&' : '')
		. ($_GET["ext"] ? "ext=" . urlencode($_GET["ext"]) . '&' : '')
		. (isset($_GET["username"]) ? "username=" . urlencode($_GET["username"]) . '&' : '')
		. (DB != "" ? 'db=' . urlencode(DB) . '&' . (isset($_GET["ns"]) ? "ns=" . urlencode($_GET["ns"]) . "&" : "") : '')
);

?>
<?php
/** Print HTML header
* @param string $title used in title, breadcrumb and heading, should be HTML escaped
* @param mixed $breadcrumb ["key" => "link", "key2" => ["link", "desc"]], null for nothing, false for driver only, true for driver and server
* @param string $title2 used after colon in title and heading, should be HTML escaped
*/
function page_header(string $title, string $error = "", $breadcrumb = array(), string $title2 = ""): void {
	page_headers();
	if (is_ajax() && $error) {
		page_messages($error);
		exit;
	}
	if (!ob_get_level()) {
		ob_start('ob_gzhandler', 4096);
	}
	$title_all = $title . ($title2 != "" ? ": $title2" : "");
	$title_page = strip_tags($title_all . (SERVER != "" && SERVER != "localhost" ? h(" - " . SERVER) : "") . " - " . adminer()->name());
	// initial-scale=1 is the default but Chrome 134 on iOS is not able to zoom out without it
	?>
<!DOCTYPE html>
<html lang="<?php echo LANG; ?>" dir="<?php echo lang(85); ?>">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $title_page; ?></title>
<link rel="stylesheet" href="<?php echo h(preg_replace("~\\?.*~", "", ME) . "?file=default.css&version=5.4.1"); ?>">
<?php

	$css = adminer()->css();
	if (is_int(key($css))) { // legacy return value
		$css = array_fill_keys($css, 'light');
	}
	$has_light = in_array('light', $css) || in_array('', $css);
	$has_dark = in_array('dark', $css) || in_array('', $css);
	$dark = ($has_light
		? ($has_dark ? null : false) // both styles - autoswitching, only adminer.css - light
		: ($has_dark ?: null) // only adminer-dark.css - dark, neither - autoswitching
	);
	$media = " media='(prefers-color-scheme: dark)'";
	if ($dark !== false) {
		echo "<link rel='stylesheet'" . ($dark ? "" : $media) . " href='" . h(preg_replace("~\\?.*~", "", ME) . "?file=dark.css&version=5.4.1") . "'>\n";
	}
	echo "<meta name='color-scheme' content='" . ($dark === null ? "light dark" : ($dark ? "dark" : "light")) . "'>\n";

	// this is matched by compile.php
	echo script_src(preg_replace("~\\?.*~", "", ME) . "?file=functions.js&version=5.4.1");
		if (adminer()->head($dark)) {
		echo "<link rel='icon' href='data:image/gif;base64,R0lGODlhEAAQAJEAAAQCBPz+/PwCBAROZCH5BAEAAAAALAAAAAAQABAAAAI2hI+pGO1rmghihiUdvUBnZ3XBQA7f05mOak1RWXrNq5nQWHMKvuoJ37BhVEEfYxQzHjWQ5qIAADs='>\n";
		echo "<link rel='apple-touch-icon' href='" . h(preg_replace("~\\?.*~", "", ME) . "?file=logo.png&version=5.4.1") . "'>\n";
	}
	foreach ($css as $url => $mode) {
		$attrs = ($mode == 'dark' && !$dark
			? $media
			: ($mode == 'light' && $has_dark ? " media='(prefers-color-scheme: light)'" : "")
		);
		echo "<link rel='stylesheet'$attrs href='" . h($url) . "'>\n";
	}
	echo "\n<body class='" . lang(85) . " nojs";
	adminer()->bodyClass();
	echo "'>\n";
	$filename = get_temp_dir() . "/adminer.version";
	if (!$_COOKIE["adminer_version"] && function_exists('openssl_verify') && file_exists($filename) && filemtime($filename) + 86400 > time()) { // 86400 - 1 day in seconds
		$version = unserialize(file_get_contents($filename));
		$public = "-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwqWOVuF5uw7/+Z70djoK
RlHIZFZPO0uYRezq90+7Amk+FDNd7KkL5eDve+vHRJBLAszF/7XKXe11xwliIsFs
DFWQlsABVZB3oisKCBEuI71J4kPH8dKGEWR9jDHFw3cWmoH3PmqImX6FISWbG3B8
h7FIx3jEaw5ckVPVTeo5JRm/1DZzJxjyDenXvBQ/6o9DgZKeNDgxwKzH+sw9/YCO
jHnq1cFpOIISzARlrHMa/43YfeNRAm/tsBXjSxembBPo7aQZLAWHmaj5+K19H10B
nCpz9Y++cipkVEiKRGih4ZEvjoFysEOdRLj6WiD/uUNky4xGeA6LaJqh5XpkFkcQ
fQIDAQAB
-----END PUBLIC KEY-----
";
		if (openssl_verify($version["version"], base64_decode($version["signature"]), $public) == 1) {
			$_COOKIE["adminer_version"] = $version["version"]; // doesn't need to send to the browser
		}
	}
	echo script("mixin(document.body, {onkeydown: bodyKeydown, onclick: bodyClick"
		. (isset($_COOKIE["adminer_version"]) ? "" : ", onload: partial(verifyVersion, '" . VERSION . "', '" . js_escape(ME) . "', '" . get_token() . "')")
		. "});
document.body.classList.replace('nojs', 'js');
const offlineMessage = '" . js_escape(lang(86)) . "';
const thousandsSeparator = '" . js_escape(lang(4)) . "';")
	;
	echo "<div id='help' class='jush-" . JUSH . " jsonly hidden'></div>\n";
	echo script("mixin(qs('#help'), {onmouseover: () => { helpOpen = 1; }, onmouseout: helpMouseout});");
	echo "<div id='content'>\n";
	echo "<span id='menuopen' class='jsonly'>" . icon("move", "", "menu", "") . "</span>" . script("qs('#menuopen').onclick = event => { qs('#foot').classList.toggle('foot'); event.stopPropagation(); }");
	if ($breadcrumb !== null) {
		$link = substr(preg_replace('~\b(username|db|ns)=[^&]*&~', '', ME), 0, -1);
		echo '<p id="breadcrumb"><a href="' . h($link ?: ".") . '">' . get_driver(DRIVER) . '</a> Â» ';
		$link = substr(preg_replace('~\b(db|ns)=[^&]*&~', '', ME), 0, -1);
		$server = adminer()->serverName(SERVER);
		$server = ($server != "" ? $server : lang(34));
		if ($breadcrumb === false) {
			echo "$server\n";
		} else {
			echo "<a href='" . h($link) . "' accesskey='1' title='Alt+Shift+1'>$server</a> Â» ";
			if ($_GET["ns"] != "" || (DB != "" && is_array($breadcrumb))) {
				echo '<a href="' . h($link . "&db=" . urlencode(DB) . (support("scheme") ? "&ns=" : "")) . '">' . h(DB) . '</a> Â» ';
			}
			if (is_array($breadcrumb)) {
				if ($_GET["ns"] != "") {
					echo '<a href="' . h(substr(ME, 0, -1)) . '">' . h($_GET["ns"]) . '</a> Â» ';
				}
				foreach ($breadcrumb as $key => $val) {
					$desc = (is_array($val) ? $val[1] : h($val));
					if ($desc != "") {
						echo "<a href='" . h(ME . "$key=") . urlencode(is_array($val) ? $val[0] : $val) . "'>$desc</a> Â» ";
					}
				}
			}
			echo "$title\n";
		}
	}
	echo "<h2>$title_all</h2>\n";
	echo "<div id='ajaxstatus' class='jsonly hidden'></div>\n";
	restart_session();
	page_messages($error);
	$databases = &get_session("dbs");
	if (DB != "" && $databases && !in_array(DB, $databases, true)) {
		$databases = null;
	}
	stop_session();
	define('Adminer\PAGE_HEADER', 1);
}

/** Send HTTP headers */
function page_headers(): void {
	header("Content-Type: text/html; charset=utf-8");
	header("Cache-Control: no-cache");
	header("X-Frame-Options: deny"); // ClickJacking protection in IE8, Safari 4, Chrome 2, Firefox 3.6.9
	header("X-XSS-Protection: 0"); // prevents introducing XSS in IE8 by removing safe parts of the page
	header("X-Content-Type-Options: nosniff");
	header("Referrer-Policy: origin-when-cross-origin");
	foreach (adminer()->csp(csp()) as $csp) {
		$header = array();
		foreach ($csp as $key => $val) {
			$header[] = "$key $val";
		}
		header("Content-Security-Policy: " . implode("; ", $header));
	}
	adminer()->headers();
}

/** Get Content Security Policy headers
* @return list<string[]> of arrays with directive name in key, allowed sources in value
*/
function csp(): array {
	return array(
		array(
			"script-src" => "'self' 'unsafe-inline' 'nonce-" . get_nonce() . "' 'strict-dynamic'", // 'self' is a fallback for browsers not supporting 'strict-dynamic', 'unsafe-inline' is a fallback for browsers not supporting 'nonce-'
			"connect-src" => "'self'",
			"frame-src" => "https://www.adminer.org",
			"object-src" => "'none'",
			"base-uri" => "'none'",
			"form-action" => "'self'",
		),
	);
}

/** Get a CSP nonce
* @return string Base64 value
*/
function get_nonce(): string {
	static $nonce;
	if (!$nonce) {
		$nonce = base64_encode(rand_string());
	}
	return $nonce;
}

/** Print flash and error messages */
function page_messages(string $error): void {
	$uri = preg_replace('~^[^?]*~', '', $_SERVER["REQUEST_URI"]);
	$messages = idx($_SESSION["messages"], $uri);
	if ($messages) {
		echo "<div class='message'>" . implode("</div>\n<div class='message'>", $messages) . "</div>" . script("messagesPrint();");
		unset($_SESSION["messages"][$uri]);
	}
	if ($error) {
		echo "<div class='error'>$error</div>\n";
	}
	if (adminer()->error) { // separate <div>
		echo "<div class='error'>" . adminer()->error . "</div>\n";
	}
}

/** Print HTML footer
* @param ''|'auth'|'db'|'ns' $missing
*/
function page_footer(string $missing = ""): void {
	echo "</div>\n\n<div id='foot' class='foot'>\n<div id='menu'>\n";
	adminer()->navigation($missing);
	echo "</div>\n";
	if ($missing != "auth") {
		?>
<form action="" method="post">
<p class="logout">
<span><?php echo h($_GET["username"]) . "\n"; ?></span>
<input type="submit" name="logout" value="<?php echo lang(87); ?>" id="logout">
<?php echo input_token(); ?>
</form>
<?php
	}
	echo "</div>\n\n";
	echo script("setupSubmitHighlight(document);");
}

?>
<?php
/** PHP implementation of XXTEA encryption algorithm
* @author Ma Bingyao <andot@ujn.edu.cn>
* @link http://www.coolcode.cn/?action=show&id=128
*/

function int32(int $n): int {
	while ($n >= 2147483648) {
		$n -= 4294967296;
	}
	while ($n <= -2147483649) {
		$n += 4294967296;
	}
	return (int) $n;
}

/**
* @param int[] $v
*/
function long2str(array $v, bool $w): string {
	$s = '';
	foreach ($v as $val) {
		$s .= pack('V', $val);
	}
	if ($w) {
		return substr($s, 0, end($v));
	}
	return $s;
}

/**
* @return int[]
*/
function str2long(string $s, bool $w): array {
	$v = array_values(unpack('V*', str_pad($s, 4 * ceil(strlen($s) / 4), "\0")));
	if ($w) {
		$v[] = strlen($s);
	}
	return $v;
}

function xxtea_mx(int $z, int $y, int $sum, int $k): int {
	return int32((($z >> 5 & 0x7FFFFFF) ^ $y << 2) + (($y >> 3 & 0x1FFFFFFF) ^ $z << 4)) ^ int32(($sum ^ $y) + ($k ^ $z));
}

/** Cipher
* @param string $str plain-text password
* @return string binary cipher
*/
function encrypt_string(string $str, string $key): string {
	if ($str == "") {
		return "";
	}
	$key = array_values(unpack("V*", pack("H*", md5($key))));
	$v = str2long($str, true);
	$n = count($v) - 1;
	$z = $v[$n];
	$y = $v[0];
	$q = floor(6 + 52 / ($n + 1));
	$sum = 0;
	while ($q-- > 0) {
		$sum = int32($sum + 0x9E3779B9);
		$e = $sum >> 2 & 3;
		for ($p=0; $p < $n; $p++) {
			$y = $v[$p + 1];
			$mx = xxtea_mx($z, $y, $sum, $key[$p & 3 ^ $e]);
			$z = int32($v[$p] + $mx);
			$v[$p] = $z;
		}
		$y = $v[0];
		$mx = xxtea_mx($z, $y, $sum, $key[$p & 3 ^ $e]);
		$z = int32($v[$n] + $mx);
		$v[$n] = $z;
	}
	return long2str($v, false);
}

/** Decipher
* @param string $str binary cipher
* @return string|false plain-text password
*/
function decrypt_string(string $str, string $key) {
	if ($str == "") {
		return "";
	}
	if (!$key) {
		return false;
	}
	$key = array_values(unpack("V*", pack("H*", md5($key))));
	$v = str2long($str, false);
	$n = count($v) - 1;
	$z = $v[$n];
	$y = $v[0];
	$q = floor(6 + 52 / ($n + 1));
	$sum = int32($q * 0x9E3779B9);
	while ($sum) {
		$e = $sum >> 2 & 3;
		for ($p=$n; $p > 0; $p--) {
			$z = $v[$p - 1];
			$mx = xxtea_mx($z, $y, $sum, $key[$p & 3 ^ $e]);
			$y = int32($v[$p] - $mx);
			$v[$p] = $y;
		}
		$z = $v[$n];
		$mx = xxtea_mx($z, $y, $sum, $key[$p & 3 ^ $e]);
		$y = int32($v[0] - $mx);
		$v[0] = $y;
		$sum = int32($sum - 0x9E3779B9);
	}
	return long2str($v, true);
}

?>
<?php
$permanent = array();
if ($_COOKIE["adminer_permanent"]) {
	foreach (explode(" ", $_COOKIE["adminer_permanent"]) as $val) {
		list($key) = explode(":", $val);
		$permanent[$key] = $val;
	}
}

function add_invalid_login(): void {
	$base = get_temp_dir() . "/adminer.invalid";
	// adminer.invalid may not be writable by us, try the files with random suffixes
	foreach (glob("$base*") ?: array($base) as $filename) {
		$fp = file_open_lock($filename);
		if ($fp) {
			break;
		}
	}
	if (!$fp) {
		$fp = file_open_lock("$base-" . rand_string());
	}
	if (!$fp) {
		return;
	}
	$invalids = unserialize(stream_get_contents($fp));
	$time = time();
	if ($invalids) {
		foreach ($invalids as $ip => $val) {
			if ($val[0] < $time) {
				unset($invalids[$ip]);
			}
		}
	}
	$invalid = &$invalids[adminer()->bruteForceKey()];
	if (!$invalid) {
		$invalid = array($time + 30*60, 0); // active for 30 minutes
	}
	$invalid[1]++;
	file_write_unlock($fp, serialize($invalids));
}

/** @param string[] $permanent */
function check_invalid_login(array &$permanent): void {
	$invalids = array();
	foreach (glob(get_temp_dir() . "/adminer.invalid*") as $filename) {
		$fp = file_open_lock($filename);
		if ($fp) {
			$invalids = unserialize(stream_get_contents($fp));
			file_unlock($fp);
			break;
		}
	}
	/** @var array{int, int} */
	$invalid = idx($invalids, adminer()->bruteForceKey(), array());
	$next_attempt = ($invalid[1] > 29 ? $invalid[0] - time() : 0); // allow 30 invalid attempts
	if ($next_attempt > 0) { //! do the same with permanent login
		auth_error(lang(88, ceil($next_attempt / 60)), $permanent);
	}
}

$auth = $_POST["auth"];
if ($auth) {
	session_regenerate_id(); // defense against session fixation
	$vendor = $auth["driver"];
	$server = $auth["server"];
	$username = $auth["username"];
	$password = (string) $auth["password"];
	$db = $auth["db"];
	set_password($vendor, $server, $username, $password);
	$_SESSION["db"][$vendor][$server][$username][$db] = true;
	if ($auth["permanent"]) {
		$key = implode("-", array_map('base64_encode', array($vendor, $server, $username, $db)));
		$private = adminer()->permanentLogin(true);
		$permanent[$key] = "$key:" . base64_encode($private ? encrypt_string($password, $private) : "");
		cookie("adminer_permanent", implode(" ", $permanent));
	}
	if (
		count($_POST) == 1 // 1 - auth
		|| DRIVER != $vendor
		|| SERVER != $server
		|| $_GET["username"] !== $username // "0" == "00"
		|| DB != $db
	) {
		redirect(auth_url($vendor, $server, $username, $db));
	}

} elseif ($_POST["logout"] && (!$_SESSION["token"] || verify_token())) {
	foreach (array("pwds", "db", "dbs", "queries") as $key) {
		set_session($key, null);
	}
	unset_permanent($permanent);
	redirect(substr(preg_replace('~\b(username|db|ns)=[^&]*&~', '', ME), 0, -1), lang(89) . ' ' . lang(90));

} elseif ($permanent && !$_SESSION["pwds"]) {
	session_regenerate_id();
	$private = adminer()->permanentLogin();
	foreach ($permanent as $key => $val) {
		list(, $cipher) = explode(":", $val);
		list($vendor, $server, $username, $db) = array_map('base64_decode', explode("-", $key));
		set_password($vendor, $server, $username, decrypt_string(base64_decode($cipher), $private));
		$_SESSION["db"][$vendor][$server][$username][$db] = true;
	}
}

/** Remove credentials from permanent login
* @param string[] $permanent
*/
function unset_permanent(array &$permanent): void {
	foreach ($permanent as $key => $val) {
		list($vendor, $server, $username, $db) = array_map('base64_decode', explode("-", $key));
		if ($vendor == DRIVER && $server == SERVER && $username == $_GET["username"] && $db == DB) {
			unset($permanent[$key]);
		}
	}
	cookie("adminer_permanent", implode(" ", $permanent));
}

/** Render an error message and a login form
* @param string $error plain text
* @param string[] $permanent
* @return never
*/
function auth_error(string $error, array &$permanent) {
	$session_name = session_name();
	if (isset($_GET["username"])) {
		header("HTTP/1.1 403 Forbidden"); // 401 requires sending WWW-Authenticate header
		if (($_COOKIE[$session_name] || $_GET[$session_name]) && !$_SESSION["token"]) {
			$error = lang(91);
		} else {
			restart_session();
			add_invalid_login();
			$password = get_password();
			if ($password !== null) {
				if ($password === false) {
					$error .= ($error ? '<br>' : '') . lang(92, target_blank(), '<code>permanentLogin()</code>');
				}
				set_password(DRIVER, SERVER, $_GET["username"], null);
			}
			unset_permanent($permanent);
		}
	}
	if (!$_COOKIE[$session_name] && $_GET[$session_name] && ini_bool("session.use_only_cookies")) {
		$error = lang(93);
	}
	$params = session_get_cookie_params();
	cookie("adminer_key", ($_COOKIE["adminer_key"] ?: rand_string()), $params["lifetime"]);
	if (!$_SESSION["token"]) {
		$_SESSION["token"] = rand(1, 1e6); // this is for next attempt
	}
	page_header(lang(38), $error, null);
	echo "<form action='' method='post'>\n";
	echo "<div>";
	if (hidden_fields($_POST, array("auth"))) { // expired session
		echo "<p class='message'>" . lang(94) . "\n";
	}
	echo "</div>\n";
	adminer()->loginForm();
	echo "</form>\n";
	page_footer("auth");
	exit;
}

if (isset($_GET["username"]) && !class_exists('Adminer\Db')) {
	unset($_SESSION["pwds"][DRIVER]);
	unset_permanent($permanent);
	page_header(lang(95), lang(96, implode(", ", Driver::$extensions)), false);
	page_footer("auth");
	exit;
}

$connection = '';
if (isset($_GET["username"]) && is_string(get_password())) {
	list(, $port) = host_port(SERVER);
	if (preg_match('~^\s*([-+]?\d+)~', $port, $match) && ($match[1] < 1024 || $match[1] > 65535)) { // is_numeric('80#') would still connect to port 80
		auth_error(lang(97), $permanent);
	}
	check_invalid_login($permanent);
	$credentials = adminer()->credentials();
	$connection = Driver::connect($credentials[0], $credentials[1], $credentials[2]);
	if (is_object($connection)) {
		Db::$instance = $connection;
		Driver::$instance = new Driver($connection);
		if ($connection->flavor) {
			save_settings(array("vendor-" . DRIVER . "-" . SERVER => get_driver(DRIVER)));
		}
	}
}

$login = null;
if (!is_object($connection) || ($login = adminer()->login($_GET["username"], get_password())) !== true) {
	$error = (is_string($connection) ? nl_br(h($connection)) : (is_string($login) ? $login : lang(98)))
		. (preg_match('~^ | $~', get_password()) ? '<br>' . lang(99) : '');
	auth_error($error, $permanent);
}

if ($_POST["logout"] && $_SESSION["token"] && !verify_token()) {
	page_header(lang(87), lang(100));
	page_footer("db");
	exit;
}

if (!$_SESSION["token"]) {
	$_SESSION["token"] = rand(1, 1e6); // defense against cross-site request forgery
}
stop_session(true);
if ($auth && $_POST["token"]) {
	$_POST["token"] = get_token(); // reset token after explicit login
}

$error = ''; ///< @var string
if ($_POST) {
	if (!verify_token()) {
		$ini = "max_input_vars";
		$max_vars = ini_get($ini);
		if (extension_loaded("suhosin")) {
			foreach (array("suhosin.request.max_vars", "suhosin.post.max_vars") as $key) {
				$val = ini_get($key);
				if ($val && (!$max_vars || $val < $max_vars)) {
					$ini = $key;
					$max_vars = $val;
				}
			}
		}
		$error = (!$_POST["token"] && $max_vars
			? lang(101, "'$ini'")
			: lang(100) . ' ' . lang(102)
		);
	}

} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
	// posted form with no data means that post_max_size exceeded because Adminer always sends token at least
	$error = lang(103, "'post_max_size'");
	if (isset($_GET["sql"])) {
		$error .= ' ' . lang(104);
	}
}

?>
<?php
// This file is not used in Adminer Editor.

/** Print select result
* @param Result $result
* @param string[] $orgtables
* @param int|numeric-string $limit
* @return string[] $orgtables
*/
function print_select_result($result, ?Db $connection2 = null, array $orgtables = array(), $limit = 0): array {
	$links = array(); // colno => orgtable - create links from these columns
	$indexes = array(); // orgtable => array(column => colno) - primary keys
	$columns = array(); // orgtable => array(column => ) - not selected columns in primary key
	$blobs = array(); // colno => bool - display bytes for blobs
	$types = array(); // colno => type - display char in <code>
	$return = array(); // table => orgtable - mapping to use in EXPLAIN
	for ($i=0; (!$limit || $i < $limit) && ($row = $result->fetch_row()); $i++) {
		if (!$i) {
			echo "<div class='scrollable'>\n";
			echo "<table class='nowrap odds'>\n";
			echo "<thead><tr>";
			for ($j=0; $j < count($row); $j++) {
				$field = $result->fetch_field();
				$name = $field->name;
				$orgtable = (isset($field->orgtable) ? $field->orgtable : "");
				$orgname = (isset($field->orgname) ? $field->orgname : $name);
				if ($orgtables && JUSH == "sql") { // MySQL EXPLAIN
					$links[$j] = ($name == "table" ? "table=" : ($name == "possible_keys" ? "indexes=" : null));
				} elseif ($orgtable != "") {
					if (isset($field->table)) {
						$return[$field->table] = $orgtable;
					}
					if (!isset($indexes[$orgtable])) {
						// find primary key in each table
						$indexes[$orgtable] = array();
						foreach (indexes($orgtable, $connection2) as $index) {
							if ($index["type"] == "PRIMARY") {
								$indexes[$orgtable] = array_flip($index["columns"]);
								break;
							}
						}
						$columns[$orgtable] = $indexes[$orgtable];
					}
					if (isset($columns[$orgtable][$orgname])) {
						unset($columns[$orgtable][$orgname]);
						$indexes[$orgtable][$orgname] = $j;
						$links[$j] = $orgtable;
					}
				}
				if ($field->charsetnr == 63) { // 63 - binary
					$blobs[$j] = true;
				}
				$types[$j] = $field->type;
				echo "<th" . ($orgtable != "" || $field->name != $orgname ? " title='" . h(($orgtable != "" ? "$orgtable." : "") . $orgname) . "'" : "") . ">" . h($name)
					. ($orgtables ? doc_link(array(
						'sql' => "explain-output.html#explain_" . strtolower($name),
						'mariadb' => "explain/#the-columns-in-explain-select",
					)) : "")
				;
			}
			echo "</thead>\n";
		}
		echo "<tr>";
		foreach ($row as $key => $val) {
			$link = "";
			if (isset($links[$key]) && !$columns[$links[$key]]) {
				if ($orgtables && JUSH == "sql") { // MySQL EXPLAIN
					$table = $row[array_search("table=", $links)];
					$link = ME . $links[$key] . urlencode($orgtables[$table] != "" ? $orgtables[$table] : $table);
				} else {
					$link = ME . "edit=" . urlencode($links[$key]);
					foreach ($indexes[$links[$key]] as $col => $j) {
						if ($row[$j] === null) {
							$link = "";
							break;
						}
						$link .= "&where" . urlencode("[" . bracket_escape($col) . "]") . "=" . urlencode($row[$j]);
					}
				}
			} elseif (is_url($val)) {
				$link = $val;
			}
			if ($val === null) {
				$val = "<i>NULL</i>";
			} elseif ($blobs[$key] && !is_utf8($val)) {
				$val = "<i>" . lang(47, strlen($val)) . "</i>"; //! link to download
			} else {
				$val = h($val);
				if ($types[$key] == 254) { // 254 - char
					$val = "<code>$val</code>";
				}
			}
			if ($link) {
				$val = "<a href='" . h($link) . "'" . (is_url($link) ? target_blank() : '') . ">$val</a>";
			}
			// https://dev.mysql.com/doc/dev/mysql-server/latest/field__types_8h.html
			echo "<td" . ($types[$key] <= 9 || $types[$key] == 246 ? " class='number'" : "") . ">$val";
		}
	}
	echo ($i ? "</table>\n</div>" : "<p class='message'>" . lang(14)) . "\n";
	return $return;
}

/** Get referencable tables with single column primary key except self
* @return array<string, Field> [$table_name => $field]
*/
function referencable_primary(string $self): array {
	$return = array(); // table_name => field
	foreach (table_status('', true) as $table_name => $table) {
		if ($table_name != $self && fk_support($table)) {
			foreach (fields($table_name) as $field) {
				if ($field["primary"]) {
					if ($return[$table_name]) { // multi column primary key
						unset($return[$table_name]);
						break;
					}
					$return[$table_name] = $field;
				}
			}
		}
	}
	return $return;
}

/** Print SQL <textarea> tag
* @param string|list<array{string}> $value
*/
function textarea(string $name, $value, int $rows = 10, int $cols = 80): void {
	echo "<textarea name='" . h($name) . "' rows='$rows' cols='$cols' class='sqlarea jush-" . JUSH . "' spellcheck='false' wrap='off'>";
	if (is_array($value)) {
		foreach ($value as $val) { // not implode() to save memory
			echo h($val[0]) . "\n\n\n"; // $val == array($query, $time, $elapsed)
		}
	} else {
		echo h($value);
	}
	echo "</textarea>";
}

/** Generate HTML <select> or <input> if $options are empty
* @param string[] $options
*/
function select_input(string $attrs, array $options, ?string $value = "", string $onchange = "", string $placeholder = ""): string {
	$tag = ($options ? "select" : "input");
	return "<$tag$attrs" . ($options
		? "><option value=''>$placeholder" . optionlist($options, $value, true) . "</select>"
		: " size='10' value='" . h($value) . "' placeholder='$placeholder'>"
	) . ($onchange ? script("qsl('$tag').onchange = $onchange;", "") : ""); //! use oninput for input
}

/** Print one row in JSON object
* @param string $key or "" to close the object
* @param string|int $val
*/
function json_row(string $key, $val = null, bool $escape = true): void {
	static $first = true;
	if ($first) {
		echo "{";
	}
	if ($key != "") {
		echo ($first ? "" : ",") . "\n\t\"" . addcslashes($key, "\r\n\t\"\\/") . '": ' . ($val !== null ? ($escape ? '"' . addcslashes($val, "\r\n\"\\/") . '"' : $val) : 'null');
		$first = false;
	} else {
		echo "\n}\n";
		$first = true;
	}
}

/** Print table columns for type edit
* @param Field $field
* @param list<string> $collations
* @param string[] $foreign_keys
* @param list<string> $extra_types extra types to prepend
*/
function edit_type(string $key, array $field, array $collations, array $foreign_keys = array(), array $extra_types = array()): void {
	$type = $field["type"];
	echo "<td><select name='" . h($key) . "[type]' class='type' aria-labelledby='label-type'>";
	if ($type && !array_key_exists($type, driver()->types()) && !isset($foreign_keys[$type]) && !in_array($type, $extra_types)) {
		$extra_types[] = $type;
	}
	$structured_types = driver()->structuredTypes();
	if ($foreign_keys) {
		$structured_types[lang(105)] = $foreign_keys;
	}
	echo optionlist(array_merge($extra_types, $structured_types), $type);
	echo "</select><td>";
	echo "<input name='" . h($key) . "[length]' value='" . h($field["length"]) . "' size='3'"
		. (!$field["length"] && preg_match('~var(char|binary)$~', $type) ? " class='required'" : "") //! type="number" with enabled JavaScript
		. " aria-labelledby='label-length'>";
	echo "<td class='options'>";
	echo ($collations
		? "<input list='collations' name='" . h($key) . "[collation]'" . (preg_match('~(char|text|enum|set)$~', $type) ? "" : " class='hidden'") . " value='" . h($field["collation"]) . "' placeholder='(" . lang(106) . ")'>"
		: ''
	);
	echo (driver()->unsigned ? "<select name='" . h($key) . "[unsigned]'" . (!$type || preg_match(number_type(), $type) ? "" : " class='hidden'") . '><option>' . optionlist(driver()->unsigned, $field["unsigned"]) . '</select>' : '');
	echo (isset($field['on_update']) ? "<select name='" . h($key) . "[on_update]'" . (preg_match('~timestamp|datetime~', $type) ? "" : " class='hidden'") . '>'
		. optionlist(array("" => "(" . lang(107) . ")", "CURRENT_TIMESTAMP"), (preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"]) ? "CURRENT_TIMESTAMP" : $field["on_update"]))
		. '</select>' : ''
	);
	echo ($foreign_keys
		? "<select name='" . h($key) . "[on_delete]'" . (preg_match("~`~", $type) ? "" : " class='hidden'") . "><option value=''>(" . lang(108) . ")" . optionlist(explode("|", driver()->onActions), $field["on_delete"]) . "</select> "
		: " " // space for IE
	);
}

/** Filter length value including enums */
function process_length(?string $length): string {
	$enum_length = driver()->enumLength;
	return (preg_match("~^\\s*\\(?\\s*$enum_length(?:\\s*,\\s*$enum_length)*+\\s*\\)?\\s*\$~", $length) && preg_match_all("~$enum_length~", $length, $matches)
		? "(" . implode(",", $matches[0]) . ")"
		: preg_replace('~^[0-9].*~', '(\0)', preg_replace('~[^-0-9,+()[\]]~', '', $length))
	);
}

/** Create SQL string from field type
* @param FieldType $field
*/
function process_type(array $field, string $collate = "COLLATE"): string {
	return " $field[type]"
		. process_length($field["length"])
		. (preg_match(number_type(), $field["type"]) && in_array($field["unsigned"], driver()->unsigned) ? " $field[unsigned]" : "")
		. (preg_match('~char|text|enum|set~', $field["type"]) && $field["collation"] ? " $collate " . (JUSH == "mssql" ? $field["collation"] : q($field["collation"])) : "")
	;
}

/** Create SQL string from field
* @param Field $field basic field information
* @param Field $type_field information about field type
* @return list<string> ["field", "type", "NULL", "DEFAULT", "ON UPDATE", "COMMENT", "AUTO_INCREMENT"]
*/
function process_field(array $field, array $type_field): array {
	// MariaDB exports CURRENT_TIMESTAMP as a function.
	if ($field["on_update"]) {
		$field["on_update"] = str_ireplace("current_timestamp()", "CURRENT_TIMESTAMP", $field["on_update"]);
	}
	return array(
		idf_escape(trim($field["field"])),
		process_type($type_field),
		($field["null"] ? " NULL" : " NOT NULL"), // NULL for timestamp
		default_value($field),
		(preg_match('~timestamp|datetime~', $field["type"]) && $field["on_update"] ? " ON UPDATE $field[on_update]" : ""),
		(support("comment") && $field["comment"] != "" ? " COMMENT " . q($field["comment"]) : ""),
		($field["auto_increment"] ? auto_increment() : null),
	);
}

/** Get default value clause
* @param Field $field
*/
function default_value(array $field): string {
	$default = $field["default"];
	$generated = $field["generated"];
	return ($default === null ? "" : (in_array($generated, driver()->generated)
		? (JUSH == "mssql" ? " AS ($default)" . ($generated == "VIRTUAL" ? "" : " $generated") . "" : " GENERATED ALWAYS AS ($default) $generated")
		: " DEFAULT " . (!preg_match('~^GENERATED ~i', $default) && (preg_match('~char|binary|text|json|enum|set~', $field["type"]) || preg_match('~^(?![a-z])~i', $default))
			? (JUSH == "sql" && preg_match('~text|json~', $field["type"]) ? "(" . q($default) . ")" : q($default)) // MySQL requires () around default value of text column
			: str_ireplace("current_timestamp()", "CURRENT_TIMESTAMP", (JUSH == "sqlite" ? "($default)" : $default))
		)
	));
}

/** Get type class to use in CSS
* @return string|void class=''
*/
function type_class(string $type) {
	foreach (
		array(
			'char' => 'text',
			'date' => 'time|year',
			'binary' => 'blob',
			'enum' => 'set',
		) as $key => $val
	) {
		if (preg_match("~$key|$val~", $type)) {
			return " class='$key'";
		}
	}
}

/** Print table interior for fields editing
* @param (Field|RoutineField)[] $fields
* @param list<string> $collations
* @param 'TABLE'|'PROCEDURE' $type
* @param string[] $foreign_keys
*/
function edit_fields(array $fields, array $collations, $type = "TABLE", array $foreign_keys = array()): void {
	$fields = array_values($fields);
	$default_class = (($_POST ? $_POST["defaults"] : get_setting("defaults")) ? "" : " class='hidden'");
	$comment_class = (($_POST ? $_POST["comments"] : get_setting("comments")) ? "" : " class='hidden'");
	echo "<thead><tr>\n";
	echo ($type == "PROCEDURE" ? "<td>" : "");
	echo "<th id='label-name'>" . ($type == "TABLE" ? lang(109) : lang(110));
	echo "<td id='label-type'>" . lang(49) . "<textarea id='enum-edit' rows='4' cols='12' wrap='off' style='display: none;'></textarea>" . script("qs('#enum-edit').onblur = editingLengthBlur;");
	echo "<td id='label-length'>" . lang(111);
	echo "<td>" . lang(112); // no label required, options have their own label
	if ($type == "TABLE") {
		echo "<td id='label-null'>NULL\n";
		echo "<td><input type='radio' name='auto_increment_col' value=''><abbr id='label-ai' title='" . lang(51) . "'>AI</abbr>";
		echo doc_link(array(
			'sql' => "example-auto-increment.html",
			'mariadb' => "auto_increment/",
			'sqlite' => "autoinc.html",
			'pgsql' => "datatype-numeric.html#DATATYPE-SERIAL",
			'mssql' => "t-sql/statements/create-table-transact-sql-identity-property",
		));
		echo "<td id='label-default'$default_class>" . lang(52);
		echo (support("comment") ? "<td id='label-comment'$comment_class>" . lang(50) : "");
	}
	echo "<td>" . icon("plus", "add[" . (support("move_col") ? 0 : count($fields)) . "]", "+", lang(113));
	echo "</thead>\n<tbody>\n";
	echo script("mixin(qsl('tbody'), {onclick: editingClick, onkeydown: editingKeydown, oninput: editingInput});");
	foreach ($fields as $i => $field) {
		$i++;
		$orig = $field[($_POST ? "orig" : "field")];
		$display = (isset($_POST["add"][$i-1]) || (isset($field["field"]) && !idx($_POST["drop_col"], $i))) && (support("drop_col") || $orig == "");
		echo "<tr" . ($display ? "" : " style='display: none;'") . ">\n";
		echo ($type == "PROCEDURE" ? "<td>" . html_select("fields[$i][inout]", explode("|", driver()->inout), $field["inout"]) : "") . "<th>";
		if ($display) {
			echo "<input name='fields[$i][field]' value='" . h($field["field"]) . "' data-maxlength='64' autocapitalize='off' aria-labelledby='label-name'" . (isset($_POST["add"][$i-1]) ? " autofocus" : "") . ">";
		}
		echo input_hidden("fields[$i][orig]", $orig);
		edit_type("fields[$i]", $field, $collations, $foreign_keys);
		if ($type == "TABLE") {
			echo "<td>" . checkbox("fields[$i][null]", 1, $field["null"], "", "", "block", "label-null");
			echo "<td><label class='block'><input type='radio' name='auto_increment_col' value='$i'" . ($field["auto_increment"] ? " checked" : "") . " aria-labelledby='label-ai'></label>";
			echo "<td$default_class>" . (driver()->generated
				? html_select("fields[$i][generated]", array_merge(array("", "DEFAULT"), driver()->generated), $field["generated"]) . " "
				: checkbox("fields[$i][generated]", 1, $field["generated"], "", "", "", "label-default")
			);
			echo "<input name='fields[$i][default]' value='" . h($field["default"]) . "' aria-labelledby='label-default'>";
			echo (support("comment") ? "<td$comment_class><input name='fields[$i][comment]' value='" . h($field["comment"]) . "' data-maxlength='" . (min_version(5.5) ? 1024 : 255) . "' aria-labelledby='label-comment'>" : "");
		}
		echo "<td>";
		echo (support("move_col") ?
			icon("plus", "add[$i]", "+", lang(113)) . " "
			. icon("up", "up[$i]", "â†‘", lang(114)) . " "
			. icon("down", "down[$i]", "â†“", lang(115)) . " "
		: "");
		echo ($orig == "" || support("drop_col") ? icon("cross", "drop_col[$i]", "x", lang(116)) : "");
	}
}

/** Move fields up and down or add field
* @param Field[] $fields
*/
function process_fields(array &$fields): bool {
	$offset = 0;
	if ($_POST["up"]) {
		$last = 0;
		foreach ($fields as $key => $field) {
			if (key($_POST["up"]) == $key) {
				unset($fields[$key]);
				array_splice($fields, $last, 0, array($field));
				break;
			}
			if (isset($field["field"])) {
				$last = $offset;
			}
			$offset++;
		}
	} elseif ($_POST["down"]) {
		$found = false;
		foreach ($fields as $key => $field) {
			if (isset($field["field"]) && $found) {
				unset($fields[key($_POST["down"])]);
				array_splice($fields, $offset, 0, array($found));
				break;
			}
			if (key($_POST["down"]) == $key) {
				$found = $field;
			}
			$offset++;
		}
	} elseif ($_POST["add"]) {
		$fields = array_values($fields);
		array_splice($fields, key($_POST["add"]), 0, array(array()));
	} elseif (!$_POST["drop_col"]) {
		return false;
	}
	return true;
}

/** Callback used in routine()
* @param list<string> $match
*/
function normalize_enum(array $match): string {
	$val = $match[0];
	return "'" . str_replace("'", "''", addcslashes(stripcslashes(str_replace($val[0] . $val[0], $val[0], substr($val, 1, -1))), '\\')) . "'";
}

/** Issue grant or revoke commands
* @param 'GRANT'|'REVOKE' $grant
* @param list<string> $privileges
* @return Result|bool
*/
function grant(string $grant, array $privileges, ?string $columns, string $on) {
	if (!$privileges) {
		return true;
	}
	if ($privileges == array("ALL PRIVILEGES", "GRANT OPTION")) {
		// can't be granted or revoked together
		return ($grant == "GRANT"
			? queries("$grant ALL PRIVILEGES$on WITH GRANT OPTION")
			: queries("$grant ALL PRIVILEGES$on") && queries("$grant GRANT OPTION$on")
		);
	}
	return queries("$grant " . preg_replace('~(GRANT OPTION)\([^)]*\)~', '\1', implode("$columns, ", $privileges) . $columns) . $on);
}

/** Drop old object and create a new one
* @param string $drop drop old object query
* @param string $create create new object query
* @param string $drop_created drop new object query
* @param string $test create test object query
* @param string $drop_test drop test object query
* @return void redirect on success
*/
function drop_create(string $drop, string $create, string $drop_created, string $test, string $drop_test, string $location, string $message_drop, string $message_alter, string $message_create, string $old_name, string $new_name): void {
	if ($_POST["drop"]) {
		query_redirect($drop, $location, $message_drop);
	} elseif ($old_name == "") {
		query_redirect($create, $location, $message_create);
	} elseif ($old_name != $new_name) {
		$created = queries($create);
		queries_redirect($location, $message_alter, $created && queries($drop));
		if ($created) {
			queries($drop_created);
		}
	} else {
		queries_redirect(
			$location,
			$message_alter,
			queries($test) && queries($drop_test) && queries($drop) && queries($create)
		);
	}
}

/** Generate SQL query for creating trigger
* @param Trigger $row
*/
function create_trigger(string $on, array $row): string {
	$timing_event = " $row[Timing] $row[Event]" . (preg_match('~ OF~', $row["Event"]) ? " $row[Of]" : ""); // SQL injection
	return "CREATE TRIGGER "
		. idf_escape($row["Trigger"])
		. (JUSH == "mssql" ? $on . $timing_event : $timing_event . $on)
		. rtrim(" $row[Type]\n$row[Statement]", ";")
		. ";"
	;
}

/** Generate SQL query for creating routine
* @param 'PROCEDURE'|'FUNCTION' $routine
* @param Routine $row
*/
function create_routine($routine, array $row): string {
	$set = array();
	$fields = (array) $row["fields"];
	ksort($fields); // enforce fields order
	foreach ($fields as $field) {
		if ($field["field"] != "") {
			$set[] = (preg_match("~^(" . driver()->inout . ")\$~", $field["inout"]) ? "$field[inout] " : "") . idf_escape($field["field"]) . process_type($field, "CHARACTER SET");
		}
	}
	$definition = rtrim($row["definition"], ";");
	return "CREATE $routine "
		. idf_escape(trim($row["name"]))
		. " (" . implode(", ", $set) . ")"
		. ($routine == "FUNCTION" ? " RETURNS" . process_type($row["returns"], "CHARACTER SET") : "")
		. ($row["language"] ? " LANGUAGE $row[language]" : "")
		. (JUSH == "pgsql" ? " AS " . q($definition) : "\n$definition;")
	;
}

/** Remove current user definer from SQL command */
function remove_definer(string $query): string {
	return preg_replace('~^([A-Z =]+) DEFINER=`' . preg_replace('~@(.*)~', '`@`(%|\1)', logged_user()) . '`~', '\1', $query); //! proper escaping of user
}

/** Format foreign key to use in SQL query
* @param ForeignKey $foreign_key
*/
function format_foreign_key(array $foreign_key): string {
	$db = $foreign_key["db"];
	$ns = $foreign_key["ns"];
	return " FOREIGN KEY (" . implode(", ", array_map('Adminer\idf_escape', $foreign_key["source"])) . ") REFERENCES "
		. ($db != "" && $db != $_GET["db"] ? idf_escape($db) . "." : "")
		. ($ns != "" && $ns != $_GET["ns"] ? idf_escape($ns) . "." : "")
		. idf_escape($foreign_key["table"])
		. " (" . implode(", ", array_map('Adminer\idf_escape', $foreign_key["target"])) . ")" //! reuse $name - check in older MySQL versions
		. (preg_match("~^(" . driver()->onActions . ")\$~", $foreign_key["on_delete"]) ? " ON DELETE $foreign_key[on_delete]" : "")
		. (preg_match("~^(" . driver()->onActions . ")\$~", $foreign_key["on_update"]) ? " ON UPDATE $foreign_key[on_update]" : "")
	;
}

/** Add a file to TAR
* @param TmpFile $tmp_file
* @return void prints the output
*/
function tar_file(string $filename, $tmp_file): void {
	$return = pack("a100a8a8a8a12a12", $filename, 644, 0, 0, decoct($tmp_file->size), decoct(time()));
	$checksum = 8*32; // space for checksum itself
	for ($i=0; $i < strlen($return); $i++) {
		$checksum += ord($return[$i]);
	}
	$return .= sprintf("%06o", $checksum) . "\0 ";
	echo $return;
	echo str_repeat("\0", 512 - strlen($return));
	$tmp_file->send();
	echo str_repeat("\0", 511 - ($tmp_file->size + 511) % 512);
}

/** Create link to database documentation
* @param string[] $paths JUSH => $path
* @param string $text HTML code
* @return string HTML code
*/
function doc_link(array $paths, string $text = "<sup>?</sup>"): string {
	$server_info = connection()->server_info;
	$version = preg_replace('~^(\d\.?\d).*~s', '\1', $server_info); // two most significant digits
	$urls = array(
		'sql' => "https://dev.mysql.com/doc/refman/$version/en/",
		'sqlite' => "https://www.sqlite.org/",
		'pgsql' => "https://www.postgresql.org/docs/" . (connection()->flavor == 'cockroach' ? "current" : $version) . "/",
		'mssql' => "https://learn.microsoft.com/en-us/sql/",
		'oracle' => "https://www.oracle.com/pls/topic/lookup?ctx=db" . preg_replace('~^.* (\d+)\.(\d+)\.\d+\.\d+\.\d+.*~s', '\1\2', $server_info) . "&id=",
	);
	if (connection()->flavor == 'maria') {
		$urls['sql'] = "https://mariadb.com/kb/en/";
		$paths['sql'] = (isset($paths['mariadb']) ? $paths['mariadb'] : str_replace(".html", "/", $paths['sql']));
	}
	return ($paths[JUSH] ? "<a href='" . h($urls[JUSH] . $paths[JUSH] . (JUSH == 'mssql' ? "?view=sql-server-ver$version" : "")) . "'" . target_blank() . ">$text</a>" : "");
}

/** Compute size of database
* @return string formatted
*/
function db_size(string $db): string {
	if (!connection()->select_db($db)) {
		return "?";
	}
	$return = 0;
	foreach (table_status() as $table_status) {
		$return += $table_status["Data_length"] + $table_status["Index_length"];
	}
	return format_number($return);
}

/** Print SET NAMES if utf8mb4 might be needed */
function set_utf8mb4(string $create): void {
	static $set = false;
	if (!$set && preg_match('~\butf8mb4~i', $create)) { // possible false positive
		$set = true;
		echo "SET NAMES " . charset(connection()) . ";\n\n";
	}
}

?>
<?php
if (isset($_GET["status"])) {
	$_GET["variables"] = $_GET["status"];
}
if (isset($_GET["import"])) {
	$_GET["sql"] = $_GET["import"];
}

if (
	!(DB != ""
		? connection()->select_db(DB)
		: isset($_GET["sql"]) || isset($_GET["dump"]) || isset($_GET["database"]) || isset($_GET["processlist"]) || isset($_GET["privileges"]) || isset($_GET["user"]) || isset($_GET["variables"])
			|| $_GET["script"] == "connect" || $_GET["script"] == "kill"
	)
) {
	if (DB != "" || $_GET["refresh"]) {
		restart_session();
		set_session("dbs", null);
	}
	if (DB != "") {
		header("HTTP/1.1 404 Not Found");
		page_header(lang(37) . ": " . h(DB), lang(117), true);
	} else {
		if ($_POST["db"] && !$error) {
			queries_redirect(substr(ME, 0, -1), lang(118), drop_databases($_POST["db"]));
		}

		page_header(lang(119), $error, false);
		echo "<p class='links'>\n";
		foreach (
			array(
				'database' => lang(120),
				'privileges' => lang(71),
				'processlist' => lang(121),
				'variables' => lang(122),
				'status' => lang(123),
			) as $key => $val
		) {
			if (support($key)) {
				echo "<a href='" . h(ME) . "$key='>$val</a>\n";
			}
		}
		echo "<p>" . lang(124, get_driver(DRIVER), "<b>" . h(connection()->server_info) . "</b>", "<b>" . connection()->extension . "</b>") . "\n";
		echo "<p>" . lang(125, "<b>" . h(logged_user()) . "</b>") . "\n";

		$databases = adminer()->databases();
		if ($databases) {
			$scheme = support("scheme");
			$collations = collations();
			echo "<form action='' method='post'>\n";
			echo "<table class='checkable odds'>\n";
			echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");
			echo "<thead><tr>"
				. (support("database") ? "<td>" : "")
				. "<th>" . lang(37) . (get_session("dbs") !== null ? " - <a href='" . h(ME) . "refresh=1'>" . lang(126) . "</a>" : "")
				. "<td>" . lang(127)
				. "<td>" . lang(128)
				. "<td>" . lang(129) . " - <a href='" . h(ME) . "dbsize=1'>" . lang(130) . "</a>" . script("qsl('a').onclick = partial(ajaxSetHtml, '" . js_escape(ME) . "script=connect');", "")
				. "</thead>\n"
			;

			$databases = ($_GET["dbsize"] ? count_tables($databases) : array_flip($databases));
			foreach ($databases as $db => $tables) {
				$root = h(ME) . "db=" . urlencode($db);
				$id = h("Db-" . $db);
				echo "<tr>" . (support("database") ? "<td>" . checkbox("db[]", $db, in_array($db, (array) $_POST["db"]), "", "", "", $id) : "");
				echo "<th><a href='$root' id='$id'>" . h($db) . "</a>";
				$collation = h(db_collation($db, $collations));
				echo "<td>" . (support("database") ? "<a href='$root" . ($scheme ? "&amp;ns=" : "") . "&amp;database=' title='" . lang(67) . "'>$collation</a>" : $collation);
				echo "<td align='right'><a href='$root&amp;schema=' id='tables-" . h($db) . "' title='" . lang(70) . "'>" . ($_GET["dbsize"] ? $tables : "?") . "</a>";
				echo "<td align='right' id='size-" . h($db) . "'>" . ($_GET["dbsize"] ? db_size($db) : "?");
				echo "\n";
			}

			echo "</table>\n";
			echo (support("database")
				? "<div class='footer'><div>\n"
					. "<fieldset><legend>" . lang(131) . " <span id='selected'></span></legend><div>\n"
					. input_hidden("all") . script("qsl('input').onclick = function () { selectCount('selected', formChecked(this, /^db/)); };") // used by trCheck()
					. "<input type='submit' name='drop' value='" . lang(132) . "'>" . confirm() . "\n"
					. "</div></fieldset>\n"
					. "</div></div>\n"
				: ""
			);
			echo input_token();
			echo "</form>\n";
			echo script("tableCheck();");
		}

		if (!empty(adminer()->plugins)) {
			echo "<div class='plugins'>\n";
			echo "<h3>" . lang(133) . "</h3>\n<ul>\n";
			foreach (adminer()->plugins as $plugin) {
				$description = (method_exists($plugin, 'description') ? $plugin->description() : "");
				if (!$description) {
					$reflection = new \ReflectionObject($plugin);
					if (preg_match('~^/[\s*]+(.+)~', $reflection->getDocComment(), $match)) {
						$description = $match[1];
					}
				}
				$screenshot = (method_exists($plugin, 'screenshot') ? $plugin->screenshot() : "");
				echo "<li><b>" . get_class($plugin) . "</b>"
					. h($description ? ": $description" : "")
					. ($screenshot ? " (<a href='" . h($screenshot) . "'" . target_blank() . ">" . lang(134) . "</a>)" : "")
					. "\n"
				;
			}
			echo "</ul>\n";
			adminer()->pluginsLinks();
			echo "</div>\n";
		}
	}

	page_footer("db");
	exit;
}

if (support("scheme")) {
	if (DB != "" && $_GET["ns"] !== "") {
		if (!isset($_GET["ns"])) {
			redirect(preg_replace('~ns=[^&]*&~', '', ME) . "ns=" . get_schema());
		}
		if (!set_schema($_GET["ns"])) {
			header("HTTP/1.1 404 Not Found");
			page_header(lang(79) . ": " . h($_GET["ns"]), lang(135), true);
			page_footer("ns");
			exit;
		}
	}
}


adminer()->afterConnect();

?>
<?php
class TmpFile {
	/** @var resource */ private $handler;
	/** @visibility protected(set) */ public int $size;

	function __construct() {
		$this->handler = tmpfile();
	}

	function write(string $contents): void {
		$this->size += strlen($contents);
		fwrite($this->handler, $contents);
	}

	function send(): void {
		fseek($this->handler, 0);
		fpassthru($this->handler);
		fclose($this->handler);
	}
}


if (isset($_GET["select"]) && ($_POST["edit"] || $_POST["clone"]) && !$_POST["save"]) {
	$_GET["edit"] = $_GET["select"];
}
// this is matched by compile.php
if (isset($_GET["callf"])) {
	$_GET["call"] = $_GET["callf"];
}
if (isset($_GET["function"])) {
	$_GET["procedure"] = $_GET["function"];
}

if (isset($_GET["download"])) {
	?>
<?php
$TABLE = $_GET["download"];
$fields = fields($TABLE);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=" . friendly_url("$TABLE-" . implode("_", $_GET["where"])) . "." . friendly_url($_GET["field"]));
$select = array(idf_escape($_GET["field"]));
$result = driver()->select($TABLE, $select, array(where($_GET, $fields)), $select);
$row = ($result ? $result->fetch_row() : array());
echo driver()->value($row[0], $fields[$_GET["field"]]);
exit; // don't output footer

} elseif (isset($_GET["table"])) {
	?>
<?php
$TABLE = $_GET["table"];
$fields = fields($TABLE);
if (!$fields) {
	$error = error() ?: lang(11);
}
$table_status = table_status1($TABLE);
$name = adminer()->tableName($table_status);

page_header(($fields && is_view($table_status) ? $table_status['Engine'] == 'materialized view' ? lang(136) : lang(137) : lang(138)) . ": " . ($name != "" ? $name : h($TABLE)), $error);

$rights = array();
foreach ($fields as $key => $field) {
	$rights += $field["privileges"];
}
adminer()->selectLinks($table_status, (isset($rights["insert"]) || !support("table") ? "" : null));

$comment = $table_status["Comment"];
if ($comment != "") {
	echo "<p class='nowrap'>" . lang(50) . ": " . h($comment) . "\n";
}

if ($fields) {
	adminer()->tableStructurePrint($fields, $table_status);
}

/** Print links to tables
* @param list<string> $tables
*/
function tables_links(array $tables): void {
	echo "<ul>\n";
	foreach ($tables as $table) {
		echo "<li><a href='" . h(ME . "table=" . urlencode($table)) . "'>" . h($table) . "</a>";
	}
	echo "</ul>\n";
}

$inherits = driver()->inheritsFrom($TABLE);
if ($inherits) {
	echo "<h3>" . lang(139) . "</h3>\n";
	tables_links($inherits);
}

if (support("indexes") && driver()->supportsIndex($table_status)) {
	echo "<h3 id='indexes'>" . lang(140) . "</h3>\n";
	$indexes = indexes($TABLE);
	if ($indexes) {
		adminer()->tableIndexesPrint($indexes, $table_status);
	}
	echo '<p class="links"><a href="' . h(ME) . 'indexes=' . urlencode($TABLE) . '">' . lang(141) . "</a>\n";
}

if (!is_view($table_status)) {
	if (fk_support($table_status)) {
		echo "<h3 id='foreign-keys'>" . lang(105) . "</h3>\n";
		$foreign_keys = foreign_keys($TABLE);
		if ($foreign_keys) {
			echo "<table>\n";
			echo "<thead><tr><th>" . lang(142) . "<td>" . lang(143) . "<td>" . lang(108) . "<td>" . lang(107) . "<td></thead>\n";
			foreach ($foreign_keys as $name => $foreign_key) {
				echo "<tr title='" . h($name) . "'>";
				echo "<th><i>" . implode("</i>, <i>", array_map('Adminer\h', $foreign_key["source"])) . "</i>";
				$link = ($foreign_key["db"] != ""
					? preg_replace('~db=[^&]*~', "db=" . urlencode($foreign_key["db"]), ME)
					: ($foreign_key["ns"] != "" ? preg_replace('~ns=[^&]*~', "ns=" . urlencode($foreign_key["ns"]), ME) : ME)
				);
				echo "<td><a href='" . h($link . "table=" . urlencode($foreign_key["table"])) . "'>"
					. ($foreign_key["db"] != "" && $foreign_key["db"] != DB ? "<b>" . h($foreign_key["db"]) . "</b>." : "")
					. ($foreign_key["ns"] != "" && $foreign_key["ns"] != $_GET["ns"] ? "<b>" . h($foreign_key["ns"]) . "</b>." : "")
					. h($foreign_key["table"])
					. "</a>"
				;
				echo "(<i>" . implode("</i>, <i>", array_map('Adminer\h', $foreign_key["target"])) . "</i>)";
				echo "<td>" . h($foreign_key["on_delete"]);
				echo "<td>" . h($foreign_key["on_update"]);
				echo '<td><a href="' . h(ME . 'foreign=' . urlencode($TABLE) . '&name=' . urlencode($name)) . '">' . lang(144) . '</a>';
				echo "\n";
			}
			echo "</table>\n";
		}
		echo '<p class="links"><a href="' . h(ME) . 'foreign=' . urlencode($TABLE) . '">' . lang(145) . "</a>\n";
	}

	if (support("check")) {
		echo "<h3 id='checks'>" . lang(146) . "</h3>\n";
		$check_constraints = driver()->checkConstraints($TABLE);
		if ($check_constraints) {
			echo "<table>\n";
			foreach ($check_constraints as $key => $val) {
				echo "<tr title='" . h($key) . "'>";
				echo "<td><code class='jush-" . JUSH . "'>" . h($val);
				echo "<td><a href='" . h(ME . 'check=' . urlencode($TABLE) . '&name=' . urlencode($key)) . "'>" . lang(144) . "</a>";
				echo "\n";
			}
			echo "</table>\n";
		}
		echo '<p class="links"><a href="' . h(ME) . 'check=' . urlencode($TABLE) . '">' . lang(147) . "</a>\n";
	}
}

if (support(is_view($table_status) ? "view_trigger" : "trigger")) {
	echo "<h3 id='triggers'>" . lang(148) . "</h3>\n";
	$triggers = triggers($TABLE);
	if ($triggers) {
		echo "<table>\n";
		foreach ($triggers as $key => $val) {
			echo "<tr valign='top'><td>" . h($val[0]) . "<td>" . h($val[1]) . "<th>" . h($key) . "<td><a href='" . h(ME . 'trigger=' . urlencode($TABLE) . '&name=' . urlencode($key)) . "'>" . lang(144) . "</a>\n";
		}
		echo "</table>\n";
	}
	echo '<p class="links"><a href="' . h(ME) . 'trigger=' . urlencode($TABLE) . '">' . lang(149) . "</a>\n";
}

$inherited = driver()->inheritedTables($TABLE);
if ($inherited) {
	echo "<h3 id='partitions'>" . lang(150) . "</h3>\n";
	$partition = driver()->partitionsInfo($TABLE);
	if ($partition) {
		echo "<p><code class='jush-" . JUSH . "'>BY " . h("$partition[partition_by]($partition[partition])") . "</code>\n";
	}
	tables_links($inherited);
}

} elseif (isset($_GET["schema"])) {
	?>
<?php
page_header(lang(70), "", array(), h(DB . ($_GET["ns"] ? ".$_GET[ns]" : "")));

/** @var array{float, float}[] */
$table_pos = array();
$table_pos_js = array();
$SCHEMA = ($_GET["schema"] ?: $_COOKIE["adminer_schema-" . str_replace(".", "_", DB)]); // $_COOKIE["adminer_schema"] was used before 3.2.0 //! ':' in table name
preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~', $SCHEMA, $matches, PREG_SET_ORDER);
foreach ($matches as $i => $match) {
	$table_pos[$match[1]] = array($match[2], $match[3]);
	$table_pos_js[] = "\n\t'" . js_escape($match[1]) . "': [ $match[2], $match[3] ]";
}

$top = 0;
$base_left = -1;
/** @var array{fields:Field[], pos:array{float, float}, references:string[][][]}[] */
$schema = array(); // table => array("fields" => array(name => field), "pos" => array(top, left), "references" => array(table => array(left => array(source, target))))
$referenced = array(); // target_table => array(table => array(left => target_column))
/** @var array<numeric-string, bool> */
$lefts = array();
$all_fields = driver()->allFields();
foreach (table_status('', true) as $table => $table_status) {
	if (is_view($table_status)) {
		continue;
	}
	$pos = 0;
	$schema[$table]["fields"] = array();
	foreach ($all_fields[$table] as $field) {
		$pos += 1.25;
		$field["pos"] = $pos;
		$schema[$table]["fields"][$field["field"]] = $field;
	}
	$schema[$table]["pos"] = ($table_pos[$table] ?: array($top, 0));
	foreach (adminer()->foreignKeys($table) as $val) {
		if (!$val["db"]) {
			$left = $base_left;
			if (idx($table_pos[$table], 1) || idx($table_pos[$val["table"]], 1)) {
				$left = min(idx($table_pos[$table], 1, 0), idx($table_pos[$val["table"]], 1, 0)) - 1;
			} else {
				$base_left -= .1;
			}
			while ($lefts[(string) $left]) {
				// find free $left
				$left -= .0001;
			}
			$schema[$table]["references"][$val["table"]][(string) $left] = array($val["source"], $val["target"]);
			$referenced[$val["table"]][$table][(string) $left] = $val["target"];
			$lefts[(string) $left] = true;
		}
	}
	$top = max($top, $schema[$table]["pos"][0] + 2.5 + $pos);
}

?>
<div id="schema" style="height: <?php echo $top; ?>em;">
<script<?php echo nonce(); ?>>
qs('#schema').onselectstart = () => false;
const tablePos = {<?php echo implode(",", $table_pos_js) . "\n"; ?>};
const em = qs('#schema').offsetHeight / <?php echo $top; ?>;
document.onmousemove = schemaMousemove;
document.onmouseup = partialArg(schemaMouseup, '<?php echo js_escape(DB); ?>');
</script>
<?php
foreach ($schema as $name => $table) {
	echo "<div class='table' style='top: " . $table["pos"][0] . "em; left: " . $table["pos"][1] . "em;'>";
	echo '<a href="' . h(ME) . 'table=' . urlencode($name) . '"><b>' . h($name) . "</b></a>";
	echo script("qsl('div').onmousedown = schemaMousedown;");

	foreach ($table["fields"] as $field) {
		$val = '<span' . type_class($field["type"]) . ' title="' . h($field["type"] . ($field["length"] ? "($field[length])" : "") . ($field["null"] ? " NULL" : '')) . '">' . h($field["field"]) . '</span>';
		echo "<br>" . ($field["primary"] ? "<i>$val</i>" : $val);
	}

	foreach ((array) $table["references"] as $target_name => $refs) {
		foreach ($refs as $left => $ref) {
			$left1 = $left - idx($table_pos[$name], 1);
			$i = 0;
			foreach ($ref[0] as $source) {
				echo "\n<div class='references' title='" . h($target_name) . "' id='refs$left-" . ($i++) . "' style='left: $left1" . "em; top: " . $table["fields"][$source]["pos"] . "em; padding-top: .5em;'>"
					. "<div style='border-top: 1px solid gray; width: " . (-$left1) . "em;'></div></div>"
				;
			}
		}
	}

	foreach ((array) $referenced[$name] as $target_name => $refs) {
		foreach ($refs as $left => $columns) {
			$left1 = $left - idx($table_pos[$name], 1);
			$i = 0;
			foreach ($columns as $target) {
				echo "\n<div class='references arrow' title='" . h($target_name) . "' id='refd$left-" . ($i++) . "' style='left: $left1" . "em; top: " . $table["fields"][$target]["pos"] . "em;'>"
					. "<div style='height: .5em; border-bottom: 1px solid gray; width: " . (-$left1) . "em;'></div>"
					. "</div>"
				;
			}
		}
	}

	echo "\n</div>\n";
}

foreach ($schema as $name => $table) {
	foreach ((array) $table["references"] as $target_name => $refs) {
		foreach ($refs as $left => $ref) {
			$min_pos = $top;
			$max_pos = -10;
			foreach ($ref[0] as $key => $source) {
				$pos1 = $table["pos"][0] + $table["fields"][$source]["pos"];
				$pos2 = $schema[$target_name]["pos"][0] + $schema[$target_name]["fields"][$ref[1][$key]]["pos"];
				$min_pos = min($min_pos, $pos1, $pos2);
				$max_pos = max($max_pos, $pos1, $pos2);
			}
			echo "<div class='references' id='refl$left' style='left: $left" . "em; top: $min_pos" . "em; padding: .5em 0;'><div style='border-right: 1px solid gray; margin-top: 1px; height: " . ($max_pos - $min_pos) . "em;'></div></div>\n";
		}
	}
}
?>
</div>
<p class="links"><a href="<?php echo h(ME . "schema=" . urlencode($SCHEMA)); ?>" id="schema-link"><?php echo lang(151); ?></a>
<?php
} elseif (isset($_GET["dump"])) {
	?>
<?php
$TABLE = $_GET["dump"];

if ($_POST && !$error) {
	save_settings(
		array_intersect_key($_POST, array_flip(array("output", "format", "db_style", "types", "routines", "events", "table_style", "auto_increment", "triggers", "data_style"))),
		"adminer_export"
	);
	$tables = array_flip((array) $_POST["tables"]) + array_flip((array) $_POST["data"]);
	$ext = dump_headers(
		(count($tables) == 1 ? key($tables) : DB),
		(DB == "" || count($tables) > 1)
	);
	$is_sql = preg_match('~sql~', $_POST["format"]);

	if ($is_sql) {
		echo "-- Adminer " . VERSION . " " . get_driver(DRIVER) . " " . str_replace("\n", " ", connection()->server_info) . " dump\n\n";
		if (JUSH == "sql") {
			echo "SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
" . ($_POST["data_style"] ? "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
" : "") . "
";
			connection()->query("SET time_zone = '+00:00'");
			connection()->query("SET sql_mode = ''");
		}
	}

	$style = $_POST["db_style"];
	$databases = array(DB);
	if (DB == "") {
		$databases = $_POST["databases"];
		if (is_string($databases)) {
			$databases = explode("\n", rtrim(str_replace("\r", "", $databases), "\n"));
		}
	}

	foreach ((array) $databases as $db) {
		adminer()->dumpDatabase($db);
		if (connection()->select_db($db)) {
			if ($is_sql) {
				if ($style) {
					echo use_sql($db, $style) . ";\n\n";
				}
				$out = "";

				if ($_POST["types"]) {
					foreach (types() as $id => $type) {
						$enums = type_values($id);
						if ($enums) {
							$out .= ($style != 'DROP+CREATE' ? "DROP TYPE IF EXISTS " . idf_escape($type) . ";;\n" : "") . "CREATE TYPE " . idf_escape($type) . " AS ENUM ($enums);\n\n";
						} else {
							//! https://github.com/postgres/postgres/blob/REL_17_4/src/bin/pg_dump/pg_dump.c#L10846
							$out .= "-- Could not export type $type\n\n";
						}
					}
				}

				if ($_POST["routines"]) {
					foreach (routines() as $row) {
						$name = $row["ROUTINE_NAME"];
						$routine = $row["ROUTINE_TYPE"];
						$create = create_routine($routine, array("name" => $name) + routine($row["SPECIFIC_NAME"], $routine));
						set_utf8mb4($create);
						$out .= ($style != 'DROP+CREATE' ? "DROP $routine IF EXISTS " . idf_escape($name) . ";;\n" : "") . "$create;\n\n";
					}
				}

				if ($_POST["events"]) {
					foreach (get_rows("SHOW EVENTS", null, "-- ") as $row) {
						$create = remove_definer(get_val("SHOW CREATE EVENT " . idf_escape($row["Name"]), 3));
						set_utf8mb4($create);
						$out .= ($style != 'DROP+CREATE' ? "DROP EVENT IF EXISTS " . idf_escape($row["Name"]) . ";;\n" : "") . "$create;;\n\n";
					}
				}

				echo ($out && JUSH == 'sql' ? "DELIMITER ;;\n\n$out" . "DELIMITER ;\n\n" : $out);
			}

			if ($_POST["table_style"] || $_POST["data_style"]) {
				$views = array();
				foreach (table_status('', true) as $name => $table_status) {
					$table = (DB == "" || in_array($name, (array) $_POST["tables"]));
					$data = (DB == "" || in_array($name, (array) $_POST["data"]));
					if ($table || $data) {
						$tmp_file = null;
						if ($ext == "tar") {
							$tmp_file = new TmpFile;
							ob_start(array($tmp_file, 'write'), 1e5);
						}

						adminer()->dumpTable($name, ($table ? $_POST["table_style"] : ""), (is_view($table_status) ? 2 : 0));
						if (is_view($table_status)) {
							$views[] = $name;
						} elseif ($data) {
							$fields = fields($name);
							adminer()->dumpData($name, $_POST["data_style"], "SELECT *" . convert_fields($fields, $fields) . " FROM " . table($name));
						}
						if ($is_sql && $_POST["triggers"] && $table && ($triggers = trigger_sql($name))) {
							echo "\nDELIMITER ;;\n$triggers\nDELIMITER ;\n";
						}

						if ($ext == "tar") {
							ob_end_flush();
							tar_file((DB != "" ? "" : "$db/") . "$name.csv", $tmp_file);
						} elseif ($is_sql) {
							echo "\n";
						}
					}
				}

				// add FKs after creating tables (except in MySQL which uses SET FOREIGN_KEY_CHECKS=0)
				if (function_exists('Adminer\foreign_keys_sql')) {
					foreach (table_status('', true) as $name => $table_status) {
						$table = (DB == "" || in_array($name, (array) $_POST["tables"]));
						if ($table && !is_view($table_status)) {
							echo foreign_keys_sql($name);
						}
					}
				}

				foreach ($views as $view) {
					adminer()->dumpTable($view, $_POST["table_style"], 1);
				}

				if ($ext == "tar") {
					echo pack("x512");
				}
			}
		}
	}

	adminer()->dumpFooter();
	exit;
}

page_header(lang(76), $error, ($_GET["export"] != "" ? array("table" => $_GET["export"]) : array()), h(DB));
?>

<form action="" method="post">
<table class="layout">
<?php
$db_style = array('', 'USE', 'DROP+CREATE', 'CREATE');
$table_style = array('', 'DROP+CREATE', 'CREATE');
$data_style = array('', 'TRUNCATE+INSERT', 'INSERT');
if (JUSH == "sql") { //! use insertUpdate() in all drivers
	$data_style[] = 'INSERT+UPDATE';
}
$row = get_settings("adminer_export");
if (!$row) {
	$row = array("output" => "text", "format" => "sql", "db_style" => (DB != "" ? "" : "CREATE"), "table_style" => "DROP+CREATE", "data_style" => "INSERT");
}
if (!isset($row["events"])) { // backwards compatibility
	$row["routines"] = $row["events"] = ($_GET["dump"] == "");
	$row["triggers"] = $row["table_style"];
}

echo "<tr><th>" . lang(152) . "<td>" . html_radios("output", adminer()->dumpOutput(), $row["output"]) . "\n";

echo "<tr><th>" . lang(153) . "<td>" . html_radios("format", adminer()->dumpFormat(), $row["format"]) . "\n";

echo (JUSH == "sqlite" ? "" : "<tr><th>" . lang(37) . "<td>" . html_select('db_style', $db_style, $row["db_style"])
	. (support("type") ? checkbox("types", 1, $row["types"], lang(6)) : "")
	. (support("routine") ? checkbox("routines", 1, $row["routines"], lang(72)) : "")
	. (support("event") ? checkbox("events", 1, $row["events"], lang(74)) : "")
);

echo "<tr><th>" . lang(128) . "<td>" . html_select('table_style', $table_style, $row["table_style"])
	. checkbox("auto_increment", 1, $row["auto_increment"], lang(51))
	. (support("trigger") ? checkbox("triggers", 1, $row["triggers"], lang(148)) : "")
;

echo "<tr><th>" . lang(154) . "<td>" . html_select('data_style', $data_style, $row["data_style"]);
?>
</table>
<p><input type="submit" value="<?php echo lang(76); ?>">
<?php echo input_token(); ?>

<table>
<?php
echo script("qsl('table').onclick = dumpClick;");
$prefixes = array();
if (DB != "") {
	$checked = ($TABLE != "" ? "" : " checked");
	echo "<thead><tr>";
	echo "<th style='text-align: left;'><label class='block'><input type='checkbox' id='check-tables'$checked>" . lang(128) . "</label>" . script("qs('#check-tables').onclick = partial(formCheck, /^tables\\[/);", "");
	echo "<th style='text-align: right;'><label class='block'>" . lang(154) . "<input type='checkbox' id='check-data'$checked></label>" . script("qs('#check-data').onclick = partial(formCheck, /^data\\[/);", "");
	echo "</thead>\n";

	$views = "";
	$tables_list = tables_list();
	foreach ($tables_list as $name => $type) {
		$prefix = preg_replace('~_.*~', '', $name);
		$checked = ($TABLE == "" || $TABLE == (substr($TABLE, -1) == "%" ? "$prefix%" : $name)); //! % may be part of table name
		$print = "<tr><td>" . checkbox("tables[]", $name, $checked, $name, "", "block");
		if ($type !== null && !preg_match('~table~i', $type)) {
			$views .= "$print\n";
		} else {
			echo "$print<td align='right'><label class='block'><span id='Rows-" . h($name) . "'></span>" . checkbox("data[]", $name, $checked) . "</label>\n";
		}
		$prefixes[$prefix]++;
	}
	echo $views;

	if ($tables_list) {
		echo script("ajaxSetHtml('" . js_escape(ME) . "script=db');");
	}

} else {
	echo "<thead><tr><th style='text-align: left;'>";
	echo "<label class='block'><input type='checkbox' id='check-databases'" . ($TABLE == "" ? " checked" : "") . ">" . lang(37) . "</label>";
	echo script("qs('#check-databases').onclick = partial(formCheck, /^databases\\[/);", "");
	echo "</thead>\n";
	$databases = adminer()->databases();
	if ($databases) {
		foreach ($databases as $db) {
			if (!information_schema($db)) {
				$prefix = preg_replace('~_.*~', '', $db);
				echo "<tr><td>" . checkbox("databases[]", $db, $TABLE == "" || $TABLE == "$prefix%", $db, "", "block") . "\n";
				$prefixes[$prefix]++;
			}
		}
	} else {
		echo "<tr><td><textarea name='databases' rows='10' cols='20'></textarea>";
	}
}
?>
</table>
</form>
<?php
$first = true;
foreach ($prefixes as $key => $val) {
	if ($key != "" && $val > 1) {
		echo ($first ? "<p>" : " ") . "<a href='" . h(ME) . "dump=" . urlencode("$key%") . "'>" . h($key) . "</a>";
		$first = false;
	}
}

} elseif (isset($_GET["privileges"])) {
	?>
<?php
page_header(lang(71));

echo '<p class="links"><a href="' . h(ME) . 'user=">' . lang(155) . "</a>";

$result = connection()->query("SELECT User, Host FROM mysql." . (DB == "" ? "user" : "db WHERE " . q(DB) . " LIKE Db") . " ORDER BY Host, User");
$grant = $result;
if (!$result) {
	// list logged user, information_schema.USER_PRIVILEGES lists just the current user too
	$result = connection()->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");
}

echo "<form action=''><p>\n";
hidden_fields_get();
echo input_hidden("db", DB);
echo ($grant ? "" : input_hidden("grant"));
echo "<table class='odds'>\n";
echo "<thead><tr><th>" . lang(35) . "<th>" . lang(34) . "<th></thead>\n";

while ($row = $result->fetch_assoc()) {
	echo '<tr><td>' . h($row["User"]) . "<td>" . h($row["Host"]) . '<td><a href="' . h(ME . 'user=' . urlencode($row["User"]) . '&host=' . urlencode($row["Host"])) . '">' . lang(12) . "</a>\n";
}

if (!$grant || DB != "") {
	echo "<tr><td><input name='user' autocapitalize='off'><td><input name='host' value='localhost' autocapitalize='off'><td><input type='submit' value='" . lang(12) . "'>\n";
}

echo "</table>\n";
echo "</form>\n";

} elseif (isset($_GET["sql"])) {
	?>
<?php
if (!$error && $_POST["export"]) {
	save_settings(array("output" => $_POST["output"], "format" => $_POST["format"]), "adminer_import");
	dump_headers("sql");
	if ($_POST["format"] == "sql") {
		echo "$_POST[query]\n";
	} else {
		adminer()->dumpTable("", "");
		adminer()->dumpData("", "table", $_POST["query"]);
		adminer()->dumpFooter();
	}
	exit;
}

restart_session();
$history_all = &get_session("queries");
$history = &$history_all[DB];
if (!$error && $_POST["clear"]) {
	$history = array();
	redirect(remove_from_uri("history"));
}
stop_session();

page_header((isset($_GET["import"]) ? lang(75) : lang(64)), $error);
$line_comment = '--' . (JUSH == 'sql' ? ' ' : '');

if (!$error && $_POST) {
	$fp = false;
	if (!isset($_GET["import"])) {
		$query = $_POST["query"];
	} elseif ($_POST["webfile"]) {
		$sql_file_path = adminer()->importServerPath();
		$fp = @fopen((file_exists($sql_file_path)
			? $sql_file_path
			: "compress.zlib://$sql_file_path.gz"
		), "rb");
		$query = ($fp ? fread($fp, 1e6) : false);
	} else {
		$query = get_file("sql_file", true, ";");
	}

	if (is_string($query)) { // get_file() returns error as number, fread() as false
		if (function_exists('memory_get_usage') && ($memory_limit = ini_bytes("memory_limit")) != "-1") {
			@ini_set("memory_limit", max($memory_limit, strval(2 * strlen($query) + memory_get_usage() + 8e6))); // @ - may be disabled, 2 - substr and trim, 8e6 - other variables
		}

		if ($query != "" && strlen($query) < 1e6) { // don't add big queries
			$q = $query . (preg_match("~;[ \t\r\n]*\$~", $query) ? "" : ";"); //! doesn't work with DELIMITER |
			if (!$history || first(end($history)) != $q) { // no repeated queries
				restart_session();
				$history[] = array($q, time()); //! add elapsed time
				set_session("queries", $history_all); // required because reference is unlinked by stop_session()
				stop_session();
			}
		}

		$space = "(?:\\s|/\\*[\s\S]*?\\*/|(?:#|$line_comment)[^\n]*\n?|--\r?\n)";
		$delimiter = ";";
		$offset = 0;
		$empty = true;
		$connection2 = connect(); // connection for exploring indexes and EXPLAIN (to not replace FOUND_ROWS()) //! PDO - silent error
		if ($connection2 && DB != "") {
			$connection2->select_db(DB);
			if ($_GET["ns"] != "") {
				set_schema($_GET["ns"], $connection2);
			}
		}
		$commands = 0;
		$errors = array();
		$parse = '[\'"' . (JUSH == "sql" ? '`#' : (JUSH == "sqlite" ? '`[' : (JUSH == "mssql" ? '[' : ''))) . ']|/\*|' . $line_comment . '|$' . (JUSH == "pgsql" ? '|\$([a-zA-Z]\w*)?\$' : '');
		$total_start = microtime(true);
		$adminer_export = get_settings("adminer_import"); // this doesn't offer SQL export so we match the import/export style at select

		while ($query != "") {
			if (!$offset && preg_match("~^$space*+DELIMITER\\s+(\\S+)~i", $query, $match)) {
				$delimiter = preg_quote($match[1]);
				$query = substr($query, strlen($match[0]));
			} elseif (!$offset && JUSH == 'pgsql' && preg_match("~^($space*+COPY\\s+)[^;]+\\s+FROM\\s+stdin;~i", $query, $match)) {
				$delimiter = "\n\\\\\\.\r?\n";
				$offset = strlen($match[0]);
			} else {
				preg_match("($delimiter\\s*|$parse)", $query, $match, PREG_OFFSET_CAPTURE, $offset); // always matches
				list($found, $pos) = $match[0];
				if (!$found && $fp && !feof($fp)) {
					$query .= fread($fp, 1e5);
				} else {
					if (!$found && rtrim($query) == "") {
						break;
					}
					$offset = $pos + strlen($found);

					if ($found && !preg_match("(^$delimiter)", $found)) { // find matching quote or comment end
						$c_style_escapes = driver()->hasCStyleEscapes() || (JUSH == "pgsql" && ($pos > 0 && strtolower($query[$pos - 1]) == "e"));

						$pattern =
							($found == '/*' ? '\*/' :
							($found == '[' ? ']' :
							(preg_match("~^$line_comment|^#~", $found) ? "\n" :
							preg_quote($found) . ($c_style_escapes ? '|\\\\.' : ''))))
						;

						while (preg_match("($pattern|\$)s", $query, $match, PREG_OFFSET_CAPTURE, $offset)) {
							$s = $match[0][0];
							if (!$s && $fp && !feof($fp)) {
								$query .= fread($fp, 1e5);
							} else {
								$offset = $match[0][1] + strlen($s);
								if (!$s || $s[0] != "\\") {
									break;
								}
							}
						}

					} else { // end of a query
						$empty = false;
						$q = substr($query, 0, $pos + ($delimiter[0] == "\n" ? 3 : 0)); // 3 - pass "\n\\." to PostgreSQL COPY
						$commands++;
						$print = "<pre id='sql-$commands'><code class='jush-" . JUSH . "'>" . adminer()->sqlCommandQuery($q) . "</code></pre>\n";
						if (JUSH == "sqlite" && preg_match("~^$space*+ATTACH\\b~i", $q, $match)) {
							// PHP doesn't support setting SQLITE_LIMIT_ATTACHED
							echo $print;
							echo "<p class='error'>" . lang(156) . "\n";
							$errors[] = " <a href='#sql-$commands'>$commands</a>";
							if ($_POST["error_stops"]) {
								break;
							}
						} else {
							if (!$_POST["only_errors"]) {
								echo $print;
								ob_flush();
								flush(); // can take a long time - show the running query
							}
							$start = microtime(true);
							//! don't allow changing of character_set_results, convert encoding of displayed query
							if (connection()->multi_query($q) && $connection2 && preg_match("~^$space*+USE\\b~i", $q)) {
								$connection2->query($q);
							}

							do {
								$result = connection()->store_result();

								if (connection()->error) {
									echo ($_POST["only_errors"] ? $print : "");
									echo "<p class='error'>" . lang(157) . (connection()->errno ? " (" . connection()->errno . ")" : "") . ": " . error() . "\n";
									$errors[] = " <a href='#sql-$commands'>$commands</a>";
									if ($_POST["error_stops"]) {
										break 2;
									}

								} else {
									$time = " <span class='time'>(" . format_time($start) . ")</span>"
										. (strlen($q) < 1000 ? " <a href='" . h(ME) . "sql=" . urlencode(trim($q)) . "'>" . lang(12) . "</a>" : "") // 1000 - maximum length of encoded URL in IE is 2083 characters
									;
									$affected = connection()->affected_rows; // getting warnings overwrites this
									$warnings = ($_POST["only_errors"] ? "" : driver()->warnings());
									$warnings_id = "warnings-$commands";
									if ($warnings) {
										$time .= ", <a href='#$warnings_id'>" . lang(46) . "</a>" . script("qsl('a').onclick = partial(toggle, '$warnings_id');", "");
									}
									$explain = null;
									$orgtables = null;
									$explain_id = "explain-$commands";
									if (is_object($result)) {
										$limit = $_POST["limit"];
										$orgtables = print_select_result($result, $connection2, array(), $limit);
										if (!$_POST["only_errors"]) {
											echo "<form action='' method='post'>\n";
											$num_rows = $result->num_rows;
											echo "<p class='sql-footer'>" . ($num_rows ? ($limit && $num_rows > $limit ? lang(158, $limit) : "") . lang(159, $num_rows) : "");
											echo $time;
											if ($connection2 && preg_match("~^($space|\\()*+SELECT\\b~i", $q) && ($explain = explain($connection2, $q))) {
												echo ", <a href='#$explain_id'>Explain</a>" . script("qsl('a').onclick = partial(toggle, '$explain_id');", "");
											}
											$id = "export-$commands";
											echo ", <a href='#$id'>" . lang(76) . "</a>" . script("qsl('a').onclick = partial(toggle, '$id');", "") . "<span id='$id' class='hidden'>: "
												. html_select("output", adminer()->dumpOutput(), $adminer_export["output"]) . " "
												. html_select("format", adminer()->dumpFormat(), $adminer_export["format"])
												. input_hidden("query", $q)
												. "<input type='submit' name='export' value='" . lang(76) . "'>" . input_token() . "</span>\n"
												. "</form>\n"
											;
										}

									} else {
										if (preg_match("~^$space*+(CREATE|DROP|ALTER)$space++(DATABASE|SCHEMA)\\b~i", $q)) {
											restart_session();
											set_session("dbs", null); // clear cache
											stop_session();
										}
										if (!$_POST["only_errors"]) {
											echo "<p class='message' title='" . h(connection()->info) . "'>" . lang(160, $affected) . "$time\n";
										}
									}
									echo ($warnings ? "<div id='$warnings_id' class='hidden'>\n$warnings</div>\n" : "");
									if ($explain) {
										echo "<div id='$explain_id' class='hidden explain'>\n";
										print_select_result($explain, $connection2, $orgtables);
										echo "</div>\n";
									}
								}

								$start = microtime(true);
							} while (connection()->next_result());
						}

						$query = substr($query, $offset);
						$offset = 0;
					}

				}
			}
		}

		if ($empty) {
			echo "<p class='message'>" . lang(161) . "\n";
		} elseif ($_POST["only_errors"]) {
			echo "<p class='message'>" . lang(162, $commands - count($errors));
			echo " <span class='time'>(" . format_time($total_start) . ")</span>\n";
		} elseif ($errors && $commands > 1) {
			echo "<p class='error'>" . lang(157) . ": " . implode("", $errors) . "\n";
		}
		//! MS SQL - SET SHOWPLAN_ALL OFF

	} else {
		echo "<p class='error'>" . upload_error($query) . "\n";
	}
}
?>

<form action="" method="post" enctype="multipart/form-data" id="form">
<?php
$execute = "<input type='submit' value='" . lang(163) . "' title='Ctrl+Enter'>";
if (!isset($_GET["import"])) {
	$q = $_GET["sql"]; // overwrite $q from if ($_POST) to save memory
	if ($_POST) {
		$q = $_POST["query"];
	} elseif ($_GET["history"] == "all") {
		$q = $history;
	} elseif ($_GET["history"] != "") {
		$q = idx($history[$_GET["history"]], 0);
	}
	echo "<p>";
	textarea("query", $q, 20);
	echo script(($_POST ? "" : "qs('textarea').focus();\n") . "qs('#form').onsubmit = partial(sqlSubmit, qs('#form'), '" . js_escape(remove_from_uri("sql|limit|error_stops|only_errors|history")) . "');");
	echo "<p>";
	adminer()->sqlPrintAfter();
	echo "$execute\n";
	echo lang(164) . ": <input type='number' name='limit' class='size' value='" . h($_POST ? $_POST["limit"] : $_GET["limit"]) . "'>\n";

} else {
	$gz = (extension_loaded("zlib") ? "[.gz]" : "");
	echo "<fieldset><legend>" . lang(165) . "</legend><div>";
	echo file_input("SQL$gz: <input type='file' name='sql_file[]' multiple>\n$execute");
	echo "</div></fieldset>\n";
	$importServerPath = adminer()->importServerPath();
	if ($importServerPath) {
		echo "<fieldset><legend>" . lang(166) . "</legend><div>";
		echo lang(167, "<code>" . h($importServerPath) . "$gz</code>");
		echo ' <input type="submit" name="webfile" value="' . lang(168) . '">';
		echo "</div></fieldset>\n";
	}
	echo "<p>";
}

echo checkbox("error_stops", 1, ($_POST ? $_POST["error_stops"] : isset($_GET["import"]) || $_GET["error_stops"]), lang(169)) . "\n";
echo checkbox("only_errors", 1, ($_POST ? $_POST["only_errors"] : isset($_GET["import"]) || $_GET["only_errors"]), lang(170)) . "\n";
echo input_token();

if (!isset($_GET["import"]) && $history) {
	print_fieldset("history", lang(171), $_GET["history"] != "");
	for ($val = end($history); $val; $val = prev($history)) { // not array_reverse() to save memory
		$key = key($history);
		list($q, $time, $elapsed) = $val;
		echo '<a href="' . h(ME . "sql=&history=$key") . '">' . lang(12) . "</a>"
			. " <span class='time' title='" . @date('Y-m-d', $time) . "'>" . @date("H:i:s", $time) . "</span>" // @ - time zone may be not set
			. " <code class='jush-" . JUSH . "'>" . shorten_utf8(ltrim(str_replace("\n", " ", str_replace("\r", "", preg_replace("~^(#|$line_comment).*~m", '', $q)))), 80, "</code>")
			. ($elapsed ? " <span class='time'>($elapsed)</span>" : "")
			. "<br>\n"
		;
	}
	echo "<input type='submit' name='clear' value='" . lang(172) . "'>\n";
	echo "<a href='" . h(ME . "sql=&history=all") . "'>" . lang(173) . "</a>\n";
	echo "</div></fieldset>\n";
}
?>
</form>
<?php
} elseif (isset($_GET["edit"])) {
	?>
<?php
$TABLE = $_GET["edit"];
$fields = fields($TABLE);
$where = (isset($_GET["select"])
	? ($_POST["check"] && count($_POST["check"]) == 1 ? where_check($_POST["check"][0], $fields) : "")
	: where($_GET, $fields)
);
$update = (isset($_GET["select"]) ? $_POST["edit"] : $where);
foreach ($fields as $name => $field) {
	if (!isset($field["privileges"][$update ? "update" : "insert"]) || adminer()->fieldName($field) == "" || $field["generated"]) {
		unset($fields[$name]);
	}
}

if ($_POST && !$error && !isset($_GET["select"])) {
	$location = $_POST["referer"];
	if ($_POST["insert"]) { // continue edit or insert
		$location = ($update ? null : $_SERVER["REQUEST_URI"]);
	} elseif (!preg_match('~^.+&select=.+$~', $location)) {
		$location = ME . "select=" . urlencode($TABLE);
	}

	$indexes = indexes($TABLE);
	$unique_array = unique_array($_GET["where"], $indexes);
	$query_where = "\nWHERE $where";

	if (isset($_POST["delete"])) {
		queries_redirect(
			$location,
			lang(174),
			driver()->delete($TABLE, $query_where, $unique_array ? 0 : 1)
		);

	} else {
		$set = array();
		foreach ($fields as $name => $field) {
			$val = process_input($field);
			if ($val !== false && $val !== null) {
				$set[idf_escape($name)] = $val;
			}
		}

		if ($update) {
			if (!$set) {
				redirect($location);
			}
			queries_redirect(
				$location,
				lang(175),
				driver()->update($TABLE, $set, $query_where, $unique_array ? 0 : 1)
			);
			if (is_ajax()) {
				page_headers();
				page_messages($error);
				exit;
			}
		} else {
			$result = driver()->insert($TABLE, $set);
			$last_id = ($result ? last_id($result) : 0);
			queries_redirect($location, lang(176, ($last_id ? " $last_id" : "")), $result); //! link
		}
	}
}

$row = null;
if ($_POST["save"]) {
	$row = (array) $_POST["fields"];
} elseif ($where) {
	$select = array();
	foreach ($fields as $name => $field) {
		if (isset($field["privileges"]["select"])) {
			$as = ($_POST["clone"] && $field["auto_increment"] ? "''" : convert_field($field));
			$select[] = ($as ? "$as AS " : "") . idf_escape($name);
		}
	}
	$row = array();
	if (!support("table")) {
		$select = array("*");
	}
	if ($select) {
		$result = driver()->select($TABLE, $select, array($where), $select, array(), (isset($_GET["select"]) ? 2 : 1));
		if (!$result) {
			$error = error();
		} else {
			$row = $result->fetch_assoc();
			if (!$row) { // MySQLi returns null
				$row = false;
			}
		}
		if (isset($_GET["select"]) && (!$row || $result->fetch_assoc())) { // $result->num_rows != 1 isn't available in all drivers
			$row = null;
		}
	}
}

if (!support("table") && !$fields) { // used by Mongo and SimpleDB
	if (!$where) { // insert
		$result = driver()->select($TABLE, array("*"), array(), array("*"));
		$row = ($result ? $result->fetch_assoc() : false);
		if (!$row) {
			$row = array(driver()->primary => "");
		}
	}
	if ($row) {
		foreach ($row as $key => $val) {
			if (!$where) {
				$row[$key] = null;
			}
			$fields[$key] = array("field" => $key, "null" => ($key != driver()->primary), "auto_increment" => ($key == driver()->primary));
		}
	}
}

edit_form($TABLE, $fields, $row, $update, $error);

} elseif (isset($_GET["create"])) {
	?>
<?php
$TABLE = $_GET["create"];
$partition_by = driver()->partitionBy;
$partitions_info = ($partition_by ? driver()->partitionsInfo($TABLE) : array());

$referencable_primary = referencable_primary($TABLE);
$foreign_keys = array();
foreach ($referencable_primary as $table_name => $field) {
	$foreign_keys[str_replace("`", "``", $table_name) . "`" . str_replace("`", "``", $field["field"])] = $table_name; // not idf_escape() - used in JS
}

$orig_fields = array();
$table_status = array();
if ($TABLE != "") {
	$orig_fields = fields($TABLE);
	$table_status = table_status1($TABLE);
	if (count($table_status) < 2) { // there's only the Name field
		$error = lang(11);
	}
}

$row = $_POST;
$row["fields"] = (array) $row["fields"];
if ($row["auto_increment_col"]) {
	$row["fields"][$row["auto_increment_col"]]["auto_increment"] = true;
}

if ($_POST) {
	save_settings(array("comments" => $_POST["comments"], "defaults" => $_POST["defaults"]));
}

if ($_POST && !process_fields($row["fields"]) && !$error) {
	if ($_POST["drop"]) {
		queries_redirect(substr(ME, 0, -1), lang(177), drop_tables(array($TABLE)));
	} else {
		$fields = array();
		$all_fields = array();
		$use_all_fields = false;
		$foreign = array();
		$orig_field = reset($orig_fields);
		$after = " FIRST";

		foreach ($row["fields"] as $key => $field) {
			$foreign_key = $foreign_keys[$field["type"]];
			$type_field = ($foreign_key !== null ? $referencable_primary[$foreign_key] : $field); //! can collide with user defined type
			if ($field["field"] != "") {
				if (!$field["generated"]) {
					$field["default"] = null;
				}
				$process_field = process_field($field, $type_field);
				$all_fields[] = array($field["orig"], $process_field, $after);
				if (!$orig_field || $process_field !== process_field($orig_field, $orig_field)) {
					$fields[] = array($field["orig"], $process_field, $after);
					if ($field["orig"] != "" || $after) {
						$use_all_fields = true;
					}
				}
				if ($foreign_key !== null) {
					$foreign[idf_escape($field["field"])] = ($TABLE != "" && JUSH != "sqlite" ? "ADD" : " ") . format_foreign_key(array(
						'table' => $foreign_keys[$field["type"]],
						'source' => array($field["field"]),
						'target' => array($type_field["field"]),
						'on_delete' => $field["on_delete"],
					));
				}
				$after = " AFTER " . idf_escape($field["field"]);
			} elseif ($field["orig"] != "") {
				$use_all_fields = true;
				$fields[] = array($field["orig"]);
			}
			if ($field["orig"] != "") {
				$orig_field = next($orig_fields);
				if (!$orig_field) {
					$after = "";
				}
			}
		}

		$partitioning = array();
		if (in_array($row["partition_by"], $partition_by)) {
			foreach ($row as $key => $val) {
				if (preg_match('~^partition~', $key)) {
					$partitioning[$key] = $val;
				}
			}
			foreach ($partitioning["partition_names"] as $key => $name) {
				if ($name == "") {
					unset($partitioning["partition_names"][$key]);
					unset($partitioning["partition_values"][$key]);
				}
			}
			$partitioning["partition_names"] = array_values($partitioning["partition_names"]);
			$partitioning["partition_values"] = array_values($partitioning["partition_values"]);
			if ($partitioning == $partitions_info) {
				$partitioning = array();
			}
		} elseif (preg_match("~partitioned~", $table_status["Create_options"])) {
			$partitioning = null;
		}

		$message = lang(178);
		if ($TABLE == "") {
			cookie("adminer_engine", $row["Engine"]);
			$message = lang(179);
		}
		$name = trim($row["name"]);

		queries_redirect(ME . (support("table") ? "table=" : "select=") . urlencode($name), $message, alter_table(
			$TABLE,
			$name,
			(JUSH == "sqlite" && ($use_all_fields || $foreign) ? $all_fields : $fields),
			$foreign,
			($row["Comment"] != $table_status["Comment"] ? $row["Comment"] : null),
			($row["Engine"] && $row["Engine"] != $table_status["Engine"] ? $row["Engine"] : ""),
			($row["Collation"] && $row["Collation"] != $table_status["Collation"] ? $row["Collation"] : ""),
			($row["Auto_increment"] != "" ? number($row["Auto_increment"]) : ""),
			$partitioning
		));
	}
}

page_header(($TABLE != "" ? lang(43) : lang(77)), $error, array("table" => $TABLE), h($TABLE));

if (!$_POST) {
	$types = driver()->types();
	$row = array(
		"Engine" => $_COOKIE["adminer_engine"],
		"fields" => array(array("field" => "", "type" => (isset($types["int"]) ? "int" : (isset($types["integer"]) ? "integer" : "")), "on_update" => "")),
		"partition_names" => array(""),
	);

	if ($TABLE != "") {
		$row = $table_status;
		$row["name"] = $TABLE;
		$row["fields"] = array();
		if (!$_GET["auto_increment"]) { // don't prefill by original Auto_increment for the sake of performance and not reusing deleted ids
			$row["Auto_increment"] = "";
		}
		foreach ($orig_fields as $field) {
			$field["generated"] = $field["generated"] ?: (isset($field["default"]) ? "DEFAULT" : "");
			$row["fields"][] = $field;
		}

		if ($partition_by) {
			$row += $partitions_info;
			$row["partition_names"][] = "";
			$row["partition_values"][] = "";
		}
	}
}

$collations = collations();
if (is_array(reset($collations))) {
	$collations = call_user_func_array('array_merge', array_values($collations));
}
$engines = driver()->engines();
// case of engine may differ
foreach ($engines as $engine) {
	if (!strcasecmp($engine, $row["Engine"])) {
		$row["Engine"] = $engine;
		break;
	}
}
?>

<form action="" method="post" id="form">
<p>
<?php
if (support("columns") || $TABLE == "") {
	echo lang(180) . ": <input name='name'" . ($TABLE == "" && !$_POST ? " autofocus" : "") . " data-maxlength='64' value='" . h($row["name"]) . "' autocapitalize='off'>\n";
	echo ($engines ? html_select("Engine", array("" => "(" . lang(181) . ")") + $engines, $row["Engine"]) . on_help("event.target.value", 1) . script("qsl('select').onchange = helpClose;") . "\n" : "");
	if ($collations) {
		echo "<datalist id='collations'>" . optionlist($collations) . "</datalist>\n";
		echo (preg_match("~sqlite|mssql~", JUSH) ? "" : "<input list='collations' name='Collation' value='" . h($row["Collation"]) . "' placeholder='(" . lang(106) . ")'>\n");
	}
	echo "<input type='submit' value='" . lang(16) . "'>\n";
}

if (support("columns")) {
	echo "<div class='scrollable'>\n";
	echo "<table id='edit-fields' class='nowrap'>\n";
	edit_fields($row["fields"], $collations, "TABLE", $foreign_keys);
	echo "</table>\n";
	echo script("editFields();");
	echo "</div>\n<p>\n";
	echo lang(51) . ": <input type='number' name='Auto_increment' class='size' value='" . h($row["Auto_increment"]) . "'>\n";
	echo checkbox("defaults", 1, ($_POST ? $_POST["defaults"] : get_setting("defaults")), lang(182), "columnShow(this.checked, 5)", "jsonly");
	$comments = ($_POST ? $_POST["comments"] : get_setting("comments"));
	echo (support("comment")
		? checkbox("comments", 1, $comments, lang(50), "editingCommentsClick(this, true);", "jsonly")
			. ' ' . (preg_match('~\n~', $row["Comment"])
				? "<textarea name='Comment' rows='2' cols='20'" . ($comments ? "" : " class='hidden'") . ">" . h($row["Comment"]) . "</textarea>"
				: '<input name="Comment" value="' . h($row["Comment"]) . '" data-maxlength="' . (min_version(5.5) ? 2048 : 60) . '"' . ($comments ? "" : " class='hidden'") . '>'
			)
		: '')
	;
	?>
<p>
<input type="submit" value="<?php echo lang(16); ?>">
<?php } ?>

<?php if ($TABLE != "") { ?>
<input type="submit" name="drop" value="<?php echo lang(132); ?>"><?php echo confirm(lang(183, $TABLE)); ?>
<?php } ?>
<?php
if ($partition_by && (JUSH == 'sql' || $TABLE == "")) {
	$partition_table = preg_match('~RANGE|LIST~', $row["partition_by"]);
	print_fieldset("partition", lang(184), $row["partition_by"]);
	echo "<p>" . html_select("partition_by", array_merge(array(""), $partition_by), $row["partition_by"]) . on_help("event.target.value.replace(/./, 'PARTITION BY \$&')", 1) . script("qsl('select').onchange = partitionByChange;");
	echo "(<input name='partition' value='" . h($row["partition"]) . "'>)\n";
	echo lang(185) . ": <input type='number' name='partitions' class='size" . ($partition_table || !$row["partition_by"] ? " hidden" : "") . "' value='" . h($row["partitions"]) . "'>\n";
	echo "<table id='partition-table'" . ($partition_table ? "" : " class='hidden'") . ">\n";
	echo "<thead><tr><th>" . lang(186) . "<th>" . lang(187) . "</thead>\n";
	foreach ($row["partition_names"] as $key => $val) {
		echo '<tr>';
		echo '<td><input name="partition_names[]" value="' . h($val) . '" autocapitalize="off">';
		echo ($key == count($row["partition_names"]) - 1 ? script("qsl('input').oninput = partitionNameChange;") : '');
		echo '<td><input name="partition_values[]" value="' . h(idx($row["partition_values"], $key)) . '">';
	}
	echo "</table>\n</div></fieldset>\n";
}
echo input_token();
?>
</form>
<?php
} elseif (isset($_GET["indexes"])) {
	?>
<?php
$TABLE = $_GET["indexes"];
$index_types = array("PRIMARY", "UNIQUE", "INDEX");
$table_status = table_status1($TABLE, true);
$index_algorithms = driver()->indexAlgorithms($table_status);
if (preg_match('~MyISAM|M?aria' . (min_version(5.6, '10.0.5') ? '|InnoDB' : '') . '~i', $table_status["Engine"])) {
	$index_types[] = "FULLTEXT";
}
if (preg_match('~MyISAM|M?aria' . (min_version(5.7, '10.2.2') ? '|InnoDB' : '') . '~i', $table_status["Engine"])) {
	$index_types[] = "SPATIAL";
}
$indexes = indexes($TABLE);
$fields = fields($TABLE);
$primary = array();
if (JUSH == "mongo") { // doesn't support primary key
	$primary = $indexes["_id_"];
	unset($index_types[0]);
	unset($indexes["_id_"]);
}
$row = $_POST;
if ($row) {
	save_settings(array("index_options" => $row["options"]));
}
if ($_POST && !$error && !$_POST["add"] && !$_POST["drop_col"]) {
	$alter = array();
	foreach ($row["indexes"] as $index) {
		$name = $index["name"];
		if (in_array($index["type"], $index_types)) {
			$columns = array();
			$lengths = array();
			$descs = array();
			$index_condition = (support("partial_indexes") ? $index["partial"] : "");
			$index_algorithm = (in_array($index["algorithm"], $index_algorithms) ? $index["algorithm"] : "");
			$set = array();
			ksort($index["columns"]);
			foreach ($index["columns"] as $key => $column) {
				if ($column != "") {
					$length = idx($index["lengths"], $key);
					$desc = idx($index["descs"], $key);
					$set[] = ($fields[$column] ? idf_escape($column) : $column) . ($length ? "(" . (+$length) . ")" : "") . ($desc ? " DESC" : "");
					$columns[] = $column;
					$lengths[] = ($length ?: null);
					$descs[] = $desc;
				}
			}

			$existing = $indexes[$name];
			if ($existing) {
				ksort($existing["columns"]);
				ksort($existing["lengths"]);
				ksort($existing["descs"]);
				if (
					$index["type"] == $existing["type"]
					&& array_values($existing["columns"]) === $columns
					&& (!$existing["lengths"] || array_values($existing["lengths"]) === $lengths)
					&& array_values($existing["descs"]) === $descs
					&& $existing["partial"] == $index_condition
					&& (!$index_algorithms || $existing["algorithm"] == $index_algorithm)
				) {
					// skip existing index
					unset($indexes[$name]);
					continue;
				}
			}
			if ($columns) {
				$alter[] = array($index["type"], $name, $set, $index_algorithm, $index_condition);
			}
		}
	}

	// drop removed indexes
	foreach ($indexes as $name => $existing) {
		$alter[] = array($existing["type"], $name, "DROP");
	}
	if (!$alter) {
		redirect(ME . "table=" . urlencode($TABLE));
	}
	queries_redirect(ME . "table=" . urlencode($TABLE), lang(188), alter_indexes($TABLE, $alter));
}

page_header(lang(140), $error, array("table" => $TABLE), h($TABLE));

$fields_keys = array_keys($fields);
if ($_POST["add"]) {
	foreach ($row["indexes"] as $key => $index) {
		if ($index["columns"][count($index["columns"])] != "") {
			$row["indexes"][$key]["columns"][] = "";
		}
	}
	$index = end($row["indexes"]);
	if ($index["type"] || array_filter($index["columns"], 'strlen')) {
		$row["indexes"][] = array("columns" => array(1 => ""));
	}
}
if (!$row) {
	foreach ($indexes as $key => $index) {
		$indexes[$key]["name"] = $key;
		$indexes[$key]["columns"][] = "";
	}
	$indexes[] = array("columns" => array(1 => ""));
	$row["indexes"] = $indexes;
}
$lengths = (JUSH == "sql" || JUSH == "mssql");
$show_options = ($_POST ? $_POST["options"] : get_setting("index_options"));
?>

<form action="" method="post">
<div class="scrollable">
<table class="nowrap">
<thead><tr>
<th id="label-type"><?php echo lang(189); ?>
<?php
$idxopts = " class='idxopts" . ($show_options ? "" : " hidden") . "'";
if ($index_algorithms) {
	echo "<th id='label-algorithm'$idxopts>" . lang(190) . doc_link(array(
		'sql' => 'create-index.html#create-index-storage-engine-index-types',
		'mariadb' => 'storage-engine-index-types/',
		'pgsql' => 'indexes-types.html',
	));
}
?>
<th><input type="submit" class="wayoff"><?php
echo lang(191) . ($lengths ? "<span$idxopts> (" . lang(192) . ")</span>" : "");
if ($lengths || support("descidx")) {
	echo checkbox("options", 1, $show_options, lang(112), "indexOptionsShow(this.checked)", "jsonly") . "\n";
}
?>
<th id="label-name"><?php echo lang(193); ?>
<?php
if (support("partial_indexes")) {
	echo "<th id='label-condition'$idxopts>" . lang(194);
}
?>
<th><noscript><?php echo icon("plus", "add[0]", "+", lang(113)); ?></noscript>
</thead>
<?php
if ($primary) {
	echo "<tr><td>PRIMARY<td>";
	foreach ($primary["columns"] as $key => $column) {
		echo select_input(" disabled", $fields_keys, $column);
		echo "<label><input disabled type='checkbox'>" . lang(59) . "</label> ";
	}
	echo "<td><td>\n";
}
$j = 1;
foreach ($row["indexes"] as $index) {
	if (!$_POST["drop_col"] || $j != key($_POST["drop_col"])) {
		echo "<tr><td>" . html_select("indexes[$j][type]", array(-1 => "") + $index_types, $index["type"], ($j == count($row["indexes"]) ? "indexesAddRow.call(this);" : ""), "label-type");

		if ($index_algorithms) {
			echo "<td$idxopts>" . html_select("indexes[$j][algorithm]", array_merge(array(""), $index_algorithms), $index['algorithm'], "label-algorithm");
		}

		echo "<td>";
		ksort($index["columns"]);
		$i = 1;
		foreach ($index["columns"] as $key => $column) {
			echo "<span>" . select_input(
				" name='indexes[$j][columns][$i]' title='" . lang(48) . "'",
				($fields && ($column == "" || $fields[$column]) ? array_combine($fields_keys, $fields_keys) : array()),
				$column,
				"partial(" . ($i == count($index["columns"]) ? "indexesAddColumn" : "indexesChangeColumn") . ", '" . js_escape(JUSH == "sql" ? "" : $_GET["indexes"] . "_") . "')"
			);
			echo "<span$idxopts>";
			echo ($lengths ? "<input type='number' name='indexes[$j][lengths][$i]' class='size' value='" . h(idx($index["lengths"], $key)) . "' title='" . lang(111) . "'>" : "");
			echo (support("descidx") ? checkbox("indexes[$j][descs][$i]", 1, idx($index["descs"], $key), lang(59)) : "");
			echo "</span> </span>";
			$i++;
		}

		echo "<td><input name='indexes[$j][name]' value='" . h($index["name"]) . "' autocapitalize='off' aria-labelledby='label-name'>\n";
		if (support("partial_indexes")) {
			echo "<td$idxopts><input name='indexes[$j][partial]' value='" . h($index["partial"]) . "' autocapitalize='off' aria-labelledby='label-condition'>\n";
		}
		echo "<td>" . icon("cross", "drop_col[$j]", "x", lang(116)) . script("qsl('button').onclick = partial(editingRemoveRow, 'indexes\$1[type]');");
	}
	$j++;
}
?>
</table>
</div>
<p>
<input type="submit" value="<?php echo lang(16); ?>">
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["database"])) {
	?>
<?php
$row = $_POST;

if ($_POST && !$error && !$_POST["add"]) {
	$name = trim($row["name"]);
	if ($_POST["drop"]) {
		$_GET["db"] = ""; // to save in global history
		queries_redirect(remove_from_uri("db|database"), lang(195), drop_databases(array(DB)));
	} elseif (DB !== $name) {
		// create or rename database
		if (DB != "") {
			$_GET["db"] = $name;
			queries_redirect(preg_replace('~\bdb=[^&]*&~', '', ME) . "db=" . urlencode($name), lang(196), rename_database($name, $row["collation"]));
		} else {
			$databases = explode("\n", str_replace("\r", "", $name));
			$success = true;
			$last = "";
			foreach ($databases as $db) {
				if (count($databases) == 1 || $db != "") { // ignore empty lines but always try to create single database
					if (!create_database($db, $row["collation"])) {
						$success = false;
					}
					$last = $db;
				}
			}
			restart_session();
			set_session("dbs", null);
			queries_redirect(ME . "db=" . urlencode($last), lang(197), $success);
		}
	} else {
		// alter database
		if (!$row["collation"]) {
			redirect(substr(ME, 0, -1));
		}
		query_redirect("ALTER DATABASE " . idf_escape($name) . (preg_match('~^[a-z0-9_]+$~i', $row["collation"]) ? " COLLATE $row[collation]" : ""), substr(ME, 0, -1), lang(198));
	}
}

page_header(DB != "" ? lang(67) : lang(120), $error, array(), h(DB));

$collations = collations();
$name = DB;
if ($_POST) {
	$name = $row["name"];
} elseif (DB != "") {
	$row["collation"] = db_collation(DB, $collations);
} elseif (JUSH == "sql") {
	// propose database name with limited privileges
	foreach (get_vals("SHOW GRANTS") as $grant) {
		if (preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\.\*)?~', $grant, $match) && $match[1]) {
			$name = stripcslashes(idf_unescape("`$match[2]`"));
			break;
		}
	}
}
?>

<form action="" method="post">
<p>
<?php
echo ($_POST["add"] || strpos($name, "\n")
	? '<textarea autofocus name="name" rows="10" cols="40">' . h($name) . '</textarea><br>'
	: '<input name="name" autofocus value="' . h($name) . '" data-maxlength="64" autocapitalize="off">'
) . "\n" . ($collations ? html_select("collation", array("" => "(" . lang(106) . ")") + $collations, $row["collation"]) . doc_link(array(
	'sql' => "charset-charsets.html",
	'mariadb' => "supported-character-sets-and-collations/",
	'mssql' => "relational-databases/system-functions/sys-fn-helpcollations-transact-sql",
)) : "");
?>
<input type="submit" value="<?php echo lang(16); ?>">
<?php
if (DB != "") {
	echo "<input type='submit' name='drop' value='" . lang(132) . "'>" . confirm(lang(183, DB)) . "\n";
} elseif (!$_POST["add"] && $_GET["db"] == "") {
	echo icon("plus", "add[0]", "+", lang(113)) . "\n";
}
echo input_token();
?>
</form>
<?php
} elseif (isset($_GET["scheme"])) {
	?>
<?php
$row = $_POST;

if ($_POST && !$error) {
	$link = preg_replace('~ns=[^&]*&~', '', ME) . "ns=";
	if ($_POST["drop"]) {
		query_redirect("DROP SCHEMA " . idf_escape($_GET["ns"]), $link, lang(199));
	} else {
		$name = trim($row["name"]);
		$link .= urlencode($name);
		if ($_GET["ns"] == "") {
			query_redirect("CREATE SCHEMA " . idf_escape($name), $link, lang(200));
		} elseif ($_GET["ns"] != $name) {
			query_redirect("ALTER SCHEMA " . idf_escape($_GET["ns"]) . " RENAME TO " . idf_escape($name), $link, lang(201)); //! sp_rename in MS SQL
		} else {
			redirect($link);
		}
	}
}

page_header($_GET["ns"] != "" ? lang(68) : lang(69), $error);

if (!$row) {
	$row["name"] = $_GET["ns"];
}
?>

<form action="" method="post">
<p><input name="name" autofocus value="<?php echo h($row["name"]); ?>" autocapitalize="off">
<input type="submit" value="<?php echo lang(16); ?>">
<?php
if ($_GET["ns"] != "") {
	echo "<input type='submit' name='drop' value='" . lang(132) . "'>" . confirm(lang(183, $_GET["ns"])) . "\n";
}
echo input_token();
?>
</form>
<?php
} elseif (isset($_GET["call"])) {
	?>
<?php
$PROCEDURE = ($_GET["name"] ?: $_GET["call"]);
page_header(lang(202) . ": " . h($PROCEDURE), $error);

$routine = routine($_GET["call"], (isset($_GET["callf"]) ? "FUNCTION" : "PROCEDURE"));
$in = array();
$out = array();
foreach ($routine["fields"] as $i => $field) {
	if (substr($field["inout"], -3) == "OUT" && JUSH == 'sql') {
		$out[$i] = "@" . idf_escape($field["field"]) . " AS " . idf_escape($field["field"]);
	}
	if (!$field["inout"] || substr($field["inout"], 0, 2) == "IN") {
		$in[] = $i;
	}
}

if (!$error && $_POST) {
	$call = array();
	foreach ($routine["fields"] as $key => $field) {
		$val = "";
		if (in_array($key, $in)) {
			$val = process_input($field);
			if ($val === false) {
				$val = "''";
			}
			if (isset($out[$key])) {
				connection()->query("SET @" . idf_escape($field["field"]) . " = $val");
			}
		}
		if (isset($out[$key])) {
			$call[] = "@" . idf_escape($field["field"]);
		} elseif (in_array($key, $in)) {
			$call[] = $val;
		}
	}

	$query = (isset($_GET["callf"]) ? "SELECT " : "CALL ") . ($routine["returns"]["type"] == "record" ? "* FROM " : "") . table($PROCEDURE) . "(" . implode(", ", $call) . ")";
	$start = microtime(true);
	$result = connection()->multi_query($query);
	$affected = connection()->affected_rows; // getting warnings overwrites this
	echo adminer()->selectQuery($query, $start, !$result);

	if (!$result) {
		echo "<p class='error'>" . error() . "\n";
	} else {
		$connection2 = connect();
		if ($connection2) {
			$connection2->select_db(DB);
		}

		do {
			$result = connection()->store_result();
			if (is_object($result)) {
				print_select_result($result, $connection2);
			} else {
				echo "<p class='message'>" . lang(203, $affected)
					. " <span class='time'>" . @date("H:i:s") . "</span>\n" // @ - time zone may be not set
				;
			}
		} while (connection()->next_result());

		if ($out) {
			print_select_result(connection()->query("SELECT " . implode(", ", $out)));
		}
	}
}
?>

<form action="" method="post">
<?php
if ($in) {
	echo "<table class='layout'>\n";
	foreach ($in as $key) {
		$field = $routine["fields"][$key];
		$name = $field["field"];
		echo "<tr><th>" . adminer()->fieldName($field);
		$value = idx($_POST["fields"], $name);
		if ($value != "") {
			if ($field["type"] == "set") {
				$value = implode(",", $value);
			}
		}
		input($field, $value, idx($_POST["function"], $name, "")); // param name can be empty
		echo "\n";
	}
	echo "</table>\n";
}
?>
<p>
<input type="submit" value="<?php echo lang(202); ?>">
<?php echo input_token(); ?>
</form>

<pre>
<?php
/** Format string as table row
* @return string HTML
*/
function pre_tr(string $s): string {
	return preg_replace('~^~m', '<tr>', preg_replace('~\|~', '<td>', preg_replace('~\|$~m', "", rtrim($s))));
}

$table = '(\+--[-+]+\+\n)';
$row = '(\| .* \|\n)';
echo preg_replace_callback(
	"~^$table?$row$table?($row*)$table?~m",
	function ($match) {
		$first_row = pre_tr($match[2]);
		return "<table>\n" . ($match[1] ? "<thead>$first_row</thead>\n" : $first_row) . pre_tr($match[4]) . "\n</table>";
	},
	preg_replace(
		'~(\n(    -|mysql)&gt; )(.+)~',
		"\\1<code class='jush-sql'>\\3</code>",
		preg_replace('~(.+)\n---+\n~', "<b>\\1</b>\n", h($routine['comment']))
	)
);
?>
</pre>
<?php
} elseif (isset($_GET["foreign"])) {
	?>
<?php
$TABLE = $_GET["foreign"];
$name = $_GET["name"];
$row = $_POST;

if ($_POST && !$error && !$_POST["add"] && !$_POST["change"] && !$_POST["change-js"]) {
	if (!$_POST["drop"]) {
		$row["source"] = array_filter($row["source"], 'strlen');
		ksort($row["source"]); // enforce input order
		$target = array();
		foreach ($row["source"] as $key => $val) {
			$target[$key] = $row["target"][$key];
		}
		$row["target"] = $target;
	}

	if (JUSH == "sqlite") {
		$result = recreate_table($TABLE, $TABLE, array(), array(), array(" $name" => ($row["drop"] ? "" : " " . format_foreign_key($row))));
	} else {
		$alter = "ALTER TABLE " . table($TABLE);
		$result = ($name == "" || queries("$alter DROP " . (JUSH == "sql" ? "FOREIGN KEY " : "CONSTRAINT ") . idf_escape($name)));
		if (!$row["drop"]) {
			$result = queries("$alter ADD" . format_foreign_key($row));
		}
	}
	queries_redirect(
		ME . "table=" . urlencode($TABLE),
		($row["drop"] ? lang(204) : ($name != "" ? lang(205) : lang(206))),
		$result
	);
	if (!$row["drop"]) {
		$error = lang(207); //! no partitioning
	}
}

page_header(lang(208), $error, array("table" => $TABLE), h($TABLE));

if ($_POST) {
	ksort($row["source"]);
	if ($_POST["add"]) {
		$row["source"][] = "";
	} elseif ($_POST["change"] || $_POST["change-js"]) {
		$row["target"] = array();
	}
} elseif ($name != "") {
	$foreign_keys = foreign_keys($TABLE);
	$row = $foreign_keys[$name];
	$row["source"][] = "";
} else {
	$row["table"] = $TABLE;
	$row["source"] = array("");
}
?>

<form action="" method="post">
<?php
$source = array_keys(fields($TABLE)); //! no text and blob
if ($row["db"] != "") {
	connection()->select_db($row["db"]);
}
if ($row["ns"] != "") {
	$orig_schema = get_schema();
	set_schema($row["ns"]);
}
$referencable = array_keys(array_filter(table_status('', true), 'Adminer\fk_support'));
$target = array_keys(fields(in_array($row["table"], $referencable) ? $row["table"] : reset($referencable)));
$onchange = "this.form['change-js'].value = '1'; this.form.submit();";
echo "<p><label>" . lang(209) . ": " . html_select("table", $referencable, $row["table"], $onchange) . "</label>\n";
if (support("scheme")) {
	$schemas = array_filter(adminer()->schemas(), function ($schema) {
		return !preg_match('~^information_schema$~i', $schema);
	});
	echo "<label>" . lang(79) . ": " . html_select("ns", $schemas, $row["ns"] != "" ? $row["ns"] : $_GET["ns"], $onchange) . "</label>";
	if ($row["ns"] != "") {
		set_schema($orig_schema);
	}
} elseif (JUSH != "sqlite") {
	$dbs = array();
	foreach (adminer()->databases() as $db) {
		if (!information_schema($db)) {
			$dbs[] = $db;
		}
	}
	echo "<label>" . lang(78) . ": " . html_select("db", $dbs, $row["db"] != "" ? $row["db"] : $_GET["db"], $onchange) . "</label>";
}
echo input_hidden("change-js");
?>
<noscript><p><input type="submit" name="change" value="<?php echo lang(210); ?>"></noscript>
<table>
<thead><tr><th id="label-source"><?php echo lang(142); ?><th id="label-target"><?php echo lang(143); ?></thead>
<?php
$j = 0;
foreach ($row["source"] as $key => $val) {
	echo "<tr>";
	echo "<td>" . html_select("source[" . (+$key) . "]", array(-1 => "") + $source, $val, ($j == count($row["source"]) - 1 ? "foreignAddRow.call(this);" : ""), "label-source");
	echo "<td>" . html_select("target[" . (+$key) . "]", $target, idx($row["target"], $key), "", "label-target");
	$j++;
}
?>
</table>
<p>
<label><?php echo lang(108); ?>: <?php echo html_select("on_delete", array(-1 => "") + explode("|", driver()->onActions), $row["on_delete"]); ?></label>
<label><?php echo lang(107); ?>: <?php echo html_select("on_update", array(-1 => "") + explode("|", driver()->onActions), $row["on_update"]); ?></label>
<?php echo doc_link(array(
	'sql' => "innodb-foreign-key-constraints.html",
	'mariadb' => "foreign-keys/",
	'pgsql' => "sql-createtable.html#SQL-CREATETABLE-REFERENCES",
	'mssql' => "t-sql/statements/create-table-transact-sql",
	'oracle' => "SQLRF01111",
)); ?>
<p>
<input type="submit" value="<?php echo lang(16); ?>">
<noscript><p><input type="submit" name="add" value="<?php echo lang(211); ?>"></noscript>
<?php if ($name != "") { ?>
<input type="submit" name="drop" value="<?php echo lang(132); ?>"><?php echo confirm(lang(183, $name)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["view"])) {
	?>
<?php
$TABLE = $_GET["view"];
$row = $_POST;
$orig_type = "VIEW";
if (JUSH == "pgsql" && $TABLE != "") {
	$status = table_status1($TABLE);
	$orig_type = strtoupper($status["Engine"]);
}

if ($_POST && !$error) {
	$name = trim($row["name"]);
	$as = " AS\n$row[select]";
	$location = ME . "table=" . urlencode($name);
	$message = lang(212);

	$type = ($_POST["materialized"] ? "MATERIALIZED VIEW" : "VIEW");

	if (!$_POST["drop"] && $TABLE == $name && JUSH != "sqlite" && $type == "VIEW" && $orig_type == "VIEW") {
		query_redirect((JUSH == "mssql" ? "ALTER" : "CREATE OR REPLACE") . " VIEW " . table($name) . $as, $location, $message);
	} else {
		$temp_name = $name . "_adminer_" . uniqid();
		drop_create(
			"DROP $orig_type " . table($TABLE),
			"CREATE $type " . table($name) . $as,
			"DROP $type " . table($name),
			"CREATE $type " . table($temp_name) . $as,
			"DROP $type " . table($temp_name),
			($_POST["drop"] ? substr(ME, 0, -1) : $location),
			lang(213),
			$message,
			lang(214),
			$TABLE,
			$name
		);
	}
}

if (!$_POST && $TABLE != "") {
	$row = view($TABLE);
	$row["name"] = $TABLE;
	$row["materialized"] = ($orig_type != "VIEW");
	if (!$error) {
		$error = error();
	}
}

page_header(($TABLE != "" ? lang(44) : lang(215)), $error, array("table" => $TABLE), h($TABLE));
?>

<form action="" method="post">
<p><?php echo lang(193); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo (support("materializedview") ? " " . checkbox("materialized", 1, $row["materialized"], lang(136)) : ""); ?>
<p><?php textarea("select", $row["select"]); ?>
<p>
<input type="submit" value="<?php echo lang(16); ?>">
<?php if ($TABLE != "") { ?>
<input type="submit" name="drop" value="<?php echo lang(132); ?>"><?php echo confirm(lang(183, $TABLE)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["event"])) {
	?>
<?php
$EVENT = $_GET["event"];
$intervals = array("YEAR", "QUARTER", "MONTH", "DAY", "HOUR", "MINUTE", "WEEK", "SECOND", "YEAR_MONTH", "DAY_HOUR", "DAY_MINUTE", "DAY_SECOND", "HOUR_MINUTE", "HOUR_SECOND", "MINUTE_SECOND");
$statuses = array("ENABLED" => "ENABLE", "DISABLED" => "DISABLE", "SLAVESIDE_DISABLED" => "DISABLE ON SLAVE");
$row = $_POST;

if ($_POST && !$error) {
	if ($_POST["drop"]) {
		query_redirect("DROP EVENT " . idf_escape($EVENT), substr(ME, 0, -1), lang(216));
	} elseif (in_array($row["INTERVAL_FIELD"], $intervals) && isset($statuses[$row["STATUS"]])) {
		$schedule = "\nON SCHEDULE " . ($row["INTERVAL_VALUE"]
			? "EVERY " . q($row["INTERVAL_VALUE"]) . " $row[INTERVAL_FIELD]"
			. ($row["STARTS"] ? " STARTS " . q($row["STARTS"]) : "")
			. ($row["ENDS"] ? " ENDS " . q($row["ENDS"]) : "") //! ALTER EVENT doesn't drop ENDS - MySQL bug #39173
			: "AT " . q($row["STARTS"])
			) . " ON COMPLETION" . ($row["ON_COMPLETION"] ? "" : " NOT") . " PRESERVE"
		;

		queries_redirect(
			substr(ME, 0, -1),
			($EVENT != "" ? lang(217) : lang(218)),
			queries(
				($EVENT != ""
				? "ALTER EVENT " . idf_escape($EVENT) . $schedule . ($EVENT != $row["EVENT_NAME"] ? "\nRENAME TO " . idf_escape($row["EVENT_NAME"]) : "")
				: "CREATE EVENT " . idf_escape($row["EVENT_NAME"]) . $schedule
				) . "\n" . $statuses[$row["STATUS"]] . " COMMENT " . q($row["EVENT_COMMENT"])
				. rtrim(" DO\n$row[EVENT_DEFINITION]", ";") . ";"
			)
		);
	}
}

page_header(($EVENT != "" ? lang(219) . ": " . h($EVENT) : lang(220)), $error);

if (!$row && $EVENT != "") {
	$rows = get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = " . q(DB) . " AND EVENT_NAME = " . q($EVENT));
	$row = reset($rows);
}
?>

<form action="" method="post">
<table class="layout">
<tr><th><?php echo lang(193); ?><td><input name="EVENT_NAME" value="<?php echo h($row["EVENT_NAME"]); ?>" data-maxlength="64" autocapitalize="off">
<tr><th title="datetime"><?php echo lang(221); ?><td><input name="STARTS" value="<?php echo h("$row[EXECUTE_AT]$row[STARTS]"); ?>">
<tr><th title="datetime"><?php echo lang(222); ?><td><input name="ENDS" value="<?php echo h($row["ENDS"]); ?>">
<tr><th><?php echo lang(223); ?><td><input type="number" name="INTERVAL_VALUE" value="<?php echo h($row["INTERVAL_VALUE"]); ?>" class="size"> <?php echo html_select("INTERVAL_FIELD", $intervals, $row["INTERVAL_FIELD"]); ?>
<tr><th><?php echo lang(123); ?><td><?php echo html_select("STATUS", $statuses, $row["STATUS"]); ?>
<tr><th><?php echo lang(50); ?><td><input name="EVENT_COMMENT" value="<?php echo h($row["EVENT_COMMENT"]); ?>" data-maxlength="64">
<tr><th><td><?php echo checkbox("ON_COMPLETION", "PRESERVE", $row["ON_COMPLETION"] == "PRESERVE", lang(224)); ?>
</table>
<p><?php textarea("EVENT_DEFINITION", $row["EVENT_DEFINITION"]); ?>
<p>
<input type="submit" value="<?php echo lang(16); ?>">
<?php if ($EVENT != "") { ?>
<input type="submit" name="drop" value="<?php echo lang(132); ?>"><?php echo confirm(lang(183, $EVENT)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["procedure"])) {
	?>
<?php
$PROCEDURE = ($_GET["name"] ?: $_GET["procedure"]);
$routine = (isset($_GET["function"]) ? "FUNCTION" : "PROCEDURE");
$row = $_POST;
$row["fields"] = (array) $row["fields"];

if ($_POST && !process_fields($row["fields"]) && !$error) {
	$orig = routine($_GET["procedure"], $routine);
	$temp_name = "$row[name]_adminer_" . uniqid();
	foreach ($row["fields"] as $key => $field) {
		if ($field["field"] == "") {
			unset($row["fields"][$key]);
		}
	}
	drop_create(
		"DROP $routine " . routine_id($PROCEDURE, $orig),
		create_routine($routine, $row),
		"DROP $routine " . routine_id($row["name"], $row),
		create_routine($routine, array("name" => $temp_name) + $row),
		"DROP $routine " . routine_id($temp_name, $row),
		substr(ME, 0, -1),
		lang(225),
		lang(226),
		lang(227),
		$PROCEDURE,
		$row["name"]
	);
}

page_header(($PROCEDURE != "" ? (isset($_GET["function"]) ? lang(228) : lang(229)) . ": " . h($PROCEDURE) : (isset($_GET["function"]) ? lang(230) : lang(231))), $error);

if (!$_POST) {
	if ($PROCEDURE == "") {
		$row["language"] = "sql";
	} else {
		$row = routine($_GET["procedure"], $routine);
		$row["name"] = $PROCEDURE;
	}
}

$collations = get_vals("SHOW CHARACTER SET");
sort($collations);
$routine_languages = routine_languages();
echo ($collations ? "<datalist id='collations'>" . optionlist($collations) . "</datalist>" : "");
?>

<form action="" method="post" id="form">
<p><?php echo lang(193); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo ($routine_languages ? "<label>" . lang(21) . ": " . html_select("language", $routine_languages, $row["language"]) . "</label>\n" : ""); ?>
<input type="submit" value="<?php echo lang(16); ?>">
<div class="scrollable">
<table class="nowrap">
<?php
edit_fields($row["fields"], $collations, $routine);
if (isset($_GET["function"])) {
	echo "<tr><td>" . lang(232);
	edit_type("returns", (array) $row["returns"], $collations, array(), (JUSH == "pgsql" ? array("void", "trigger") : array()));
}
?>
</table>
<?php echo script("editFields();"); ?>
</div>
<p><?php textarea("definition", $row["definition"], 20); ?>
<p>
<input type="submit" value="<?php echo lang(16); ?>">
<?php if ($PROCEDURE != "") { ?>
<input type="submit" name="drop" value="<?php echo lang(132); ?>"><?php echo confirm(lang(183, $PROCEDURE)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["sequence"])) {
	?>
<?php
$SEQUENCE = $_GET["sequence"];
$row = $_POST;

if ($_POST && !$error) {
	$link = substr(ME, 0, -1);
	$name = trim($row["name"]);
	if ($_POST["drop"]) {
		query_redirect("DROP SEQUENCE " . idf_escape($SEQUENCE), $link, lang(233));
	} elseif ($SEQUENCE == "") {
		query_redirect("CREATE SEQUENCE " . idf_escape($name), $link, lang(234));
	} elseif ($SEQUENCE != $name) {
		query_redirect("ALTER SEQUENCE " . idf_escape($SEQUENCE) . " RENAME TO " . idf_escape($name), $link, lang(235));
	} else {
		redirect($link);
	}
}

page_header($SEQUENCE != "" ? lang(236) . ": " . h($SEQUENCE) : lang(237), $error);

if (!$row) {
	$row["name"] = $SEQUENCE;
}
?>

<form action="" method="post">
<p><input name="name" value="<?php echo h($row["name"]); ?>" autocapitalize="off">
<input type="submit" value="<?php echo lang(16); ?>">
<?php
if ($SEQUENCE != "") {
	echo "<input type='submit' name='drop' value='" . lang(132) . "'>" . confirm(lang(183, $SEQUENCE)) . "\n";
}
echo input_token();
?>
</form>
<?php
} elseif (isset($_GET["type"])) {
	?>
<?php
$TYPE = $_GET["type"];
$row = $_POST;

if ($_POST && !$error) {
	$link = substr(ME, 0, -1);
	if ($_POST["drop"]) {
		query_redirect("DROP TYPE " . idf_escape($TYPE), $link, lang(238));
	} else {
		query_redirect("CREATE TYPE " . idf_escape(trim($row["name"])) . " $row[as]", $link, lang(239));
	}
}

page_header($TYPE != "" ? lang(240) . ": " . h($TYPE) : lang(241), $error);

if (!$row) {
	$row["as"] = "AS ";
}
?>

<form action="" method="post">
<p>
<?php
if ($TYPE != "") {
	$types = driver()->types();
	$enums = type_values($types[$TYPE]);
	if ($enums) {
		echo "<code class='jush-" . JUSH . "'>ENUM (" . h($enums) . ")</code>\n<p>";
	}
	echo "<input type='submit' name='drop' value='" . lang(132) . "'>" . confirm(lang(183, $TYPE)) . "\n";
} else {
	echo lang(193) . ": <input name='name' value='" . h($row['name']) . "' autocapitalize='off'>\n";
	echo doc_link(array(
		'pgsql' => "datatype-enum.html",
	), "?");
	textarea("as", $row["as"]);
	echo "<p><input type='submit' value='" . lang(16) . "'>\n";
}
echo input_token();
?>
</form>
<?php
} elseif (isset($_GET["check"])) {
	?>
<?php
$TABLE = $_GET["check"];
$name = $_GET["name"];
$row = $_POST;

if ($row && !$error) {
	if (JUSH == "sqlite") {
		$result = recreate_table($TABLE, $TABLE, array(), array(), array(), "", array(), "$name", ($row["drop"] ? "" : $row["clause"]));
	} else {
		$result = ($name == "" || queries("ALTER TABLE " . table($TABLE) . " DROP CONSTRAINT " . idf_escape($name)));
		if (!$row["drop"]) {
			$result = queries("ALTER TABLE " . table($TABLE) . " ADD" . ($row["name"] != "" ? " CONSTRAINT " . idf_escape($row["name"]) : "") . " CHECK ($row[clause])"); //! SQL injection
		}
	}
	queries_redirect(
		ME . "table=" . urlencode($TABLE),
		($row["drop"] ? lang(242) : ($name != "" ? lang(243) : lang(244))),
		$result
	);
}

page_header(($name != "" ? lang(245) . ": " . h($name) : lang(147)), $error, array("table" => $TABLE));

if (!$row) {
	$checks = driver()->checkConstraints($TABLE);
	$row = array("name" => $name, "clause" => $checks[$name]);
}
?>

<form action="" method="post">
<p><?php
if (JUSH != "sqlite") {
	echo lang(193) . ': <input name="name" value="' . h($row["name"]) . '" data-maxlength="64" autocapitalize="off"> ';
}
echo doc_link(array(
	'sql' => "create-table-check-constraints.html",
	'mariadb' => "constraint/",
	'pgsql' => "ddl-constraints.html#DDL-CONSTRAINTS-CHECK-CONSTRAINTS",
	'mssql' => "relational-databases/tables/create-check-constraints",
	'sqlite' => "lang_createtable.html#check_constraints",
), "?");
?>
<p><?php textarea("clause", $row["clause"]); ?>
<p><input type="submit" value="<?php echo lang(16); ?>">
<?php if ($name != "") { ?>
<input type="submit" name="drop" value="<?php echo lang(132); ?>"><?php echo confirm(lang(183, $name)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["trigger"])) {
	?>
<?php
$TABLE = $_GET["trigger"];
$name = "$_GET[name]";
$trigger_options = trigger_options();
$row = (array) trigger($name, $TABLE) + array("Trigger" => $TABLE . "_bi");

if ($_POST) {
	if (!$error && in_array($_POST["Timing"], $trigger_options["Timing"]) && in_array($_POST["Event"], $trigger_options["Event"]) && in_array($_POST["Type"], $trigger_options["Type"])) {
		// don't use drop_create() because there may not be more triggers for the same action
		$on = " ON " . table($TABLE);
		$drop = "DROP TRIGGER " . idf_escape($name) . (JUSH == "pgsql" ? $on : "");
		$location = ME . "table=" . urlencode($TABLE);
		if ($_POST["drop"]) {
			query_redirect($drop, $location, lang(246));
		} else {
			if ($name != "") {
				queries($drop);
			}
			queries_redirect(
				$location,
				($name != "" ? lang(247) : lang(248)),
				queries(create_trigger($on, $_POST))
			);
			if ($name != "") {
				queries(create_trigger($on, $row + array("Type" => reset($trigger_options["Type"]))));
			}
		}
	}
	$row = $_POST;
}

page_header(($name != "" ? lang(249) . ": " . h($name) : lang(250)), $error, array("table" => $TABLE));
?>

<form action="" method="post" id="form">
<table class="layout">
<tr><th><?php echo lang(251); ?><td><?php echo html_select("Timing", $trigger_options["Timing"], $row["Timing"], "triggerChange(/^" . preg_quote($TABLE, "/") . "_[ba][iud]$/, '" . js_escape($TABLE) . "', this.form);"); ?>
<tr><th><?php echo lang(252); ?><td><?php echo html_select("Event", $trigger_options["Event"], $row["Event"], "this.form['Timing'].onchange();"); ?>
<?php echo (in_array("UPDATE OF", $trigger_options["Event"]) ? " <input name='Of' value='" . h($row["Of"]) . "' class='hidden'>": ""); ?>
<tr><th><?php echo lang(49); ?><td><?php echo html_select("Type", $trigger_options["Type"], $row["Type"]); ?>
</table>
<p><?php echo lang(193); ?>: <input name="Trigger" value="<?php echo h($row["Trigger"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo script("qs('#form')['Timing'].onchange();"); ?>
<p><?php textarea("Statement", $row["Statement"]); ?>
<p>
<input type="submit" value="<?php echo lang(16); ?>">
<?php if ($name != "") { ?>
<input type="submit" name="drop" value="<?php echo lang(132); ?>"><?php echo confirm(lang(183, $name)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["user"])) {
	?>
<?php
$USER = $_GET["user"];
$privileges = array("" => array("All privileges" => ""));
foreach (get_rows("SHOW PRIVILEGES") as $row) {
	foreach (explode(",", ($row["Privilege"] == "Grant option" ? "" : $row["Context"])) as $context) {
		$privileges[$context][$row["Privilege"]] = $row["Comment"];
	}
}
$privileges["Server Admin"] += $privileges["File access on server"];
$privileges["Databases"]["Create routine"] = $privileges["Procedures"]["Create routine"]; // MySQL bug #30305
unset($privileges["Procedures"]["Create routine"]);
$privileges["Columns"] = array();
foreach (array("Select", "Insert", "Update", "References") as $val) {
	$privileges["Columns"][$val] = $privileges["Tables"][$val];
}
unset($privileges["Server Admin"]["Usage"]);
foreach ($privileges["Tables"] as $key => $val) {
	unset($privileges["Databases"][$key]);
}

$new_grants = array();
if ($_POST) {
	foreach ($_POST["objects"] as $key => $val) {
		$new_grants[$val] = (array) $new_grants[$val] + idx($_POST["grants"], $key, array());
	}
}
$grants = array();
$old_pass = "";

if (isset($_GET["host"]) && ($result = connection()->query("SHOW GRANTS FOR " . q($USER) . "@" . q($_GET["host"])))) { //! use information_schema for MySQL 5 - column names in column privileges are not escaped
	while ($row = $result->fetch_row()) {
		if (preg_match('~GRANT (.*) ON (.*) TO ~', $row[0], $match) && preg_match_all('~ *([^(,]*[^ ,(])( *\([^)]+\))?~', $match[1], $matches, PREG_SET_ORDER)) { //! escape the part between ON and TO
			foreach ($matches as $val) {
				if ($val[1] != "USAGE") {
					$grants["$match[2]$val[2]"][$val[1]] = true;
				}
				if (preg_match('~ WITH GRANT OPTION~', $row[0])) { //! don't check inside strings and identifiers
					$grants["$match[2]$val[2]"]["GRANT OPTION"] = true;
				}
			}
		}
		if (preg_match("~ IDENTIFIED BY PASSWORD '([^']+)~", $row[0], $match)) {
			$old_pass = $match[1];
		}
	}
}

if ($_POST && !$error) {
	$old_user = (isset($_GET["host"]) ? q($USER) . "@" . q($_GET["host"]) : "''");
	if ($_POST["drop"]) {
		query_redirect("DROP USER $old_user", ME . "privileges=", lang(253));
	} else {
		$new_user = q($_POST["user"]) . "@" . q($_POST["host"]); // if $_GET["host"] is not set then $new_user is always different
		$pass = $_POST["pass"];
		if ($pass != '' && !$_POST["hashed"] && !min_version(8)) {
			// compute hash in a separate query so that plain text password is not saved to history
			$pass = get_val("SELECT PASSWORD(" . q($pass) . ")");
			$error = !$pass;
		}

		$created = false;
		if (!$error) {
			if ($old_user != $new_user) {
				$created = queries((min_version(5) ? "CREATE USER" : "GRANT USAGE ON *.* TO") . " $new_user IDENTIFIED BY " . (min_version(8) ? "" : "PASSWORD ") . q($pass));
				$error = !$created;
			} elseif ($pass != $old_pass) {
				queries("SET PASSWORD FOR $new_user = " . q($pass));
			}
		}

		if (!$error) {
			$revoke = array();
			foreach ($new_grants as $object => $grant) {
				if (isset($_GET["grant"])) {
					$grant = array_filter($grant);
				}
				$grant = array_keys($grant);
				if (isset($_GET["grant"])) {
					// no rights to mysql.user table
					$revoke = array_diff(array_keys(array_filter($new_grants[$object], 'strlen')), $grant);
				} elseif ($old_user == $new_user) {
					$old_grant = array_keys((array) $grants[$object]);
					$revoke = array_diff($old_grant, $grant);
					$grant = array_diff($grant, $old_grant);
					unset($grants[$object]);
				}
				if (
					preg_match('~^(.+)\s*(\(.*\))?$~U', $object, $match) && (
					!grant("REVOKE", $revoke, $match[2], " ON $match[1] FROM $new_user") //! SQL injection
					|| !grant("GRANT", $grant, $match[2], " ON $match[1] TO $new_user"))
				) {
					$error = true;
					break;
				}
			}
		}

		if (!$error && isset($_GET["host"])) {
			if ($old_user != $new_user) {
				queries("DROP USER $old_user");
			} elseif (!isset($_GET["grant"])) {
				foreach ($grants as $object => $revoke) {
					if (preg_match('~^(.+)(\(.*\))?$~U', $object, $match)) {
						grant("REVOKE", array_keys($revoke), $match[2], " ON $match[1] FROM $new_user");
					}
				}
			}
		}

		queries_redirect(ME . "privileges=", (isset($_GET["host"]) ? lang(254) : lang(255)), !$error);

		if ($created) {
			// delete new user in case of an error
			connection()->query("DROP USER $new_user");
		}
	}
}

page_header((isset($_GET["host"]) ? lang(35) . ": " . h("$USER@$_GET[host]") : lang(155)), $error, array("privileges" => array('', lang(71))));

$row = $_POST;
if ($row) {
	$grants = $new_grants;
} else {
	$row = $_GET + array("host" => get_val("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)")); // create user on the same domain by default
	$row["pass"] = $old_pass;
	if ($old_pass != "") {
		$row["hashed"] = true;
	}
	$grants[(DB == "" || $grants ? "" : idf_escape(addcslashes(DB, "%_\\"))) . ".*"] = array();
}

?>
<form action="" method="post">
<table class="layout">
<tr><th><?php echo lang(34); ?><td><input name="host" data-maxlength="60" value="<?php echo h($row["host"]); ?>" autocapitalize="off">
<tr><th><?php echo lang(35); ?><td><input name="user" data-maxlength="80" value="<?php echo h($row["user"]); ?>" autocapitalize="off">
<tr><th><?php echo lang(36); ?><td><input name="pass" id="pass" value="<?php echo h($row["pass"]); ?>" autocomplete="new-password">
<?php echo ($row["hashed"] ? "" : script("typePassword(qs('#pass'));")); ?>
<?php echo (min_version(8) ? "" : checkbox("hashed", 1, $row["hashed"], lang(256), "typePassword(this.form['pass'], this.checked);")); ?>
</table>

<?php
//! MAX_* limits, REQUIRE
echo "<table class='odds'>\n";
echo "<thead><tr><th colspan='2'>" . lang(71) . doc_link(array('sql' => "grant.html#priv_level"));
$i = 0;
foreach ($grants as $object => $grant) {
	echo '<th>' . ($object != "*.*"
		? "<input name='objects[$i]' value='" . h($object) . "' size='10' autocapitalize='off'>"
		: input_hidden("objects[$i]", "*.*") . "*.*"
	); //! separate db, table, columns, PROCEDURE|FUNCTION, routine
	$i++;
}
echo "</thead>\n";

foreach (
	array(
		"" => "",
		"Server Admin" => lang(34),
		"Databases" => lang(37),
		"Tables" => lang(138),
		"Columns" => lang(48),
		"Procedures" => lang(257),
	) as $context => $desc
) {
	foreach ((array) $privileges[$context] as $privilege => $comment) {
		echo "<tr><td" . ($desc ? ">$desc<td" : " colspan='2'") . ' lang="en" title="' . h($comment) . '">' . h($privilege);
		$i = 0;
		foreach ($grants as $object => $grant) {
			$name = "'grants[$i][" . h(strtoupper($privilege)) . "]'";
			$value = $grant[strtoupper($privilege)];
			if ($context == "Server Admin" && $object != (isset($grants["*.*"]) ? "*.*" : ".*")) {
				echo "<td>";
			} elseif (isset($_GET["grant"])) {
				echo "<td><select name=$name><option><option value='1'" . ($value ? " selected" : "") . ">" . lang(258) . "<option value='0'" . ($value == "0" ? " selected" : "") . ">" . lang(259) . "</select>";
			} else {
				echo "<td align='center'><label class='block'>";
				echo "<input type='checkbox' name=$name value='1'" . ($value ? " checked" : "") . ($privilege == "All privileges"
					? " id='grants-$i-all'>" //! uncheck all except grant if all is checked
					: ">" . ($privilege == "Grant option" ? "" : script("qsl('input').onclick = function () { if (this.checked) formUncheck('grants-$i-all'); };")));
				echo "</label>";
			}
			$i++;
		}
	}
}

echo "</table>\n";
?>
<p>
<input type="submit" value="<?php echo lang(16); ?>">
<?php if (isset($_GET["host"])) { ?>
<input type="submit" name="drop" value="<?php echo lang(132); ?>"><?php echo confirm(lang(183, "$USER@$_GET[host]")); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["processlist"])) {
	?>
<?php
if (support("kill")) {
	if ($_POST && !$error) {
		$killed = 0;
		foreach ((array) $_POST["kill"] as $val) {
			if (adminer()->killProcess($val)) {
				$killed++;
			}
		}
		queries_redirect(ME . "processlist=", lang(260, $killed), $killed || !$_POST["kill"]);
	}
}

page_header(lang(121), $error);
?>

<form action="" method="post">
<div class="scrollable">
<table class="nowrap checkable odds">
<?php
echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");
// HTML valid because there is always at least one process
$i = -1;
foreach (adminer()->processList() as $i => $row) {
	if (!$i) {
		echo "<thead><tr lang='en'>" . (support("kill") ? "<th>" : "");
		foreach ($row as $key => $val) {
			echo "<th>$key" . doc_link(array(
				'sql' => "show-processlist.html#processlist_" . strtolower($key),
				'pgsql' => "monitoring-stats.html#PG-STAT-ACTIVITY-VIEW",
				'oracle' => "REFRN30223",
			));
		}
		echo "</thead>\n";
	}
	echo "<tr>" . (support("kill") ? "<td>" . checkbox("kill[]", $row[JUSH == "sql" ? "Id" : "pid"], 0) : "");
	foreach ($row as $key => $val) {
		echo "<td>" . (
			(JUSH == "sql" && $key == "Info" && preg_match("~Query|Killed~", $row["Command"]) && $val != "") ||
			(JUSH == "pgsql" && $key == "current_query" && $val != "<IDLE>") ||
			(JUSH == "oracle" && $key == "sql_text" && $val != "")
			? "<code class='jush-" . JUSH . "'>" . shorten_utf8($val, 100, "</code>") . ' <a href="' . h(ME . ($row["db"] != "" ? "db=" . urlencode($row["db"]) . "&" : "") . "sql=" . urlencode($val)) . '">' . lang(261) . '</a>'
			: h($val)
		);
	}
	echo "\n";
}
?>
</table>
</div>
<p>
<?php
if (support("kill")) {
	echo ($i + 1) . "/" . lang(262, max_connections());
	echo "<p><input type='submit' value='" . lang(263) . "'>\n";
}
echo input_token();
?>
</form>
<?php echo script("tableCheck();"); ?>
<?php
} elseif (isset($_GET["select"])) {
	?>
<?php
$TABLE = $_GET["select"];
$table_status = table_status1($TABLE);
$indexes = indexes($TABLE);
$fields = fields($TABLE);
$foreign_keys = column_foreign_keys($TABLE);
$oid = $table_status["Oid"];
$adminer_import = get_settings("adminer_import");

$rights = array(); // privilege => 0
$columns = array(); // selectable columns
$search_columns = array(); // searchable columns
$order_columns = array(); // searchable columns
$text_length = "";
foreach ($fields as $key => $field) {
	$name = adminer()->fieldName($field);
	$name_plain = html_entity_decode(strip_tags($name), ENT_QUOTES);
	if (isset($field["privileges"]["select"]) && $name != "") {
		$columns[$key] = $name_plain;
		if (is_shortable($field)) {
			$text_length = adminer()->selectLengthProcess();
		}
	}
	if (isset($field["privileges"]["where"]) && $name != "") {
		$search_columns[$key] = $name_plain;
	}
	if (isset($field["privileges"]["order"]) && $name != "") {
		$order_columns[$key] = $name_plain;
	}
	$rights += $field["privileges"];
}

list($select, $group) = adminer()->selectColumnsProcess($columns, $indexes);
$select = array_unique($select);
$group = array_unique($group);
$is_group = count($group) < count($select);
$where = adminer()->selectSearchProcess($fields, $indexes);
$order = adminer()->selectOrderProcess($fields, $indexes);
$limit = adminer()->selectLimitProcess();

if ($_GET["val"] && is_ajax()) {
	header("Content-Type: text/plain; charset=utf-8");
	foreach ($_GET["val"] as $unique_idf => $row) {
		$as = convert_field($fields[key($row)]);
		$select = array($as ?: idf_escape(key($row)));
		$where[] = where_check($unique_idf, $fields);
		$return = driver()->select($TABLE, $select, $where, $select);
		if ($return) {
			echo first($return->fetch_row());
		}
	}
	exit;
}

$primary = $unselected = array();
foreach ($indexes as $index) {
	if ($index["type"] == "PRIMARY") {
		$primary = array_flip($index["columns"]);
		$unselected = ($select ? $primary : array());
		foreach ($unselected as $key => $val) {
			if (in_array(idf_escape($key), $select)) {
				unset($unselected[$key]);
			}
		}
		break;
	}
}
if ($oid && !$primary) {
	$primary = $unselected = array($oid => 0);
	$indexes[] = array("type" => "PRIMARY", "columns" => array($oid));
}

if ($_POST && !$error) {
	$where_check = $where;
	if (!$_POST["all"] && is_array($_POST["check"])) {
		$checks = array();
		foreach ($_POST["check"] as $check) {
			$checks[] = where_check($check, $fields);
		}
		$where_check[] = "((" . implode(") OR (", $checks) . "))";
	}
	$where_check = ($where_check ? "\nWHERE " . implode(" AND ", $where_check) : "");
	if ($_POST["export"]) {
		save_settings(array("output" => $_POST["output"], "format" => $_POST["format"]), "adminer_import");
		dump_headers($TABLE);
		adminer()->dumpTable($TABLE, "");
		$from = ($select ? implode(", ", $select) : "*")
			. convert_fields($columns, $fields, $select)
			. "\nFROM " . table($TABLE);
		$group_by = ($group && $is_group ? "\nGROUP BY " . implode(", ", $group) : "") . ($order ? "\nORDER BY " . implode(", ", $order) : "");
		$query = "SELECT $from$where_check$group_by";
		if (is_array($_POST["check"]) && !$primary) {
			$union = array();
			foreach ($_POST["check"] as $val) {
				// where is not unique so OR can't be used
				$union[] = "(SELECT" . limit($from, "\nWHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($val, $fields) . $group_by, 1) . ")";
			}
			$query = implode(" UNION ALL ", $union);
		}
		adminer()->dumpData($TABLE, "table", $query);
		adminer()->dumpFooter();
		exit;
	}

	if (!adminer()->selectEmailProcess($where, $foreign_keys)) {
		if ($_POST["save"] || $_POST["delete"]) { // edit
			$result = true;
			$affected = 0;
			$set = array();
			if (!$_POST["delete"]) {
				foreach ($_POST["fields"] as $name => $val) {
					$val = process_input($fields[$name]);
					if ($val !== null && ($_POST["clone"] || $val !== false)) {
						$set[idf_escape($name)] = ($val !== false ? $val : idf_escape($name));
					}
				}
			}
			if ($_POST["delete"] || $set) {
				$query = ($_POST["clone"] ? "INTO " . table($TABLE) . " (" . implode(", ", array_keys($set)) . ")\nSELECT " . implode(", ", $set) . "\nFROM " . table($TABLE) : "");
				if ($_POST["all"] || ($primary && is_array($_POST["check"])) || $is_group) {
					$result = ($_POST["delete"]
						? driver()->delete($TABLE, $where_check)
						: ($_POST["clone"]
							? queries("INSERT $query$where_check" . driver()->insertReturning($TABLE))
							: driver()->update($TABLE, $set, $where_check)
						)
					);
					$affected = connection()->affected_rows;
					if (is_object($result)) { // PostgreSQL with RETURNING fills num_rows
						$affected += $result->num_rows;
					}
				} else {
					foreach ((array) $_POST["check"] as $val) {
						// where is not unique so OR can't be used
						$where2 = "\nWHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($val, $fields);
						$result = ($_POST["delete"]
							? driver()->delete($TABLE, $where2, 1)
							: ($_POST["clone"]
								? queries("INSERT" . limit1($TABLE, $query, $where2))
								: driver()->update($TABLE, $set, $where2, 1)
							)
						);
						if (!$result) {
							break;
						}
						$affected += connection()->affected_rows;
					}
				}
			}
			$message = lang(264, $affected);
			if ($_POST["clone"] && $result && $affected == 1) {
				$last_id = last_id($result);
				if ($last_id) {
					$message = lang(176, " $last_id");
				}
			}
			queries_redirect(remove_from_uri($_POST["all"] && $_POST["delete"] ? "page" : ""), $message, $result);
			if (!$_POST["delete"]) {
				$post_fields = (array) $_POST["fields"];
				edit_form($TABLE, array_intersect_key($fields, $post_fields), $post_fields, !$_POST["clone"], $error);
				page_footer();
				exit;
			}

		} elseif (!$_POST["import"]) { // modify
			if (!$_POST["val"]) {
				$error = lang(265);
			} else {
				$result = true;
				$affected = 0;
				foreach ($_POST["val"] as $unique_idf => $row) {
					$set = array();
					foreach ($row as $key => $val) {
						$key = bracket_escape($key, true); // true - back
						$set[idf_escape($key)] = (preg_match('~char|text~', $fields[$key]["type"]) || $val != "" ? adminer()->processInput($fields[$key], $val) : "NULL");
					}
					$result = driver()->update(
						$TABLE,
						$set,
						" WHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($unique_idf, $fields),
						($is_group || $primary ? 0 : 1),
						" "
					);
					if (!$result) {
						break;
					}
					$affected += connection()->affected_rows;
				}
				queries_redirect(remove_from_uri(), lang(264, $affected), $result);
			}

		} elseif (!is_string($file = get_file("csv_file", true))) {
			$error = upload_error($file);
		} elseif (!preg_match('~~u', $file)) {
			$error = lang(266);
		} else {
			save_settings(array("output" => $adminer_import["output"], "format" => $_POST["separator"]), "adminer_import");
			$result = true;
			$cols = array_keys($fields);
			preg_match_all('~(?>"[^"]*"|[^"\r\n]+)+~', $file, $matches);
			$affected = count($matches[0]);
			driver()->begin();
			$separator = ($_POST["separator"] == "csv" ? "," : ($_POST["separator"] == "tsv" ? "\t" : ";"));
			$rows = array();
			foreach ($matches[0] as $key => $val) {
				preg_match_all("~((?>\"[^\"]*\")+|[^$separator]*)$separator~", $val . $separator, $matches2);
				if (!$key && !array_diff($matches2[1], $cols)) { //! doesn't work with column names containing ",\n
					// first row corresponds to column names - use it for table structure
					$cols = $matches2[1];
					$affected--;
				} else {
					$set = array();
					foreach ($matches2[1] as $i => $col) {
						$set[idf_escape($cols[$i])] = ($col == "" && $fields[$cols[$i]]["null"] ? "NULL" : q(preg_match('~^".*"$~s', $col) ? str_replace('""', '"', substr($col, 1, -1)) : $col));
					}
					$rows[] = $set;
				}
			}
			$result = (!$rows || driver()->insertUpdate($TABLE, $rows, $primary));
			if ($result) {
				driver()->commit();
			}
			queries_redirect(remove_from_uri("page"), lang(267, $affected), $result);
			driver()->rollback(); // after queries_redirect() to not overwrite error

		}
	}
}

$table_name = adminer()->tableName($table_status);
if (is_ajax()) {
	page_headers();
	ob_start();
} else {
	page_header(lang(53) . ": $table_name", $error);
}

$set = null;
if (isset($rights["insert"]) || !support("table")) {
	$params = array();
	foreach ((array) $_GET["where"] as $val) {
		if (
			isset($foreign_keys[$val["col"]]) && count($foreign_keys[$val["col"]]) == 1
			&& ($val["op"] == "=" || (!$val["op"] && (is_array($val["val"]) || !preg_match('~[_%]~', $val["val"])))) // LIKE in Editor
		) {
			$params["set" . "[" . bracket_escape($val["col"]) . "]"] = $val["val"];
		}
	}

	$set = $params ? "&" . http_build_query($params) : "";
}
adminer()->selectLinks($table_status, $set);

if (!$columns && support("table")) {
	echo "<p class='error'>" . lang(268) . ($fields ? "." : ": " . error()) . "\n";
} else {
	echo "<form action='' id='form'>\n";
	echo "<div style='display: none;'>";
	hidden_fields_get();
	echo (DB != "" ? input_hidden("db", DB) . (isset($_GET["ns"]) ? input_hidden("ns", $_GET["ns"]) : "") : ""); // not used in Editor
	echo input_hidden("select", $TABLE);
	echo "</div>\n";
	adminer()->selectColumnsPrint($select, $columns);
	adminer()->selectSearchPrint($where, $search_columns, $indexes);
	adminer()->selectOrderPrint($order, $order_columns, $indexes);
	adminer()->selectLimitPrint($limit);
	adminer()->selectLengthPrint($text_length);
	adminer()->selectActionPrint($indexes);
	echo "</form>\n";

	$page = $_GET["page"];
	$found_rows = null;
	if ($page == "last") {
		$found_rows = get_val(count_rows($TABLE, $where, $is_group, $group));
		$page = floor(max(0, intval($found_rows) - 1) / $limit);
	}

	$select2 = $select;
	$group2 = $group;
	if (!$select2) {
		$select2[] = "*";
		$convert_fields = convert_fields($columns, $fields, $select);
		if ($convert_fields) {
			$select2[] = substr($convert_fields, 2);
		}
	}
	foreach ($select as $key => $val) {
		$field = $fields[idf_unescape($val)];
		if ($field && ($as = convert_field($field))) {
			$select2[$key] = "$as AS $val";
		}
	}
	if (!$is_group && $unselected) {
		foreach ($unselected as $key => $val) {
			$select2[] = idf_escape($key);
			if ($group2) {
				$group2[] = idf_escape($key);
			}
		}
	}
	$result = driver()->select($TABLE, $select2, $where, $group2, $order, $limit, $page, true);

	if (!$result) {
		echo "<p class='error'>" . error() . "\n";
	} else {
		if (JUSH == "mssql" && $page) {
			$result->seek($limit * $page);
		}
		$email_fields = array();
		echo "<form action='' method='post' enctype='multipart/form-data'>\n";
		$rows = array();
		while ($row = $result->fetch_assoc()) {
			if ($page && JUSH == "oracle") {
				unset($row["RNUM"]);
			}
			$rows[] = $row;
		}

		// use count($rows) without LIMIT, COUNT(*) without grouping, FOUND_ROWS otherwise (slowest)
		if ($_GET["page"] != "last" && $limit && $group && $is_group && JUSH == "sql") {
			$found_rows = get_val(" SELECT FOUND_ROWS()"); // space to allow mysql.trace_mode
		}

		if (!$rows) {
			echo "<p class='message'>" . lang(14) . "\n";
		} else {
			$backward_keys = adminer()->backwardKeys($TABLE, $table_name);

			echo "<div class='scrollable'>";
			echo "<table id='table' class='nowrap checkable odds'>";
			echo script("mixin(qs('#table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true), onkeydown: editingKeydown});");
			echo "<thead><tr>" . (!$group && $select
				? ""
				: "<td><input type='checkbox' id='all-page' class='jsonly'>" . script("qs('#all-page').onclick = partial(formCheck, /check/);", "")
					. " <a href='" . h($_GET["modify"] ? remove_from_uri("modify") : $_SERVER["REQUEST_URI"] . "&modify=1") . "'>" . lang(269) . "</a>");
			$names = array();
			$functions = array();
			reset($select);
			$rank = 1;
			foreach ($rows[0] as $key => $val) {
				if (!isset($unselected[$key])) {
					/** @var array{fun?:string, col?:string} */
					$val = idx($_GET["columns"], key($select)) ?: array();
					$field = $fields[$select ? ($val ? $val["col"] : current($select)) : $key];
					$name = ($field ? adminer()->fieldName($field, $rank) : ($val["fun"] ? "*" : h($key)));
					if ($name != "") {
						$rank++;
						$names[$key] = $name;
						$column = idf_escape($key);
						$href = remove_from_uri('(order|desc)[^=]*|page') . '&order%5B0%5D=' . urlencode($key);
						$desc = "&desc%5B0%5D=1";
						echo "<th id='th[" . h(bracket_escape($key)) . "]'>" . script("mixin(qsl('th'), {onmouseover: partial(columnMouse), onmouseout: partial(columnMouse, ' hidden')});", "");
						$fun = apply_sql_function($val["fun"], $name); //! columns looking like functions
						$sortable = isset($field["privileges"]["order"]) || $fun;
						echo ($sortable ? "<a href='" . h($href . ($order[0] == $column || $order[0] == $key ? $desc : '')) . "'>$fun</a>" : $fun); // $order[0] == $key - COUNT(*)
						echo "<span class='column hidden'>";
						if ($sortable) {
							echo "<a href='" . h($href . $desc) . "' title='" . lang(59) . "' class='text'> â†“</a>";
						}
						if (!$val["fun"] && isset($field["privileges"]["where"])) {
							echo '<a href="#fieldset-search" title="' . lang(56) . '" class="text jsonly"> =</a>';
							echo script("qsl('a').onclick = partial(selectSearch, '" . js_escape($key) . "');");
						}
						echo "</span>";
					}
					$functions[$key] = $val["fun"];
					next($select);
				}
			}

			$lengths = array();
			if ($_GET["modify"]) {
				foreach ($rows as $row) {
					foreach ($row as $key => $val) {
						$lengths[$key] = max($lengths[$key], min(40, strlen(utf8_decode($val))));
					}
				}
			}

			echo ($backward_keys ? "<th>" . lang(270) : "") . "</thead>\n";

			if (is_ajax()) {
				ob_end_clean();
			}

			foreach (adminer()->rowDescriptions($rows, $foreign_keys) as $n => $row) {
				$unique_array = unique_array($rows[$n], $indexes);
				if (!$unique_array) {
					$unique_array = array();
					reset($select);
					foreach ($rows[$n] as $key => $val) {
						if (!preg_match('~^(COUNT|AVG|GROUP_CONCAT|MAX|MIN|SUM)\(~', current($select))) {
							$unique_array[$key] = $val;
						}
						next($select);
					}
				}
				$unique_idf = "";
				foreach ($unique_array as $key => $val) {
					$field = (array) $fields[$key];
					if ((JUSH == "sql" || JUSH == "pgsql") && preg_match('~char|text|enum|set~', $field["type"]) && strlen($val) > 64) {
						$key = (strpos($key, '(') ? $key : idf_escape($key)); //! columns looking like functions
						$key = "MD5(" . (JUSH != 'sql' || preg_match("~^utf8~", $field["collation"]) ? $key : "CONVERT($key USING " . charset(connection()) . ")") . ")";
						$val = md5($val);
					}
					$unique_idf .= "&" . ($val !== null ? urlencode("where[" . bracket_escape($key) . "]") . "=" . urlencode($val === false ? "f" : $val) : "null%5B%5D=" . urlencode($key));
				}
				echo "<tr>" . (!$group && $select ? "" : "<td>"
					. checkbox("check[]", substr($unique_idf, 1), in_array(substr($unique_idf, 1), (array) $_POST["check"]))
					. ($is_group || information_schema(DB) ? "" : " <a href='" . h(ME . "edit=" . urlencode($TABLE) . $unique_idf) . "' class='edit'>" . lang(271) . "</a>")
				);

				reset($select);
				foreach ($row as $key => $val) {
					if (isset($names[$key])) {
						$column = current($select);
						$field = (array) $fields[$key];
						$val = driver()->value($val, $field);
						if ($val != "" && (!isset($email_fields[$key]) || $email_fields[$key] != "")) {
							$email_fields[$key] = (is_mail($val) ? $names[$key] : ""); //! filled e-mails can be contained on other pages
						}

						$link = "";
						if (is_blob($field) && $val != "") {
							$link = ME . 'download=' . urlencode($TABLE) . '&field=' . urlencode($key) . $unique_idf;
						}
						if (!$link && $val !== null) { // link related items
							foreach ((array) $foreign_keys[$key] as $foreign_key) {
								if (count($foreign_keys[$key]) == 1 || end($foreign_key["source"]) == $key) {
									$link = "";
									foreach ($foreign_key["source"] as $i => $source) {
										$link .= where_link($i, $foreign_key["target"][$i], $rows[$n][$source]);
									}
									$link = ($foreign_key["db"] != "" ? preg_replace('~([?&]db=)[^&]+~', '\1' . urlencode($foreign_key["db"]), ME) : ME) . 'select=' . urlencode($foreign_key["table"]) . $link; // InnoDB supports non-UNIQUE keys
									if ($foreign_key["ns"]) {
										$link = preg_replace('~([?&]ns=)[^&]+~', '\1' . urlencode($foreign_key["ns"]), $link);
									}
									if (count($foreign_key["source"]) == 1) {
										break;
									}
								}
							}
						}
						if ($column == "COUNT(*)") {
							$link = ME . "select=" . urlencode($TABLE);
							$i = 0;
							foreach ((array) $_GET["where"] as $v) {
								if (!array_key_exists($v["col"], $unique_array)) {
									$link .= where_link($i++, $v["col"], $v["val"], $v["op"]);
								}
							}
							foreach ($unique_array as $k => $v) {
								$link .= where_link($i++, $k, $v);
							}
						}

						$html = select_value($val, $link, $field, $text_length);
						$id = h("val[$unique_idf][" . bracket_escape($key) . "]");
						$posted = idx(idx($_POST["val"], $unique_idf), bracket_escape($key));
						$editable = !is_array($row[$key]) && is_utf8($html) && $rows[$n][$key] == $row[$key] && !$functions[$key] && !$field["generated"];
						$type = (preg_match('~^(AVG|MIN|MAX)\((.+)\)~', $column, $match) ? $fields[idf_unescape($match[2])]["type"] : $field["type"]);
						$text = preg_match('~text|json|lob~', $type);
						$is_number = preg_match(number_type(), $type) || preg_match('~^(CHAR_LENGTH|ROUND|FLOOR|CEIL|TIME_TO_SEC|COUNT|SUM)\(~', $column);
						echo "<td id='$id'" . ($is_number && ($val === null || is_numeric(strip_tags($html)) || $type == "money") ? " class='number'" : "");
						if (($_GET["modify"] && $editable && $val !== null) || $posted !== null) {
							$h_value = h($posted !== null ? $posted : $row[$key]);
							echo ">" . ($text ? "<textarea name='$id' cols='30' rows='" . (substr_count($row[$key], "\n") + 1) . "'>$h_value</textarea>" : "<input name='$id' value='$h_value' size='$lengths[$key]'>");
						} else {
							$long = strpos($html, "<i>â€¦</i>");
							echo " data-text='" . ($long ? 2 : ($text ? 1 : 0)) . "'"
								. ($editable ? "" : " data-warning='" . h(lang(272)) . "'")
								. ">$html"
							;
						}
					}
					next($select);
				}

				if ($backward_keys) {
					echo "<td>";
				}
				adminer()->backwardKeysPrint($backward_keys, $rows[$n]);
				echo "</tr>\n"; // close to allow white-space: pre
			}

			if (is_ajax()) {
				exit;
			}
			echo "</table>\n";
			echo "</div>\n";
		}

		if (!is_ajax()) {
			if ($rows || $page) {
				$exact_count = true;
				if ($_GET["page"] != "last") {
					if (!$limit || (count($rows) < $limit && ($rows || !$page))) {
						$found_rows = ($page ? $page * $limit : 0) + count($rows);
					} elseif (JUSH != "sql" || !$is_group) {
						$found_rows = ($is_group ? false : found_rows($table_status, $where));
						if (intval($found_rows) < max(1e4, 2 * ($page + 1) * $limit)) {
							// slow with big tables
							$found_rows = first(slow_query(count_rows($TABLE, $where, $is_group, $group)));
						} else {
							$exact_count = false;
						}
					}
				}

				$pagination = ($limit && ($found_rows === false || $found_rows > $limit || $page));
				if ($pagination) {
					echo (($found_rows === false ? count($rows) + 1 : $found_rows - $page * $limit) > $limit
						? '<p><a href="' . h(remove_from_uri("page") . "&page=" . ($page + 1)) . '" class="loadmore">' . lang(273) . '</a>'
							. script("qsl('a').onclick = partial(selectLoadMore, $limit, '" . lang(274) . "â€¦');", "")
						: ''
					);
					echo "\n";
				}

				echo "<div class='footer'><div>\n";
				if ($pagination) {
					// display first, previous 4, next 4 and last page
					$max_page = ($found_rows === false
						? $page + (count($rows) >= $limit ? 2 : 1)
						: floor(($found_rows - 1) / $limit)
					);
					echo "<fieldset>";
					if (JUSH != "simpledb") {
						echo "<legend><a href='" . h(remove_from_uri("page")) . "'>" . lang(275) . "</a></legend>";
						echo script("qsl('a').onclick = function () { pageClick(this.href, +prompt('" . lang(275) . "', '" . ($page + 1) . "')); return false; };");
						echo pagination(0, $page) . ($page > 5 ? " â€¦" : "");
						for ($i = max(1, $page - 4); $i < min($max_page, $page + 5); $i++) {
							echo pagination($i, $page);
						}
						if ($max_page > 0) {
							echo ($page + 5 < $max_page ? " â€¦" : "");
							echo ($exact_count && $found_rows !== false
								? pagination($max_page, $page)
								: " <a href='" . h(remove_from_uri("page") . "&page=last") . "' title='~$max_page'>" . lang(276) . "</a>"
							);
						}
					} else {
						echo "<legend>" . lang(275) . "</legend>";
						echo pagination(0, $page) . ($page > 1 ? " â€¦" : "");
						echo ($page ? pagination($page, $page) : "");
						echo ($max_page > $page ? pagination($page + 1, $page) . ($max_page > $page + 1 ? " â€¦" : "") : "");
					}
					echo "</fieldset>\n";
				}

				echo "<fieldset>";
				echo "<legend>" . lang(277) . "</legend>";
				$display_rows = ($exact_count ? "" : "~ ") . $found_rows;
				$onclick = "const checked = formChecked(this, /check/); selectCount('selected', this.checked ? '$display_rows' : checked); selectCount('selected2', this.checked || !checked ? '$display_rows' : checked);";
				echo checkbox("all", 1, 0, ($found_rows !== false ? ($exact_count ? "" : "~ ") . lang(159, $found_rows) : ""), $onclick) . "\n";
				echo "</fieldset>\n";

				if (adminer()->selectCommandPrint()) {
					?>
<fieldset<?php echo ($_GET["modify"] ? '' : ' class="jsonly"'); ?>><legend><?php echo lang(269); ?></legend><div>
<input type="submit" value="<?php echo lang(16); ?>"<?php echo ($_GET["modify"] ? '' : ' title="' . lang(265) . '"'); ?>>
</div></fieldset>
<fieldset><legend><?php echo lang(131); ?> <span id="selected"></span></legend><div>
<input type="submit" name="edit" value="<?php echo lang(12); ?>">
<input type="submit" name="clone" value="<?php echo lang(261); ?>">
<input type="submit" name="delete" value="<?php echo lang(20); ?>"><?php echo confirm(); ?>
</div></fieldset>
<?php
				}

				$format = adminer()->dumpFormat();
				foreach ((array) $_GET["columns"] as $column) {
					if ($column["fun"]) {
						unset($format['sql']);
						break;
					}
				}
				if ($format) {
					print_fieldset("export", lang(76) . " <span id='selected2'></span>");
					$output = adminer()->dumpOutput();
					echo ($output ? html_select("output", $output, $adminer_import["output"]) . " " : "");
					echo html_select("format", $format, $adminer_import["format"]);
					echo " <input type='submit' name='export' value='" . lang(76) . "'>\n";
					echo "</div></fieldset>\n";
				}

				adminer()->selectEmailPrint(array_filter($email_fields, 'strlen'), $columns);
				echo "</div></div>\n";
			}

			if (adminer()->selectImportPrint()) {
				echo "<p>";
				echo "<a href='#import'>" . lang(75) . "</a>";
				echo script("qsl('a').onclick = partial(toggle, 'import');", "");
				echo "<span id='import'" . ($_POST["import"] ? "" : " class='hidden'") . ">: ";
				echo file_input("<input type='file' name='csv_file'> "
					. html_select("separator", array("csv" => "CSV,", "csv;" => "CSV;", "tsv" => "TSV"), $adminer_import["format"])
					. " <input type='submit' name='import' value='" . lang(75) . "'>")
				;
				echo "</span>";
			}

			echo input_token();
			echo "</form>\n";
			echo (!$group && $select ? "" : script("tableCheck();"));
		}
	}
}

if (is_ajax()) {
	ob_end_clean();
	exit;
}

} elseif (isset($_GET["variables"])) {
	?>
<?php
$status = isset($_GET["status"]);
page_header($status ? lang(123) : lang(122));

$variables = ($status ? show_status() : show_variables());
if (!$variables) {
	echo "<p class='message'>" . lang(14) . "\n";
} else {
	echo "<table>\n";
	foreach ($variables as $row) {
		echo "<tr>";
		$key = array_shift($row);
		echo "<th><code class='jush-" . JUSH . ($status ? "status" : "set") . "'>" . h($key) . "</code>";
		foreach ($row as $val) {
			echo "<td>" . nl_br(h($val));
		}
	}
	echo "</table>\n";
}

} elseif (isset($_GET["script"])) {
	?>
<?php
header("Content-Type: text/javascript; charset=utf-8");

if ($_GET["script"] == "db") {
	$sums = array("Data_length" => 0, "Index_length" => 0, "Data_free" => 0);
	foreach (table_status() as $name => $table_status) {
		json_row("Comment-$name", h($table_status["Comment"]));
		if (!is_view($table_status) || preg_match('~materialized~i', $table_status["Engine"])) {
			foreach (array("Engine", "Collation") as $key) {
				json_row("$key-$name", h($table_status[$key]));
			}
			foreach ($sums + array("Auto_increment" => 0, "Rows" => 0) as $key => $val) {
				if ($table_status[$key] != "") {
					$val = format_number($table_status[$key]);
					if ($val >= 0) {
						json_row("$key-$name", ($key == "Rows" && $val && $table_status["Engine"] == (JUSH == "pgsql" ? "table" : "InnoDB")
							? "~ $val"
							: $val
						));
					}
					if (isset($sums[$key])) {
						// ignore innodb_file_per_table because it is not active for tables created before it was enabled
						$sums[$key] += ($table_status["Engine"] != "InnoDB" || $key != "Data_free" ? $table_status[$key] : 0);
					}
				} elseif (array_key_exists($key, $table_status)) {
					json_row("$key-$name", "?");
				}
			}
		}
	}
	foreach ($sums as $key => $val) {
		json_row("sum-$key", format_number($val));
	}
	json_row("");

} elseif ($_GET["script"] == "kill") {
	connection()->query("KILL " . number($_POST["kill"]));

} else { // connect
	foreach (count_tables(adminer()->databases()) as $db => $val) {
		json_row("tables-$db", $val);
		json_row("size-$db", db_size($db));
	}
	json_row("");
}

exit; // don't print footer

} else {
	?>
<?php
$tables_views = array_merge((array) $_POST["tables"], (array) $_POST["views"]);

if ($tables_views && !$error && !$_POST["search"]) {
	$result = true;
	$message = "";
	if (JUSH == "sql" && $_POST["tables"] && count($_POST["tables"]) > 1 && ($_POST["drop"] || $_POST["truncate"] || $_POST["copy"])) {
		queries("SET foreign_key_checks = 0"); // allows to truncate or drop several tables at once
	}

	if ($_POST["truncate"]) {
		if ($_POST["tables"]) {
			$result = truncate_tables($_POST["tables"]);
		}
		$message = lang(278);
	} elseif ($_POST["move"]) {
		$result = move_tables((array) $_POST["tables"], (array) $_POST["views"], $_POST["target"]);
		$message = lang(279);
	} elseif ($_POST["copy"]) {
		$result = copy_tables((array) $_POST["tables"], (array) $_POST["views"], $_POST["target"]);
		$message = lang(280);
	} elseif ($_POST["drop"]) {
		if ($_POST["views"]) {
			$result = drop_views($_POST["views"]);
		}
		if ($result && $_POST["tables"]) {
			$result = drop_tables($_POST["tables"]);
		}
		$message = lang(281);
	} elseif (JUSH == "sqlite" && $_POST["check"]) {
		foreach ((array) $_POST["tables"] as $table) {
			foreach (get_rows("PRAGMA integrity_check(" . q($table) . ")") as $row) {
				$message .= "<b>" . h($table) . "</b>: " . h($row["integrity_check"]) . "<br>";
			}
		}
	} elseif (JUSH != "sql") {
		$result = (JUSH == "sqlite"
			? queries("VACUUM")
			: apply_queries("VACUUM" . ($_POST["optimize"] ? "" : " ANALYZE"), $_POST["tables"])
		);
		$message = lang(282);
	} elseif (!$_POST["tables"]) {
		$message = lang(11);
	} elseif ($result = queries(($_POST["optimize"] ? "OPTIMIZE" : ($_POST["check"] ? "CHECK" : ($_POST["repair"] ? "REPAIR" : "ANALYZE"))) . " TABLE " . implode(", ", array_map('Adminer\idf_escape', $_POST["tables"])))) {
		while ($row = $result->fetch_assoc()) {
			$message .= "<b>" . h($row["Table"]) . "</b>: " . h($row["Msg_text"]) . "<br>";
		}
	}

	queries_redirect(substr(ME, 0, -1), $message, $result);
}

page_header(($_GET["ns"] == "" ? lang(37) . ": " . h(DB) : lang(79) . ": " . h($_GET["ns"])), $error, true);

if (adminer()->homepage()) {
	if ($_GET["ns"] !== "") {
		echo "<h3 id='tables-views'>" . lang(283) . "</h3>\n";
		$tables_list = tables_list();
		if (!$tables_list) {
			echo "<p class='message'>" . lang(11) . "\n";
		} else {
			echo "<form action='' method='post'>\n";
			if (support("table")) {
				echo "<fieldset><legend>" . lang(284) . " <span id='selected2'></span></legend><div>";
				echo html_select("op", adminer()->operators(), idx($_POST, "op", JUSH == "elastic" ? "should" : "LIKE %%"));
				echo " <input type='search' name='query' value='" . h($_POST["query"]) . "'>";
				echo script("qsl('input').onkeydown = partialArg(bodyKeydown, 'search');", "");
				echo " <input type='submit' name='search' value='" . lang(56) . "'>\n";
				echo "</div></fieldset>\n";
				if ($_POST["search"] && $_POST["query"] != "") {
					$_GET["where"][0]["op"] = $_POST["op"];
					search_tables();
				}
			}
			echo "<div class='scrollable'>\n";
			echo "<table class='nowrap checkable odds'>\n";
			echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");
			echo '<thead><tr class="wrap">';
			echo '<td><input id="check-all" type="checkbox" class="jsonly">' . script("qs('#check-all').onclick = partial(formCheck, /^(tables|views)\[/);", "");
			echo '<th>' . lang(138);
			echo '<td>' . lang(285) . doc_link(array('sql' => 'storage-engines.html'));
			echo '<td>' . lang(127) . doc_link(array('sql' => 'charset-charsets.html', 'mariadb' => 'supported-character-sets-and-collations/'));
			echo '<td>' . lang(286) . doc_link(array('sql' => 'show-table-status.html', 'pgsql' => 'functions-admin.html#FUNCTIONS-ADMIN-DBOBJECT', 'oracle' => 'REFRN20286'));
			echo '<td>' . lang(287) . doc_link(array('sql' => 'show-table-status.html', 'pgsql' => 'functions-admin.html#FUNCTIONS-ADMIN-DBOBJECT'));
			echo '<td>' . lang(288) . doc_link(array('sql' => 'show-table-status.html'));
			echo '<td>' . lang(51) . doc_link(array('sql' => 'example-auto-increment.html', 'mariadb' => 'auto_increment/'));
			echo '<td>' . lang(289) . doc_link(array('sql' => 'show-table-status.html', 'pgsql' => 'catalog-pg-class.html#CATALOG-PG-CLASS', 'oracle' => 'REFRN20286'));
			echo (support("comment") ? '<td>' . lang(50) . doc_link(array('sql' => 'show-table-status.html', 'pgsql' => 'functions-info.html#FUNCTIONS-INFO-COMMENT-TABLE')) : '');
			echo "</thead>\n";

			$tables = 0;
			foreach ($tables_list as $name => $type) {
				$view = ($type !== null && !preg_match('~table|sequence~i', $type));
				$id = h("Table-" . $name);
				echo '<tr><td>' . checkbox(($view ? "views[]" : "tables[]"), $name, in_array("$name", $tables_views, true), "", "", "", $id); // "$name" to check numeric table names
				echo '<th>' . (support("table") || support("indexes") ? "<a href='" . h(ME) . "table=" . urlencode($name) . "' title='" . lang(42) . "' id='$id'>" . h($name) . '</a>' : h($name));
				if ($view && !preg_match('~materialized~i', $type)) {
					$title = lang(137);
					echo '<td colspan="6">' . (support("view") ? "<a href='" . h(ME) . "view=" . urlencode($name) . "' title='" . lang(44) . "'>$title</a>" : $title);
					echo '<td align="right"><a href="' . h(ME) . "select=" . urlencode($name) . '" title="' . lang(41) . '">?</a>';
				} else {
					foreach (
						array(
							"Engine" => array(),
							"Collation" => array(),
							"Data_length" => array("create", lang(43)),
							"Index_length" => array("indexes", lang(141)),
							"Data_free" => array("edit", lang(45)),
							"Auto_increment" => array("auto_increment=1&create", lang(43)),
							"Rows" => array("select", lang(41)),
						) as $key => $link
					) {
						$id = " id='$key-" . h($name) . "'";
						echo ($link ? "<td align='right'>" . (support("table") || $key == "Rows" || (support("indexes") && $key != "Data_length")
							? "<a href='" . h(ME . "$link[0]=") . urlencode($name) . "'$id title='$link[1]'>?</a>"
							: "<span$id>?</span>"
						) : "<td id='$key-" . h($name) . "'>");
					}
					$tables++;
				}
				echo (support("comment") ? "<td id='Comment-" . h($name) . "'>" : "");
				echo "\n";
			}

			echo "<tr><td><th>" . lang(262, count($tables_list));
			echo "<td>" . h(JUSH == "sql" ? get_val("SELECT @@default_storage_engine") : "");
			echo "<td>" . h(db_collation(DB, collations()));
			foreach (array("Data_length", "Index_length", "Data_free") as $key) {
				echo "<td align='right' id='sum-$key'>";
			}
			echo "\n";

			echo "</table>\n";
			echo script("ajaxSetHtml('" . js_escape(ME) . "script=db');");
			echo "</div>\n";
			if (!information_schema(DB)) {
				echo "<div class='footer'><div>\n";
				$vacuum = "<input type='submit' value='" . lang(290) . "'> " . on_help("'VACUUM'");
				$optimize = "<input type='submit' name='optimize' value='" . lang(291) . "'> " . on_help(JUSH == "sql" ? "'OPTIMIZE TABLE'" : "'VACUUM OPTIMIZE'");
				echo "<fieldset><legend>" . lang(131) . " <span id='selected'></span></legend><div>"
				. (JUSH == "sqlite" ? $vacuum . "<input type='submit' name='check' value='" . lang(292) . "'> " . on_help("'PRAGMA integrity_check'")
				: (JUSH == "pgsql" ? $vacuum . $optimize
				: (JUSH == "sql" ? "<input type='submit' value='" . lang(293) . "'> " . on_help("'ANALYZE TABLE'")
					. $optimize
					. "<input type='submit' name='check' value='" . lang(292) . "'> " . on_help("'CHECK TABLE'")
					. "<input type='submit' name='repair' value='" . lang(294) . "'> " . on_help("'REPAIR TABLE'")
				: "")))
				. "<input type='submit' name='truncate' value='" . lang(295) . "'> " . on_help(JUSH == "sqlite" ? "'DELETE'" : "'TRUNCATE" . (JUSH == "pgsql" ? "'" : " TABLE'")) . confirm()
				. "<input type='submit' name='drop' value='" . lang(132) . "'>" . on_help("'DROP TABLE'") . confirm() . "\n";
				$databases = (support("scheme") ? adminer()->schemas() : adminer()->databases());
				echo "</div></fieldset>\n";
				$script = "";
				if (count($databases) != 1 && JUSH != "sqlite") {
					echo "<fieldset><legend>" . lang(296) . " <span id='selected3'></span></legend><div>";
					$db = (isset($_POST["target"]) ? $_POST["target"] : (support("scheme") ? $_GET["ns"] : DB));
					echo ($databases ? html_select("target", $databases, $db) : '<input name="target" value="' . h($db) . '" autocapitalize="off">');
					echo "</label> <input type='submit' name='move' value='" . lang(297) . "'>";
					echo (support("copy") ? " <input type='submit' name='copy' value='" . lang(298) . "'> " . checkbox("overwrite", 1, $_POST["overwrite"], lang(299)) : "");
					echo "</div></fieldset>\n";
					$script = " selectCount('selected3', formChecked(this, /^(tables|views)\[/));";
				}
				echo "<input type='hidden' name='all' value=''>"; // used by trCheck()
				echo script("qsl('input').onclick = function () { selectCount('selected', formChecked(this, /^(tables|views)\[/));"
					. (support("table") ? " selectCount('selected2', formChecked(this, /^tables\[/) || $tables);" : "")
					. "$script }")
				;
				echo input_token();
				echo "</div></div>\n";
			}
			echo "</form>\n";
			echo script("tableCheck();");
		}

		echo "<p class='links'><a href='" . h(ME) . "create='>" . lang(77) . "</a>\n";
		echo (support("view") ? "<a href='" . h(ME) . "view='>" . lang(215) . "</a>\n" : "");

		if (support("routine")) {
			echo "<h3 id='routines'>" . lang(72) . "</h3>\n";
			$routines = routines();
			if ($routines) {
				echo "<table class='odds'>\n";
				echo '<thead><tr><th>' . lang(193) . '<td>' . lang(49) . '<td>' . lang(232) . "<td></thead>\n";
				foreach ($routines as $row) {
					$name = ($row["SPECIFIC_NAME"] == $row["ROUTINE_NAME"] ? "" : "&name=" . urlencode($row["ROUTINE_NAME"])); // not computed on the pages to be able to print the header first
					echo '<tr>';
					echo '<th><a href="' . h(ME . ($row["ROUTINE_TYPE"] != "PROCEDURE" ? 'callf=' : 'call=') . urlencode($row["SPECIFIC_NAME"]) . $name) . '">' . h($row["ROUTINE_NAME"]) . '</a>';
					echo '<td>' . h($row["ROUTINE_TYPE"]);
					echo '<td>' . h($row["DTD_IDENTIFIER"]);
					echo '<td><a href="' . h(ME . ($row["ROUTINE_TYPE"] != "PROCEDURE" ? 'function=' : 'procedure=') . urlencode($row["SPECIFIC_NAME"]) . $name) . '">' . lang(144) . "</a>";
				}
				echo "</table>\n";
			}
			echo '<p class="links">'
				. (support("procedure") ? '<a href="' . h(ME) . 'procedure=">' . lang(231) . '</a>' : '')
				. '<a href="' . h(ME) . 'function=">' . lang(230) . "</a>\n"
			;
		}

		if (support("sequence")) {
			echo "<h3 id='sequences'>" . lang(73) . "</h3>\n";
			$sequences = get_vals("SELECT sequence_name FROM information_schema.sequences WHERE sequence_schema = current_schema() ORDER BY sequence_name");
			if ($sequences) {
				echo "<table class='odds'>\n";
				echo "<thead><tr><th>" . lang(193) . "</thead>\n";
				foreach ($sequences as $val) {
					echo "<tr><th><a href='" . h(ME) . "sequence=" . urlencode($val) . "'>" . h($val) . "</a>\n";
				}
				echo "</table>\n";
			}
			echo "<p class='links'><a href='" . h(ME) . "sequence='>" . lang(237) . "</a>\n";
		}

		if (support("type")) {
			echo "<h3 id='user-types'>" . lang(6) . "</h3>\n";
			$user_types = types();
			if ($user_types) {
				echo "<table class='odds'>\n";
				echo "<thead><tr><th>" . lang(193) . "</thead>\n";
				foreach ($user_types as $val) {
					echo "<tr><th><a href='" . h(ME) . "type=" . urlencode($val) . "'>" . h($val) . "</a>\n";
				}
				echo "</table>\n";
			}
			echo "<p class='links'><a href='" . h(ME) . "type='>" . lang(241) . "</a>\n";
		}

		if (support("event")) {
			echo "<h3 id='events'>" . lang(74) . "</h3>\n";
			$rows = get_rows("SHOW EVENTS");
			if ($rows) {
				echo "<table>\n";
				echo "<thead><tr><th>" . lang(193) . "<td>" . lang(300) . "<td>" . lang(221) . "<td>" . lang(222) . "<td></thead>\n";
				foreach ($rows as $row) {
					echo "<tr>";
					echo "<th>" . h($row["Name"]);
					echo "<td>" . ($row["Execute at"] ? lang(301) . "<td>" . $row["Execute at"] : lang(223) . " " . $row["Interval value"] . " " . $row["Interval field"] . "<td>$row[Starts]");
					echo "<td>$row[Ends]";
					echo '<td><a href="' . h(ME) . 'event=' . urlencode($row["Name"]) . '">' . lang(144) . '</a>';
				}
				echo "</table>\n";
				$event_scheduler = get_val("SELECT @@event_scheduler");
				if ($event_scheduler && $event_scheduler != "ON") {
					echo "<p class='error'><code class='jush-sqlset'>event_scheduler</code>: " . h($event_scheduler) . "\n";
				}
			}
			echo '<p class="links"><a href="' . h(ME) . 'event=">' . lang(220) . "</a>\n";
		}
	}
}

}

// each page calls its own page_header(), if the footer should not be called then the page exits
page_footer();
