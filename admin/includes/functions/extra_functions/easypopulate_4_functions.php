<?php

if (!function_exists(zen_get_sub_categories)) {
  function zen_get_sub_categories(&$categories, $categories_id) {
    $sub_categories_query = mysql_query("select categories_id from " . TABLE_CATEGORIES . " where parent_id = '" . (int)$categories_id . "'");
    while ($sub_categories = mysql_fetch_array($sub_categories_query)) {
      if ($sub_categories['categories_id'] == 0) return true;
      $categories[sizeof($categories)] = $sub_categories['categories_id'];
      if ($sub_categories['categories_id'] != $categories_id) {
        zen_get_sub_categories($categories, $sub_categories['categories_id']);
      }
    }
  }
}


function ep_4_get_uploaded_file($filename) {
	if (isset($_FILES[$filename])) {
		//global $_FILES;
		$uploaded_file = array('name' => $_FILES[$filename]['name'],
		'type' => $_FILES[$filename]['type'],
		'size' => $_FILES[$filename]['size'],
		'tmp_name' => $_FILES[$filename]['tmp_name']);
	} elseif (isset($_POST[$filename])) {
		$uploaded_file = array('name' => $_POST[$filename],
		);
	} elseif (isset($GLOBALS['HTTP_POST_FILES'][$filename])) {
		global $HTTP_POST_FILES;
		$uploaded_file = array('name' => $HTTP_POST_FILES[$filename]['name'],
		'type' => $HTTP_POST_FILES[$filename]['type'],
		'size' => $HTTP_POST_FILES[$filename]['size'],
		'tmp_name' => $HTTP_POST_FILES[$filename]['tmp_name']);
	} elseif (isset($GLOBALS['HTTP_POST_VARS'][$filename])) {
		global $HTTP_POST_VARS;
		$uploaded_file = array('name' => $HTTP_POST_VARS[$filename],
		);
	} else {
		$uploaded_file = array('name' => $GLOBALS[$filename . '_name'],
		'type' => $GLOBALS[$filename . '_type'],
		'size' => $GLOBALS[$filename . '_size'],
		'tmp_name' => $GLOBALS[$filename]);
	}
return $uploaded_file;
}

// the $filename parameter is an array with the following elements: name, type, size, tmp_name
function ep_4_copy_uploaded_file($filename, $target) {
	if (substr($target, -1) != '/') $target .= '/';
	$target .= $filename['name'];
	move_uploaded_file($filename['tmp_name'], $target);
}

function ep_4_get_tax_class_rate($tax_class_id) {
	$tax_multiplier = 0;
	$tax_query = mysql_query("select SUM(tax_rate) as tax_rate from " . TABLE_TAX_RATES . " WHERE  tax_class_id = '" . zen_db_input($tax_class_id) . "' GROUP BY tax_priority");
	if (mysql_num_rows($tax_query)) {
		while ($tax = mysql_fetch_array($tax_query)) {
			$tax_multiplier += $tax['tax_rate'];
		}
	}
	return $tax_multiplier;
}

function ep_4_get_tax_title_class_id($tax_class_title) {
	$classes_query = mysql_query("select tax_class_id from " . TABLE_TAX_CLASS . " WHERE tax_class_title = '" . zen_db_input($tax_class_title) . "'" );
	$tax_class_array = mysql_fetch_array($classes_query);
	$tax_class_id = $tax_class_array['tax_class_id'];
	return $tax_class_id ;
}

function print_el_4($item2) {
	$output_display = substr(strip_tags($item2), 0, 10) . " | ";
	return $output_display;
}

function print_el1_4($item2) {
	$output_display = sprintf("| %'.4s ", substr(strip_tags($item2), 0, 80));
	return $output_display;
}

function smart_tags_4($string,$tags,$crsub,$doit) {
	if ($doit == true) {
		foreach ($tags as $tag => $new) {
			$tag = '/('.$tag.')/';
			$string = preg_replace($tag,$new,$string);
		}
	}
	// we remove problem characters here anyway as they are not wanted..
	$string = preg_replace("/(\r\n|\n|\r)/", "", $string);
	// $crsub is redundant - may add it again later though..
	return $string;
}

function ep_4_field_name_exists($table_name, $field_name) {
    global $db;
    $sql = "show fields from " . $table_name;
    $result = $db->Execute($sql);
    while (!$result->EOF) {
      // echo 'fields found='.$result->fields['Field'].'<br />';
      if  ($result->fields['Field'] == $field_name) {
        return true; // exists, so return with no error
      }
      $result->MoveNext();
    }
    return false;
}

function ep_4_remove_product($product_model) {
  global $db, $ep_debug_logging, $ep_debug_logging_all, $ep_stack_sql_error;
  $sql = "select products_id from " . TABLE_PRODUCTS . " where products_model = '" . zen_db_input($product_model) . "'";
  $products = $db->Execute($sql);
  if (mysql_errno()) {
	$ep_stack_sql_error = true;
	if ($ep_debug_logging == true) {
		$string = "MySQL error ".mysql_errno().": ".mysql_error()."\nWhen executing:\n$sql\n";
		write_debug_log($string);
 	  }
	} elseif ($ep_debug_logging_all == true) {
		$string = "MySQL PASSED\nWhen executing:\n$sql\n";
		write_debug_log($string);
	}
  while (!$products->EOF) {
    zen_remove_product($products->fields['products_id']);
    $products->MoveNext();
  }
  return;
}

function ep_4_update_cat_ids() { // reset products master categories ID - I do not believe this works correctly - chadd
  global $db;
  $sql = "select products_id from " . TABLE_PRODUCTS;
  $check_products = $db->Execute($sql);
  while (!$check_products->EOF) {
    $sql = "select products_id, categories_id from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id='" . $check_products->fields['products_id'] . "'";
    $check_category = $db->Execute($sql);
    $sql = "update " . TABLE_PRODUCTS . " set master_categories_id='" . $check_category->fields['categories_id'] . "' where products_id='" . $check_products->fields['products_id'] . "'";
    $update_viewed = $db->Execute($sql);
    $check_products->MoveNext();
  }
}

function ep_4_update_prices() { // reset products_price_sorter for searches etc. - does this run correctly? Why not use standard zencart call?
  global $db;
  $sql = "select products_id from " . TABLE_PRODUCTS;
  $update_prices = $db->Execute($sql);
  while (!$update_prices->EOF) {
    zen_update_products_price_sorter($update_prices->fields['products_id']);
    $update_prices->MoveNext();
  }
}

function ep_4_update_attributes_sort_order() {
	global $db;
	$all_products_attributes= $db->Execute("select p.products_id, pa.products_attributes_id from " .
	TABLE_PRODUCTS . " p, " .
	TABLE_PRODUCTS_ATTRIBUTES . " pa " . "
	where p.products_id= pa.products_id"
	);
	while (!$all_products_attributes->EOF) {
	  $count++;
	  //$product_id_updated .= ' - ' . $all_products_attributes->fields['products_id'] . ':' . $all_products_attributes->fields['products_attributes_id'];
	  zen_update_attributes_products_option_values_sort_order($all_products_attributes->fields['products_id']);
	  $all_products_attributes->MoveNext();
	}
}


function write_debug_log_4($string) {
	global $ep_debug_log_path;
	$logFile = $ep_debug_log_path . 'ep_debug_log.txt';
  $fp = fopen($logFile,'ab');
  fwrite($fp, $string);
  fclose($fp);
  return;
}

function ep_4_query($query) {
	global $ep_debug_logging, $ep_debug_logging_all, $ep_stack_sql_error;
	$result = mysql_query($query);
	if (mysql_errno()) {
		$ep_stack_sql_error = true;
		if ($ep_debug_logging == true) {
			$string = "MySQL error ".mysql_errno().": ".mysql_error()."\nWhen executing:\n$query\n";
			write_debug_log_4($string);
		}
	} elseif ($ep_debug_logging_all == true) {
		$string = "MySQL PASSED\nWhen executing:\n$query\n";
		write_debug_log_4($string);
	}
	return $result;
}

function install_easypopulate_4() {
	global $db;
	$db->Execute("INSERT INTO " . TABLE_CONFIGURATION_GROUP . " VALUES ('', 'Easy Populate 4', 'Configuration Options for Easy Populate 4', '1', '1')");
	$group_id = mysql_insert_id();
	$db->Execute("UPDATE " . TABLE_CONFIGURATION_GROUP . " SET sort_order = " . $group_id . " WHERE configuration_group_id = " . $group_id);
	$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " VALUES 
		('', 'Uploads Directory',                  'EASYPOPULATE_4_CONFIG_TEMP_DIR', 'temp/', 'Name of directory for your uploads (default: temp/).', " . $group_id . ", '0', NULL, now(), NULL, NULL),
		('', 'Upload File Date Format',            'EASYPOPULATE_4_CONFIG_FILE_DATE_FORMAT', 'm-d-y', 'Choose order of date values that corresponds to your uploads file, usually generated by MS Excel. Raw dates in your uploads file (Eg 2005-09-26 09:00:00) are not affected, and will upload as they are.', " . $group_id . ", '1', NULL, now(), NULL, 'zen_cfg_select_option(array(\"m-d-y\", \"d-m-y\", \"y-m-d\"),'),
		('', 'Default Raw Time',                   'EASYPOPULATE_4_CONFIG_DEFAULT_RAW_TIME', '09:00:00', 'If no time value stipulated in upload file, use this value. Useful for ensuring specials begin after a specific time of the day (default: 09:00:00)', " . $group_id . ", '2', NULL, now(), NULL, NULL),
		('', 'Maximum Category Depth',             'EASYPOPULATE_4_CONFIG_MAX_CATEGORY_LEVELS', '7', 'Maximum depth of categories required for your store. Is the number of category columns in downloaded file (default: 7).', " . $group_id . ", '4', NULL, now(), NULL, NULL),
		('', 'Upload/Download Prices Include Tax', 'EASYPOPULATE_4_CONFIG_PRICE_INC_TAX', 'false', 'Choose to include or exclude tax, depending on how you manage prices outside of Zen Cart.', " . $group_id . ", '5', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
		('', 'Make Zero Qty Products Inactive',    'EASYPOPULATE_4_CONFIG_ZERO_QTY_INACTIVE', 'false', 'When uploading, make the status Inactive for products with zero qty (default: false).', " . $group_id . ", '6', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
		('', 'Smart Tags Replacement of Newlines', 'EASYPOPULATE_4_CONFIG_SMART_TAGS', 'true', 'Allows your description fields in your uploads file to have carriage returns and/or new-lines converted to HTML line-breaks on uploading, thus preserving some rudimentary formatting (default: true).', " . $group_id . ", '7', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
		('', 'Advanced Smart Tags',                'EASYPOPULATE_4_CONFIG_ADV_SMART_TAGS', 'false', 'Allow the use of complex regular expressions to format descriptions, making headings bold, add bullets, etc. Configuration is in ADMIN/easypopulate_TAB.php (default: false).', " . $group_id . ", '8', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
		('', 'Debug Logging',                      'EASYPOPULATE_4_CONFIG_DEBUG_LOGGING', 'true', 'Allow Easy Populate to generate an error log on errors only (default: true)', " . $group_id . ", '9', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')
		");
}

function remove_easypopulate_4() {
	global $db, $ep_keys;
	$sql = "SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'Easy Populate 4'";
	$result = ep_4_query($sql);
	if (mysql_num_rows($result)) { // we have at least 1 EP group
		$ep_groups =  mysql_fetch_array($result);
		foreach ($ep_groups as $ep_group) {
		    $db->Execute("DELETE FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_id = '" . (int)$ep_group . "'");
		}
	}
	// delete any EP keys found in config
	foreach ($ep_keys as $ep_key) {
		@$db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $ep_key . "'");
	}
}

function ep_4_chmod_check($tempdir) {
	global $messageStack;
	if (!@file_exists(DIR_FS_CATALOG . $tempdir . ".")) { // directory does not exist
		$messageStack->add(sprintf(EASYPOPULATE_4_MSGSTACK_TEMP_FOLDER_MISSING, $tempdir, DIR_FS_CATALOG), 'warning');
		$chmod_check = false;
	} else { // directory exists, test is writeable
		if (!@is_writable(DIR_FS_CATALOG . $tempdir . ".")) { // directory does not exist
			$messageStack->add(sprintf(EASYPOPULATE_4_MSGSTACK_TEMP_FOLDER_NOT_WRITABLE, $tempdir, DIR_FS_CATALOG), 'warning');
			$chmod_check = false;
		} else { 
			$chmod_check = true;
		}
	}
	return $chmod_check;
}

/**
* The following functions are for testing purposes only
*/
// available zen functions of use..
/*
function zen_get_category_name($category_id, $language_id)
function zen_get_category_description($category_id, $language_id)
function zen_get_products_name($product_id, $language_id = 0)
function zen_get_products_description($product_id, $language_id)
function zen_get_products_model($products_id)
*/

function register_globals_vars_check_4 () {
	echo phpversion();
	echo '<br>register_globals = ', ini_get('register_globals'), '<br>';
	print "_GET: "; print_r($_GET); echo '<br />';
	print "_POST: "; print_r($_POST); echo '<br />';
	print "_FILES: "; print_r($_FILES); echo '<br />';
	print "_COOKIE: "; print_r($_COOKIE); echo '<br />';
	print "GLOBALS: "; print_r($GLOBALS); echo '<br />';
	print "_REQUEST: "; print_r($_REQUEST); echo '<br /><br />';
	global $HTTP_POST_FILES;
	print "HTTP_POST_FILES: "; print_r($HTTP_POST_FILES); echo '<br />';
}
?>