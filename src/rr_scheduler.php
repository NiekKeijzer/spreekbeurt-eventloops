<?php

function countdown($n)
{
    while ($n > 0) {
        yield $n;
        $n--;
    }
}


function run()
{

    $tasks = [
        countdown(10),
        countdown(5),
        countdown(20),
    ];

    while ($tasks) {
        $task = array_shift($tasks);
        $result = $task->current();

        echo $result, PHP_EOL;

        $task->next();

        if ($task->valid()) {
            $tasks[] = $task;
        } else {
            echo "Task complete", PHP_EOL;
        }
    }
}

run();