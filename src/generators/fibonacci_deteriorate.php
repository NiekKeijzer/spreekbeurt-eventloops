<?php


/**
 * Inefficient recursive implementation of fibonacci to demonstrate a long running process
 *
 * @param $n
 * @return int n-th fibonacci number
 */
function fibonacci($n) {
    if ($n <= 2) {
        return 1;
    }

    return fibonacci($n - 1) + fibonacci($n - 2);
}