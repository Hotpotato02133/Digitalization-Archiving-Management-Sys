<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
dats_require_page_access(basename(__FILE__));
header('Location: index.php', true, 302);
exit;
