<?php

/*
 * This is the file processed if the import file is a attrib-detailed-ep file.
 *
 */

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
