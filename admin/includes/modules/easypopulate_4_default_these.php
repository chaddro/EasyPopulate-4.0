<?php
/*
 * When updating products info, these values are used for existing data 
 *   This allows a reduced number of columns to be used on updates  
 *   otherwise these would have to be exported/imported every time 
 *   this ONLY applies to when a column is MISSING 
 * includes/modules/easypopulate_4_default_these.php
 */

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

