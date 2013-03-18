<?php
session_start();

	global $wpdb;
require_once("../../../wp-config.php");
	$table_types = $wpdb->prefix . plugin_db_prefix . 'room_types';
	$table_rooms = $wpdb->prefix . plugin_db_prefix . 'rooms';
	$table_reservations = $wpdb->prefix . plugin_db_prefix . 'reservations';
			
	switch($_POST['action']) {
		case 'create_room_type': {
			$name=$_POST['name'];
			$price=$_POST['price'];
			$quantity=$_POST['quantity'];
			
			$wpdb->insert($table_types,array('room_name' => $name,'rooms_quantity' => $quantity),array('%s','%d'));
			
			$id = $wpdb->insert_id;
			
			for ($i = 0; $i < $quantity; $i++) {
				$wpdb->insert($table_rooms,array('room_type' => $id,'room_price' => $price),array('%d','%f'));
			}
	
			header('Location: ' . $_SERVER['HTTP_REFERER']);
	
			break;
		}
		
		case 'reserve': {
			$startdate = $_POST['startfield'];
			$enddate = $_POST['endfield'];
			$roomtype = $_POST['room_type'];
			$email = $_POST['email'];
			$phone = $_POST['phone'];
			$name = $_POST['name'];
			
			if (($name == '') || ($phone == '') || ($email == '') || ($enddate == '') || ($startdate == '')) { 
				$_SESSION['error'] = 'Please fill all fields.';
				header('Location: ' . $_SERVER['HTTP_REFERER']);
				break;
			} else {
				if (($startdate == "") || ($enddate =="")) {
					$_SESSION['error'] = 'Select time period.';
					header('Location: ' . $_SERVER['HTTP_REFERER']);
					break;
				}
				
				if (!preg_match('/^[^\W][a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*\@[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*\.[a-zA-Z]{2,4}$/',$email)) {
					$_SESSION['error'] = 'Invalid email address.';
					header('Location: ' . $_SERVER['HTTP_REFERER']);
					break;
				}

				$result = $wpdb->get_results(
							$wpdb->prepare("SELECT B.id FROM " . $table_reservations . " A" .
								" RIGHT JOIN " . $table_rooms . " B" . 
								" ON A.room_id = B.id WHERE B.room_type='$roomtype' AND ((B.id NOT IN (SELECT room_id FROM " . $table_reservations . 
								" WHERE ((start_date BETWEEN '$startdate' AND '$enddate') OR" .
								" (end_date BETWEEN '$startdate' AND '$enddate') OR" . 
								" ('$startdate' BETWEEN  start_date AND end_date) OR" . 
								" ('$enddate' BETWEEN start_date AND start_date)) AND" . 
								" room_id IN (SELECT id from " . $table_rooms .
								" WHERE room_type = '$roomtype'))) OR" . 
								" (start_date IS NULL OR end_date IS NULL)) LIMIT 1",
								$table_reservations, $table_rooms, $roomtype, $table_reservations, $startdate, $enddate, $startdate, $enddate, $startdate, $enddate, $table_rooms, $roomtype),ARRAY_A
						);
				
				if (empty($result)) {
					$_SESSION['error'] = "We don't have spare rooms in your selected period of this type.";
					header('Location: ' . $_SERVER['HTTP_REFERER']);
					break;
				} else {
					$wpdb->insert($table_reservations,array('start_date' => $startdate,'end_date' => $enddate,'email' => $email,'phone' => $phone,'name' => $name, 'room_id'=>$result[0]["id"]),array('%s','%s','%s','%s','%s','%d'));
					
					$_SESSION['error'] = "Successfully booked!";
					header('Location: ' . $_SERVER['HTTP_REFERER']);		
				}
			}
			break;		
		} 
		
		case 'update_reservations': {					
			$approveRange = getSelectRange($_POST['approve']);
			if (!empty($approveRange)) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE $table_reservations SET approved='1' WHERE id IN(%s)",
						$approveRange
					)
				);
			}		
			
			$deleteRange = getSelectRange($_POST['delete']);
			if (!empty($deleteRange)) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM $table_reservations WHERE id IN(%s)",
						$deleteRange
					)
				);
			}								
			
			header('Location: ' . $_SERVER['HTTP_REFERER']);
		}
		
		case 'delete_room_types': {
			$deleteRange = getSelectRange($_POST['delete']);
			if ($deleteRange !== "") {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM $table_types WHERE id IN(%s)",
						$deleteRange
					)
				);
			}
			header('Location: ' . $_SERVER['HTTP_REFERER']);
		}
	}	
	
	function getSelectRange($array) {
		$range = "";
		if (isset($array)) {						
			foreach($array as $id) {
				$range .= $id.",";
			}
			
			$range = substr($range, 0, -1);
		}			
		
		return $range;
	}
?>