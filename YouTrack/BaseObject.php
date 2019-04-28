<?php
namespace YouTrack;

/**
 * A class describing a youtrack object.
 */
class BaseObject implements \Iterator
{
    /**
     * @var null|Connection
     */
    protected $youtrack = null;

    /**
     * @var array
     */
    protected $attributes = [];

    public function __construct(\SimpleXMLElement $xml = null, Connection $youtrack = null)
    {
        $this->youtrack = $youtrack;
        if ($xml !== null) {
            $this->update($xml);
        }
    }

    /**
     * @param $name
     * @return string
     */
    protected function guessAttributeName($name)
    {
        $name = (string)$name;
        $oName = $name;

        if (!empty($this->attributes[$name])) {
            return $name;
        }

        $name = lcfirst($name);
        if (!empty($this->attributes[$name])) {
            return $name;
        }
        $name = ucfirst($name);
        if (!empty($this->attributes[$name])) {
            return $name;
        }
        $name = str_replace( '_', " ", $oName);
        if (!empty($this->attributes[$name])) {
            return $name;
        }
        return $oName;
    }

    public function __get($name)
    {
        $name = $this->guessAttributeName($name);
        if (!empty($this->attributes["$name"])) {
            return $this->attributes["$name"];
        }
        return null;
    }

    public function __call($name, $args)
    {
        // Magic getter
        if (strlen($name) > 3 && substr($name, 0, 3) === 'get') {
            $name = $this->guessAttributeName(substr($name, 3));
            if (!empty($this->attributes[$name])) {
                return $this->attributes[$name];
            }
        }
        // Magic setter
        if (strlen($name) > 3 && substr($name, 0, 3) === 'set') {
            $name = $this->guessAttributeName(substr($name, 3));
            $this->attributes[$name] = $args[0];
        }
    }

    public function __set($name, $value)
    {
        $name = $this->guessAttributeName($name);
        $this->attributes["$name"] = $value;
    }

    public function __isset($name)
    {
        return isset($this->attributes[$this->guessAttributeName($name)]);
    }

    protected function update($xml)
    {
        $this->updateAttributes($xml);
        $this->updateChildrenAttributes($xml);
    }

    protected function updateAttributes(\SimpleXMLElement $xml)
    {
        foreach ($xml->attributes() as $k => $v) {
            $this->attributes[$k] = (string)$v;
        }
        foreach ($xml->xpath('/*') as $node) {
            foreach ($node->attributes() as $key => $value) {
                $this->attributes["$key"] = (string)$value;
            }
        }
    }

    protected function updateChildrenAttributes(\SimpleXMLElement $xml)
    {
        foreach ($xml->children() as $node) {
            /** @var \SimpleXMLElement $node */
            foreach ($node->attributes() as $key => $value) {
                if ($key == 'name') {
                    $this->__set($value, (string)$node->value);
                } else {
                    $this->__set($key, (string)$value);
                }
            }
        }
    }

    /**
     * Converts all timestamps into \DateTime objects with the local timezone
     *
     * @param string[] $attributeNames
     */
    public function updateDateAttributes(array $attributeNames)
    {
        foreach ($attributeNames as $name) {
            if (isset($this->attributes[$name])) {
                $this->attributes[$name] = $this->convertTimestampToDateTime($this->attributes[$name]);
            }
            if (isset($this->{$name})) {
                $this->{$name} = $this->convertTimestampToDateTime($this->{$name});
            }
        }
    }

    /**
     * Converts a given timestamp (string) into a local \DateTime object
     *
     * Like the parameter documentation of `created` or `updated` the given timestamp represents
     * "the number of milliseconds since January 1, 1970, 00:00:00 GMT represented by this date".
     * @see https://www.jetbrains.com/help/youtrack/incloud/Get-the-List-of-Issues.html
     * This means the given timestamp is a UNIX timestamp
     *
     *
     * @param int|string|\DateTime $rawTimestamp Timestamp in milliseconds
     * @return \DateTime
     */
    public function convertTimestampToDateTime($rawTimestamp)
    {
        if ($rawTimestamp instanceof \DateTime) {
            return $rawTimestamp;
        }
        if (!is_numeric($rawTimestamp)) {
            throw new \InvalidArgumentException('The given timestamp is not numeric.');
        }

        $millis = (string)$rawTimestamp;

        // @see http://confluence.jetbrains.com/display/YTD4/Timestamps+in+REST+API
        // do not divide by 1000 (small integer php settings cause wrong ints)
        $ts = substr($millis, 0, -3);
        $date = new \DateTime('@' . $ts, new \DateTimeZone('UTC'));

        $defaultTimeZone = date_default_timezone_get();
        if (!empty($defaultTimeZone)) {
            $date->setTimezone(new \DateTimeZone($defaultTimeZone));
        }

        return $date;
    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        return current($this->attributes);
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        next($this->attributes);
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return key($this->attributes);
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
        return false !== current($this->attributes);
    }

    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        reset($this->attributes);
    }
}
