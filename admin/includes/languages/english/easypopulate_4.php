<?php
// $Id: easypopulate_4.php, v4.0.21 06-01-2012 chadderuski $

// $display_output defines

// file uploads display - output via $display_output
define('EASYPOPULATE_4_DISPLAY_SPLIT_LOCATION','You can also download your split files from your %s directory<br />');
define('EASYPOPULATE_4_DISPLAY_HEADING','<br /><h3><u>Import Results</u></h3><br />');
define('EASYPOPULATE_4_DISPLAY_UPLOADED_FILE_SPEC','<p class=smallText>File uploaded.<br />Temporary filename: %s<br /><b>User filename: %s</b><br />Size: %s<br />'); // open paragraph
define('EASYPOPULATE_4_DISPLAY_LOCAL_FILE_SPEC','<p class=smallText><b>Filename: %s</b><br />'); // open paragraph

// upload results display - output via $display_output
define('EASYPOPULATE_4_DISPLAY_RESULT_DELETED','<br /><font color="fuchsia"><b>DELETED! - Model:</b> %s</font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_DELETE_NOT_FOUND','<br /><font color="darkviolet"><b>NOT FOUND! - Model:</b> %s - cant delete...</font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_NOT_FOUND', '<br /><font color="red"><b>SKIPPED! - Model:</b> %s - No category provided for this%s product</font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_NAME_LONG','<br /><font color="red"><b>SKIPPED! - Model:</b> %s - Category name: "%s" exceeds max. length: %s</font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_PRODUCTS_URL_LONG','<br /><font color="red"><b>WARNING! - Model:</b> %s - URL: "%s" exceeds max. length: %s</font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_MANUFACTURER_NAME_LONG','<br /><font color="red"><b>MANUFACTURER SKIPPED! - Manufacturer:</b> %s - Manufacturer name exceeds max. length: %s</font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_PRODUCTS_MODEL_LONG','<br /><font color="red"><b>SKIPPED! - Model: </b>%s - Products model exceeds max. length: %s</font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_PRODUCTS_NAME_LONG','<br /><font color="red"><b>WARNING! - Model: </b>%s - Products name: "%s" exceeds max. length: %s</font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_NEW_PRODUCT', '<br /><font color="green"><b>NEW PRODUCT! - Model:</b> %s</font> | ');
define('EASYPOPULATE_4_DISPLAY_RESULT_NEW_PRODUCT_FAIL', '<br /><font color="red"><b>ADD NEW PRODUCT FAILED! - Model:</b> %s - SQL error. Check Easy Populate error log in uploads directory</font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_UPDATE_PRODUCT', '<br /><font color="mediumblue"><b>UPDATED! - Model:</b> %s</font> | ');
define('EASYPOPULATE_4_DISPLAY_RESULT_UPDATE_PRODUCT_FAIL', '<br /><font color="red"><b>UPDATE PRODUCT FAILED! - Model:</b> %s - SQL error. Check Easy Populate error log in uploads directory</font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_NO_MODEL', '<br /><font color="red"><b>No model field in record. This line was not imported</b></font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_UPLOAD_COMPLETE','<br /><b>Upload Complete</b></p>'); // close paragraph above
define('EASYPOPULATE_4_DISPLAY_RESULT_ARTISTS_NAME_LONG','<br /><font color="red"><b>SKIPPED! - Artist Name:</b> %s - exceeds max. length: %s</font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_RECORD_COMPANY_NAME_LONG','<br /><font color="red"><b>SKIPPED! - Record Company Name:</b> %s - exceeds max. length: %s</font>');
define('EASYPOPULATE_4_DISPLAY_RESULT_MUSIC_GENRE_NAME_LONG','<br /><font color="red"><b>SKIPPED! - Music Genre Name:</b> %s - exceeds max. length: %s</font>');

// $messageStack defines
// checks - msg stack alerts - output via $messageStack
define('EASYPOPULATE_4_MSGSTACK_TEMP_FOLDER_MISSING','<b>Easy Populate "Uploads Folder" missing!</b><br />Your uploads folder is missing. Your configuration indicates that your uploads folder is named <b>%s</b>, and is located in <b>%s</b>.<br>');
define('EASYPOPULATE_4_MSGSTACK_TEMP_FOLDER_NOT_WRITABLE','<b>Easy Populate "Uploads Folder" is not writable!</b><br />Your uploads folder is not writable. Folder permissions for <b>%s</b> must be "777" or "755" depending on your server setup.<br>You must correct folder permissions before contining.<br>');
define('EASYPOPULATE_4_MSGSTACK_TEMP_FOLDER_PERMISSIONS_SUCCESS','Easy Populate successfully adjusted the permissions on your uploads folder! You can now upload files using Easy Populate...');
define('EASYPOPULATE_4_MSGSTACK_TEMP_FOLDER_PERMISSIONS_SUCCESS_777','Easy Populate successfully adjusted the permissions on your uploads folder, but the folder is now publically viewable. Please ensure that you added the index.html file to this directory to prevent public browsing/downloading of your Easy Populate files.');
define('EASYPOPULATE_4_MSGSTACK_MODELSIZE_DETECT_FAIL','Easy Populate cannot determine the maximum size permissible for the products_model field in your products table. Please ensure that the length of your model data field does not exceed the Zen Cart default value of 32 characters for any given product. Failure to heed this warning may have unintended consequences for your data.');
define('EASYPOPULATE_4_MSGSTACK_ERROR_SQL', 'An SQL error has occured. Please check your input data for tabs within fields and delete these. If this error continues, please forward your error log to the Easy Populate maintainer');
define('EASYPOPULATE_4_MSGSTACK_DROSS_DELETE_FAIL', '<b>Deleting of product data debris failed!</b> Please see the debug log in your uploads directory for further information.');
define('EASYPOPULATE_4_MSGSTACK_DROSS_DELETE_SUCCESS', 'Deleting of product data debris succeeded!');
define('EASYPOPULATE_4_MSGSTACK_DROSS_DETECTED', '<b>%s partially deleted product(s) found!</b> Delete this dross to prevent unwanted zencart behaviour by clicking <a href="%s">here.</a><br />You are seeing this because there are references in tables to a product that no longer exists, which is usually caused by an incomplete product deletion. This can cause Zen Cart to misbehave in certain circumstances.');
define('EASYPOPULATE_4_MSGSTACK_DATE_FORMAT_FAIL', '%s is not a valid date format. If you upload any date other than raw format (such as from Excel) you will mangle your dates. Please fix this by correcting your date format in the Easy Populate config.');

// install - msg stack alerts - output via $messageStack
define('EASYPOPULATE_4_MSGSTACK_INSTALL_DELETE_SUCCESS','Redundant file <b>%s</b> was deleted from <b>YOUR_ADMIN%s</b> directory.');
define('EASYPOPULATE_4_MSGSTACK_INSTALL_DELETE_FAIL','Easy Populate was unable to delete redundant file <b>%s</b> from <b>YOUR_ADMIN%s</b> directory. Please delete this file manually.');
define('EASYPOPULATE_4_MSGSTACK_INSTALL_CHMOD_FAIL','<b>Please run the Easy Populate install again after fixing your uploads directory problem.</b>');
define('EASYPOPULATE_4_MSGSTACK_INSTALL_CHMOD_SUCCESS','<b>Configuration Variables Installation Successfull!</b>');
define('EASYPOPULATE_4_MSGSTACK_INSTALL_KEYS_FAIL','<b>Easy Populate Configuration Missing.</b>  Please install your configuration by clicking %sInstall EP4%s');

// file handling - msg stack alerts - output via $messageStack
define('EASYPOPULATE_4_MSGSTACK_FILE_EXPORT_SUCCESS', 'File <b>%s.csv</b> successfully exported! The file is ready for download in your /%s directory.');

// $specials_print defines
// results of specials in $specials_print
define('EASYPOPULATE_4_SPECIALS_HEADING', '<b><u>Specials Summary</u></b><p class=smallText>'); // open paragraph
define('EASYPOPULATE_4_SPECIALS_PRICE_FAIL', '<font color="red"><b>SKIPPED! - Model:</b> %s - specials price higher than normal price...</font><br />');
define('EASYPOPULATE_4_SPECIALS_NEW', '<font color="green"><b>NEW! - Model:</b> %s</font> | %s | %s | <font color="green"><b>%s</b></font> |<br />');
define('EASYPOPULATE_4_SPECIALS_UPDATE', '<font color="mediumblue"><b>UPDATED! - Model:</b> %s</font> | %s | %s | <font color="green"><b>%s</b></font> |<br />');
define('EASYPOPULATE_4_SPECIALS_DELETE', '<font color="fuchsia"><b>DELETED! - Model:</b> %s</font> | %s |<br />');
define('EASYPOPULATE_4_SPECIALS_DELETE_FAIL', '<font color="darkviolet"><b>NOT FOUND! - Model:</b> %s - cannot delete special...</font><br />');
define('EASYPOPULATE_4_SPECIALS_FOOTER', '</p>'); // close paragraph

// error log defines - for ep_debug_log.txt
define('EASYPOPULATE_4_ERRORLOG_SQL_ERROR', 'MySQL error %s: %s\nWhen executing:\n%sn');
?>