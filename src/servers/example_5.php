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
        Fiber::suspend(['read', $client]);

        $data = socket_read($client, 1024);  // blocking

        if ($data === '') {
            break;
        }

        $n = (int)($data);
        $result = fibonacci($n);

        Fiber::suspend(['write', $client]);
        socket_write($client, $result . "\n");  // potentially blocking if buffers are full
    }

    echo "Connection closed\n";
}

/**
 * Opens a socket and listens for incoming connections. For each connection, start a new handler
 */
function fib_server(SplStack $fibers)
{
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_bind($sock, '127.0.0.1', 12021);
    socket_listen($sock, 5);

    try {
        while (true) {
            Fiber::suspend(['read', $sock]);

            $client = socket_accept($sock); // blocking

            // Start a new fiber (task) to handle the client
            $fiber = new Fiber('fib_handler');
            $fiber->start($client);

            $fibers->push($fiber);
        }
    } finally {
        socket_close($sock);
    }
}

/**
 * Round robin scheduler, uses yield statements to pause and resume tasks and communicate the intent of the stop
 * @return void
 */
function run(SplStack $fibers)
{
    // Mapping sockets to tasks to resume when data is available (tasks are generators
    $readWait = [];
    $writeWait = [];

    while ($fibers->count() || $readWait || $writeWait) {
        echo "doing work...\n";

        // no active tasks, wait for I/O
        while (!$fibers->count()) {
            echo "waiting for I/O...\n";

            if ($readWait || $writeWait) {
                $readable = array_map(static fn($fiber) => $fiber[0], $readWait);
                $writable = array_map(static fn($fiber) => $fiber[0], $writeWait);
                $except = [];

                // Poll the OS for socket activity
                socket_select($readable, $writable, $except, null);

                // Pull the tasks from the waiting area and add them back to the main queue
                // Note, we must do this for both read and write
                foreach ($readable as $socket) {
                    $id = spl_object_id($socket);

                    $fibers->push($readWait[$id][1]);
                    unset($readWait[$id]);
                }

                foreach ($writable as $socket) {
                    $id = spl_object_id($socket);

                    $fibers->push($writeWait[$id][1]);
                    unset($writeWait[$id]);
                }
            }
        }

        $fiber = $fibers->shift();
        if ($fiber->isTerminated()) {
            continue;
        }

        [$why, $who] = $fiber->resume();
        if (null === $why) {
            continue;
        }

        $id = spl_object_id($who);

        if ($why === 'read') {
            $readWait[$id] = [$who, $fiber];
        } elseif ($why === 'write') {
            $writeWait[$id] = [$who, $fiber];
        } else {
            throw new \Exception("¯\_(ツ)_/¯");
        }
    }
}

$fibers = new SplStack();
// Must start the server as a task to be able to pause and resume it
$fiber = new Fiber('fib_server');
$fiber->start($fibers);

$fibers->push($fiber);
run($fibers);