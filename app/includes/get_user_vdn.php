<?php
if ($_GET['jbtn']) {$jbtn=$_GET['jbtn'];}
if ( strlen ($_POST['msg_to']) > 1) {

    $host = "172.5.5.70";
    $database = "maincc147";
    $database_prefix = "cc147_main";
    $user = "root";
    $password = "adminccsby";
	if ($jbtn == "108"){ $jbt="('Agent 108')";}
	else if ( $jbtn == "147"){ $jbt="('Agent Flexi','Agent POTS','Agent Speedy')";}
	else { $jbt ="('Agent Flexi','Agent POTS','Agent Speedy')";}
	//jbt="('Agent 108')";
    mysql_connect( $host, $user, $password);
    mysql_select_db( $database);
	if(is_numeric($_POST['msg_to'])){$cari="a.username LIKE '" . $_POST['msg_to'] . "%'";}else{$cari="a.name LIKE '%" . $_POST['msg_to'] . "%'";}
	if ($jbtn){$wjbtn="AND b.user3 in $jbt";}else{$wjbtn="";}
    $sql = "SELECT a.username, a.name FROM ".$database_prefix."_users a, ".$database_prefix."_users_extended b WHERE $cari AND a.user_level>0 AND a.username != 'admin' AND b.user13='AKTIF' $wjbtn AND a.user_id=b.user_id";
    $rs  = mysql_query( $sql);
?>


    <ul>

    <?php
    while( $data = mysql_fetch_assoc( $rs)) {
    ?>
        <li><b><?php echo stripslashes( $data['username']);?></b><span class="informal"> (<?php echo stripslashes( $data['name']);?>)</span></li>
    <?php
    }
    ?>

    </ul>

<?php
}
else {
    echo "Direct Access to this location is not allowed.";
}
?>