<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$server = getenv('DB_HOSPITAL_HOST');
$username = getenv('DB_HOSPITAL_USERNAME');
$password = getenv('DB_HOSPITAL_PASSWORD');
$database = getenv('DB_HOSPITAL_DATABASE') ?: 'HOSPITAL';

if (!$server || !$username || $password === false) {
    fwrite(STDERR, "Set DB_HOSPITAL_HOST, DB_HOSPITAL_USERNAME, and DB_HOSPITAL_PASSWORD first.\n");
    exit(1);
}

$connection = sqlsrv_connect($server, [
    'Database' => $database,
    'UID' => $username,
    'PWD' => $password,
]);

if (!$connection) {
    fwrite(STDERR, "Unable to connect to the configured SQL Server.\n");
    exit(1);
}

$tables = ['TA_TRS_BOOKING', 'TA_TRS_ANTRIAN', 'TA_LAYANAN', 'TD_PEG', 'TC_MR', 'TA_REGISTRASI', 'TA_GOL_DARAH'];
$sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION";

foreach ($tables as $table) {
    echo $table . " columns:\n";
    $statement = sqlsrv_query($connection, $sql, [$table]);
    if (!$statement) {
        fwrite(STDERR, "Metadata query failed for " . $table . ".\n");
        exit(1);
    }

    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        echo " - " . $row['COLUMN_NAME'] . "\n";
    }
}

sqlsrv_close($connection);
