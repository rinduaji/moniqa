
<?php 
if ($_GET) {extract($_GET,EXTR_OVERWRITE);}
if ($_POST){extract($_POST,EXTR_OVERWRITE);}

include'koneksi.php';
 


$query = "SELECT user1,user2 FROM `maincc147`.`cc147_main_users_extended` WHERE `user3` = 'Agent Fbcc' and `user7` = '$area' ";
$hasil  =mysqli_query($conn, $query);
 
 
if(mysqli_num_rows($hasil) > 0 ){
  $response = array();
  $response["rows"] = array();
  
    $h['value'] = "";
    $h['name'] = "Login \/ User --";
array_push($response["rows"], $h);
  while($x = mysqli_fetch_array($hasil)){
  	
  	

    $h['value'] = $x["user1"];
    $h['name'] = $x["user2"];
   
    array_push($response["rows"], $h);
  }
  echo json_encode($response);
}else {
  $response["message"]="tidak ada data";
  echo json_encode($response);

}


?>

