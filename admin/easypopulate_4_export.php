<?php
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

$filelayout = array();
$filelayout_sql = '';
$filelayout = ep_4_set_filelayout($ep_dltype,  $filelayout_sql, $sql_filter, $langcode, $ep_supported_mods); 
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

// these variablels are for the Attrib_Basic Export
$active_products_id = ""; // start empty
$active_options_id = ""; // start empty
$active_language_id = ""; // start empty
$active_row = array(); // empty array
$last_products_id = "";

$result = ep_4_query($filelayout_sql);
while ($row = mysql_fetch_array($result)) {

if ($ep_dltype == 'attrib_basic') { // special case 'attrib_basic'

	if ($row['v_products_id'] == $active_products_id) {
		if ($row['v_options_id'] == $active_options_id) {
			// collect the products_options_values_name
			if ($active_language_id <> $row['v_language_id']) {
				$l_id = $row['v_language_id'];
				$active_row['v_products_options_name_'.$l_id] = $row['v_products_options_name'];
				$active_row['v_products_options_values_name_'.$l_id] = $row['v_products_options_values_name'];
				$active_language_id = $row['v_language_id'];
			} else {
				$l_id = $row['v_language_id'];
				$active_row['v_products_options_name_'.$l_id] = $row['v_products_options_name'];
				$active_row['v_products_options_values_name_'.$l_id] .= ",".$row['v_products_options_values_name'];
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
				$row2 = mysql_fetch_array($result2);
				if (mysql_num_rows($result2)) {
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
		if ($ep_dltype == 'full') {
			// names and descriptions require that we loop thru all installed languages
			foreach ($langcode as $key => $lang) {
				$lid = $lang['id'];
				// metaData start
				$sqlMeta = 'SELECT * FROM '.TABLE_META_TAGS_PRODUCTS_DESCRIPTION.' WHERE products_id = '.$row['v_products_id'].' AND language_id = '.$lid.' LIMIT 1 ';
				$resultMeta = ep_4_query($sqlMeta);
				$rowMeta    = mysql_fetch_array($resultMeta);
				$row['v_metatags_title_'.$lid]       = $rowMeta['metatags_title'];
				$row['v_metatags_keywords_'.$lid]    = $rowMeta['metatags_keywords'];
				$row['v_metatags_description_'.$lid] = $rowMeta['metatags_description'];
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
		
		// Multi-Lingual Categories, Categories Meta, Categories Descriptions
		if ($ep_dltype == 'categorymeta') {
			// names and descriptions require that we loop thru all languages that are turned on in the store
			foreach ($langcode as $key => $lang) {
				$lid = $lang['id'];
				// metaData start
				$sqlMeta = 'SELECT * FROM '.TABLE_METATAGS_CATEGORIES_DESCRIPTION.' WHERE categories_id = '.$row['v_categories_id'].' AND language_id = '.$lid.' LIMIT 1 ';
				$resultMeta = ep_4_query($sqlMeta) or die(mysql_error());
				$rowMeta    = mysql_fetch_array($resultMeta);
				$row['v_metatags_title_'.$lid]       = $rowMeta['metatags_title'];
				$row['v_metatags_keywords_'.$lid]    = $rowMeta['metatags_keywords'];
				$row['v_metatags_description_'.$lid] = $rowMeta['metatags_description'];
				// metaData end
				// for each language, get category description and name
				$sql2    = 'SELECT * FROM '.TABLE_CATEGORIES_DESCRIPTION.' WHERE categories_id = '.$row['v_categories_id'].' AND language_id = '.$lid.' LIMIT 1 ';
				$result2 = ep_4_query($sql2);
				$row2    = mysql_fetch_array($result2);
				$row['v_categories_name_'.$lid]        = $row2['categories_name'];
				$row['v_categories_description_'.$lid] = $row2['categories_description'];
			} // foreach
		} // if ($ep_dltype ...
		
		// CATEGORIES EXPORT
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
			
			// trim off trailing category delimiter '^'
			foreach ($langcode as $key => $lang) {
				$lid = $lang['id'];
				$row['v_categories_name_'.$lid] = rtrim($row['v_categories_name_'.$lid], "^");		
			} // foreach
		} // if() delimited categories path

		// Music Information Export for products with products_type == 2
		if (isset($filelayout['v_artists_name'])  && ($row['v_products_type'] == '2')) {
			$sql_music_extra = 'SELECT * FROM '.TABLE_PRODUCT_MUSIC_EXTRA.' WHERE products_id = '.$row['v_products_id'].' LIMIT 1';
			$result_music_extra = ep_4_query($sql_music_extra);
			$row_music_extra = mysql_fetch_array($result_music_extra);
			// artist
			if ( ($row_music_extra['artists_id'] != '0') && ($row_music_extra['artists_id'] != '') ) { // '0' is correct, but '' NULL is possible
				$sql_record_artists = 'SELECT * FROM '.TABLE_RECORD_ARTISTS.' WHERE artists_id = '.$row_music_extra['artists_id'].' LIMIT 1';
				$result_record_artists = ep_4_query($sql_record_artists);
				$row_record_artists = mysql_fetch_array($result_record_artists);
				$row['v_artists_name'] = $row_record_artists['artists_name'];
				$row['v_artists_image'] = $row_record_artists['artists_image'];
				foreach ($langcode as $key => $lang) {
					$lid = $lang['id'];
					$sql_record_artists_info = 'SELECT * FROM '.TABLE_RECORD_ARTISTS_INFO.' WHERE artists_id = '.$row_music_extra['artists_id'].' AND languages_id = '.$lid.' LIMIT 1';
					$result_record_artists_info = ep_4_query($sql_record_artists_info);
					$row_record_artists_info = mysql_fetch_array($result_record_artists_info);
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
				$row_record_company = mysql_fetch_array($result_record_company);
				$row['v_record_company_name'] = $row_record_company['record_company_name'];
				$row['v_record_company_image'] = $row_record_company['record_company_image'];
				foreach ($langcode as $key => $lang) {
					$lid = $lang['id'];
					$sql_record_company_info = 'SELECT * FROM '.TABLE_RECORD_COMPANY_INFO.' WHERE record_company_id = '.$row_music_extra['record_company_id'].' AND languages_id = '.$lid.' LIMIT 1';
					$result_record_company_info = ep_4_query($sql_record_company_info);
					$row_record_company_info = mysql_fetch_array($result_record_company_info);
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
				$row_music_genre = mysql_fetch_array($result_music_genre);
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
				$row2    = mysql_fetch_array($result2);
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