<?php
// get download type
$ep_dltype = (isset($_GET['dltype'])) ? $_GET['dltype'] : $ep_dltype;

// BEGIN: File Download Layouts
// if dltype is set, then create the filelayout.  Otherwise filelayout is read from the uploaded file.
// depending on the type of the download the user wanted, create a file layout for it.
if (zen_not_null($ep_dltype)) {
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
	if ($_GET['ep_manufacturer_filter']!='') { $sql_filter .= ' and p.manufacturers_id = ' . (int)$_GET['ep_manufacturer_filter']; }
	if ($_GET['ep_status_filter']!='') { $sql_filter .= ' AND p.products_status = ' . (int)$_GET['ep_status_filter']; }
	if ($_GET['dltype']!='') { $ep_dltype = $_GET['dltype']; }

	$filelayout = array();
	$fileheaders = array();
	
	$filelayout_sql = '';
	
$time_start = microtime(true);
 
	$filelayout = ep_4_set_filelayout($ep_dltype,  $filelayout_sql, $sql_filter, $langcode, $ep_supported_mods); 

// $time_end = microtime(true);
// $time = $time_end - $time_start;

	$filelayout = array_flip($filelayout);
	$fileheaders = array_flip($fileheaders);
} // END: File Download Layouts

$ep_dlmethod = isset($_GET['download']) ? $_GET['download'] : $ep_dlmethod;
if ($ep_dlmethod == 'stream' or  $ep_dlmethod == 'tempfile') { // DOWNLOAD FILE
	$filestring	= ""; // file name
	$result		= ep_4_query($filelayout_sql);

	// 09-30-09 - chadd - no custom header mapping code elsewhere, just use:
	$filelayout_header = $filelayout; 
	
	// prepare the table heading with layout values
	foreach( $filelayout_header as $key => $value ) {
		$filestring .= $key . $csv_delimiter;
	}
	// Remove trailing tab
	$filestring = substr($filestring, 0, strlen($filestring)-1);
	
	// one record per line, end with newline
	$filestring .= "\n"; 
	
	while ($row = mysql_fetch_array($result)) {
	
		// PRODUCTS IMAGE
		if (isset($filelayout['v_products_image'])) { 
			$products_image = (($row['v_products_image'] == PRODUCTS_IMAGE_NO_IMAGE) ? '' : $row['v_products_image']);
		}
		
		// LANGUAGES AND DESCRIPTIONS
		if ($ep_dltype == 'full') {
			// names and descriptions require that we loop thru all languages that are turned on in the store
			foreach ($langcode as $key => $lang) {
				$lid = $lang['id'];
				// metaData start
				$sqlMeta = 'SELECT * FROM '.TABLE_META_TAGS_PRODUCTS_DESCRIPTION.' WHERE products_id = '.$row['v_products_id'].' AND language_id = '.$lid.' LIMIT 1 ';
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
		
		// CATEGORIES LANGUAGE, DESCRIPTIONS AND MetaTags added 12-02-2010
		if ($ep_dltype == 'categorymeta') {
			// names and descriptions require that we loop thru all languages that are turned on in the store
			foreach ($langcode as $key => $lang) {
				$lid = $lang['id'];
				// metaData start
				$sqlMeta = 'SELECT * FROM '.TABLE_METATAGS_CATEGORIES_DESCRIPTION.' WHERE categories_id = '.$row['v_categories_id'].' AND language_id = '.$lid.' LIMIT 1 ';
				$resultMeta = ep_4_query($sqlMeta) or die(mysql_error());
				$rowMeta    = mysql_fetch_array($resultMeta);
				$row['v_metatags_title_' . $lid]       = $rowMeta['metatags_title'];
				$row['v_metatags_keywords_' . $lid]    = $rowMeta['metatags_keywords'];
				$row['v_metatags_description_' . $lid] = $rowMeta['metatags_description'];
				// metaData end
				// for each language, get category description and name
				$sql2    = 'SELECT * FROM '.TABLE_CATEGORIES_DESCRIPTION.' WHERE categories_id = '.$row['v_categories_id'].' AND language_id = '.$lid.' LIMIT 1 ';
				$result2 = ep_4_query($sql2);
				$row2    = mysql_fetch_array($result2);
				$row['v_categories_name_'.$lid]        = $row2['categories_name'];
				$row['v_categories_description_'.$lid] = $row2['categories_description'];
			} // foreach
		} // if ($ep_dltype ...
		
		
		// CATEGORIES
		// chadd - 12-13-2010 - logic change. $max_categories no longer required. better to loop back to root category and 
		// concatenate the entire categories path into one string with $category_delimiter for separater.
		if ( ($ep_dltype == 'full') OR ($ep_dltype == 'category') ) { // chadd - 12-02-2010 fixed error: missing parenthesis
			// NEW While-loop for unlimited category depth			
			$category_delimiter = "^";
			$thecategory_id = $row['v_categories_id']; // starting category_id
		  
			// $fullcategory = array(); // this will have the entire category path separated by $category_delimiter
			// if parent_id is not null ('0'), then follow it up.
			while (!empty($thecategory_id)) {
				// mult-lingual categories start - for each language, get category description and name
				  $sql2 =  'SELECT * FROM '.TABLE_CATEGORIES_DESCRIPTION.' WHERE categories_id = '.$thecategory_id.' ORDER BY language_id';
				  $result2 = ep_4_query($sql2);
				while ($row2 = mysql_fetch_array($result2)) {
					$lid = $row2['language_id'];
					$row['v_categories_name_'.$lid] = $row2['categories_name'].$category_delimiter.$row['v_categories_name_'.$lid];
				}
			
				// this is an alternative way to accomplilsh the same thing, however, it is on average 2 seconds long than above code
				// on export of store with 2 languages installed over 3300 records!	
				/*
				foreach ($langcode as $key => $lang) {
					$lid = $lang['id'];
					// mult-lingual categories start - for each language, get category description and name
					$sql2    = 'SELECT * FROM '.TABLE_CATEGORIES_DESCRIPTION.' WHERE categories_id = '.$thecategory_id.' AND language_id = '.$lid.' LIMIT 1 ';
					$result2 = ep_4_query($sql2);
					$row2    = mysql_fetch_array($result2);
					$row['v_categories_name_'.$lid] = $row2['categories_name'].$category_delimiter.$row['v_categories_name_'.$lid];
				} // foreach
				*/
			
				// look for parent categories ID
				$sql3 = 'SELECT parent_id FROM '.TABLE_CATEGORIES.' WHERE categories_id = '.$thecategory_id;
				$result3 = ep_4_query($sql3);
				$row3 = mysql_fetch_array($result3);
				$theparent_id = $row3['parent_id'];
				if ($theparent_id != '') { // Found parent ID, set thecategoryid to get the next level
				  $thecategory_id = $theparent_id;
				} else { // Category Root Found
				  $thecategory_id = false;
				}
			} // while
			
			// trim off trailing delimiter
			foreach ($langcode as $key => $lang) {
				$lid = $lang['id'];
				$row['v_categories_name_'.$lid] = substr($row['v_categories_name_'.$lid],0,strlen($row['v_categories_name_'.$lid])-1); 		
			} // foreach
		} // if() delimited categories path

		// MANUFACTURERS DOWNLOAD
		// if the filelayout says we need a manfacturers name, get it for download file
		if (isset($filelayout['v_manufacturers_name'])) {
			if ( ($row['v_manufacturers_id'] != '0') && ($row['v_manufacturers_id'] != '') ) { // '0' is correct, but '' NULL is possible
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
			  $therow .= '"'.str_replace('"','""',$thetext).'"' . $csv_delimiter;
			} else {
			  // and put the text into the output separated by $csv_delimiter defined above
			  $therow .= $thetext . $csv_delimiter;
			}
		}

		// Remove trailing tab, then append the end-of-line
		$therow = substr($therow,0,strlen($therow)-1) . "\n";

		$filestring .= $therow;
	} // while ($row)
	
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
		case 'category':
		$EXPORT_FILE = 'Category-EP';
		break;
		case 'categorymeta': // chadd - added 12-02-2010
		$EXPORT_FILE = 'CategoryMeta-EP';
		break;
		case 'attrib':
		$EXPORT_FILE = 'Attrib-Full-EP';
		break;
		case 'attrib_basic_detailed':
		$EXPORT_FILE = 'Attrib-Basic-Detailed-EP';
		break;
		case 'attrib_basic_simple':
		$EXPORT_FILE = 'Attrib-Basic-Simple-EP';
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


	// either stream it to them or put it in the temp directory
	if ($ep_dlmethod == 'stream') { // Stream File
		header("Content-type: application/vnd.ms-excel");
//		header("Content-disposition: attachment; filename=$EXPORT_FILE" . (($excel_safe_output == true)?".csv":".txt")); // this should check the delimiter instead!
		header("Content-disposition: attachment; filename=$EXPORT_FILE" . (($csv_delimiter == ",")?".csv":".txt")); // this should check the delimiter instead!
		// Changed if using SSL, helps prevent program delay/timeout (add to backup.php also)
		if ($request_type== 'NONSSL'){
			header("Pragma: no-cache");
		} else {
			header("Pragma: ");
		}
		header("Expires: 0");

		$time_end = microtime(true);
		$time = $time_end - $time_start;

		echo $filestring.$time; // chadd
		die();
	} else { // PUT FILE IN TEMP DIR
		$tmpfpath = DIR_FS_CATALOG . '' . $tempdir . "$EXPORT_FILE" . (($csv_delimiter == ",")?".csv":".txt");
		$fp = fopen( $tmpfpath, "w+");
		fwrite($fp, $filestring);
		
		fclose($fp);
		$messageStack->add(sprintf(EASYPOPULATE_4_MSGSTACK_FILE_EXPORT_SUCCESS, $EXPORT_FILE, $tempdir), 'success');
	}
} // END: Download Section
?>