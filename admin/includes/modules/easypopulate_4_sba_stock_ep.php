<?php

/*
 * This is the file processed if the import file is a attrib-detailed-ep file.
 *
 */

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
