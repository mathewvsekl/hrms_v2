<?php
$permissions = ['attendance:view', 'attendance:view', 'attendance:edit'];
$unique = array_unique($permissions);
echo json_encode($unique);
