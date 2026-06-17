<?php
$password = 'admin123';
$hash = '$2y$10$w81o9.K49Dk25o.BOnP3V.Qj4bIt2Xh9vjH/X.n8qH5mYvTf.0.cW';
if (password_verify($password, $hash)) {
    echo "Password Correct\n";
} else {
    echo "Password Incorrect\n";
    echo "Correct hash for 'admin123': " . password_hash($password, PASSWORD_BCRYPT) . "\n";
}
