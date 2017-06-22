<?php



require_once "Automate.php";


if (isset($_GET['cmd'])) {

	if ($_GET['cmd'] == 'iface.js') {
		header("Content-Type: text/javascript;charset=UTF-8");
		readfile("iface.js");
		return;
	}
	else if ($_GET['cmd'] == 'iface.gz') {
		header("Content-Type: application/octet-stream");
		readfile("iface.gz");
		return;
	}
	else if (empty($_GET['cmd'])) {
		header("Content-Type: text/html;charset=UTF-8");
		readfile("index.html");
		return;
	}
}


$a = new Automate(); $a->requestEntry();

?>
