<?php
// NOTE: Need over-ride for installed mods. Basically, a file check to ensure all table columns are installed. If no, set mod to FALSE.

// CSV VARIABLES - need to make this configurable in the ADMIN
// $csv_delimiter = "\t"; // "\t" = tab AND "," = COMMA
$csv_delimiter = ","; // "\t" = tab AND "," = COMMA
$csv_enclosure   = '"'; // chadd - i think you should always us the '"' for best compatibility

$excel_safe_output = true; // this forces enclosure in quotes

// START INITIALIZATION
require_once ('includes/application_top.php');
@set_time_limit(300); // if possible, let's try for 5 minutes before timeouts

/* Configuration Variables from Admin Interface  */
$tempdir            = EASYPOPULATE_4_CONFIG_TEMP_DIR;
$ep_date_format     = EASYPOPULATE_4_CONFIG_FILE_DATE_FORMAT;
$ep_raw_time        = EASYPOPULATE_4_CONFIG_DEFAULT_RAW_TIME;
$ep_debug_logging = ((EASYPOPULATE_4_CONFIG_DEBUG_LOGGING == 'true') ? true : false);
$maxrecs            = EASYPOPULATE_4_CONFIG_SPLIT_MAX;
$price_with_tax   = ((EASYPOPULATE_4_CONFIG_PRICE_INC_TAX == 'true') ? true : false);
$strip_smart_tags = ((EASYPOPULATE_4_CONFIG_SMART_TAGS == 'true') ? true : false);
$max_qty_discounts  = EASYPOPULATE_4_CONFIG_MAX_QTY_DISCOUNTS;

/* Test area start */
// error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);//test purposes only
// register_globals_vars_check();
// $maxrecs = 4; 
// usefull stuff: mysql_affected_rows(), mysql_num_rows().
$ep_debug_logging_all = false; // do not comment out.. make false instead
//$sql_fail_test == true; // used to cause an sql error on new product upload - tests error handling & logs
/* Test area end */

/* Initialise vars */

// Current EP Version - Modded by Chadd
$curver              = '4.0.12 - Beta 11-04-2011';
$display_output      = ''; // results of import displayed after script run
$ep_dltype           = NULL;
$ep_dlmethod         = NULL;
$chmod_check         = true;
$ep_stack_sql_error  = false; // function returns true on any 1 error, and notifies user of an error
$specials_print      = EASYPOPULATE_4_SPECIALS_HEADING;
$has_specials        = false;

// all mods go in this array as 'name' => 'true' if exist. eg $ep_supported_mods['psd'] => true means it exists.
$ep_supported_mods = array();

// config keys array - must contain any expired keys to ensure they are deleted on install or removal
$ep_keys = ep_4_setkeys();

// default smart-tags setting when enabled. This can be added to.
$smart_tags = array("\r\n|\r|\n" => '<br />', );

if (substr($tempdir, -1) != '/') $tempdir .= '/';
if (substr($tempdir, 0, 1) == '/') $tempdir = substr($tempdir, 1);

$ep_debug_log_path = DIR_FS_CATALOG . $tempdir;

if ($ep_debug_logging_all == true) {
	$fp = fopen($ep_debug_log_path . 'ep_debug_log.txt','w'); // new blank log file on each page impression for full testing log (too big otherwise!!)
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
// END: check for existance of various mods

// maximum length for a category in this database
$category_strlen_max = zen_field_length(TABLE_CATEGORIES_DESCRIPTION, 'categories_name');

// model name length error handling - chadd - 12-10-2010 - why do we need to do this?
$model_varchar = zen_field_length(TABLE_PRODUCTS, 'products_model');
if (!isset($model_varchar)) {
	$messageStack->add(EASYPOPULATE_4_MSGSTACK_MODELSIZE_DETECT_FAIL, 'warning');
	$modelsize = 32;
} else {
	$modelsize = $model_varchar;
}
// Pre-flight checks finish here

// check default language_id from configuration table DEFAULT_LANGUAGE
// chadd - really shouldn't do this! $epdlanguage_id is better replaced with $langcode[]
// $epdlanguage_id is the only value used here ( I assume for default language)
// default langauage should not be important since all installed languages are used $langcode[]
// and we should iterate through that array (even if only 1 stored value)
// $epdlanguage_id is used only in categories generation code since the products import code doesn't support multi-language categories
$epdlanguage_query = ep_4_query("SELECT languages_id, name FROM " . TABLE_LANGUAGES . " WHERE code = '" . DEFAULT_LANGUAGE . "'");
if (mysql_num_rows($epdlanguage_query)) {
	$epdlanguage = mysql_fetch_array($epdlanguage_query);
	$epdlanguage_id   = $epdlanguage['languages_id'];
	$epdlanguage_name = $epdlanguage['name'];
} else {
	exit("EP4 FATAL ERROR: No default language set."); // this should never happen
}

$langcode = ep_4_get_languages(); // array of currently used language codes

require_once('easypopulate_4_export.php'); // this file contains all data export code

require_once('easypopulate_4_import.php'); // this file contains all data import code

// if we had an SQL error anywhere, let's tell the user - maybe they can sort out why
if ($ep_stack_sql_error == true) $messageStack->add(EASYPOPULATE_4_MSGSTACK_ERROR_SQL, 'caution');
?>

<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
	<title><?php echo TITLE; ?></title>
	<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
	<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
	<script language="javascript" src="includes/menu.js"></script>
	<script language="javascript" src="includes/general.js"></script>
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
</head>
<body onLoad="init()">
<!-- header -->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof -->

<!-- body -->
<div style="padding:5px">
	
	<?php 	echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?>
	<div class="pageHeading"><?php echo "Easy Populate $curver"; ?></div>
    
	<div style="text-align:right; float:right; width:25%"><a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'epinstaller=remove') ?>">Un-Install EP4</a>
    <? 
		echo '<br>Temporary Directory: <b>'.$tempdir.'</b>'; 
		echo '<br><b>Supported Mods:</b><br>';
		echo 'Product Short Descriptions: '.(($ep_supported_mods['psd']) ? "TRUE":"FALSE").'<br>';
		echo 'Product Unit of Measure: '.(($ep_supported_mods['uom']) ? "TRUE":"FALSE").'<br>';
		echo 'Product UPC Code: '.(($ep_supported_mods['upc']) ? "TRUE":"FALSE").'<br>';
		echo '<b>Installed Languages:</b> <br>';
		foreach ($langcode as $key => $lang) {
			echo $lang['id'].'-'.$lang['code'].': '.$lang['name'].'<br>';
		}
		echo 'Default Language: '.	$epdlanguage_id .'-'. $epdlanguage_name.'<br>'; 
	
	?></div>

	<?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?>

     <div style="text-align:left">
     		<!-- import from browser upload -->
            <form ENCTYPE="multipart/form-data" ACTION="easypopulate_4.php" METHOD="POST">
                <div align = "left">
                    <b>Upload EP File</b><br />
                    <input TYPE="hidden" name="MAX_FILE_SIZE" value="100000000">
                    <input name="usrfl" type="file" size="50">
                    <input type="submit" name="buttoninsert" value="Import Data File">
                    <br />
                </div>
            </form>
            <!-- import from local file -->
            <form ENCTYPE="multipart/form-data" ACTION="easypopulate_4.php" METHOD="POST">
                <div align = "left">
                    <b>Import from Temporary Directory: <? echo $tempdir; ?></b><br />
                    <input TYPE="text" name="localfile" size="50">
                    <input type="submit" name="buttoninsert" value="Import Data File">
                    <br />
                </div>
				<br>
            </form>
			
		  <?php echo zen_draw_form('custom', 'easypopulate_4.php', 'id="custom"', 'get'); ?>
                <div align = "left">
					<?php	
					$manufacturers_array = array();
					$manufacturers_array[] = array( "id" => '', 'text' => "Manufacturers" );
					$manufacturers_query = mysql_query("SELECT manufacturers_id, manufacturers_name FROM " . TABLE_MANUFACTURERS . " ORDER BY manufacturers_name");
					while ($manufacturers = mysql_fetch_array($manufacturers_query)) {
						$manufacturers_array[] = array( "id" => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name'] );
					}
					$status_array = array(array( "id" => '1', 'text' => "status" ),array( "id" => '1', 'text' => "active" ),array( "id" => '0', 'text' => "inactive" ));
					echo "Filter Complete Download by: " . zen_draw_pull_down_menu('ep_category_filter', array_merge(array( 0 => array( "id" => '', 'text' => "Categories" )), zen_get_category_tree()));
					echo ' ' . zen_draw_pull_down_menu('ep_manufacturer_filter', $manufacturers_array) . ' ';
					echo ' ' . zen_draw_pull_down_menu('ep_status_filter', $status_array) . ' ';

					$download_array = array(array( "id" => 'download', 'text' => "download" ),array( "id" => 'stream', 'text' => "stream" ),array( "id" => 'tempfile', 'text' => "tempfile" ));
					echo ' ' . zen_draw_pull_down_menu('download', $download_array) . ' ';

					echo zen_draw_input_field('dltype', 'full', ' style="padding: 0px"', false, 'submit');
					?>				
                    <br />
                </div>
				<br>

            <b>Easy Populate Download Options:</b>
            <br /><br />
            <!-- Download file links -->
            <a href="easypopulate_4.php?download=stream&dltype=full"><b>Complete Products</b> (with Metatags)</a><br />
            <a href="easypopulate_4.php?download=stream&dltype=priceqty"><b>Model/Price/Qty</b> (with Specials)</a><br />
            <a href="easypopulate_4.php?download=stream&dltype=pricebreaks"><b>Model/Price/Breaks</b></a><br />
            <a href="easypopulate_4.php?download=stream&dltype=category"><b>Model/Category</b> </a><br />
            <a href="easypopulate_4.php?download=stream&dltype=categorymeta"><b>Categories Only</b> (with Metatags)</a><br />
			<br>
			<br>Under Construction<br>
            <a href="easypopulate_4.php?download=stream&dltype=attrib"><b>Detailed Products Attributes</b> (detailed multi-line)</a><br />
            <a href="easypopulate_4.php?download=stream&dltype=attrib_basic_detailed"><b>Basic Products Attributes</b> (detailed multi-line)</a><br />
            <a href="easypopulate_4.php?download=stream&dltype=attrib_basic_simple"><b>Basic Products Attributes</b> (single-line)</a><br />
            <a href="easypopulate_4.php?download=stream&dltype=options"><b>Attribute Options Names</b> </a><br />
            <a href="easypopulate_4.php?download=stream&dltype=values"><b>Attribute Options Values</b> </a><br />
            <a href="easypopulate_4.php?download=stream&dltype=optionvalues"><b>Attribute Options-Names-to-Values</b> </a><br />
	
	        <?php /* 
			<br /><br />
            <b>Create Easy Populate Files in Temp Directory (<? echo $tempdir; ?>)</b>
            <br /><br />
            <a href="easypopulate_4.php?download=tempfile&dltype=full">Create <b>Complete</b> </a>
            <span class="fieldRequired"> (Attributes Not Included)</span>
            <br />
            <a href="easypopulate_4.php?download=tempfile&dltype=priceqty">Create <b>Model/Price/Qty</b> </a><br />
            <a href="easypopulate_4.php?download=tempfile&dltype=pricebreaks">Create <b>Model/Price/Breaks</b> </a><br />
            <a href="easypopulate_4.php?download=tempfile&dltype=category">Create <b>Model/Category</b> </a><br />
			<br>
            <a href="easypopulate_4.php?download=tempfile&dltype=attrib">Create <b>Products Attributes</b> </a><br />
            <a href="easypopulate_4.php?download=tempfile&dltype=options">Create <b>Attribute Optoins/Values</b> </a><br />
			*/ ?>

	</div>
	
	<?php
        echo '<br />' . $printsplit; // our files splitting matrix
        echo $display_output; // upload results
        if (strlen($specials_print) > strlen(EASYPOPULATE_4_SPECIALS_HEADING)) {
            echo '<br />' . $specials_print . EASYPOPULATE_4_SPECIALS_FOOTER; // specials summary
        }	
    ?>
    
<!-- body_text_eof -->
</div>
<!-- body_eof -->

<br />
<!-- footer -->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof -->
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>