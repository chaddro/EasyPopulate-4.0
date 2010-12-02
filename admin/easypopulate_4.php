<?php
// 1.3.00 - 02-19-09 - Perform much code cleaning.
// 1.3.01 - 03-30-09 - BUG FIX - when uploading FULL file. If v_status column is left out, would re-activate deactivated items.
// 1.3.02 - 03-30-09 - BUG FIX - fixed blank manufactuers import where empty v_manufacturers_name when blank was not reset correctly
// 1.3.03 - 09-08-09 - Begins Major Code Revisions
/* 1.3.03 Notes:
- Simplified ?langer=install and ?langer=remove  "langer" to "epinstaller", removed ?langer=installnew
- removed paypal request for donations!!
- no longer creates first export file
- changed logic of function ep_chmod_check() ... it now ONLY checks to see if the directory exist and is writeable and fails if either is not true.
  Also removed automatic attemps to chmod() throught this script - this is not secure.
09-15-09 - Decided to also split this mod off properly by adding TAB (like CSV and OSC)
09-29-09 - Created multiple exports for product attributes, option names, and option values
09-29-09 - updates Price/Qty/Breaks export code to use while statement. Now allow for unlimited in number of price breaks! 
10-01-09 - phazie's array definitions for filelayouts are more efficient and also more code readible and mod-able. array_merger() if very inefficient
			this required some tweaking in other places
		 - found some places in the download code that I replace with exclusion code to speed up downloads. No sense executing code that wasn't needed.
		 - export time in now in 24-hour:minute:second which makes better sense to me
10-02-09 - added metatags for products as in phazie's csv version - so far testing is good
10-05-09 - it is VERY important to have the correct delimiter set or the import of information will fail
*/
// 1.3.04 - 10-13-09 - Fixed function call errors and added filtering check for UPC and UOM mods when downloading files
// 1.3.05 - 10-14-09 - Auto switching for file extention fixed dependant on delimitor <tab> or <comma>
// 					 - Somehow when adding a new item with empty manufacturer, it was being assigned the previously imported manufactures id. Spelling error in code!

// 4.0.000 - 12-21-09 - Begin Move to 4.0.000 beta
// 4.0.001 - 12-22-09 - Ha! Manufacturers weren't being created because I left off the "s" in manufacturers!
// 4.0.02  - 06-03-10 - changed all price_as to price_uom for unit of measure. This is a logical correction. Databased updated
// ALTER TABLE products ADD products_upc VARCHAR(32) AFTER products_model;
// ALTER TABLE products ADD products_price_uom VARCHAR(2) AFTER products_price;
// 4.0.03 - 11-19-2010 Fixed bug where v_date_avail (products_date_available) wasn't being set to NULL correctly.
// 4.0.04 - 11-22-2010 worked on quantity discount import code. now removes old discounts when discount type or number of discounts changes
// 4.0.05 - 11-23-2010 more work on quantity breaks. Eliminated the v_discount_id column since all discounts are entered at once fresh i'm just using loop index
//          11-23-2010 added products_status to the Model/Price/Qty and Model/Price/Breaks
// 4.0.06 - 12-02-2010 added uninstall button on form page, removed unnecessary tables from form page
//			12-02-2010 removed 'v_discount_id_' from export file layout - no longer needed
//			12-02-2010 add EASYPOPULATE_4_CONFIG_MAX_QTY_DISCOUNTS variable to configuration page


// CSV VARIABLES - need to make this configurable in the ADMIN
$csv_deliminator = "\t"; // "\t" = tab AND "," = COMMA
$csv_deliminator = ","; // "\t" = tab AND "," = COMMA
$csv_enclosure   = '"'; // if want none, change to space (chadd - i think you should always us the '"').

$separator = "\t"; // tab delimited file
$separator = ",";  // CSV - comma delimited file

$excel_safe_output = true; // this  forces enclosure in quotes

// START INITIALIZATION
require_once ('includes/application_top.php');
@set_time_limit(300); // if possible, let's try for 5 minutes before timeouts

/* Configuration Variable from Admin Interface  */

$tempdir          = EASYPOPULATE_4_CONFIG_TEMP_DIR;
$ep_date_format   = EASYPOPULATE_4_CONFIG_FILE_DATE_FORMAT;
$ep_raw_time      = EASYPOPULATE_4_CONFIG_DEFAULT_RAW_TIME;
$ep_debug_logging = ((EASYPOPULATE_4_CONFIG_DEBUG_LOGGING == 'true') ? true : false);
$maxrecs          = EASYPOPULATE_4_CONFIG_SPLIT_MAX;
$price_with_tax   = ((EASYPOPULATE_4_CONFIG_PRICE_INC_TAX == 'true') ? true : false);
$max_categories   = EASYPOPULATE_4_CONFIG_MAX_CATEGORY_LEVELS;
$strip_smart_tags = ((EASYPOPULATE_4_CONFIG_SMART_TAGS == 'true') ? true : false);
$max_qty_discounts = EASYPOPULATE_4_CONFIG_MAX_QTY_DISCOUNTS;

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
$curver                   = '4.0.06 - Beta 12-2-2010';
$display_output           = '';
$ep_dltype                = NULL;
$ep_dlmethod              = NULL;
$chmod_check              = true;
$ep_stack_sql_error       = false; // function returns true on any 1 error, and notifies user of an error
$specials_print           = EASYPOPULATE_4_SPECIALS_HEADING;
$replace_quotes           = false; //  this is probably redundant now...retain here for now..
$products_with_attributes = false; //  this will be redundant after html renovation
// maybe below can go in array eg $ep_processed['attributes'] = true, etc.. cold skip all post-upload tasks on check if isset var $ep_processed.
$has_attributes == false;
$has_specials == false;


// all mods go in this array as 'name' => 'true' if exist. eg $ep_supported_mods['psd'] => true means it exists.
$ep_supported_mods = array();

// config keys array - must contain any expired keys to ensure they are deleted on install or removal
$ep_keys = array(
	'EASYPOPULATE_4_CONFIG_TEMP_DIR',
	'EASYPOPULATE_4_CONFIG_FILE_DATE_FORMAT',
	'EASYPOPULATE_4_CONFIG_DEFAULT_RAW_TIME',
	'EASYPOPULATE_4_CONFIG_MAX_CATEGORY_LEVELS',
	'EASYPOPULATE_4_CONFIG_PRICE_INC_TAX',
	'EASYPOPULATE_4_CONFIG_ZERO_QTY_INACTIVE',
	'EASYPOPULATE_4_CONFIG_SMART_TAGS',
	'EASYPOPULATE_4_CONFIG_ADV_SMART_TAGS',
	'EASYPOPULATE_4_CONFIG_DEBUG_LOGGING',
	);

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
$ep_supported_mods['psd'] = false; //ep_4_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_short_desc');
$ep_supported_mods['uom'] = true; //ep_4_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_price_uom'); // uom = unit of measure, added by Chadd
$ep_supported_mods['upc'] = true; //ep_4_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_upc'); // upc = UPC Code, added by Chadd

//echo $ep_supported_mods['psd'].'<br>';
//echo $ep_supported_mods['uom'].'<br>';
//echo $ep_supported_mods['upc'].'<br>';

// END: check for existance of various mods

// maximum length for a category in this database
$category_strlen_max = zen_field_length(TABLE_CATEGORIES_DESCRIPTION, 'categories_name');

// model name length error handling
$model_varchar = zen_field_length(TABLE_PRODUCTS, 'products_model');
if (!isset($model_varchar)) {
	$messageStack->add(EASYPOPULATE_4_MSGSTACK_MODELSIZE_DETECT_FAIL, 'warning');
	$modelsize = 32;
} else {
	$modelsize = $model_varchar;
}
// Pre-flight checks finish here

// START: Create File Layout for Download Types
// check default language_id from configuration table DEFAULT_LANGUAGE
$epdlanguage_query = ep_4_query("SELECT languages_id, name FROM " . TABLE_LANGUAGES . " WHERE code = '" . DEFAULT_LANGUAGE . "'");
if (mysql_num_rows($epdlanguage_query)) {
	$epdlanguage = mysql_fetch_array($epdlanguage_query);
	$epdlanguage_id   = $epdlanguage['languages_id'];
	$epdlanguage_name = $epdlanguage['name'];
} else {
	echo 'No default language set!... This should not happen';
}

$langcode = array();
$languages_query = ep_4_query("SELECT languages_id, code FROM " . TABLE_LANGUAGES . " ORDER BY sort_order");
// start array at one, the rest of the code expects it that way
$ll = 1;
while ($ep_languages = mysql_fetch_array($languages_query)) {
	//will be used to return language_id en language code to report in product_name_code instead of product_name_id
	$ep_languages_array[$ll++] = array(
		'id' => $ep_languages['languages_id'],
		'code' => $ep_languages['code']
		);
}
$langcode = $ep_languages_array;
$ep_dltype = (isset($_GET['dltype'])) ? $_GET['dltype'] : $ep_dltype;

/*if ( $ep_dltype != '' ){
	// if dltype is set, then create the filelayout.  Otherwise it gets read from the uploaded file
	ep_4_create_filelayout($ep_dltype); // get the right filelayout for this download
}*/


if (zen_not_null($ep_dltype)) {
	// if dltype is set, then create the filelayout.  Otherwise filelayout is read from the uploaded file.
	// depending on the type of the download the user wanted, create a file layout for it.

	$filelayout = array();
	$fileheaders = array();
	
	// build filters
	$sql_filter = '';
	if (!empty($_GET['ep_category_filter'])) {
	  $sub_categories = array();
	  $categories_query_addition = 'ptoc.categories_id = ' . (int)$_GET['ep_category_filter'] . '';
	  zen_get_sub_categories($sub_categories, $_GET['ep_category_filter']);
	  foreach ($sub_categories AS $key => $category ) {
		$categories_query_addition .= ' OR ptoc.categories_id = ' . (int)$category . '';
	  }
	  $sql_filter .= ' AND (' . $categories_query_addition . ')';
	}
	if ($_GET['ep_manufacturer_filter']!='') {
	  $sql_filter .= ' and p.manufacturers_id = ' . (int)$_GET['ep_manufacturer_filter'];
	}
	if ($_GET['ep_status_filter']!='') {
	  $sql_filter .= ' AND p.products_status = ' . (int)$_GET['ep_status_filter'];
	}
	if ($_GET['dltype']!='') {
	  $ep_dltype = $_GET['dltype'];
	}
	
	switch($ep_dltype) {

	case 'full': // FULL products download
		// The file layout is dynamically made depending on the number of languages
		$fileMeta = array(); // metatag support by phazie

		$filelayout[] = 'v_products_model';
		$filelayout[] = 'v_products_image';
			
	 	$fileMeta[] = 'v_metatags_products_name_status';
		$fileMeta[] = 'v_metatags_title_status';
		$fileMeta[] = 'v_metatags_model_status';
		$fileMeta[] = 'v_metatags_price_status';
		$fileMeta[] = 'v_metatags_title_tagline_status';
			
		foreach ($langcode as $key => $lang) { // create variables for each language id
			$l_id = $lang['id'];
			$filelayout[] = 'v_products_name_' . $l_id;
			$filelayout[] = 'v_products_description_' . $l_id;

			if ($ep_supported_mods['psd'] == true) { // products short description mod
				$filelayout[] = 'v_products_short_desc_' . $l_id;
			}
			
			$filelayout[] = 'v_products_url_' . $l_id;

			$fileMeta[] = 'v_metatags_title_' . $l_id;
			$fileMeta[] = 'v_metatags_keywords_' . $l_id;
			$fileMeta[] = 'v_metatags_description_' . $l_id;
		} 
	
		$filelayout[] = 'v_specials_price';
		$filelayout[] = 'v_specials_date_avail';
		$filelayout[] = 'v_specials_expires_date';
		$filelayout[] = 'v_products_price';
			
		if ($ep_supported_mods['uom'] == true) { // price UOM mod
			$filelayout[] = 'v_products_price_uom'; // to soon be changed to v_products_price_uom
		} 

		if ($ep_supported_mods['upc'] == true) { // UPC Mod
			$filelayout[] = 'v_products_upc'; 
		} 

		$filelayout[] = 'v_products_weight';
		$filelayout[] = 'v_product_is_call';
		$filelayout[] = 'v_products_sort_order';
		$filelayout[] = 'v_products_quantity_order_min';
		$filelayout[] = 'v_products_quantity_order_units';
		$filelayout[] = 'v_date_avail'; // should be changed to v_products_date_available for clarity
		$filelayout[] = 'v_date_added'; // should be changed to v_products_date_added for clarity
		$filelayout[] = 'v_products_quantity';
		$filelayout[] = 'v_manufacturers_name';

		// build the categories name section of the array based on the number of categores the user wants to have
		for($i=1;$i<$max_categories+1;$i++) {
			$filelayout[] = 'v_categories_name_' . $i;
		}

		$filelayout[] = 'v_tax_class_title';
		$filelayout[] = 'v_status';
		
		$filelayout = array_merge($filelayout, $fileMeta);

		$filelayout_sql = 'SELECT
			p.products_id					as v_products_id,
			p.products_model				as v_products_model,
			p.products_image				as v_products_image,
			p.products_price				as v_products_price,';
			
		if ($ep_supported_mods['uom'] == true) { // price UOM mod
			$filelayout_sql .=  'p.products_price_uom as v_products_price_uom,'; // to soon be changed to v_products_price_uom
		} 
		if ($ep_supported_mods['upc'] == true) { // UPC Code mod
			$filelayout_sql .=  'p.products_upc as v_products_upc,'; 
		} 
			
		$filelayout_sql .= 'p.products_weight as v_products_weight,
			p.product_is_call				as v_product_is_call,
			p.products_sort_order			as v_products_sort_order, 
			p.products_quantity_order_min	as v_products_quantity_order_min,
			p.products_quantity_order_units	as v_products_quantity_order_units,
			p.products_date_available		as v_date_avail,
			p.products_date_added			as v_date_added,
			p.products_tax_class_id			as v_tax_class_id,
			p.products_quantity				as v_products_quantity,
			p.manufacturers_id				as v_manufacturers_id,
			subc.categories_id				as v_categories_id,
			p.products_status				as v_status,
			p.metatags_title_status         as v_metatags_title_status,
			p.metatags_products_name_status as v_metatags_products_name_status,
			p.metatags_model_status         as v_metatags_model_status,
			p.metatags_price_status         as v_metatags_price_status,
			p.metatags_title_tagline_status as v_metatags_title_tagline_status 
			FROM '
			.TABLE_PRODUCTS.' as p,'
			.TABLE_CATEGORIES.' as subc,'
			.TABLE_PRODUCTS_TO_CATEGORIES.' as ptoc
			WHERE
			p.products_id = ptoc.products_id AND
			ptoc.categories_id = subc.categories_id'.$sql_filter;
		break;
		
	case 'priceqty':
		$filelayout[] = 'v_products_model';
		$filelayout[] = 'v_status'; // 11-23-2010 added product status to price quantity option
		$filelayout[] = 'v_specials_price';
		$filelayout[] = 'v_specials_date_avail';
		$filelayout[] = 'v_specials_expires_date';
		$filelayout[] = 'v_products_price';
		if ($ep_supported_mods['uom'] == true) { // price UOM mod
			$filelayout[] = 'v_products_price_uom'; // to soon be changed to v_products_price_uom
		} 
		$filelayout[] = 'v_products_quantity';

		$filelayout_sql = 'SELECT
			p.products_id     as v_products_id,
			p.products_status as v_status,
			p.products_model  as v_products_model,
			p.products_price  as v_products_price,';

		if ($ep_supported_mods['uom'] == true) { // price UOM mod
			$filelayout_sql .=  'p.products_price_uom as v_products_price_uom,'; // to soon be changed to v_products_price_uom
		} 
		
		$filelayout_sql .= 'p.products_tax_class_id as v_tax_class_id,
			p.products_quantity as v_products_quantity
			FROM '
			.TABLE_PRODUCTS.' as p';
		break;

	
	// Chadd: quantity price breaks file layout
	// 09-30-09 Need a configuration variable to set the MAX discounts level
	//          then I will be able to generate $filelayout() dynamically
	case 'pricebreaks':
		$filelayout[] =	'v_products_model';
		$filelayout[] = 'v_status'; // 11-23-2010 added product status to price quantity option
		$filelayout[] =	'v_products_price';
		
		if ($ep_supported_mods['uom'] == true) { // price UOM mod
			$filelayout[] = 'v_products_price_uom'; // to soon be changed to v_products_price_uom
		} 

		$filelayout[] =	'v_products_discount_type';
		$filelayout[] =	'v_products_discount_type_from';
		// discount quantities base on $max_qty_discounts	
		for ($i=1;$i<$max_qty_discounts+1;$i++) {
			// $filelayout[] = 'v_discount_id_' . $i; // chadd - no longer needed
			$filelayout[] = 'v_discount_qty_' . $i;
			$filelayout[] = 'v_discount_price_' . $i;
		}
			
		$filelayout_sql = 'SELECT
			p.products_id     as v_products_id,
			p.products_status as v_status,
			p.products_model  as v_products_model,
			p.products_price  as v_products_price,';
			
		if ($ep_supported_mods['uom'] == true) { // price UOM mod
			$filelayout_sql .=  'p.products_price_uom as v_products_price_uom,'; // to soon be changed to v_products_price_uom
		} 
			
		$filelayout_sql .= 'p.products_discount_type as v_products_discount_type,
			p.products_discount_type_from as v_products_discount_type_from
			FROM '
			.TABLE_PRODUCTS.' as p';
	break;	

	case 'category':
		// The file layout is dynamically made depending on the number of languages
		$filelayout[] = 'v_products_model';

		// build categories name section of the array based on the number of categores the user wants to have
		for ($i=1;$i<$max_categories+1;$i++) {
			$filelayout[] = 'v_categories_name_' . $i;
		}

		$filelayout_sql = 'SELECT
			p.products_id      as v_products_id,
			p.products_model   as v_products_model,
			subc.categories_id as v_categories_id
			FROM '
			.TABLE_PRODUCTS.'   as p,'
			.TABLE_CATEGORIES.' as subc,'
			.TABLE_PRODUCTS_TO_CATEGORIES.' as ptoc      
			WHERE
			p.products_id = ptoc.products_id AND
			ptoc.categories_id = subc.categories_id';
		break;
		
	case 'attrib':
		$filelayout[] =	'v_products_attributes_id';
		$filelayout[] =	'v_products_id';
		$filelayout[] =	'v_products_model'; // product model from table PRODUCTS
		$filelayout[] =	'v_options_id';
		$filelayout[] =	'v_products_options_name'; // options name from table PRODUCTS_OPTIONS
		$filelayout[] =	'v_products_options_type'; // 0-drop down, 1=text , 2=radio , 3=checkbox, 4=file, 5=read only 
		$filelayout[] =	'v_options_values_id';
		$filelayout[] =	'v_products_options_values_name'; // options values name from table PRODUCTS_OPTIONS_VALUES
		$filelayout[] =	'v_options_values_price';
		$filelayout[] =	'v_price_prefix';
		$filelayout[] =	'v_products_options_sort_order';
		$filelayout[] =	'v_product_attribute_is_free';
		$filelayout[] =	'v_products_attributes_weight';
		$filelayout[] =	'v_products_attributes_weight_prefix';
		$filelayout[] =	'v_attributes_display_only';
		$filelayout[] =	'v_attributes_default';
		$filelayout[] =	'v_attributes_discounted';
		$filelayout[] =	'v_attributes_image';
		$filelayout[] =	'v_attributes_price_base_included';
		$filelayout[] =	'v_attributes_price_onetime';
		$filelayout[] =	'v_attributes_price_factor';
		$filelayout[] =	'v_attributes_price_factor_offset';
		$filelayout[] =	'v_attributes_price_factor_onetime';
		$filelayout[] =	'v_attributes_price_factor_onetime_offset';
		$filelayout[] =	'v_attributes_qty_prices';
		$filelayout[] =	'v_attributes_qty_prices_onetime';
		$filelayout[] =	'v_attributes_price_words';
		$filelayout[] =	'v_attributes_price_words_free';
		$filelayout[] =	'v_attributes_price_letters';
		$filelayout[] =	'v_attributes_price_letters_free';
		$filelayout[] =	'v_attributes_required';

		// a = table PRODUCTS_ATTRIBUTES
		// p = table PRODUCTS
		// o = table PRODUCTS_OPTIONS
		// v = table PRODUCTS_OPTIONS_VALUES
		$filelayout_sql = 'SELECT
			a.products_attributes_id            as v_products_attributes_id,
			a.products_id                       as v_products_id,
			p.products_model				    as v_products_model,
			a.options_id                        as v_options_id,
			o.products_options_id               as v_products_options_id,
			o.products_options_name             as v_products_options_name,
			o.products_options_type             as v_products_options_type,
			a.options_values_id                 as v_options_values_id,
			v.products_options_values_id        as v_products_options_values_id,
			v.products_options_values_name      as v_products_options_values_name,
			a.options_values_price              as v_options_values_price,
			a.price_prefix                      as v_price_prefix,
			a.products_options_sort_order       as v_products_options_sort_order,
			a.product_attribute_is_free         as v_product_attribute_is_free,
			a.products_attributes_weight        as v_products_attributes_weight,
			a.products_attributes_weight_prefix as v_products_attributes_weight_prefix,
			a.attributes_display_only           as v_attributes_display_only,
			a.attributes_default                as v_attributes_default,
			a.attributes_discounted             as v_attributes_discounted,
			a.attributes_image                  as v_attributes_image,
			a.attributes_price_base_included    as v_attributes_price_base_included,
			a.attributes_price_onetime          as v_attributes_price_onetime,
			a.attributes_price_factor           as v_attributes_price_factor,
			a.attributes_price_factor_offset    as v_attributes_price_factor_offset,
			a.attributes_price_factor_onetime   as v_attributes_price_factor_onetime,
			a.attributes_price_factor_onetime_offset      as v_attributes_price_factor_onetime_offset,
			a.attributes_qty_prices             as v_attributes_qty_prices,
			a.attributes_qty_prices_onetime     as v_attributes_qty_prices_onetime,
			a.attributes_price_words            as v_attributes_price_words,
			a.attributes_price_words_free       as v_attributes_price_words_free,
			a.attributes_price_letters          as v_attributes_price_letters,
			a.attributes_price_letters_free     as v_attributes_price_letters_free,
			a.attributes_required               as v_attributes_required
			FROM '
			.TABLE_PRODUCTS_ATTRIBUTES.     ' as a,'
			.TABLE_PRODUCTS.                ' as p,'
			.TABLE_PRODUCTS_OPTIONS.        ' as o,'
			.TABLE_PRODUCTS_OPTIONS_VALUES. ' as v
			WHERE
			a.products_id       = p.products_id AND
			a.options_id        = o.products_options_id AND
			a.options_values_id = v.products_options_values_id' 			
			;
		break;

	case 'attrib_basic':
		$filelayout[] =	'v_products_attributes_id';
		$filelayout[] =	'v_products_id';
		$filelayout[] =	'v_products_model'; // product model from table PRODUCTS
		$filelayout[] =	'v_options_id';
		$filelayout[] =	'v_products_options_name'; // options name from table PRODUCTS_OPTIONS
		$filelayout[] =	'v_products_options_type'; // 0-drop down, 1=text , 2=radio , 3=checkbox, 4=file, 5=read only 
		$filelayout[] =	'v_options_values_id';
		$filelayout[] =	'v_products_options_values_name'; // options values name from table PRODUCTS_OPTIONS_VALUES
	
		// a = table PRODUCTS_ATTRIBUTES
		// p = table PRODUCTS
		// o = table PRODUCTS_OPTIONS
		// v = table PRODUCTS_OPTIONS_VALUES
		$filelayout_sql = 'SELECT
			a.products_attributes_id            as v_products_attributes_id,
			a.products_id                       as v_products_id,
			p.products_model				    as v_products_model,
			a.options_id                        as v_options_id,
			o.products_options_id               as v_products_options_id,
			o.products_options_name             as v_products_options_name,
			o.products_options_type             as v_products_options_type,
			a.options_values_id                 as v_options_values_id,
			v.products_options_values_id        as v_products_options_values_id,
			v.products_options_values_name      as v_products_options_values_name
			FROM '
			.TABLE_PRODUCTS_ATTRIBUTES.     ' as a,'
			.TABLE_PRODUCTS.                ' as p,'
			.TABLE_PRODUCTS_OPTIONS.        ' as o,'
			.TABLE_PRODUCTS_OPTIONS_VALUES. ' as v
			WHERE
			a.products_id       = p.products_id AND
			a.options_id        = o.products_options_id AND
			a.options_values_id = v.products_options_values_id' 			
			;
		break;
		
	case 'options':
		$filelayout[] =	'v_products_options_id';
		$filelayout[] =	'v_language_id';
		$filelayout[] =	'v_products_options_name';
		$filelayout[] =	'v_products_options_sort_order';
		$filelayout[] =	'v_products_options_type';
		$filelayout[] =	'v_products_options_length';
		$filelayout[] =	'v_products_options_comment';
		$filelayout[] =	'v_products_options_size';
		$filelayout[] =	'v_products_options_images_per_row';
		$filelayout[] =	'v_products_options_images_style';
		$filelayout[] =	'v_products_options_rows';

		// o = table PRODUCTS_OPTIONS
		$filelayout_sql = 'SELECT
			o.products_options_id             AS v_products_options_id,
			o.language_id                     AS v_language_id,
			o.products_options_name           AS v_products_options_name,
			o.products_options_sort_order     AS v_products_options_sort_order,
			o.products_options_type           AS v_products_options_type,
			o.products_options_length         AS v_products_options_length,
			o.products_options_comment        AS v_products_options_comment,
			o.products_options_size           AS v_products_options_size,
			o.products_options_images_per_row AS v_products_options_images_per_row,
			o.products_options_images_style   AS v_products_options_images_style,
			o.products_options_rows           AS v_products_options_rows '
			.' FROM '
			.TABLE_PRODUCTS_OPTIONS. ' AS o';
		break;
	
	case 'values':
		$filelayout[] =	'v_products_options_values_id';
		$filelayout[] =	'v_language_id';
		$filelayout[] =	'v_products_options_values_name';
		$filelayout[] =	'v_products_options_values_sort_order';

		// v = table PRODUCTS_OPTIONS_VALUES
		$filelayout_sql = 'SELECT
			v.products_options_values_id         AS v_products_options_values_id,
			v.language_id                        AS v_language_id,
			v.products_options_values_name       AS v_products_options_values_name,
			v.products_options_values_sort_order AS v_products_options_values_sort_order '
			.' FROM '
			.TABLE_PRODUCTS_OPTIONS_VALUES. ' AS v'; 
		break;

	case 'optionvalues':
		$filelayout[] =	'v_products_options_values_to_products_options_id';
		$filelayout[] =	'v_products_options_id';
		$filelayout[] =	'v_products_options_name';
		$filelayout[] =	'v_products_options_values_id';
		$filelayout[] =	'v_products_options_values_name';

		// o = table PRODUCTS_OPTIONS
		// v = table PRODUCTS_OPTIONS_VALUES
		// otv = table PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS
		$filelayout_sql = 'SELECT
			otv.products_options_values_to_products_options_id AS v_products_options_values_to_products_options_id,   	    	 
			otv.products_options_id           AS v_products_options_id,
			o.products_options_name           AS v_products_options_name,
			otv.products_options_values_id    AS v_products_options_values_id,
			v.products_options_values_name    AS v_products_options_values_name '
			.' FROM '
			.TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS. ' AS otv, '
			.TABLE_PRODUCTS_OPTIONS.        ' AS o, '
			.TABLE_PRODUCTS_OPTIONS_VALUES. ' AS v 
			WHERE 
			otv.products_options_id        = o.products_options_id AND
			otv.products_options_values_id = v.products_options_values_id';
		break;

	}
	$filelayout = array_flip($filelayout);
	$fileheaders = array_flip($fileheaders);
	$filelayout_count = count($filelayout);
} 
// END: File Download Layouts

$ep_dlmethod = isset($_GET['download']) ? $_GET['download'] : $ep_dlmethod;
if ($ep_dlmethod == 'stream' or  $ep_dlmethod == 'tempfile') { // DOWNLOAD FILE
	$filestring	= ""; // file name
	$result		= ep_4_query($filelayout_sql);
	$row		= mysql_fetch_array($result, MYSQL_ASSOC);

	// 09-30-09 - chadd - no custom header mapping code elsewhere, just use:
	$filelayout_header = $filelayout; 
	
	// prepare the table heading with layout values
	foreach( $filelayout_header as $key => $value ) {
		$filestring .= $key . $separator;
	}
	// Remove trailing tab
	$filestring = substr($filestring, 0, strlen($filestring)-1);


	// set End-of-Row End-of-Record Seperator
	$endofrow = $separator . 'EOREOR' . "\n"; // 'EOREOR' is now redundant -chadd
	$filestring .= $endofrow;
	$num_of_langs = count($langcode); // why repeat this every loop?
	while ($row) {
	
		// PRODUCTS IMAGE
		if (isset($filelayout['v_products_image'])) { 
			$products_image = (($row['v_products_image'] == PRODUCTS_IMAGE_NO_IMAGE) ? '' : $row['v_products_image']);
		}
		
		// LANGUAGES AND DESCRIPTIONS
		if ( $ep_dltype == 'full') {
			// names and descriptions require that we loop thru all languages that are turned on in the store
			foreach ($langcode as $key => $lang) {
				$lid = $lang['id'];
				// metaData start
				$sqlMeta = 'SELECT * FROM '.TABLE_META_TAGS_PRODUCTS_DESCRIPTION.' WHERE products_id = '.$row['v_products_id'].
					' AND language_id = '.$lid.' LIMIT 1 ';
				$resultMeta = ep_4_query($sqlMeta);
				$rowMeta    = mysql_fetch_array($resultMeta);
				$row['v_metatags_title_' . $lid]       = $rowMeta['metatags_title'];
				$row['v_metatags_keywords_' . $lid]    = $rowMeta['metatags_keywords'];
				$row['v_metatags_description_' . $lid] = $rowMeta['metatags_description'];
				// metaData end
				
				// for each language, get the description and set the vals
				$sql2    = 'SELECT * FROM '.TABLE_PRODUCTS_DESCRIPTION.' WHERE products_id = '.$row['v_products_id'].' AND language_id = '.$lid.' LIMIT 1 ';
				$result2 = ep_4_query($sql2);
				$row2    = mysql_fetch_array($result2);
				
				$row['v_products_name_'.$lid]        = $row2['products_name'];
				$row['v_products_description_'.$lid] = $row2['products_description'];
				
				if ($ep_supported_mods['psd'] == true) { // products short descriptions mod
					$row['v_products_short_desc_'.$lid] = $row2['products_short_desc'];
				}
				$row['v_products_url_'.$lid] = $row2['products_url'];
			} // foreach
		} // if($ep_dltype == 'full')

		// BEGIN: Specials
		if (isset($filelayout['v_specials_price'])) {
			$specials_query = ep_4_query('SELECT specials_new_products_price, specials_date_available, expires_date FROM '.
				TABLE_SPECIALS.' WHERE products_id = '.$row['v_products_id']);
			if (mysql_num_rows($specials_query)) { 	// special
				$ep_specials = mysql_fetch_array($specials_query);
				$row['v_specials_price']        = $ep_specials['specials_new_products_price'];
				$row['v_specials_date_avail']   = $ep_specials['specials_date_available'];
				$row['v_specials_expires_date'] = $ep_specials['expires_date'];
			} else { // no special
				$row['v_specials_price']        = '';
				$row['v_specials_date_avail']   = '';
				$row['v_specials_expires_date'] = '';
			}
		} // END: Specials
		
		// CATEGORIES
		// start with v_categories_id, Get the category description, set the appropriate variable name
		// if parent_id is not null, then follow it up.
		if (($ep_dltype == 'full') OR $ep_dltype == 'category') {
			$thecategory_id = $row['v_categories_id'];
			$fullcategory = ''; // this will have the entire category stack for froogle - is this now dead? - chadd
			for ($categorylevel=1; $categorylevel<$max_categories+1; $categorylevel++) {
				if (!empty($thecategory_id)) {
					$sql2 = 'SELECT categories_name FROM '.TABLE_CATEGORIES_DESCRIPTION.
						' WHERE categories_id = '.$thecategory_id.' AND language_id = '.$epdlanguage_id;
					$result2 = ep_4_query($sql2);
					$row2 = mysql_fetch_array($result2);
					// only set it if we found something
					$temprow['v_categories_name_' . $categorylevel] = $row2['categories_name'];
					// now get the parent ID if there was one
					$sql3 = 'SELECT parent_id FROM '.TABLE_CATEGORIES.' WHERE categories_id = '.$thecategory_id;
					$result3 = ep_4_query($sql3);
					$row3 = mysql_fetch_array($result3);
					$theparent_id = $row3['parent_id'];
					if ($theparent_id != '') { // Found parent ID, set thecategoryid to get the next level
						$thecategory_id = $theparent_id;
					} else { // Category Root Found
						$thecategory_id = false;
					}
					$fullcategory = $row2['categories_name'] . " > " . $fullcategory;
				} else {
					$temprow['v_categories_name_' . $categorylevel] = '';
				} // if
			} // for
			// trim off the last ">" from the category stack
			$row['v_category_fullpath'] = substr($fullcategory,0,strlen($fullcategory)-3);
			// temprow has the old style low to high level categories.
			$newlevel = 1;
			// let's turn them into high to low level categories
			for ($categorylevel=6; $categorylevel>0; $categorylevel--) {
				if ($temprow['v_categories_name_' . $categorylevel] != '') {
					$row['v_categories_name_' . $newlevel++] = $temprow['v_categories_name_' . $categorylevel];
				}
			} // for
		} // if ($ep_dltype == 'full')

		// MANUFACTURERS DOWNLOAD
		// if the filelayout says we need a manfacturers name, get it for download file
		if (isset($filelayout['v_manufacturers_name'])) {
			if ($row['v_manufacturers_id'] != '0') { // '' or 0 or '0' - not sure, chadd
				$sql2 = 'SELECT manufacturers_name FROM '.TABLE_MANUFACTURERS.' WHERE manufacturers_id = '.$row['v_manufacturers_id'];
				$result2 = ep_4_query($sql2);
				$row2    = mysql_fetch_array($result2);
				$row['v_manufacturers_name'] = $row2['manufacturers_name']; 
			} else {  // this is to fix the error on manufacturers name
				// $row['v_manufacturers_id'] = '0';  blank name mean 0 id - right? chadd 4-7-09
				$row['v_manufacturers_name'] = ''; // no manufacturer name
			}
		}
		
		// Price/Qty/Discounts - chadd - updated 12-02-2010 to remove discount_id from export
		 $discount_index = 1;
		 while (isset($filelayout['v_discount_qty_'.$discount_index])) {
			if ($row['v_products_discount_type'] != '0') { // if v_products_discount_type == 0 then there are no quantity breaks
				$sql2 = 'SELECT discount_id, discount_qty, discount_price FROM '. 
					TABLE_PRODUCTS_DISCOUNT_QUANTITY.' WHERE products_id = '. 
					$row['v_products_id'].' AND discount_id='.$discount_index;
				$result2 = ep_4_query($sql2);
				$row2    = mysql_fetch_array($result2);
				// $row['v_discount_id_'.$discount_index]    = $row2['discount_id'];  // chadd - no longer needed
				$row['v_discount_price_'.$discount_index] = $row2['discount_price'];
				$row['v_discount_qty_'.$discount_index]   = $row2['discount_qty'];
			}
			$discount_index++;		
		 }
		
		// We check the value of tax class and title instead of the id
		// Then we add the tax to price if $price_with_tax is set to 1
		$row_tax_multiplier       = ep_4_get_tax_class_rate($row['v_tax_class_id']);
		$row['v_tax_class_title'] = zen_get_tax_class_title($row['v_tax_class_id']);
		$row['v_products_price']  = round($row['v_products_price'] + ($price_with_tax * $row['v_products_price'] * $row_tax_multiplier / 100),2);

		// Now set the status to a word the user specd in the config vars
		// Clean the texts that could confuse EasyPopulate
		// chadd - i think something in NOT correct here
		$therow = '';
		foreach($filelayout as $key => $value) {
			$thetext = $row[$key];
			// remove carriage returns and tabs in the descriptions
			// chadd - why? what if I want those in the file????
			$thetext = str_replace("\r",' ',$thetext);
			$thetext = str_replace("\n",' ',$thetext);
			$thetext = str_replace("\t",' ',$thetext);
			
			// chadd - I do not know if this is actually needed like this, instead of safe mode, force quotes???
			if ($excel_safe_output == true) {
			  // use quoted values and escape the embedded quotes for excel safe output.
			  $therow .= '"'.str_replace('"','""',$thetext).'"' . $separator;
			} else {
			  // and put the text into the output separated by $separator defined above
			  $therow .= $thetext . $separator;
			}
		}

		// Remove trailing tab, then append the end-of-row indicator
		$therow = substr($therow,0,strlen($therow)-1) . $endofrow;

		$filestring .= $therow;
		// grab the next row from the db
		$row = mysql_fetch_array($result);
	} // while ($row) {
	
	// Create export file name
	// $EXPORT_TIME=time();
	$EXPORT_TIME = strftime('%Y%b%d-%H%M%S');  // chadd - changed for hour.minute.second
	switch ($ep_dltype) { // chadd - changed to use $EXPORT_FILE
		case 'full':
		$EXPORT_FILE = "Full-EP" . $EXPORT_TIME;
		break;
		case 'priceqty':
		$EXPORT_FILE = "PriceQty-EP" . $EXPORT_TIME;
		break;
		case 'pricebreaks':
		$EXPORT_FILE = "PriceBreaks-EP" . $EXPORT_TIME;
		break;
		case 'category':
		$EXPORT_FILE = "Category-EP" . $EXPORT_TIME;
		break;
		case 'attrib':
		$EXPORT_FILE = "Attrib-Full-EP" . $EXPORT_TIME;
		break;
		case 'attrib_basic':
		$EXPORT_FILE = "Attrib-Basic-EP" . $EXPORT_TIME;
		break;
		case 'options':
		$EXPORT_FILE = "Options-EP" . $EXPORT_TIME;
		break;
		case 'values':
		$EXPORT_FILE = "Values-EP" . $EXPORT_TIME;
		break;
		case 'optionvalues':
		$EXPORT_FILE = "OptVals-EP" . $EXPORT_TIME;
		break;
	}

	
	// either stream it to them or put it in the temp directory
	if ($ep_dlmethod == 'stream') { // Stream File
		header("Content-type: application/vnd.ms-excel");
//		header("Content-disposition: attachment; filename=$EXPORT_FILE" . (($excel_safe_output == true)?".csv":".txt")); // this should check the delimiter instead!
		header("Content-disposition: attachment; filename=$EXPORT_FILE" . (($csv_deliminator == ",")?".csv":".txt")); // this should check the delimiter instead!
		// Changed if using SSL, helps prevent program delay/timeout (add to backup.php also)
		if ($request_type== 'NONSSL'){
			header("Pragma: no-cache");
		} else {
			header("Pragma: ");
		}
		header("Expires: 0");
		echo $filestring;
		die();
	} else { // PUT FILE IN TEMP DIR
		$tmpfpath = DIR_FS_CATALOG . '' . $tempdir . "$EXPORT_FILE" . (($csv_deliminator == ",")?".csv":".txt");
		$fp = fopen( $tmpfpath, "w+");
		fwrite($fp, $filestring);
		fclose($fp);
		$messageStack->add(sprintf(EASYPOPULATE_4_MSGSTACK_FILE_EXPORT_SUCCESS, $EXPORT_FILE, $tempdir), 'success');
	}
} // END: Download Section


// BEGIN: UPLOAD FILES STARTS HERE
if ( isset($_POST['localfile']) OR isset($_FILES['usrfl']) ) {

	$display_output .= EASYPOPULATE_4_DISPLAY_HEADING;
	
	//  UPLOAD FILE
	if (isset($_FILES['usrfl']) ) {
		$file = ep_4_get_uploaded_file('usrfl'); 		
		if (is_uploaded_file($file['tmp_name'])) {
			ep_4_copy_uploaded_file($file, DIR_FS_CATALOG . $tempdir);
		}
		$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_UPLOADED_FILE_SPEC, $file['tmp_name'], $file['name'], $file['size']);
	}
	
	if ( isset($_POST['localfile']) ) { 
		$file = ep_4_get_uploaded_file('localfile');
		$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_LOCAL_FILE_SPEC, $file['name']);
	}
	
	// When updating products info, these values are used for exisint data
	// This allows a reduced number of columns to be used on updates 
	$default_these = array(
		'v_products_image',
		'v_categories_id',
		'v_products_price');

	if ($ep_supported_mods['uom'] == true) { // price UOM mod - chadd
		$mod_array = array('v_products_price_uom'); // chadd
		$default_these = array_merge($default_these, $mod_array);
	} 
	if ($ep_supported_mods['upc'] == true) { // UPC Code mod - chadd
		$mod_array = array('v_products_upc'); 
		$default_these = array_merge($default_these, $mod_array);
	} 
	
	// default values - 
	$default_these = array_merge( $default_these, array('v_products_quantity',
		'v_products_weight',
		'v_products_discount_type',
		'v_products_discount_type_from',
		'v_product_is_call',
		'v_products_sort_order',
		'v_products_quantity_order_min',
		'v_products_quantity_order_units',
		'v_date_added',
		'v_date_avail', // chadd - this should default to null not "zero" or system date
		'v_instock',
		'v_tax_class_title',
		'v_manufacturers_name',
		'v_manufacturers_id',
		'v_products_status' // added by chadd so that de-activated products are not reactivated when the column is missing
	));

	$file_location = DIR_FS_CATALOG . $tempdir . $file['name'];
	if (!file_exists($file_location)) {
		$display_output .="<b>ERROR: file doesn't exist</b>";
	} else if ( !($handle = fopen($file_location, "r"))) {
		$display_output .="<b>ERROR: Can't open file</b>";
	} else if($filelayout = array_flip(fgetcsv($handle, 0, $csv_deliminator, $csv_enclosure))) { // header row


	// Main IMPORT loop
	while ($items = fgetcsv($handle, 0, $csv_deliminator, $csv_enclosure)) { // read 1 line of data
		// now do a query to get the record's current contents
		$sql = 'SELECT
			p.products_id					as v_products_id,
			p.products_model				as v_products_model,
			p.products_image				as v_products_image,
			p.products_price				as v_products_price,';
			
		if ($ep_supported_mods['uom'] == true) { // price UOM mod - chadd
			$sql .=  'p.products_price_uom as v_products_price_uom,'; // chadd
		} 
		if ($ep_supported_mods['upc'] == true) { // UPC Code mod- chadd
			$sql .=  'p.products_upc as v_products_upc,'; 
		} 

		$sql .= 'p.products_weight			as v_products_weight,
			p.products_discount_type		as v_products_discount_type,
			p.products_discount_type_from   as v_products_discount_type_from,
			p.product_is_call				as v_product_is_call,
			p.products_sort_order			as v_products_sort_order,
			p.products_quantity_order_min	as v_products_quantity_order_min,
			p.products_quantity_order_units	as v_products_quantity_order_units,
			p.products_date_added			as v_date_added,
			p.products_date_available		as v_date_avail,
			p.products_tax_class_id			as v_tax_class_id,
			p.products_quantity				as v_products_quantity,
			p.products_status				as v_products_status,
			p.manufacturers_id				as v_manufacturers_id,
			subc.categories_id				as v_categories_id
			FROM '.
			TABLE_PRODUCTS.' as p,'.
			TABLE_CATEGORIES.' as subc,'.
			TABLE_PRODUCTS_TO_CATEGORIES." as ptoc
			WHERE
			p.products_id      = ptoc.products_id AND
			p.products_model   = '". zen_db_input($items[$filelayout['v_products_model']]) . "' AND
			ptoc.categories_id = subc.categories_id";
		$result			= ep_4_query($sql);
		$product_is_new = true;
		
		// langer - inputs: $items array (file data by column #); $filelayout array (headings by column #); $row (current db TABLE_PRODUCTS data by heading name)
		while ( $row = mysql_fetch_array($result) ) {
			$product_is_new = false;
			// Get current products descriptions and categories for this model from database
			// $row at present consists of current product data for above fields only (in $sql)

			// since we have a row, the item already exists.
			// let's check and delete it if requested     
			if ($items[$filelayout['v_status']] == 9) {
				$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_DELETED, $items[$filelayout['v_products_model']]);
				ep_4_remove_product($items[$filelayout['v_products_model']]);
				continue 2;
			}
			
			// Let's get all the data we need and fill in all the fields that need to be defaulted to the current values
			// for each language, get the description and set the vals
			foreach ($langcode as $key => $lang) {
				$sql2 = 'SELECT * FROM '.TABLE_PRODUCTS_DESCRIPTION.' WHERE products_id = '. 
					$row['v_products_id'] . " AND language_id = '" . $lang['id'] . "'";
				$result2 = ep_4_query($sql2);
				$row2 = mysql_fetch_array($result2);
				// Need to report from ......_name_1 not ..._name_0
				$row['v_products_name_' . $lang['id']] = $row2['products_name']; // name assigned
				$row['v_products_description_' . $lang['id']] = $row2['products_description']; // description assigned
				// if short descriptions exist
				if ($ep_supported_mods['psd'] == true) {
					$row['v_products_short_desc_' . $lang['id']] = $row2['products_short_desc'];
				}
				$row['v_products_url_' . $lang['id']] = $row2['products_url'];// url assigned
			}
			// table descriptions values by each language assigned into array $row
			
			// langer - outputs: $items array (file data by column #); $filelayout array (headings by column #); $row (current db TABLE_PRODUCTS & TABLE_PRODUCTS_DESCRIPTION data by heading name)
			
			// Categories start
			
			// start with v_categories_id
			// Get the category description
			// set the appropriate variable name
			// if parent_id is not null, then follow it up.
			$thecategory_id = $row['v_categories_id']; // master category id
	
			for ($categorylevel=1; $categorylevel<$max_categories+1; $categorylevel++) {
				if (!empty($thecategory_id)) {
					$sql2 = 'SELECT categories_name FROM '.
						TABLE_CATEGORIES_DESCRIPTION.' WHERE categories_id = ' . 
						$thecategory_id . ' AND language_id = ' . $epdlanguage_id;
					$result2 = ep_4_query($sql2);
					$row2 = mysql_fetch_array($result2);
					// only set it if we found something
					$temprow['v_categories_name_' . $categorylevel] = $row2['categories_name'];
					
					// now get the parent ID if there was one
					$sql3 = 'SELECT parent_id FROM '.TABLE_CATEGORIES.' WHERE categories_id = ' . $thecategory_id;
					$result3 = ep_4_query($sql3);
					$row3 =  mysql_fetch_array($result3);
					$theparent_id = $row3['parent_id'];
					if ($theparent_id != '') { // parent ID, lets set thecategoryid to get the next level
						$thecategory_id = $theparent_id;
					} else { // we have found the top level category for this item,
						$thecategory_id = false;
					}
				} else {
					$temprow['v_categories_name_' . $categorylevel] = '';
				}
			}
			// temprow has the old style low to high level categories.
			$newlevel = 1;
			// let's turn them into high to low level categories
			for ($categorylevel=$max_categories+1; $categorylevel>0; $categorylevel--) {
				if ($temprow['v_categories_name_' . $categorylevel] != ''){
					$row['v_categories_name_' . $newlevel++] = $temprow['v_categories_name_' . $categorylevel];
				}
			}
			/** Categories path for existing product retrieved from db in $row array */
			
			/** Retrieve current manufacturer name from db for this product if exist */
			if ($row['v_manufacturers_id'] != '0') { // chadd made '0' - if 0, no manufacturer set
				$sql2 = 'SELECT manufacturers_name FROM '.TABLE_MANUFACTURERS.' WHERE manufacturers_id = ' . $row['v_manufacturers_id'];
				$result2 = ep_4_query($sql2);
				$row2 = mysql_fetch_array($result2);
				$row['v_manufacturers_name'] = $row2['manufacturers_name']; 
				}
			else {
				$row['v_manufacturers_name'] = '';  // added by chadd 4-7-09 - default name to blank
				}
			
			
			// Get tax info for this product
			// We check the value of tax class and title instead of the id
			// Then we add the tax to price if $price_with_tax is set to true
			$row_tax_multiplier = ep_4_get_tax_class_rate($row['v_tax_class_id']);
			$row['v_tax_class_title'] = zen_get_tax_class_title($row['v_tax_class_id']);
			if ($price_with_tax) {
				$row['v_products_price'] = round($row['v_products_price'] + ($row['v_products_price'] * $row_tax_multiplier / 100),2);
			}
			
			// langer - the following defaults all of our current data from our db ($row array) to our update variables (called internal variables here)
			// for each $default_these - this limits it just to TABLE_PRODUCTS fields defined in this array!
			// eg $v_products_price = $row['v_products_price'];
			// perhaps we should build onto this array with each $row assignment routing above, so as to default all data to existing database
			
			// now create the internal variables that will be used
			// the $$thisvar is on purpose: it creates a variable named what ever was in $thisvar and sets the value
			// sets them to $row value, which is the existing value for these fields in the database
			foreach ($default_these as $thisvar) {
				$$thisvar = $row[$thisvar];
			}
		}

		// langer - We have now set our PRODUCT_TABLE vars for existing products, and got our default descriptions & categories in $row still
		// new products start here!

		// langer - let's have some data error checking
		// inputs: $items; $filelayout; $product_is_new (no reliance on $row)
		if ($items[$filelayout['v_status']] == 9 && zen_not_null($items[$filelayout['v_products_model']])) {
			// new delete got this far, so cant exist in db. Cant delete what we don't have...
			$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_DELETE_NOT_FOUND, $items[$filelayout['v_products_model']]);
			continue;
		}
		if ($product_is_new == true) {
			if (!zen_not_null(trim($items[$filelayout['v_categories_name_1']])) && zen_not_null($items[$filelayout['v_products_model']])) {
			// let's skip this new product without a master category..
			$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_NOT_FOUND, $items[$filelayout['v_products_model']], ' new');
			continue;
			} else {
				// minimum test for new product - model(already tested below), name, price, category, taxclass(?), status (defaults to active)
				// to add
			}
		} else { // not new product
			if (!zen_not_null(trim($items[$filelayout['v_categories_name_1']])) && isset($filelayout['v_categories_name_1'])) {
				// let's skip this existing product without a master category but has the column heading
				// or should we just update it to result of $row (it's current category..)??
				$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_NOT_FOUND, $items[$filelayout['v_products_model']], '');
				foreach ($items as $col => $langer) {
					if ($col == $filelayout['v_products_model']) continue;
					$display_output .= print_el_4($langer);
				}
				continue;
			}
		} // End data checking
		
		/**
		* langer - assign to our vars any new data from $items (from our file)
		* output is: $v_products_model = "modelofthing", $v_products_description_1 = "descofthing", etc for each file heading
		* any existing (default) data assigned above is overwritten here with the new vals from file
		*/
		
		// this is an important loop.  What it does is go thru all the fields in the incoming file and set the internal vars.
		// Internal vars not set here are either set in the loop above for existing records, or not set at all (null values)
		// the array values are handled separately, although they will set variables in this loop, we won't use them.
		// $key is column heading name, $value is column number for the heading..
		// langer - this would appear to over-write our defaults with null values in $items if they exist
		// in other words, if we have a file heading, then you want all listed models updated in this field
		// add option here - update all null values, or ignore null values???
		foreach ($filelayout as $key => $value) {
			$$key = $items[$value];
		}
	
		// so how to handle these?  we shouldn't build the array unless it's been giving to us.
		// The assumption is that if you give us names and descriptions, then you give us name and description for all applicable languages
		foreach ($langcode as $lang) {
			$l_id = $lang['id'];
			// metaTags
			if ( isset($filelayout['v_metatags_title_' . $l_id ]) ) { 
				$v_metatags_title[$l_id] = $items[$filelayout['v_metatags_title_' . $l_id]];
				$v_metatags_keywords[$l_id] = $items[$filelayout['v_metatags_keywords_' . $l_id]];
				$v_metatags_description[$l_id] = $items[$filelayout['v_metatags_description_' . $l_id]];
			}
			// metaTags

			if (isset($filelayout['v_products_name_' . $l_id ])) { // do for each language in our upload file if exist
				// we set dynamically the language vars
				$v_products_name[$l_id]        = smart_tags_4($items[$filelayout['v_products_name_' . $l_id]],$smart_tags,$cr_replace,false);
				$v_products_description[$l_id] = smart_tags_4($items[$filelayout['v_products_description_' . $l_id ]],$smart_tags,$cr_replace,$strip_smart_tags);
				// if short descriptions exist
				if ($ep_supported_mods['psd'] == true) {
					$v_products_short_desc[$l_id] = smart_tags_4($items[$filelayout['v_products_short_desc_' . $l_id ]],$smart_tags,$cr_replace,$strip_smart_tags);
				}
				$v_products_url[$l_id] = smart_tags_4($items[$filelayout['v_products_url_' . $l_id ]],$smart_tags,$cr_replace,false);
			}
		}
		// we get the tax_clas_id from the tax_title - from zencart??
		// on screen will still be displayed the tax_class_title instead of the id....
		if (isset($v_tax_class_title)) {
			$v_tax_class_id = ep_4_get_tax_title_class_id($v_tax_class_title);
		}
		// we check the tax rate of this tax_class_id
		$row_tax_multiplier = ep_4_get_tax_class_rate($v_tax_class_id);
	
		// And we recalculate price without the included tax...
		// Since it seems display is made before, the displayed price will still include tax
		// This is same problem for the tax_clas_id that display tax_class_title
		if ($price_with_tax == true) {
			$v_products_price = round( $v_products_price / (1 + ( $row_tax_multiplier * $price_with_tax/100) ), 4);
		}
	
		// if they give us one category, they give us all 6 categories
		// langer - this does not appear to support more than 7 categories??
		unset ($v_categories_name); // default to not set.
		
		if (isset($filelayout['v_categories_name_1'])) { // does category 1 column exist in our file..
			$category_strlen_long = FALSE;// checks cat length does not exceed db, else exclude product from upload
			$newlevel = 1;
			for($categorylevel=6; $categorylevel>0; $categorylevel--) {
				if ($items[$filelayout['v_categories_name_' . $categorylevel]] != '') {
					if (strlen($items[$filelayout['v_categories_name_' . $categorylevel]]) > $category_strlen_max) $category_strlen_long = TRUE;
					$v_categories_name[$newlevel++] = $items[$filelayout['v_categories_name_' . $categorylevel]]; // adding the category name values to $v_categories_name array
				} // null categories are not assigned
			}
			while( $newlevel < $max_categories+1) {
				$v_categories_name[$newlevel++] = ''; // default the remaining items to nothing
			}
			if ($category_strlen_long == TRUE) {
				$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_NAME_LONG, $v_products_model, $category_strlen_max);
				continue;
			}
		}
		
		// if $v_products_quantity is null, set it to: 0
		if (trim($v_products_quantity) == '') {
			$v_products_quantity = 0;
		}

		// date variables - chadd ... these should really be products_date_available and products_date_added for clarity
		// date_avail is only set to show when out of stock items will be available, else it is NULL
		// 11-19-2010 fixed this bug where NULL wasn't being correctly set
		$v_date_avail = ($v_date_avail) ? "'".date("Y-m-d H:i:s",strtotime($v_date_avail))."'" : "NULL";
		
		// if products has been added before, do not change, else use current time stamp
		$v_date_added = ($v_date_added) ? "'".date("Y-m-d H:i:s",strtotime($v_date_added))."'" : "CURRENT_TIMESTAMP";

		// default the stock if they spec'd it or if it's blank
		// $v_db_status = '1'; // default to active
		$v_db_status = $v_products_status; // changed by chadd to database default value 3-30-09
		if ($v_status == '0') { // request deactivate this item
			$v_db_status = '0';
		}
		if ($v_status == '1') { // request activate this item
			$v_db_status = '1';
		}

		if (EASYPOPULATE_4_CONFIG_ZERO_QTY_INACTIVE == 'true' && $v_products_quantity == 0) {
			// if they said that zero qty products should be deactivated, let's deactivate if the qty is zero
			$v_db_status = '0';
		}
		
		// why is this even here? chadd 4-7-09
	//	if ($v_manufacturers_name == '') { // chadd 10/14/09 
	//		$v_manufacturers_id = 0; // chadd - added 10/14/09. If id=0, name is also blank.
	//	}
		
		if (trim($v_products_image) == '') {
			$v_products_image = PRODUCTS_IMAGE_NO_IMAGE;
		}
		if (strlen($v_products_model) > $modelsize) {
			$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_MODEL_NAME_LONG, $v_products_model);
			continue;
		}
		
//======= MANUFACTURER BEGIN
		// OK, we need to convert the manufacturer's name into id's for the database
		if ( isset($v_manufacturers_name) && ($v_manufacturers_name != '') ) {
			$sql = "SELECT man.manufacturers_id as manID FROM ".TABLE_MANUFACTURERS." as man WHERE man.manufacturers_name = '" .zen_db_input($v_manufacturers_name) . "' LIMIT 1";
			$result = ep_4_query($sql);
			if ( $row = mysql_fetch_array($result) ) {
				$v_manufacturers_id = $row['manID'];
				} else {
					// It is set to autoincrement, do not need to fetch max id
					$sql = "INSERT INTO " . TABLE_MANUFACTURERS . "( manufacturers_name, date_added, last_modified )
						VALUES ( '" . zen_db_input($v_manufacturers_name) . "',	CURRENT_TIMESTAMP, CURRENT_TIMESTAMP )";
					$result = ep_4_query($sql);
					$v_manufacturers_id = mysql_insert_id();
				}
			} else {
				// $v_manufacturers_name == '' so set $_manufacturers_id to '0'
				$v_manufacturers_id = 0; // 0 or '0' - chadd 
			}
//======= MANUFACTURER END
	
		// if the categories names are set then try to update them
		if (isset($v_categories_name_1)) {
			// start from the highest possible category and work our way down from the parent
			$v_categories_id = 0;
			$theparent_id = 0;
			for ( $categorylevel=$max_categories+1; $categorylevel>0; $categorylevel-- ) {
				$thiscategoryname = $v_categories_name[$categorylevel];
				if ( $thiscategoryname != '') {
					// we found a category name in this field
					// now the subcategory
					$sql = "SELECT cat.categories_id
						FROM ".TABLE_CATEGORIES." as cat, 
							 ".TABLE_CATEGORIES_DESCRIPTION." as des
						WHERE
							cat.categories_id = des.categories_id AND
							des.language_id = $epdlanguage_id AND
							cat.parent_id = " . $theparent_id . " AND
							des.categories_name = '" . zen_db_input($thiscategoryname) . "' LIMIT 1";
					$result = ep_4_query($sql);
					$row = mysql_fetch_array($result);
					if ( $row != '' ) { // null result here where len of $v_categories_name[] exceeds maximum in database
						foreach( $row as $item ) {
							$thiscategoryid = $item;
						}
					} else { // to add, we need to put stuff in categories and categories_description
						$sql = "SELECT MAX(categories_id) max FROM ".TABLE_CATEGORIES;
						$result = ep_4_query($sql);
						$row = mysql_fetch_array($result);
						$max_category_id = $row['max']+1;
						if (!is_numeric($max_category_id) ) {
							$max_category_id=1;
						}
						$sql = "INSERT INTO ".TABLE_CATEGORIES."(
							categories_id,
							categories_image,
							parent_id,
							sort_order,
							date_added,
							last_modified
							) VALUES (
							$max_category_id,
							'',
							$theparent_id,
							0,
							CURRENT_TIMESTAMP,
							CURRENT_TIMESTAMP
							)";
						$result = ep_4_query($sql);
						$sql = "INSERT INTO ".TABLE_CATEGORIES_DESCRIPTION."(
							categories_id,
							language_id,
							categories_name
							) VALUES (
							$max_category_id,
							'$epdlanguage_id',
							'".zen_db_input($thiscategoryname)."'
							)";
						$result = ep_4_query($sql);
						$thiscategoryid = $max_category_id;
					}
					// the current catid is the next level's parent
					$theparent_id = $thiscategoryid;
					$v_categories_id = $thiscategoryid; // keep setting this, we need the lowest level category ID later
				}
			}
		}
		
		// insert new, or update existing, product
		if ($v_products_model != "") { // products_model exists!
			// First we check to see if this is a product in the current db.
			$result = ep_4_query("SELECT products_id FROM ".TABLE_PRODUCTS." WHERE (products_model = '" . zen_db_input($v_products_model) . "')");
			if (mysql_num_rows($result) == 0)  { // new item, insert into products
				$v_date_added	= ($v_date_added == 'NULL') ? CURRENT_TIMESTAMP : $v_date_added;
				$sql			= "SHOW TABLE STATUS LIKE '".TABLE_PRODUCTS."'";
				$result			= ep_4_query($sql);
				$row			= mysql_fetch_array($result);
				$max_product_id = $row['Auto_increment'];
				if (!is_numeric($max_product_id) ) {
					$max_product_id = 1;
				}
				$v_products_id = $max_product_id;
				
				$query = "INSERT INTO " . TABLE_PRODUCTS . " SET
					products_model					= '".zen_db_input($v_products_model)."',
					products_price					= '".zen_db_input($v_products_price)."',";
				if ($ep_supported_mods['uom'] == true) { // price UOM mod
					$query .= "products_price_uom = '".zen_db_input($v_products_price_uom)."',"; 
				} 
				if ($ep_supported_mods['upc'] == true) { // UPC Code mod
					$query .= "products_upc = '".zen_db_input($v_products_upc)."',";
				} 
				$query .= "products_image			= '".zen_db_input($v_products_image)."',
					products_weight					= '".zen_db_input($v_products_weight)."',
					products_discount_type          = '".zen_db_input($v_products_discount_type)."',
					products_discount_type_from     = '".zen_db_input($v_products_discount_type_from)."',
					product_is_call                 = '".zen_db_input($v_product_is_call)."',
					products_sort_order             = '".zen_db_input($v_products_sort_order)."',
					products_quantity_order_min     = '".zen_db_input($v_products_quantity_order_min)."',
					products_quantity_order_units   = '".zen_db_input($v_products_quantity_order_units)."',
					products_tax_class_id			= '".zen_db_input($v_tax_class_id)."',
					products_date_available			= $v_date_avail, 
					products_date_added				= $v_date_added,
					products_last_modified			= CURRENT_TIMESTAMP,
					products_quantity				= '".zen_db_input($v_products_quantity)."',
					master_categories_id			= '".zen_db_input($v_categories_id)."',
					manufacturers_id				= '".$v_manufacturers_id."',
					products_status					= '".zen_db_input($v_db_status)."',
					metatags_title_status			= '".zen_db_input($v_metatags_title_status)."',
					metatags_products_name_status	= '".zen_db_input($v_metatags_products_name_status)."',
					metatags_model_status			= '".zen_db_input($v_metatags_model_status)."',
					metatags_price_status			= '".zen_db_input($v_metatags_price_status)."',
					metatags_title_tagline_status	= '".zen_db_input($v_metatags_title_tagline_status)."'";	

				$result = ep_4_query($query);
				if ($result == true) {
					$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_NEW_PRODUCT, $v_products_model);
				} else {
					$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_NEW_PRODUCT_FAIL, $v_products_model);
					continue; // langer - any new categories however have been created by now..Adding into product table needs to be 1st action?
				}
				foreach ($items as $col => $langer) {
					if ($col == $filelayout['v_products_model']) continue;
					$display_output .= print_el_4($langer);
				}
			} else { // existing product, get the id from the query and update the product data
				// if date added is null, let's keep the existing date in db..
				$v_date_added = ($v_date_added == 'NULL') ? $row['v_date_added'] : $v_date_added; // if NULL, use date in db
				$v_date_added = zen_not_null($v_date_added) ? $v_date_added : CURRENT_TIMESTAMP; // if updating, but date added is null, we use today's date
				$row = mysql_fetch_array($result);
				$v_products_id = $row['products_id'];
				$row = mysql_fetch_array($result); 
				// CHADD - why is master_categories_id not being set on update???
				$query = "UPDATE " . TABLE_PRODUCTS . " SET
					products_price					=	'" . zen_db_input($v_products_price)."',";
					
				if ($ep_supported_mods['uom'] == true) { // price UOM mod
					$query .= "products_price_uom = '".zen_db_input($v_products_price_uom)."',"; 
				} 
				if ($ep_supported_mods['upc'] == true) { // UPC Code mod
					$query .= "products_upc = '".zen_db_input($v_products_upc)."',";
				} 
					
				$query .= "products_image			= '".zen_db_input($v_products_image)."',
					products_weight					= '".zen_db_input($v_products_weight)."',
					products_discount_type			= '".zen_db_input($v_products_discount_type)."',
					products_discount_type_from		= '".zen_db_input($v_products_discount_type_from)."',
					product_is_call					= '".zen_db_input($v_product_is_call)."',
					products_sort_order				= '".zen_db_input($v_products_sort_order)."',
					products_quantity_order_min		= '".zen_db_input($v_products_quantity_order_min)."',
					products_quantity_order_units	= '".zen_db_input($v_products_quantity_order_units)."',				
					products_tax_class_id			= '".zen_db_input($v_tax_class_id)."',
					products_date_available			= $v_date_avail,
					products_date_added				= $v_date_added,
					products_last_modified			= CURRENT_TIMESTAMP,
					products_quantity				= '".zen_db_input($v_products_quantity)."',
					manufacturers_id				= '".$v_manufacturers_id."',
					products_status					= '".zen_db_input($v_db_status)."',
					metatags_title_status			= '".zen_db_input($v_metatags_title_status)."',
					metatags_products_name_status	= '".zen_db_input($v_metatags_products_name_status)."',
					metatags_model_status			= '".zen_db_input($v_metatags_model_status)."',
					metatags_price_status			= '".zen_db_input($v_metatags_price_status)."',
					metatags_title_tagline_status	= '".zen_db_input($v_metatags_title_tagline_status)."'".
					" WHERE ( products_id = '". $v_products_id . "' )";
				
				$result = ep_4_query($query);
				if ($result == true) {
					$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_UPDATE_PRODUCT, $v_products_model);
					foreach ($items as $col => $langer) {
						if ($col == $filelayout['v_products_model']) continue;
						$display_output .= print_el_4($langer);
					}
				} else {
					$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_UPDATE_PRODUCT_FAIL, $v_products_model);
				}
			}
			
			// Product Meta Start
			if (isset($v_metatags_title)){
				foreach ( $v_metatags_title as $key => $metaData ) {
					$sql = "SELECT `products_id` FROM ".TABLE_META_TAGS_PRODUCTS_DESCRIPTION." WHERE (`products_id` = '$v_products_id' AND `language_id` = '$key') LIMIT 1 ";
					$result = ep_4_query($sql);
					if ($row = mysql_fetch_array($result)) {
						// UPDATE
						$sql = "UPDATE ".TABLE_META_TAGS_PRODUCTS_DESCRIPTION." SET 
							`metatags_title`		= '".zen_db_input($v_metatags_title[$key])."',
							`metatags_keywords`		= '".zen_db_input($v_metatags_keywords[$key])."',
							`metatags_description`	= '".zen_db_input($v_metatags_description[$key])."'
							WHERE (`products_id` = '$v_products_id' AND `language_id` = '$key') ";
					} else {
						// NEW
						$sql = "INSERT INTO ".TABLE_META_TAGS_PRODUCTS_DESCRIPTION." SET 
							`metatags_title`		= '".zen_db_input($v_metatags_title[$key])."',
							`metatags_keywords`		= '".zen_db_input($v_metatags_keywords[$key])."',
							`metatags_description`	= '".zen_db_input($v_metatags_description[$key])."',
							`products_id` 			= '$v_products_id',
							`language_id` 			= '$key' ";
					}
					$result = ep_4_query($sql);
				}
			}
		
			// chadd: use this command to remove all old discount entries.
			// $db->Execute("delete from " . TABLE_PRODUCTS_DISCOUNT_QUANTITY . " where products_id = '" . (int)$v_products_id . "'");
			
			// this code does not check for existing quantity breaks, it simply updates or adds them. No algorithm for removal.
			// update quantity price breaks - chadd
			// 9-29-09 - changed to while loop to allow for more than 3 discounts
			// 9-29-09 - code has test well for first run through.
			// initialize variables
			
			if ($v_products_discount_type != '0') { // if v_products_discount_type == 0 then there are no quantity breaks
				if ($v_products_model != "") { // we check to see if this is a product in the current db, must have product model number
					$result = ep_4_query("SELECT products_id FROM ".TABLE_PRODUCTS." WHERE (products_model = '" . zen_db_input($v_products_model) . "')");
					if (mysql_num_rows($result) != 0)  { // found entry
						$row3 =  mysql_fetch_array($result);
						$v_products_id = $row3['products_id'];

						// remove all old associated quantity discounts
						// this simplifies the below code to JUST insert all the new values
						$db->Execute("delete from " . TABLE_PRODUCTS_DISCOUNT_QUANTITY . " where products_id = '" . (int)$v_products_id . "'");

						// initialize quantity discount variables
						$xxx = 1;
						// $v_discount_id_var    = 'v_discount_id_'.$xxx ; // this column is now redundant
						$v_discount_qty_var   = 'v_discount_qty_'.$xxx;
						$v_discount_price_var = 'v_discount_price_'.$xxx;
						while (isset($$v_discount_qty_var)) { 
							// INSERT price break
							if ($$v_discount_price_var != "") { // check for empty price
								$sql = 'INSERT INTO ' . TABLE_PRODUCTS_DISCOUNT_QUANTITY . "(
									products_id,
									discount_id,
									discount_qty,
									discount_price
								) VALUES (
									'$v_products_id',
									'".zen_db_input($xxx)."',
									'".zen_db_input($$v_discount_qty_var)."',
									'".zen_db_input($$v_discount_price_var)."')";
								$result = ep_4_query($sql);
							} // end: check for empty price
			
							$xxx++; 
							// $v_discount_id_var    = 'v_discount_id_'.$xxx ;  // chadd - now redundant, just input $xxx index value
							$v_discount_qty_var   = 'v_discount_qty_'.$xxx;
							$v_discount_price_var = 'v_discount_price_'.$xxx;
						} // while (isset($$v_discount_id_var)
			
					} // end: if (row count <> 0) found entry
				} // if ($v_products_model)

			} else { // products_discount_type == 0, so remove any old quantity_discounts
				if ($v_products_model != "") { // we check to see if this is a product in the current db, must have product model number
					$result = ep_4_query("SELECT products_id FROM ".TABLE_PRODUCTS." WHERE (products_model = '" . zen_db_input($v_products_model) . "')");

					if (mysql_num_rows($result) != 0)  { // found entry
						$row3 =  mysql_fetch_array($result);
						$v_products_id = $row3['products_id'];
						
						// remove all associated quantity discounts
						$db->Execute("delete from " . TABLE_PRODUCTS_DISCOUNT_QUANTITY . " where products_id = '" . (int)$v_products_id . "'");
					}
				}
			} // if ($v_products_discount_type != '0')
	
			
			
			// BEGIN: Products Descriptions
			// the following is common in both the updating an existing product and creating a new product
			if (isset($v_products_name)) {
				foreach( $v_products_name as $key => $name) {
					if ($name!='') {
						$sql = "SELECT * FROM ".TABLE_PRODUCTS_DESCRIPTION." WHERE
								products_id = $v_products_id AND
								language_id = " . $key;
						$result = ep_4_query($sql);
						if (mysql_num_rows($result) == 0) {
							// nope, this is a new product description
							$sql = "INSERT INTO ".TABLE_PRODUCTS_DESCRIPTION."(
									products_id,
									language_id,
									products_name,
									products_description,";
							if ($ep_supported_mods['psd'] == true) {
								$sql .= " products_short_desc,";
							}
							$sql .= " products_url )
									VALUES (
										'" . $v_products_id . "',
										" . $key . ",
										'" . zen_db_input($name) . "',
										'" . zen_db_input($v_products_description[$key]) . "',";
							if ($ep_supported_mods['psd'] == true) {
								$sql .= "'" . zen_db_input($v_products_short_desc[$key]) . "',";
							}
								$sql .= "'" . zen_db_input($v_products_url[$key]) . "')";
							$result = ep_4_query($sql);
						} else { // already in the description, update it
							$sql = "UPDATE ".TABLE_PRODUCTS_DESCRIPTION." SET
									products_name='" . zen_db_input($name) . "',
									products_description='" . zen_db_input($v_products_description[$key]) . "',
									";
							if ($ep_supported_mods['psd'] == true) {
								$sql .= " products_short_desc='" . zen_db_input($v_products_short_desc[$key]) . "',";
							}
							$sql .= " products_url='" . zen_db_input($v_products_url[$key]) . "'
								WHERE
									products_id = '$v_products_id' AND
									language_id = '$key'";
							$result = ep_4_query($sql);
						}
					}
				}
			} // END: Products Descriptions End
			
			// langer - Assign product to category if linked
			// chadd - this is buggy as instances occur when the master category id is INCORRECT!
			if (isset($v_categories_id)) { // find out if this product is listed in the category given
				$result_incategory = ep_4_query('SELECT
					'.TABLE_PRODUCTS_TO_CATEGORIES.'.products_id,
					'.TABLE_PRODUCTS_TO_CATEGORIES.'.categories_id
					FROM
					'.TABLE_PRODUCTS_TO_CATEGORIES.'
					WHERE
					'.TABLE_PRODUCTS_TO_CATEGORIES.'.products_id='.$v_products_id.' AND
					'.TABLE_PRODUCTS_TO_CATEGORIES.'.categories_id='.$v_categories_id);
	
				if (mysql_num_rows($result_incategory) == 0) { // nope, this is a new category for this product
					$res1 = ep_4_query('INSERT INTO '.TABLE_PRODUCTS_TO_CATEGORIES.' (products_id, categories_id)
								VALUES ("' . $v_products_id . '", "' . $v_categories_id . '")');
				} else { // already in this category, nothing to do!
				}
			}
			
			
			/* Specials - if a null value in specials price, do not add or update. If price = 0, let's delete it */
			if (isset($v_specials_price) && zen_not_null($v_specials_price)) {
				if ($v_specials_price >= $v_products_price) {
					$specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_PRICE_FAIL, $v_products_model, substr(strip_tags($v_products_name[$epdlanguage_id]), 0, 10));
					// available function: zen_set_specials_status($specials_id, $status)
					// could alternatively make status inactive, and still upload..
					continue;
				}
				// column is in upload file, and price is in field (not empty)
				// if null (set further above), set forever, else get raw date
				$has_specials == true;
				
				// using new date functions - chadd
				$v_specials_date_avail = ($v_specials_date_avail == true) ? date("Y-m-d H:i:s",strtotime($v_specials_date_avail)) : "0001-01-01";
				$v_specials_expires_date = ($v_specials_expires_date == true) ? date("Y-m-d H:i:s",strtotime($v_specials_expires_date)) : "0001-01-01";
				
				// Check if this product already has a special
				$special = ep_4_query("SELECT products_id FROM " . TABLE_SPECIALS . " WHERE products_id = ". $v_products_id);
																
				if (mysql_num_rows($special) == 0) { // not in db
					if ($v_specials_price == '0') { // delete requested, but is not a special
						$specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_DELETE_FAIL, $v_products_model, substr(strip_tags($v_products_name[$epdlanguage_id]), 0, 10));
						continue;
					}
					// insert new into specials
					$sql = "INSERT INTO " . TABLE_SPECIALS . "
						(products_id,
						specials_new_products_price,
						specials_date_added,
						specials_date_available,
						expires_date,
						status)
						VALUES (
						'" . (int)$v_products_id . "',
						'" . $v_specials_price . "',
						now(),
						'" . $v_specials_date_avail . "',
						'" . $v_specials_expires_date . "',
						'1')";
					$result = ep_4_query($sql);
					$specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_NEW, $v_products_model, substr(strip_tags($v_products_name[$epdlanguage_id]), 0, 10), $v_products_price , $v_specials_price);
				} else { // existing product
					if ($v_specials_price == '0') { // delete of existing requested
						$db->Execute("delete from " . TABLE_SPECIALS . " where products_id = '" . (int)$v_products_id . "'");
						$specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_DELETE, $v_products_model);
						continue;
					}
					// just make an update
					$sql = "UPDATE " . TABLE_SPECIALS . " SET
						specials_new_products_price	= '" . $v_specials_price . "',
						specials_last_modified		= now(),
						specials_date_available		= '" . $v_specials_date_avail . "',
						expires_date				= '" . $v_specials_expires_date . "',
						status						= '1'
						WHERE products_id			= '" . (int)$v_products_id . "'";
					ep_4_query($sql);
					$specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_UPDATE, $v_products_model, substr(strip_tags($v_products_name[$epdlanguage_id]), 0, 10), $v_products_price , $v_specials_price);
				} // we still have our special here
			} // end specials for this product
		} else {
			// this record is missing the product_model
			$display_output .= EASYPOPULATE_4_DISPLAY_RESULT_NO_MODEL;
			foreach ($items as $col => $langer) {
				if ($col == $filelayout['v_products_model']) continue;
				$display_output .= print_el_4($langer);
			}
		} // end of row insertion code
	}
	$display_output .= EASYPOPULATE_4_DISPLAY_RESULT_UPLOAD_COMPLETE;
}	
	
	/* Post-upload tasks start */
	ep_4_update_prices(); // update price sorter
	
	// specials status = 0 if date_expires is past.
	if ($has_specials == true) { // specials were in upload so check for expired specials
		zen_expire_specials();
	}
	
	/* Post-upload tasks end */
}
// END FILE UPLOADS

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
    
    
	<div style="text-align:right"><a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'epinstaller=remove') ?>">Un-Install EP4</a></div>

	<?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?>

     <div style="text-align:left">
            <form ENCTYPE="multipart/form-data" ACTION="easypopulate_4.php" METHOD="POST">
                <div align = "left">
                    <b>Upload EP File</b><br />
                    <input TYPE="hidden" name="MAX_FILE_SIZE" value="100000000">
                    <input name="usrfl" type="file" size="50">
                    <input type="submit" name="buttoninsert" value="Insert into db">
                    <br />
                </div>
            </form>
                
            <form ENCTYPE="multipart/form-data" ACTION="easypopulate_4.php" METHOD="POST">
                <div align = "left">
                    <b>Import from Temp Dir (<? echo $tempdir; ?>)</b><br />
                    <input TYPE="text" name="localfile" size="50">
                    <input type="submit" name="buttoninsert" value="Insert into db">
                    <br />
                </div>
				<br>
            </form>
			
		  <?php echo zen_draw_form('custom', 'easypopulate_4.php', 'id="custom"', 'get'); ?>
          <!--  <form ENCTYPE="multipart/form-data" ACTION="easypopulate_4.php?download=stream&dltype=full" METHOD="POST"> -->
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
            </form>
            </p>

            <b>Easy Populate Files</b>
            <br /><br />
            <!-- Download file links -  Add your custom fields here -->
            <a href="easypopulate_4.php?download=stream&dltype=full">Download <b>Complete Products</b></a><br />
            <a href="easypopulate_4.php?download=stream&dltype=priceqty">Download <b>Model/Price/Qty</b></a><br />
            <a href="easypopulate_4.php?download=stream&dltype=pricebreaks">Download <b>Model/Price/Breaks</b></a><br />
            <a href="easypopulate_4.php?download=stream&dltype=category">Download <b>Model/Category</b> </a><br />
			<br>
			<br>Under Construction<br>
            <a href="easypopulate_4.php?download=stream&dltype=attrib">Download <b>Detailed Products Attributes</b> (multi-line)</a><br />
            <a href="easypopulate_4.php?download=stream&dltype=attrib_basic">Download <b>Basic Products Attributes</b> (single-line)</a><br />
            <a href="easypopulate_4.php?download=stream&dltype=options">Download <b>Attribute Options Names</b> </a><br />
            <a href="easypopulate_4.php?download=stream&dltype=values">Download <b>Attribute Options Values</b> </a><br />
            <a href="easypopulate_4.php?download=stream&dltype=optionvalues">Download <b>Attribute Options-Names-to-Values</b> </a><br />
	
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