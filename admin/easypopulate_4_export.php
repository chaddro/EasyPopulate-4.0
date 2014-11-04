<?php
// $Id: easypopulate_4_export.php, v4.0.27 11-02-2014 mc12345678 $

// get download type
$ep_dltype = (isset($_POST['export'])) ? $_POST['export'] : $ep_dltype;
$display_output = '';

//if (isset($_POST['filter'])) {
//	$ep_dltype = $_POST['filter'];
//}

if (isset($_GET['export'])) {
	$ep_dltype = $_GET['export'];
}

if ($ep_dltype == '') {
   die("error: no export type set - press backspace to return."); // need better handler
}

$ep_export_count = 0;
// BEGIN: File Download Layouts
// if dltype is set, then create the filelayout.  Otherwise filelayout is read from the uploaded file.
// depending on the type of the download the user wanted, create a file layout for it.
$time_start = microtime(true); // benchmarking
// build export filters

// override for $ep_dltype
if ( isset($_POST['ep_export_type']) ) {
	if ($_POST['ep_export_type']=='0') { 
		$ep_dltype = 'full'; // Complete Products
	} elseif ($_POST['ep_export_type']=='1') {
		$ep_dltype = 'priceqty'; // Model/Price/Qty
	} elseif ($_POST['ep_export_type']=='2') {
		$ep_dltype = 'pricebreaks'; // Model/Price/Breaks
	}
}

$sql_filter = '';

if ( isset($_POST['ep_category_filter']) ) {
	if (!empty($_POST['ep_category_filter'])) {
		$sub_categories = array();
		$categories_query_addition = 'ptoc.categories_id = '.(int)$_POST['ep_category_filter'].'';
		zen_get_sub_categories($sub_categories, $_POST['ep_category_filter']);
		foreach ($sub_categories AS $key => $category ) {
			$categories_query_addition .= ' OR ptoc.categories_id = '.(int)$category.'';
		}
		$sql_filter .= ' AND ('.$categories_query_addition.')';
	}
}

if (isset($_POST['ep_manufacturer_filter'])) {
	if ($_POST['ep_manufacturer_filter']!='') { 
		$sql_filter .= ' AND p.manufacturers_id = '.(int)$_POST['ep_manufacturer_filter']; 
	}
}

if (isset($_POST['ep_status_filter'])) {	
	if ($_POST['ep_status_filter']!='3') { 
		$sql_filter .= ' AND p.products_status = '.(int)$_POST['ep_status_filter']; 
	}
}

if ($ep_dltype == 'SBAStockProdFilter') {
	$ep_dltype = 'SBAStock';
	$sql_filter = 'p.products_id = p.products_id ORDER BY p.products_id ASC';
	
}

$filelayout = array();
$filelayout_sql = '';
$filelayout = ep_4_set_filelayout($ep_dltype,  $filelayout_sql, $sql_filter, $langcode, $ep_supported_mods, $custom_fields); 
if (($ep_dltype == 'full' || $ep_dltype == 'categorymeta') && EASYPOPULATE_4_CONFIG_EXPORT_URI != '0') {
  //require_once(DIR_FS_CATALOG . DIR_WS_FUNCTIONS . 'functions_categories.php');
  $filelayout[] = 'v_html_uri';
}
$filelayout = array_flip($filelayout);
// END: File Download Layouts

// Create export file name
switch ($ep_dltype) { // chadd - changed to use $EXPORT_FILE
	case 'full':
	$EXPORT_FILE = 'Full-EP';
	break;
	case 'priceqty':
	$EXPORT_FILE = 'PriceQty-EP';
	break;
	case 'pricebreaks':
	$EXPORT_FILE = 'PriceBreaks-EP';
	break;
	case 'featured':
	$EXPORT_FILE = 'Featured-EP'; // added 5-2-2012
	break;
	case 'category':
	$EXPORT_FILE = 'Category-EP';
	break;
	case 'categorymeta': // chadd - added 12-02-2010
	$EXPORT_FILE = 'CategoryMeta-EP';
	break;
	case 'attrib_detailed':
	$EXPORT_FILE = 'Attrib-Detailed-EP';
	break;
	case 'SBA_detailed'; // mc12345678 - added 07-18-2013 to support Stock By Attributes
	$EXPORT_FILE = 'SBA-Detailed-EP';
	break;
	case 'SBAStock'; // mc12345678 - added 02-22-2014 to support providing a stock export when SBA is installed.
	$EXPORT_FILE = 'SBA-Stock-EP';
	break;
//	case 'SBAStockProdFilter'; // mc12345678 - added 03-08-2014 to support sorting by stock_id providing a stock export when SBA is installed
//	$EXPORT_FILE = 'SBA-Stock-EP';
//	break;
	case 'attrib_basic':
	$EXPORT_FILE = 'Attrib-Basic-EP';
	break;
	case 'options':
	$EXPORT_FILE = 'Options-EP';
	break;
	case 'values':
	$EXPORT_FILE = 'Values-EP';
	break;
	case 'optionvalues':
	$EXPORT_FILE = 'OptVals-EP';
	break;
}
$EXPORT_FILE .= strftime('%Y%b%d-%H%M%S'); // chadd - changed for hour.minute.second

// create file name and path and prepare for writing
$tmpfpath = DIR_FS_CATALOG.''.$tempdir."$EXPORT_FILE".(($csv_delimiter == ",")?".csv":".txt");
$fp = fopen( $tmpfpath, "w+"); 

$column_headers	= ""; // column headers
$filelayout_header = $filelayout; 

// prepare the table heading with layout values
foreach( $filelayout_header as $key => $value ) {
	$column_headers .= $key.$csv_delimiter;
}
// Trim trailing tab then append end-of-line
$column_headers = rtrim($column_headers, $csv_delimiter)."\n";
fwrite($fp, $column_headers); // write column headers

// these variables are for the Attrib_Basic Export
$active_products_id = ""; // start empty
$active_options_id = ""; // start empty
$active_language_id = ""; // start empty
$active_row = array(); // empty array
$last_products_id = "";
$print1 = 0;
$result = ep_4_query($filelayout_sql);
while ($row = ($ep_uses_mysqli ?  mysqli_fetch_array($result) : mysql_fetch_array($result))) {

	if ($ep_dltype == 'attrib_basic') { // special case 'attrib_basic'


	if ($row['v_products_id'] == $active_products_id) {
		if ($row['v_options_id'] == $active_options_id) {
			// collect the products_options_values_name
			if ($active_language_id <> $row['v_language_id']) {
				$l_id = $row['v_language_id'];
				$active_row['v_products_options_type'] = $row['v_products_options_type'];
				$active_row['v_products_options_name_'.$l_id] = $row['v_products_options_name'];
				$active_row['v_products_options_values_name_'.$l_id] = $row['v_products_options_values_name'];
				$active_language_id = $row['v_language_id'];
			} else {
				$l_id = $row['v_language_id'];
				$active_row['v_products_options_name_'.$l_id] = $row['v_products_options_name'];
				$active_row['v_products_options_values_name_'.$l_id] .= ",".$row['v_products_options_values_name'];
				$active_row['v_products_options_type'] = $row['v_products_options_type'];
			}
			continue; // loop - for more products_options_values_name on same v_products_id/v_options_id combo
		} else { // same product, new attribute - only executes once on new option
			// Clean the texts that could break CSV file formatting
			$dataRow = '';
			$problem_chars = array("\r", "\n", "\t"); // carriage return, newline, tab
			foreach($filelayout as $key => $value) {
				$thetext = $active_row[$key];
				// remove carriage returns, newlines, and tabs - needs review
				$thetext = str_replace($problem_chars,' ',$thetext);
				// encapsulate data in quotes, and escape embedded quotes in data
				$dataRow .= '"'.str_replace('"','""',$thetext).'"'.$csv_delimiter;
			}
			// Remove trailing tab, then append the end-of-line
			$dataRow = rtrim($dataRow,$csv_delimiter)."\n";
			fwrite($fp, $dataRow); // write 1 line of csv data (this can be slow...)
			$ep_export_count++;				
			
			$active_options_id = $row['v_options_id'];
			$active_language_id = $row['v_language_id'];
			$l_id = $row['v_language_id'];
			$active_row['v_products_options_name_'.$l_id] = $row['v_products_options_name'];
			$active_row['v_products_options_values_name_'.$l_id] = $row['v_products_options_values_name'];
			$active_row['v_products_options_type'] = $row['v_products_options_type'];
			continue; // loop - for more products_options_values_name on same v_products_id/v_options_id combo
		}
	} else { // new combo or different product or first time through while-loop
		if ($active_row['v_products_model'] <> $last_products_id) {
			// Clean the texts that could break CSV file formatting
			$dataRow = '';
			$problem_chars = array("\r", "\n", "\t"); // carriage return, newline, tab
			foreach($filelayout as $key => $value) {
				$thetext = $active_row[$key];
				// remove carriage returns, newlines, and tabs - needs review
				$thetext = str_replace($problem_chars,' ',$thetext);
				// encapsulate data in quotes, and escape embedded quotes in data
				$dataRow .= '"'.str_replace('"','""',$thetext).'"'.$csv_delimiter;
			}
			// Remove trailing tab, then append the end-of-line
			$dataRow = rtrim($dataRow,$csv_delimiter)."\n";
			fwrite($fp, $dataRow); // write 1 line of csv data (this can be slow...)
			$ep_export_count++;
			$last_products_id = $active_row['v_products_model'];
		}
		
		// get current row of data
		$active_products_id = $row['v_products_id'];
		$active_options_id = $row['v_options_id'];
		$active_language_id = $row['v_language_id'];
		
		$active_row['v_products_model'] = $row['v_products_model'];
		$active_row['v_products_options_type'] = $row['v_products_options_type'];
		
		$l_id = $row['v_language_id'];
		$active_row['v_products_options_name_'.$l_id] = $row['v_products_options_name']; 
		$active_row['v_products_options_values_name_'.$l_id] = $row['v_products_options_values_name'];
	} // end of special case 'attrib_basic'
	
} else { // standard export processing

		if ($ep_dltype == 'attrib_detailed') {
			if (isset($filelayout['v_products_attributes_filename'])) { 
				$sql2 = 'SELECT * FROM '.TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD.' WHERE products_attributes_id = '.$row['v_products_attributes_id'].' LIMIT 1';
				$result2 = ep_4_query($sql2);
				$row2 = ($ep_uses_mysqli ? mysqli_fetch_array($result2) : mysql_fetch_array($result2));
				if (($ep_uses_mysqli ? mysqli_num_rows($result2) : mysql_num_rows($result2))) {
					$row['v_products_attributes_filename'] = $row2['products_attributes_filename']; 
					$row['v_products_attributes_maxdays'] = $row2['products_attributes_maxdays']; 
					$row['v_products_attributes_maxcount'] = $row2['products_attributes_maxcount']; 
				} else {
					$row['v_products_attributes_filename'] = '';
					$row['v_products_attributes_maxdays'] = '';
					$row['v_products_attributes_maxcount'] = '';
				}
			}				
		} 
		elseif ($ep_dltype == 'SBA_detailed') {
				if (isset($filelayout['v_products_attributes_filename'])) /* Believe this should be an SBA filename; however, need to look at the filename assignment function to see how this works.  */{ 
					$sql2 = 'SELECT * FROM '.TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD.' WHERE products_attributes_id = '.$row['v_products_attributes_id'].' LIMIT 1';
					$result2 = ep_4_query($sql2);
				$row2 = ($ep_uses_mysqli ? mysqli_fetch_array($result2) : mysql_fetch_array($result2));
				if (($ep_uses_mysqli ? mysqli_num_rows($result2) : mysql_num_rows($result2))) {
						$row['v_products_attributes_filename'] = $row2['products_attributes_filename']; 
						$row['v_products_attributes_maxdays'] = $row2['products_attributes_maxdays']; 
						$row['v_products_attributes_maxcount'] = $row2['products_attributes_maxcount']; 
					} else {
						$row['v_products_attributes_filename'] = '';
						$row['v_products_attributes_maxdays'] = '';
						$row['v_products_attributes_maxcount'] = '';
					}
				}				
			}
		// Products Image
		if (isset($filelayout['v_products_image'])) { 
			$products_image = (($row['v_products_image'] == PRODUCTS_IMAGE_NO_IMAGE) ? '' : $row['v_products_image']);
		}
		// Multi-Lingual Meta-Tage, Products Name, Products Description, Products URL, and Products Short Descriptions
		if ($ep_dltype == 'full' || $ep_dltype == 'SBAStock') {
			// names and descriptions require that we loop thru all installed languages
			foreach ($langcode as $key => $lang) {
				$lid = $lang['id'];
				// metaData start
				$sqlMeta = 'SELECT * FROM '.TABLE_META_TAGS_PRODUCTS_DESCRIPTION.' WHERE products_id = '.$row['v_products_id'].' AND language_id = '.$lid.' LIMIT 1 ';
				$resultMeta = ep_4_query($sqlMeta);
				$rowMeta = ($ep_uses_mysqli ? mysqli_fetch_array($resultMeta) : mysql_fetch_array($resultMeta));
				$row['v_metatags_title_'.$lid]       = $rowMeta['metatags_title'];
				$row['v_metatags_keywords_'.$lid]    = $rowMeta['metatags_keywords'];
				$row['v_metatags_description_'.$lid] = $rowMeta['metatags_description'];
				// metaData end
				// for each language, get the description and set the vals
				$sql2    = 'SELECT * FROM '.TABLE_PRODUCTS_DESCRIPTION.' WHERE products_id = '.$row['v_products_id'].' AND language_id = '.$lid.' LIMIT 1 ';
				$result2 = ep_4_query($sql2);
				$row2 = ($ep_uses_mysqli ? mysqli_fetch_array($result2) : mysql_fetch_array($result2));
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
			if (($ep_uses_mysqli ? mysqli_num_rows($specials_query) : mysql_num_rows($specials_query)) ) {  // special
				$ep_specials = ($ep_uses_mysqli ? mysqli_fetch_array($specials_query) : mysql_fetch_array($specials_query));
				$row['v_specials_price']        = $ep_specials['specials_new_products_price'];
				$row['v_specials_date_avail']   = $ep_specials['specials_date_available'];
				$row['v_specials_expires_date'] = $ep_specials['expires_date'];
			} else { // no special
				$row['v_specials_price']        = '';
				$row['v_specials_date_avail']   = '';
				$row['v_specials_expires_date'] = '';
			}
		} // END: Specials
		
		// Multi-Lingual Categories, Categories Meta, Categories Descriptions
		if ($ep_dltype == 'categorymeta') {
			// names and descriptions require that we loop thru all languages that are turned on in the store
			foreach ($langcode as $key => $lang) {
				$lid = $lang['id'];
				// metaData start
				$sqlMeta = 'SELECT * FROM '.TABLE_METATAGS_CATEGORIES_DESCRIPTION.' WHERE categories_id = '.$row['v_categories_id'].' AND language_id = '.$lid.' LIMIT 1 ';
				$resultMeta = ep_4_query($sqlMeta) or die(($ep_uses_mysqli ? mysqli_error($db->link) : mysql_error()));
				$rowMeta = ($ep_uses_mysqli ? mysqli_fetch_array($resultMeta) : mysql_fetch_array($resultMeta));
				$row['v_metatags_title_'.$lid]       = $rowMeta['metatags_title'];
				$row['v_metatags_keywords_'.$lid]    = $rowMeta['metatags_keywords'];
				$row['v_metatags_description_'.$lid] = $rowMeta['metatags_description'];
				// metaData end
				// for each language, get category description and name
				$sql2    = 'SELECT * FROM '.TABLE_CATEGORIES_DESCRIPTION.' WHERE categories_id = '.$row['v_categories_id'].' AND language_id = '.$lid.' LIMIT 1 ';
				$result2 = ep_4_query($sql2);
				$row2 = ($ep_uses_mysqli ? mysqli_fetch_array($result2) : mysql_fetch_array($result2));
				$row['v_categories_name_'.$lid]        = $row2['categories_name'];
				$row['v_categories_description_'.$lid] = $row2['categories_description'];
			} // foreach

      if (EASYPOPULATE_4_CONFIG_EXPORT_URI != '0') {
        $row['v_html_uri'] = zen_catalog_href_link(FILENAME_DEFAULT, 'cPath=' . zen_get_path($row['v_categories_id']),'NONSSL');
      }

    } // if ($ep_dltype ...
		
		// CATEGORIES EXPORT
		// chadd - 12-13-2010 - logic change. $max_categories no longer required. better to loop back to root category and 
		// concatenate the entire categories path into one string with $category_delimiter for separater.
		if ( ($ep_dltype == 'full') || ($ep_dltype == 'category') ) { // chadd - 12-02-2010 fixed error: missing parenthesis
			// NEW While-loop for unlimited category depth			
			$category_delimiter = "^";
			$thecategory_id = $row['v_categories_id']; // starting category_id

      if ($ep_dltype == 'full' && EASYPOPULATE_4_CONFIG_EXPORT_URI != '0'){
        $sql_type = "SELECT type_handler FROM " . TABLE_PRODUCT_TYPES . " WHERE type_id = " . (int)zen_get_products_type($row['v_products_id']);
        $sql_typename = $db->Execute($sql_type);
//        $row['v_html_uri'] = zen_href_link(FILENAME_DEFAULT, 'main_page=' . $sql_typename->fields['type_handler'] . '_info&cPath=' . zen_get_generated_category_path_ids($row['v_master_categories_id']) . '&products_id=' . $row['v_products_id'],'NONSSL', false, true, false, true); //This generates an admin folder like link/reference not a catalog version.
        $row['v_html_uri'] = zen_catalog_href_link($sql_typename->fields['type_handler'] . '_info', 'cPath=' . zen_get_generated_category_path_ids($row['v_master_categories_id']) . '&products_id=' . $row['v_products_id'],'NONSSL');
//zen_catalog_href_link($page = '', $parameters = '', $connection = 'NONSSL')        //FILENAME_DEFAULT . '?main_page=' . zen_get_products_type($row['products_id'])
        //function zen_href_link($page = '', $parameters = '', $connection = 'NONSSL', $add_session_id = true, $search_engine_safe = true, $static = false, $use_dir_ws_catalog = true) 
      }
			// $fullcategory = array(); // this will have the entire category path separated by $category_delimiter
			// if parent_id is not null ('0'), then follow it up.
			while (!empty($thecategory_id)) {
				// mult-lingual categories start - for each language, get category description and name
				$sql2 =  'SELECT * FROM '.TABLE_CATEGORIES_DESCRIPTION.' WHERE categories_id = '.$thecategory_id.' ORDER BY language_id';
				$result2 = ep_4_query($sql2);
			while ($row2 = ($ep_uses_mysqli ? mysqli_fetch_array($result2) : mysql_fetch_array($result2))) {
					$lid = $row2['language_id'];
					$row['v_categories_name_'.$lid] = $row2['categories_name'].$category_delimiter.$row['v_categories_name_'.$lid];
				}
				// look for parent categories ID
				$sql3 = 'SELECT parent_id FROM '.TABLE_CATEGORIES.' WHERE categories_id = '.$thecategory_id;
				$result3 = ep_4_query($sql3);
			$row3 = ($ep_uses_mysqli ? mysqli_fetch_array($result3) : mysql_fetch_array($result3));
				$theparent_id = $row3['parent_id'];
				if ($theparent_id != '') { // Found parent ID, set thecategoryid to get the next level
				  $thecategory_id = $theparent_id;
				} else { // Category Root Found
				  $thecategory_id = false;
				}
			} // while
			
			// trim off trailing category delimiter '^'
			foreach ($langcode as $key => $lang) {
				$lid = $lang['id'];
				$row['v_categories_name_'.$lid] = rtrim($row['v_categories_name_'.$lid], "^");		
			} // foreach
		} // if() delimited categories path
		
		//This will do all of the special work to provide the remaining row data:
		//  	'v_SBA_tracked';
		//	'v_table_tracker';
		//  Products_attributes (either combined attributes of a non-SBA tracked item
		//	or the SBA stock data (both legible/understandable to a reviewer of the
		//	spreadsheet)
		//	
		// Clean the texts that could break CSV file formatting
		$dataRow ='';
		$problem_chars = array("\r", "\n", "\t"); // carriage return, newline, tab

		if ( ($ep_dltype == 'SBAStock')) {
			
			//Get the option names and option values for the current product


			// a = table PRODUCTS_ATTRIBUTES
			// p = table PRODUCTS
			// o = table PRODUCTS_OPTIONS
			// v = table PRODUCTS_OPTIONS_VALUES
			// d = table PRODUCTS_ATTRIBUTES_DOWNLOAD
			$sqlAttrib = 'SELECT DISTINCT 
				' . /*a.products_attributes_id            as v_products_attributes_id,*/'
				p.products_id                       as v_products_id,
				'./*p.products_model				    as v_products_model,
				a.options_id                        as v_options_id,
				o.products_options_id               as v_products_options_id,*/'
				o.products_options_name             as v_products_options_name,
				'./*o.products_options_type             as v_products_options_type,
				a.options_values_id                 as v_options_values_id,
				v.products_options_values_id        as v_products_options_values_id,*/'
				v.products_options_values_name      as v_products_options_values_name './*
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
				a.attributes_required               as v_attributes_required */' 
				FROM '
				.TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS. ' as ovpo,'
				.TABLE_PRODUCTS_OPTIONS_VALUES. ' as v,'
				.TABLE_PRODUCTS.                ' as p,'
				.TABLE_PRODUCTS_OPTIONS.        ' as o,'
				.TABLE_PRODUCTS_ATTRIBUTES.     ' a LEFT JOIN '
				.TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK.	' pwas ON pwas.stock_attributes = a.products_attributes_id
				WHERE
				a.products_id       = p.products_id AND
				a.options_id        = o.products_options_id AND 
				a.options_values_id = v.products_options_values_id AND
				o.language_id       = v.language_id AND
				p.products_id		= \''. $row['v_products_id'] .'\' AND
				o.products_options_id	= ovpo.products_options_id AND
				v.products_options_values_id	= ovpo.products_options_values_id AND 
				o.language_id       = 1 ORDER BY p.products_id ASC';/*, a.options_id, v.products_options_values_id';*/
			
			$resultAttrib = ep_4_query($sqlAttrib);
			$resultAttribCount = ($ep_uses_mysqli ? mysqli_num_rows($resultAttrib) : mysql_num_rows($resultAttrib));
			
			if ($resultAttribCount !== false) {
				while ($rowAttrib = ($ep_uses_mysqli ? mysqli_fetch_assoc($resultAttrib) : mysql_fetch_assoc($resultAttrib))) {
					$row['v_products_attributes'] .= $rowAttrib['v_products_options_name'] . ': ' . $rowAttrib['v_products_options_values_name'] . '; ';
				}
			}
			//Combine the option names and option values for this product.
			//Set v_products_attributes to the combined data
			//Assign product_id to v_table_tracker for main product
			$row['v_SBA_tracked'] = '';
			$row['v_table_tracker'] = $row['v_products_id'];

			//Check if product is tracked via SBA  
			$sqlSBA = 'SELECT
				s.products_id				as v_products_id, 
				s.stock_id					 as v_stock_id,
				s.stock_attributes				 as v_stock_attributes,
				s.quantity					 as v_quantity' . ( $ep_4_SBAEnabled == '2' ? ',
        s.customid            as v_customid ' : ' ') .
				'FROM '
				.TABLE_PRODUCTS.                ' as p,'
				.TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK. ' as s
				WHERE 
				s.products_id		= p.products_id AND
				p.products_id = \''. $row['v_products_id'] . '\'';
			$resultSBA = ep_4_query($sqlSBA);
			$resultSBACount = ($ep_uses_mysqli ? mysqli_num_rows($resultSBA) : mysql_num_rows($resultSBA));
			
			if ($resultSBACount !== false && $resultSBACount > 0) {
				//If product is tracked by SBA

				// Clean the data then write the row of the original data
				$dataRow ='';
				foreach($filelayout as $key => $value) {
					if (strpos($key, "v_products_description") !== false ){
						$row[$key] = strip_tags($row[$key]);
					}
					$thetext = $row[$key];
					// remove carriage returns, newlines, and tabs - needs review
					$thetext = str_replace($problem_chars,' ',$thetext);
					// $thetext = str_replace("\r",' ',$thetext);
					// $thetext = str_replace("\n",' ',$thetext);
					// $thetext = str_replace("\t",' ',$thetext);

					// encapsulate data in quotes, and escape embedded quotes in data
					$dataRow .= '"'.str_replace('"','""',$thetext).'"'.$csv_delimiter;
					/*
					if ($excel_safe_output == true) { // encapsulate field data with quotes
					  // use quoted values and escape the embedded quotes for excel safe output.
					  $dataRow .= '"'.str_replace('"','""',$thetext).'"' . $csv_delimiter;
					} else {
					  // and put the text into the output separated by $csv_delimiter defined above
					  $dataRow .= $thetext . $csv_delimiter;
					} */
				} // End Data Cleanup

				// Remove trailing tab, then append the end-of-line
				$dataRow = rtrim($dataRow,$csv_delimiter)."\n";

				fwrite($fp, $dataRow); // write 1 line of csv data (this can be slow...)
				
				// loop through the SBA data until one before the end
				// Must get the attribute and quantity data from the SBA table
				// While not at the one before end
				//  clean the data then
				//  Store the data.
				$resultSBACounter = 1;
				while ($rowSBA = ($ep_uses_mysqli ? mysqli_fetch_assoc($resultSBA) : mysql_fetch_assoc($resultSBA))){
//					print_r($rowSBA);
					//$rowSBA    = mysql_fetch_array($resultSBA);
					//  get the attribute and quantity data from the SBA table
					//  clean the data then

					//Need to explode the attributes list,
					$trackAttribute = explode(",", $rowSBA['v_stock_attributes']);
					//Need to trim the numerical string before sending for review
					$row['v_products_attributes'] = '';
					foreach ($trackAttribute as $currentAttrib) {
						$sqlSBAAttributes = 'SELECT
							o.products_options_name		as v_SBA_option_name,
							v.products_options_values_name	as v_SBA_value_name 
							FROM '
							.TABLE_PRODUCTS_OPTIONS .		' as o, '
							.TABLE_PRODUCTS_OPTIONS_VALUES .	' as v, '
							.TABLE_PRODUCTS_ATTRIBUTES .		' as a
							WHERE 
							o.products_options_id = a.options_id AND
							v.products_options_values_id = a.options_values_id AND
							a.products_attributes_id = \''. trim($currentAttrib) . '\'';
						$resultSBAAttributes = ep_4_query($sqlSBAAttributes);
						$resultSBACountAttributes = ($ep_uses_mysqli ? mysqli_fetch_assoc($resultSBAAttributes) : mysql_fetch_assoc($resultSBAAttributes));

						$row['v_products_attributes'] .= (is_null($resultSBACountAttributes['v_SBA_value_name']) ? 'Option Does Not Exist' : $resultSBACountAttributes['v_SBA_option_name']) . ': ' . (is_null($resultSBACountAttributes['v_SBA_value_name']) ? 'Value Does Not Exist' : $resultSBACountAttributes['v_SBA_value_name']) . '; ';
					}

					$row['v_products_quantity'] = $rowSBA['v_quantity'];
					$row['v_SBA_tracked'] = 'X';
					$row['v_table_tracker'] = $rowSBA['v_stock_id'];
          if ($ep_4_SBAEnabled == '2') {
            $row['v_customid'] = $rowSBA['v_customid'];
          }
					// loop through the SBA data until one before the end
					// While not at the one before end
					//  get the attribute and quantity data from the SBA table
					if ($resultSBACounter < $resultSBACount) {
						//  clean the data then
						//  write the row (keep the same base data as previous)
						// Clean the data then write the row of the original data
						$dataRow ='';
						foreach($filelayout as $key => $value) {
							if (strpos($key, "v_products_description") !== false ){
								$row[$key] = strip_tags($row[$key]);
							}
							$thetext = $row[$key];
							// remove carriage returns, newlines, and tabs - needs review
							$thetext = str_replace($problem_chars,' ',$thetext);
							// $thetext = str_replace("\r",' ',$thetext);
							// $thetext = str_replace("\n",' ',$thetext);
							// $thetext = str_replace("\t",' ',$thetext);

							// encapsulate data in quotes, and escape embedded quotes in data
							$dataRow .= '"'.str_replace('"','""',$thetext).'"'.$csv_delimiter;
							/*
							if ($excel_safe_output == true) { // encapsulate field data with quotes
							  // use quoted values and escape the embedded quotes for excel safe output.
							  $dataRow .= '"'.str_replace('"','""',$thetext).'"' . $csv_delimiter;
							} else {
							  // and put the text into the output separated by $csv_delimiter defined above
							  $dataRow .= $thetext . $csv_delimiter;
							} */
						} // End Data Cleanup

						// Remove trailing tab, then append the end-of-line
						$dataRow = rtrim($dataRow,$csv_delimiter)."\n";

						fwrite($fp, $dataRow); // write 1 line of csv data (this can be slow...)
						
					}
					$resultSBACounter++;
				}
				//  For the last row, assign the data to the $row; however, do not write
				//	it yet.. The end of the below loop will write it.

			}
		} // End if SBAStock
	
		// Music Information Export for products with products_type == 2
		if (isset($filelayout['v_artists_name'])  && ($row['v_products_type'] == '2')) {
			$sql_music_extra = 'SELECT * FROM '.TABLE_PRODUCT_MUSIC_EXTRA.' WHERE products_id = '.$row['v_products_id'].' LIMIT 1';
			$result_music_extra = ep_4_query($sql_music_extra);
		$row_music_extra = ($ep_uses_mysqli ? mysqli_fetch_array($result_music_extra) : mysql_fetch_array($result_music_extra));
			// artist
			if ( ($row_music_extra['artists_id'] != '0') && ($row_music_extra['artists_id'] != '') ) { // '0' is correct, but '' NULL is possible
				$sql_record_artists = 'SELECT * FROM '.TABLE_RECORD_ARTISTS.' WHERE artists_id = '.$row_music_extra['artists_id'].' LIMIT 1';
				$result_record_artists = ep_4_query($sql_record_artists);
			$row_record_artists = ($ep_uses_mysqli ? mysqli_fetch_array($result_record_artists) : mysql_fetch_array($result_record_artists));
				$row['v_artists_name'] = $row_record_artists['artists_name'];
				$row['v_artists_image'] = $row_record_artists['artists_image'];
				foreach ($langcode as $key => $lang) {
					$lid = $lang['id'];
					$sql_record_artists_info = 'SELECT * FROM '.TABLE_RECORD_ARTISTS_INFO.' WHERE artists_id = '.$row_music_extra['artists_id'].' AND languages_id = '.$lid.' LIMIT 1';
					$result_record_artists_info = ep_4_query($sql_record_artists_info);
					$row_record_artists_info = ($ep_uses_mysqli ? mysqli_fetch_array($result_record_artists_info) : mysql_fetch_array($result_record_artists_info));
					$row['v_artists_url_'.$lid] = $row_record_artists_info['artists_url'];
				}
			} else { 
				$row['v_artists_name'] = ''; // no artists name
				$row['v_artists_image'] = '';
				foreach ($langcode as $key => $lang) {
					$lid = $lang['id'];
					$row['v_artists_url_'.$lid] = '';
				}
			}
			// record company
			if ( ($row_music_extra['record_company_id'] != '0') && ($row_music_extra['record_company_id'] != '') ) { // '0' is correct, but '' NULL is possible
				$sql_record_company = 'SELECT * FROM '.TABLE_RECORD_COMPANY.' WHERE record_company_id = '.$row_music_extra['record_company_id'].' LIMIT 1';
				$result_record_company = ep_4_query($sql_record_company);
			$row_record_company = ($ep_uses_mysqli ? mysqli_fetch_array($result_record_company) : mysql_fetch_array($result_record_company));
				$row['v_record_company_name'] = $row_record_company['record_company_name'];
				$row['v_record_company_image'] = $row_record_company['record_company_image'];
				foreach ($langcode as $key => $lang) {
					$lid = $lang['id'];
					$sql_record_company_info = 'SELECT * FROM '.TABLE_RECORD_COMPANY_INFO.' WHERE record_company_id = '.$row_music_extra['record_company_id'].' AND languages_id = '.$lid.' LIMIT 1';
					$result_record_company_info = ep_4_query($sql_record_company_info);
					$row_record_company_info = ($ep_uses_mysqli ? mysqli_fetch_array($result_record_company_info) : mysql_fetch_array($result_record_company_info));
					$row['v_record_company_url_'.$lid] = $row_record_company_info['record_company_url'];
				}
			} else {  
				$row['v_record_company_name'] = '';
				$row['v_record_company_image'] = '';
			}
			// genre
			if ( ($row_music_extra['music_genre_id'] != '0') && ($row_music_extra['music_genre_id'] != '') ) { // '0' is correct, but '' NULL is possible
				$sql_music_genre = 'SELECT * FROM '.TABLE_MUSIC_GENRE.' WHERE music_genre_id = '.$row_music_extra['music_genre_id'].' LIMIT 1';
				$result_music_genre = ep_4_query($sql_music_genre);
			$row_music_genre = ($ep_uses_mysqli ? mysqli_fetch_array($result_music_genre) : mysql_fetch_array($result_music_genre));
				$row['v_music_genre_name'] = $row_music_genre['music_genre_name'];
			} else {  
				$row['v_music_genre_name'] = '';
			}
		}
		

		// MANUFACTURERS EXPORT - THIS NEEDS MULTI-LINGUAL SUPPORT LIKE EVERYTHING ELSE!
		// if the filelayout says we need a manfacturers name, get it for download file
		if (isset($filelayout['v_manufacturers_name'])) {
			if ( ($row['v_manufacturers_id'] != '0') && ($row['v_manufacturers_id'] != '') ) { // '0' is correct, but '' NULL is possible
				$sql2 = 'SELECT manufacturers_name FROM '.TABLE_MANUFACTURERS.' WHERE manufacturers_id = '.$row['v_manufacturers_id'];
				$result2 = ep_4_query($sql2);
			$row2 = ($ep_uses_mysqli ? mysqli_fetch_array($result2) : mysql_fetch_array($result2));
				$row['v_manufacturers_name'] = $row2['manufacturers_name']; 
			} else {  // this is to fix the error on manufacturers name
				// $row['v_manufacturers_id'] = '0';  blank name mean 0 id - right? chadd 4-7-09
				$row['v_manufacturers_name'] = ''; // no manufacturer name
			}
		}
		
		// Price/Qty/Discounts
		$discount_index = 1;
		while (isset($filelayout['v_discount_qty_'.$discount_index])) {
			if ($row['v_products_discount_type'] != '0') { // if v_products_discount_type == 0 then there are no quantity breaks
				$sql2 = 'SELECT discount_id, discount_qty, discount_price FROM '. 
					TABLE_PRODUCTS_DISCOUNT_QUANTITY.' WHERE products_id = '. 
					$row['v_products_id'].' AND discount_id='.$discount_index;
				$result2 = ep_4_query($sql2);
				$row2 = ($ep_uses_mysqli ? mysqli_fetch_array($result2) : mysql_fetch_array($result2));
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

		// Clean the texts that could break CSV file formatting
		$dataRow ='';
		$problem_chars = array("\r", "\n", "\t"); // carriage return, newline, tab
		foreach($filelayout as $key => $value) {
			$thetext = $row[$key];
			// remove carriage returns, newlines, and tabs - needs review
			$thetext = str_replace($problem_chars,' ',$thetext);
			// $thetext = str_replace("\r",' ',$thetext);
			// $thetext = str_replace("\n",' ',$thetext);
			// $thetext = str_replace("\t",' ',$thetext);
			
			// encapsulate data in quotes, and escape embedded quotes in data
			$dataRow .= '"'.str_replace('"','""',$thetext).'"'.$csv_delimiter;
			/*
			if ($excel_safe_output == true) { // encapsulate field data with quotes
			  // use quoted values and escape the embedded quotes for excel safe output.
			  $dataRow .= '"'.str_replace('"','""',$thetext).'"' . $csv_delimiter;
			} else {
			  // and put the text into the output separated by $csv_delimiter defined above
			  $dataRow .= $thetext . $csv_delimiter;
			} */
		}
		// Remove trailing tab, then append the end-of-line
		$dataRow = rtrim($dataRow,$csv_delimiter)."\n";

		fwrite($fp, $dataRow); // write 1 line of csv data (this can be slow...)
		$ep_export_count++;
} // if 
} // while ($row)

if ($ep_dltype == 'attrib_basic') { // must write last record
	// Clean the texts that could break CSV file formatting
	$dataRow = '';
	$problem_chars = array("\r", "\n", "\t"); // carriage return, newline, tab
	foreach($filelayout as $key => $value) {
		$thetext = $active_row[$key];
		// remove carriage returns, newlines, and tabs - needs review
		$thetext = str_replace($problem_chars,' ',$thetext);
		// encapsulate data in quotes, and escape embedded quotes in data
		$dataRow .= '"'.str_replace('"','""',$thetext).'"'.$csv_delimiter;
	}
	// Remove trailing tab, then append the end-of-line
	$dataRow = rtrim($dataRow,$csv_delimiter)."\n";
	fwrite($fp, $dataRow); // write 1 line of csv data (this can be slow...)
	$ep_export_count++;
}



fclose($fp); // close output file

$display_output .= '<br><u><h3>Export Results</h3></u><br>';
$display_output .= sprintf(EASYPOPULATE_4_MSGSTACK_FILE_EXPORT_SUCCESS, $EXPORT_FILE, $tempdir);	
$display_output .= '<br>Records Exported: '.$ep_export_count.'<br>';
if (function_exists('memory_get_usage')) {
	$display_output .= '<br>Memory Usage: '.memory_get_usage(); 
	$display_output .= '<br>Memory Peak: '.memory_get_peak_usage(); 
}
// benchmarking
$time_end = microtime(true);
$time = $time_end - $time_start;	
$display_output .= '<br>Execution Time: '.$time.' seconds.';
?>