<?php
// BEGIN: Data Import Module
if ( isset($_GET['import']) ) {
	$time_start = microtime(true); // benchmarking
	$display_output .= EASYPOPULATE_4_DISPLAY_HEADING;

	$file = array('name' => $_GET['import']);
	$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_LOCAL_FILE_SPEC, $file['name']);
	
	$ep_update_count = 0; // product records updated 
	$ep_import_count = 0; // new products records imported
	$ep_error_count = 0; // errors detected during import
	$ep_warning_count = 0; // warning detected during import

	// When updating products info, these values are used for exisint data
	// This allows a reduced number of columns to be used on updates 
	// otherwise these would have to be exported/imported every time
	// this ONLY applies to when a column is MISSING
	$default_these = array();
	$default_these[] = 'v_products_image';
	$default_these[] = 'v_categories_id';
	$default_these[] = 'v_products_price';
	if ($ep_supported_mods['uom'] == true) { // price UOM mod - chadd
		$default_these[] = 'v_products_price_uom';
	} 
	if ($ep_supported_mods['upc'] == true) { // UPC Code mod - chadd
		$default_these[] = 'v_products_upc';
	}
	if ($ep_supported_mods['gpc'] == true) { // Google Product Category for Google Merchant Center - chadd 10-1-2011
		$default_these[] = 'v_products_gpc';
	}
	if ($ep_supported_mods['msrp'] == true) { // Manufacturer's Suggested Retail Price
		$default_these[] = 'v_products_msrp';
	} 
	$default_these[] = 'v_products_quantity';
	$default_these[] = 'v_products_weight';
	$default_these[] = 'v_products_discount_type';
	$default_these[] = 'v_products_discount_type_from';
	$default_these[] = 'v_product_is_call';
	$default_these[] = 'v_products_sort_order';
	$default_these[] = 'v_products_quantity_order_min';
	$default_these[] = 'v_products_quantity_order_units';
	$default_these[] = 'v_date_added';
	$default_these[] = 'v_date_avail'; // chadd - this should default to null not "zero" or system date
	$default_these[] = 'v_instock';
	$default_these[] = 'v_tax_class_title';
	$default_these[] = 'v_manufacturers_name';
	$default_these[] = 'v_manufacturers_id';
	$default_these[] = 'v_products_status'; // added by chadd so that de-activated products are not reactivated when the column is missing
	// metatags switches also need to be pulled, 11-08-2011
	$default_these[] = 'v_metatags_products_name_status';
	$default_these[] = 'v_metatags_title_status';
	$default_these[] = 'v_metatags_model_status';
	$default_these[] = 'v_metatags_price_status';
	$default_these[] = 'v_metatags_title_tagline_status';

	$file_location = DIR_FS_CATALOG . $tempdir . $file['name'];
	// Error Checking
	if (!file_exists($file_location)) {
		$display_output .='<font color="red"><b>ERROR: Import file does not exist:'.$file_location.'</b></font><br>';
	} else if ( !($handle = fopen($file_location, "r"))) {
		$display_output .= '<font color="red"><b>ERROR: Cannot open import file:'.$file_location.'</b></font><br>';
	}
	
	// Read Column Headers
	if ($filelayout = array_flip(fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure))) {
	
	// Attributes Import
	if (substr($file['name'],0,9) == "Attrib-EP") {
		require_once('easypopulate_4_attrib.php');
	} // Attributes Import	

	// CATEGORIES1
	// This Category MetaTags import routine only deals with existing Categories. It does not create or modify the tree.
	// This code is ONLY used to Edit Categories image, description, and metatags data!
	// Categories are updated via the categories_id NOT the Name!! Very important distinction here!
	if (substr($file['name'],0,15) == "CategoryMeta-EP") {
		while ($items = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) { // read 1 line of data
			// $items[$filelayout['v_categories_id']];
			// $items[$filelayout['v_categories_image']];
			$sql = 'SELECT categories_id FROM '.TABLE_CATEGORIES.' WHERE (categories_id = '.$items[$filelayout['v_categories_id']].') LIMIT 1';
			$result = ep_4_query($sql);
			if ($row = mysql_fetch_array($result)) {
				// UPDATE
				$sql = "UPDATE ".TABLE_CATEGORIES." SET 
					categories_image = '".addslashes($items[$filelayout['v_categories_image']])."',
					last_modified    = CURRENT_TIMESTAMP 
					WHERE (categories_id = ".$items[$filelayout['v_categories_id']].")";
				$result = ep_4_query($sql);
				foreach ($langcode as $key => $lang) {
					$lid = $lang['id'];
					// $items[$filelayout['v_categories_name_'.$lid]];
					// $items[$filelayout['v_categories_description_'.$lid]];
					$sql = "UPDATE ".TABLE_CATEGORIES_DESCRIPTION." SET 
						categories_name        = '".addslashes($items[$filelayout['v_categories_name_'.$lid]])."',
						categories_description = '".addslashes($items[$filelayout['v_categories_description_'.$lid]])."'
						WHERE 
						(categories_id = ".$items[$filelayout['v_categories_id']]." AND language_id = ".$lid.")";
					$result = ep_4_query($sql);
				
					// $items[$filelayout['v_metatags_title_'.$lid]];
					// $items[$filelayout['v_metatags_keywords_'.$lid]];
					// $items[$filelayout['v_metatags_description_'.$lid]];
					// Categories Meta Start
					$sql = "SELECT categories_id FROM ".TABLE_METATAGS_CATEGORIES_DESCRIPTION." WHERE 
						(categories_id = ".$items[$filelayout['v_categories_id']]." AND language_id = ".$lid.") LIMIT 1";
					$result = ep_4_query($sql);
					if ($row = mysql_fetch_array($result)) {
						// UPDATE
						$sql = "UPDATE ".TABLE_METATAGS_CATEGORIES_DESCRIPTION." SET 
						metatags_title			= '".addslashes($items[$filelayout['v_metatags_title_'.$lid]])."',
						metatags_keywords		= '".addslashes($items[$filelayout['v_metatags_keywords_'.$lid]])."',
						metatags_description	= '".addslashes($items[$filelayout['v_metatags_description_'.$lid]])."'
						WHERE 
						(categories_id = ".$items[$filelayout['v_categories_id']]." AND language_id = ".$lid.")";
					} else {
						// NEW - this should not happen
						$sql = "INSERT INTO ".TABLE_METATAGS_CATEGORIES_DESCRIPTION." SET 
							metatags_title			= '".addslashes($items[$filelayout['v_metatags_title_'.$lid]])."',
							metatags_keywords		= '".addslashes($items[$filelayout['v_metatags_keywords_'.$lid]])."',
							metatags_description	= '".addslashes($items[$filelayout['v_metatags_description_'.$lid]])."',
							categories_id			= '".$items[$filelayout['v_categories_id']]."',
							language_id 			= '".$lid."'";
					}
					$result = ep_4_query($sql);					
				} 
			} else { // error
				// die("Category ID not Found");
				$display_output .= sprintf('<br /><font color="red"><b>SKIPPED! - Category ID: </b>%s - Not Found!</font>', $items[$filelayout['v_categories_id']]);
			} // if category found
		} // while
	} // if

if ((substr($file['name'],0,15) <> "CategoryMeta-EP") && (substr($file['name'],0,9) <> "Attrib-EP")){ //  temporary solution here... 12-06-2010
	
	// Main IMPORT loop For Product Related Data. v_products_id is the main key
	while ($items = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) { // read 1 line of data
	
		// now do a query to get the record's current contents
		// chadd - 12-14-2010 - redefining this variable everytime it loops must be very inefficient! must be a better way!
		$sql = 'SELECT
			p.products_id					as v_products_id,
			p.products_model				as v_products_model,
			p.products_image				as v_products_image,
			p.products_price				as v_products_price,';
			
		if ($ep_supported_mods['uom'] == true) { // price UOM mod - chadd
			$sql .= 'p.products_price_uom as v_products_price_uom,';
		} 
		if ($ep_supported_mods['upc'] == true) { // UPC Code mod- chadd
			$sql .= 'p.products_upc as v_products_upc,'; 
		}
		if ($ep_supported_mods['gpc'] == true) { // Google Product Category for Google Merhant Center - chadd 10-1-2011
			$sql .= 'p.products_gpc as v_products_gpc,'; 
		}
		if ($ep_supported_mods['msrp'] == true) { // Manufacturer's Suggested Retail Price
			$sql .= 'p.products_msrp as v_products_msrp,'; 
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
			p.metatags_products_name_status	as v_metatags_products_name_status,
			p.metatags_title_status			as v_metatags_title_status,
			p.metatags_model_status			as v_metatags_model_status,
			p.metatags_price_status			as v_metatags_price_status,
			p.metatags_title_tagline_status	as v_metatags_title_tagline_status,
			subc.categories_id				as v_categories_id
			FROM '.
			TABLE_PRODUCTS.' as p,'.
			TABLE_CATEGORIES.' as subc,'.
			TABLE_PRODUCTS_TO_CATEGORIES." as ptoc
			WHERE
			p.products_id      = ptoc.products_id AND
			p.products_model   = '".addslashes($items[$filelayout['v_products_model']])."' AND
			ptoc.categories_id = subc.categories_id";
			
		$result	= ep_4_query($sql);
		$product_is_new = true;
		
//============================================================================
		// this appears to get default values for current v_products_model
		// inputs: $items array (file data by column #); $filelayout array (headings by column #); 
		// $row (current TABLE_PRODUCTS data by heading name)
		while ( $row = mysql_fetch_array($result) ) { // chadd - this executes once?? why use while-loop??
			$product_is_new = false; // we found products_model in database
			// Get current products descriptions and categories for this model from database
			// $row at present consists of current product data for above fields only (in $sql)

			// since we have a row, the item already exists.
			// let's check and delete it if requested   
			// v_status == 9 is a delete request  
			if ($items[$filelayout['v_status']] == 9) {
				$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_DELETED, $items[$filelayout['v_products_model']]);
				ep_4_remove_product($items[$filelayout['v_products_model']]);
				continue 2; // short circuit to next row
			}
			
			// Create variables and assign default values for each language products name, description, url and optional short description
			foreach ($langcode as $key => $lang) {
				$sql2 = 'SELECT * FROM '.TABLE_PRODUCTS_DESCRIPTION.' WHERE products_id = '.$row['v_products_id'].' AND language_id = '.$lang['id'];
				$result2 = ep_4_query($sql2);
				$row2 = mysql_fetch_array($result2);
				// create variables (v_products_name_1, v_products_name_2, etc. which corresponds to our column headers) and assign data
				$row['v_products_name_'.$lang['id']] = $row2['products_name'];
				
// utf-8 conversion of smart-quotes, em-dash, and ellipsis
				$text = $row2['products_description'];
				$text = str_replace(
 array("\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x93", "\xe2\x80\x94", "\xe2\x80\xa6"),
 array("'", "'", '"', '"', '-', '--', '...'),
 $text);
				$row['v_products_description_'.$lang['id']] = $text; // description assigned				
				
				//$row['v_products_description_'.$lang['id']] = $row2['products_description']; // description assigned
				// if short descriptions exist
				if ($ep_supported_mods['psd'] == true) {
					$row['v_products_short_desc_'.$lang['id']] = $row2['products_short_desc'];
				}
				$row['v_products_url_'.$lang['id']] = $row2['products_url']; // url assigned
			}
			
			// Default values for manufacturers name if exist
			// Note: need to test for '0' and NULL for best compatibility with older version of EP that set blank manufacturers to NULL
			// I find it very strange that the Manufacturer's Name is NOT multi-lingual, but he URL IS!
			if (($row['v_manufacturers_id'] != '0') && ($row['v_manufacturers_id'] != '') ) { // if 0, no manufacturer set
				$sql2 = 'SELECT manufacturers_name FROM '.TABLE_MANUFACTURERS.' WHERE manufacturers_id = '.$row['v_manufacturers_id'];
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
			
			// $$thisvar creates a variable named $thisvar and sets the value to $row value ($v_products_price = $row['v_products_price'];), 
			// which is the existing value for these fields in the database before importing the updated information
			foreach ($default_these as $thisvar) {
				$$thisvar = $row[$thisvar];
			}
		} // while ( $row = mysql_fetch_array($result) )
//============================================================================
	
		// basic error checking
		
		// inputs: $items; $filelayout; $product_is_new
		// chadd - this first condition cannot exist since we short-circuited on delete above
		if ($items[$filelayout['v_status']] == 9 && zen_not_null($items[$filelayout['v_products_model']])) {
			// cannot delete product that is not found
			$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_DELETE_NOT_FOUND, $items[$filelayout['v_products_model']]);
			continue;
		}
		
		// NEW products must have a 'categories_name_'.$lang['id'] column header, else error out
		if ($product_is_new == true) {
			if ( zen_not_null($items[$filelayout['v_products_model']]) ) { // must have products_model
				// new products must have a categories_name to be added to the store.
				$categories_name_exists = false; // assume no column defined
				foreach ($langcode as $key => $lang) {
					// test column headers for each language
					if (zen_not_null(trim($items[$filelayout['v_categories_name_'.$lang['id']]])) ) { // import column found
						$categories_name_exists = true;
					}
				}
				if ( !$categories_name_exists ) {
					// let's skip this new product without a master category..
					$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_NOT_FOUND, $items[$filelayout['v_products_model']], ' new');
					$ep_error_count++;
					continue; // error, loop to next record
				}
			} else {
				// minimum test for new product - model(already tested below), name, price, category, taxclass(?), status (defaults to active)
				// to add
			}
		} else { // Product Exists
			// I don't see why this is necessary
			/*		
			if (!zen_not_null(trim($items[$filelayout['v_categories_name_1']])) && isset($filelayout['v_categories_name_1'])) {
				// let's skip this existing product without a master category but has the column heading
				// or should we just update it to result of $row (it's current category..)??
				$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_NOT_FOUND, $items[$filelayout['v_products_model']], '');
				foreach ($items as $col => $summary) {
					if ($col == $filelayout['v_products_model']) continue;
					$display_output .= print_el_4($summary);
				}
				continue; // error, loop to next record
			}
			*/
			
		} // End data checking

		// Assign new values, i.e. $v_products_model = new import value over writing existing data pulled above
		// This loop goes through all the fields in the import file and sets each corresponding variable.
		// Variables not set here are either set in the loop above for existing product records
		// $key is column heading name, $value is column number for the heading..
		foreach ($filelayout as $key => $value) {
			$$key = $items[$value];
		}
	
		// chadd - 12-13-2010 - this should allow you to update descriptions only for any given language of the import file!
		// cycle through all defined language codes, but only update those languages defined in the import file
		// Note 11-08-2011: I may remove the "smart_tags_4" function for better performance.
		foreach ($langcode as $lang) {
			$l_id = $lang['id'];
			// products meta tags
			if ( isset($filelayout['v_metatags_title_'.$l_id ]) ) { 
				$v_metatags_title[$l_id] = $items[$filelayout['v_metatags_title_'.$l_id]];
				$v_metatags_keywords[$l_id] = $items[$filelayout['v_metatags_keywords_'.$l_id]];
				$v_metatags_description[$l_id] = $items[$filelayout['v_metatags_description_'.$l_id]];
			}
			// products name, description, url, and optional short description
			// smart_tags_4 ... removed - chadd 11-18-2011 - will look into this feature at a future date
			// chadd 12-09-2011 - added some error checking on field lengths
			if (isset($filelayout['v_products_name_'.$l_id ])) { // do for each language in our upload file if exist
				$v_products_name[$l_id] = ep_4_curly_quotes($items[$filelayout['v_products_name_'.$l_id]]);
				// check products name length and display warning on error, but still process record
				if (mb_strlen($v_products_name[$l_id]) > $products_name_max_len) { 
					$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_PRODUCTS_NAME_LONG, $v_products_model, $v_products_name[$l_id], $products_name_max_len);
					$ep_warning_count++;
				}
				// utf-8 conversion of smart-quotes, em-dash, en-dash, and ellipsis
				$v_products_description[$l_id] = ep_4_curly_quotes($items[$filelayout['v_products_description_'.$l_id]]);
				if ($ep_supported_mods['psd'] == true) { // if short descriptions exist
					$v_products_short_desc[$l_id] = $items[$filelayout['v_products_short_desc_'.$l_id]];
				}
				$v_products_url[$l_id] = $items[$filelayout['v_products_url_'.$l_id]];
				// check products url length and display warning on error, but still process record
				if (mb_strlen($v_products_url[$l_id]) > $products_url_max_len) { 
					$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_PRODUCTS_URL_LONG, $v_products_model, $v_products_url[$l_id], $products_url_max_len);
					$ep_warning_count++;
				}
			} else { // column doesn't exist in the IMPORT file
				// and product is new
				if ($product_is_new) {
					// create language place holders
					$v_products_name[$l_id] = "";
					$v_products_description[$l_id] = "";
					// if short descriptions exist
					if ($ep_supported_mods['psd'] == true) {
						$v_products_short_desc[$_id] = "";
					}
					$v_products_url[$l_id] = "";	
				}
			}
		}

		// Note: 11-08-2011 this section needs careful review		
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

		// if $v_products_quantity is null, set it to: 0
		if (trim($v_products_quantity) == '') {
			$v_products_quantity = 0; // new products are set to with quanitity '0', updated products are set with default_these() values
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

		// deactivate zero quantity products, if configuration variable is true
		if (EASYPOPULATE_4_CONFIG_ZERO_QTY_INACTIVE == 'true' && $v_products_quantity == 0) {
			$v_db_status = '0';
		}

		// check for empty $v_products_image
		// if the v_products_image column exists and no image is set, then
		// apply the default "no image" image.
		if (trim($v_products_image) == '') {
			$v_products_image = PRODUCTS_IMAGE_NO_IMAGE;
		}

		// check size of v_products_model, loop on error
		if (mb_strlen($v_products_model) > $products_model_max_len) {
			$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_PRODUCTS_MODEL_LONG, $v_products_model, $products_model_max_len);
			$ep_error_count++;
			continue; // short-circuit on error
		}
		
		// BEGIN: Manufacturer's Name
		// convert the manufacturer's name into id's for the database
		if ( isset($v_manufacturers_name) && ($v_manufacturers_name != '') && (mb_strlen($v_manufacturers_name) <= $manufacturers_name_max_len) ) {
			$sql = "SELECT man.manufacturers_id AS manID FROM ".TABLE_MANUFACTURERS." AS man WHERE man.manufacturers_name = '".addslashes($v_manufacturers_name)."' LIMIT 1";
			$result = ep_4_query($sql);
			if ( $row = mysql_fetch_array($result) ) {
				$v_manufacturers_id = $row['manID']; // this id goes into the products table
			} else { // It is set to autoincrement, do not need to fetch max id
				$sql = "INSERT INTO ".TABLE_MANUFACTURERS." (manufacturers_name, date_added, last_modified)
					VALUES ('".addslashes($v_manufacturers_name)."', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
				$result = ep_4_query($sql);
				$v_manufacturers_id = mysql_insert_id(); // id is auto_increment, so can use this function
				
				// BUG FIX: TABLE_MANUFACTURERS_INFO need an entry for each installed language! chadd 11-14-2011
				// This is not a complete fix, since we are not importing manufacturers_url
				foreach ($langcode as $lang) {
					$l_id = $lang['id'];
					$sql = "INSERT INTO ".TABLE_MANUFACTURERS_INFO." (manufacturers_id, languages_id, manufacturers_url)
						VALUES ('".addslashes($v_manufacturers_id) . "',".(int)$l_id.",'')"; // seems we are skipping manufacturers url
					$result = ep_4_query($sql);
				}
			}
		} else { // $v_manufacturers_name == '' or name length violation
			if (mb_strlen($v_manufacturers_name) > $manufacturers_name_max_len) {
				$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_MANUFACTURER_NAME_LONG, $v_manufacturers_name, $manufacturers_name_max_len);
				$ep_error_count++;
				continue;
			}
			$v_manufacturers_id = 0; // chadd - zencart uses manufacturer's id = '0' for no assisgned manufacturer
		} // END: Manufacturer's Name
	
// BEGIN: CATEGORIES2 ===============================================================================================	

		$categories_name_exists = false; // assume no column defined
		foreach ($langcode as $key => $lang) {
			// test column headers for each language
			if (zen_not_null(trim($items[$filelayout['v_categories_name_'.$lang['id']]])) ) { // import column found
				$categories_name_exists = true; // at least one language column defined
			}
		}
		if ($categories_name_exists) { // we have at least 1 language column
			// chadd - 12-14-2010 - $categories_names_array[] has our category names
			$categories_delimiter = "^"; // add this to configuration variables
			// get all defined categories
			foreach ($langcode as $key => $lang) {
				
				// iso-8859-1
				// $categories_names_array[$lang['id']] = explode($categories_delimiter,$items[$filelayout['v_categories_name_'.$lang['id']]]); 
				// utf-8 
				$categories_names_array[$lang['id']] = mb_split('\x5e',$items[$filelayout['v_categories_name_'.$lang['id']]]); 
				
				// get the number of tokens in $categories_names_array[]
				$categories_count[$lang['id']] = count($categories_names_array[$lang['id']]);
 				// check category names for length violation. abort on error
				if ($categories_count[$lang['id']] > 0) { // only check $categories_name_max_len if $categories_count[$lang['id']] > 0
					for ( $category_index=0; $category_index<$categories_count[$lang['id']]; $category_index++ ) {
						if ( mb_strlen($categories_names_array[$lang['id']][$category_index]) > $categories_name_max_len ) {
							$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_NAME_LONG, 
								$v_products_model, $categories_names_array[$lang['id']][$category_index], $categories_name_max_len);
							$ep_error_count++;
							continue 3; // skip to next record
						}
					}
				}
			} // foreach
			
			// need check on $categories_count to ensure all counts are equal
			
			if (count($categories_count) > 1 ) { // check elements 
				$categories_count_value = $categories_count[$langcode[1]['id']];
				foreach ($langcode as $key => $lang) {
					$v_categories_name_check = 'v_categories_name_'.$lang['id']; 
					if (isset($$v_categories_name_check)) {
						if ( ($categories_count_value != $categories_count[$lang['id']]) && ($categories_count[$lang['id']] != 0) ) {
							//$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_NAME_LONG, 
							//$v_products_model, $categories_names_array[$lang['id']][$category_index], $categories_name_max_len);
							$display_output .= "<br>Error: Unbalanced Categories defined in: ".$items[$filelayout['v_categories_name_'.$lang['id']]];
							$ep_error_count++;
							continue 2; // skip to next record
						}
					}
				} // foreach
			}
			
		}
		// start with first defined language... (does not have to be 1)
		$lid = $langcode[1]['id'];	
		$v_categories_name_var = 'v_categories_name_'.$lid; // $$v_categories_name_var >> $v_categories_name_1, $v_categories_name_2, etc.
		if (isset($$v_categories_name_var)) { // does column header exist?
			// start from the highest possible category and work our way down from the parent
			$v_categories_id = 0;
			$theparent_id = 0; // 0 is top level parent
			// $categories_delimiter = "^"; // add this to configuration variables
			for ( $category_index=0; $category_index<$categories_count[$lid]; $category_index++ ) {
				$thiscategoryname = $categories_names_array[$lid][$category_index]; // category name
				$sql = "SELECT cat.categories_id
					FROM ".TABLE_CATEGORIES." AS cat, 
						 ".TABLE_CATEGORIES_DESCRIPTION." AS des
					WHERE
						cat.categories_id = des.categories_id AND
						des.language_id = ".$lid." AND
						cat.parent_id = ".$theparent_id." AND
						des.categories_name = '".addslashes($thiscategoryname)."' LIMIT 1";
				$result = ep_4_query($sql);
				$row = mysql_fetch_array($result);
				// if $row is not null, we found entry, so retrive info
				if ( $row != '' ) { // category exists
					foreach( $row as $item ) {
						$thiscategoryid = $item; // array of data
					}
					foreach ($langcode as $key => $lang2) {
						$v_categories_name_check = 'v_categories_name_'.$lang2['id']; 
						if (isset($$v_categories_name_check)) { // update
							$cat_lang_id = $lang2['id'];
							$sql = "UPDATE ".TABLE_CATEGORIES_DESCRIPTION." SET 
									categories_name = '".addslashes($categories_names_array[$cat_lang_id][$category_index])."'
								WHERE
									categories_id   = '".$thiscategoryid."' AND
									language_id     = '".$cat_lang_id."'";
							$result = ep_4_query($sql);
						}
					}
				} else { // otherwise add new category
					// get next available categoies_id
					$sql = "SELECT MAX(categories_id) max FROM ".TABLE_CATEGORIES;
					$result = ep_4_query($sql);
					$row = mysql_fetch_array($result);
					$max_category_id = $row['max']+1;
					// if database is empty, start at 1
					if (!is_numeric($max_category_id) ) {
						$max_category_id=1;
					}
					// TABLE_CATEGORIES has 1 entry per categories_id
					$sql = "INSERT INTO ".TABLE_CATEGORIES."(categories_id, categories_image, parent_id, sort_order, date_added, last_modified
						) VALUES (
						$max_category_id, '', $theparent_id, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
						)";						
					$result = ep_4_query($sql);
					// TABLE_CATEGORIES_DESCRIPTION has an entry for EACH installed languag
					// Check for multiple categories language. If a column is not defined, default to the main language:
					// categories_name = '".addslashes($thiscategoryname)."'";
					// else, set to that langauges category entry:
					// categories_name = '".addslashes($categories_names_array[$lid][$category_index])."'";
					foreach ($langcode as $key => $lang2) {
						$v_categories_name_check = 'v_categories_name_'.$lang2['id']; 
						if (isset($$v_categories_name_check)) { // update	
							$cat_lang_id = $lang2['id'];
							$sql = "INSERT INTO ".TABLE_CATEGORIES_DESCRIPTION." SET 
								categories_id   = '".$max_category_id."',
								language_id     = '".$cat_lang_id."',
								categories_name = '".addslashes($categories_names_array[$cat_lang_id][$category_index])."'";
						} else { // column is missing, so default to defined column's value
							$cat_lang_id = $lang2['id'];
							$sql = "INSERT INTO ".TABLE_CATEGORIES_DESCRIPTION." SET 
								categories_id   = '".$max_category_id."',
								language_id     = '".$cat_lang_id."',
								categories_name = '".addslashes($thiscategoryname)."'";
						}	
						$result = ep_4_query($sql);
					}
					$thiscategoryid = $max_category_id;
				}
				// the current catid is the next level's parent
				$theparent_id = $thiscategoryid;
				// keep setting this, we need the lowest level category ID later
				$v_categories_id = $thiscategoryid; 
			} // ( $category_index=0; $category_index<$catego.....
		} // (isset($$v_categories_name_var))
// END: CATEGORIES2 ===============================================================================================	
		
		// insert new, or update existing, product
		if ($v_products_model != "") { // products_model exists!
			// First we check to see if this is a product in the current db.
			$result = ep_4_query("SELECT products_id FROM ".TABLE_PRODUCTS." WHERE (products_model = '".addslashes($v_products_model)."') LIMIT 1");
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
				
				$query = "INSERT INTO ".TABLE_PRODUCTS." SET
					products_model					= '".addslashes($v_products_model)."',
					products_price					= '".$v_products_price."',";
				if ($ep_supported_mods['uom'] == true) { // price UOM mod
					$query .= "products_price_uom = '".$v_products_price_uom."',"; 
				} 
				if ($ep_supported_mods['upc'] == true) { // UPC Code mod
					$query .= "products_upc = '".addslashes($v_products_upc)."',";
				}
				if ($ep_supported_mods['gpc'] == true) { // Google Product Category for Google Merhcant Center - chadd 10-1-2011
					$query .= "products_gpc = '".addslashes($v_products_gpc)."',";
				}
				if ($ep_supported_mods['msrp'] == true) { // Manufacturer's Suggested Retail Price
					$query .= "products_msrp = '".$v_products_msrp."',";
				}
				$query .= "products_image			= '".addslashes($v_products_image)."',
					products_weight					= '".$v_products_weight."',
					products_discount_type          = '".$v_products_discount_type."',
					products_discount_type_from     = '".$v_products_discount_type_from."',
					product_is_call                 = '".$v_product_is_call."',
					products_sort_order             = '".$v_products_sort_order."',
					products_quantity_order_min     = '".$v_products_quantity_order_min."',
					products_quantity_order_units   = '".$v_products_quantity_order_units."',
					products_tax_class_id			= '".$v_tax_class_id."',
					products_date_available			= $v_date_avail, 
					products_date_added				= $v_date_added,
					products_last_modified			= CURRENT_TIMESTAMP,
					products_quantity				= '".$v_products_quantity."',
					master_categories_id			= '".$v_categories_id."',
					manufacturers_id				= '".$v_manufacturers_id."',
					products_status					= '".$v_db_status."',
					metatags_title_status			= '".$v_metatags_title_status."',
					metatags_products_name_status	= '".$v_metatags_products_name_status."',
					metatags_model_status			= '".$v_metatags_model_status."',
					metatags_price_status			= '".$v_metatags_price_status."',
					metatags_title_tagline_status	= '".$v_metatags_title_tagline_status."'";	

				$result = ep_4_query($query);
				if ($result == true) {
					// need to change to an log file, this is gobbling up memory! chadd 11-14-2011
					if ($ep_feedback == true) {
						$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_NEW_PRODUCT, $v_products_model);
					}
					$ep_import_count++;
				} else {
					$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_NEW_PRODUCT_FAIL, $v_products_model);
					$ep_error_count++;
					continue; // new categories however have been created by now... Adding into product table needs to be 1st action?
				}
				// needs to go into log file chadd 11-14-2011
				if ($ep_feedback == true) {
					foreach ($items as $col => $summary) {
						if ($col == $filelayout['v_products_model']) continue;
						$display_output .= print_el_4($summary);
					}
				}
				
			} else { // existing product, get the id from the query and update the product data
				// if date added is null, let's keep the existing date in db..
				$v_date_added = ($v_date_added == 'NULL') ? $row['v_date_added'] : $v_date_added; // if NULL, use date in db
				$v_date_added = zen_not_null($v_date_added) ? $v_date_added : CURRENT_TIMESTAMP; // if updating, but date added is null, we use today's date
				$row = mysql_fetch_array($result);
				$v_products_id = $row['products_id'];
				$row = mysql_fetch_array($result); 
				// CHADD - why is master_categories_id not being set on update???
				$query = "UPDATE ".TABLE_PRODUCTS." SET
					products_price = '".$v_products_price."',";
					
				if ($ep_supported_mods['uom'] == true) { // price UOM mod
					$query .= "products_price_uom = '".$v_products_price_uom."',"; 
				} 
				if ($ep_supported_mods['upc'] == true) { // UPC Code mod
					$query .= "products_upc = '".addslashes($v_products_upc)."',";
				}
				if ($ep_supported_mods['gpc'] == true) { // Google Product Category for Google Merhcant Center - chadd 10-1-2011
					$query .= "products_gpc = '".addslashes($v_products_gpc)."',";
				}
				if ($ep_supported_mods['msrp'] == true) { // Manufacturer's Suggested Retail Price
					$query .= "products_msrp = '".$v_products_msrp."',";
				}
				$query .= "products_image			= '".addslashes($v_products_image)."',
					products_weight					= '".$v_products_weight."',
					products_discount_type			= '".$v_products_discount_type."',
					products_discount_type_from		= '".$v_products_discount_type_from."',
					product_is_call					= '".$v_product_is_call."',
					products_sort_order				= '".$v_products_sort_order."',
					products_quantity_order_min		= '".$v_products_quantity_order_min."',
					products_quantity_order_units	= '".$v_products_quantity_order_units."',				
					products_tax_class_id			= '".$v_tax_class_id."',
					products_date_available			= $v_date_avail,
					products_date_added				= $v_date_added,
					products_last_modified			= CURRENT_TIMESTAMP,
					products_quantity				= '".$v_products_quantity."',
					manufacturers_id				= '".$v_manufacturers_id."',
					products_status					= '".$v_db_status."',
					metatags_title_status			= '".$v_metatags_title_status."',
					metatags_products_name_status	= '".$v_metatags_products_name_status."',
					metatags_model_status			= '".$v_metatags_model_status."',
					metatags_price_status			= '".$v_metatags_price_status."',
					metatags_title_tagline_status	= '".$v_metatags_title_tagline_status."'".
					" WHERE (products_id = '".$v_products_id."')";
				
				$result = ep_4_query($query);
				if ($result == true) {
					// needs to go into a log file chadd 11-14-2011
					if ($ep_feedback == true) {
					$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_UPDATE_PRODUCT, $v_products_model);
						foreach ($items as $col => $summary) {
							if ($col == $filelayout['v_products_model']) continue;
							$display_output .= print_el_4($summary);
						}
					}
					$ep_update_count++;				
				} else {
					$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_UPDATE_PRODUCT_FAIL, $v_products_model);
					$ep_error_count++;
				}
			}
			
			// START: Product MetaTags
			if (isset($v_metatags_title)){
				foreach ( $v_metatags_title as $key => $metaData ) {
					$sql = "SELECT products_id FROM ".TABLE_META_TAGS_PRODUCTS_DESCRIPTION." WHERE 
						(products_id = '$v_products_id' AND language_id = '$key') LIMIT 1 ";
					$result = ep_4_query($sql);
					if ($row = mysql_fetch_array($result)) {
						// UPDATE
						$sql = "UPDATE ".TABLE_META_TAGS_PRODUCTS_DESCRIPTION." SET 
							metatags_title = '".addslashes($v_metatags_title[$key])."',
							metatags_keywords = '".addslashes($v_metatags_keywords[$key])."',
							metatags_description = '".addslashes($v_metatags_description[$key])."'
							WHERE (products_id = '".$v_products_id."' AND language_id = '".$key."')";
					} else {
						// NEW
						$sql = "INSERT INTO ".TABLE_META_TAGS_PRODUCTS_DESCRIPTION." SET 
							metatags_title = '".addslashes($v_metatags_title[$key])."',
							metatags_keywords = '".addslashes($v_metatags_keywords[$key])."',
							metatags_description = '".addslashes($v_metatags_description[$key])."',
							products_id = '".$v_products_id."',
							language_id = '".$key."'";
					}
					$result = ep_4_query($sql);
				}
			} // END: Products MetaTags
			

		
			// chadd: use this command to remove all old discount entries.
			// $db->Execute("delete from ".TABLE_PRODUCTS_DISCOUNT_QUANTITY." where products_id = '".(int)$v_products_id."'");
			
			// this code does not check for existing quantity breaks, it simply updates or adds them. No algorithm for removal.
			// update quantity price breaks - chadd
			// 9-29-09 - changed to while loop to allow for more than 3 discounts
			// 9-29-09 - code has test well for first run through.
			// initialize variables

// 12-6-2010 problem identified: when uploading Model/Price/Qty to set Specials Pricing, quantity discounts are being wiped.
// so only execute this code if uploading PriceBreaks
if ( substr($file['name'],0,14) == "PriceBreaks-EP") {

			if ($v_products_discount_type != '0') { // if v_products_discount_type == 0 then there are no quantity breaks
				if ($v_products_model != "") { // we check to see if this is a product in the current db, must have product model number
					$result = ep_4_query("SELECT products_id FROM ".TABLE_PRODUCTS." WHERE (products_model = '".addslashes($v_products_model)."') LIMIT 1");
					if (mysql_num_rows($result) != 0)  { // found entry
						$row3 =  mysql_fetch_array($result);
						$v_products_id = $row3['products_id'];

						// remove all old associated quantity discounts
						// this simplifies the below code to JUST insert all the new values
						$db->Execute("DELETE FROM ".TABLE_PRODUCTS_DISCOUNT_QUANTITY." WHERE products_id = '".(int)$v_products_id."'");

						// initialize quantity discount variables
						$xxx = 1;
						// $v_discount_id_var    = 'v_discount_id_'.$xxx ; // this column is now redundant
						$v_discount_qty_var   = 'v_discount_qty_'.$xxx;
						$v_discount_price_var = 'v_discount_price_'.$xxx;
						while (isset($$v_discount_qty_var)) { 
							// INSERT price break
							if ($$v_discount_price_var != "") { // check for empty price
								$sql = "INSERT INTO ".TABLE_PRODUCTS_DISCOUNT_QUANTITY." SET
									products_id    = '".$v_products_id."',
									discount_id    = '".$xxx."',
									discount_qty   = '".$$v_discount_qty_var."',
									discount_price = '".$$v_discount_price_var."'";
								$result = ep_4_query($sql);
							} // end: check for empty price
							$xxx++; 
							$v_discount_qty_var   = 'v_discount_qty_'.$xxx;
							$v_discount_price_var = 'v_discount_price_'.$xxx;
						} // while (isset($$v_discount_id_var)
			
					} // end: if (row count <> 0) found entry
				} // if ($v_products_model)
			} else { // products_discount_type == 0, so remove any old quantity_discounts
				if ($v_products_model != "") { // we check to see if this is a product in the current db, must have product model number
					$result = ep_4_query("SELECT products_id FROM ".TABLE_PRODUCTS." WHERE (products_model = '".addslashes($v_products_model)."') LIMIT 1");
					if (mysql_num_rows($result) != 0)  { // found entry
						$row3 =  mysql_fetch_array($result);
						$v_products_id = $row3['products_id'];
						// remove all associated quantity discounts
						$db->Execute("DELETE FROM ".TABLE_PRODUCTS_DISCOUNT_QUANTITY." WHERE products_id = '".(int)$v_products_id."'");
					}
				}
			} // if ($v_products_discount_type != '0')
}	
			
			
			// BEGIN: Products Descriptions
			// the following is common in both the updating an existing product and creating a new product
			if (isset($v_products_name)) {
				foreach( $v_products_name as $key => $name) {
					
					
					// if ($name != '') {
					
					
						$sql = "SELECT * FROM ".TABLE_PRODUCTS_DESCRIPTION." WHERE
								products_id = ".$v_products_id." AND
								language_id = ".$key;
						$result = ep_4_query($sql);
						
						if (mysql_num_rows($result) == 0) {
							// nope, this is a new product description
							/*
							$sql = "INSERT INTO ".TABLE_PRODUCTS_DESCRIPTION." SET
									products_id          ='".$v_products_id."',
									language_id          ='".$key.",
									products_name        ='".addslashes($name)."',
									products_description ='".addslashes($v_products_description[$key])."',";
							if ($ep_supported_mods['psd'] == true) {
								$sql .= "products_short_desc ='".addslashes($v_products_short_desc[$key])."',";
							}
							$sql .= " products_url = '".addslashes($v_products_url[$key])."'";
							*/
							
							// old way of sql 
							$sql = "INSERT INTO ".TABLE_PRODUCTS_DESCRIPTION." (
									products_id,
									language_id,
									products_name,
									products_description,";
							if ($ep_supported_mods['psd'] == true) {
								$sql .= " products_short_desc,";
							}
							$sql .= " products_url )
									VALUES (
										'".$v_products_id."',
										".$key.",
										'".addslashes($name)."',
										'".addslashes($v_products_description[$key])."',";
							if ($ep_supported_mods['psd'] == true) {
								$sql .= "'".addslashes($v_products_short_desc[$key])."',";
							}
							$sql .= "'".addslashes($v_products_url[$key])."')";
							$result = ep_4_query($sql);
						} else { // already in the description, update it
							$sql = "UPDATE ".TABLE_PRODUCTS_DESCRIPTION." SET
									products_name        ='".addslashes($name)."',
									products_description ='".addslashes($v_products_description[$key])."',";
							if ($ep_supported_mods['psd'] == true) {
								$sql .= " products_short_desc = '".addslashes($v_products_short_desc[$key])."',";
							}
							$sql .= " products_url='".addslashes($v_products_url[$key])."'
								WHERE products_id = '".$v_products_id."' AND language_id = '".$key."'";
							$result = ep_4_query($sql);
						}
					
					// } // if ($name != '')
				
				
				}		
			} // END: Products Descriptions End
			

//==================================================================================================================================			
			// Assign product to category if linked
			// chadd - need to dig into further
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
								VALUES ("'.$v_products_id.'", "'.$v_categories_id.'")');
				} else { // already in this category, nothing to do!
				}
			}
//==================================================================================================================================			

			
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
				$has_specials = true;
				
				// using new date functions - chadd
				$v_specials_date_avail = ($v_specials_date_avail == true) ? date("Y-m-d H:i:s",strtotime($v_specials_date_avail)) : "0001-01-01";
				$v_specials_expires_date = ($v_specials_expires_date == true) ? date("Y-m-d H:i:s",strtotime($v_specials_expires_date)) : "0001-01-01";
				
				// Check if this product already has a special
				$special = ep_4_query("SELECT products_id FROM ".TABLE_SPECIALS." WHERE products_id = ".$v_products_id);
																
				if (mysql_num_rows($special) == 0) { // not in db
					if ($v_specials_price == '0') { // delete requested, but is not a special
						$specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_DELETE_FAIL, $v_products_model, substr(strip_tags($v_products_name[$epdlanguage_id]), 0, 10));
						continue;
					}
					// insert new into specials
					$sql = "INSERT INTO ".TABLE_SPECIALS."
						(products_id,
						specials_new_products_price,
						specials_date_added,
						specials_date_available,
						expires_date,
						status)
						VALUES (
						'".(int)$v_products_id."',
						'".$v_specials_price."',
						now(),
						'".$v_specials_date_avail."',
						'".$v_specials_expires_date."',
						'1')";
					$result = ep_4_query($sql);
					$specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_NEW, $v_products_model, substr(strip_tags($v_products_name[$epdlanguage_id]), 0, 10), $v_products_price , $v_specials_price);
				} else { // existing product
					if ($v_specials_price == '0') { // delete of existing requested
						$db->Execute("DELETE FROM ".TABLE_SPECIALS." WHERE products_id = '".(int)$v_products_id."'");
						$specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_DELETE, $v_products_model);
						continue;
					}
					// just make an update
					$sql = "UPDATE ".TABLE_SPECIALS." SET
						specials_new_products_price	= '".$v_specials_price."',
						specials_last_modified		= now(),
						specials_date_available		= '".$v_specials_date_avail."',
						expires_date				= '".$v_specials_expires_date."',
						status						= '1'
						WHERE products_id			= '".(int)$v_products_id."'";
					ep_4_query($sql);
					$specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_UPDATE, $v_products_model, substr(strip_tags($v_products_name[$epdlanguage_id]), 0, 10), $v_products_price , $v_specials_price);
				} // we still have our special here
			} // end specials for this product
			
			// this is a test chadd - 12-08-2011
			// why not just update price_sorter after each product?
			// better yet, why not ONLY call if pricing was updated
			// ALL these affect pricing: products_tax_class_id, products_price, products_priced_by_attribute, product_is_free, product_is_call
			zen_update_products_price_sorter($v_products_id);
			
		} else {
			// this record is missing the product_model
			$display_output .= EASYPOPULATE_4_DISPLAY_RESULT_NO_MODEL;
			foreach ($items as $col => $summary) {
				if ($col == $filelayout['v_products_model']) continue;
				$display_output .= print_el_4($summary);
			}
		} // end of row insertion code
	} // end of Mail While Loop
	} // conditional IF statement
	
	$display_output .= '<h3>Finished Processing Import File</h3>';
	
	$display_output .= '<br>Updated records: '.$ep_update_count;
	$display_output .= '<br>New Imported records: '.$ep_import_count;
	$display_output .= '<br>Errors Detected: '.$ep_error_count;
	$display_output .= '<br>Warnings Detected: '.$ep_warning_count;
	
	$display_output .= '<br>Memory Usage: '.memory_get_usage(); 
	$display_output .= '<br>Memory Peak: '.memory_get_peak_usage();
	
	// benchmarking
	$time_end = microtime(true);
	$time = $time_end - $time_start;	
	$display_output .= '<br>Execution Time: '. $time . ' seconds.';
}	
	
	// specials status = 0 if date_expires is past.
	// HEY!!! THIS ALSO CALLS zen_update_products_price_sorter($v_products_id); !!!!!!
	if ($has_specials == true) { // specials were in upload so check for expired specials
		zen_expire_specials();
	}
} // END FILE UPLOADS
?>