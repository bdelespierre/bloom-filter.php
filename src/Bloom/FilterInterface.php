<?php

namespace Bloom;

interface FilterInterface extends \Countable
{
	public function add($item);

	public function has($item);

	public function isFull();

	public function getFalsePositiveProbability();
}