<?php

namespace PlanetaDelEste\ApiToolbox\Contracts;

use IteratorAggregate;
use Lovata\Toolbox\Classes\Item\ElementItem;

/**
 * Interface CollectionInterface
 *
 * @template T of ElementItem
 */
interface CollectionInterface extends IteratorAggregate
{
    /**
     * @return class-string<T>
     */
    public static function getItemClass(): string;

    /**
     * Get first element item
     * @return T|null
     */
    public function first();

    /**
     * Get element item with ID
     * @param int $iElementID
     *
     * @return T
     */
    public function find(int $iElementID);

    /**
     * Get element item list
     * @return array<T>
     */
    public function all();

    /**
     * Check list is empty
     * @return bool
     */
    public function isEmpty();

    /**
     * Check list is not empty
     * @return bool
     */
    public function isNotEmpty();

    /**
     * Check list is clear
     * @return bool
     */
    public function isClear();

    /**
     * Get element count
     * @return int
     */
    public function count();

    /**
     * Get element ID list
     * @return array
     */
    public function getIDList(): array;

    /**
     * Set new
     * @param array $arElementIDList
     *
     * @return $this
     */
    public function set(array $arElementIDList);

    /**
     * Checking, has collection ID
     * @param int $iElementID
     *
     * @return bool
     */
    public function has(int $iElementID);

    /**
     * Set clear array to element list
     * @return $this
     */
    public function clear();

    /**
     * Apply array_intersect for element array list
     * @param array $arElementIDList
     *
     * @return $this
     */
    public function intersect(array $arElementIDList);

    /**
     * Apply array_merge for element array list
     * @param array $arElementIDList
     *
     * @return $this
     */
    public function merge(array $arElementIDList);

    /**
     * Apply array_diff for element array list
     * @param array $arExcludeIDList
     *
     * @return $this
     */
    public function diff(array $arExcludeIDList = []);

    /**
     * Set skip element count
     * @param int $iCount
     *
     * @return $this
     */
    public function skip(int $iCount);

    /**
     * Take array with element items
     * @param int $iCount
     *
     * @return array
     */
    public function take(int $iCount = 0);

    /**
     * Exclude element id from collection
     * @param int $iElementID
     *
     * @return $this
     */
    public function exclude(?int $iElementID = null);
}
