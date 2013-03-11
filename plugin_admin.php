<?php
session_start();
define("plugin_db_prefix", "vsb_");
class vsb_admin_base {

	function __construct() {
		wp_register_script( 'timeframe', plugins_url('/js/timeframe.js', __FILE__) );
		wp_register_script( 'vsbookings', plugins_url('/js/vsbookings.js', __FILE__) );
		wp_register_style( 'timeframe', plugins_url('/css/timeframe.css', __FILE__) );
		wp_register_style( 'tables', plugins_url('/css/table.css', __FILE__) );
		
		global $wpdb;
		$table_rooms = $wpdb->prefix . plugin_db_prefix . 'rooms';
		
		$this->vsb_admin_add_menu();
		$this->vsb_admin_add_shortcodes();
	}
	
	function vsb_get_table($table) { global $wpdb; return $table_types = $wpdb->prefix . plugin_db_prefix . $table; }
	
	function vsb_admin_create_table_types() {
		global $wpdb;
		$table_types = $wpdb->prefix . plugin_db_prefix . 'room_types';
		$sql = "CREATE TABLE $table_types (
				id tinyint NOT NULL AUTO_INCREMENT,
				room_name text CHARACTER SET utf8 NOT NULL,
				rooms_quantity integer NOT NULL,
				UNIQUE KEY id (id));";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); //in order to use dbDelta - WP's function for table examination and update
		dbDelta($sql);
	}
	
	function vsb_admin_create_table_rooms() {
		global $wpdb;
		$table_rooms = $wpdb->prefix . plugin_db_prefix . 'rooms';
		$sql = "CREATE TABLE $table_rooms (
				id tinyint NOT NULL AUTO_INCREMENT,
				room_type tinyint NOT NULL,
				room_price decimal(7, 2) NOT NULL,
				UNIQUE KEY id (id));";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	
	function vsb_admin_create_table_reservations() {
		global $wpdb;
		$table_reservations = $wpdb->prefix . plugin_db_prefix . 'reservations';
		$sql = "CREATE TABLE $table_reservations (
				id tinyint NOT NULL AUTO_INCREMENT,
				room_id integer NOT NULL,
				start_date date NOT NULL,
				end_date date NOT NULL,
				email varchar(255) NOT NULL,
				phone varchar(15) NOT NULL,
				name varchar(255) NOT NULL,
				approved tinyint DEFAULT '0' NOT NULL,
				UNIQUE KEY id (id));";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	
	function vsb_admin_add_shortcodes() {		
		add_shortcode( 'bookings', array(&$this,'vsb_admin_bookings_shortcode'));
	}
	
	function vsb_admin_dependecies() {
		wp_enqueue_script('prototype');
		wp_enqueue_script('timeframe');
		wp_enqueue_script('vsbookings');
		wp_enqueue_style('timeframe');
	} 
	
	function vsb_admin_bookings_shortcode() {
		global $wpdb;
		$this->vsb_admin_dependecies();		
		$error = null;
		
		if (isset($_SESSION['error'])){
			$error = $_SESSION['error'];
			unset($_SESSION['error']);
		}		
		
		$page = "<center><form method='POST' action='".get_bloginfo('url')."/wp-content/plugins/VSBookings/process.php'>" .
				"<b>" . $error . "</b></br>" .
				"Room type: <select name='room_type'>";
		
		$table_types = $this->vsb_get_table('room_types');
		$result = $wpdb->get_results( "SELECT * FROM $table_types" );		
				
		foreach($result as $row) { $page .= "<option value='".$row->id."'>".$row->room_name."</option>"; }
				
		$page .= "</select></br>".
				"Name: <input type='text' name='name' size='10' /></br>".
				"E-mail: <input type='text' name='email' size='10' /></br>".
				"Phone: <input type='text' name='phone' size='10' /></br>".
				"<div id='calendars'></div></br>".
				"   <div id='fields'>
						<span>
							<input type='text' name='startfield' value='' size=10 id='start'/>
							&ndash;
							<input type='text' name='endfield' value='' size=10 id='end'/>
						</span>
						</div>".
				"<input type='hidden' name='action' value='reserve' />".
				"<input type='submit' name='submit' value='Reserve' />".
				"</form></center>";	
				
		return $page;
	}
	
	function vsb_admin_add_menu() {	
		add_action('admin_menu', array(&$this, 'vsb_add_menu_callback'));
	}
	
	function vsb_add_menu_callback() {

		add_menu_page( 'VS Bookings', 'VS Bookings', 'manage_options', 'VSB_administration', array(&$this, 'vsb_home_admin'));
		
		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page( 'VSB_administration', 'VSB: Room types', 'Room types', 'manage_options', 'vsb_types_admin', array(&$this, 'vsb_types_admin'));
			add_submenu_page( 'VSB_administration', 'VSB: Statistics', 'Statistics', 'manage_options', 'vsb_stats_admin', array(&$this,'vsb_stats_admin'));
		}
		if ( current_user_can( 'publish_posts' ) ) {
			add_submenu_page( 'VSB_administration', 'VSB: Queue', 'Queue', 'publish_posts', 'vsb_queue_admin', array(&$this,'vsb_queue_admin'));
			add_submenu_page( 'VSB_administration', 'VSB: Bookings', 'Bookings', 'publish_posts', 'vsb_bookings_admin', array(&$this,'vsb_bookings_admin'));
		} else {
				wp_die( __( 'You do not have permission to access this page.' ) );
		}
	}
	
	function vsb_admin_list_room_types() {
		global $wpdb;
		wp_enqueue_style('tables');
		$table_rooms = $this->vsb_get_table('rooms');
		$table_types = $this->vsb_get_table('room_types');
		$result = $wpdb->get_results( "SELECT DISTINCT A.id, A.room_name, A.rooms_quantity, B.room_price FROM $table_types A JOIN $table_rooms B ON A.id = B.room_type");

		echo "<div id='lists'><form method='POST' action='".get_bloginfo('url')."/wp-content/plugins/VSBookings/process.php'>".
			 "<table border=0><th>Type ID</th><th>Name</th><th>Price per night</th><th>Number of rooms</th><th>Delete</th>";
		$i = 0;
		foreach($result as $row) {
			$i++;
			if ($i % 2 == 0) { echo "<tr>"; } else { echo "<tr class='odd'>"; }
			echo "<td>".$row->id."</td>";
			echo "<td>".$row->room_name."</td>";
			echo "<td>".$row->room_price."</td>";
			echo "<td>".$row->rooms_quantity."</td>";
			echo "<td><input type='checkbox' name='delete[]' value='".$row->id."'/></td></tr>";
		}
		echo "</table></div><br/><input type='submit' name='submit' value='Delete selected' /><input type='hidden' name='action' value='delete_room_types' /></form>";
	}
	
	function vsb_admin_list_reservations($approved) {
		wp_enqueue_style('tables');
		global $wpdb;
		$table_reservations = $this->vsb_get_table('reservations');
		$table_rooms = $this->vsb_get_table('rooms');
		$count = $wpdb->get_var("SELECT COUNT(id) FROM $table_reservations WHERE approved = '$approved'");
		$recordsPerPage = 35;
		$currentPage = (isset($_GET['p'])) ? $_GET['p'] : 1;		
		$result = $wpdb->get_results( "SELECT A.*, B.room_price FROM $table_reservations A JOIN $table_rooms B ON A.room_id = B.id WHERE A.approved = '$approved' LIMIT $recordsPerPage OFFSET ".$recordsPerPage*($currentPage-1));
		$pagesCount = ceil($count / $recordsPerPage);
		$i = 0;
		echo "<div id='lists'><form method='POST' action='".get_bloginfo('url')."/wp-content/plugins/VSBookings/process.php'><table border=0 cellspacing=5><th>Reservation ID</th><th>Room №</th><th>Name</th><th>Price per night</th><th>Total price</th><th>Start date</th><th>End date</th><th>Stay</th><th>E-mail</th><th>Phone</th>".((!$approved) ? "<th>Approve</th>" : "")."<th>Delete</th>";
		foreach($result as $row) {
			$start = new DateTime("$row->start_date");
			$end = new DateTime("$row->end_date");
			$interval = date_diff($start, $end);
			$calc = $interval->format('%a');
			$calc1 = $calc * $row->room_price;
			$i++;
			if ($i % 2 == 0) { echo "<tr>"; } else { echo "<tr class='odd'>"; }
			echo "<td>".$row->id."</td>";
			echo "<td>".$row->room_id."</td>";
			echo "<td>".$row->name."</td>";
			echo "<td>".$row->room_price."</td>";
			echo "<td>".$calc1."</td>";
			echo "<td>".$row->start_date."</td>";
			echo "<td>".$row->end_date."</td>";
			echo "<td>".$interval->format('%a nights')."</td>";
			echo "<td>".$row->email."</td>";
			echo "<td>".$row->phone."</td>";
			if (!$approved): echo "<td><input type='checkbox' name='approve[]' value='".$row->id."'/></td>"; endif;
			echo "<td><input type='checkbox' name='delete[]' value='".$row->id."'/></td>";			
			echo "</tr>";
		}		
		echo "</table></div></br><input type='submit' name='submit' value='Update selected' /><input type='hidden' name='action' value='delete_reservations' /></form>";		
		
		if ($currentPage > 1) { echo "<a href='?page=".$_GET['page']."&p=".($currentPage-1)."'>Previous page</a>&nbsp;"; }
		if ($currentPage < $pagesCount) { echo "<a href='?page=".$_GET['page']."&p=".($currentPage+1)."'>Next page</a>"; }
	}
	
	function vsb_get_most_common_month() {
		global $wpdb;
		$table_reservations = $this->vsb_get_table('reservations');
		$getmonths = $wpdb->get_results( "SELECT start_date FROM $table_reservations" );
		foreach($getmonths as $row) {
			$str = substr($row->start_date, 5, 2);
			$months[] = $str;
		}
		$frequency = array_count_values($months);
		for ($i = 1; $i < 13; $i++) {
			switch ($i) {
				case 1: echo '<tr class="odd"><td>January</td>'; break;
				case 2: echo '<tr><td>February</td>'; break;
				case 3: echo '<tr class="odd"><td>March</td>'; break;
				case 4: echo '<tr><td>April</td>'; break;
				case 5: echo '<tr class="odd"><td>May</td>'; break;
				case 6: echo '<tr><td>June</td>'; break;
				case 7: echo '<tr class="odd"><td>July</td>'; break;
				case 8: echo '<tr><td>August</td>'; break;
				case 9: echo '<tr class="odd"><td>September</td>'; break;
				case 10: echo '<tr><td>October</td>'; break;
				case 11: echo '<tr class="odd"><td>November</td>'; break;
				case 12: echo '<tr><td>December</td>'; break;
			}
			$freq = $frequency[str_pad($i, 2, '0', STR_PAD_LEFT)];
			
			if (is_null($freq)) $freq = 0;
			
			echo '<td>'.$freq.'</td></tr>';
		}
	}
	
	function vsb_home_admin() {
		?><div class="wrap">
			<h2>VS Bookings</h2>
			<p>Thank you for using VS Bookings!</br>
			To publish the booking form simmply put:</br>
			<i>[bookings]</i></br>
			in any post or page you desire. </br>
			Reservations need to be approved by either an administrator or an author at the <i>Queue</i> page in order to appear at <i>Bookings</i>.</br></br>
			Authors can only access <i>Queue</i> and <i>Bookings</i>.</br></br>
			For any further questions or assistance contact the plugin author at <i>val.i.slavov@gmail.com</i></p>
	
		<?php
	}
	
	function vsb_types_admin() {
		?><div class="wrap">
			<?php echo '<h2>Room configuration</h2>';
			$this->vsb_admin_list_room_types();
			?>
			<form name="product_form" action="<?php bloginfo('url'); ?>/wp-content/plugins/VSBookings/process.php" method="post">
				<?php echo '<h4>Add room type</h4>'; ?>
				<p><?php echo 'Name: '; ?><input type="text" name="name" size="20" />
				<?php echo 'Price per night: '; ?><input type="text" name="price" size="5" />
				<?php echo 'Quantity: '; ?><input type="text" name="quantity" size="5" />
				<input type="hidden" name="action" value="create_room_type" /><input type="submit" name="Submit" value="Add" /></p>
			</form>
		</div>
		<?php 	
	}
	

	
	function vsb_stats_admin() {
		wp_enqueue_style('tables');
		echo "<div id='lists'><table border='0' cellspacing='5'>".
			 "<th>Month</th><th>№ of reservations</th>";
		$this->vsb_get_most_common_month();
		echo "</table></div>";
		
	}
	
	function vsb_queue_admin() {
		echo '<div class="wrap">';
		$this->vsb_admin_list_reservations(0);
		echo '</div>';
	}
	
	function vsb_bookings_admin() {
		echo '<div class="wrap">';
		$this->vsb_admin_list_reservations(1);
		echo '</div>';
	}
}

$vsb_admin_base = new vsb_admin_base();
?>

