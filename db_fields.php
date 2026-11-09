<?php
$serverName = "192.168.20.1";
$connectionInfo = array( "Database"=>"HOSPITAL", "UID"=>"admin", "PWD"=>"123");
$conn = sqlsrv_connect( $serverName, $connectionInfo);
if( $conn ) {
     $q = sqlsrv_query($conn, "SELECT * FROM TA_GOL_DARAH");
     while($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
         print_r($row);
     }
}
