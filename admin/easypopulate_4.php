<?php
// $Id: easypopulate_4.php, v4.0.36 05-27-2016 mc12345678 $

// CSV VARIABLES - need to make this configurable in the ADMIN
// $csv_delimiter = "\t"; // "\t" = tab AND "," = COMMA
$csv_delimiter = ","; // "\t" = tab AND "," = COMMA
$csv_enclosure = '"'; // chadd - i think you should always use the '"' for best compatibility
//$category_delimiter = "^"; //Need to move this to the admin panel
$category_delimiter = "\x5e"; //Need to move this to the admin panel
// See https://en.wikipedia.org/wiki/UTF-8 for UTF-8 character encodings


$excel_safe_output = true; // this forces enclosure in quotes

// START INITIALIZATION
require_once ('includes/application_top.php');
if (!defined('EP4_DB_FILTER_KEY')) {
  // Need to define this to support use of primary key for import/export
  //   Instead of adding an additional switch, have incorporated the conversion
  //   of a blank product_id field to a new product in here.  Currently 
  //   expecting three choices: products_model, products_id, and blank_new 
  //   with model as default
  define(EP4_DB_FILTER_KEY, 'products_model'); // This could/should apply to both
  //  import and export files, so here is a good location for it.
}
if (!defined('EP4_ADMIN_TEMP_DIRECTORY')) {
  // Intention is to identify which file path to reference throughout instead of 
  //  storing the path in the database. If the individual wishes to use the 
  //  admin path, then this switch will direct the files to use the admin path
  //  instead of storing the path in the database.
  define('EP4_ADMIN_TEMP_DIRECTORY', 'true'); // Valid Values considered (false, true)
}
if (!defined('EP4_SHOW_ALL_FILETYPES')) {
  // Intention is to force display of all file types and files for someone
  //  that hasn't done an update on the database as part of installing this
  //  software.  Perhaps could/need to create a default(s) file to 
  //  assist with installation/operation.  mc12345678 12/30/15
  define('EP4_SHOW_ALL_FILETYPES', 'true');
}
/* Configuration Variables from Admin Interface  */
$tempdir = EASYPOPULATE_4_CONFIG_TEMP_DIR; // This ideally should not actually include the Admin Directory in the variable.
$ep_date_format = EASYPOPULATE_4_CONFIG_FILE_DATE_FORMAT;
$ep_raw_time = EASYPOPULATE_4_CONFIG_DEFAULT_RAW_TIME;
$ep_debug_logging = ((EASYPOPULATE_4_CONFIG_DEBUG_LOGGING == 'true') ? true : false);
$ep_split_records = (int) EASYPOPULATE_4_CONFIG_SPLIT_RECORDS;
$price_with_tax = ((EASYPOPULATE_4_CONFIG_PRICE_INC_TAX == 'true') ? true : false);
$strip_smart_tags = ((EASYPOPULATE_4_CONFIG_SMART_TAGS == 'true') ? true : false);
$max_qty_discounts = EASYPOPULATE_4_CONFIG_MAX_QTY_DISCOUNTS;
$ep_feedback = ((EASYPOPULATE_4_CONFIG_VERBOSE == 'true') ? true : false);
$ep_execution = (int) EASYPOPULATE_4_CONFIG_EXECUTION_TIME;
$ep_curly_quotes = (int) EASYPOPULATE_4_CONFIG_CURLY_QUOTES;
$ep_char_92 = (int) EASYPOPULATE_4_CONFIG_CHAR_92;
$ep_metatags = (int) EASYPOPULATE_4_CONFIG_META_DATA; // 0-Disable, 1-Enable
$ep_music = (int) EASYPOPULATE_4_CONFIG_MUSIC_DATA; // 0-Disable, 1-Enable
$ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3' ? true : false);

@set_time_limit($ep_execution);  // executin limit in seconds. 300 = 5 minutes before timeout, 0 means no timelimit

if ((isset($error) && !$error) || !isset($error)) {
  $upload_max_filesize = ini_get("upload_max_filesize");
  if (preg_match("/([0-9]+)K/i", $upload_max_filesize, $tempregs)) {
    $upload_max_filesize = $tempregs[1] * 1024;
  }
  if (preg_match("/([0-9]+)M/i", $upload_max_filesize, $tempregs)) {
    $upload_max_filesize = $tempregs[1] * 1024 * 1024;
  }
  if (preg_match("/([0-9]+)G/i", $upload_max_filesize, $tempregs)) {
    $upload_max_filesize = $tempregs[1] * 1024 * 1024 * 1024;
  }
}

/* Test area start */
// error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);//test purposes only
// register_globals_vars_check();
// $maxrecs = 4; 
// usefull stuff: mysql_affected_rows(), mysql_num_rows().
$ep_debug_logging_all = false; // do not comment out.. make false instead
//$sql_fail_test == true; // used to cause an sql error on new product upload - tests error handling & logs
/* Test area end */

$curver = '4.0.36.a';
$message = '';
if (IS_ADMIN_FLAG) {
  $new_version_details = plugin_version_check_for_updates(2069, $curver);
  if ($new_version_details !== FALSE) {
    $message = '<span class="alert">' . ' - NOTE: A NEW VERSION OF THIS PLUGIN IS AVAILABLE. <a href="' . $new_version_details['link'] . '" target="_blank">[Details]</a>' . '</span>';
  }
}

// Current EP Version - Modded by mc12345678 after Chadd had done so much
$curver              = $curver . ' - 04-29-2016' . $message;
$display_output = ''; // results of import displayed after script run
$ep_dltype = NULL;
$ep_stack_sql_error = false; // function returns true on any 1 error, and notifies user of an error
$specials_print = EASYPOPULATE_4_SPECIALS_HEADING;
$has_specials = false;
$zco_notifier->notify('EP4_START');

// Load language file(s) for main screen menu(s).
if(file_exists(DIR_FS_ADMIN . DIR_WS_LANGUAGES . $_SESSION['language'] . '/easypopulate_4_menus.php'))
{
  require(DIR_FS_ADMIN . DIR_WS_LANGUAGES . $_SESSION['language'] . '/easypopulate_4_menus.php');
} else {
  require(DIR_FS_ADMIN . DIR_WS_LANGUAGES . 'english' . '/easypopulate_4_menus.php');
}

// all mods go in this array as 'name' => 'true' if exist. eg $ep_supported_mods['psd'] => true means it exists.
$ep_supported_mods = array();

// default smart-tags setting when enabled. This can be added to.
$smart_tags = array("\r\n|\r|\n" => '<br />',); // need to check into this more

if (substr($tempdir, -1) != '/') {
  $tempdir .= '/';
}
if (substr($tempdir, 0, 1) == '/') {
  $tempdir = substr($tempdir, 1);
}

//$ep_debug_log_path = DIR_FS_CATALOG . $tempdir;
$ep_debug_log_path = (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ DIR_FS_CATALOG : /* Admin side */ DIR_FS_ADMIN) . $tempdir;

// Check the current path of the above directory, if the selection is the
//  store directory, but the path leads into the admin directory, then 
//  reset the selection to be the admin directory and modify the path so 
//  that the admin directory is no longer typed into the path.  This same
//  action occurs in the configuration window now, but this is in case
//  operation of the program has allowed some other modification to occur
//  and the database for EP4 has the admin path in it.
if (EP4_ADMIN_TEMP_DIRECTORY !== 'true') {
  if (strpos($ep_debug_log_path, DIR_FS_ADMIN) !== false) {
    $temp_rem = substr($ep_debug_log_path, strlen(DIR_FS_ADMIN));
    $db->Execute('UPDATE ' . TABLE_CONFIGURATION . ' SET configuration_value = \'true\' where configuration_key = \'EP4_ADMIN_TEMP_DIRECTORY\'', false, false, 0, true);
    
    $db->Execute('UPDATE ' . TABLE_CONFIGURATION . ' SET configuration_value = \'' . $temp_rem . '\' WHERE configuration_key = \'EASYPOPULATE_4_CONFIG_TEMP_DIR\'', false, false, 0, true);

    // need a message to  be displayed...

    // Reload the page with the path now reset. No parameters are passed.
    zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
  }
}

if ($ep_debug_logging_all == true) {
  $fp = fopen($ep_debug_log_path . 'ep_debug_log.txt', 'w'); // new blank log file on each page impression for full testing log (too big otherwise!!)
  fclose($fp);
}

// Pre-flight checks start here
$chmod_check = ep_4_chmod_check($tempdir);
if ($chmod_check == false) { // test for temporary folder and that it is writable
  // $messageStack->add(EASYPOPULATE_4_MSGSTACK_INSTALL_CHMOD_FAIL, 'caution');
}

// /temp is the default folder - check if it exists & has writeable permissions
if (EASYPOPULATE_4_CONFIG_TEMP_DIR === 'EASYPOPULATE_4_CONFIG_TEMP_DIR' && (is_null($_GET['epinstaller']) && !isset($_GET['epinstaller']) && $_GET['epinstaller'] != 'install')) { // admin area config not installed
  $messageStack->add(sprintf(EASYPOPULATE_4_MSGSTACK_INSTALL_KEYS_FAIL, '<a href="' . zen_href_link(FILENAME_EASYPOPULATE_4, 'epinstaller=install') . '">', '</a>'), 'warning');
}

// installation start
if (!is_null($_GET['epinstaller']) && isset($_GET['epinstaller']) && $_GET['epinstaller'] == 'install') {
  install_easypopulate_4(); // install new configuration keys
  //$messageStack->add(EASYPOPULATE_4_MSGSTACK_INSTALL_CHMOD_SUCCESS, 'success');
  zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
}

if (!is_null($_GET['epinstaller']) && $_GET['epinstaller'] == 'remove') { // remove easy populate configuration variables
  remove_easypopulate_4();
  zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
} // end installation/removal

// START: check for existance of various mods
$ep_supported_mods['psd'] = ep_4_check_table_column(TABLE_PRODUCTS_DESCRIPTION, 'products_short_desc');
$ep_supported_mods['uom'] = ep_4_check_table_column(TABLE_PRODUCTS, 'products_price_uom'); // uom = unit of measure, added by Chadd
$ep_supported_mods['upc'] = ep_4_check_table_column(TABLE_PRODUCTS, 'products_upc');       // upc = UPC Code, added by Chadd
$ep_supported_mods['gpc'] = ep_4_check_table_column(TABLE_PRODUCTS, 'products_gpc'); // gpc = google product category for Google Merchant Center, added by Chadd 10-1-2011
$ep_supported_mods['msrp'] = ep_4_check_table_column(TABLE_PRODUCTS, 'products_msrp'); // msrp = manufacturer's suggested retail price, added by Chadd 1-9-2012
$ep_supported_mods['map'] = ep_4_check_table_column(TABLE_PRODUCTS, 'map_enabled');
$ep_supported_mods['map'] = ($ep_supported_mods['map'] && ep_4_check_table_column(TABLE_PRODUCTS, 'map_price'));
$ep_supported_mods['gppi'] = ep_4_check_table_column(TABLE_PRODUCTS, 'products_group_a_price'); // gppi = group pricing per item, added by Chadd 4-24-2012
$ep_supported_mods['excl'] = ep_4_check_table_column(TABLE_PRODUCTS, 'products_exclusive'); // exclu = Custom Mod for Exclusive Products: 04-24-2012
$ep_supported_mods['dual'] = ep_4_check_table_column(TABLE_PRODUCTS_ATTRIBUTES, 'options_values_price_w');
// END: check for existance of various mods

// custom products fields check
$custom_field_names = array();
$custom_field_check = array();
$custom_fields = array();
if (strlen(EASYPOPULATE_4_CONFIG_CUSTOM_FIELDS) > 0) {
  $custom_field_names = explode(',', EASYPOPULATE_4_CONFIG_CUSTOM_FIELDS);
  foreach ($custom_field_names as $field) {
    if (ep_4_check_table_column(TABLE_PRODUCTS, $field)) {
      $custom_field_check[] = TRUE;
      $custom_fields[] = $field;
    } else {
      $custom_field_check[] = FALSE;
    }
  }
}

// maximum length for a category in this database
$category_strlen_max = zen_field_length(TABLE_CATEGORIES_DESCRIPTION, 'categories_name');

// maximum length for important fields
$categories_name_max_len = zen_field_length(TABLE_CATEGORIES_DESCRIPTION, 'categories_name');
$manufacturers_name_max_len = zen_field_length(TABLE_MANUFACTURERS, 'manufacturers_name');
$products_model_max_len = zen_field_length(TABLE_PRODUCTS, 'products_model');
$products_name_max_len = zen_field_length(TABLE_PRODUCTS_DESCRIPTION, 'products_name');
$products_url_max_len = zen_field_length(TABLE_PRODUCTS_DESCRIPTION, 'products_url');
$artists_name_max_len = zen_field_length(TABLE_RECORD_ARTISTS, 'artists_name');
$record_company_name_max_len = zen_field_length(TABLE_RECORD_COMPANY, 'record_company_name');
$music_genre_name_max_len = zen_field_length(TABLE_MUSIC_GENRE, 'music_genre_name');

$project = PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR;

if ($ep_uses_mysqli) {
  $collation = mysqli_character_set_name($db->link); // should be either latin1 or utf8
} else {
  $collation = mysql_client_encoding(); // should be either latin1 or utf8
}
if ($collation == 'utf8') {
  if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding("UTF-8");
  }
}

if (($collation == 'utf8') && ((substr($project, 0, 5) == "1.3.8") || (substr($project, 0, 5) == "1.3.9"))) {
  //mb_internal_encoding("UTF-8");
  $category_strlen_max = $category_strlen_max / 3;
  $categories_name_max_len = $categories_name_max_len / 3;
  $manufacturers_name_max_len = $manufacturers_name_max_len / 3;
  $products_model_max_len = $products_model_max_len / 3;
  $products_name_max_len = $products_name_max_len / 3;
  $products_url_max_len = $products_url_max_len / 3;
  $artists_name_max_len = $artists_name_max_len / 3;
  $record_company_name_max_len = $record_company_name_max_len / 3;
  $music_genre_name_max_len = $music_genre_name_max_len / 3;

  $zco_notifier->notify('EP4_COLLATION_UTF8_ZC13X');

}

// test for Ajeh
//$ajeh_sql = 'SELECT * FROM '. TABLE_PRODUCTS .' WHERE '.TABLE_PRODUCTS.'.products_id NOT IN (SELECT '. TABLE_PRODUCTS_TO_CATEGORIES.'.products_id FROM '. TABLE_PRODUCTS_TO_CATEGORIES.')';
//$ajeh_result = ep_4_query($ajeh_sql);

// Pre-flight checks finish here

// check default language_id from configuration table DEFAULT_LANGUAGE
// chadd - really shouldn't do this! $epdlanguage_id is better replaced with $langcode[]
// $epdlanguage_id is the only value used here ( I assume for default language)
// default langauage should not be important since all installed languages are used $langcode[]
// and we should iterate through that array (even if only 1 stored value)
// $epdlanguage_id is used only in categories generation code since the products import code doesn't support multi-language categories
/* @var $epdlanguage_query type array */
//$epdlanguage_query = $db->Execute("SELECT languages_id, name FROM ".TABLE_LANGUAGES." WHERE code = '".DEFAULT_LANGUAGE."'");
if (!defined(DEFAULT_LANGUAGE)) {
  $epdlanguage_query = ep_4_query("SELECT languages_id, code FROM " . TABLE_LANGUAGES . " ORDER BY languages_id LIMIT 1");
  $epdlanguage = ($ep_uses_mysqli ? mysqli_fetch_array($epdlanguage_query) : mysql_fetch_array($epdlanguage_query));
  define('DEFAULT_LANGUAGE', $epdlanguage['code']);
}
$epdlanguage_query = ep_4_query("SELECT languages_id, name FROM " . TABLE_LANGUAGES . " WHERE code = '" . DEFAULT_LANGUAGE . "'");
if (($ep_uses_mysqli ? mysqli_num_rows($epdlanguage_query) : mysql_num_rows($epdlanguage_query))) {
  $epdlanguage = ($ep_uses_mysqli ? mysqli_fetch_array($epdlanguage_query) : mysql_fetch_array($epdlanguage_query));
  $epdlanguage_id = $epdlanguage['languages_id'];
  $epdlanguage_name = $epdlanguage['name'];
} else {
  exit("EP4 FATAL ERROR: No default language set."); // this should never happen
}

$langcode = ep_4_get_languages(); // array of currently used language codes ( 1, 2, 3, ...)
$ep_4_SBAEnabled = ep_4_SBA1Exists();

/*
  if ( isset($_GET['export2']) ) { // working on attributes export
  include_once('easypopulate_4_export2.php'); // this file contains all data import code
  } */
$ep4CEONURIDoesExist = false;
if (ep_4_CEONURIExists() == true) {
  $ep4CEONURIDoesExist = true;
  if (!sizeof($languages)) {
    $languages = zen_get_languages();
  }
}
if ( (!is_null($_POST['export']) && isset($_POST['export'])) || (!is_null($_GET['export']) && isset($_GET['export'])) || (!is_null($_POST['exportorder']) && isset($_POST['exportorder'])) ) {
  include_once('easypopulate_4_export.php'); // this file contains all data export code
}
if (!is_null($_POST['import']) && isset($_POST['import'])) {
  include_once('easypopulate_4_import.php'); // this file contains all data import code
}
if (!is_null($_POST['split']) && isset($_POST['split'])) {
  include_once('easypopulate_4_split.php'); // this file has split code
}

// if we had an SQL error anywhere, let's tell the user - maybe they can sort out why
if ($ep_stack_sql_error == true) {
  $messageStack->add(EASYPOPULATE_4_MSGSTACK_ERROR_SQL, 'caution');
}

$upload_dir = (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ DIR_FS_CATALOG : /* Admin side */ DIR_FS_ADMIN) . $tempdir;

//  UPLOAD FILE isset($_FILES['usrfl'])
if (isset($_FILES['uploadfile'])) {
  $file = ep_4_get_uploaded_file('uploadfile');
  if (is_uploaded_file($file['tmp_name'])) {
    ep_4_copy_uploaded_file($file, (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ DIR_FS_CATALOG : /* Admin side */ DIR_FS_ADMIN) . $tempdir);
  }
  $messageStack->add(sprintf("File uploaded successfully: " . $file['name']), 'success');
}

// Handle file deletion (delete only in the current directory for security reasons)
if (((isset($error) && !$error) || !isset($error)) && (!is_null($_POST["delete"]) && isset($_POST["delete"])) && !is_null($_SERVER["SCRIPT_FILENAME"]) && $_POST["delete"] != basename($_SERVER["SCRIPT_FILENAME"])) {
  if (preg_match("/(\.(sql|gz|csv|txt|log))$/i", $_POST["delete"]) && @unlink($upload_dir . basename($_POST["delete"]))) {
    // $messageStack->add(sprintf($_POST["delete"]." was deleted successfully"), 'success');
    zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
  } else {
    $messageStack->add(sprintf("Cannot delete file: " . $_POST["delete"]), 'caution');
    // zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
  }
}
?>

<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
    <title><?php echo TITLE; ?></title>
    <link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
    <link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
    <?php $zco_notifier->notify('EP4_EASYPOPULATE_4_LINK'); ?>
    <script language="javascript" type="text/javascript" src="includes/menu.js"></script>
    <script language="javascript" type="text/javascript" src="includes/general.js"></script>
    <!-- <script language="javascript" src="includes/ep4ajax.js"></script> -->
    <script type="text/javascript">
<!--
	 function init()
	 {
		 cssjsmenu('navbar');
		 if (document.getElementById)
		 {
			 var kill = document.getElementById('hoverJS');
			 kill.disabled = true;
		 }
	 }
// -->
    </script>
    <style type="text/css">
      #epfiles tbody tr:hover { background:#CCCCCC; }
    </style>
  </head>
  <body onLoad="init()">
       <?php require(DIR_WS_INCLUDES . 'header.php'); 
       $zco_notifier->notify('EP4_ZC155_AFTER_HEADER');
       ?>

    <!-- body -->
    <div style="padding:5px">
         <?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?>
      <div class="pageHeading"><?php echo "Easy Populate $curver"; ?></div>

      <div style="text-align:right; float:right; width:25%"><a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'epinstaller=remove') ?>"><?php echo EASYPOPULATE_4_REMOVE_SETTINGS; ?></a>
           <?php
           echo '<br /><b><u>' . EASYPOPULATE_4_CONFIG_SETTINGS . '</u></b><br />';
           echo EASYPOPULATE_4_CONFIG_UPLOAD . '<b>' . (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ 'catalog/' : /* Admin side */ 'admin/') . $tempdir . '</b><br />';
           echo 'Verbose Feedback: ' . (($ep_feedback) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_SPLIT_SHORT . $ep_split_records . '<br />';
           echo EASYPOPULATE_4_DISPLAY_EXEC_TIME . $ep_execution . '<br />';
           switch ($ep_curly_quotes) {
             case 0:
               $ep_curly_text = "No Change";
               break;
             case 1:
               $ep_curly_text = "Basic";
               break;
             case 2:
               $ep_curly_text = "HTML";
               break;
           }
           switch ($ep_char_92) {
             case 0:
               $ep_char92_text = "No Change";
               break;
             case 1:
               $ep_char92_text = "Basic";
               break;
             case 2:
               $ep_char92_text = "HTML";
               break;
           }
           echo 'Convert Curly Quotes: ' . $ep_curly_text . '<br />';
           echo 'Convert Char 0x92: ' . $ep_char92_text . '<br />';
           echo EASYPOPULATE_4_DISPLAY_ENABLE_META . $ep_metatags . '<br />';
           echo EASYPOPULATE_4_DISPLAY_ENABLE_MUSIC . $ep_music . '<br />';

           echo '<br /><b><u>' . EASYPOPULATE_4_DISPLAY_CUSTOM_PRODUCT_FIELDS . '</u></b><br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_SHORT_DESC . (($ep_supported_mods['psd']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_UNIT_MEAS . (($ep_supported_mods['uom']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_UPC . (($ep_supported_mods['upc']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           // Google Product Category for Google Merchant Center
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_GOOGLE_CAT . (($ep_supported_mods['gpc']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_MSRP . (($ep_supported_mods['msrp']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_MAP . (($ep_supported_mods['map']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_GP . (($ep_supported_mods['gppi']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_EXCLUSIVE . (($ep_supported_mods['excl']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_SBA . (($ep_4_SBAEnabled != false) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_CEON . (($ep4CEONURIDoesExist == true) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_DPM . (($ep_supported_mods['dual']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';

           $zco_notifier->notify('EP4_DISPLAY_STATUS');

           echo "<br /><b><u>" . EASYPOPULATE_4_DISPLAY_USER_DEF_FIELDS . "</u></b><br />";
           $i = 0;
           foreach ($custom_field_names as $field) {
             echo $field . ': ' . (($custom_field_check[$i]) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
             $i++;
           }

           echo '<br /><b><u>' . EASYPOPULATE_4_DISPLAY_INSTALLED_LANG . '</u></b><br />';
           foreach ($langcode as $key => $lang) {
             echo $lang['id'] . '-' . $lang['code'] . ': ' . $lang['name'] . '<br />';
           }
           echo EASYPOPULATE_4_DISPLAY_INSTALLED_LANG_DEF . $epdlanguage_id . '-' . $epdlanguage_name . '<br />';
           echo EASYPOPULATE_4_DISPLAY_INT_CHAR_ENC . (function_exists('mb_internal_encoding') ? mb_internal_encoding() : 'mb_internal_encoding not available') . '<br />';
           echo EASYPOPULATE_4_DISPLAY_DB_COLL . $collation . '<br />';

           echo '<br /><b><u>' . EASYPOPULATE_4_DISPLAY_DB_FLD_LGTH . '</u></b><br />';
           echo 'categories_name:' . $categories_name_max_len . '<br />';
           echo 'manufacturers_name:' . $manufacturers_name_max_len . '<br />';
           echo 'products_model:' . $products_model_max_len . '<br />';
           echo 'products_name:' . $products_name_max_len . '<br />';

           $zco_notifier->notify('EP4_MAX_LEN');
           /*  // some error checking
             echo '<br /><br />Problem Data: '. mysql_num_rows($ajeh_result);
             echo '<br />Memory Usage: '.memory_get_usage();
             echo '<br />Memory Peak: '.memory_get_peak_usage();
             echo '<br /><br />';
             print_r($langcode);
             echo '<br /><br />code: '.$langcode[1]['id'];
            */
           //register_globals_vars_check_4(); // testing
           ?></div>

      <?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?>

      <div style="text-align:left">

        <?php echo zen_draw_form('ep4 upload', FILENAME_EASYPOPULATE_4, '', 'post', 'enctype="multipart/form-data"'); ?>
          <div align = "left"><br />
            <b><?php echo EASYPOPULATE_4_DISPLAY_TITLE_UPLOAD; ?></b><br />
            <?php echo sprintf(EASYPOPULATE_4_DISPLAY_MAX_UP_SIZE, $upload_max_filesize, round($upload_max_filesize / 1024 / 1024)) . '<br />'; ?>
            <?php echo zen_draw_hidden_field('MAX_FILE_SIZE', $upload_max_filesize, $parameters = ''); ?>
            <?php echo zen_draw_file_field('uploadfile', false); ?>
            <?php echo zen_draw_input_field("buttoninsert", EASYPOPULATE_4_DISPLAY_UPLOAD_BUTTON_TEXT, '', false, 'submit', true); ?>
            <br /><br /><br />
          </div>
        </form>

        <?php
// echo zen_draw_form('custom', 'easypopulate_4.php', 'id="custom"', 'get'); 
        echo zen_draw_form('custom', FILENAME_EASYPOPULATE_4, '', 'post', 'id="custom"');
        ?>

        <div align = "left">
             <?php
             $manufacturers_array = array();
             $manufacturers_array[] = array("id" => '', 'text' => EASYPOPULATE_4_DISPLAY_MANUFACTURERS);
             if ($ep_uses_mysqli) {
               $manufacturers_query = mysqli_query($db->link, "SELECT manufacturers_id, manufacturers_name FROM " . TABLE_MANUFACTURERS . " ORDER BY manufacturers_name");
               while ($manufacturers = mysqli_fetch_array($manufacturers_query)) {
                 $manufacturers_array[] = array("id" => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name']);
               }
			
             } else {
               $manufacturers_query = mysql_query("SELECT manufacturers_id, manufacturers_name FROM " . TABLE_MANUFACTURERS . " ORDER BY manufacturers_name");
               while ($manufacturers = mysql_fetch_array($manufacturers_query)) {
                 $manufacturers_array[] = array("id" => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name']);
               }
             }
             $status_array = array(array("id" => '1', 'text' => EASYPOPULATE_4_DD_STATUS_DEFAULT ), array("id" => '1', 'text' => EASYPOPULATE_4_DD_STATUS_ACTIVE), array("id" => '0', 'text' => EASYPOPULATE_4_DD_STATUS_INACTIVE), array("id" => '3', 'text' => EASYPOPULATE_4_DD_STATUS_ALL));
             $export_type_array = array(array("id" => '0', 'text' => EASYPOPULATE_4_DD_DOWNLOAD_DEFAULT),
               array("id" => '0', 'text' => EASYPOPULATE_4_DD_DOWNLOAD_COMPLETE),
               array("id" => '1', 'text' => EASYPOPULATE_4_DD_DOWNLOAD_QUANTITY),
               array("id" => '2', 'text' => EASYPOPULATE_4_DD_DOWNLOAD_BREAKS));

             echo "<b>" . EASYPOPULATE_4_DISPLAY_FILTERABLE_EXPORTS . "</b><br />";

             echo zen_draw_pull_down_menu('ep_export_type', $export_type_array) . ' ';
             echo ' ' . zen_draw_pull_down_menu('ep_category_filter', array_merge(array(0 => array("id" => '', 'text' => EASYPOPULATE_4_DD_FILTER_CATEGORIES)), zen_get_category_tree())) . ' ';
             echo ' ' . zen_draw_pull_down_menu('ep_manufacturer_filter', $manufacturers_array) . ' ';
             echo ' ' . zen_draw_pull_down_menu('ep_status_filter', $status_array) . ' ';
             echo zen_draw_input_field('export', EASYPOPULATE_4_DD_FILTER_EXPORT, ' style="padding: 0px"', false, 'submit');
             ?>				
          <br /><br />
        </div></form>
  <?php
	echo zen_draw_form('custom2', FILENAME_EASYPOPULATE_4, '', 'post', 'id="custom2"'); 
	?>
	
    <div align = "left">
		<?php	
		$order_export_type_array  = array(array( "id" => '0', 'text' => EASYPOPULATE_4_ORDERS_DROPDOWN_FIRST ),
			array( "id" => '1', 'text' => EASYPOPULATE_4_ORDERS_FULL ),
			array( "id" => '2', 'text' => EASYPOPULATE_4_ORDERS_NEWFULL ),
			array( "id" => '3', 'text' => EASYPOPULATE_4_ORDERS_NO_ATTRIBS ),
      array( "id" => '4', 'text' => EASYPOPULATE_4_ORDERS_ATTRIBS ));
		$order_status_export_array = array ();
		echo EASYPOPULATE_4_ORDERS_DROPDOWN_TITLE;
		
		echo zen_draw_pull_down_menu('ep_order_export_type', $order_export_type_array) . ' ';
    echo zen_cfg_pull_down_order_statuses(NULL, 'order_status');
		echo zen_draw_input_field('exportorder', EASYPOPULATE_4_ORDERS_DROPDOWN_EXPORT, ' style="padding: 0px"', false, 'submit');
		?>				
    <br /><br />
    </div></form>
    
    
        <b><?php echo EASYPOPULATE_4_DISPLAY_PRODUCTS_PRICE_EXPORT_OPTION; ?></b><br />
        <!-- Download file links -->
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=full', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_COMPLETE_PRODUCTS; ?></a><br/>
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=priceqty', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_PRICE_QTY; ?></a><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=pricebreaks', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_PRICE_BREAKS; ?></a><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=featured', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_FEATURED; ?></a><br />

        <br /><b><?php echo EASYPOPULATE_4_DISPLAY_TITLE_CATEGORY; ?></b><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=category', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_CATEGORY; ?></a><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=categorymeta', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_CATEGORYMETA; ?></a><br/>

        <br /><?php echo EASYPOPULATE_4_DISPLAY_TITLE_ATTRIBUTE; ?><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=attrib_basic', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_ATTRIBUTE_BASIC; ?></a><br /> 
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=attrib_detailed', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_ATTRIBUTE_DETAILED; ?></a><br />
        <?php
        /* Begin SBA1 addition */
        if ($ep_4_SBAEnabled != false) {
          ?>
          <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=SBA_detailed', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_DETAILED_SBA; ?></a><br />
          <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=SBAStock', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_SBA_STOCK; ?></a><br />

          <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=SBAStockProdFilter, $request_type'); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_SBA_STOCK_ASC; ?></a><br />

        <?php } /* End SBA1 Addition */ 
		$zco_notifier->notify('EP4_LINK_SELECTION_END');
		?>

        <br><?php echo EASYPOPULATE_4_DISPLAY_TITLE_EXPORT_ONLY; ?><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=options', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_OPTION_NAMES; ?></a><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=values', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_OPTION_VALUES; ?></a><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=optionvalues', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_OPTION_NAMES_TO_VALUES; ?></a><br />

        <?php
// List uploaded files in multifile mode
// Table header
        echo '<br /><br />';
//	echo "<table id=\"epfiles\"    width=\"80%\" border=1 cellspacing=\"2\" cellpadding=\"2\">\n";
// $upload_dir = DIR_FS_CATALOG.$tempdir; // defined above
        if ($dirhandle = opendir($upload_dir)) {
          $files = array();
          while (false !== ($files[] = readdir($dirhandle))); // get directory contents
          closedir($dirhandle);
          $file_count = 0;
          sort($files);
          /*
           * Have a list of files... Now need to work through them to identify what they can do.
           * After identified what they can do, need to generate the screen view of their option(s).  Ideally, every file will have the same capability, but will be grouped with a title and associated action.  
           * So need to identify the "#" of groups, and loop through the files for those groups.  Probably a case statement
           */

          $filenames = array(
            "attrib-basic-ep" => ATTRIB_BASIC_EP,
            "attrib-detailed-ep" => ATTRIB_DETAILED_EP_DESC,
            "category-ep" => CATEGORY_EP_DESC,
            "categorymeta-ep" => CATEGORYMETA_EP_DESC,
            "featured-ep" => FEATURED_EP_DESC,
            "pricebreaks-ep" => PRICEBREAKS_EP_DESC,
            "priceqty-ep" => PRICEQTY_EP_DESC,
            "sba-detailed-ep" => SBA_DETAILED_EP_DESC,
            "sba-stock-ep" => SBA_STOCK_EP_DESC,
            "orders-full-ep"=>ORDERSEXPORT_LINK_SAVE1,
            "orders-fullb-ep"=>ORDERSEXPORT_LINK_SAVE1B,
            "orders-noattribs-ep"=>ORDERSEXPORT_LINK_SAVE2,
            "orders-onlyAttribs-ep"=>ORDERSEXPORT_LINK_SAVE3
          );
		  $zco_notifier->notify('EP4_FILENAMES');

          $filetypes = array();

          for ($i = 0; $i < sizeof($files); $i++) {
            if (($files[$i] != ".") && ($files[$i] != "..") && preg_match("/\.(sql|gz|csv|txt|log)$/i", $files[$i])) {
              $found = false;

              foreach ($filenames as $key => $val) {
                if (strtolower(substr($files[$i], 0, strlen($key))) == strtolower($key)) {
                  $filetypes[$key][] = $i;
                  $found = true;
                  break;
                }
              }

              if ($found == false) {
                // Treat as an everything else file.
                $filetypes['zzzzzzzz'][] = $i;
              }
            } // End of if file is one to manage here.
          } // End For $i of $files
          ksort($filetypes);
          
          $filenames_merged = array();
          $filenames_merged = array_merge($filenames, array("zzzzzzzz" => CATCHALL_EP_DESC));

          echo "\n";
          echo "\n";
          echo "<table id=\"epfiles\"    width=\"80%\" border=1 cellspacing=\"2\" cellpadding=\"2\">\n";
          if (EP4_SHOW_ALL_FILETYPES == 'Hidden') {
            echo "<tr><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_FILENAME . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_SIZE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DATE_TIME . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_TYPE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_SPLIT . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_IMPORT . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DELETE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DOWNLOAD . "</th>\n";
          }

          foreach ((EP4_SHOW_ALL_FILETYPES != 'false' ? $filenames_merged : $filetypes) as $key => $val) {
            (EP4_SHOW_ALL_FILETYPES != 'false' ? $val = $filetypes[$key] : '');
            if (EP4_SHOW_ALL_FILETYPES == 'Hidden') {
              $val = array();
              for ($i = 0; $i < sizeof($files); $i++) {
                $val[$i] = $i;
              }
            }

            $file_count = 0;
            //Display the information needed to start use of a filetype.
            $plural_state = "<strong>" . (sizeof($val) > 1 ? EP_DESC_PLURAL : EP_DESC_SING) . "</strong>";
            if (EP4_SHOW_ALL_FILETYPES != 'Hidden') {
              echo "<tr><td colspan=\"8\">" . sprintf($filenames_merged[$key], "<strong>" . $key . "</strong>", $plural_state) . "</td></tr>";
              echo "<tr><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_FILENAME . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_SIZE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DATE_TIME . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_TYPE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_SPLIT . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_IMPORT . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DELETE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DOWNLOAD . "</th>\n";
            }

            for ($i = 0; $i < sizeof($val); $i++) {
              if (EP4_SHOW_ALL_FILETYPES != 'Hidden' || (EP4_SHOW_ALL_FILETYPES == 'Hidden' && ($files[$i] != ".") && ($files[$i] != "..") && preg_match("/\.(sql|gz|csv|txt|log)$/i", $files[$i]) )) {
                $file_count++;
                echo '<tr><td>' . $files[$val[$i]] . '</td>
					<td align="right">' . filesize($upload_dir . $files[$val[$i]]) . '</td>
					<td align="center">' . date("Y-m-d H:i:s", filemtime($upload_dir . $files[$val[$i]])) . '</td>';
                $ext = strtolower(end(explode('.', $files[$val[$i]])));
                // file type
                switch ($ext) {
                  case 'sql':
                    echo '<td align=center>SQL</td>';
                    break;
                  case 'gz':
                    echo '<td align=center>GZip</td>';
                    break;
                  case 'csv':
                    echo '<td align=center>CSV</td>';
                    break;
                  case 'txt':
                    echo '<td align=center>TXT</td>';
                    break;
                  case 'log':
                    echo '<td align=center>LOG</td>';
                    break;
                  default:
                }
                // file management
                if ($ext == 'csv') {
                  // $_SERVER["PHP_SELF"] vs $_SERVER['SCRIPT_NAME']
//                  echo "<td align=center><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?split=" . $files[$val[$i]] . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_SPLIT . "</a></td>\n";
                  echo "<td align=center>" . zen_draw_form('split_form', basename($_SERVER['SCRIPT_NAME']), /*$parameters = */'', 'post', /*$params =*/ '', $request_type == 'SSL') . zen_draw_hidden_field('split', urlencode($files[$val[$i]]), /*$parameters = */'') . zen_draw_input_field('split_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_SPLIT, /*$parameters = */'', /*$required = */false, /*$type = */'submit') . "</form></td>\n"; //. "<a href=\"" . $_SERVER['SCRIPT_NAME'] . "?split=" . $files[$val[$i]] . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_SPLIT . "</a></td>\n";
                  if (strtolower(substr($files[$val[$i]], 0, 12)) == "sba-stock-ep") {
//                    echo "<td align=center><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?import=" . $files[$val[$i]] . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT . "</a><br /><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?import=" . $files[$val[$i]] . "&amp;sync=1\"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT_SYNC; </a></td>\n";
                    echo "<td align=center>" . zen_draw_form('import_form', basename($_SERVER['SCRIPT_NAME']), /*$parameters = */'', 'post', /*$params =*/ '', $request_type == 'SSL') . zen_draw_hidden_field('import', urlencode($files[$val[$i]]), /*$parameters = */'') . zen_draw_input_field('import_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT, /*$parameters = */'', /*$required = */false, /*$type = */'submit') . "</form><br />" . zen_draw_form('import_form', basename($_SERVER['SCRIPT_NAME']), /*$parameters = */'', 'post', /*$params =*/ '', $request_type == 'SSL') . zen_draw_hidden_field('import', urlencode($files[$val[$i]]), /*$parameters = */'') . zen_draw_hidden_field('sync', '1', /*$parameters = */'') . zen_draw_input_field('import_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT_SYNC, /*$parameters = */'', /*$required = */false, /*$type = */'submit') . "</form></td>\n"; //"<a href=\"" . $_SERVER['SCRIPT_NAME'] . "?import=" . $files[$val[$i]] . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT . "</a><br /><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?import=" . $files[$val[$i]] . "&amp;sync=1\"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT_SYNC; //</a></td>\n";
                  } else {
//                    echo "<td align=center><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?import=" . $files[$val[$i]] . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT . "</a></td>\n";
                    echo "<td align=center>" . zen_draw_form('import_form', basename($_SERVER['SCRIPT_NAME']), /*$parameters = */'', 'post', /*$params =*/ '', $request_type == 'SSL') . zen_draw_hidden_field('import', urlencode($files[$val[$i]]), /*$parameters = */'') . zen_draw_input_field('import_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT, /*$parameters = */'', /*$required = */false, /*$type = */'submit') . "</form></td>\n"; // <a href=\"" . $_SERVER['SCRIPT_NAME'] . "?import=" . $files[$val[$i]] . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT . "</a></td>\n";
                  }
//        echo zen_draw_form('custom', 'easypopulate_4.php', '', 'post', 'id="custom"');

                  echo "<td align=center>" . zen_draw_form('delete_form', basename($_SERVER['SCRIPT_NAME']), /*$parameters = */'', 'post', /*$params =*/ '', $request_type == 'SSL') . zen_draw_hidden_field('delete', urlencode($files[$val[$i]]), /*$parameters = */'') . zen_draw_input_field('delete_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_DELETE, /*$parameters = */'', /*$required = */false, /*$type = */'submit') . "</form></td>";
/*                  echo "<td align=center><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?delete=" . urlencode($files[$val[$i]]) . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_DELETE . "</a></td>";*/
                  echo "<td align=center><a href=\"" . (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* BOF Storeside */ defined('ENABLE_SSL_CATALOG') && ENABLE_SSL_CATALOG === 'true' ? HTTPS_CATALOG_SERVER . DIR_WS_HTTPS_CATALOG : HTTP_CATALOG_SERVER . DIR_WS_CATALOG /* EOF Storeside */ : /* BOF Adminside */ (defined('ENABLE_SSL_ADMIN') && ENABLE_SSL_ADMIN === 'true' ? (defined('HTTPS_SERVER') ? HTTPS_SERVER : HTTP_SERVER) . (defined('DIR_WS_HTTPS_ADMIN') ? DIR_WS_HTTPS_ADMIN : DIR_WS_ADMIN) : HTTP_SERVER . DIR_WS_ADMIN) /* EOF Adminside */) . $tempdir . $files[$val[$i]] . "\" target=_blank>" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_DOWNLOAD . "</a></td></tr>\n";
                } else {
                  echo "<td>&nbsp;</td>\n";
                  echo "<td>&nbsp;</td>\n";
//                  echo "<td align=center><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?delete=" . urlencode($files[$val[$i]]) . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_DELETE . "</a></td>";
                  echo "<td align=center>" . zen_draw_form('delete_form', basename($_SERVER['SCRIPT_NAME']), /*$parameters = */'', 'post', /*$params =*/ '', $request_type == 'SSL') . zen_draw_hidden_field('delete', urlencode($files[$val[$i]]), /*$parameters = */'') . zen_draw_input_field('delete_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_DELETE, /*$parameters = */'', /*$required = */false, /*$type = */'submit') . "</form></td>";
                  echo "<td align=center><a href=\"" . (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* BOF Storeside */ defined('ENABLE_SSL_CATALOG') && ENABLE_SSL_CATALOG === 'true' ? HTTPS_CATALOG_SERVER . DIR_WS_HTTPS_CATALOG : HTTP_CATALOG_SERVER . DIR_WS_CATALOG /* EOF Storeside */ : /* BOF Adminside */ (defined('ENABLE_SSL_ADMIN') && ENABLE_SSL_ADMIN === 'true' ? (defined('HTTPS_SERVER') ? HTTPS_SERVER : HTTP_SERVER) . (defined('DIR_WS_HTTPS_ADMIN') ? DIR_WS_HTTPS_ADMIN : DIR_WS_ADMIN) : HTTP_SERVER . DIR_WS_ADMIN) /* EOF Adminside */) . $tempdir . $files[$val[$i]] . "\" target=_blank>" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_DOWNLOAD . "</a></td></tr>\n";
                }
              }
            } // End loop within a filetype
            if ($file_count == 0 && EP4_SHOW_ALL_FILETYPES != 'Hidden') {
              echo "<tr><td COLSPAN=8><font color='red'>" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_NONE_SUPPORTED . "</font></td></tr>\n";
            } // if (sizeof($files)>0)
            if (EP4_SHOW_ALL_FILETYPES == 'Hidden') {
              break;
            }
          } // End foreach filetype 
          if (EP4_SHOW_ALL_FILETYPES != 'Hidden') {
            echo "</table>\n";
            if (sizeof($filetypes) == 0 && EP4_SHOW_ALL_FILETYPES == 'false') {
              echo "<table id=\"epfiles\"    width=\"80%\" border=1 cellspacing=\"2\" cellpadding=\"2\">\n";
              echo "<tr><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_FILENAME . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_SIZE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DATE_TIME . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_TYPE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_SPLIT . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_IMPORT . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DELETE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DOWNLOAD . "</th>\n";
              echo "<tr><td COLSPAN=8><font color='red'>" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_NONE_SUPPORTED . "</font></td></tr>\n";
              echo "</table>\n";
            }
          }
        } else { // can't open directory
          echo "<tr><td COLSPAN=8><font color='red'>" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_ERROR_FOLDER_OPEN . "</font> " . $tempdir . "</td></tr>\n";
          $error = true;
        } // opendir()
        if (EP4_SHOW_ALL_FILETYPES == 'Hidden') {
          echo "</table>\n";
          if (sizeof($filetypes) == 0) {
            echo "<table id=\"epfiles\"    width=\"80%\" border=1 cellspacing=\"2\" cellpadding=\"2\">\n";
            echo "<tr><td COLSPAN=8><font color='red'>" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_NONE_SUPPORTED . "</font></td></tr>\n";
            echo "</table>\n";
          }
        }




        /*
          echo "<div>";
          $test_string = "Πλαστικά^Εξαρτήματα";
          $test_array1 = explode("^", $test_string);
          echo "<br />Using explode() with: ".$test_string."<br />";
          print_r($test_array1);

          $test_array2 = mb_split('\x5e', $test_string);
          echo "<br /><br />Using mb_split() with: ".$test_string."<br />";
          print_r($test_array2);
          echo "</div>";
         */
        ?>
      </div>
      <div id='results'></div>
      <?php
      echo $display_output; // upload results
      if (strlen($specials_print) > strlen(EASYPOPULATE_4_SPECIALS_HEADING)) {
        echo '<br />' . $specials_print . EASYPOPULATE_4_SPECIALS_FOOTER; // specials summary
      }
      ?>
    </div>
    <br />
    <?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
  </body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
