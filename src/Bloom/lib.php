<?php

namespace Bloom;

/**
 * Greatest common divisor
 *
 * @param  array|int $n* numbers
 * @return integer
 */
function gcd($n)
{
	$n = array_unique(is_array($n) ? $n : func_get_args()); sort($n);
	$a = array_shift($n);
	foreach ($n as $b) {
		for (; $m = $a % $b; $a = $b, $b = $m);
		if (1 == ($a = $b)) break;
	}

	return $a;
}

/**
 * Weighted round-robin
 *
 * Adapted from LVS Weighted round-robin implementation
 * (@see http://kb.linuxvirtualserver.org/wiki/Weighted_Round-Robin_Scheduling)
 *
 * @param  array    $S  weights
 * @param  integer &$i  current position
 * @param  integer &$cw current weight
 * @return integer
 */
function wrr($S, &$i = -1, &$cw = 0)
{
	if (!$n = count($S))
		return null;

	while (true) {
		$i = ($i + 1) % $n;
		if ($i == 0) {
			$cw = $cw - gcd($S);
			if ($cw <= 0) {
				$cw = max($S);
				if ($cw == 0)
					return null;
			}
		}
		if ($S[$i] >= $cw)
			return $i;
	}
}