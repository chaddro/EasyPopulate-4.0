<?php

// Database default values
$products_options_id  = 1; // this needs to auto increment for NEW products options
$language_id          = 1; // default 1=english - multi-language to be added in the future
$product_options_type = 0; // default 0=Dropdown, 1=Text, 2=Radio, 3=Checkbox, 4=File, 5=Read Only
$products_options_values_id = 1;

		// attribute import loop
		while ($contents = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) { // read 1 line of data from input file - while #1 - Main Loop
			// print_r($contents);
			// echo "<br>";
			$v_products_model = $contents[$filelayout['v_products_model']];

			// READ products_id and products_model from TABLE_PRODUCTS
			// Since products_model must be unique (for EP4 at least), this query can be LIMIT 1 
			$query ="SELECT * FROM ".TABLE_PRODUCTS." WHERE (products_model = '" . addslashes($v_products_model) . "') LIMIT 1";
			$result = ep_4_query($query);

			if (mysql_num_rows($result) == 0)  { // products_model is not in TABLE_PRODUCTS
				echo "SKIPPED: model not found:".$v_products_model."<br>"; // NEED TO ERROR OUT OF LOOP AND GIVE WARNING, CONTINUE ON REST OF FILE
				continue; // skip current record (returns to while #1)
			}

			// Find the correct product_model to edit
			
			// WHY USE WHILE HERE??? WHY NOT IF?			
			while($row = mysql_fetch_array($result)) { // BEGIN while #2
			
			// process single products_model - don't need "while #2"
			//$row = mysql_fetch_array($result)

				// echo "<br>model: >>> ".$row['products_model']."<br>";
				$v_products_id = $row['products_id'];
				// echo "id: >>> ".$row['products_id']."<br>";
				
				// why am I again testing products_model? I used that to query the database in the first place!
				if ($contents[$filelayout['v_products_model']] == $row['products_model']) { 
					// echo "model: ".$contents[$filelayout['v_products_model']]."<br>";
					// echo "options_name: ".$contents[$filelayout['v_products_options_name']]."<br>";
					// echo "options_type: ".$contents[$filelayout['v_products_options_type']]."<br>";
					// echo "values_names: ".$contents[$filelayout['v_products_options_values_name']]."<br>";
					
					$values_names = explode(',',$contents[$filelayout['v_products_options_values_name']]);
					// echo "count of values names: ".count($values_names).'<br>';
					$v_products_options_name = $contents[$filelayout['v_products_options_name']];
					$v_products_options_type = $contents[$filelayout['v_products_options_type']];
					$v_products_options_values_name = $contents[$filelayout['v_products_options_values_name']];
					
					
//					
// PRODUCTS OPTIONS NAMES
// This will insert a new product_options_name, and product_options_type into TABLE_PRODUCTS_OPTIONS
//					
				// READ products_options_id and products_options_name from TABLE_PRODUCTS_OPTIONS
				// Does products_options_name exist?
			
				// NOTE: DUPLICATE OPTIONS NAMES ARE HANDLED AS IF THEY ARE THE SAME OPTION NAME!!! 
				// Zencart, however, will let you define multiple option names with the same products_options_name value.
				// This works in zencart because products_options_name is not the key, products_options_id is the key.
				// To auto populate the attributes it is easier to use just products_options_name uniquely, otherwise you will have to also define the key.
				// This CAN be done, but it adds to the complexity of the import logic.
				// 
					$query  = "SELECT products_options_id, products_options_name FROM ".TABLE_PRODUCTS_OPTIONS." WHERE (products_options_name = '" . $v_products_options_name . "')";
					$result = ep_4_query($query);
		
				// insert new products_options_name
					if ($row = mysql_fetch_array($result))  { 
						$v_products_options_id = $row['products_options_id'];
				// get current products_options_id
					} else { // products_options_name is not in TABLE_PRODUCTS so ADD it
						$sql_max = "SELECT MAX(products_options_id) FROM ".TABLE_PRODUCTS_OPTIONS;
						$result_max = ep_4_query($sql_max);
						$row_max = mysql_fetch_array($result_max);
						
						$v_products_options_id = $row_max[0]+1;
						
						if (!is_numeric($products_options_id) ) { // i don't think this ever gets executed even when table is empty!!!
						    // echo '<br>assigning max id to 1';
							$v_products_options_id=1; 
						}
						
						// echo '<br>v_product_options_id max value: '.$v_products_options_id.'<br>';					
						// HERE ======> This resolves the missing entries for additionally defined languages beyond [1]			
						foreach ($langcode as $key => $lang) {
							$sql = "INSERT INTO ".TABLE_PRODUCTS_OPTIONS." 
								(products_options_id, language_id, products_options_name, products_options_type)
								VALUES
								('$v_products_options_id', '".$lang['id']."', '".$v_products_options_name."', '".zen_db_input($v_products_options_type)."')";
							$errorcheck = ep_4_query($sql);
						}
		/* old code 
		$sql = "INSERT INTO ".TABLE_PRODUCTS_OPTIONS." 
			(products_options_id, language_id, products_options_name, products_options_type)
			VALUES ('$v_products_options_id', '$language_id', '".$v_products_options_name."', '".zen_db_input($v_products_options_type)."')";
		$errorcheck = ep_4_query($sql);
		*/
					}
//
// BEGIN: PRODUCTS OPTIONS VALUES
//
			//  Begin assign values to individual product_options_id 
					$num_of_elements = count($values_names);
					$values_names_index = 0; // values_names index - array indexes start at zero
					$products_options_values_sort_order = 1;
					
			// Get max index id for TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS
					$sql_max2 = "SELECT MAX(products_options_values_to_products_options_id) max FROM ".TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS;
 					$result2 = ep_4_query($sql_max2);
					$row2 = mysql_fetch_array($result2);
					$products_options_values_to_products_options_id = $row2['max']+1;
					if ( !is_numeric($products_options_values_to_products_options_id) ) { 
						$products_options_values_to_products_options_id=1; }
					// echo '<br>products_options_values_to_products_options_id: '.$products_options_values_to_products_options_id.'<br>' ;
						
			// Get max index id for TABLE_PRODUCTS_OPTIONS_VALUES
					$sql_max3 = "SELECT MAX(products_options_values_id) max FROM ".TABLE_PRODUCTS_OPTIONS_VALUES;
 					$result3 = ep_4_query($sql_max3);
					$row3 = mysql_fetch_array($result3);
					$products_options_values_id = $row3['max']+1;
					if (!is_numeric($products_options_values_id) ) { 
						$products_options_values_id=1;
					}						
					// echo '<br>products_options_values_id: '.$products_options_values_id.'<br>' ;

					$exclude_array = array(1, 4); // exclude 1=TEXT, 4=FILE are special cases and are assigned products_options_values=0
					while ($values_names_index < $num_of_elements) { // BEGIN: while #3
					
						// TABLE: products_options_values 
						// This does not take into account that you can have a larger options_values set than on the current product
						// it is just auto incrementing
						if (in_array($v_products_options_type, $exclude_array)) { 
							// do not process excluded types into TABLE_PRODUCTS_OPTIONS_VALUES
						} else {
							// look for existing products_options_name associated with products_options_id
							$sql = "SELECT 
								a.products_options_id,
								a.products_options_values_id,
								b.products_options_values_id,
								b.products_options_values_name
								FROM "
								.TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS." as a, "
								.TABLE_PRODUCTS_OPTIONS_VALUES." as b 
								WHERE 
								a.products_options_id = '".$v_products_options_id."' AND
								a.products_options_values_id   = b.products_options_values_id AND
								b.products_options_values_name = '".$values_names[$values_names_index]."'";

 							$result4 = ep_4_query($sql);
			 				// if $result4 == 0, products_options_values_name not found
							if (mysql_num_rows($result4) == 0)  { // products_options_name is not in TABLE_PRODUCTS_OPTIONS_VALUES
								// insert New products_options_values_name
								// HERE ===========> changed to add language entries for all defined languages					
								foreach ($langcode as $key => $lang) {
								$sql = "INSERT INTO ".TABLE_PRODUCTS_OPTIONS_VALUES." 
									(products_options_values_id, 
									language_id, 
									products_options_values_name, 
									products_options_values_sort_order)
									VALUES ( 
									'$products_options_values_id', 
									'".$lang['id']."', 
									'".$values_names[$values_names_index]."',
									'$products_options_values_sort_order')";
									$errorcheck = ep_4_query($sql);
								}
					/* old code
							$errorcheck = ep_4_query("INSERT INTO ".TABLE_PRODUCTS_OPTIONS_VALUES." 
								(products_options_values_id, 
								language_id, 
								products_options_values_name, 
								products_options_values_sort_order)
								VALUES ( 
								'$products_options_values_id', 
								'$language_id', 
								'".$values_names[$values_names_index]."',
								'$products_options_values_sort_order')");
					*/
							} else { // this is an existing products_options_values_name assisgned to this products_options_name
							}
						}


						// TABLE: products_options_values_to_products_options
						// Now associate "product_options_values_id" with "product_options_id" so the names can be associated with a product_id
						if (in_array($v_products_options_type, $exclude_array)) { // excluded type get special TABLE_PRODUCTS_OPTIONS_VALUES id=0 (a TEXT type)
							$sql5 = "SELECT
								products_options_id,
								products_options_values_id
								FROM "
								.TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS."
								WHERE
								products_options_id =  '".$v_products_options_id."' AND
								products_options_values_id = '0'";
							$result5 = ep_4_query($sql5);
			 				// if $result5 == 0, combination not found
							if (mysql_num_rows($result5) == 0)  { // combination is not in TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS
								// insert new combination
								$errorcheck = ep_4_query("INSERT INTO ".TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS."
								(products_options_values_to_products_options_id, products_options_id, products_options_values_id) 
								VALUES('$products_options_values_to_products_options_id', '$v_products_options_id', '0')");
							} else { // duplicate entry, skip
							} 
						} else { // add $products_options_values_id
							$sql5 = "SELECT 
								a.products_options_id,
								a.products_options_values_id,
								b.products_options_values_id,
								b.products_options_values_name
								FROM "
								.TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS. " as a, "
								.TABLE_PRODUCTS_OPTIONS_VALUES. " as b 
								WHERE 
								a.products_options_id = '".$v_products_options_id."' AND
								a.products_options_values_id = b.products_options_values_id AND
								b.products_options_values_name = '". $values_names[$values_names_index]."'";
								
							$result5 = ep_4_query($sql5);
			 				// if $result5 == 0, combination not found
							if (mysql_num_rows($result5) == 0)  { // combination is not in TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS
								$errorcheck = ep_4_query("INSERT INTO ".TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS."
									(products_options_values_to_products_options_id, products_options_id, products_options_values_id) 
									VALUES('$products_options_values_to_products_options_id', '$v_products_options_id', '$products_options_values_id')");
							} else { // duplicate entry, skip
							}
						}
					
					
						// TABLE: products_attributes
						// Finish up by associating the correct set of options with as an attribute_id
						if (in_array($v_products_options_type, $exclude_array)) { // 
							$errorcheck = ep_4_query("INSERT INTO ".TABLE_PRODUCTS_ATTRIBUTES."
							(products_id, options_id, options_values_id) 
							VALUES ('".$v_products_id."', '".$v_products_options_id."','0')");
						} else {
							$sql5 = "SELECT 
								a.products_options_id,
								a.products_options_values_id,
								b.products_options_values_id,
								b.products_options_values_name
								FROM "
								.TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS. " as a, "
								.TABLE_PRODUCTS_OPTIONS_VALUES. " as b 
								WHERE 
								a.products_options_id = '".$v_products_options_id."' AND
								a.products_options_values_id = b.products_options_values_id AND
								b.products_options_values_name = '". $values_names[$values_names_index]."'";
							$result5 = ep_4_query($sql5);
							
							// echo '<br>number of rows:'.mysql_num_rows($result5);
							$row5 = mysql_fetch_array($result5);
							// print_r($row5);
							$a_products_options_values_id = $row5['products_options_values_id'];
							// echo '<br>value: '.$a_products_options_values_id;
							
							// INSERT vs UPDATE!!!
							// need to query the v_products_id, v_products_options_id, and a_products_options_values_id
							// if found update, else insert new values
							
							$sql6 = "SELECT * FROM "
								.TABLE_PRODUCTS_ATTRIBUTES. "
								WHERE 
								`products_id` = '".$v_products_id."' AND
								`options_id` = '".$v_products_options_id."' AND
								`options_values_id` = '".$a_products_options_values_id."'";
							// echo '<br>SQL6-> '.$sql6;
							
							$result6 = ep_4_query($sql6);
							// echo '<br>number of rows:'.mysql_num_rows($result6);
							$row6 = mysql_fetch_array($result6);
							// print_r($row6);						
							
							if (mysql_num_rows($result6) == 0)  {
							  $errorcheck = ep_4_query("INSERT INTO ".TABLE_PRODUCTS_ATTRIBUTES."
								  (products_id, options_id, options_values_id) 
								  VALUES ('".$v_products_id."', '".$v_products_options_id."','".$a_products_options_values_id."')");
							} else { // UPDATE
							  $sql7 ="UPDATE ".TABLE_PRODUCTS_ATTRIBUTES." SET
								`products_options_sort_order`='" . $values_names_index . "'
								WHERE
								`products_id` = '".$v_products_id."' AND
								`options_id` = '".$v_products_options_id."' AND
 								`options_values_id` = '".$a_products_options_values_id."'";
								// echo '<br>SQL7-> '.$sql7;
							  $errorcheck = ep_4_query($sql7);
							}
						}

				// $products_options_values_id++;
				// Get max index id for TABLE_PRODUCTS_OPTIONS_VALUES
						$sql_max3 = "SELECT MAX(products_options_values_id) max FROM ".TABLE_PRODUCTS_OPTIONS_VALUES;
						$result3 = ep_4_query($sql_max3);
						$row3    = mysql_fetch_array($result3);
						$products_options_values_id = $row3['max']+1;
						
				// $products_options_values_to_products_options_id++; // this is safe to increment here??
				// Get max index id for TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS
						$sql_max2 = "SELECT MAX(products_options_values_to_products_options_id) max FROM ".TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS;
						$result2 = ep_4_query($sql_max2);
						$row2    = mysql_fetch_array($result2);
						$products_options_values_to_products_options_id = $row2['max']+1;

						$values_names_index++;
						$products_options_values_sort_order++; 
					
					} // END: while #3
// 
// END: PRODUCTS OPTIONS VALUES
// 
					
				} // END: if                           
			}  // END: while #2
		} // END: while #1
?>