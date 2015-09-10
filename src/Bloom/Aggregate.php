<?php

namespace Bloom;

/**
 * Bloom-Filter aggregator
 */
class Aggregate implements \Serializable, FilterInterface
{
	/**
	 * Internal filters
	 * @var array
	 */
	protected $filters = [];

	/**
	 * Current index (for round-robin distribution)
	 * @var integer
	 */
	protected $currentIndex;

	/**
	 * Current weight (for round-robin distribution)
	 * @var integer
	 */
	protected $currentWeight;

	/**
	 * Options
	 * @var array
	 */
	protected $options;

	/**
	 * Constructor
	 *
	 * @param array $options options
	 */
	public function __construct(array $options = [])
	{
		$this->options = $options + [
			'false_probability_threshold' => 1,
		];
	}

	/**
	 * Attach an existing filter to the current aggregator
	 *
	 * @param  FilterInterface $filter the filter
	 * @return Aggregate
	 */
	public function attach(FilterInterface $filter)
	{
		$this->filters[] = $filter;
		return $this;
	}

	/**
	 * Sums all the internal filter's items count
	 *
	 * @return integer
	 */
	public function count()
	{
		$count = 0;
		foreach ($this->filters as $filter)
			$count += count($filter);

		return $count;
	}

	/**
	 * Adds an item to any of the internal bloom-filters. The bloom-filter which
	 * will take the item is determined using a weighted round robin method:
	 * the higher the false-positive probability of the filter, the less likely
	 * it is to recieve a new item. When all internal bloom-filters have reached
	 * their capacity (either being full or their false positive probability
	 * is greater than the configuration threshold), an OverflowException is
	 * thrown
	 *
	 * @param  mixed $item the item
	 * @return FilterInterface the filter that handled the item
	 */
	public function add($item)
	{
		if (empty($this->filters))
			throw new \UnderflowException("No filter attached to current aggregator");

		$weights = $this->getWeights();
		$offset  = array_sum($weights) ? wrr($weights, $this->currentIndex, $this->currentWeight) : null;
		if ($offset === null)
			throw new \OverflowException("All attached filters are virtually full");

		return $this->filters[$offset]->add($item);
	}

	/**
	 * Returns all the internal bloom filters that may have the item. The
	 * result is ordered by their false-positive probability. I the item cannot
	 * be found in any internal bloom-filter, false is returned
	 *
	 * @param  mixed  $item the item
	 * @return SplPriorityQueue
	 */
	public function has($item)
	{
		$positives = new \SplPriorityQueue;
		foreach ($this->filters as $filter)
			if ($filter->has($item))
				$positives->insert($filter, 1 - $filter->getFalsePositiveProbability());

		return count($positives) ? $positives : false;
	}

	/**
	 * Returns true if all internal bloom-filters are full (all bits set to 1)
	 *
	 * @return boolean
	 */
	public function isFull()
	{
		foreach ($this->filters as $filter)
			if (!$filter->isFull())
				return false;

		return true;
	}

	/**
	 * Get the aggregate overall false-positive probability, that is to say the
	 * highest probability amongst the inteal bloom-filters
	 *
	 * @return float
	 */
	public function getFalsePositiveProbability()
	{
		$max = 0;
		foreach ($this->filters as $filter)
			$max = max($max, $filter->getFalsePositiveProbability());

		return $max;
	}

	/**
	 * Gets a decimal representation of all internal bloom-filters weights
	 * (based on their false-positive probability)
	 *
	 * @return array
	 */
	protected function getWeights()
	{
		$weights = [];
		foreach ($this->filters as $filter) {
			$probability = $filter->getFalsePositiveProbability();
			$weight      = 100 - round($probability * 100);

			if ($probability > $this->options['false_probability_threshold'])
				$weight = 0;

			if ($filter->isFull())
				$weight = 0;

			$weights[] = $weight;
		}

		return $weights;
	}

	/**
	 * Serialize
	 *
	 * @return string
	 */
	public function serialize()
	{
		return serialize([$this->currentIndex, $this->currentWeight, $this->options, $this->filters]);
	}

	/**
	 * Unserialize
	 *
	 * @param  string $serialized the serialized form of Aggregate object
	 * @return void
	 */
	public function unserialize($serialized)
	{
		list($this->currentIndex, $this->currentWeight, $this->options, $this->filters) = unserialize($serialized);
	}
}