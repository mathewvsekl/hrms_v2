<?php
$uri = '/api/tax-slabs?company_id=1';
if (strpos($uri, '/api/tax-slabs') === 0 && !preg_match('/^\/api\/tax-slabs\/(\d+|bulk)/', $uri)) {
    echo "Matched tax-slabs\n";
} else {
    echo "Failed to match\n";
}
