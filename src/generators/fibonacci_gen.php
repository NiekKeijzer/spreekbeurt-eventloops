<?php


function fibonacci(int $n): Iterator
{
    [$a, $b] = [0, 1];

    for ($i = 0; $i < $n; $i++) {
        yield $a;
        $c = $a + $b;
        [$a, $b] = [$b, $c];
    }
}
