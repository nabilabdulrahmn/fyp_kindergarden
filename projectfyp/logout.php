<?php
// logout.php
session_start();
session_unset(); // Buang semua variable session
session_destroy(); // Musnahkan session

// Tendang balik ke muka surat log masuk
header("Location: login.php");
exit();
?>