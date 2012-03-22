<?php 
header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0').' 404 Not Found');
echo "404\n";print_r($arguments);

