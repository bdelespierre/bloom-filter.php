<?php

namespace Bloom;

/**
 * Bloom-Filter
 */
class Filter extends \SplFixedArray implements \Serializable, FilterInterface
{
	/**
	 * For debug
	 * @var int
	 */
	public static $intSize = PHP_INT_SIZE;

	/**
	 * Hash algorithms
	 * @var array
	 */
	protected $hashes;

	/**
	 * Bloom-filter size (in bits)
	 * @var integer
	 */
	protected $size;

	/**
	 * Internal counter of added items
	 * @var integer
	 */
	protected $count;

	/**
	 * Constructor
	 *
	 * @param integer $size   bloom-filter size (in bits)
	 * @param array   $hashes hash algorithms to use
	 */
	public function __construct($size, array $hashes)
	{
		if ($size <= 0)
			throw new \UnexpectedValueException("Size cannot be negative or null");

		if (empty($hashes))
			throw new \UnexpectedValueException("You must provide at least one hash algorithm");

		$hashAlgos = hash_algos();
		foreach ($hashes as $hash)
			if (!in_array($hash, $hashAlgos))
				throw new \UnexpectedValueException("Hash algorithm {$hash} is not supported");

		$this->hashes  = $hashes;
		$this->size    = $size;
		$this->count   = 0;

		parent::__construct($size);
	}

	/**
	 * Get an optimized bloom-filter for a target false-positive probability
	 * and capacity that minimizes the chances of collisions
	 *
	 * @param  float   $probability the target false-positive probability
	 * @param  integer $itemsCount  the target bloom-filter capacity
	 * @return Filter
	 */
	public static function getOptimumFilter($probability, $itemsCount)
	{
		$size   = self::getOptimalSize($probability, $itemsCount);
		$nbhash = self::getOptimalNumberOfHashFunctions($size, $itemsCount);
		$algos  = hash_algos();

		shuffle($algos);
		return new static($size, array_slice($algos, 0, $nbhash));
	}

	/**
	 * Calculates the optimal bloom-filter size (in bits) for a target
	 * false-positive probability and capacity that minimizes the chances of
	 * collisions
	 *
	 * @param  float   $probability the target false-positive probability
	 * @param  integer $itemsCount  the target bloom-filter capacity
	 * @return integer
	 */
	public static function getOptimalSize($probability, $itemsCount)
	{
		if ($itemsCount < 0)
			throw new \DomainException("Item count cannot be negative");

		if ($probability >  1 || $probability <  0)
			throw new \DomainException("False positive probability cannot be negative or greater than 1");

		if ($probability == 1 || $probability == 0)
			throw new \LogicException("Unable to calculate size for false positive probability of $probability");

		return -($itemsCount * log($probability)) / pow(log(2), 2);
	}

	/**
	 * Calculates the optimal number of hash function for a given bloom-filter
	 * size (in bits) and capacity (items) that minimizes the chances of
	 * collisions
	 *
	 * @param  integer $size       the target bloom-filter size (in bits)
	 * @param  integer $itemsCount the target bloom-filter capacity
	 * @return integer
	 */
	public static function getOptimalNumberOfHashFunctions($size, $itemsCount)
	{
		if ($size < 0)
			throw new \DomainException("Size cannot be negative");

		if ($itemsCount < 0)
			throw new \DomainException("Item count cannot be negative");

		return max(($size / $itemsCount) * log(2), 1);
	}

	/**
	 * Export the bloom-filter as a fixed-length hexadecimal string
	 *
	 * @return string
	 */
	public function __toString()
	{
		$str  = '';
		$byte = 0;
		foreach ($this as $i => $bit) {
			if ($i && $i % 8 == 0) {
				$str .= str_pad(dechex($byte), 2, '0', STR_PAD_LEFT);
				$byte = 0;
			}

			$bit && $byte += 1 << ($i % 8);
		}

		$str .= str_pad(dechex($byte), 2, '0', STR_PAD_LEFT);

		return $str;
	}

	/**
	 * Returns the number of indistinct items added to the bloom-filter
	 *
	 * @return integer
	 */
	public function count()
	{
		return $this->count;
	}

	/**
	 * Adds an item to the bloom-filter
	 * @param  mixed $item the item to add
	 * @return Filter
	 */
	public function add($item)
	{
		foreach ($this->hash($item) as $bit)
			$this[$bit] = true;

		$this->count++;
		return $this;
	}

	/**
	 * Tells whether the item may be present in the bloom-filter. If false
	 * is returned, the item is certainly not in the set
	 *
	 * @param  mixed $item the item
	 * @return boolean
	 */
	public function has($item)
	{
		foreach ($this->hash($item) as $bit)
			if (!$this[$bit])
				return false;

		return true;
	}

	/**
	 * Hashes the item and yields a succession of bits position (on per hash
	 * algorithm present on the bloom-filter)
	 *
	 * @param  mixed $item the item
	 * @return Generator
	 */
	public function hash($item)
	{
		foreach ($this->hashes as $algo)
			yield abs(crc32(hash($algo, (string)$item, true))) % $this->size;
	}

	/**
	 * Computes the union of 2 bloom-filters
	 *
	 * @param  Filter   $filter the other filter
	 * @return Filter
	 */
	public function union(self $filter)
	{
		if ($filter->hashes != $this->hashes)
			throw new \RuntimeException("Cannot compute union of bloom-filters with different sets of hash functions");

		if ($filter->size != $this->size)
			throw new \RuntimeException("Cannot compute union of bloom-filters with different sizes");

		$union = new self($this->size, $this->hashes);
		for ($i = 0; $i < $this->size; $i++)
			$union[$i] = $this[$i] || $filter[$i];

		return $union;
	}

	/**
	 * Computes the intersection of 2 bloom-filters
	 *
	 * @param  Filter   $filter the other filter
	 * @return Filter
	 */
	public function intersect(self $filter)
	{
		if ($filter->hashes != $this->hashes)
			throw new \RuntimeException("Cannot compute intersection of bloom-filters with different sets of hash functions");

		if ($filter->size != $this->size)
			throw new \RuntimeException("Cannot compute intersection of bloom-filters with different sizes");

		$intersection = new self($this->size, $this->hashes);
		for ($i = 0; $i < $this->size; $i++)
			$intersection[$i] = $this[$i] && $filter[$i];

		return $intersection;
	}

	/**
	 * Returns true if all bits in the current bloom-filter are set to 1
	 *
	 * @return boolean
	 */
	public function isFull()
	{
		foreach ($this as $bit)
			if (!$bit)
				return false;

		return true;
	}

	/**
	 * Get the hashes functions defined on the current bloom-filter
	 *
	 * @return array
	 */
	public function getHashes()
	{
		return $this->hashes;
	}

	/**
	 * Get the false-positive probability (as float)
	 *
	 * @return float
	 */
	public function getFalsePositiveProbability()
	{
		$m = $this->size;
		$n = $this->count;
		$k = count($this->hashes);

		return pow(1 - exp(-$k * $n / $m), $k);
	}

	/**
	 * Estimate the number of distinct items that can be added to the current
	 * bloom-filter before reaching the target false-positive probability
	 *
	 * @param  float $probability the target false-positive probability
	 * @return float
	 */
	public function estimateCapacity($probability)
	{
		if ($probability > 1 || $probability < 0)
			throw new \DomainException("False positive probability cannot be negative or greater than 1");

		if ($probability == 1)
			return INF;

		if ($probability == 0)
			return 0;

		return -($this->size * pow(log(2), 2)) / log($probability);
	}

	/**
	 * Estimate the fill rate of the current bloom-filter based on its estimated
	 * capacity (see Filter::estimateCapacity) for a given false-positive
	 * probability
	 *
	 * @param  float $probability the target false-positive probability
	 * @return float
	 */
	public function estimateFillRate($probability)
	{
		if (!$this->count)
			return 0;

		return $this->count / $this->estimateCapacity($probability);
	}

	/**
	 * Get the hamming-distance between the item and the bloom filter (useful
	 * if you want to maximize the chances of collisions)
	 *
	 * @param  mixed $item the item
	 * @return integer
	 */
	public function distanceWith($item)
	{
		$distance = 0;
		foreach ($this->hash($item) as $bit)
			if (!$this[$bit])
				$distance++;

		return $distance;
	}

	/**
	 * Serialize the current bloom-filter
	 * @return string
	 */
	public function serialize()
	{
		return serialize([$this->size, $this->hashes, $this->count, self::$intSize, (string)$this]);
	}

	/**
	 * Unserialize a serialized bloom-filter
	 *
	 * @todo fix the INT size problem:
	 *
	 * Because of the int signature bit problem issued by the use of crc32 on
	 * item hashes, a filter created under a 32b architecture cannot be used
	 * on a 64b architecture (and vice versa) because it could lead to false
	 * negatives.
	 *
	 * On 32b systems, the crc32 hashing function can return negative numbers (
	 * as stated on http://php.net/manual/en/function.crc32.php). Hence only
	 * the absolute value is used by Filter::hash, limiting the number of
	 * returned bytes to 31 bits instead of 32. This problem is not issued on
	 * 64b architectures.
	 *
	 * @param  string $serialized the serialized bloom-filter
	 * @return Filter
	 */
	public function unserialize($serialized)
	{
		list($this->size, $this->hashes, $this->count, $intSize, $bitfield) = unserialize($serialized);

		if ($intSize != PHP_INT_SIZE)
			throw new \RuntimeException(sprintf(
				"Unable to import bloom-filter from %db architecture: current architecture is %db",
				$intSize * 8, PHP_INT_SIZE * 8
			));

		$this->setSize($this->size);

		foreach (str_split($bitfield, 2) as $i => $byte) {
			$byte = hexdec($byte);

			for ($k = 0; $k < 8; $k++)
				if ($byte & (1 << $k))
					$this[$i * 8 + $k] = true;
		}
	}
}