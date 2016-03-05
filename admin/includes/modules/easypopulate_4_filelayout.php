<?php
// $Id: easypopulate_4_functions.php, v4.0.33 02-29-2016 mc12345678 $

//function ep_4_set_filelayout($ep_dltype, &$filelayout_sql, $sql_filter, $langcode, $ep_supported_mods, $custom_fields) {
//  global $db, $zco_notifier, $flielayout;

if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
}

	$filelayout = array();
	switch($ep_dltype) {
	case 'SBAStock';
		$filelayout[] = 'v_products_model';
		$filelayout[] = 'v_status';
		foreach ($langcode as $key => $lang) { // create variables for each language id
			$l_id = $lang['id'];
			$filelayout[] = 'v_products_name_'.$l_id;
			$filelayout[] = 'v_products_description_'.$l_id;
			if ($ep_supported_mods['psd'] == true) { // products short description mod
				$filelayout[] = 'v_products_short_desc_'.$l_id;
			}
		}
   	$ep_4_SBAEnabled = ep_4_SBA1Exists();
    if ($ep_4_SBAEnabled == '2') {
      $filelayout[] = 'v_customid';
    }
		//$filelayout[] =	'v_products_options_values_name'; // options values name from table PRODUCTS_OPTIONS_VALUES
		$filelayout[] = 'v_SBA_tracked';
		$filelayout[] = 'v_table_tracker';
		$filelayout[] = 'v_products_attributes'; // options name from table 
		$filelayout[] = 'v_products_quantity';
		
		$filelayout_sql = 'SELECT
			p.products_id					as v_products_id,
			p.products_model				as v_products_model,';
		if (count($custom_fields) > 0) { // User Defined Products Fields
			foreach ($custom_fields as $field) {
				$filelayout_sql .= 'p.'.$field.' as v_'.$field.',';
			}
		}
		$filelayout_sql .= '
			p.products_quantity				as v_products_quantity,
			p.products_status				as v_status 
			FROM '
			.TABLE_PRODUCTS.' as p '
			//.TABLE_CATEGORIES.' as subc,'
			//.TABLE_PRODUCTS_TO_CATEGORIES.' as ptoc
			. ($sql_filter <> '' ? 'WHERE '. $sql_filter : '');
			//p.products_id = ptoc.products_id AND
			/*ptoc.categories_id = subc.categories_id '.$sql_filter;*/
		break;
		
	case 'full': // FULL products download
		$zco_notifier->notify('EP4_EXTRA_FUNCTIONS_SET_FILELAYOUT_FULL_START');

		// The file layout is dynamically made depending on the number of languages
		$filelayout[] = 'v_products_model';
		$filelayout[] = 'v_products_type'; // 4-23-2012
		$filelayout[] = 'v_products_image';
		foreach ($langcode as $key => $lang) { // create variables for each language id
			$l_id = $lang['id'];
			$filelayout[] = 'v_products_name_'.$l_id;
			$filelayout[] = 'v_products_description_'.$l_id;
			if ($ep_supported_mods['psd'] == true) { // products short description mod
				$filelayout[] = 'v_products_short_desc_'.$l_id;
			}
			$filelayout[] = 'v_products_url_'.$l_id;
		} 
		$zco_notifier->notify('EP4_EXTRA_FUNCTIONS_SET_FILELAYOUT_FULL_FILELAYOUT');
		
		$filelayout[] = 'v_specials_price';
		$filelayout[] = 'v_specials_date_avail';
		$filelayout[] = 'v_specials_expires_date';
		$filelayout[] = 'v_products_price';
		if ($ep_supported_mods['uom'] == true) { // price UOM mod
			$filelayout[] = 'v_products_price_uom';
		} 
		if ($ep_supported_mods['upc'] == true) { // UPC Mod
			$filelayout[] = 'v_products_upc'; 
		}
		if ($ep_supported_mods['gpc'] == true) { // Google Product Category for Google Merchant Center - chadd 10-1-2011
			$filelayout[] = 'v_products_gpc'; 
		}
		if ($ep_supported_mods['msrp'] == true) { // Requested Mod Support - Manufacturer's Suggest Retail Price
			$filelayout[] = 'v_products_msrp'; 
		}
		if ($ep_supported_mods['map'] == true) { // Requested Mod Support - Manufacturer's Advertised Price
      $filelayout[] = 'v_map_enabled';
      $filelayout[] = 'v_map_price';
    }
    if ($ep_supported_mods['gppi'] == true) { // Requested Mod Support - Group Pricing Per Item
			$filelayout[] = 'v_products_group_a_price';
			$filelayout[] = 'v_products_group_b_price';
			$filelayout[] = 'v_products_group_c_price';
			$filelayout[] = 'v_products_group_d_price';
		}
		if ($ep_supported_mods['excl'] == true) { // Exclusive Product Custom Mod
			$filelayout[] = 'v_products_exclusive'; 
		}
		if (count($custom_fields) > 0) { // User Defined Products Fields
			foreach ($custom_fields as $field) {
				$filelayout[] = 'v_'.$field;
			}
		}
		$filelayout[] = 'v_products_weight';
		$filelayout[] = 'v_product_is_call';
		$filelayout[] = 'v_products_sort_order';
		$filelayout[] = 'v_products_quantity_order_min';
		$filelayout[] = 'v_products_quantity_order_units';
		$filelayout[] = 'v_products_priced_by_attribute'; // 4-30-2012
		$filelayout[] = 'v_product_is_always_free_shipping'; // 4-30-2012
		$filelayout[] = 'v_date_avail'; // should be changed to v_products_date_available for clarity
		$filelayout[] = 'v_date_added'; // should be changed to v_products_date_added for clarity
		$filelayout[] = 'v_products_quantity';
		$filelayout[] = 'v_manufacturers_name';
		// NEW code for 'unlimited' category depth - 1 Category Column for each installed Language
		foreach ($langcode as $key => $lang) { // create categories variables for each language id
			$l_id = $lang['id'];
			$filelayout[] = 'v_categories_name_'.$l_id;
		} 
		$filelayout[] = 'v_tax_class_title';
		$filelayout[] = 'v_status'; // this should be v_products_status for clarity
		// metatags - 4-23-2012: added switch
		if ((int)EASYPOPULATE_4_CONFIG_META_DATA) {
			$filelayout[] = 'v_metatags_products_name_status';
			$filelayout[] = 'v_metatags_title_status';
			$filelayout[] = 'v_metatags_model_status';
			$filelayout[] = 'v_metatags_price_status';
			$filelayout[] = 'v_metatags_title_tagline_status';
			foreach ($langcode as $key => $lang) { // create variables for each language id
				$l_id = $lang['id'];
				$filelayout[] = 'v_metatags_title_'.$l_id;
				$filelayout[] = 'v_metatags_keywords_'.$l_id;
				$filelayout[] = 'v_metatags_description_'.$l_id;
			}
		}
		// music info - 4-23-2012
		// record_artist, record_artist_info
		// record_company, record_company_info
		// music_genre
		if ((int)EASYPOPULATE_4_CONFIG_MUSIC_DATA) {
			$filelayout[] = 'v_artists_name';
			$filelayout[] = 'v_artists_image';
			foreach ($langcode as $key => $lang) { // create variables for each language id
				$l_id = $lang['id'];
				$filelayout[] = 'v_artists_url_'.$l_id;
			}			
			$filelayout[] = 'v_record_company_name';
			$filelayout[] = 'v_record_company_image';
			foreach ($langcode as $key => $lang) { // create variables for each language id
				$l_id = $lang['id'];
				$filelayout[] = 'v_record_company_url_'.$l_id;
			}
			$filelayout[] = 'v_music_genre_name';
		}
		$filelayout_sql = 'SELECT DISTINCT 

			p.products_id					as v_products_id,
			p.products_model				as v_products_model,
			p.products_type					as v_products_type,
			p.products_image				as v_products_image,
			p.products_price				as v_products_price,';
		if ($ep_supported_mods['uom'] == true) { // price UOM mod
			$filelayout_sql .=  'p.products_price_uom as v_products_price_uom,'; // to soon be changed to v_products_price_uom
		} 
		if ($ep_supported_mods['upc'] == true) { // UPC Code mod
			$filelayout_sql .=  'p.products_upc as v_products_upc,'; 
		}
		if ($ep_supported_mods['gpc'] == true) { // Google Product Category for Google Merchant Center - chadd 10-1-2011
			$filelayout_sql .=  'p.products_gpc as v_products_gpc,'; 
		}
		if ($ep_supported_mods['msrp'] == true) { // Requested Mod Support - Manufacturer's Suggest Retail Price
			$filelayout_sql .=  'p.products_msrp as v_products_msrp,'; 
		}	
		if ($ep_supported_mods['map'] == true) { // Requested Mod Support - Manufacturer's Advertised Price
      $filelayout_sql .= 'p.map_enabled as v_map_enabled,';
      $filelayout_sql .= 'p.map_price as v_map_price,';
    }
		if ($ep_supported_mods['gppi'] == true) { // Requested Mod Support - Group Pricing Per Item
			$filelayout_sql .=  'p.products_group_a_price as v_products_group_a_price,';
			$filelayout_sql .=  'p.products_group_b_price as v_products_group_b_price,';
			$filelayout_sql .=  'p.products_group_c_price as v_products_group_c_price,';
			$filelayout_sql .=  'p.products_group_d_price as v_products_group_d_price,';
		}
		if ($ep_supported_mods['excl'] == true) { // Custom Mode for Exclusive Products Status VARCHAR(32)
			$filelayout_sql .=  'p.products_exclusive as v_products_exclusive,';
		}
		$zco_notifier->notify('EP4_EXTRA_FUNCTIONS_SET_FILELAYOUT_FULL_SQL_SELECT');

		if (count($custom_fields) > 0) { // User Defined Products Fields
			foreach ($custom_fields as $field) {
				$filelayout_sql .= 'p.'.$field.' as v_'.$field.',';
			}
		}
		$filelayout_sql .= ' p.products_weight as v_products_weight,
			p.product_is_call				as v_product_is_call,
			p.products_sort_order			as v_products_sort_order, 
			p.products_quantity_order_min	as v_products_quantity_order_min,
			p.products_quantity_order_units	as v_products_quantity_order_units,
			p.products_priced_by_attribute	as v_products_priced_by_attribute,
			p.product_is_always_free_shipping	as v_product_is_always_free_shipping,			
			p.products_date_available		as v_date_avail,
			p.products_date_added			as v_date_added,
			p.products_tax_class_id			as v_tax_class_id,
			p.products_quantity				as v_products_quantity,
			p.master_categories_id				as v_master_categories_id,
			p.manufacturers_id				as v_manufacturers_id,
			subc.categories_id				as v_categories_id,
			p.products_status				as v_status,
			p.metatags_title_status         as v_metatags_title_status,
			p.metatags_products_name_status as v_metatags_products_name_status,
			p.metatags_model_status         as v_metatags_model_status,
			p.metatags_price_status         as v_metatags_price_status,
			p.metatags_title_tagline_status as v_metatags_title_tagline_status 
			FROM '
			.TABLE_CATEGORIES.' as subc, '
			.TABLE_PRODUCTS_TO_CATEGORIES.' as ptoc, '
			.TABLE_PRODUCTS.' as p ';
			$zco_notifier->notify('EP4_EXTRA_FUNCTIONS_SET_FILELAYOUT_FULL_SQL_TABLE');

			$filelayout_sql .= ' WHERE 
			p.products_id = ptoc.products_id AND ';
      $zco_notifier->notify('EP4_EXTRA_FUNCTIONS_WHERE_FILELAYOUT_FULL_SQL_TABLE');

			$filelayout_sql .= '
			ptoc.categories_id = subc.categories_id '.$sql_filter;
		break;

	case 'featured': // added 5-2-2012
		$filelayout[] = 'v_products_model';
		$zco_notifier->notify('EP4_EXTRA_FUNCTIONS_SET_FILELAYOUT_FEATURED_FILELAYOUT');
    if (EP4_DB_FILTER_KEY === 'products_id' || EP4_DB_FILTER_KEY === 'blank_new') {
      $filelayout[] = 'v_products_id';
    }
    $filelayout[] = 'v_status';
		$filelayout[] = 'v_featured_date_added';
		$filelayout[] = 'v_expires_date';
		$filelayout[] = 'v_date_status_change';
		$filelayout[] = 'v_featured_date_available';

		$filelayout_sql = 'SELECT
			p.products_id             as v_products_id,
			p.products_model          as v_products_model,
			f.featured_id             as v_featured_id,
			f.featured_date_added     as v_featured_date_added,
			f.featured_last_modified  as v_featured_date_modified,
			f.expires_date            as v_expires_date,
			f.date_status_change      as v_date_status_change,
			f.status                  as v_status,
			f.featured_date_available as v_featured_date_available
			FROM '
			.TABLE_PRODUCTS.' as p,'
			.TABLE_FEATURED.' as f
			WHERE
			p.products_id = f.products_id';
		break;	
	
	case 'priceqty':
		$filelayout[] = 'v_products_model';
		$filelayout[] = 'v_products_name';
		$filelayout[] = 'v_status'; // 11-23-2010 added product status to price quantity option
		$filelayout[] = 'v_specials_price';
		$filelayout[] = 'v_specials_date_avail';
		$filelayout[] = 'v_specials_expires_date';
		$filelayout[] = 'v_products_price';
		if ($ep_supported_mods['uom'] == true) { // price UOM mod
			$filelayout[] = 'v_products_price_uom';
		}
		if ($ep_supported_mods['msrp'] == true) { // Manufacturer's Suggested Retail Price
			$filelayout[] = 'v_products_msrp'; 
		}
		if ($ep_supported_mods['map'] == true) { // Requested Mod Support - Manufacturer's Advertised Price
      $filelayout[] = 'v_map_enabled';
      $filelayout[] = 'v_map_price';
    }
		$filelayout[] = 'v_products_quantity';
		$filelayout_sql = 'SELECT
			p.products_id     as v_products_id,
			d.products_name   as v_products_name,
			p.products_status as v_status,
			p.products_model  as v_products_model,
			p.products_price  as v_products_price,
			p.manufacturers_id	as v_manufacturers_id,
			subc.categories_id	as v_categories_id,';
		if ($ep_supported_mods['uom'] == true) { // price UOM mod
			$filelayout_sql .= 'p.products_price_uom as v_products_price_uom,';
		}
		if ($ep_supported_mods['msrp'] == true) { // Requested Mod Support - Manufacturer's Suggest Retail Price
			$filelayout_sql .=  'p.products_msrp as v_products_msrp,'; 
		}	
		if ($ep_supported_mods['map'] == true) { // Requested Mod Support - Manufacturer's Advertised Price
      $filelayout_sql .= 'p.map_enabled as v_map_enabled,';
      $filelayout_sql .= 'p.map_price as v_map_price,';
    }
		$filelayout_sql .= 'p.products_tax_class_id as v_tax_class_id,
			p.products_quantity as v_products_quantity
			FROM '		
			.TABLE_PRODUCTS.' as p,'
			.TABLE_PRODUCTS_DESCRIPTION.' as d,'
			.TABLE_CATEGORIES.' as subc,'
			.TABLE_PRODUCTS_TO_CATEGORIES.' as ptoc
			WHERE
			p.products_id = ptoc.products_id AND
			d.products_id = p.products_id AND
			ptoc.categories_id = subc.categories_id '.$sql_filter; // added filter 4-13-2012	
		break;
		
	// Quantity price breaks file layout
	case 'pricebreaks':
		$filelayout[] =	'v_products_model';
		$filelayout[] = 'v_status'; // 11-23-2010 added product status to price quantity option
		$filelayout[] =	'v_products_price';
		if ($ep_supported_mods['uom'] == true) { // price UOM mod
			$filelayout[] = 'v_products_price_uom';
		}
		if ($ep_supported_mods['msrp'] == true) { // Manufacturer's Suggested Retail Price
			$filelayout[] = 'v_products_msrp'; 
		}
		if ($ep_supported_mods['map'] == true) { // Requested Mod Support - Manufacturer's Advertised Price
      $filelayout[] = 'v_map_enabled';
      $filelayout[] = 'v_map_price';
    }
		$filelayout[] =	'v_products_discount_type';
		$filelayout[] =	'v_products_discount_type_from';
		// discount quantities base on $max_qty_discounts	
		// must be a better way to get the maximum discounts used at any given time
		for ($i=1;$i<EASYPOPULATE_4_CONFIG_MAX_QTY_DISCOUNTS+1;$i++) {
			// $filelayout[] = 'v_discount_id_' . $i; // chadd - no longer needed
			$filelayout[] = 'v_discount_qty_'.$i;
			$filelayout[] = 'v_discount_price_'.$i;
		}
		$filelayout_sql = 'SELECT
			p.products_id     as v_products_id,
			p.products_status as v_status,
			p.products_model  as v_products_model,
			p.products_price  as v_products_price,
			p.manufacturers_id	as v_manufacturers_id,
			subc.categories_id	as v_categories_id,';
		if ($ep_supported_mods['uom'] == true) { // price UOM mod
			$filelayout_sql .= 'p.products_price_uom as v_products_price_uom,';
		}
		if ($ep_supported_mods['msrp'] == true) { // Requested Mod Support - Manufacturer's Suggest Retail Price
			$filelayout_sql .=  'p.products_msrp as v_products_msrp,'; 
		}	
		if ($ep_supported_mods['map'] == true) { // Requested Mod Support - Manufacturer's Advertised Price
      $filelayout_sql .= 'p.map_enabled as v_map_enabled,';
      $filelayout_sql .= 'p.map_price as v_map_price,';
    }
		$filelayout_sql .= 'p.products_discount_type as v_products_discount_type,
			p.products_discount_type_from as v_products_discount_type_from
			FROM '
			.TABLE_PRODUCTS.' as p,'
			.TABLE_CATEGORIES.' as subc,'
			.TABLE_PRODUCTS_TO_CATEGORIES.' as ptoc
			WHERE
			p.products_id = ptoc.products_id AND
			ptoc.categories_id = subc.categories_id '.$sql_filter; // added filter 4-13-2012		
	break;	

	case 'category': 
       $zco_notifier->notify('EP4_EXTRA_FUNCTIONS_SET_FILELAYOUT_CATEGORY_FILELAYOUT');

		// The file layout is dynamically made depending on the number of languages
		$filelayout[] = 'v_products_model';
    if (EP4_DB_FILTER_KEY != 'products_model') {
      $filelayout[] = 'v_' . (EP4_DB_FILTER_KEY === 'blank_new' ? 'products_id' : EP4_DB_FILTER_KEY);
    }
    // NEW code for unlimited category depth - 1 Category Column for each installed Language
		foreach ($langcode as $key => $lang) { // create categories variables for each language id
			$l_id = $lang['id'];
			$filelayout[] = 'v_categories_name_'.$l_id;
		} 
		$zco_notifier->notify('EP4_EXTRA_FUNCTIONS_SET_FILELAYOUT_CATEGORY_SQL_SELECT');

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

    // Categories Meta Data - added 12-02-2010
	// 12-10-2010 removed array_merge() for better performance
	case 'categorymeta':
    $zco_notifier->notify('EP4_EXTRA_FUNCTIONS_SET_FILELAYOUT_CATEGORYMETA_FILELAYOUT');

		$fileMeta = array();
		$filelayout = array();
		$filelayout[] = 'v_categories_id';
		$filelayout[] = 'v_categories_image';
    foreach ($langcode as $key => $lang) { // create categories variables for each language id
			$l_id = $lang['id'];
			$filelayout[] = 'v_categories_name_'.$l_id;
			$filelayout[] = 'v_categories_description_'.$l_id;
//			$filelayout[] = 'v_uri_' . $l_id;
		} 
		$zco_notifier->notify('EP4_EXTRA_FUNCTIONS_SET_FILELAYOUT_CATEGORY_SQL_SELECT');

		foreach ($langcode as $key => $lang) { // create metatags variables for each language id
			$l_id = $lang['id'];
			$filelayout[]   = 'v_metatags_title_'.$l_id;
			$filelayout[]   = 'v_metatags_keywords_'.$l_id;
			$filelayout[]   = 'v_metatags_description_'.$l_id;
		} 
    $filelayout[] = 'v_sort_order';
		$filelayout_sql = 'SELECT
			c.categories_id    AS v_categories_id,
			c.categories_image AS v_categories_image,
      c.sort_order    as v_sort_order
			FROM '
			.TABLE_CATEGORIES.' AS c';
		break;
	
	case 'attrib_detailed':
		$filelayout[] =	'v_products_attributes_id';
		$filelayout[] =	'v_products_id';
		$filelayout[] =	'v_products_model'; // product model from table PRODUCTS
		$filelayout[] =	'v_options_id';
		$filelayout[] =	'v_products_options_name'; // options name from table PRODUCTS_OPTIONS
		$filelayout[] =	'v_products_options_type'; // 0-drop down, 1=text , 2=radio , 3=checkbox, 4=file, 5=read only 
		$filelayout[] =	'v_options_values_id';
		$filelayout[] =	'v_products_options_values_name'; // options values name from table PRODUCTS_OPTIONS_VALUES
		$filelayout[] =	'v_options_values_price';
    if ($ep_supported_mods['dual']) {
      $filelayout[] = 'v_options_values_price_w';
    }
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
// table TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD		
		$filelayout[] =	'v_products_attributes_filename';
		$filelayout[] =	'v_products_attributes_maxdays';
		$filelayout[] =	'v_products_attributes_maxcount';
		
		
		// a = table PRODUCTS_ATTRIBUTES
		// p = table PRODUCTS
		// o = table PRODUCTS_OPTIONS
		// v = table PRODUCTS_OPTIONS_VALUES
		// d = table PRODUCTS_ATTRIBUTES_DOWNLOAD
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
			a.options_values_price              as v_options_values_price, ';
    if ($ep_supported_mods['dual']) {
$filelayout_sql .= '
      a.options_values_price_w            as v_options_values_price_w,
      ';
    }
$filelayout_sql .= '
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
			a.options_values_id = v.products_options_values_id AND
			o.language_id       = v.language_id AND
			o.language_id       = 1 ORDER BY a.products_id, a.options_id, v.products_options_values_id';
 		break;


	case 'attrib_basic': // simplified sinlge-line attributes ... eventually!
		// $filelayout[] =	'v_products_attributes_id';
		// $filelayout[] =	'v_products_id';
		$filelayout[] =	'v_products_model'; // product model from table PRODUCTS
		$filelayout[] =	'v_products_options_type'; // 0-drop down, 1=text , 2=radio , 3=checkbox, 4=file, 5=read only 
		foreach ($langcode as $key => $lang) { // create categories variables for each language id
			$l_id = $lang['id'];
			$filelayout[] = 'v_products_options_name_'.$l_id;
		} 
		foreach ($langcode as $key => $lang) { // create categories variables for each language id
			$l_id = $lang['id'];
			$filelayout[] = 'v_products_options_values_name_'.$l_id;
		}
		// a = table PRODUCTS_ATTRIBUTES
		// p = table PRODUCTS
		// o = table PRODUCTS_OPTIONS
		// v = table PRODUCTS_OPTIONS_VALUES
		$filelayout_sql = 'SELECT
			a.products_attributes_id            as v_products_attributes_id,
			a.products_id                       as v_products_id,
			a.options_id                        as v_options_id,
			a.options_values_id                 as v_options_values_id,
			p.products_model				    as v_products_model,
			o.products_options_id               as v_products_options_id,
			o.products_options_name             as v_products_options_name,
			o.products_options_type             as v_products_options_type,
			v.products_options_values_id        as v_products_options_values_id,
			v.products_options_values_name      as v_products_options_values_name,
			v.language_id                       as v_language_id
			FROM '
			.TABLE_PRODUCTS_ATTRIBUTES.     ' as a,'
			.TABLE_PRODUCTS.                ' as p,'
			.TABLE_PRODUCTS_OPTIONS.        ' as o,'
			.TABLE_PRODUCTS_OPTIONS_VALUES. ' as v
			WHERE
			a.products_id       = p.products_id AND
			a.options_id        = o.products_options_id AND
			a.options_values_id = v.products_options_values_id AND
			o.language_id       = v.language_id ORDER BY a.products_id, a.options_id, v.language_id, v.products_options_values_id';
 		break;
		
	case 'SBA_detailed':
		$filelayout[] =	'v_stock_id'; // stock id from SBA table
		$filelayout[] =	'v_products_id';
		$filelayout[] =	'v_stock_attributes'; 
		$filelayout[] =	'v_products_model'; // product model from table PRODUCTS
		$filelayout[] =	'v_quantity';
   	$ep_4_SBAEnabled = ep_4_SBA1Exists();
    if ($ep_4_SBAEnabled == '2') {
      $filelayout[] = 'v_customid';
    }
		$filelayout[] =	'v_sort';
		$filelayout[] =	'v_products_name'; // product name from table PRODUCTS
		$filelayout[] =	'v_products_options_name'; // options name from table PRODUCTS_OPTIONS
		$filelayout[] =	'v_products_options_values_name'; // options values name from table PRODUCTS_OPTIONS_VALUES
		$filelayout[] =	'v_products_attributes_id';
		$filelayout[] =	'v_products_options_type'; // 0-drop down, 1=text , 2=radio , 3=checkbox, 4=file, 5=read only 
		$filelayout[] =	'v_options_id';
		$filelayout[] =	'v_options_values_id';
//		$filelayout[] =	'v_options_values_price';
//		$filelayout[] =	'v_price_prefix';
//		$filelayout[] =	'v_products_options_sort_order';
//		$filelayout[] =	'v_product_attribute_is_free';
//		$filelayout[] =	'v_products_attributes_weight';
//		$filelayout[] =	'v_products_attributes_weight_prefix';
//		$filelayout[] =	'v_attributes_display_only';
//		$filelayout[] =	'v_attributes_default';
//		$filelayout[] =	'v_attributes_discounted';
//		$filelayout[] =	'v_attributes_image';
//		$filelayout[] =	'v_attributes_price_base_included';
//		$filelayout[] =	'v_attributes_price_onetime';
//		$filelayout[] =	'v_attributes_price_factor';
//		$filelayout[] =	'v_attributes_price_factor_offset';
//		$filelayout[] =	'v_attributes_price_factor_onetime';
//		$filelayout[] =	'v_attributes_price_factor_onetime_offset';
//		$filelayout[] =	'v_attributes_qty_prices';
//		$filelayout[] =	'v_attributes_qty_prices_onetime';
//		$filelayout[] =	'v_attributes_price_words';
//		$filelayout[] =	'v_attributes_price_words_free';
//		$filelayout[] =	'v_attributes_price_letters';
//		$filelayout[] =	'v_attributes_price_letters_free';
//		$filelayout[] =	'v_attributes_required';
// table TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD		
//		$filelayout[] =	'v_products_SBA_filename';
		$filelayout[] =	'v_products_attributes_filename';
		$filelayout[] =	'v_products_attributes_maxdays';
		$filelayout[] =	'v_products_attributes_maxcount';
		
		// a = table PRODUCTS_ATTRIBUTES
		// p = table PRODUCTS
		// o = table PRODUCTS_OPTIONS
		// v = table PRODUCTS_OPTIONS_VALUES
		// d = table PRODUCTS_ATTRIBUTES_DOWNLOAD
		// s = table PRODUCTS_WITH_ATTRIBUTES_STOCK
		// pd = table PRODUCTS_DESCRIPTIONS
		$filelayout_sql = 'SELECT DISTINCT
			a.products_attributes_id            as v_products_attributes_id,
			a.products_id                       as v_products_id,
			p.products_model				    as v_products_model,
			a.options_id                        as v_options_id,
			o.products_options_id               as v_products_options_id,
			o.products_options_name             as v_products_options_name,
			o.products_options_type             as v_products_options_type,
			a.options_values_id                 as v_options_values_id,
			v.products_options_values_id        as v_products_options_values_id,
			v.products_options_values_name      as v_products_options_values_name,'./*
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
			a.attributes_required               as v_attributes_required, */
			's.stock_id					 as v_stock_id,
			s.stock_attributes				 as v_stock_attributes,
			s.quantity					 as v_quantity,
			s.sort						 as v_sort,
			pd.products_name				 as v_products_name' . ( $ep_4_SBAEnabled == '2' ? ',
        s.customid            as v_customid ' : ' ') .
				'FROM '
			.TABLE_PRODUCTS_ATTRIBUTES.     ' as a,'
			.TABLE_PRODUCTS.                ' as p,'
			.TABLE_PRODUCTS_OPTIONS.        ' as o,'
			.TABLE_PRODUCTS_OPTIONS_VALUES. ' as v,'
			.TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK. ' as s,'
			.TABLE_PRODUCTS_DESCRIPTION.	  ' as pd
			WHERE
			a.products_id       = p.products_id AND
			pd.products_id		= p.products_id AND
			a.options_id        = o.products_options_id AND
			a.options_values_id = v.products_options_values_id AND
			o.language_id       = v.language_id AND
			o.language_id       = 1 AND
			s.products_id		= p.products_id AND
			s.stock_attributes	= a.products_attributes_id
			ORDER BY a.products_id, a.options_id, v.products_options_values_id';
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

  case 'orders_3': // No attributes
  case 'orders_1': // Export All
  case 'orders_2': // New Export all
  case 'orders_4': // Attributes Only
    // Filelayout is the same for orders_1 and orders_2
    $filelayout[] =	'v_date_purchased'; 
    $filelayout[] =	'v_orders_status_name';
    $filelayout[] =	'v_orders_id' ; 
    $filelayout[] =	'v_customers_id'; 
    $filelayout[] =	'v_customers_name'; 
    $filelayout[] =	'v_customers_company'; 
    $filelayout[] =	'v_customers_street_address'; 
    $filelayout[] =	'v_customers_suburb'; 
    $filelayout[] =	'v_customers_city'; 
    $filelayout[] =	'v_customers_postcode'; 
    //'v_customers_state'; 
    $filelayout[] =	'v_customers_country'; 
    $filelayout[] =	'v_customers_telephone'; 
    $filelayout[] =	'v_customers_email_address'; 
    $filelayout[] =	'v_products_model'; 
    $filelayout[] =	'v_products_name'; 
    if ($ep_dltype != 'orders_3') {
    $filelayout[] =	'v_products_options'; 
    $filelayout[] =	'v_products_options_values';
    }
    $filelayout[] = 'v_products_comments';

    // 'all types of query'
    $filelayout_sql = "SELECT DISTINCT 
      zo.orders_id as v_orders_id,
      zop.products_id as v_products_id,
      customers_id as v_customers_id,
      customers_name as v_customers_name,
      customers_company as v_customers_company,
      customers_street_address as v_customers_street_address,
      customers_suburb as v_customers_suburb,
      customers_city as v_customers_city,
      customers_postcode as v_customers_postcode,
      customers_country as v_customers_country,
      customers_telephone as v_customers_telephone,
      customers_email_address as v_customers_email_address,
      date_purchased as v_date_purchased,
      orders_status_name as v_orders_status_name,
      products_model as v_products_model,
      products_name as v_products_name,
      " . ( $ep_dltype != 'orders_3' ?
	  "products_options as v_products_options,
      products_options_values as v_products_options_values,
	  " : "") . 
	  "zo.order_total as v_total_cost,
      osh.comments as V_orders_comments 
      FROM " . TABLE_ORDERS . " zo, " . ($ep_dltype != 'orders_3' ? TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " opa, " : "") . 
	  TABLE_ORDERS_PRODUCTS . " zop, " . TABLE_ORDERS_STATUS." zos, " .
	  TABLE_ORDERS_STATUS_HISTORY . " osh 
      WHERE zo.orders_id = zop.orders_id AND
	  osh.orders_id = zo.orders_id 
      " . (($ep_dltype == 'orders_2' || $ep_dltype == 'orders_4') ? " AND zos.orders_status_id != :orders_status_id: " : "") . 
	  ($ep_dltype != 'orders_3' ? " AND zop.orders_products_id = opa.orders_products_id" : "") . "
      AND zo.orders_status = zos.orders_status_id 
		";
    $filelayout_sql = $db->bindVars($filelayout_sql, ':orders_status_id:', $_POST['configuration[order_status]'], 'integer');

//    echo $filelayout[] = $filelayout_sql;
    break;
  default:
    $zco_notifier->notify('EP4_EXTRA_FUNCTIONS_SET_FILELAYOUT_CASE_DEFAULT');
    break;
	}
  
//}
