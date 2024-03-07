<?php

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($sock, '127.0.0.1', 12021);

while (true) {
    $start = microtime(true);
    socket_write($sock, "30");
    $result = socket_read($sock, 1024);
    $end = microtime(true);
    echo ($end - $start) . "\n";
}