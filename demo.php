<?php

require __DIR__ . '/src/Bloom/FilterInterface.php';
require __DIR__ . '/src/Bloom/Filter.php';
require __DIR__ . '/src/Bloom/Aggregate.php';
require __DIR__ . '/src/Bloom/InfiniteFilter.php';
require __DIR__ . '/src/Bloom/lib.php';

for ($p = 0.01; $p < 1; $p += 0.01) {
	$f = Bloom\Filter::getOptimumFilter($p, 1000);
	$c = $f->estimateCapacity($p);

	for ($i=0;$f->getFalsePositiveProbability() < $p; $i++)
		$f->add($i);

	printf("%3d%% (%d) : %5d - %5d - %5d : %4d%%\n", $p * 100, count($f->getHashes()), $f->getSize(), $c, $i, (($c - $i) / $i) * 100);
}

