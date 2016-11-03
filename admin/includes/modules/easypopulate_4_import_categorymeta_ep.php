<?php

/*
 * This is the file processed if the import file is a categorymeta-ep file.
 * $Id: includes\modules\easypopulate_4_import_categorymeta_ep.php, v4.0.35.ZC.2 10-03-2016 mc12345678 $
 */

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
