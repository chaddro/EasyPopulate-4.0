<?php
// BEGIN: Data Import Module
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
	// otherwise these would have to be exported/imported every time
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

	$file_location = DIR_FS_CATALOG . $tempdir . $file['name'];
	
	// if no error, retreive header row
	if (!file_exists($file_location)) {
		$display_output .="<b>ERROR: file doesn't exist</b>";
	} else if ( !($handle = fopen($file_location, "r"))) {
		$display_output .="<b>ERROR: Can't open file</b>";
	} else if($filelayout = array_flip(fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure))) { // read header row
	
/////////
/////////	
	
	// Attributes Import
	if ( substr($file['name'],0,9) == "Attrib-EP") {
		require_once('easypopulate_4_attrib.php');
	} // Attributes Import	

////////
////////

	// This Category MetaTags import routine only deals with existing Categories. It does not create or modify the tree.
	// This code is used to Edit Categories image, description, and metatags data only.
	if ( substr($file['name'],0,15) == "CategoryMeta-EP") {
		while ($items = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) { // read 1 line of data
			// $items[$filelayout['v_categories_id']];
			// $items[$filelayout['v_categories_image']];
			$sql = "SELECT `categories_id` FROM ".TABLE_CATEGORIES." WHERE (`categories_id` = ".$items[$filelayout['v_categories_id']]." )";
			$result = ep_4_query($sql);
			if ($row = mysql_fetch_array($result)) {
				// UPDATE
				$sql = "UPDATE ".TABLE_CATEGORIES." SET 
					`categories_image` = '".zen_db_input($items[$filelayout['v_categories_image']])."',
					`last_modified`    = CURRENT_TIMESTAMP 
					WHERE 
					(`categories_id` = ".$items[$filelayout['v_categories_id']]." ) ";
				$result = ep_4_query($sql);

				foreach ($langcode as $key => $lang) {
					$lid = $lang['id'];
					// $items[$filelayout['v_categories_name_'.$lid]];
					// $items[$filelayout['v_categories_description_'.$lid]];
					$sql = "UPDATE ".TABLE_CATEGORIES_DESCRIPTION." SET 
						`categories_name`        = '".zen_db_input($items[$filelayout['v_categories_name_'.$lid]])."',
						`categories_description` = '".zen_db_input($items[$filelayout['v_categories_description_'.$lid]])."'
						WHERE 
						(`categories_id` = ".$items[$filelayout['v_categories_id']]." AND `language_id` = ".$lid.")";
					$result = ep_4_query($sql);
				
					// $items[$filelayout['v_metatags_title_'.$lid]];
					// $items[$filelayout['v_metatags_keywords_'.$lid]];
					// $items[$filelayout['v_metatags_description_'.$lid]];
					// Categories Meta Start
					$sql = "SELECT `categories_id` FROM ".TABLE_METATAGS_CATEGORIES_DESCRIPTION." WHERE (`categories_id` =  ".$items[$filelayout['v_categories_id']]." AND `language_id` = ".$lid.")";
					$result = ep_4_query($sql);
					if ($row = mysql_fetch_array($result)) {
						// UPDATE
						$sql = "UPDATE ".TABLE_METATAGS_CATEGORIES_DESCRIPTION." SET 
						`metatags_title`		= '".zen_db_input($items[$filelayout['v_metatags_title_'.$lid]])."',
						`metatags_keywords`		= '".zen_db_input($items[$filelayout['v_metatags_keywords_'.$lid]])."',
						`metatags_description`	= '".zen_db_input($items[$filelayout['v_metatags_description_'.$lid]])."'
						WHERE 
						(`categories_id` = ".$items[$filelayout['v_categories_id']]." AND `language_id` = ".$lid.")";
					} else {
						// NEW
						$sql = "INSERT INTO ".TABLE_METATAGS_CATEGORIES_DESCRIPTION." SET 
							`metatags_title`		= '".zen_db_input($items[$filelayout['v_metatags_title_'.$lid]])."',
							`metatags_keywords`		= '".zen_db_input($items[$filelayout['v_metatags_keywords_'.$lid]])."',
							`metatags_description`	= '".zen_db_input($items[$filelayout['v_metatags_description_'.$lid]])."',
							`categories_id`			= '".$items[$filelayout['v_categories_id']]."',
							`language_id` 			= '$lid' ";
					}
					$result = ep_4_query($sql);					
				} 
			} else {
				// error
				die("Category ID not Found");
			} // if category found
		} // while
	} // if

if ((substr($file['name'],0,15) <> "CategoryMeta-EP") && (substr($file['name'],0,9) <> "Attrib-EP")){ //  temporary solution here... 12-06-2010
	
	// Main IMPORT loop For Product Related Data. v_products_id is the main key here.
	while ($items = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) { // read 1 line of data
	
		// now do a query to get the record's current contents
		// chadd - 12-14-2010 - redefining this variable everytime it loops must be very inefficient! must be a better way!
		$sql = 'SELECT
			p.products_id					as v_products_id,
			p.products_model				as v_products_model,
			p.products_image				as v_products_image,
			p.products_price				as v_products_price,';
			
		if ($ep_supported_mods['uom'] == true) { // price UOM mod - chadd
			$sql .= 'p.products_price_uom as v_products_price_uom,'; // chadd
		} 
		if ($ep_supported_mods['upc'] == true) { // UPC Code mod- chadd
			$sql .= 'p.products_upc as v_products_upc,'; 
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
		
		// this appears to get default values for current v_products_model
		// inputs: $items array (file data by column #); $filelayout array (headings by column #); 
		// $row (current TABLE_PRODUCTS data by heading name)
		while ( $row = mysql_fetch_array($result) ) { // chadd - this executes once?? why use while-loop??
			$product_is_new = false;
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
			
			//==================================================================================================================================			
			// Categories start
			// start with v_categories_id
			// Get the category description
			// set the appropriate variable name
			// if parent_id is not null, then follow it up.
			// chadd - this code ONLY works with the DEFAULT language id ($epdlanguage_id) set earlier

/* what the hell is going on here?????
			
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
*/			
			/** Categories path for existing product retrieved from db in $row array */
			//==================================================================================================================================			
			
			/** Retrieve current manufacturer name from db for this product if exist */
			if (($row['v_manufacturers_id'] != '0') && ($row['v_manufacturers_id'] != '')) { // '0' or '' (NULL), no manufacturer set
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
		} // while ( $row = mysql_fetch_array($result) )
		

		// langer - We have now set our PRODUCT_TABLE vars for existing products, and got our default descriptions & categories in $row still
		// new products start here!

		// langer - let's have some data error checking
		// inputs: $items; $filelayout; $product_is_new (no reliance on $row)
		// chadd - this first condition cannot exist since we short-circuited on delete above
		if ($items[$filelayout['v_status']] == 9 && zen_not_null($items[$filelayout['v_products_model']])) {
			// new delete got this far, so cant exist in db. Cant delete what we don't have...
			$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_DELETE_NOT_FOUND, $items[$filelayout['v_products_model']]);
			continue;
		}
		// a NEW product must have a category to go into, else error out
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
		// The assumption is that if you give us names and descriptions, then you give us name and description for all applicable languages (FALSE!!)
		// chadd - 12-13-2010 - this should allow you to update descriptions only for any given language!
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

// CATEGORIES ==================================================================================================================================			
		// if they give us one category, they give us all 6 categories
		// langer - this does not appear to support more than 7 categories??
		// some kind of error checking
		
/*		
		unset ($v_categories_name); // default to not set.
		
		if (isset($filelayout['v_categories_name_1'])) { // does category 1 column exist in our file..
			$category_strlen_long = FALSE;// checks cat length does not exceed db, else exclude product from upload
			$newlevel = 1;
			for($categorylevel=6; $categorylevel>0; $categorylevel--) { // chadd - error: $categorylevel here should not be 6, but max_categories-1
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
*/		
		
// CATEGORIES ==================================================================================================================================			
		
		// if $v_products_quantity is null, set it to: 0
		if (trim($v_products_quantity) == '') {
			$v_products_quantity = 0; // new products are set to with quanitity '0', updated products are get default_these() values
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
		// check for empty $v_products_image
		if (trim($v_products_image) == '') {
			$v_products_image = PRODUCTS_IMAGE_NO_IMAGE;
		}
		// check size of v_products_model
		if (strlen($v_products_model) > $modelsize) {
			$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_MODEL_NAME_LONG, $v_products_model);
			continue; // short-circuit on error
		}
		
		// BEGIN: Manufacturer's Name
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
					$v_manufacturers_id = mysql_insert_id(); // id is auto_increment, so can use this function
				}
			} else { // $v_manufacturers_name == ''
				$v_manufacturers_id = 0; // chadd - zencart uses manufacturer's id = '0' for no assisgned manufacturer
			} // END: Manufacturer's Name
	
// CATEGORIES ==================================================================================================================================	
//
// NOTE: I'LL TOTALLY SHIT MYSELF IF THIS WORKS FIRST TIME ROUND! LOL! :)
//		
		// 12-10-2010 This code incomplete. It does not support multiple language and requires too many columns
		// Instead, a single cell should have the category path with a delimiter, then multiple languages could be implemented. 
		// if the categories names are set then try to update them
		
		// echo '<br>Entering Categories: '.$v_categories_name_1.'<br>';
		
		
		if (isset($v_categories_name_1)) {
			// start from the highest possible category and work our way down from the parent
			$v_categories_id = 0;
			$theparent_id = 0; // 0 is top level parent
			//
			// chadd - 12-14-2010 - $values_name[] has our categories (array index starts at zero!)
			//
			$categories_delimiter = '^';
//			$values_names = explode($categories_delimiter,$v_categories_name_1);
			$categories_names_array = explode('^',$v_categories_name_1);
		//	print_r($categories_names_array);
			$categories_count = count($categories_names_array);
		//	echo '<br>categories_count: '.$categories_count.'<br>Now print walk of array:<br>';

			// test walk of array
		//	for ( $category_index=0; $category_index<$categories_count; $category_index++ ) {
		//		$thiscategoryname = $categories_names_array[$category_index]; // category name
		//		echo '<br>'.$thiscategoryname.' -> '.$category_index;
		//	}			
			
			for ( $category_index=0; $category_index<$categories_count; $category_index++ ) {
				$thiscategoryname = $categories_names_array[$category_index]; // category name
			
				// note: use of $eplanguage_id is "contant" 1 at this time
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
				// if $row is not null, we found entry, so retrive info
				if ( $row != '' ) {
					foreach( $row as $item ) {
						$thiscategoryid = $item; // array of data
					}
				// otherwise add new category 
				} else { 
					// get next available categoies_id
					$sql = "SELECT MAX(categories_id) max FROM ".TABLE_CATEGORIES;
					$result = ep_4_query($sql);
					$row = mysql_fetch_array($result);
					$max_category_id = $row['max']+1;
					// if database is empty, start at 1
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
				// keep setting this, we need the lowest level category ID later
				$v_categories_id = $thiscategoryid; 
			}
		}
// CATEGORIES ==================================================================================================================================			

		
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
			
			// START: Product MetaTags
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
			} // END: Products MetaTags
			

		
			// chadd: use this command to remove all old discount entries.
			// $db->Execute("delete from " . TABLE_PRODUCTS_DISCOUNT_QUANTITY . " where products_id = '" . (int)$v_products_id . "'");
			
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
}	
			
			
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
			

//==================================================================================================================================			
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
	} // end of Mail While Loop
	} // conditional IF statement
	
	$display_output .= EASYPOPULATE_4_DISPLAY_RESULT_UPLOAD_COMPLETE;
}	
	
	// Post-upload tasks start
	ep_4_update_prices(); // update price sorter
	
	// specials status = 0 if date_expires is past.
	if ($has_specials == true) { // specials were in upload so check for expired specials
		zen_expire_specials();
	}
	// Post-upload tasks end
} // END FILE UPLOADS

?>