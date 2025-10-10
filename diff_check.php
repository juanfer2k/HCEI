<?php
$files = ['index.php','index2.php'];
foreach ($files as $f) {
  if (is_file($f)) {
    echo "<p><strong>$f</strong> — size: ".filesize($f)." — md5: ".md5_file($f)."</p>";
  } else {
    echo "<p>$f no existe</p>";
  }
}
$a = @file('index.php');
$b = @file('index2.php');
if ($a && $b) {
  $max = max(count($a), count($b));
  for ($i=0; $i<$max; $i++) {
    $la = $a[$i] ?? '';
    $lb = $b[$i] ?? '';
    if ($la !== $lb) {
      echo "<pre>Primera diferencia en línea ".($i+1).":\n< ".htmlspecialchars($la)."\n> ".htmlspecialchars($lb)."</pre>";
      break;
    }
  }
  if ($a === $b) echo "<p>Archivos idénticos.</p>";
}
