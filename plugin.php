<?php
/* 
    Plugin Name: VS Booking
    Plugin URI: 
    Description: Plugin for easy managing hotel bookings.
    Author: <a href="http://www.linkedin.com/in/valentinslavov">Valentin Slavov</a>
    Version: 0.9.0
*/

include ("plugin_admin.php");
/*
* Causes "The plugin generated 5 characters of unexpected output during activation. If you notice “headers already sent” messages, problems with syndication * * feeds or other issues, try deactivating or removing this plugin." on * activation...
* ...but still works.
*/
register_activation_hook( __FILE__, array( 'vsb_admin_base', 'vsb_admin_create_table_types' ) );
register_activation_hook( __FILE__, array( 'vsb_admin_base', 'vsb_admin_create_table_rooms' ) );
register_activation_hook( __FILE__, array( 'vsb_admin_base', 'vsb_admin_create_table_reservations' ) );
?>