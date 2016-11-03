<?php

/*
 * This is the file processed if the import file is a featured-ep file.
 * $Id: includes\modules\easypopulate_4_featured_ep.php, v4.0.35.ZC.2 10-03-2016 mc12345678 $
 */

      // check products table to see if product_model exists
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
            if (isset($filelayout['v_expires_date']) && $items[$filelayout['v_expires_date']] > '0001-01-01') {
            $v_expires_date = $items[$filelayout['v_expires_date']];
            } else {
              $v_expires_date = '0001-01-01';
            }
            if (isset($filelayout['v_featured_date_available']) && $items[$filelayout['v_featured_date_available']] > '0001-01-01') {
            $v_featured_date_available = $items[$filelayout['v_featured_date_available']];
            } else {
              $v_featured_date_available = '0001-01-01';
            }
            if (($v_today >= strtotime($v_featured_date_available)) && ($v_today < strtotime($v_expires_date)) || ($v_today >= strtotime($v_featured_date_available) && $v_featured_date_available != '0001-01-01' && $v_expires_date == '0001-01-01') || ($v_featured_date_available == '0001-01-01' && $v_expires_date == '0001-01-01' && (defined('EP4_ACTIVATE_BLANK_FEATURED') ? EP4_ACTIVATE_BLANK_FEATURED : true))) {
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
            if (isset($filelayout['v_expires_date']) && $items[$filelayout['v_expires_date']] > '0001-01-01') {
            $v_expires_date = $items[$filelayout['v_expires_date']];
            } else {
              $v_expires_date = '0001-01-01';
            }
            if (isset($filelayout['v_featured_date_available']) && $items[$filelayout['v_featured_date_available']] > '0001-01-01') {
            $v_featured_date_available = $items[$filelayout['v_featured_date_available']];
            } else {
              $v_featured_date_available = '0001-01-01';
            }
            if (($v_today >= strtotime($v_featured_date_available)) && ($v_today < strtotime($v_expires_date)) || ($v_today >= strtotime($v_featured_date_available) && $v_featured_date_available != '0001-01-01' && $v_expires_date == '0001-01-01') || ($v_featured_date_available == '0001-01-01' && $v_expires_date == '0001-01-01' && (defined('EP4_ACTIVATE_BLANK_FEATURED') ? EP4_ACTIVATE_BLANK_FEATURED : true))) {
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
