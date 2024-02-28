<?php
$servername = "10.194.41.7";
$username = "root";
$password = "infonusa";
$database = "maincc147";

// Create connection
//$conn = new mysqli($servername, $username, $password, $database);

$conn = @mysqli_connect("$servername", "root", "$password", "maincc147");
	//cek koneksi error atau tidak
	if (!$conn) {
		echo "Error: " . mysqli_connect_error();
		exit();
	}
//mem
?>