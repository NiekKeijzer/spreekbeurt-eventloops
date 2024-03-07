<?php

/**
 * Opens a socket at port 12021 and acts as world's worst microservice to calculate fibonacci numbers
 * Cannot handle more than 1 request at a time
 */

require __DIR__ . '/../generators/fibonacci_deteriorate.php';

/**
 * Handles a single client connection while it's alive. Respond with fibonacci number for each request
 *
 * @param $client
 * @return void
 */
function fib_handler($client)
{
    while (true) {
        yield ['read', $client];
        $data = socket_read($client, 1024);  // blocking

        if ($data === '') {
            break;
        }

        $n = (int)($data);
        $result = fibonacci($n);

        yield ['write', $client];
        socket_write($client, $result . "\n");  // potentially blocking if buffers are full
    }

    echo "Connection closed\n";
}

/**
 * Opens a socket and listens for incoming connections. For each connection, start a new handler
 */
function fib_server()
{
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_bind($sock, '127.0.0.1', 12021);
    socket_listen($sock, 5);

    try {
        while (true) {
            yield ['accept', $sock];
            $client = socket_accept($sock); // blocking

            // Could be moved to a process pool to handle multiple requests at once
            // with the caveat that data needs to be serialized and deserialized
            fib_handler($client);
        }
    } finally {
        socket_close($sock);
    }
}


$tasks = [];

// Mapping sockets to tasks to resume when data is available (tasks are generators
$readWait = [];
$writeWait = [];

/**
 * Round robin scheduler, uses yield statements to pause and resume tasks and communicate the intent of the stop
 * @return void
 */
function run(&$tasks)
{
    while ($tasks) {
        $task = array_shift($tasks);

        [$why, $who] = $task->current();
        $id = spl_object_id($who);

        if ($why === 'read') {
            $readWait[$id] = $task;
        } elseif ($why === 'write') {
            $writeWait[$id] = $task;
        } else {
            throw new \Exception("¯\_(ツ)_/¯");
        }

        $task->next();

        if ($task->valid()) {
            $tasks[] = $task;
        } else {
            echo "Task complete", PHP_EOL;
        }
    }
}

// Must start the server as a task to be able to pause and resume it
$tasks[] = fib_server();
run($tasks);