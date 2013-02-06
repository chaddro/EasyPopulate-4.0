<?php
// $Id: reg_easypopulate_4.php, v4.0.21 06-01-2012 chadderuski $
// NOTE: This is for registering the mod in zencart 1.5+ only
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
if (function_exists('zen_register_admin_page')) {
    if (!zen_page_key_exists('easypopulate_4')) {
        // Add easypopulate_4 to Tools menu
        zen_register_admin_page('easypopulate_4', 'BOX_TOOLS_EASYPOPULATE_4','FILENAME_EASYPOPULATE_4', '', 'tools', 'Y', 97);
    }
}
?>
