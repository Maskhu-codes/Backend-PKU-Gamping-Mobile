<?php
$serverName = "192.168.20.1";
$connectionInfo = array( "Database"=>"HOSPITAL", "UID"=>"admin", "PWD"=>"123");
$conn = sqlsrv_connect( $serverName, $connectionInfo);

if( $conn ) {
     $sql = "DECLARE @UpdatedTable TABLE (NewValue INT);
             SET NOCOUNT ON;
             UPDATE TZ_PARAMETER_NO SET FN_VALUE = FN_VALUE + 1 OUTPUT INSERTED.FN_VALUE INTO @UpdatedTable WHERE FS_KD_PARAMETER = 'NOMR';
             SELECT NewValue AS FN_VALUE FROM @UpdatedTable;";
     $stmt = sqlsrv_query( $conn, $sql );
     if($stmt === false) {
          die(print_r(sqlsrv_errors(), true));
     }
     $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
     print_r($row);
}
?>
