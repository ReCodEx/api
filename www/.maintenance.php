<?php

header('HTTP/1.1 503 Service Unavailable');
header('Content-Type: application/json; charset=utf-8');
header('Retry-After: 300'); // 5 minutes in seconds

?>
    {"code":503,"success":false,"msg":"Service Unavailable"}
<?php

exit;
