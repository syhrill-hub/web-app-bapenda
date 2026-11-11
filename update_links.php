<?php
require 'config.php';
$conn->query("UPDATE settings SET value='dokumen.php' WHERE key_name='menu_6_link'");
$conn->query("UPDATE settings SET value='gallery.php' WHERE key_name='menu_7_link'");
echo "Done";
