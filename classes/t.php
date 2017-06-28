<?php
$date = '2011-04-03 19:13:14';
$date = strtotime($date);
$date = is_numeric($date) ? date('c', $date) : $date;
echo "$date\n";
?>

