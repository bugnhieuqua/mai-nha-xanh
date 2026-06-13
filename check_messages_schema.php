<?php
require 'config/bootstrap.php';
$db = getDB();
$stmt = $db->query('DESCRIBE messages');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
