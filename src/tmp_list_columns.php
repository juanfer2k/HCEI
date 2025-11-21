<?php
require 'conn.php';
function showCols($table){
    global $conn;
    $res = $conn->query("SHOW COLUMNS FROM `$table`");
    if(!$res){
        echo "ERROR: " . $conn->error . "\n";
        return;
    }
    echo "--- $table ---\n";
    while($r = $res->fetch_assoc()){
        echo $r['Field'] . "\n";
    }
}
showCols('atenciones');
showCols('atenciones_sig');
?>