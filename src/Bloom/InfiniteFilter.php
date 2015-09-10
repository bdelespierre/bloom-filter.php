<?php

namespace Bloom;

class InfiniteFilter extends Aggregate
{
	protected $factory;

	public function __construct(callable $factory, array $options = [])
	{
		parent::__construct($options);
		$this->factory = $factory;
	}

	public function add($item)
	{
		try {
			return parent::add($item);
		} catch (\UnderflowException $e) {
			// ignore
		} catch (\OverflowException $e) {
			// ignore
		}

		$factory = $this->factory;
		$this->attach($factory($this));
		return $this->add($item);
	}
}