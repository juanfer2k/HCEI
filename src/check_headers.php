<?php
ob_start();
echo "Checking headers...<br>";
if (headers_sent($file, $line)) {
    echo "Headers already sent in $file on line $line<br>";
} else {
    echo "Headers NOT sent yet.<br>";
}
?>
