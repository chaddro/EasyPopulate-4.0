<?php
// $Id: easypopulate_4.php, v4.0.30 06-27-2015 mc12345678 $

// CSV VARIABLES - need to make this configurable in the ADMIN
// $csv_delimiter = "\t"; // "\t" = tab AND "," = COMMA
$csv_delimiter = ","; // "\t" = tab AND "," = COMMA
$csv_enclosure   = '"'; // chadd - i think you should always us the '"' for best compatibility

$excel_safe_output = true; // this forces enclosure in quotes

// START INITIALIZATION
require_once ('includes/application_top.php');

/* Configuration Variables from Admin Interface  */
$tempdir          = EASYPOPULATE_4_CONFIG_TEMP_DIR;
$ep_date_format   = EASYPOPULATE_4_CONFIG_FILE_DATE_FORMAT;
$ep_raw_time      = EASYPOPULATE_4_CONFIG_DEFAULT_RAW_TIME;
$ep_debug_logging = ((EASYPOPULATE_4_CONFIG_DEBUG_LOGGING == 'true') ? true : false);
$ep_split_records = (int)EASYPOPULATE_4_CONFIG_SPLIT_RECORDS;
$price_with_tax   = ((EASYPOPULATE_4_CONFIG_PRICE_INC_TAX == 'true') ? true : false);
$strip_smart_tags = ((EASYPOPULATE_4_CONFIG_SMART_TAGS == 'true') ? true : false);
$max_qty_discounts= EASYPOPULATE_4_CONFIG_MAX_QTY_DISCOUNTS;
$ep_feedback      = ((EASYPOPULATE_4_CONFIG_VERBOSE == 'true') ? true : false);
$ep_execution     = (int)EASYPOPULATE_4_CONFIG_EXECUTION_TIME;
$ep_curly_quotes  = (int)EASYPOPULATE_4_CONFIG_CURLY_QUOTES;
$ep_char_92       = (int)EASYPOPULATE_4_CONFIG_CHAR_92;
$ep_metatags      = (int)EASYPOPULATE_4_CONFIG_META_DATA; // 0-Disable, 1-Enable
$ep_music         = (int)EASYPOPULATE_4_CONFIG_MUSIC_DATA; // 0-Disable, 1-Enable
$ep_uses_mysqli   = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3' ? true : false);

@set_time_limit($ep_execution);  // executin limit in seconds. 300 = 5 minutes before timeout, 0 means no timelimit

if ((isset($error) && !$error) || !isset($error)) { 
  $upload_max_filesize=ini_get("upload_max_filesize");
  if (preg_match("/([0-9]+)K/i",$upload_max_filesize,$tempregs)) $upload_max_filesize=$tempregs[1]*1024;
  if (preg_match("/([0-9]+)M/i",$upload_max_filesize,$tempregs)) $upload_max_filesize=$tempregs[1]*1024*1024;
  if (preg_match("/([0-9]+)G/i",$upload_max_filesize,$tempregs)) $upload_max_filesize=$tempregs[1]*1024*1024*1024;
}

/* Test area start */
// error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);//test purposes only
// register_globals_vars_check();
// $maxrecs = 4; 
// usefull stuff: mysql_affected_rows(), mysql_num_rows().
$ep_debug_logging_all = false; // do not comment out.. make false instead
//$sql_fail_test == true; // used to cause an sql error on new product upload - tests error handling & logs
/* Test area end */

// Current EP Version - Modded by mc12345678 after Chadd had done so much
$curver              = '4.0.30 - Beta 06-27-2015';
$display_output      = ''; // results of import displayed after script run
$ep_dltype           = NULL;
$ep_stack_sql_error  = false; // function returns true on any 1 error, and notifies user of an error
$specials_print      = EASYPOPULATE_4_SPECIALS_HEADING;
$has_specials        = false;

// all mods go in this array as 'name' => 'true' if exist. eg $ep_supported_mods['psd'] => true means it exists.
$ep_supported_mods = array();

// default smart-tags setting when enabled. This can be added to.
$smart_tags = array("\r\n|\r|\n" => '<br />', ); // need to check into this more

if (substr($tempdir, -1) != '/') $tempdir .= '/';
if (substr($tempdir, 0, 1) == '/') $tempdir = substr($tempdir, 1);

$ep_debug_log_path = DIR_FS_CATALOG.$tempdir;

if ($ep_debug_logging_all == true) {
	$fp = fopen($ep_debug_log_path.'ep_debug_log.txt','w'); // new blank log file on each page impression for full testing log (too big otherwise!!)
	fclose($fp);
}

// Pre-flight checks start here
$chmod_check = ep_4_chmod_check($tempdir);
if ($chmod_check == false) { // test for temporary folder and that it is writable
	// $messageStack->add(EASYPOPULATE_4_MSGSTACK_INSTALL_CHMOD_FAIL, 'caution');
}

// /temp is the default folder - check if it exists & has writeable permissions
if (EASYPOPULATE_4_CONFIG_TEMP_DIR == 'EASYPOPULATE_4_CONFIG_TEMP_DIR' && ($_GET['epinstaller'] != 'install')) { // admin area config not installed
	$messageStack->add(sprintf(EASYPOPULATE_4_MSGSTACK_INSTALL_KEYS_FAIL, '<a href="' . zen_href_link(FILENAME_EASYPOPULATE_4, 'epinstaller=install') . '">', '</a>'), 'warning');
}

// installation start
if ($_GET['epinstaller'] == 'install'  ) {
	install_easypopulate_4(); // install new configuration keys
	//$messageStack->add(EASYPOPULATE_4_MSGSTACK_INSTALL_CHMOD_SUCCESS, 'success');
	zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
} 

if ($_GET['epinstaller'] == 'remove') { // remove easy populate configuration variables
	remove_easypopulate_4();
	zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
} // end installation/removal

// START: check for existance of various mods
$ep_supported_mods['psd'] = ep_4_check_table_column(TABLE_PRODUCTS_DESCRIPTION,'products_short_desc');
$ep_supported_mods['uom'] = ep_4_check_table_column(TABLE_PRODUCTS,'products_price_uom'); // uom = unit of measure, added by Chadd
$ep_supported_mods['upc'] = ep_4_check_table_column(TABLE_PRODUCTS,'products_upc');       // upc = UPC Code, added by Chadd
$ep_supported_mods['gpc'] = ep_4_check_table_column(TABLE_PRODUCTS,'products_gpc'); // gpc = google product category for Google Merchant Center, added by Chadd 10-1-2011
$ep_supported_mods['msrp'] = ep_4_check_table_column(TABLE_PRODUCTS,'products_msrp'); // msrp = manufacturer's suggested retail price, added by Chadd 1-9-2012
$ep_supported_mods['map'] = ep_4_check_table_column(TABLE_PRODUCTS,'map_enabled');
$ep_supported_mods['map'] = ($ep_supported_mods['map'] && ep_4_check_table_column(TABLE_PRODUCTS, 'map_price'));
$ep_supported_mods['gppi'] = ep_4_check_table_column(TABLE_PRODUCTS,'products_group_a_price'); // gppi = group pricing per item, added by Chadd 4-24-2012
$ep_supported_mods['excl'] = ep_4_check_table_column(TABLE_PRODUCTS,'products_exclusive'); // exclu = Custom Mod for Exclusive Products: 04-24-2012
$ep_supported_mods['dual'] = ep_4_check_table_column(TABLE_PRODUCTS_ATTRIBUTES, 'options_values_price_w');
// END: check for existance of various mods

// custom products fields check
$custom_field_names = array();
$custom_field_check = array();
$custom_fields = array();
if(strlen(EASYPOPULATE_4_CONFIG_CUSTOM_FIELDS) > 0) {
	$custom_field_names = explode(',',EASYPOPULATE_4_CONFIG_CUSTOM_FIELDS);
	foreach($custom_field_names as $field) {
		if ( ep_4_check_table_column(TABLE_PRODUCTS,$field) ) {
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

$project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;

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

if ( ($collation == 'utf8') && ((substr($project,0,5) == "1.3.8") || (substr($project,0,5) == "1.3.9")) ) {
	//mb_internal_encoding("UTF-8");
	$category_strlen_max = $category_strlen_max/3;
	$categories_name_max_len = $categories_name_max_len/3;
	$manufacturers_name_max_len = $manufacturers_name_max_len/3;
	$products_model_max_len = $products_model_max_len/3;
	$products_name_max_len = $products_name_max_len/3;
	$products_url_max_len = $products_url_max_len/3;
	$artists_name_max_len = $artists_name_max_len/3;
	$record_company_name_max_len = $record_company_name_max_len/3;
	$music_genre_name_max_len = $music_genre_name_max_len/3;
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
	$epdlanguage_query = ep_4_query("SELECT languages_id, code FROM ".TABLE_LANGUAGES." ORDER BY languages_id LIMIT 1");
	$epdlanguage = ($ep_uses_mysqli ? mysqli_fetch_array($epdlanguage_query) : mysql_fetch_array($epdlanguage_query));
	define('DEFAULT_LANGUAGE', $epdlanguage['code']);
}
$epdlanguage_query = ep_4_query("SELECT languages_id, name FROM ".TABLE_LANGUAGES." WHERE code = '".DEFAULT_LANGUAGE."'");
if (($ep_uses_mysqli ? mysqli_num_rows($epdlanguage_query) : mysql_num_rows($epdlanguage_query))) {
	$epdlanguage = ($ep_uses_mysqli ? mysqli_fetch_array($epdlanguage_query) : mysql_fetch_array($epdlanguage_query));
	$epdlanguage_id   = $epdlanguage['languages_id'];
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
if ( isset($_POST['export']) OR isset($_GET['export'])  ) {
	include_once('easypopulate_4_export.php'); // this file contains all data export code
}
if ( isset($_GET['import']) ) {
	include_once('easypopulate_4_import.php'); // this file contains all data import code
}
if ( isset($_GET['split']) ) {
	include_once('easypopulate_4_split.php'); // this file has split code
}

// if we had an SQL error anywhere, let's tell the user - maybe they can sort out why
if ($ep_stack_sql_error == true) $messageStack->add(EASYPOPULATE_4_MSGSTACK_ERROR_SQL, 'caution');

$upload_dir = DIR_FS_CATALOG . $tempdir;

//  UPLOAD FILE isset($_FILES['usrfl'])
if (isset($_FILES['uploadfile'])) {
	$file = ep_4_get_uploaded_file('uploadfile'); 		
	if (is_uploaded_file($file['tmp_name'])) {
		ep_4_copy_uploaded_file($file, DIR_FS_CATALOG . $tempdir);
	}
	$messageStack->add(sprintf("File uploaded successfully: ".$file['name']), 'success');
}

// Handle file deletion (delete only in the current directory for security reasons)
if (((isset($error) && !$error) || !isset($error)) && isset($_REQUEST["delete"]) && $_REQUEST["delete"]!=basename($_SERVER["SCRIPT_FILENAME"])) { 
  if (preg_match("/(\.(sql|gz|csv|txt|log))$/i",$_REQUEST["delete"]) && @unlink($upload_dir.basename($_REQUEST["delete"]))) {
	// $messageStack->add(sprintf($_REQUEST["delete"]." was deleted successfully"), 'success');
	zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
  }
  else {
	$messageStack->add(sprintf("Cannot delete file: ".$_REQUEST["delete"]), 'caution');
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
    <style>
		#epfiles tbody tr:hover { background:#CCCCCC; }
	</style>
</head>
<body onLoad="init()">
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>

<!-- body -->
<div style="padding:5px">
	<?php 	echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?>
	<div class="pageHeading"><?php echo "Easy Populate $curver"; ?></div>
    
	<div style="text-align:right; float:right; width:25%"><a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'epinstaller=remove') ?>"><?php echo EASYPOPULATE_4_REMOVE_SETTINGS; ?></a>
    <?php
		echo '<br/><b><u>' . EASYPOPULATE_4_CONFIG_SETTINGS . '</u></b><br/>';
		echo EASYPOPULATE_4_CONFIG_UPLOAD . '<b>'.$tempdir.'</b><br/>'; 
		echo 'Verbose Feedback: '.(($ep_feedback) ? '<font color="green">TRUE</font>':"FALSE").'<br/>';
		echo EASYPOPULATE_4_DISPLAY_SPLIT_SHORT . $ep_split_records.'<br/>';
		echo EASYPOPULATE_4_DISPLAY_EXEC_TIME . $ep_execution.'<br/>';
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
		echo 'Convert Curly Quotes: '.$ep_curly_text.'<br/>';
		echo 'Convert Char 0x92: '.$ep_char92_text.'<br/>';
		echo EASYPOPULATE_4_DISPLAY_ENABLE_META.$ep_metatags.'<br/>';
		echo EASYPOPULATE_4_DISPLAY_ENABLE_MUSIC.$ep_music.'<br/>';
			
		echo '<br/><b><u>Custom Products Fields</u></b><br/>';
		echo 'Product Short Descriptions: '.(($ep_supported_mods['psd']) ? '<font color="green">TRUE</font>':"FALSE").'<br/>';
		echo 'Product Unit of Measure: '.(($ep_supported_mods['uom']) ? '<font color="green">TRUE</font>':"FALSE").'<br/>';
		echo 'Product UPC Code: '.(($ep_supported_mods['upc']) ? '<font color="green">TRUE</font>':"FALSE").'<br/>';
		// Google Product Category for Google Merchant Center
		echo 'Google Product Category: '.(($ep_supported_mods['gpc']) ? '<font color="green">TRUE</font>':"FALSE").'<br/>';
		echo "Manufacturer's Suggested Retail Price: ".(($ep_supported_mods['msrp']) ? '<font color="green">TRUE</font>':"FALSE").'<br/>';
    echo "Manufacturer's Advertised Price: ".(($ep_supported_mods['map']) ? '<font color="green">TRUE</font>':"FALSE").'<br/>';
    echo "Group Pricing Per Item: ".(($ep_supported_mods['gppi']) ? '<font color="green">TRUE</font>':"FALSE").'<br/>';
		echo "Exclusive Products Mod: ".(($ep_supported_mods['excl']) ? '<font color="green">TRUE</font>':"FALSE").'<br/>';
 		echo "Stock By Attributes Mod: ".(($ep_4_SBAEnabled != false) ? '<font color="green">TRUE</font>':"FALSE").'<br/>';
    echo "CEON URI Rewriter Mod: " . (($ep4CEONURIDoesExist == true) ? '<font color="green">TRUE</font>' : "FALSE") . '<br/>';
    echo "Dual Pricing Mod: " . (($ep_supported_mods['dual']) ? '<font color="green">TRUE</font>':"FALSE").'<br/>';

		echo "<br/><b><u>User Defined Products Fields: </u></b><br/>";
		$i = 0;
		foreach ($custom_field_names as $field) {
			echo $field.': '.(($custom_field_check[$i]) ? '<font color="green">TRUE</font>':"FALSE").'<br/>';
			$i++;
		}
		
		echo '<br/><b><u>Installed Languages</u></b> <br/>';
		foreach ($langcode as $key => $lang) {
			echo $lang['id'].'-'.$lang['code'].': '.$lang['name'].'<br/>';
		}
		echo 'Default Language: '.	$epdlanguage_id .'-'. $epdlanguage_name.'<br/>';
		echo 'Internal Character Encoding: '.(function_exists('mb_internal_encoding') ? mb_internal_encoding() : 'mb_internal_encoding not available').'<br/>';
		echo 'DB Collation: '.$collation.'<br/>';
		
		echo '<br/><b><u>Database Field Lengths</u></b><br/>';
		echo 'categories_name:'.$categories_name_max_len.'<br/>';
		echo 'manufacturers_name:'.$manufacturers_name_max_len.'<br/>';
		echo 'products_model:'.$products_model_max_len.'<br/>';
		echo 'products_name:'.$products_name_max_len.'<br/>';
	
	/*  // some error checking
		echo '<br/><br/>Problem Data: '. mysql_num_rows($ajeh_result);
		echo '<br/>Memory Usage: '.memory_get_usage(); 
		echo '<br/>Memory Peak: '.memory_get_peak_usage();
		echo '<br/><br/>';
		print_r($langcode);
		echo '<br/><br/>code: '.$langcode[1]['id'];
	*/
		//register_globals_vars_check_4(); // testing
	
	?></div>

	<?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?>

	<div style="text-align:left">
            
    <form ENCTYPE="multipart/form-data" ACTION="easypopulate_4.php" METHOD="POST">
        <div align = "left"><br/>
            <b>Upload EP File</b><br/>
            <?php echo "Http Max Upload File Size: $upload_max_filesize bytes (".round($upload_max_filesize/1024/1024)." Mbytes)<br/>";?>
            <input TYPE="hidden" name="MAX_FILE_SIZE" value="<?php echo $upload_max_filesize; ?>">
            <input name="uploadfile" type="file" size="50">
            <input type="submit" name="buttoninsert" value="Upload File">
            <br/><br/><br/>
        </div>
    </form>

	<?php 
	// echo zen_draw_form('custom', 'easypopulate_4.php', 'id="custom"', 'get'); 
	echo zen_draw_form('custom', 'easypopulate_4.php', 'id="custom"', 'post'); 
	?>
	
    <div align = "left">
		<?php	
		$manufacturers_array = array();
		$manufacturers_array[] = array( "id" => '', 'text' => "Manufacturers" );
		if ($ep_uses_mysqli) {
			$manufacturers_query = mysqli_query($db->link,"SELECT manufacturers_id, manufacturers_name FROM " . TABLE_MANUFACTURERS . " ORDER BY manufacturers_name");
			while ($manufacturers = mysqli_fetch_array($manufacturers_query)) {
				$manufacturers_array[] = array( "id" => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name'] );
			}
			
		} else{
		$manufacturers_query = mysql_query("SELECT manufacturers_id, manufacturers_name FROM " . TABLE_MANUFACTURERS . " ORDER BY manufacturers_name");
		while ($manufacturers = mysql_fetch_array($manufacturers_query)) {
			$manufacturers_array[] = array( "id" => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name'] );
		}
		}
		$status_array = array(array( "id" => '1', 'text' => "Status" ),array( "id" => '1', 'text' => "active" ),array( "id" => '0', 'text' => "inactive" ),array( "id" => '3', 'text' => "all" ));
		$export_type_array  = array(array( "id" => '0', 'text' => "Download Type" ),
			array( "id" => '0', 'text' => "Complete Products" ),
			array( "id" => '1', 'text' => "Model/Price/Qty" ),
			array( "id" => '2', 'text' => "Model/Price/Breaks" ));
		
		echo "<b>Filterable Exports:</b><br/>";
		
		echo zen_draw_pull_down_menu('ep_export_type', $export_type_array) . ' ';
		echo ' ' . zen_draw_pull_down_menu('ep_category_filter', array_merge(array( 0 => array( "id" => '', 'text' => "Categories" )), zen_get_category_tree())) . ' ';
		echo ' ' . zen_draw_pull_down_menu('ep_manufacturer_filter', $manufacturers_array) . ' ';
		echo ' ' . zen_draw_pull_down_menu('ep_status_filter', $status_array) . ' ';
		echo zen_draw_input_field('export', 'Export', ' style="padding: 0px"', false, 'submit');
		?>				
    <br/><br/>
    </div></form>

    <b>Product &amp; Pricing Export/Import Options:</b><br/>
    <!-- Download file links -->
    <a href="easypopulate_4.php?export=full"><b>Complete Products</b> (with Metatags)</a><br/>
    <a href="easypopulate_4.php?export=priceqty"><b>Model/Price/Qty</b> (with Specials)</a><br/>
    <a href="easypopulate_4.php?export=pricebreaks"><b>Model/Price/Breaks</b></a><br/>
    <a href="easypopulate_4.php?export=featured"><b>Featured Products</b></a><br/>
    
    <br/><b>Category Export/Import Options</b><br/>
    <a href="easypopulate_4.php?export=category"><b>Model/Category</b></a><br/>
    <a href="easypopulate_4.php?export=categorymeta"><b>Categories Only</b> (with Metatags)</a><br/>
    
    <br/><b>Attribute Export/Import Options</b><br/>
    <a href="easypopulate_4.php?export=attrib_basic"><b>Basic Products Attributes</b> (basic single-line)</a><br/> 
    <a href="easypopulate_4.php?export=attrib_detailed"><b>Detailed Products Attributes</b> (detailed multi-line)</a><br/>
<?php
	if ($ep_4_SBAEnabled != false) { 
	?>
    <a href="easypopulate_4.php?export=SBA_detailed"><b>Detailed Stock By Attributes Data</b> (detailed multi-line)</a><br/>
    <a href="easypopulate_4.php?export=SBAStock"><b>Stock of Items with Attributes Including SBA</b></a><br/>

    <a href="easypopulate_4.php?export=SBAStockProdFilter"><b>Stock of Items with Attributes Including SBA Sorted Ascending</b></a><br/>

<?php } ?>
    
    <br>DIAGNOSTIC EXPORTS - Note: NOT FOR IMPORTING ATTRIBUTES!<br/>
    <a href="easypopulate_4.php?export=options"><b>Attribute Options Names</b></a><br/>
    <a href="easypopulate_4.php?export=values"><b>Attribute Options Values</b></a><br/>
    <a href="easypopulate_4.php?export=optionvalues"><b>Attribute Options-Names-to-Values</b></a><br/>

	<?php   
    // List uploaded files in multifile mode
	// Table header
	echo '<br/><br/>';
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

    $filenames = 
      array(
        "attrib-basic-ep"=>ATTRIB_BASIC_EP,
        "attrib-detailed-ep"=>ATTRIB_DETAILED_EP_DESC, 
        "category-ep"=>CATEGORY_EP_DESC, 
        "categorymeta-ep"=>CATEGORYMETA_EP_DESC, 
        "featured-ep"=>FEATURED_EP_DESC, 
        "pricebreaks-ep"=>PRICEBREAKS_EP_DESC, 
        "priceqty-ep"=>PRICEQTY_EP_DESC, 
        "sba-detailed-ep"=>SBA_DETAILED_EP_DESC, 
        "sba-stock-ep"=>SBA_STOCK_EP_DESC
      );
      
    $filetypes = array();
    
    for ($i = 0; $i < sizeof($files); $i++) {
			if ( ($files[$i] != ".") && ($files[$i] != "..") && preg_match("/\.(sql|gz|csv|txt|log)$/i",$files[$i]) ) {
        $found = false;

        foreach ($filenames as $key=>$val) {
          if (strtolower(substr($files[$i],0,strlen($key))) == strtolower($key)) { 
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
    
    $filenames = 
      array_merge($filenames, array("zzzzzzzz"=>CATCHALL_EP_DESC));

    echo "\n";
    echo "\n";
    echo "<table id=\"epfiles\"    width=\"80%\" border=1 cellspacing=\"2\" cellpadding=\"2\">\n";
    if (EP4_SHOW_ALL_FILETYPES == 'Hidden') {
      echo "<tr><th>File Name</th><th>Size</th><th>Date &amp; Time</th><th>Type</th><th>Split</th><th>Import</th><th>Delete</th><th>Download</th>\n";
    }
    
    foreach ((EP4_SHOW_ALL_FILETYPES != 'False' ? $filenames : $filetypes ) as $key=>$val) {
      (EP4_SHOW_ALL_FILETYPES != 'False' ? $val = $filetypes[$key] : '');
      if (EP4_SHOW_ALL_FILETYPES == 'Hidden') {
        $val = array();
        for ($i = 0; $i < sizeof($files); $i++){
          $val[$i] = $i;
        }
      }
      
      $file_count = 0;
      //Display the information needed to start use of a filetype.
      $plural_state = "<strong>" . (sizeof($val) > 1 ? EP_DESC_PLURAL : EP_DESC_SING) . "</strong>";
      if (EP4_SHOW_ALL_FILETYPES != 'Hidden') {
        echo "<tr><td colspan=\"8\">" . sprintf($filenames[$key], "<strong>" . $key . "</strong>", $plural_state) . "</td></tr>"; 
        echo "<tr><th>File Name</th><th>Size</th><th>Date &amp; Time</th><th>Type</th><th>Split</th><th>Import</th><th>Delete</th><th>Download</th>\n";
      }
      
      for ($i=0; $i < sizeof($val); $i++) {
			if (EP4_SHOW_ALL_FILETYPES != 'Hidden' || (EP4_SHOW_ALL_FILETYPES == 'Hidden' && ($files[$i] != ".") && ($files[$i] != "..") && preg_match("/\.(sql|gz|csv|txt|log)$/i",$files[$i]) )) {
        $file_count++;
				echo '<tr><td>'.$files[$val[$i]].'</td>
					<td align="right">'.filesize($upload_dir.$files[$val[$i]]).'</td>
					<td align="center">'.date ("Y-m-d H:i:s", filemtime($upload_dir.$files[$val[$i]])).'</td>';
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
					echo "<td align=center><a href=\"".$_SERVER['SCRIPT_NAME']."?split=".$files[$val[$i]]."\">Split</a></td>\n";
					if (strtolower(substr($files[$val[$i]],0,12))== "sba-stock-ep") {
					echo "<td align=center><a href=\"".$_SERVER['SCRIPT_NAME']."?import=".$files[$val[$i]]."\">Import</a><br/><a href=\"".$_SERVER['SCRIPT_NAME']."?import=".$files[$val[$i]]."&amp;sync=1\">Import w/Sync</a></td>\n";
					} else {
					echo "<td align=center><a href=\"".$_SERVER['SCRIPT_NAME']."?import=".$files[$val[$i]]."\">Import</a></td>\n";
					}
					echo "<td align=center><a href=\"".$_SERVER['SCRIPT_NAME']."?delete=".urlencode($files[$val[$i]])."\">Delete file</a></td>";
					echo "<td align=center><a href=\"".DIR_WS_CATALOG.$tempdir.$files[$val[$i]]."\" target=_blank>Download</a></td></tr>\n";
				} else {		  
					echo "<td>&nbsp;</td>\n";
					echo "<td>&nbsp;</td>\n";
					echo "<td align=center><a href=\"".$_SERVER['SCRIPT_NAME']."?delete=".urlencode($files[$val[$i]])."\">Delete file</a></td>";
					echo "<td align=center><a href=\"".DIR_WS_CATALOG.$tempdir.$files[$val[$i]]."\" target=_blank>Download</a></td></tr>\n";
				}
      } 
      } // End loop within a filetype
      if ( $file_count == 0 && EP4_SHOW_ALL_FILETYPES != 'Hidden') {
        echo "<tr><td COLSPAN=8><font color='red'><b>No Supported Data Files </b></font></td></tr>\n";
      } // if (sizeof($files)>0)
      if (EP4_SHOW_ALL_FILETYPES == 'Hidden') {
        break;
      }
    } // End foreach filetype 
    if (EP4_SHOW_ALL_FILETYPES != 'Hidden') {
      echo "</table>\n";
      if (sizeof($filetypes) == 0) {
        echo "<table id=\"epfiles\"    width=\"80%\" border=1 cellspacing=\"2\" cellpadding=\"2\">\n";
        echo "<tr><td COLSPAN=8><font color='red'><b>No Supported Data Files</b></font></td></tr>\n";
        echo "</table>\n";
      }
    }
	} else { // can't open directory
		echo "<tr><td COLSPAN=8><font color='red'><b>Error Opening Upload Directory:</b></font> ".$tempdir."</td></tr>\n";
		$error=true;
	} // opendir()
  if (EP4_SHOW_ALL_FILETYPES == 'Hidden') {
    echo "</table>\n";
    if (sizeof($filetypes) == 0) {
      echo "<table id=\"epfiles\"    width=\"80%\" border=1 cellspacing=\"2\" cellpadding=\"2\">\n";
      echo "<tr><td COLSPAN=8><font color='red'><b>No Supported Data Files</b></font></td></tr>\n";
      echo "</table>\n";
    }
  }
	
	

	
	/*
	echo "<div>";
		$test_string = "Πλαστικά^Εξαρτήματα";
		$test_array1 = explode("^", $test_string);
		echo "<br/>Using explode() with: ".$test_string."<br/>";
		print_r($test_array1);
		
		$test_array2 = mb_split('\x5e', $test_string);
		echo "<br/><br/>Using mb_split() with: ".$test_string."<br/>";
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
