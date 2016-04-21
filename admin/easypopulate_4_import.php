<?php
// $Id: easypopulate_4_import.php, v4.0.34a 03-29-2016 mc12345678 $

if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
}

if (!defined(EP4_REPLACE_BLANK_IMAGE)) {
  define('EP4_REPLACE_BLANK_IMAGE', 'false'); // Values to be 'true' and 'false' true, if on import the image path evaluates to '' then if true, stores the value of PRODUCTS_IMAGE_NO_IMAGE as the image, otherwise will leave it as blank.;
}

// BEGIN: Data Import Module
if (!is_null($_POST['import']) && isset($_POST['import'])) {
  $time_start = microtime(true); // benchmarking
  $display_output .= EASYPOPULATE_4_DISPLAY_HEADING;

  $file = array('name' => $_POST['import']);
  $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_LOCAL_FILE_SPEC, $file['name']);

  $ep_update_count = 0; // product records updated 
  $ep_import_count = 0; // new products records imported
  $ep_error_count = 0; // errors detected during import
  $ep_warning_count = 0; // warning detected during import

  $zco_notifier->notify('EP4_IMPORT_START');
  // When updating products info, these values are used for existing data
  // This allows a reduced number of columns to be used on updates 
  // otherwise these would have to be exported/imported every time
  // this ONLY applies to when a column is MISSING
  $default_these = array();
  $default_these[] = 'v_products_image';
  $default_these[] = 'v_products_type';
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
  if ($ep_supported_mods['map'] == true) { // Manufacturer's Advertised Price
    $default_these[] = 'v_map_enabled';
    $default_these[] = 'v_map_price';
  }
  if ($ep_supported_mods['gppi'] == true) { // Group Pricing Per Item - 4-24-2012
    $default_these[] = 'v_products_group_a_price';
    $default_these[] = 'v_products_group_b_price';
    $default_these[] = 'v_products_group_c_price';
    $default_these[] = 'v_products_group_d_price';
  }
  if ($ep_supported_mods['excl'] == true) { // Exclusive Products Custom Mod
    $default_these[] = 'v_products_exclusive';
  }
  if (count($custom_fields) > 0) {
    foreach ($custom_fields as $field) {
      $filelayout[] = 'v_' . $field;
    }
  }
  $default_these[] = 'v_products_quantity';
  $default_these[] = 'v_products_weight';
  $default_these[] = 'v_products_discount_type';
  $default_these[] = 'v_products_discount_type_from';
  $default_these[] = 'v_product_is_call';
  $default_these[] = 'v_products_sort_order';
  $default_these[] = 'v_products_quantity_order_min';
  $default_these[] = 'v_products_quantity_order_units';
  $default_these[] = 'v_products_priced_by_attribute'; // 4-30-2012
  $default_these[] = 'v_product_is_always_free_shipping'; // 4-30-2012
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

  $file_location = (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ DIR_FS_CATALOG : /* Admin side */ DIR_FS_ADMIN) . $tempdir . $file['name'];
  // Error Checking
  if (!file_exists($file_location)) {
    $display_output .='<font color="red"><b>ERROR: Import file does not exist:' . $file_location . '</b></font><br/>';
  } else if (!($handle = fopen($file_location, "r"))) {
    $display_output .= '<font color="red"><b>ERROR: Cannot open import file:' . $file_location . '</b></font><br/>';
  }

  // Read Column Headers
  if ($raw_headers = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) {
    /* 		$header_search = array("ARTIST","TITLE","FORMAT","LABEL",
      "CATALOG_NUMBER","UPC","PRICE","RETAIL",
      "WHOLESALE",	"GENRE","RELEASE_DATE","EXCLUSIVE",
      "WEIGHT","QTY","DISPLAY ON SITE","IMAGE",
      "DESCRIPTION", "TERRITORY","TRACKLISTING");

      $header_replace = array("v_artists_name","v_products_name_1","v_categories_name_1","v_record_company_name",
      "v_products_model","v_products_upc","v_products_price",	"v_products_group_a_price",
      "v_products_group_b_price","v_music_genre_name","v_date_avail","v_products_exclusive",
      "v_products_weight","v_products_quantity","v_status","v_products_image",
      "v_products_description_1", "v_territory","v_tracklisting");

      $raw_headers = str_replace($header_search, $header_replace, $raw_headers);
     */
    $filelayout = array_flip($raw_headers);

    // Featured Products 5-2-2012
    if (strtolower(substr($file['name'], 0, 11)) == "featured-ep") {
      // check products table to see of product_model exists
      while ($items = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) { // read 1 line of data
        $sql = "SELECT * FROM " . TABLE_PRODUCTS; 

        switch (EP4_DB_FILTER_KEY) {
          case 'products_model':
            $sql .= " WHERE (products_model = :products_model:)";
            $chosen_key = 'v_products_model';
            break;
          case 'blank_new':
          case 'products_id':
            $sql .= " WHERE (products_id = :products_id:)";
            $chosen_key = 'v_products_id';
            break;
          default:
            $sql .= " WHERE (products_model = :products_model:)";
            $chosen_key = 'v_products_model';
            break;
        }
        ${$chosen_key} = NULL;
  
        $sql .= " LIMIT 1";

        $sql = $db->bindVars($sql, ':products_model:', $items[$filelayout['v_products_model']], 'string');
        $sql = $db->bindVars($sql, ':products_id:', $items[$filelayout['v_products_id']], 'string');
        $result = ep_4_query($sql);
        if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result))) {
          $v_products_id = $row['products_id'];
          // Add or Update the table Featured
          $sql2 = "SELECT * FROM " . TABLE_FEATURED . " WHERE ( products_id = :products_id: ) LIMIT 1";
          $sql2 = $db->bindVars($sql2, ':products_id:', $v_products_id, 'integer');
          $result2 = ep_4_query($sql2);
          if ($row2 = ($ep_uses_mysqli ? mysqli_fetch_array($result2) : mysql_fetch_array($result2))) { // update featured product
            $v_featured_id = $row2['featured_id'];
            $v_today = strtotime(date("Y-m-d"));
            $v_expires_date = $items[$filelayout['v_expires_date']];
            $v_featured_date_available = $items[$filelayout['v_featured_date_available']];
            if (($v_today >= strtotime($v_featured_date_available)) && ($v_today < strtotime($v_expires_date))) {
              $v_status = 1;
              $v_date_status_change = date("Y-m-d");
            } else {
              $v_status = 0;
              $v_date_status_change = date("Y-m-d");
            }
            $sql = "UPDATE " . TABLE_FEATURED . " SET 
							featured_last_modified  = CURRENT_TIMESTAMP,
							expires_date            = :expires_date:,
							date_status_change      = :date_status_change:,
							status                  = :status:,
							featured_date_available = :featured_date_available:
							WHERE (
							featured_id = :featured_id:)";
            $sql = $db->bindVars($sql, ':expires_date:', $v_expires_date, 'string');
            $sql = $db->bindVars($sql, ':date_status_change:', $v_date_status_change, 'string');
            $sql = $db->bindVars($sql, ':status:', $v_status , 'integer');
            $sql = $db->bindVars($sql, ':featured_date_available:', $v_featured_date_available, 'string');
            $sql = $db->bindVars($sql, ':featured_id:', $v_featured_id, 'string');
            $result = ep_4_query($sql);
            if ($result) {
              zen_record_admin_activity('Updated featured product with featured_id ' . (int) $v_featured_id . ' via EP4.', 'info');
            }
            $ep_update_count++;
          } else {
            // add featured product
            $sql_max = "SELECT MAX(featured_id) max FROM " . TABLE_FEATURED;
            $result_max = ep_4_query($sql_max);
            $row_max = ($ep_uses_mysqli ? mysqli_fetch_array($result_max) : mysql_fetch_array($result_max));
            $max_featured_id = $row_max['max'] + 1;
            // if database is empty, start at 1
            if (!is_numeric($max_featured_id)) {
              $max_featured_id = 1;
            }
            $v_today = strtotime(date("Y-m-d"));
            $v_expires_date = $items[$filelayout['v_expires_date']];
            $v_featured_date_available = $items[$filelayout['v_featured_date_available']];
            if (($v_today >= strtotime($v_featured_date_available)) && ($v_today < strtotime($v_expires_date))) {
              $v_status = 1;
              $v_date_status_change = date("Y-m-d");
            } else {
              $v_status = 0;
              $v_date_status_change = date("Y-m-d");
            }
            $sql = "INSERT INTO " . TABLE_FEATURED . " SET 
							featured_id             = :max_featured_id:,
							products_id             = :products_id:,
							featured_date_added     = CURRENT_TIMESTAMP,
							featured_last_modified  = '',
							expires_date            = :expires_date:,
							date_status_change      = :date_status_change:,
							status                  = :status:,
							featured_date_available = :featured_date_available:";
            $sql = $db->bindVars($sql, ':max_featured_id:', $max_featured_id, 'string');
            $sql = $db->bindVars($sql, ':products_id:', $v_products_id, 'integer');
            $sql = $db->bindVars($sql, ':expires_date:', $v_expires_date, 'date');
            $sql = $db->bindVars($sql, ':date_status_change:', $v_date_status_change, 'string');
            $sql = $db->bindVars($sql, ':status:', $v_status , 'integer');
            $sql = $db->bindVars($sql, ':featured_date_available:', $v_featured_date_available, 'date');
            $result = ep_4_query($sql);
            if ($result) {
              zen_record_admin_activity('Inserted product ' . (int) $v_products_id . ' via EP4 into featured table.', 'info');
            $ep_import_count++;
            }
          }
        } else { // ERROR: This products_model doen't exist!
          $display_output .= sprintf('<br /><font color="red"><b>SKIPPED! - ' . $chosen_key . ': </b>%s - Not Found!</font>', $items[$filelayout[$chosen_key]]);
          $ep_error_count++;
        }
      }
    } // Featured Products
	
    // Basic Attributes Import
    if (strtolower(substr($file['name'], 0, 15)) == "attrib-basic-ep") {
      require_once('easypopulate_4_attrib.php');
    } // Attributes Import	

    // Detailed Attributes Import
    if (strtolower(substr($file['name'], 0, 18)) == "attrib-detailed-ep") {
      while ($items = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) { // read 1 line of data
        $sql = 'SELECT * FROM ' . TABLE_PRODUCTS_ATTRIBUTES . ' 
					WHERE (
					products_attributes_id = :products_attributes_id: AND 
					products_id = :products_id: AND 
					options_id = :options_id: AND 
					options_values_id = :options_values_id:
					) LIMIT 1';
        $sql = $db->bindVars($sql, ':products_attributes_id:', $items[$filelayout['v_products_attributes_id']], 'integer');
        $sql = $db->bindVars($sql, ':products_id:', $items[$filelayout['v_products_id']], 'integer');
        $sql = $db->bindVars($sql, ':options_id:', $items[$filelayout['v_options_id']], 'integer');
        $sql = $db->bindVars($sql, ':options_values_id:', $items[$filelayout['v_options_values_id']], 'integer');
        $result = ep_4_query($sql);
        if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result))) {
          // UPDATE
          $sql = "UPDATE " . TABLE_PRODUCTS_ATTRIBUTES . " SET 
					options_values_price              = " . $items[$filelayout['v_options_values_price']] . ", 
					";
          if ($ep_supported_mods['dual']) {
            $sql .= "options_values_price_w              = :options_values_price_w:, ";
            $sql = $db->bindVars($sql, ':options_values_price_w:', $items[$filelayout['v_options_values_price_w']], 'currency');
          }

          $sql .= "
					price_prefix                      = :price_prefix:,
					products_options_sort_order       = :products_options_sort_order:,
					product_attribute_is_free         = :product_attribute_is_free:,
					products_attributes_weight        = :products_attributes_weight:,
					products_attributes_weight_prefix = :products_attributes_weight_prefix:,
					attributes_display_only           = :attributes_display_only:,
					attributes_default                = :attributes_default:,
					attributes_discounted             = :attributes_discounted:,
					attributes_image                  = :attributes_image:,
					attributes_price_base_included    = :attributes_price_base_included:,
					attributes_price_onetime          = :attributes_price_onetime:,
					attributes_price_factor           = :attributes_price_factor:,
					attributes_price_factor_offset    = :attributes_price_factor_offset:,
					attributes_price_factor_onetime   = :attributes_price_factor_onetime:,
					attributes_price_factor_onetime_offset = :attributes_price_factor_onetime_offset:,
					attributes_qty_prices             = :attributes_qty_prices:,
					attributes_qty_prices_onetime     = :attributes_qty_prices_onetime:,
					attributes_price_words            = :attributes_price_words:,
					attributes_price_words_free       = :attributes_price_words_free:,
					attributes_price_letters          = :attributes_price_letters:,
					attributes_price_letters_free     = :attributes_price_letters_free:,
					attributes_required               = :attributes_required:
					WHERE (
					products_attributes_id = :products_attributes_id: AND 
					products_id = :products_id: AND 
					options_id = :options_id: AND 
					options_values_id = :options_values_id:)";

          $sql = $db->bindVars($sql, ':price_prefix:', $items[$filelayout['v_price_prefix']], 'string');
          $sql = $db->bindVars($sql, ':products_options_sort_order:', $items[$filelayout['v_products_options_sort_order']], 'integer');
          $sql = $db->bindVars($sql, ':product_attribute_is_free:', $items[$filelayout['v_product_attribute_is_free']], 'integer');
          $sql = $db->bindVars($sql, ':products_attributes_weight:', $items[$filelayout['v_products_attributes_weight']], 'float');
          $sql = $db->bindVars($sql, ':products_attributes_weight_prefix:', $items[$filelayout['v_products_attributes_weight_prefix']], 'string');
          $sql = $db->bindVars($sql, ':attributes_display_only:', $items[$filelayout['v_attributes_display_only']], 'integer');
          $sql = $db->bindVars($sql, ':attributes_default:', $items[$filelayout['v_attributes_default']], 'integer');
          $sql = $db->bindVars($sql, ':attributes_discounted:', $items[$filelayout['v_attributes_discounted']], 'integer');
          $sql = $db->bindVars($sql, ':attributes_image:', $items[$filelayout['v_attributes_image']], 'string');
          $sql = $db->bindVars($sql, ':attributes_price_base_included:', $items[$filelayout['v_attributes_price_base_included']], 'integer');
          $sql = $db->bindVars($sql, ':attributes_price_onetime:', $items[$filelayout['v_attributes_price_onetime']], 'float');
          $sql = $db->bindVars($sql, ':attributes_price_factor:', $items[$filelayout['v_attributes_price_factor']], 'float');
          $sql = $db->bindVars($sql, ':attributes_price_factor_offset:', $items[$filelayout['v_attributes_price_factor_offset']], 'float');
          $sql = $db->bindVars($sql, ':attributes_price_factor_onetime:', $items[$filelayout['v_attributes_price_factor_onetime']], 'float');
          $sql = $db->bindVars($sql, ':attributes_price_factor_onetime_offset:', $items[$filelayout['v_attributes_price_factor_onetime_offset']], 'float');
          $sql = $db->bindVars($sql, ':attributes_qty_prices:', $items[$filelayout['v_attributes_qty_prices']], 'string');
          $sql = $db->bindVars($sql, ':attributes_qty_prices_onetime:', $items[$filelayout['v_attributes_qty_prices_onetime']], 'string');
          $sql = $db->bindVars($sql, ':attributes_price_words:', $items[$filelayout['v_attributes_price_words']], 'float');
          $sql = $db->bindVars($sql, ':attributes_price_words_free:', $items[$filelayout['v_attributes_price_words_free']], 'integer');
          $sql = $db->bindVars($sql, ':attributes_price_letters:', $items[$filelayout['v_attributes_price_letters']], 'float');
          $sql = $db->bindVars($sql, ':attributes_price_letters_free:', $items[$filelayout['v_attributes_price_letters_free']], 'float');
          $sql = $db->bindVars($sql, ':attributes_required:', $items[$filelayout['v_attributes_required']], 'integer');
          $sql = $db->bindVars($sql, ':products_attributes_id:', $items[$filelayout['v_products_attributes_id']], 'integer');
          $sql = $db->bindVars($sql, ':products_id:', $items[$filelayout['v_products_id']], 'integer');
          $sql = $db->bindVars($sql, ':options_id:', $items[$filelayout['v_options_id']], 'integer');
          $sql = $db->bindVars($sql, ':options_values_id:', $items[$filelayout['v_options_values_id']], 'integer');
              
          $result = ep_4_query($sql);
          if ($result) {
            zen_record_admin_activity('Updated products attributes ' . (int) $items[$filelayout['v_products_attributes_id']] . ' of product ' . $items[$filelayout['v_products_id']] . ' having option ' . $items[$filelayout['v_options_id']] . ' and option value ' . $items[$filelayout['v_options_values_id']] . ' via EP4.', 'info');
          }

          if ($items[$filelayout['v_products_attributes_filename']] <> '') { // download file name
            $sql = 'SELECT * FROM ' . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . ' 
              WHERE (products_attributes_id = :products_attributes_id:) LIMIT 1';
            $sql = $db->bindVars($sql, ':products_attributes_id:', $items[$filelayout['v_products_attributes_id']], 'integer');
            $result = ep_4_query($sql);
            if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result))) { // update
              $sql = "UPDATE " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " SET 
								products_attributes_filename = :products_attributes_filename:,
								products_attributes_maxdays  = :products_attributes_maxdays:,
								products_attributes_maxcount = :products_attributes_maxcount:
								WHERE ( 
								products_attributes_id = :products_attributes_id:)";
              
              $sql = $db->bindVars($sql, ':products_attributes_filename:', $items[$filelayout['v_products_attributes_filename']], 'string');
              $sql = $db->bindVars($sql, ':products_attributes_maxdays:', $items[$filelayout['v_products_attributes_maxdays']], 'integer');
              $sql = $db->bindVars($sql, ':products_attributes_maxcount:', $items[$filelayout['v_products_attributes_maxcount']], 'integer');
              $sql = $db->bindVars($sql, ':products_attributes_id:', $items[$filelayout['v_products_attributes_id']], 'integer');
              
              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Downloads-manager details updated by EP4 for ' . $items[$filelayout['v_products_attributes_id']], 'info');
              }
            } else { // insert
              $sql = "INSERT INTO " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " SET 
								products_attributes_id       = :products_attributes_id:,
								products_attributes_filename = :products_attributes_filename:,
								products_attributes_maxdays  = :products_attributes_maxdays:,
								products_attributes_maxcount = :products_attributes_maxcount:";
              
              $sql = $db->bindVars($sql, ':products_attributes_id:', $items[$filelayout['v_products_attributes_id']], 'integer');
              $sql = $db->bindVars($sql, ':products_attributes_filename:', $items[$filelayout['v_products_attributes_filename']], 'string');
              $sql = $db->bindVars($sql, ':products_attributes_maxdays:', $items[$filelayout['v_products_attributes_maxdays']], 'integer');
              $sql = $db->bindVars($sql, ':products_attributes_maxcount:', $items[$filelayout['v_products_attributes_maxcount']], 'integer');

              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Downloads-manager details inserted by EP4 for ' . $items[$filelayout['v_products_attributes_id']], 'info');
              }
            }
          }
          $ep_update_count++;
        } else { // error Attribute entry not found - needs work!
          $display_output .= sprintf('<br /><font color="red"><b>SKIPPED! - Attribute Entry on ' . substr($chosen_key, 2) . ': </b>%s - Not Found!</font>', $items[$filelayout[$chosen_key]]);
          $ep_error_count++;
        } // if 
      } // while
    } // if Detailed Attributes Import

    // Detailed Attributes Import
    if (strtolower(substr($file['name'], 0, 15)) == "sba-detailed-ep" && $ep_4_SBAEnabled != false) {
      while ($items = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) { // read 1 line of data
        $sql = 'SELECT * FROM ' . TABLE_PRODUCTS_ATTRIBUTES . ' 
					WHERE (
					products_attributes_id = :products_attributes_id:' . /* AND 
                  products_id = '.$items[$filelayout['v_products_id']].' AND
                  options_id = '.$items[$filelayout['v_options_id']].' AND
                  options_values_id = '.$items[$filelayout['v_options_values_id']].'
                 */' ) LIMIT 1';
        $sql = 'SELECT * FROM ' . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . ' 
					WHERE (
					stock_id = :stock_id: ' . /* AND 
                  products_id = '.$items[$filelayout['v_products_id']].' AND
                  options_id = '.$items[$filelayout['v_options_id']].' AND
                  options_values_id = '.$items[$filelayout['v_options_values_id']].'
                 */') LIMIT 1';
        $sql = $db->bindVars($sql, ':stock_id:', $items[$filelayout['v_stock_id']], 'integer');
        $sql = $db->bindVars($sql, ':products_attributes_id:', $items[$filelayout['v_products_attributes_id']], 'integer');
        
        $result = ep_4_query($sql);
        if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result))) {
          // UPDATE
          $sql = "UPDATE " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " SET 
						products_id		              = :products_id:,
						stock_attributes                  = :stock_attributes:,
						quantity					    = :quantity:,
					  sort						    = :sort:" . ( $ep_4_SBAEnabled == '2' ? ",
            customid            = :customid: " : " ") .
                  "
						WHERE (
						stock_id = :stock_id: )";

          $sql = $db->bindVars($sql, ':products_id:', $items[$filelayout['v_products_id']], 'integer');
          $sql = $db->bindVars($sql, ':stock_attributes:', $items[$filelayout['v_stock_attributes']], 'string');
          $sql = $db->bindVars($sql, ':quantity:', $items[$filelayout['v_quantity']], 'float');
          $sql = $db->bindVars($sql, ':sort:', $items[$filelayout['v_sort']], 'integer');
          $sql = $db->bindVars($sql, ':customid:', (zen_not_null($items[$filelayout['v_customid']]) ? $items[$filelayout['v_customid']] : 'NULL'), (zen_not_null($items[$filelayout['v_customid']]) ? 'string' : 'passthru'));
          $sql = $db->bindVars($sql, ':stock_id:', $items[$filelayout['v_stock_id']], 'integer');
          
          $result = ep_4_query($sql);
          if ($result) {
            zen_record_admin_activity('Updated products with attributes stock ' . (int) $items[$filelayout['v_stock_id']] . ' via EP4.', 'info');
          }

          if ($items[$filelayout['v_products_attributes_filename']] <> '') { // download file name
            $sql = 'SELECT * FROM ' . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . ' 
							WHERE (products_attributes_id = :products_attributes_id:) LIMIT 1';
            $sql = $db->bindVars($sql, ':products_attributes_id:', $items[$filelayout['v_products_attributes_id']], 'integer');
            
            $result = ep_4_query($sql);
            if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result))) { // update
              $sql = "UPDATE " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " SET 
								products_attributes_filename = :products_attributes_filename:,
								products_attributes_maxdays  = :products_attributes_maxdays:,
								products_attributes_maxcount = :products_attributes_maxcount:
								WHERE ( 
								products_attributes_id = :products_attributes_id:)";
              $sql = $db->bindVars($sql, ':products_attributes_filename:', $items[$filelayout['v_products_attributes_filename']], 'string');
              $sql = $db->bindVars($sql, ':products_attributes_maxdays:', $items[$filelayout['v_products_attributes_maxdays']], 'integer');
              $sql = $db->bindVars($sql, ':products_attributes_maxcount:', $items[$filelayout['v_products_attributes_maxcount']], 'integer');
              $sql = $db->bindVars($sql, ':products_attributes_id:', $items[$filelayout['v_products_attributes_id']], 'integer');
              
              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Downloads-manager details updated by EP4 for ' . $items[$filelayout['v_products_attributes_id']], 'info');
              }
            } else { // insert
              $sql = "INSERT INTO " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " SET 
								products_attributes_id       = :products_attributes_id:,
								products_attributes_filename = :products_attributes_filename:,
								products_attributes_maxdays  = :products_attributes_maxdays:,
								products_attributes_maxcount = :products_attributes_maxcount:";
              
              $sql = $db->bindVars($sql, ':products_attributes_id:', $items[$filelayout['v_products_attributes_id']], 'integer');
              $sql = $db->bindVars($sql, ':products_attributes_filename:', $items[$filelayout['v_products_attributes_filename']], 'string');
              $sql = $db->bindVars($sql, ':products_attributes_maxdays:', $items[$filelayout['v_products_attributes_maxdays']], 'integer');
              $sql = $db->bindVars($sql, ':products_attributes_maxcount:', $items[$filelayout['v_products_attributes_maxcount']], 'integer');

              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Downloads-manager details inserted by EP4 for ' . $items[$filelayout['v_products_attributes_id']], 'info');
              }
            }
          }
          $ep_update_count++;
        } else { // error Attribute entry not found - needs work!
          $display_output .= sprintf('<br /><font color="red"><b>SKIPPED! - Attribute Entry on ' . substr($chosen_key, 2) . 'Model: </b>%s - Not Found!</font>', $items[$filelayout[$chosen_key]]);
          $ep_error_count++;
        } // if 
      } // while
    } // if Detailed Stock By Attributes Import

    // Import stock quantity knowing that cart has SBA installed:
    // FOR RESYNC, THEN THE FOLLOWING APPLIES and will need to get/carry over the 
    // $stock class:
    // require(DIR_WS_CLASSES . 'products_with_attributes_stock.php');
    // $stock = new products_with_attributes_stock;
    // 		if(is_numeric((int)$_POST['products_id'])){
    //			$stock->update_parent_products_stock((int)$_POST['products_id']);
    //		$messageStack->add_session('Parent Product Quantity Updated', 'success');

    if (strtolower(substr($file['name'], 0, 12)) == "sba-stock-ep" && $ep_4_SBAEnabled != false) {
      $sync = false;
      if (!is_null($_POST['sync']) && isset($_POST['sync']) && $_POST['sync'] == '1') {
        $query = array();
        $sync = true;

        require(DIR_WS_CLASSES . 'products_with_attributes_stock.php');
        $stock = new products_with_attributes_stock;
      }

      while ($items = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) { // read 1 line of data
        //IF STANDARD STOCK, then Update the standard stock
        if ($items[$filelayout['v_SBA_tracked']] == '') {
          $sql = "UPDATE " . TABLE_PRODUCTS . " SET 
					  products_quantity					    = :products_quantity:
					  WHERE (
					  products_id = :products_id: )";
          $sql = $db->bindVars($sql, ':products_quantity:', $items[$filelayout['v_products_quantity']], 'float');
          $sql = $db->bindVars($sql, ':products_id:', $items[$filelayout['v_table_tracker']], 'integer');
          //$sql = $db->bindVars($sql, ':products_quantity'); Need to verify the above works with float values which I think not.. 
          if ($sync) {
            $query[$items[(int) $filelayout['v_products_model']]] = $items; //mc12345678 Not sure that this line is correct... For a couple of reasons, but will have to look at later...  Biggest issue is that the model is cast as an integer when it can be a string... 
            //Need to capture all of the product model/product number/quantity counts so that can do a comparison in the SBA section and remove the data point.  Once all done, then cycle through this data and update with it.
          } elseif (!$sync) {
            if ($result = ep_4_query($sql)) {
              zen_record_admin_activity('Updated product ' . (int) $items[(int) $filelayout['v_table_tracker']] . ' via EP4.', 'info');
              $ep_update_count++;
              $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_UPDATE_PRODUCT, $items[(int) $filelayout['v_products_model']]) . $items[(int) $filelayout['v_products_quantity']];
            } else { // error Attribute entry not found - needs work!
              $display_output .= sprintf('<br /><font color="red"><b>SKIPPED! - Product Quantity on Model: </b>%s - Not Found!</font>', $items[(int) $filelayout['v_products_model']]);
              $ep_error_count++;
            } // if 
          }
        } elseif ($items[(int) $filelayout['v_SBA_tracked']] == "X") {
          $sql = "UPDATE " . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . " SET 
            quantity              = :products_quantity: " . ($ep_4_SBAEnabled == '2' ? ", customid  = :customid: " : "") . "
					WHERE (
					stock_id = :stock_id: )";
          $sql = $db->bindVars($sql, ':products_quantity:', $items[$filelayout['v_products_quantity']], 'float');
          $sql = $db->bindVars($sql, ':stock_id:', $items[$filelayout['v_table_tracker']], 'integer');
          $sql = $db->bindVars($sql, ':customid:', (zen_not_null($items[$filelayout['v_customid']]) ? $items[$filelayout['v_customid']] : 'NULL'), (zen_not_null($items[$filelayout['v_customid']]) ? 'string' : 'passthru'));
          if ($result = ep_4_query($sql)) {
            zen_record_admin_activity('Updated products with attributes stock ' . (int) $items[$filelayout['v_table_tracker']] . ' via EP4.', 'info');
            $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_UPDATE_PRODUCT, $items[$filelayout['v_products_model']]) . $items[$filelayout['v_products_quantity']] . ($ep_4_SBAEnabled == '2' ? " " . $items[$filelayout['v_customid']] : "");
            $ep_update_count++;
            if ($sync) {
              $stock->update_parent_products_stock((int) $query[$items[(int) $filelayout['v_products_model']]][(int) $filelayout['v_table_tracker']]);
              //		$messageStack->add_session('Parent Product Quantity Updated', 'success');
              unset($query[$items[(int) $filelayout['v_products_model']]]);
            }
          } else { // error Attribute entry not found - needs work!
            $display_output .= sprintf('<br /><font color="red"><b>SKIPPED! - SBA Tracked Quantity ' . ($ep_4_SBAEnabled == '2' ? 'and CustomID ' : '') . 'on Model: </b>%s - Not Found!</font>', $items[(int) $filelayout['v_products_model']]);
            $ep_error_count++;
          } // if 
        } //end if Standard / SBA stock
      } // end while

      if ($sync) {
        foreach ($query as $items) {
          $sql = "UPDATE " . TABLE_PRODUCTS . " SET 
					products_quantity					    = :products_quantity:
					WHERE (
					products_id = :products_id: )";
          $sql = $db->bindVars($sql, ':products_quantity:', $items[$filelayout['v_products_quantity']], 'float');
          $sql = $db->bindVars($sql, ':products_id:', $items[$filelayout['v_table_tracker']], 'integer');
          if ($result = ep_4_query($sql)) {
            zen_record_admin_activity('Updated product ' . (int) $items[(int) $filelayout['v_table_tracker']] . ' via EP4.', 'info');
            $ep_update_count++;
            $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_UPDATE_PRODUCT, $items[(int) $filelayout['v_products_model']]) . $items[(int) $filelayout['v_products_quantity']];
          } else { // error Attribute entry not found - needs work!
            $display_output .= sprintf('<br /><font color="red"><b>SKIPPED! - Product Quantity on Model: </b>%s - Not Found!</font>', $items[(int) $filelayout['v_products_model']]);
            $ep_error_count++;
          } //end if Standard
        } //end foreach
      } // end if sync
//		$display_output .= '<br/> This: ' . print_r($query) . 'test<br/>';
    } // End of Import stock quantity knowing that cart has SBA installed.

    // CATEGORIES1
    // This Category MetaTags import routine only deals with existing Categories. It does not create or modify the tree.
    // This code is ONLY used to Edit Categories image, description, and metatags data!
    // Categories are updated via the categories_id NOT the Name!! Very important distinction here!
    // mc12345678 below routine: categorymeta-ep could possibly clear assignments of 
    //   data if the field(s) are not included in the download file.  This
    //   needs to be modified to allow a reduced set of columns to be imported.
    if (strtolower(substr($file['name'], 0, 15)) == "categorymeta-ep") {
      while ($items = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) { // read 1 line of data
        // $items[$filelayout['v_categories_id']];
        // $items[$filelayout['v_categories_image']];
        $sql = 'SELECT categories_id FROM ' . TABLE_CATEGORIES . ' WHERE (categories_id = :categories_id:) LIMIT 1';
        $sql = $db->bindVars($sql, ':categories_id:', $items[$filelayout['v_categories_id']], 'integer');
        $result = ep_4_query($sql);
        if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result))) {
          // UPDATE
          $sql = "UPDATE " . TABLE_CATEGORIES . " SET 
						categories_image = :categories_image:,
					last_modified    = CURRENT_TIMESTAMP " . (array_key_exists('v_sort_order', $filelayout) ? ", sort_order = :sort_order:" : "" ) . "
						WHERE (categories_id = :categories_id:)";
          $sql = $db->bindVars($sql, ':categories_image:', $items[$filelayout['v_categories_image']], 'string');
          $sql = $db->bindVars($sql, ':sort_order:', $items[$filelayout['v_sort_order']], 'integer');
          $sql = $db->bindVars($sql, ':categories_id:', $items[$filelayout['v_categories_id']], 'integer');
          $result = ep_4_query($sql);
          if ($result) {
            zen_record_admin_activity('Updated category ' . (int) $items[$filelayout['v_categories_id']] . ' via EP4.', 'info');
          }
          foreach ($langcode as $key => $lang) {
            $lid = $lang['id'];
            // $items[$filelayout['v_categories_name_'.$lid]];
            // $items[$filelayout['v_categories_description_'.$lid]];
          if (isset($filelayout['v_categories_name_' . $lid]) || isset($filelayout['v_categories_description_' . $lid])) {
            $sql = "UPDATE " . TABLE_CATEGORIES_DESCRIPTION . " SET ";
              $update_count = false;
              if (isset($filelayout['v_categories_name_' . $lid])) {
                $sql .= "categories_name        = :categories_name:";
                $update_count = true;
              }
              if (isset($filelayout['v_categories_description_' . $lid])) {
                $sql .= ($update_count ? ", " : "") . "categories_description = :categories_description: ";
                $update_count = true;
              }
              $sql .= "
							WHERE 
							(categories_id = :categories_id: AND language_id = :language_id:)";

            $sql = $db->bindVars($sql, ':categories_name:', ep_4_curly_quotes($items[$filelayout['v_categories_name_' . $lid]]), 'string');
            $sql = $db->bindVars($sql, ':categories_description:', ep_4_curly_quotes($items[$filelayout['v_categories_description_' . $lid]]), 'string');
            $sql = $db->bindVars($sql, ':categories_id:', $items[$filelayout['v_categories_id']], 'integer');
            $sql = $db->bindVars($sql, ':language_id:', $lid, 'integer');
            $result = ep_4_query($sql);
            if ($result) {
              zen_record_admin_activity('Updated category description ' . (int) $items[$filelayout['v_categories_id']] . ' via EP4.', 'info');
            }
          }        
            // $items[$filelayout['v_metatags_title_'.$lid]];
            // $items[$filelayout['v_metatags_keywords_'.$lid]];
            // $items[$filelayout['v_metatags_description_'.$lid]];
            // Categories Meta Start
            $sql = "SELECT categories_id FROM " . TABLE_METATAGS_CATEGORIES_DESCRIPTION . " WHERE 
							(categories_id = :categories_id: AND language_id = :language_id:) LIMIT 1";
            $sql = $db->bindVars($sql, ':categories_id:', $items[$filelayout['v_categories_id']], 'integer');
            $sql = $db->bindVars($sql, ':language_id:', $lid, 'integer');
            $result = ep_4_query($sql);
            if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result))) {
              // UPDATE
            if (isset($filelayout['v_metatags_title_' . $lid]) || isset($filelayout['v_metatags_keywords_' . $lid]) || isset($filelayout['v_metatags_description_' . $lid])) {
              $sql = "UPDATE " . TABLE_METATAGS_CATEGORIES_DESCRIPTION . " SET ";
                $update_count = false;
                if (isset($filelayout['v_metatags_title_' . $lid])) {
                  $sql .= "metatags_title		 = :metatags_title: ";
                  $update_count = true;
                }
                if (isset($filelayout['v_metatags_keywords_' . $lid])) {
                  $sql .= ($update_count ? ", " : "") . "metatags_keywords	 = :metatags_keywords:";
                  $update_count = true;
                }
                if (isset($filelayout['v_metatags_description_' . $lid])) {
                  $sql .= ($update_count ? ", " : "") . "metatags_description = :metatags_description:";
                  $update_count = true;
                }
              $sql .= "
							WHERE 
							(categories_id = :categories_id: AND language_id = :language_id:)";
            }
            } else {
              // NEW - this should not happen
              $sql = "INSERT INTO " . TABLE_METATAGS_CATEGORIES_DESCRIPTION . " SET 
								metatags_title		 = :metatags_title:,
								metatags_keywords	 = :metatags_keywords:,
								metatags_description = :metatags_description:,
								categories_id		 = :categories_id:,
								language_id 		 = :language_id:";
            }
            $sql = $db->bindVars($sql, ':metatags_title:', ep_4_curly_quotes($items[$filelayout['v_metatags_title_' . $lid]]), 'string');
            $sql = $db->bindVars($sql, ':metatags_keywords:', ep_4_curly_quotes($items[$filelayout['v_metatags_keywords_' . $lid]]), 'string');
            $sql = $db->bindVars($sql, ':metatags_description:', ep_4_curly_quotes($items[$filelayout['v_metatags_description_' . $lid]]), 'string');
            $sql = $db->bindVars($sql, ':categories_id:', $items[$filelayout['v_categories_id']], 'integer');
            $sql = $db->bindVars($sql, ':language_id:', $lid, 'integer');
            if (($row && (isset($filelayout['v_metatags_title_' . $lid]) || isset($filelayout['v_metatags_keywords_' . $lid]) || isset($filelayout['v_metatags_description_' . $lid]))) || !$row) {
            $result = ep_4_query($sql);
            }
            if ($result) {
              zen_record_admin_activity('Inserted/Updated category metatag information ' . (int) $items[(int) $filelayout['v_categories_id']] . ' via EP4.', 'info');
            }
            $ep_update_count++;
          }
        } else { // error Category ID not Found
          $display_output .= sprintf('<br /><font color="red"><b>SKIPPED! - Category ID: </b>%s - Not Found!</font>', $items[$filelayout['v_categories_id']]);
          $ep_error_count++;
        } // if category found
      } // while
    } // if

    if (( strtolower(substr($file['name'], 0, 15)) <> "categorymeta-ep") && ( strtolower(substr($file['name'], 0, 7)) <> "attrib-") && ($ep_4_SBAEnabled != false ? ( strtolower(substr($file['name'], 0, 4)) <> "sba-") : true )) { //  temporary solution here... 12-06-2010
      $zco_notifier->notify('EP4_IMPORT_GENERAL_FILE_ALL');
	
      // Main IMPORT loop For Product Related Data. v_products_id is the main key
      while ($items = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) { // read 1 line of data

        // bug fix 5-10-2012: when adding/updating a mix of old and new products and missing certain columns, 
        // an exising product's info is being put into a subsquently new product.
        // So, first clear old values...
        foreach ($default_these as $thisvar) {
          ${$thisvar} = '';
        }

        // now do a query to get the record's current contents
        // chadd - 12-14-2010 - redefining this variable everytime it loops must be very inefficient! must be a better way!
        $sql = 'SELECT
					p.products_id					as v_products_id,
					p.products_type					as v_products_type,
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
        if ($ep_supported_mods['map'] == true) { // Manufacturer's Advertised Price
          $sql .= 'p.map_enabled as v_map_enabled,';
          $sql .= 'p.map_price as v_map_price,';
        }
        if ($ep_supported_mods['gppi'] == true) { // Group Pricing Per Item
          $sql .= 'p.products_group_a_price as v_products_group_a_price,';
          $sql .= 'p.products_group_b_price as v_products_group_b_price,';
          $sql .= 'p.products_group_c_price as v_products_group_c_price,';
          $sql .= 'p.products_group_d_price as v_products_group_d_price,';
        }
        if ($ep_supported_mods['excl'] == true) { // Exclusive Product Custom Mod
          $sql .= 'p.products_exclusive as v_products_exclusive,';
        }
        $zco_notifier->notify('EP4_IMPORT_PRODUCT_DEFAULT_SELECT_FIELDS');
        if (count($custom_fields) > 0) {
          foreach ($custom_fields as $field) {
            $sql .= 'p.' . $field . ' as v_' . $field . ',';
          }
        }
        $sql .= 'p.products_weight      as v_products_weight,
					p.products_discount_type		as v_products_discount_type,
					p.products_discount_type_from   as v_products_discount_type_from,
					p.product_is_call				as v_product_is_call,
					p.products_sort_order			as v_products_sort_order,
					p.products_quantity_order_min	as v_products_quantity_order_min,
					p.products_quantity_order_units	as v_products_quantity_order_units,
					p.products_priced_by_attribute	as v_products_priced_by_attribute,
					p.product_is_always_free_shipping	as v_product_is_always_free_shipping,
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
					FROM ' .
                TABLE_PRODUCTS_TO_CATEGORIES . ' as ptoc,' .
                TABLE_CATEGORIES . ' as subc,' .
                TABLE_PRODUCTS . " as p ";
        $zco_notifier->notify('EP4_IMPORT_PRODUCT_DEFAULT_SELECT_TABLES');
        $sql .= "WHERE
					p.products_id      = ptoc.products_id AND
					ptoc.categories_id = subc.categories_id AND ";
        switch (EP4_DB_FILTER_KEY){
          case 'products_model':
            $sql .= "
            p.products_model   = :products_model:";
            break;
          case 'blank_new':
          case 'products_id':
            $sql .= "
            p.products_id   = :products_id:";
            break;
          default:
            $sql .= "
            p.products_model   = :products_model:";
            break;
        }
        $sql = $db->bindVars($sql, ':products_model:', $items[$filelayout['v_products_model']], 'string');
        $sql = $db->bindVars($sql, ':products_id:', $items[$filelayout['v_products_id']], 'integer');
        $result = ep_4_query($sql);
        $product_is_new = true;

        //============================================================================
        // this gets default values for current v_products_model
        // inputs: $items array (file data by column #); $filelayout array (headings by column #); 
        // $row (current TABLE_PRODUCTS data by heading name)
        while ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result) )) { // chadd - this executes once?? why use while-loop??
          $product_is_new = false; // we found products_model in database
          // Get current products descriptions and categories for this model from database
          // $row at present consists of current product data for above fields only (in $sql)

          // since we have a row, the item already exists.
          // let's check and delete it if requested   
          // v_status == 9 is a delete request  
          $continueNextRow = false;
          if ($items[$filelayout['v_status']] == 9) {
            $chosen_key = '';
            switch (EP4_DB_FILTER_KEY) {
              case 'products_model':
                $chosen_key = 'v_products_model';
                break;
              case 'blank_new':
              case 'products_id':
                $chosen_key = 'v_products_id';
                break;
              default:
                $chosen_key = 'v_products_model';
                break;
            }

            $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_DELETED, $items[$filelayout[$chosen_key]]);
            ep_4_remove_product($items[$filelayout[$chosen_key]]);

            $continueNextRow = true;
          }
      
          $zco_notifier->notify('EP4_IMPORT_FILE_EARLY_ROW_PROCESSING');

          if ($continueNextRow == true) {
            continue 2; // short circuit - loop to next record
          }

          // Create variables and assign default values for each language products name, description, url and optional short description
          foreach ($langcode as $key => $lang) {
            $sql2 = 'SELECT * FROM ' . TABLE_PRODUCTS_DESCRIPTION . ' WHERE products_id = :products_id: AND language_id = :language_id:';
            $sql2 = $db->bindVars($sql2, ':products_id:', $row['v_products_id'], 'integer');
            $sql2 = $db->bindVars($sql2, ':language_id:', $lang['id'], 'integer');
            $result2 = ep_4_query($sql2);
            $row2 = ($ep_uses_mysqli ? mysqli_fetch_array($result2) : mysql_fetch_array($result2));
            // create variables (v_products_name_1, v_products_name_2, etc. which corresponds to our column headers) and assign data
            $row['v_products_name_' . $lang['id']] = ep_4_curly_quotes($row2['products_name']);

            // utf-8 conversion of smart-quotes, em-dash, and ellipsis
            /* 				$text = $row2['products_description'];
              $text = str_replace(
              array("\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x93", "\xe2\x80\x94", "\xe2\x80\xa6"),
              array("'", "'", '"', '"', '-', '--', '...'),  $text);
              $row['v_products_description_'.$lang['id']] = $text; // description assigned
             */
            $row['v_products_description_' . $lang['id']] = ep_4_curly_quotes($row2['products_description']); // description assigned				
				
            //$row['v_products_description_'.$lang['id']] = $row2['products_description']; // description assigned
            // if short descriptions exist
            if ($ep_supported_mods['psd'] == true) {
              $row['v_products_short_desc_' . $lang['id']] = ep_4_curly_quotes($row2['products_short_desc']);
            }
            $row['v_products_url_' . $lang['id']] = $row2['products_url']; // url assigned
          }

          // Default values for manufacturers name if exist
          // Note: need to test for '0' and NULL for best compatibility with older version of EP that set blank manufacturers to NULL
          // I find it very strange that the Manufacturer's Name is NOT multi-lingual, but he URL IS!
          if (($row['v_manufacturers_id'] != '0') && ($row['v_manufacturers_id'] != '')) { // if 0, no manufacturer set
            $sql2 = 'SELECT manufacturers_name FROM ' . TABLE_MANUFACTURERS . ' WHERE manufacturers_id = :manufacturers_id:';
            $sql2 = $db->bindVars($sql2, ':manufacturers_id:', $row['v_manufacturers_id'], 'integer');
            $result2 = ep_4_query($sql2);
            $row2 = ($ep_uses_mysqli ? mysqli_fetch_array($result2) : mysql_fetch_array($result2));
            $row['v_manufacturers_name'] = $row2['manufacturers_name'];
          } else {
            $row['v_manufacturers_name'] = '';  // added by chadd 4-7-09 - default name to blank
          }

          // Get tax info for this product
          // We check the value of tax class and title instead of the id
          // Then we add the tax to price if $price_with_tax is set to true
          $row_tax_multiplier = ep_4_get_tax_class_rate($row['v_tax_class_id']);
          $row['v_tax_class_title'] = zen_get_tax_class_title($row['v_tax_class_id']);
          if ($price_with_tax) {
            $row['v_products_price'] = round($row['v_products_price'] + ($row['v_products_price'] * $row_tax_multiplier / 100), 2);
          }

          // ${$thisvar} creates a variable named $thisvar and sets the value to $row value ($v_products_price = $row['v_products_price'];), 
          // which is the existing value for these fields in the database before importing the updated information
          foreach ($default_these as $thisvar) {
            ${$thisvar} = $row[$thisvar];
          }
        } // while ( $row = mysql_fetch_array($result) )
        //============================================================================
	
        // basic error checking
		
        // inputs: $items; $filelayout; $product_is_new
        // chadd - this first condition cannot exist since we short-circuited on delete above
        $chosen_key = '';
        switch (EP4_DB_FILTER_KEY) {
          case 'products_model':
            $chosen_key = 'v_products_model';
            break;
          case 'blank_new':
          case 'products_id':
            $chosen_key = 'v_products_id';
            break;
          default:
            $chosen_key = 'v_products_model';
            break;
        }
        
        if ($items[$filelayout['v_status']] == 9 && zen_not_null($items[$filelayout[$chosen_key]])) {
          // cannot delete product that is not found
          $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_DELETE_NOT_FOUND, $items[$filelayout[$chosen_key]]);
          continue;
        }

        // NEW products must have a 'categories_name_'.$lang['id'] column header, else error out
        if ($product_is_new == true) {
          if (zen_not_null($items[$filelayout[$chosen_key]])) { // must have products_model
            // new products must have a categories_name to be added to the store.
            $categories_name_exists = false; // assume no column defined
            foreach ($langcode as $key => $lang) {
              // test column headers for each language
              if (zen_not_null(trim($items[$filelayout['v_categories_name_' . $lang['id']]]))) { // import column found
                $categories_name_exists = true;
              }
            }
            if (!$categories_name_exists) {
              // let's skip this new product without a master category..
              $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_NOT_FOUND, $items[$filelayout[$chosen_key]], ' new');
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
          ${$key} = $items[$value];
        }

        // chadd - 12-13-2010 - this should allow you to update descriptions only for any given language of the import file!
        // cycle through all defined language codes, but only update those languages defined in the import file
        // Note 11-08-2011: I may remove the "smart_tags_4" function for better performance.
        foreach ($langcode as $lang) {
          $l_id = $lang['id'];
          // products meta tags
          if (isset($filelayout['v_metatags_title_' . $l_id])) {
            $v_metatags_title[$l_id] = ep_4_curly_quotes($items[$filelayout['v_metatags_title_' . $l_id]]);
          }
          if (isset($filelayout['v_metatags_keywords_' . $l_id])) {
            $v_metatags_keywords[$l_id] = ep_4_curly_quotes($items[$filelayout['v_metatags_keywords_' . $l_id]]);
          }
          if (isset($filelayout['v_metatags_description_' . $l_id])) {
            $v_metatags_description[$l_id] = ep_4_curly_quotes($items[$filelayout['v_metatags_description_' . $l_id]]);
          }

          // products name, description, url, and optional short description
          // smart_tags_4 ... removed - chadd 11-18-2011 - will look into this feature at a future date
          // chadd 12-09-2011 - added some error checking on field lengths
          // 4-10-2012 - changed to handle name, description and url independently
          if (isset($filelayout['v_products_name_' . $l_id])) { // do for each language in our upload file if exist
            // check products name length and display warning on error, but still process record
            $v_products_name[$l_id] = ep_4_curly_quotes($items[$filelayout['v_products_name_' . $l_id]]);
            if (mb_strlen($v_products_name[$l_id]) > $products_name_max_len) {
              $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_PRODUCTS_NAME_LONG, $v_products_model, $v_products_name[$l_id], $products_name_max_len);
              $ep_warning_count++;
            }
          } else { // column doesn't exist in the IMPORT file
            // and product is new
            if ($product_is_new) {
              $v_products_name[$l_id] = "";
            }
          }
          if (isset($filelayout['v_products_description_' . $l_id])) { // do for each language in our upload file if exist
            // utf-8 conversion of smart-quotes, em-dash, en-dash, and ellipsis
            $v_products_description[$l_id] = ep_4_curly_quotes($items[$filelayout['v_products_description_' . $l_id]]);
            if ($ep_supported_mods['psd'] == true) { // if short descriptions exist
              $v_products_short_desc[$l_id] = ep_4_curly_quotes($items[$filelayout['v_products_short_desc_' . $l_id]]);
            }
          } else { // column doesn't exist in the IMPORT file
            // and product is new
            if ($product_is_new) {
              $v_products_description[$l_id] = "";
              // if short descriptions exist
              if ($ep_supported_mods['psd'] == true) {
                $v_products_short_desc[$l_id] = "";
              }
            }
          }
          if (isset($filelayout['v_products_url_' . $l_id])) { // do for each language in our upload file if exist
            $v_products_url[$l_id] = $items[$filelayout['v_products_url_' . $l_id]];
            // check products url length and display warning on error, but still process record
            if (mb_strlen($v_products_url[$l_id]) > $products_url_max_len) {
              $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_PRODUCTS_URL_LONG, $v_products_model, $v_products_url[$l_id], $products_url_max_len);
              $ep_warning_count++;
            }
          } else { // column doesn't exist in the IMPORT file
            // and product is new
            if ($product_is_new) {
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
          $v_products_price = round($v_products_price / (1 + ( $row_tax_multiplier * $price_with_tax / 100) ), 4);
        }

        // if $v_products_quantity is null, set it to: 0
        if (trim($v_products_quantity) == '') {
          $v_products_quantity = 0; // new products are set to quanitity '0', updated products are set with default_these() values
        }

        // date variables - chadd ... these should really be products_date_available and products_date_added for clarity
        // date_avail is only set to show when out of stock items will be available, else it is NULL
        // 11-19-2010 fixed this bug where NULL wasn't being correctly set
        $v_date_avail = ($v_date_avail == true && $v_date_avail > "0001-01-01") ? date("Y-m-d H:i:s", strtotime($v_date_avail)) : "NULL";

        // if products has been added before, do not change, else use current time stamp
        $v_date_added = ($v_date_added == true & $v_date_added > "0001-01-01") ? date("Y-m-d H:i:s", strtotime($v_date_added)) : "CURRENT_TIMESTAMP";

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
        if (EASYPOPULATE_4_CONFIG_ZERO_QTY_INACTIVE == 'true' && $v_products_quantity <= 0) {
          $v_db_status = '0';
        }

        // check for empty $v_products_image
        // if the v_products_image column exists and no image is set, then
        // apply the default "no image" image.
        // mc12345678 NOT SO SURE THIS SHOULD BE SET TO THE NO_IMAGE Image
        //  Automatically as this actually modifies the database rather than
        //  allow the system to operate independent Sunday
        if (trim($v_products_image) == '') {
          $v_products_image = (EP4_REPLACE_BLANK_IMAGE === 'true' ? PRODUCTS_IMAGE_NO_IMAGE : '');
        }

        // check size of v_products_model, loop on error
        if (mb_strlen($v_products_model) > $products_model_max_len) {
          $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_PRODUCTS_MODEL_LONG, $v_products_model, $products_model_max_len);
          $ep_error_count++;
          continue; // short-circuit on error
        }

        // BEGIN: Manufacturer's Name
        // convert the manufacturer's name into id's for the database
        if (isset($v_manufacturers_name) && ($v_manufacturers_name != '') && (mb_strlen($v_manufacturers_name) <= $manufacturers_name_max_len)) {
          $sql = "SELECT man.manufacturers_id AS manID FROM " . TABLE_MANUFACTURERS . " AS man WHERE man.manufacturers_name = :manufacturers_name: LIMIT 1";
          $sql = $db->bindVars($sql, ':manufacturers_name:', $v_manufacturers_name, 'string');
          
          $result = ep_4_query($sql);
          if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result) )) {
            $v_manufacturers_id = $row['manID']; // this id goes into the products table
          } else { // It is set to autoincrement, do not need to fetch max id
            $sql = "INSERT INTO " . TABLE_MANUFACTURERS . " (manufacturers_name, date_added, last_modified)
							VALUES (:manufacturers_name:, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
            $sql = $db->bindVars($sql, ':manufacturers_name:', ep_4_curly_quotes($v_manufacturers_name), 'string');
            $result = ep_4_query($sql);
            if ($result) {
              zen_record_admin_activity('Inserted manufacturer ' . addslashes($v_manufacturers_name) . ' via EP4.', 'info');
            }
            $v_manufacturers_id = ($ep_uses_mysqli ? mysqli_insert_id($db->link) : mysql_insert_id()); // id is auto_increment, so can use this function
				
            // BUG FIX: TABLE_MANUFACTURERS_INFO need an entry for each installed language! chadd 11-14-2011
            // This is not a complete fix, since we are not importing manufacturers_url
            foreach ($langcode as $lang) {
              $l_id = $lang['id'];
              $sql = "INSERT INTO " . TABLE_MANUFACTURERS_INFO . " (manufacturers_id, languages_id, manufacturers_url)
								VALUES (:manufacturers_id:, :languages_id:, :manufacturers_url:)"; // seems we are skipping manufacturers url
              $sql = $db->bindVars($sql, ':manufacturers_id:', $v_manufacturers_id, 'integer');
              $sql = $db->bindVars($sql, ':languages_id:', $l_id, 'integer');
              $sql = $db->bindVars($sql, ':manufacturers_url:', '', 'string');
              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Inserted manufacturers info ' . (int) $v_manufacturers_id . ' via EP4.', 'info');
              }
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
          if (zen_not_null(trim($items[$filelayout['v_categories_name_' . $lang['id']]]))) { // import column found
            $categories_name_exists = true; // at least one language column defined
          }
        }
        if ($categories_name_exists) { // we have at least 1 language column
          // chadd - 12-14-2010 - $categories_names_array[] has our category names
          // $categories_delimiter = "\x5e"; // add this to configuration variables
          $categories_delimiter = $category_delimiter; // add this to configuration variables
          // get all defined categories
          foreach ($langcode as $key => $lang) {
            // iso-8859-1
            // $categories_names_array[$lang['id']] = explode($categories_delimiter,$items[$filelayout['v_categories_name_'.$lang['id']]]); 
            // utf-8 
            $categories_names_array[$lang['id']] = mb_split(preg_quote($categories_delimiter), $items[$filelayout['v_categories_name_' . $lang['id']]]);

            // get the number of tokens in $categories_names_array[]
            $categories_count[$lang['id']] = count($categories_names_array[$lang['id']]);
            // check category names for length violation. abort on error
            if ($categories_count[$lang['id']] > 0) { // only check $categories_name_max_len if $categories_count[$lang['id']] > 0
              for ($category_index = 0; $category_index < $categories_count[$lang['id']]; $category_index++) {
                if (mb_strlen($categories_names_array[$lang['id']][$category_index]) > $categories_name_max_len) {
                  $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_NAME_LONG, ${$chosen_key}, $categories_names_array[$lang['id']][$category_index], $categories_name_max_len);
                  $ep_error_count++;
                  continue 3; // skip to next record
                }
              }
            }
          } // foreach
			
          // need check on $categories_count to ensure all counts are equal
          if (count($categories_count) > 1) { // check elements 
            $categories_count_value = $categories_count[$langcode[1]['id']];
            foreach ($langcode as $key => $lang) {
              $v_categories_name_check = 'v_categories_name_' . $lang['id'];
              if (isset(${$v_categories_name_check})) {
                if (($categories_count_value != $categories_count[$lang['id']]) && ($categories_count[$lang['id']] != 0)) {
                  //$display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_NAME_LONG, 
                  //$v_products_model, $categories_names_array[$lang['id']][$category_index], $categories_name_max_len);
                  $display_output .= "<br>Error: Unbalanced Categories defined in: " . $items[$filelayout['v_categories_name_' . $lang['id']]];
                  $ep_error_count++;
                  continue 2; // skip to next record
                }
              }
            } // foreach
          }
        }
        // start with first defined language... (does not have to be 1)
        $lid = $langcode[1]['id'];
        $v_categories_name_var = 'v_categories_name_' . $lid; // ${$v_categories_name_var} >> $v_categories_name_1, $v_categories_name_2, etc.
        if (isset(${$v_categories_name_var})) { // does column header exist?
          // start from the highest possible category and work our way down from the parent
          $v_categories_id = 0;
          $theparent_id = 0; // 0 is top level parent
//category_index, 					// $categories_delimiter = "^"; // add this to configuration variables
          for ($category_index = 0; $category_index < $categories_count[$lid]; $category_index++) {
            $thiscategoryname = ep_4_curly_quotes($categories_names_array[$lid][$category_index]); // category name - 5-3-2012 added curly quote fix
            $sql = "SELECT cat.categories_id
							FROM " . TABLE_CATEGORIES . " AS cat, 
								 " . TABLE_CATEGORIES_DESCRIPTION . " AS des
							WHERE
								cat.categories_id = des.categories_id AND
								des.language_id = :language_id: AND
								cat.parent_id = :parent_id: AND
								des.categories_name = :categories_name: LIMIT 1";
            $sql = $db->bindVars($sql, ':language_id:', $lid, 'integer');
            $sql = $db->bindVars($sql, ':parent_id:', $theparent_id, 'integer');
            $sql = $db->bindVars($sql, ':categories_name:', $thiscategoryname, 'string');
            
            $result = ep_4_query($sql);
            $row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result));
            // if $row is not null, we found entry, so retrive info
            if ($row != '') { // category exists
              $parent_category_id = $theparent_id;
              foreach ($row as $item) {
                $thiscategoryid = $item; // array of data
                $current_category_id = $thiscategoryid;
              }
              foreach ($langcode as $key => $lang2) {
                $v_categories_name_check = 'v_categories_name_' . $lang2['id'];
                if (isset(${$v_categories_name_check})) { // update
                  $cat_lang_id = $lang2['id'];
                  $sql = "UPDATE " . TABLE_CATEGORIES_DESCRIPTION . " SET 
											categories_name = :categories_name:
										WHERE
											categories_id   = :categories_id: AND
											language_id     = :language_id:";
                  $sql = $db->bindVars($sql, ':categories_name:', ep_4_curly_quotes($categories_names_array[$cat_lang_id][$category_index]), 'string');
                  $sql = $db->bindVars($sql, ':categories_id:', $thiscategoryid, 'integer');
                  $sql = $db->bindVars($sql, ':language_id:', $cat_lang_id, 'integer');
                  $result = ep_4_query($sql);
                  if ($result) {
                    zen_record_admin_activity('Updated category description ' . (int) $thiscategoryid . ' via EP4.', 'info');
                  }
                }
              }
            } else { // otherwise add new category
              // get next available categoies_id
              $sql = "SELECT MAX(categories_id) max FROM " . TABLE_CATEGORIES;
              $result = ep_4_query($sql);
              $row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result));
              $max_category_id = $row['max'] + 1;
              // if database is empty, start at 1
              if (!is_numeric($max_category_id)) {
                $max_category_id = 1;
              }
              // TABLE_CATEGORIES has 1 entry per categories_id
              $sql = "INSERT INTO " . TABLE_CATEGORIES . "(categories_id, categories_image, parent_id, sort_order, date_added, last_modified
								) VALUES (
								:categories_id:, '', :parent_id:, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
								)";
              $sql = $db->bindVars($sql, ':categories_id:', $max_category_id, 'integer');
              $sql = $db->bindVars($sql, ':parent_id:', $theparent_id, 'integer');
              $current_category_id = $max_category_id;
              $parent_category_id = $theparent_id;
              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Inserted category ' . (int) $max_category_id . ' via EP4.', 'info');
              }
              // TABLE_CATEGORIES_DESCRIPTION has an entry for EACH installed languag
              // Check for multiple categories language. If a column is not defined, default to the main language:
              // categories_name = '".addslashes($thiscategoryname)."'";
              // else, set to that langauges category entry:
              // categories_name = '".addslashes($categories_names_array[$lid][$category_index])."'";
              foreach ($langcode as $key => $lang2) {
                $v_categories_name_check = 'v_categories_name_' . $lang2['id'];
                if (isset(${$v_categories_name_check})) { // update	
                  $cat_lang_id = $lang2['id'];
                  $sql = "INSERT INTO " . TABLE_CATEGORIES_DESCRIPTION . " SET 
										categories_id   = :categories_id:,
										language_id     = :language_id:,
										categories_name = :categories_name:";
                  $sql = $db->bindVars($sql, ':categories_name:', ep_4_curly_quotes($categories_names_array[$cat_lang_id][$category_index]), 'string');
                } else { // column is missing, so default to defined column's value
                  $cat_lang_id = $lang2['id'];
                  $sql = "INSERT INTO " . TABLE_CATEGORIES_DESCRIPTION . " SET 
										categories_id   = :categories_id:,
										language_id     = :language_id:,
										categories_name = :categories_name:";
                  $sql = $db->bindVars($sql, ':categories_name:', ep_4_curly_quotes($thiscategoryname), 'string');
                }
                $sql = $db->bindVars($sql, ':categories_id:', $max_category_id, 'integer');
                $sql = $db->bindVars($sql, ':language_id:', $cat_lang_id, 'integer');
                $result = ep_4_query($sql);
                if ($result) {
                  zen_record_admin_activity('Inserted category description ' . (int) $max_category_id . ' via EP4.', 'info');
                }
              }
          $thiscategoryid = $max_category_id;
        }
        // the current catid is the next level's parent
        $theparent_id = $thiscategoryid;
        // keep setting this, we need the lowest level category ID later
        $v_categories_id = $thiscategoryid; 
      } // ( $category_index=0; $category_index<$catego.....
    } // (isset($$v_categories_name_var))
        $zco_notifier->notify('EP4_IMPORT_AFTER_CATEGORY');
        // END: CATEGORIES2 ===============================================================================================	
        // HERE ==========================>
        // BEGIN: record_artists
        if (isset($filelayout['v_artists_name'])) {
          if (isset($v_artists_name) && ($v_artists_name != '') && (mb_strlen($v_artists_name) <= $artists_name_max_len)) {
            $sql = "SELECT artists_id AS artistsID FROM " . TABLE_RECORD_ARTISTS . " WHERE artists_name = :artists_name: LIMIT 1";
            $sql = $db->bindVars($sql, ':artists_name:', ep_4_curly_quotes($v_artists_name), 'string');
            $result = ep_4_query($sql);
            if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result) )) {
              $v_artists_id = $row['artistsID']; // this id goes into the product_music_extra table
              $sql = "UPDATE " . TABLE_RECORD_ARTISTS . " SET 
								artists_image = :artists_image:,
								last_modified = CURRENT_TIMESTAMP
								WHERE artists_id = :artists_id:";
              $sql = $db->bindVars($sql, ':artists_image:', $v_artists_image, 'string');
              $sql = $db->bindVars($sql, ':artists_id:', $v_artists_id, 'integer');
              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Updated record artist ' . (int) $v_artists_id . ' via EP4.', 'info');
              }
              foreach ($langcode as $lang) {
                $l_id = $lang['id'];
                // if the column is not in the import file, then don't modify
                //  or update that particular language's value.  This way 
                //  only the columns desired to be updated are modified, not
                //  all columns and thus require on any update to have all
                //  columns present even those not being updated.
                if (!isset($filelayout['v_artists_url_' . $l_id])) { 
                  continue;
                }
                $sql = "UPDATE " . TABLE_RECORD_ARTISTS_INFO . " SET
									artists_url = :artists_url:
									WHERE artists_id = :artists_id: AND languages_id = :languages_id:";
                $sql = $db->bindVars($sql, ':artists_id:', $v_artists_id, 'integer');
                $sql = $db->bindVars($sql, ':artists_url:', $items[$filelayout['v_artists_url_' . $l_id]], 'string');
                $sql = $db->bindVars($sql, ':languages_id:', $l_id, 'integer');
                $result = ep_4_query($sql);
                if ($result) {
                  zen_record_admin_activity('Updated record artist info ' . (int) $v_artists_id . ' via EP4.', 'info');
                }
              }
            } else { // It is set to autoincrement, do not need to fetch max id
              $sql = "INSERT INTO " . TABLE_RECORD_ARTISTS . " (artists_name, artists_image, date_added, last_modified)
								VALUES (:artists_name:, :artists_image:, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
              $sql = $db->bindVars($sql, ':artists_name:', ep_4_curly_quotes($v_artists_name), 'string');
              $sql = $db->bindVars($sql, ':artists_image:', $v_artists_image, 'string');
              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Inserted record artist ' . $addslashes(ep_4_curly_quotes($v_artists_name)) . ' via EP4.', 'info');
              }
              $v_artists_id = ($ep_uses_mysqli ? mysqli_insert_id($db->link) : mysql_insert_id()); // id is auto_increment, so can use this function
              foreach ($langcode as $lang) {
                $l_id = $lang['id'];
                // If the artists_url column for this language was not in the file,
                //  then do not modify the setting... But, also make sure
                //  using the correct "check" mc12345678 2015-12-30
                //  $filelayout chosen as it is to return an array represeting
                //  the position in the file that is translated to the data
                //  at that position.  For an insert (ie. new record), if 
                //  all of the data is not provided, then will populate with
                //  the data of the "first" language (which should be included)
                //  if the particular artists_url is provided then that is used.
                $sql = "INSERT INTO " . TABLE_RECORD_ARTISTS_INFO . " (artists_id, languages_id, artists_url)
									VALUES (:artists_id:, :languages_id:, :artists_url:)"; // seems we are skipping manufacturers url
                $sql = $db->bindVars($sql, ':artists_id:', $v_artists_id, 'integer');
                $sql = $db->bindVars($sql, ':languages_id:', $l_id, 'integer');
                $sql = $db->bindVars($sql, ':artists_url:', (isset($filelayout['v_artists_url_' . $l_id]) ? $items[$filelayout['v_artists_url_' . $l_id]] : $items[$filelayout['v_artists_url_' . $lid]]), 'string');
                $result = ep_4_query($sql);
                if ($result) {
                  zen_record_admin_activity('Inserted record artists info ' . (int) $v_artists_id . ' via EP4.', 'info');
                }
              }
            }
          } else { // $v_artists_name == '' or name length violation
            if (mb_strlen($v_artists_name) > $artists_name_max_len) {
              $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_ARTISTS_NAME_LONG, $v_artists_name, $artists_name_max_len);
              $ep_error_count++;
              continue;
            }
            $v_artists_id = 0; // chadd - zencart uses artists_id = '0' for no assisgned artists
          }
        }
        // END: record_artists
		
        // HERE ==========================>
        // BEGIN: record_company
        if (isset($filelayout['v_record_company_name'])) {
          if (isset($v_record_company_name) && ($v_record_company_name != '') && (mb_strlen($v_record_company_name) <= $record_company_name_max_len)) {
            $sql = "SELECT record_company_id AS record_companyID FROM " . TABLE_RECORD_COMPANY . " WHERE record_company_name = :record_company_name: LIMIT 1";
            $sql = $db->bindVars($sql, ':record_company_name:', ep_4_curly_quotes($v_record_company_name), 'string');
            $result = ep_4_query($sql);
            if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result) )) {
              $v_record_company_id = $row['record_companyID']; // this id goes into the product_music_extra table
              $sql = "UPDATE " . TABLE_RECORD_COMPANY . " SET 
								record_company_image = :record_company_image:,
								last_modified = CURRENT_TIMESTAMP
								WHERE record_company_id = :record_company_id:";
              $sql = $db->bindVars($sql, ':record_company_image:', $v_record_company_image, 'string');
              $sql = $db->bindVars($sql, ':record_company_id:', $v_record_company_id, 'integer');
              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Updated record company ' . (int) $v_record_company_id . ' via EP4.', 'info');
              }
              foreach ($langcode as $lang) {
                $l_id = $lang['id'];
                if (!isset($filelayout['v_record_company_url_' . $l_id])) {
                  continue;
                }
                $sql = "UPDATE " . TABLE_RECORD_COMPANY_INFO . " SET
									record_company_url = :record_company_url:
									WHERE record_company_id = :record_company_id: AND languages_id = :languages_id:";
                $sql = $db->bindVars($sql, ':record_company_url:', $items[$filelayout['v_record_company_url_' . $l_id]], 'string');
                $sql = $db->bindVars($sql, ':record_company_id:', $v_record_company_id, 'integer');
                $sql = $db->bindVars($sql, ':languages_id:', $l_id, 'integer');
                $result = ep_4_query($sql);
                if ($result) {
                  zen_record_admin_activity('Updated record company info ' . (int) $v_record_company_id . ' via EP4.', 'info');
                }
              }
            } else { // It is set to autoincrement, do not need to fetch max id
              $sql = "INSERT INTO " . TABLE_RECORD_COMPANY . " (record_company_name, record_company_image, date_added, last_modified)
								VALUES (:record_company_name:, :record_company_image:, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
              $sql = $db->bindVars($sql, ':record_company_name:', ep_4_curly_quotes($v_record_company_name), 'string');
              $sql = $db->bindVars($sql, ':record_company_image:', $v_record_company_image, 'string');
              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Inserted record company ' . addslashes(ep_4_curly_quotes($v_record_company_name)) . ' via EP4.', 'info');
              }
              $v_record_company_id = ($ep_uses_mysqli ? mysqli_insert_id($db->link) : mysql_insert_id()); // id is auto_increment, so can use this function
              foreach ($langcode as $lang) {
                $l_id = $lang['id'];
                $sql = "INSERT INTO " . TABLE_RECORD_COMPANY_INFO . " (record_company_id, languages_id, record_company_url)
									VALUES (:record_company_id:, :languages_id:, :record_company_url:)"; // seems we are skipping manufacturers url
                $sql = $db->bindVars($sql, ':record_company_id:', $v_record_company_id, 'integer');
                $sql = $db->bindVars($sql, ':languages_id:', $l_id, 'integer');
                $sql = $db->bindVars($sql, ':record_company_url:',  (isset($filelayout['v_record_company_url_' . $l_id]) ? $items[$filelayout['v_record_company_url_' . $l_id]] : $items[$filelayout['v_record_company_url_' . $lid]]), 'string');
                $result = ep_4_query($sql);
                if ($result) {
                  zen_record_admin_activity('Inserted record company info ' . (int) $v_record_company_id . ' via EP4.', 'info');
                }
              }
            }
          } else { // $v_record_company_name == '' or name length violation
            if (mb_strlen($v_record_company_name) > $record_company_name_max_len) {
              $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_RECORD_COMPANY_NAME_LONG, $v_record_company_name, $record_company_name_max_len);
              $ep_error_count++;
              continue;
            }
            $v_record_company_id = 0; // record_company_id = '0' for no assisgned artists
          }
        }
        // END: record_company

        // HERE ==========================>
        // BEGIN: music_genre
        if (isset($filelayout['v_music_genre_name'])) {
          if (isset($v_music_genre_name) && ($v_music_genre_name != '') && (mb_strlen($v_music_genre_name) <= $music_genre_name_max_len)) {
            $sql = "SELECT music_genre_id AS music_genreID FROM " . TABLE_MUSIC_GENRE . " WHERE music_genre_name = :music_genre_name: LIMIT 1";
            $sql = $db->bindVars($sql, ':music_genre_name:', $v_music_genre_name, 'string');
            $result = ep_4_query($sql);
            if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result) )) {
              $v_music_genre_id = $row['music_genreID']; // this id goes into the product_music_extra table
            } else { // It is set to autoincrement, do not need to fetch max id
              $sql = "INSERT INTO " . TABLE_MUSIC_GENRE . " (music_genre_name, date_added, last_modified)
								VALUES (:music_genre_name:, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
              $sql = $db->bindVars($sql, ':music_genre_name:', $v_music_genre_name, 'string');
              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Inserted music genre ' . addslashes($v_music_genre_name) . ' via EP4.', 'info');
              }
              $v_music_genre_id = ($ep_uses_mysqli ? mysqli_insert_id($db->link) : mysql_insert_id()); // id is auto_increment
            }
          } else { // $v_music_genre_name == '' or name length violation
            if (mb_strlen($v_music_genre_name) > $music_genre_name_max_len) {
              $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_MUSIC_GENRE_NAME_LONG, $v_music_genre_name, $music_genre_name_max_len);
              $ep_error_count++;
              continue;
            }
            $v_music_genre_id = 0; // chadd - zencart uses genre_id = '0' for no assisgned artists
          }
        }
        // END: music_genre

        $chosen_key = '';
        switch (EP4_DB_FILTER_KEY) {
          case 'products_model':
            $chosen_key = 'v_products_model';
            break;
          case 'blank_new':
          case 'products_id':
            $chosen_key = 'v_products_id';
            break;
          default:
            $chosen_key = 'v_products_model';
            break;
        }
        	
        // insert new, or update existing, product
        if (${$chosen_key} != "" || ($chosen_key == 'v_products_id' && defined('EP4_DB_FILTER_KEY') && EP4_DB_FILTER_KEY === 'blank_new' && !zen_not_null(${$chosen_key}))) { // products_model exists!
          // First we check to see if this is a product in the current db.
          $sql = "SELECT products_id FROM " . TABLE_PRODUCTS;
          switch ($chosen_key) {
            case 'v_products_model':
              $sql .= " WHERE (products_model = :products_model:) LIMIT 1";
              break;
            case 'v_products_id':
              $sql .= " WHERE (products_id = :products_id:) LIMIT 1";
              break;
            default:
              $sql .= " WHERE (products_model = :products_model:) LIMIT 1";
              break;
          }
          $sql = $db->bindVars($sql, ':products_model:', $v_products_model, 'string');
          $sql = $db->bindVars($sql, ':products_id:', $v_products_id, 'integer');
          $result = ep_4_query($sql);
          if (($ep_uses_mysqli ? mysqli_num_rows($result) : mysql_num_rows($result)) == 0) { // new item, insert into products
            $v_date_added = ($v_date_added == 'NULL') ? CURRENT_TIMESTAMP : $v_date_added;
            $sql = "SHOW TABLE STATUS LIKE '" . TABLE_PRODUCTS . "'";
            $result = ep_4_query($sql);
            $row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result));
            $max_product_id = $row['Auto_increment'];
            if (!is_numeric($max_product_id)) {
              $max_product_id = 1;
            }
            if ($chosen_key == 'v_products_model' || ($chosen_key == 'v_products_id' && defined('EP4_DB_FILTER_KEY') && EP4_DB_FILTER_KEY === 'blank_new' && ${$chosen_key} == "")) {
              $v_products_id = $max_product_id;
            }
            if ($v_artists_name <> '') {
              $v_products_type = 2; // 2 = music Product - Music
            } else {
              $v_products_type = 1; // 1 = standard product
            }

            $zco_notifier->notify('EP4_IMPORT_FILE_NEW_PRODUCT_PRODUCT_TYPE');

// mc12345678, new item need to address products_id assignment as it is provided
            $query = "INSERT INTO " . TABLE_PRODUCTS . " SET
							products_model					= :products_model:,
							products_type     		        = :products_type:,
							products_price					= :products_price:, ";
            if ($chosen_key == 'v_products_id' && !in_array(substr($chosen_key, 2), $custom_fields) && zen_not_null(${$chosen_key})) {
              $query .= "products_id  = :products_id:, ";
            }
            if ($ep_supported_mods['uom'] == true) { // price UOM mod
              $query .= "products_price_uom = :products_price_uom:, ";
            }
            if ($ep_supported_mods['upc'] == true) { // UPC Code mod
              $query .= "products_upc = :products_upc:, ";
            }
            if ($ep_supported_mods['gpc'] == true) { // Google Product Category for Google Merhcant Center - chadd 10-1-2011
              $query .= "products_gpc = :products_gpc:, ";
            }
            if ($ep_supported_mods['msrp'] == true) { // Manufacturer's Suggested Retail Price
              $query .= "products_msrp = :products_msrp:, ";
            }
            if ($ep_supported_mods['map'] == true) { // Manufacturer's Advertised Price
              $query .= "map_enabled = :map_enabled:, ";
              $query .= "map_price = :map_price:, ";
            }
            if ($ep_supported_mods['gppi'] == true) { // Group Pricing Per Item
              $query .= "products_group_a_price = :products_group_a_price:, ";
              $query .= "products_group_b_price = :products_group_b_price:, ";
              $query .= "products_group_c_price = :products_group_c_price:, ";
              $query .= "products_group_d_price = :products_group_d_price:, ";
            }
            if ($ep_supported_mods['excl'] == true) { // Exclusive Product custom mod
              $query .= "products_exclusive = :products_exclusive:, ";
            }
            if (count($custom_fields) > 0) {
              foreach ($custom_fields as $field) {
                if ($field != 'products_id' || ( $field == 'products_id' && defined('EP4_DB_FILTER_KEY') && EP4_DB_FILTER_KEY === 'blank_new' && zen_not_null(${$chosen_key}))) {
                  $value = 'v_' . $field;
                  if ($filelayout[$value]) {
                    $query .= ":field: = :value:, ";
                    $query = $db->bindVars($query, ':field:', $field, 'noquotestring');
                    $query = $db->bindVars($query, ':value:', ${$value}, ('string'));
                  }
                }
              }
            }
            $query .= "products_image			= :products_image:,
							products_weight					= :products_weight:,
							products_discount_type          = :products_discount_type:,
							products_discount_type_from     = :products_discount_type_from:,
							product_is_call                 = :product_is_call:,
							products_sort_order             = :products_sort_order:,
							products_quantity_order_min     = :products_quantity_order_min:,
							products_quantity_order_units   = :products_quantity_order_units:,
							products_priced_by_attribute	= :products_priced_by_attribute:,
							product_is_always_free_shipping	= :product_is_always_free_shipping:,
							products_tax_class_id			= :tax_class_id:,
							products_date_available			= :date_avail:, 
							products_date_added				= :date_added:,
							products_last_modified			= CURRENT_TIMESTAMP,
							products_quantity				= :products_quantity:,
							master_categories_id			= :categories_id:,
							manufacturers_id				= :manufacturers_id:,
							products_status					= :products_status:,
							metatags_title_status			= :metatags_title_status:,
							metatags_products_name_status	= :metatags_products_name_status:,
							metatags_model_status			= :metatags_model_status:,
							metatags_price_status			= :metatags_price_status:,
							metatags_title_tagline_status	= :metatags_title_tagline_status:";
            $query = $db->bindVars($query, ':products_model:', $v_products_model , 'string');
            $query = $db->bindVars($query, ':products_type:', $v_products_type , 'integer');
            $query = $db->bindVars($query, ':products_price:', $v_products_price , 'currency');
            $query = $db->bindVars($query, ':products_price_uom:', $v_products_price_uom , 'currency');
            $query = $db->bindVars($query, ':products_id:', $v_products_id, 'integer');
            $query = $db->bindVars($query, ':products_upc:', $v_products_upc, 'string');
            $query = $db->bindVars($query, ':products_gpc:', $v_products_gpc, 'string');
            $query = $db->bindVars($query, ':products_msrp:', $v_products_msrp, 'string');
            $query = $db->bindVars($query, ':map_enabled:', $v_map_enabled, 'string');
            $query = $db->bindVars($query, ':map_price:', $v_map_price, 'string');
            $query = $db->bindVars($query, ':products_group_a_price:', $v_products_group_a_price, 'string');
            $query = $db->bindVars($query, ':products_group_b_price:', $v_products_group_b_price, 'string');
            $query = $db->bindVars($query, ':products_group_c_price:', $v_products_group_c_price, 'string');
            $query = $db->bindVars($query, ':products_group_d_price:', $v_products_group_d_price, 'string');
            $query = $db->bindVars($query, ':products_exclusive:', $v_products_exclusive, 'string');
            $query = $db->bindVars($query, ':products_image:', $v_products_image, 'string');
            $query = $db->bindVars($query, ':products_weight:', $v_products_weight, 'string');
            $query = $db->bindVars($query, ':products_discount_type:', $v_products_discount_type, 'string');
            $query = $db->bindVars($query, ':products_discount_type_from:', $v_products_discount_type_from, 'string');
            $query = $db->bindVars($query, ':product_is_call:', $v_product_is_call, 'string');
            $query = $db->bindVars($query, ':products_sort_order:', $v_products_sort_order, 'string');
            $query = $db->bindVars($query, ':products_quantity_order_min:', $v_products_quantity_order_min, 'float');
            $query = $db->bindVars($query, ':products_quantity_order_units:', $v_products_quantity_order_units, 'float');
            $query = $db->bindVars($query, ':products_priced_by_attribute:', $v_products_priced_by_attribute, 'string');
            $query = $db->bindVars($query, ':product_is_always_free_shipping:', $v_product_is_always_free_shipping, 'string');
            $query = $db->bindVars($query, ':tax_class_id:', $v_tax_class_id, 'integer');
            $query = $db->bindVars($query, ':date_avail:', $v_date_avail, ($v_date_avail == "NULL" ? 'noquotestring' : 'string'));
            $query = $db->bindVars($query, ':date_added:', $v_date_added, ($v_date_added == "CURRENT_TIMESTAMP" ? 'noquotestring' : 'string'));
            $query = $db->bindVars($query, ':products_quantity:', $v_products_quantity, 'float');
            $query = $db->bindVars($query, ':categories_id:', $v_categories_id, 'integer');
            $query = $db->bindVars($query, ':manufacturers_id:', $v_manufacturers_id, 'integer');
            $query = $db->bindVars($query, ':products_status:', $v_db_status, 'string');
            $query = $db->bindVars($query, ':metatags_title_status:', $v_metatags_title_status, 'string');
            $query = $db->bindVars($query, ':metatags_products_name_status:', $v_metatags_products_name_status, 'string');
            $query = $db->bindVars($query, ':metatags_model_status:', $v_metatags_model_status, 'string');
            $query = $db->bindVars($query, ':metatags_price_status:', $v_metatags_price_status, 'string');
            $query = $db->bindVars($query, ':metatags_title_tagline_status:', $v_metatags_title_tagline_status, 'string');

            $result = ep_4_query($query);
            if ($result == true) {
              // need to change to an log file, this is gobbling up memory! chadd 11-14-2011
              zen_record_admin_activity('New product ' . (int) $v_products_id . ' added via EP4.', 'info');

              if ($ep_feedback == true) {
                $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_NEW_PRODUCT, ${$chosen_key});
              }
              $ep_import_count++;
              // PRODUCT_MUSIC_EXTRA
              if ($v_products_type == '2') {
                $sql_music_extra = ep_4_query("SELECT * FROM " . TABLE_PRODUCT_MUSIC_EXTRA . " WHERE (products_id = :products_id:) LIMIT 1");
                $sql_music_extra = $db->bindVars($sql_music_extra, ':products_id:', $v_products_id, 'integer');
                if (($ep_uses_mysqli ? mysqli_num_rows($sql_music_extra) : mysql_num_rows($sql_music_extra)) == 0) { // new item, insert into products
                  $query = "INSERT INTO " . TABLE_PRODUCT_MUSIC_EXTRA . " SET
										products_id			= :products_id:,
										artists_id     		= :artists_id:,
										record_company_id	= :record_company_id:,
										music_genre_id		= :music_genre_id:";
                  $query = $db->bindVars($query, ':products_id:', $v_products_id, 'integer');
                  $query = $db->bindVars($query, ':artists_id:', $v_artists_id, 'integer');
                  $query = $db->bindVars($query, ':record_company_id:', $v_record_company_id, 'integer');
                  $query = $db->bindVars($query, ':music_genre_id:', $v_music_genre_id, 'integer');
                  $result = ep_4_query($query);
                  if ($result) {
                    zen_record_admin_activity('Inserted product music extra ' . (int) $v_products_id . ' via EP4.', 'info');
                  }
                }
              }
            } else {
              $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_NEW_PRODUCT_FAIL, ${$chosen_key});
              $ep_error_count++;
              continue; // new categories however have been created by now... Adding into product table needs to be 1st action?
            }
            // needs to go into log file chadd 11-14-2011
            if ($ep_feedback == true) {
              foreach ($items as $col => $summary) {
                if ($col == $filelayout[$chosen_key]){
                  continue;
                }
                $display_output .= print_el_4($summary);
              }
            }
          } else { // existing product, get the id from the query and update the product data
            // if date added is null, let's keep the existing date in db..
            $v_date_added = (!zen_not_null($v_date_added) ? $row['v_date_added'] : $v_date_added); // if NULL, use date in db
            $v_date_added = zen_not_null($v_date_added) ? $v_date_added : CURRENT_TIMESTAMP; // if updating, but date added is null, we use today's date
            $row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result));
            $v_products_id = $row['products_id'];
            $row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result));
            // CHADD - why is master_categories_id not being set on update??? //mc12345678 Because on update, the category was already set, on the first upload it was set to be the master_category_id, but now the product is merely updated to add the category(ies) to it, not to update the master_category_id which should be done on a separate transaction.
            $query = "UPDATE " . TABLE_PRODUCTS . " SET
							products_price = :products_price:, ";
            if ($ep_supported_mods['uom'] == true) { // price UOM mod
              $query .= "products_price_uom = :products_price_uom:, ";
            }
            if ($ep_supported_mods['upc'] == true) { // UPC Code mod
              $query .= "products_upc = :products_upc:, ";
            }
            if ($ep_supported_mods['gpc'] == true) { // Google Product Category for Google Merhcant Center - chadd 10-1-2011
              $query .= "products_gpc = :products_gpc:, ";
            }
            if ($ep_supported_mods['msrp'] == true) { // Manufacturer's Suggested Retail Price
              $query .= "products_msrp = :products_msrp:, ";
            }
            if ($ep_supported_mods['map'] == true) { // Manufacturer's Advertised Price
              $query .= "map_enabled = :map_enabled:, ";
              $query .= "map_price = :map_price:, ";
            }
            if ($ep_supported_mods['gppi'] == true) { // Group Pricing Per Item
              $query .= "products_group_a_price = :products_group_a_price:, ";
              $query .= "products_group_b_price = :products_group_b_price:, ";
              $query .= "products_group_c_price = :products_group_c_price:, ";
              $query .= "products_group_d_price = :products_group_d_price:, ";
            }
            if ($ep_supported_mods['excl'] == true) { // Exclusive Products custom mod
              $query .= "products_exclusive = :products_exclusive:, ";
            }
            if (count($custom_fields) > 0) {
              foreach ($custom_fields as $field) {
                $value = 'v_' . $field;
                if ($filelayout[$value]) {
                  $query .= ":field: = :value:, ";
                  $query = $db->bindVars($query, ':field:', $field, 'noquotestring');
                  $query = $db->bindVars($query, ':value:', ${$value}, 'string');
                }
              }
            }
            $query .= "products_image			= :products_image:,
							products_weight					= :products_weight:,
							products_discount_type          = :products_discount_type:,
							products_discount_type_from     = :products_discount_type_from:,
							product_is_call                 = :product_is_call:,
							products_sort_order             = :products_sort_order:,
							products_quantity_order_min     = :products_quantity_order_min:,
							products_quantity_order_units   = :products_quantity_order_units:,
							products_priced_by_attribute	= :products_priced_by_attribute:,
							product_is_always_free_shipping	= :product_is_always_free_shipping:,
							products_tax_class_id			= :tax_class_id:,
							products_date_available			= :date_avail:, 
							products_date_added				= :date_added:,
							products_last_modified			= CURRENT_TIMESTAMP,
							products_quantity				= :products_quantity:,
							manufacturers_id				= :manufacturers_id:,
							products_status					= :products_status:,
							metatags_title_status			= :metatags_title_status:,
							metatags_products_name_status	= :metatags_products_name_status:,
							metatags_model_status			= :metatags_model_status:,
							metatags_price_status			= :metatags_price_status:,
							metatags_title_tagline_status	= :metatags_title_tagline_status:
                     WHERE (products_id = :products_id:)";
            $query = $db->bindVars($query, ':products_price:', $v_products_price , 'currency');
            $query = $db->bindVars($query, ':products_price_uom:', $v_products_price_uom , 'currency');
            $query = $db->bindVars($query, ':products_upc:', $v_products_upc, 'string');
            $query = $db->bindVars($query, ':products_gpc:', $v_products_gpc, 'string');
            $query = $db->bindVars($query, ':products_msrp:', $v_products_msrp, 'string');
            $query = $db->bindVars($query, ':map_enabled:', $v_map_enabled, 'string');
            $query = $db->bindVars($query, ':map_price:', $v_map_price, 'string');
            $query = $db->bindVars($query, ':products_group_a_price:', $v_products_group_a_price, 'string');
            $query = $db->bindVars($query, ':products_group_b_price:', $v_products_group_b_price, 'string');
            $query = $db->bindVars($query, ':products_group_c_price:', $v_products_group_c_price, 'string');
            $query = $db->bindVars($query, ':products_group_d_price:', $v_products_group_d_price, 'string');
            $query = $db->bindVars($query, ':products_exclusive:', $v_products_exclusive, 'string');
            $query = $db->bindVars($query, ':products_image:', $v_products_image, 'string');
            $query = $db->bindVars($query, ':products_weight:', $v_products_weight, 'string');
            $query = $db->bindVars($query, ':products_discount_type:', $v_products_discount_type, 'string');
            $query = $db->bindVars($query, ':products_discount_type_from:', $v_products_discount_type_from, 'string');
            $query = $db->bindVars($query, ':product_is_call:', $v_product_is_call, 'string');
            $query = $db->bindVars($query, ':products_sort_order:', $v_products_sort_order, 'string');
            $query = $db->bindVars($query, ':products_quantity_order_min:', $v_products_quantity_order_min, 'float');
            $query = $db->bindVars($query, ':products_quantity_order_units:', $v_products_quantity_order_units, 'float');
            $query = $db->bindVars($query, ':products_priced_by_attribute:', $v_products_priced_by_attribute, 'string');
            $query = $db->bindVars($query, ':product_is_always_free_shipping:', $v_product_is_always_free_shipping, 'string');
            $query = $db->bindVars($query, ':tax_class_id:', $v_tax_class_id, 'integer');
            $query = $db->bindVars($query, ':date_avail:', $v_date_avail, ($v_date_avail == 'NULL' ? 'noquotestring': 'string'));
            $query = $db->bindVars($query, ':date_added:', $v_date_added, ($v_date_added == 'CURRENT_TIMESTAMP' ? 'noquotestring': 'string'));
            $query = $db->bindVars($query, ':products_quantity:', $v_products_quantity, 'float');
            $query = $db->bindVars($query, ':categories_id:', $v_categories_id, 'integer');
            $query = $db->bindVars($query, ':manufacturers_id:', $v_manufacturers_id, 'integer');
            $query = $db->bindVars($query, ':products_status:', $v_db_status, 'string');
            $query = $db->bindVars($query, ':metatags_title_status:', $v_metatags_title_status, 'string');
            $query = $db->bindVars($query, ':metatags_products_name_status:', $v_metatags_products_name_status, 'string');
            $query = $db->bindVars($query, ':metatags_model_status:', $v_metatags_model_status, 'string');
            $query = $db->bindVars($query, ':metatags_price_status:', $v_metatags_price_status, 'string');
            $query = $db->bindVars($query, ':metatags_title_tagline_status:', $v_metatags_title_tagline_status, 'string');
            $query = $db->bindVars($query, ':products_id:', $v_products_id, 'integer');

            $result = ep_4_query($query);
            if ($result == true) {
              zen_record_admin_activity('Updated product ' . (int) $v_products_id . ' via EP4.', 'info');

              // needs to go into a log file chadd 11-14-2011
              if ($ep_feedback == true) {
                $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_UPDATE_PRODUCT, ${$chosen_key});
                foreach ($items as $col => $summary) {
                  if ($col == $filelayout[$chosen_key]){
                    continue;
                  }
                  $display_output .= print_el_4($summary);
                }
              }
              // PRODUCT_MUSIC_EXTRA
              if ($v_products_type == '2') {
                $sql_music_extra = ep_4_query("SELECT * FROM " . TABLE_PRODUCT_MUSIC_EXTRA . " WHERE (products_id = '" . $v_products_id . "') LIMIT 1");
                if (($ep_uses_mysqli ? mysqli_num_rows($sql_music_extra) : mysql_num_rows($sql_music_extra)) == 1) { // update
                  $query = "UPDATE " . TABLE_PRODUCT_MUSIC_EXTRA . " SET
									artists_id     		= :artists_id:,
										record_company_id	= :record_company_id:,
										music_genre_id		= :music_genre_id:
										WHERE products_id   = :products_id:";
                  $query = $db->bindVars($query, ':artists_id:', $v_artists_id, 'integer');
                  $query = $db->bindVars($query, ':record_company_id:', $v_record_company_id, 'integer');
                  $query = $db->bindVars($query, ':music_genre_id:', $v_music_genre_id, 'integer');
                  $query = $db->bindVars($query, ':products_id:', $v_products_id, 'integer');
                  $result = ep_4_query($query);
                  if ($result) {
                    zen_record_admin_activity('Updated product music extra ' . (int) $v_artists_id . ' via EP4.', 'info');
                  }
                }
              }
              $ep_update_count++;
            } else {
              $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_UPDATE_PRODUCT_FAIL, ${$chosen_key});
              $ep_error_count++;
            }
          } // EOF Update product

          // START: Product MetaTags
          if (isset($v_metatags_title)) {
            foreach ($v_metatags_title as $key => $metaData) {
              $sql = "SELECT products_id FROM " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . " WHERE 
								(products_id = :products_id: AND language_id = :key:) LIMIT 1 ";
              $sql = $db->bindVars($sql, ':products_id:', $v_products_id, 'integer');
              $sql = $db->bindVars($sql, ':key:', $key, 'integer');
              $result = ep_4_query($sql);
              if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result))) {
                // UPDATE
                $sql = "UPDATE " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . " SET 
									metatags_title = :metatags_title:,
									metatags_keywords = :metatags_keywords:,
									metatags_description = :metatags_description:
									WHERE (products_id = :products_id: AND language_id = :key:)";
                $sql = $db->bindVars($sql, ':metatags_title:', ep_4_curly_quotes($v_metatags_title[$key]), 'string');
                $sql = $db->bindVars($sql, ':metatags_keywords:', ep_4_curly_quotes($v_metatags_keywords[$key]), 'string');
                $sql = $db->bindVars($sql, ':metatags_description:', ep_4_curly_quotes($v_metatags_description[$key]), 'string');
                $sql = $db->bindVars($sql, ':products_id:', $v_products_id, 'integer');
                $sql = $db->bindVars($sql, ':key:', $key, 'integer');
              } else {
                // NEW
                $sql = "INSERT INTO " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . " SET 
									metatags_title = :metatags_title:,
									metatags_keywords = :metatags_keywords:,
									metatags_description = :metatags_description:,
									products_id = :products_id:,
									language_id = :key:";
                $sql = $db->bindVars($sql, ':metatags_title:', ep_4_curly_quotes($v_metatags_title[$key]), 'string');
                $sql = $db->bindVars($sql, ':metatags_keywords:', ep_4_curly_quotes($v_metatags_keywords[$key]), 'string');
                $sql = $db->bindVars($sql, ':metatags_description:', ep_4_curly_quotes($v_metatags_description[$key]), 'string');
                $sql = $db->bindVars($sql, ':products_id:', $v_products_id, 'integer');
                $sql = $db->bindVars($sql, ':key:', $key, 'integer');
              }
              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Updated/inserted meta tags products description ' . (int) $v_products_id . ' via EP4.', 'info');
              }
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
          if (strtolower(substr($file['name'], 0, 14)) == "pricebreaks-ep") {

            if ($v_products_discount_type != '0') { // if v_products_discount_type == 0 then there are no quantity breaks
              if (${$chosen_key} != "") { // we check to see if this is a product in the current db, must have product model number
                $sql = "SELECT products_id FROM " . TABLE_PRODUCTS;
                switch ($chosen_key) {
                  case 'v_products_model':
                    $sql .= " WHERE (products_model = :products_model:) LIMIT 1";
                    break;
                  case 'v_products_id':
                    $sql .= " WHERE (products_id = :products_id:) LIMIT 1";
                    break;
                  default:
                    $sql .= " WHERE (products_model = :products_model:) LIMIT 1";
                    break;
                }
                $sql = $db->bindVars($sql, ':products_model:', $v_products_model, 'string');
                $sql = $db->bindVars($sql, ':products_id:', $v_products_id, 'integer');
                $result = ep_4_query($sql);
                if (($ep_uses_mysqli ? mysqli_num_rows($result) : mysql_num_rows($result)) != 0) { // found entry
                  $row3 = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result));
                  $v_products_id = $row3['products_id'];

                  // remove all old associated quantity discounts
                  // this simplifies the below code to JUST insert all the new values
                  $db->Execute("DELETE FROM " . TABLE_PRODUCTS_DISCOUNT_QUANTITY . " WHERE products_id = '" . (int) $v_products_id . "'");

                  // initialize quantity discount variables
                  $xxx = 1;
                  // $v_discount_id_var    = 'v_discount_id_'.$xxx ; // this column is now redundant
                  $v_discount_qty_var = 'v_discount_qty_' . $xxx;
                  $v_discount_price_var = 'v_discount_price_' . $xxx;
                  while (isset(${$v_discount_qty_var})) {
                    // INSERT price break
                    if (${$v_discount_price_var} != "") { // check for empty price
                      $sql = "INSERT INTO " . TABLE_PRODUCTS_DISCOUNT_QUANTITY . " SET
										products_id    = :products_id:,
										discount_id    = :discount_id:,
										discount_qty   = :discount_qty_var:,
										discount_price = :discount_price_var:";
                      $sql = $db->bindVars($sql, ':products_id:', $v_products_id, 'integer');
                      $sql = $db->bindVars($sql, ':discount_id:', $xxx, 'integer');
                      $sql = $db->bindVars($sql, ':discount_qty_var:', ${$v_discount_qty_var}, 'float');
                      $sql = $db->bindVars($sql, ':discount_price_var:', ${$v_discount_price_var}, 'currency');
                      $result = ep_4_query($sql);
                      if ($result) {
                        zen_record_admin_activity('Inserted products discount quantity ' . (int) $v_products_id . ' via EP4.', 'info');
                      }
                    } // end: check for empty price
                    $xxx++;
                    $v_discount_qty_var = 'v_discount_qty_' . $xxx;
                    $v_discount_price_var = 'v_discount_price_' . $xxx;
                  } // while (isset(${$v_discount_id_var})
                } // end: if (row count <> 0) found entry
              } // if ($v_products_model)
            } else { // products_discount_type == 0, so remove any old quantity_discounts
              if (${$chosen_key} != "") { // we check to see if this is a product in the current db, must have product model number
                $sql = "SELECT products_id FROM " . TABLE_PRODUCTS;
                switch ($chosen_key) {
                  case 'v_products_model':
                    $sql .= " WHERE (products_model = :products_model:) LIMIT 1";
                    break;
                  case 'v_products_id':
                    $sql .= " WHERE (products_id = :products_id:) LIMIT 1";
                    break;
                  default:
                    $sql .= " WHERE (products_model = :products_model:) LIMIT 1";
                    break;
                }
                $sql = $db->bindVars($sql, ':products_model:', $v_products_model, 'string');
                $sql = $db->bindVars($sql, ':products_id:', $v_products_id, 'integer');
                $result = ep_4_query($sql);
                if (($ep_uses_mysqli ? mysqli_num_rows($result) : mysql_num_rows($result)) != 0) { // found entry
                  $row3 = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result));
                  $v_products_id = $row3['products_id'];
                  // remove all associated quantity discounts
                  $db->Execute("DELETE FROM " . TABLE_PRODUCTS_DISCOUNT_QUANTITY . " WHERE products_id = '" . (int) $v_products_id . "'");
                }
              }
            } // if ($v_products_discount_type != '0')
          }


          // BEGIN: Products Descriptions
          // the following is common in both the updating an existing product and creating a new product // mc12345678 updated to allow omission of v_products_description in the import file.
    $add_products_description_data = false;
    $zco_notifier->notify('EP4_IMPORT_FILE_PRODUCTS_DESCRIPTION_ADD_OR_CHANGE_DATA');
    if ((isset($v_products_name) && is_array($v_products_name)) || (isset($v_products_description) && is_array
            ($v_products_description)) ||
        ($ep_supported_mods['psd'] == true
            &&
            isset
            ($v_products_short_desc) ) || (isset($v_products_url) && is_array($v_products_url)) ||
        $add_products_description_data) { //
      // Effectively need a way to step through all language options, this section to be "accepted" if there is something to be updated.  Prefer the ability to verify update need without having to loop on anything, but just by "presence" of information.
      foreach ($langcode as $lang) {
        // foreach ($v_products_name as $key => $name) {  // Decouple the dependency on the products_name being imported to update the products_name, description, short description and/or URL. //mc12345678 2015-Dec-12
        $lang_id = $lang['id'];
              $sql = "SELECT * FROM " . TABLE_PRODUCTS_DESCRIPTION . " WHERE
                        products_id = :products_id: AND
                        language_id = :language_id:";
              $sql = $db->bindVars($sql, ':products_id:', $v_products_id, 'integer');
        $sql = $db->bindVars($sql, ':language_id:', $lang_id, 'integer');

              $result = ep_4_query($sql);

              if (($ep_uses_mysqli ? mysqli_num_rows($result) : mysql_num_rows($result)) == 0) {
                $sql = "INSERT INTO " . TABLE_PRODUCTS_DESCRIPTION . " (
                            products_id,
                  " . (isset($filelayout['v_products_name_' . $lang_id]) || $product_is_new ? "products_name, " : "") . 
                  ((isset($filelayout['v_products_description_' . $lang_id]) || ( isset($filelayout['v_products_description_' . $lang_id]) && $product_is_new) ) ? " products_description, " : "");
          if ($ep_supported_mods['psd'] == true && isset($v_products_short_desc)) {
                  $sql .= " products_short_desc,";
                }
          $sql .= (isset($filelayout['v_products_url_' . $lang_id]) ? " products_url, " : "");
          $zco_notifier->notify('EP4_IMPORT_FILE_PRODUCTS_DESCRIPTION_INSERT_FIELDS');
          $sql .= "
                   language_id )
                            VALUES (
                   :v_products_id:,
                   " . (isset($filelayout['v_products_name_' . $lang_id]) || $product_is_new ? ":v_products_name:, " : "") .
                   ((isset($filelayout['v_products_description_' . $lang_id]) || ( isset($filelayout['v_products_description_' . $lang_id]) && $product_is_new) ) ? ":v_products_description:, " : "");
          if ($ep_supported_mods['psd'] == true && isset($v_products_short_desc)) {
            $sql .= ":v_products_short_desc:, ";
                }
          $sql .= (isset($filelayout['v_products_url_' . $lang_id]) ? ":v_products_url:, " : "");
          $zco_notifier->notify('EP4_IMPORT_FILE_PRODUCTS_DESCRIPTION_INSERT_FIELDS_VALUES');
          $sql .= "
                   :language_id:)";

          $zco_notifier->notify('EP4_IMPORT_FILE_PRODUCTS_DESCRIPTION_FIELDS_BIND_START');
          $sql = $db->bindVars($sql, ':v_products_id:', $v_products_id, 'integer');
          $sql = $db->bindVars($sql, ':language_id:', $lang_id, 'integer');
          $sql = $db->bindVars($sql, ':v_products_name:', $v_products_name[$lang_id], 'string');
          $sql = $db->bindVars($sql, ':v_products_description:', $v_products_description[$lang_id], 'string');
          $sql = $db->bindVars($sql, ':v_products_short_desc:', $v_products_short_desc[$lang_id], 'string');
          $sql = $db->bindVars($sql, ':v_products_url:', $v_products_url[$lang_id], 'string');
          $zco_notifier->notify('EP4_IMPORT_FILE_PRODUCTS_DESCRIPTION_FIELDS_BIND_END');

          $result = ep_4_query($sql);
          if ($result) {
            zen_record_admin_activity('New product ' . (int)$v_products_id . ' description added via EP4.', 'info');
          }
        } else { // already in the description, update it
          $sql = "UPDATE " . TABLE_PRODUCTS_DESCRIPTION . " SET ";
          $update_count = false;
          if (isset($filelayout['v_products_name_' . $lang_id])) {
            $sql .= " products_name      = :v_products_name:";
            $update_count = true;
          }
          if (isset($filelayout['v_products_description_' . $lang_id]) || ( isset($filelayout['v_products_description_' . $lang_id]) && $product_is_new)) {
            $sql .= ($update_count ? ", " : "") . "products_description = :v_products_description:";
            $update_count = true;
          }
          if ($ep_supported_mods['psd'] == true && isset($v_products_short_desc[$lang_id])) {
            $sql .= ($update_count ? ", " : "") . "products_short_desc = :v_products_short_desc:";
                }
          if (isset($filelayout['v_products_url_' . $lang_id])) {
            $sql .= ($update_count ? ", " : "") . " products_url = :v_products_url: ";
            $update_count = true;
          }
          // If using this notifier to add to the $sql, the when something is added be sure to
          //  set $update_count = true;
          $zco_notifier->notify('EP4_IMPORT_FILE_PRODUCTS_DESCRIPTION_UPDATE_FIELDS_VALUES');
                
          $sql .= "        WHERE products_id = :v_products_id: AND language_id = :language_id:";
                
          $zco_notifier->notify('EP4_IMPORT_FILE_PRODUCTS_DESCRIPTION_UPDATE_WHERE');
                
          $zco_notifier->notify('EP4_IMPORT_FILE_PRODUCTS_DESCRIPTION_FIELDS_BIND_START');
          $sql = $db->bindVars($sql, ':v_products_name:', $v_products_name[$lang_id], 'string');
          $sql = $db->bindVars($sql, ':v_products_description:', $v_products_description[$lang_id], 'string');
          $sql = $db->bindVars($sql, ':v_products_short_desc:', $v_products_short_desc[$lang_id], 'string');
          $sql = $db->bindVars($sql, ':v_products_url:', $v_products_url[$lang_id], 'string');
          $sql = $db->bindVars($sql, ':v_products_id:', $v_products_id, 'integer');
          $sql = $db->bindVars($sql, ':language_id:', $lang_id, 'integer');
          $zco_notifier->notify('EP4_IMPORT_FILE_PRODUCTS_DESCRIPTION_FIELDS_BIND_END');
                
                // Be sure to run the update query only if there has been something provded to
                //  update.
                if ($update_count == true) {
                  $result = ep_4_query($sql);
                  if ($result) {
                    zen_record_admin_activity('Updated product ' . (int)$v_products_id . ' description via EP4.', 'info');
                  }
                }  // Perform query if there is something to update.
              } // END: already in description, update it
            } // END: foreach on languages
          } // END: Products Descriptions End
          //==================================================================================================================================
          // Assign product to category if linked
          // chadd - need to dig into further
          if (isset($v_categories_id)) { // find out if this product is listed in the category given
        $result_incategory = ep_4_query('SELECT 
          '.TABLE_PRODUCTS_TO_CATEGORIES.'.products_id,
          '.TABLE_PRODUCTS_TO_CATEGORIES.'.categories_id,
          '.TABLE_PRODUCTS.'.master_categories_id
          FROM
          '.TABLE_PRODUCTS.'
          LEFT JOIN
          '.TABLE_PRODUCTS_TO_CATEGORIES.' ON ('.TABLE_PRODUCTS.'.products_id = '.TABLE_PRODUCTS_TO_CATEGORIES.'.products_id AND '.TABLE_PRODUCTS_TO_CATEGORIES.'.categories_id='.$v_categories_id.')
          WHERE
          '.TABLE_PRODUCTS.'.products_id='.$v_products_id);
$result_incategory = ($ep_uses_mysqli ? mysqli_fetch_array($result_incategory) : mysql_fetch_array($result_incategory));
          if (!zen_not_null($result_incategory['products_id']) || sizeof($result_incategory) <= 0 /* ($ep_uses_mysqli ? mysqli_num_rows($result_incategory) : mysql_num_rows($result_incategory)) == 0 */) { // nope, this is a new category for this product
              if ($items[$filelayout['v_status']] == 7) {

                /* $result_incategory = ep_4_query('SELECT 
                  '.TABLE_PRODUCTS.'.master_categories_id
                  FROM
                  '.TABLE_PRODUCTS.'
                  WHERE
                  '.TABLE_PRODUCTS.'.products_id='.$v_products_id);

                  $result_incategory = ($ep_uses_mysqli ? mysqli_fetch_array($result_incategory) : mysql_fetch_array($result_incategory)); */
                //do category move action.
                // if the master_categories_id != categories_id, then for "safety" sake, should successfully insert the product to the category before deleting it from the previous category. Should also verify that the master_categories_id is set, because if it is not then there is a bigger issue.  As part of the verification, if it is not set, then don't try to delete the previous, just add it and provide equivalent information.
                if ((int) $result_incategory['master_categories_id'] != (int) $v_categories_id) {
                  //move is eminent
                  if ($result_incategory['master_categories_id'] > 0) {
                    //master_category is assigned to the product do the move.
                    // Ensure the product still is on the way to the move and available.
                    $res1 = ep_4_query('INSERT INTO ' . TABLE_PRODUCTS_TO_CATEGORIES . ' (products_id, categories_id)
            VALUES (' . $v_products_id . ', ' . $v_categories_id . ')');
                    if ($res1) {
                      //Successfully inserted the product to the category, now need to remove the link from the previous category.
                      $res1 = ep_4_query('UPDATE ' . TABLE_PRODUCTS . ' set master_categories_id = ' . $v_categories_id . ' 
                                 WHERE products_id = ' . $v_products_id);

                      $res2 = ep_4_query('DELETE FROM ' . TABLE_PRODUCTS_TO_CATEGORIES . ' WHERE products_id = ' . $v_products_id . ' AND categories_id = 
                             ' . $result_incategory['master_categories_id']);
                      if ($res1 && $res2) {
                        zen_record_admin_activity('Product ' . (int) $v_products_id . ' moved to category ' . (int) $v_categories_id . ' via EP4.', 'info');
                      } else if ($res1 && !$res2) {
                        zen_record_admin_activity('Product ' . (int) $v_products_id . ' master_categories_id changed to category ' . (int) $v_categories_id . ' via EP4.', 'info');
                      } else if (!$res1 && $res2) {
                        zen_record_admin_activity('Product ' . (int) $v_products_id . ' deleted from category ' . (int) $result_incategory['master_categories_id'] . ' and added to category ' . (int) $v_categories_id . ' via EP4.', 'info');
                      } else if (!$res1 && !$res2) {
                        zen_record_admin_activity('Product ' . (int) $v_products_id . ' copied as link to category ' . (int) $v_categories_id . ' via EP4.', 'info');
                      }
                    }
                  } /* EOF Master Category is set */ else {
                    //master_category is not assigned, assign the category to the master category and do the move.
                    $res1 = ep_4_query('INSERT INTO ' . TABLE_PRODUCTS_TO_CATEGORIES . ' (products_id, categories_id)
            VALUES (' . $v_products_id . ', ' . $v_categories_id . ')');
                    if ($res1) {
                      //Successfully inserted the product to the category, now need to remove the link from the previous category.
                      $res1 = ep_4_query('UPDATE ' . TABLE_PRODUCTS . ' set master_categories_id = ' . $v_categories_id . ' 
                                 WHERE products_id = ' . $v_products_id);
                      if ($res1) {
                        zen_record_admin_activity('Product ' . (int) $v_products_id . ' moved to category ' . (int) $v_categories_id . ' via EP4.', 'info');
                      } else {
                        zen_record_admin_activity('Product ' . (int) $v_products_id . ' copied as link to category ' . (int) $v_categories_id . ' via EP4.', 'info');
                      }
                    }
                  } // EOF Master category is not defined.
                } // End if master and category different, nothing else to do as no where to go because both are the same...
              } /* EOF status == Move */ else {
                $res1 = ep_4_query('INSERT INTO ' . TABLE_PRODUCTS_TO_CATEGORIES . ' (products_id, categories_id)
            VALUES (' . $v_products_id . ', ' . $v_categories_id . ')');
                if ($res1) {
                  zen_record_admin_activity('Product ' . (int) $v_products_id . ' copied as link to category ' . (int) $v_categories_id . ' via EP4.', 'info');
                }
              } // Don't move the product
            } else { // already in this category, nothing to do! // Though may need to do the move action so there is still possibly something to do... 
              if ($items[$filelayout['v_status']] == 7) {
//                $result_incategory = ($ep_uses_mysqli ? mysqli_fetch_array($result_incategory) : mysql_fetch_array($result_incategory));

//do category move action.
                // if the master_categories_id != categories_id, then for "safety" sake, should successfully insert the product to the category before deleting it from the previous category. Should also verify that the master_categories_id is set, because if it is not then there is a bigger issue.  As part of the verification, if it is not set, then don't try to delete the previous, just add it and provide equivalent information.
                if ($result_incategory['master_categories_id'] != $result_incategory['categories_id']) {
                  //move is eminent
                  if ($result_incategory['master_categories_id'] > 0) {
                    //master_category is assigned to the product complete the move.
                    // Ensure the product still is on the way to the move and available.
                    //      $res1 = ep_4_query('INSERT INTO '.TABLE_PRODUCTS_TO_CATEGORIES.' (products_id, categories_id)
                    //      VALUES ("'.$v_products_id.'", "'.$v_categories_id.'")');
                    $res1 = ep_4_query('UPDATE ' . TABLE_PRODUCTS . ' set master_categories_id = ' . $v_categories_id . ' 
                                 WHERE products_id = ' . $v_products_id);

                    /* if ($res1) */
                      //Successfully updated the product to the category, now need to remove the link from the previous category.

                    $res2 = ep_4_query('DELETE FROM ' . TABLE_PRODUCTS_TO_CATEGORIES . ' WHERE products_id = ' . $v_products_id . ' AND categories_id = 
                             ' . $result_incategory['master_categories_id']);
                    
                    if ($res1 && $res2) {
                      zen_record_admin_activity('Product ' . (int) $v_products_id . ' moved from category ' . (int)$result_incategory['master_categories_id'] . ' to category ' . (int) $v_categories_id . ' via EP4.', 'info');
                    } else if ($res1 && !$res2) {
                      zen_record_admin_activity('Product ' . (int) $v_products_id . ' master_categories_id changed from category ' . (int)$result_incategory['master_categories_id'] . ' to category ' . (int) $v_categories_id . ' via EP4.', 'info');
                    } else if (!$res1 && $res2) {
                      zen_record_admin_activity('Product ' . (int) $v_products_id . ' deleted from category ' . (int) $result_incategory['master_categories_id'] . ' and added to category ' . (int) $v_categories_id . ' via EP4.', 'info');
                    }
                  } else {
                    //master_category is not assigned, assign the category to the master category and do the move.
                    //   $res1 = ep_4_query('INSERT INTO '.TABLE_PRODUCTS_TO_CATEGORIES.' (products_id, categories_id)
                    //      VALUES ("'.$v_products_id.'", "'.$v_categories_id.'")');
                    //Successfully inserted the product to the category, now need to remove the link from the previous category.
                    $res1 = ep_4_query('UPDATE ' . TABLE_PRODUCTS . ' set master_categories_id = ' . $v_categories_id . ' 
                                 WHERE products_id = ' . $v_products_id);
                    if ($res1) {
                      zen_record_admin_activity('Product ' . (int) $v_products_id . ' master_categories_id established as category ' . (int) $v_categories_id . ' via EP4.', 'info');
                    }
                  }
                } // End if master and category different, nothing else to do as no where to go because both are the same...
                //continue;
              }
            }
          }
          //==================================================================================================================================

          /* Specials - if a null value in specials price, do not add or update. If price = 0, let's delete it */
          if (isset($v_specials_price) && zen_not_null($v_specials_price)) {
            if ($v_specials_price >= $v_products_price) {
              $specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_PRICE_FAIL, ${$chosen_key}, substr(strip_tags($v_products_name[$epdlanguage_id]), 0, 10));
              // available function: zen_set_specials_status($specials_id, $status)
              // could alternatively make status inactive, and still upload..
              continue;
            }
            // column is in upload file, and price is in field (not empty)
            // if null (set further above), set forever, else get raw date
            $has_specials = true;

            // using new date functions - chadd
            $v_specials_date_avail = ($v_specials_date_avail == true && $v_specials_date_avail > "0001-01-01") ? date("Y-m-d H:i:s", strtotime($v_specials_date_avail)) : "0001-01-01";
            $v_specials_expires_date = ($v_specials_expires_date == true  && $v_specials_expires_date > "0001-01-01") ? date("Y-m-d H:i:s", strtotime($v_specials_expires_date)) : "0001-01-01";

            // Check if this product already has a special
            $special = ep_4_query("SELECT products_id FROM " . TABLE_SPECIALS . " WHERE products_id = " . $v_products_id);

            if (($ep_uses_mysqli ? mysqli_num_rows($special) : mysql_num_rows($special)) == 0) { // not in db
              if ($v_specials_price == '0') { // delete requested, but is not a special
                $specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_DELETE_FAIL, ${$chosen_key}, substr(strip_tags($v_products_name[$epdlanguage_id]), 0, 10));
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
							:products_id:,
							:specials_price:,
							now(),
							:specials_date_avail:,
							:specials_expires_date:,
							'1')";
              $sql = $db->bindVars($sql, ':products_id:', $v_products_id, 'integer');
              $sql = $db->bindVars($sql, ':specials_price:', $v_specials_price, 'float');
              $sql = $db->bindVars($sql, ':specials_date_avail:', $v_specials_date_avail, 'string');
              $sql = $db->bindVars($sql, ':specials_expires_date:', $v_specials_expires_date, 'string');

              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Inserted special ' . (int) $v_products_id . ' via EP4.', 'info');
              }
              $specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_NEW, ${$chosen_key}, substr(strip_tags($v_products_name[$epdlanguage_id]), 0, 10), $v_products_price, $v_specials_price);
            } else { // existing product
              if ($v_specials_price == '0') { // delete of existing requested
                $db->Execute("DELETE FROM " . TABLE_SPECIALS . " WHERE products_id = '" . (int) $v_products_id . "'");
                $specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_DELETE, ${$chosen_key});
                continue;
              }
              // just make an update
              $sql = "UPDATE " . TABLE_SPECIALS . " SET
							specials_new_products_price	= :specials_price:,
							specials_last_modified		= now(),
							specials_date_available		= :specials_date_avail:,
							expires_date				= :specials_expires_date:,
							status						= '1'
							WHERE products_id			= :products_id:";
              $sql = $db->bindVars($sql, ':specials_price:', $v_specials_price, 'float');
              $sql = $db->bindVars($sql, ':specials_date_avail:', $v_specials_date_avail, 'string');
              $sql = $db->bindVars($sql, ':specials_expires_date:', $v_specials_expires_date, 'string');
              $sql = $db->bindVars($sql, ':products_id:', $v_products_id, 'integer');
              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Updated special ' . (int) $v_products_id . ' via EP4.', 'info');
              }
              $specials_print .= sprintf(EASYPOPULATE_4_SPECIALS_UPDATE, ${$chosen_key}, substr(strip_tags($v_products_name[$epdlanguage_id]), 0, 10), $v_products_price, $v_specials_price);
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
            if ($col == $filelayout[$chosen_key]){
              continue;
            }
            $display_output .= print_el_4($summary);
          }
        } // end of row insertion code
      } // end of Mail While Loop
    } // conditional IF statement
  
    $zco_notifier->notify('EP4_IMPORT_FILE_PRE_DISPLAY_OUTPUT');

//    $display_output .= '<h3>Finished Processing Import File</h3>';
    $display_output .= EASYPOPULATE_4_DISPLAY_IMPORT_RESULTS_TITLE;

//    $display_output .= '<br/>Updated records: ' . $ep_update_count;
    $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_IMPORT_RESULTS_NUM_RECORDS_UPDATE, $ep_update_count);
//    $display_output .= '<br/>New Imported records: ' . $ep_import_count;
    $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_IMPORT_RESULTS_NUM_RECORDS_IMPORT, $ep_import_count);
//    $display_output .= '<br/>Errors Detected: ' . $ep_error_count;
    $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_IMPORT_RESULTS_NUM_ERRORS, $ep_error_count);
//    $display_output .= '<br/>Warnings Detected: ' . $ep_warning_count;
    $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_IMPORT_RESULTS_NUM_WARNINGS, $ep_warning_count);

//    $display_output .= '<br/>Memory Usage: ' . memory_get_usage();
    $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_IMPORT_RESULTS_MEM_USE, memory_get_usage());
//    $display_output .= '<br/>Memory Peak: ' . memory_get_peak_usage();
    $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_IMPORT_RESULTS_MEM_PEAK, memory_get_peak_usage());

    // benchmarking
    $time_end = microtime(true);
    $time = $time_end - $time_start;
//    $display_output .= '<br/>Execution Time: ' . $time . ' seconds.';
    $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_IMPORT_RESULTS_EXEC_TIME, $time);
  }

  // specials status = 0 if date_expires is past.
  // HEY!!! THIS ALSO CALLS zen_update_products_price_sorter($v_products_id); !!!!!!
  if ($has_specials == true) { // specials were in upload so check for expired specials
    zen_expire_specials();
  }
  if (($ep_warning_count > 0) || ($ep_error_count > 0)) {
    $messageStack->add(EASYPOPULATE_4_DISPLAY_IMPORT_COMPLETE_ISSUES, 'warning');
  } else {
    $messageStack->add(EASYPOPULATE_4_DISPLAY_IMPORT_COMPLETE, 'success');
  }
} // END FILE UPLOADS
