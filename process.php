﻿<?php
session_start();

	require_once("../../../wp-config.php");
	global $wpdb;
	
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
				header('Location: ' . $_SERVER['HTTP_REFERER']);
				$_SESSION['error'] = 'Please fill all fields.';
				break;
			} else {
				if (($startdate == "") || ($enddate =="")) {
					header('Location: ' . $_SERVER['HTTP_REFERER']);
					$_SESSION['error'] = 'Select time period.';
					break;
				}
				
				if (!preg_match('/^[^\W][a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*\@[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*\.[a-zA-Z]{2,4}$/',$email)) {
					header('Location: ' . $_SERVER['HTTP_REFERER']);
					$_SESSION['error'] = 'Invalid email address.';
					break;
				}
				
				$result = $wpdb->get_results("SELECT B.id FROM " . $table_reservations . " A" .
						" RIGHT JOIN " . $table_rooms . " B" . 
						" ON A.room_id = B.id WHERE B.room_type='$roomtype' AND ((B.id NOT IN (SELECT room_id FROM " . $table_reservations . 
						" WHERE ((start_date BETWEEN '$startdate' AND '$enddate') OR" .
						" (end_date BETWEEN '$startdate' AND '$enddate') OR" . 
						" ('$startdate' BETWEEN  start_date AND end_date) OR" . 
						" ('$enddate' BETWEEN start_date AND start_date)) AND" . 
						" room_id IN (SELECT id from " . $table_rooms .
						" WHERE room_type = '$roomtype'))) OR" . 
						" (start_date IS NULL OR end_date IS NULL)) LIMIT 1",ARRAY_A);

									
				if (empty($result)) {
					header('Location: ' . $_SERVER['HTTP_REFERER']);
					$_SESSION['error'] = "We don't have spare rooms in your selected period of this type.";
					break;
				} else {
					$wpdb->insert($table_reservations,array('start_date' => $startdate,'end_date' => $enddate,'email' => $email,'phone' => $phone,'room_id'=>$result[0]["id"]),array('%s','%s','%s','%s','%d'));
					
					header('Location: ' . $_SERVER['HTTP_REFERER']);
					$_SESSION['error'] = "Successfully booked!";
				}
			}
			break;		
		} 
		
		case 'delete_reservations': {					
			$approveRange = getSelectRange($_POST['approve']);
			if ($approveRange !== "") {
				$wpdb->query("UPDATE $table_reservations SET approved='1' WHERE id IN($approveRange)");
			}		
			
			$deleteRange = getSelectRange($_POST['delete']);
			if ($deleteRange !== "") {
				$wpdb->query("DELETE FROM $table_reservations WHERE id IN($deleteRange)");
			}								
			
			header('Location: ' . $_SERVER['HTTP_REFERER']);
		}
		
		case 'delete_room_types': {
			$deleteRange = getSelectRange($_POST['delete']);
			if ($deleteRange !== "") {
				$wpdb->query("DELETE FROM $table_types WHERE id IN($deleteRange)");
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