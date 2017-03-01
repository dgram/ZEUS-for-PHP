<?php

namespace Zeus\Kernel\ProcessManager\Shared;

class FixedCollection implements \Iterator, \ArrayAccess, \Countable
{
    protected $ids = [];

    protected $values = [];

    public function __construct($arraySize)
    {
        $this->ids = new \SplFixedArray($arraySize);
        $this->values = new \SplFixedArray($arraySize);
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        $index = array_search($offset, $this->ids->toArray(), true);

        return $index !== false;
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        $index = array_search($offset, $this->ids->toArray(), true);

        if ($index === false) {
            return null;
        }

        return $this->values[$index];
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        $index = array_search($offset, $this->ids->toArray(), true);

        if ($index !== false) {
            $this->values[$index] = $value;
            return;
        }

        foreach ($this->ids as $id => $index) {
            if ($index === null) {
                $this->ids[$id] = $offset;
                $this->values[$id] = $value;

                return;
            }
        }

        throw new \RuntimeException("Array slots exhausted");
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->ids->getSize();
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        $index = array_search($offset, $this->ids->toArray(), true);

        $this->ids[$index] = null;
        $this->values[$index] = null;
    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        if ($this->values->key() === null) {
            $this->next();
        }

        if ($this->values->key() === null) {
            return null;
        }

        return $this->values->current();
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        do {
            $this->values->next();
            $index = $this->values->key();

            if (is_int($index) && isset($this->ids[$index])) {
                return;
            }
        } while ($index < $this->values->getSize());
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        $index = $this->values->key();
        if (!is_int($index) || is_null($this->ids[$index])) {
            $this->next();

            $index = $this->values->key();
        }

        return !is_null($this->ids[$index]) ? $this->ids[$index] : null;
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        if (count(array_filter($this->ids->toArray())) === 0) {
            return false;
        }

        return $this->values->key() < $this->values->getSize();
    }

    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        $this->values->rewind();
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return count(array_values(array_filter($this->ids->toArray())));
    }
}