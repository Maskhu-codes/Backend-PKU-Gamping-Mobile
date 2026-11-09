<?php
$serverName = "192.168.20.1";
$connectionInfo = array( "Database"=>"HOSPITAL", "UID"=>"admin", "PWD"=>"123");
$conn = sqlsrv_connect( $serverName, $connectionInfo);

if( $conn ) {
     $tables = ['TA_STATUS_KAWIN_DK', 'TA_PENDIDIKAN_DK', 'TA_AGAMA'];
     foreach ($tables as $t) {
         echo "$t Columns:\n";
         $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$t'";
         $stmt = sqlsrv_query( $conn, $sql );
         while( $row = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
              echo " - " . $row['COLUMN_NAME'] . "\n";
         }
     }
}else{
     echo "Connection could not be established.\n";
     die( print_r( sqlsrv_errors(), true));
}
?>
