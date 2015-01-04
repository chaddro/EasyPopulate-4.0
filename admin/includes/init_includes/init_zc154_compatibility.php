<?php
// -----
// Common Module (used by many plugins) to provide downward compatibility to Zen Cart v1.5.4 admin-level changes.
// Copyright (c) 2014 Vinos de Frutas Tropicales
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
// -----
// This plugin uses a Zen Cart v1.5.4 "core-file base".  One or more files overwritten for this plugin make
// use of a function (zen_record_admin_activity) that was introduced in Zen Cart v1.5.4.  That function (present in
// /admin/includes/classes/class.admin.zcObserverLogEventListener.php) loads at checkpoint 65.
//
// This init-file, also loaded at CP-65, provides the definition for the zen_record_admin_activity function IF IT IS
// NOT PREVIOUSLY DEFINED.  That way, the various functions in the overwritten core files will have their function-interface
// satisfied.
//
if (!function_exists ('zen_record_admin_activity')) {
  function zen_record_admin_activity ($message, $severity) {
  }
}