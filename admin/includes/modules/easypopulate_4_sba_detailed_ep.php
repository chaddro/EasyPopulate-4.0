<?php

/*
 * This is the file processed if the import file is a attrib-detailed-ep file.
 * includes\modules\easypopulate_4_sba_detailed_ep.php, v4.0.35.ZC.2 10-03-2016 mc12345678 $
 */

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
