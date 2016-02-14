<?php
namespace YouTrack;

/**
 * A class describing a youtrack workitem.
 *
 * @property string id
 * @method string getId
 * @method string setId(string $value)
 * @property int date
 * @method int getDate
 * @method int setDate(int $value)
 * @property int duration
 * @method int getDuration
 * @method int setDuration(int $value)
 * @property string description
 * @method string getDescription
 * @method string setDescription(string $value)
 * @property User author
 * @method User getAuthor
 * @method User setAuthor(int $value)
 *
 * @link https://confluence.jetbrains.com/display/YTD65/Get+Available+Work+Items+of+Issue
 */
class Workitem extends Object
{

    public function __construct(\SimpleXMLElement $xml = NULL, Connection $youtrack = NULL)
    {
        parent::__construct($xml, $youtrack);

        if (isset($xml->date)) {
            $this->attributes['date'] = intval((string)$xml->date / 1000);
        }

        if (isset($xml->author)) {
            $this->attributes['author'] = new User(null, $youtrack);
            $this->attributes['author']->__set('login', (string)$xml->author['login']);
        }
    }

    protected function updateChildrenAttributes(\SimpleXMLElement $xml)
    {
        foreach ($xml->children() as $nodeName => $node) {
            $this->attributes[$nodeName] = (string)$node;
        }
    }
}
