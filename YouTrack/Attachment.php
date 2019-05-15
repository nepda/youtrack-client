<?php

namespace YouTrack;

/**
 * A class describing a YouTrack attachment.
 *
 * @property string url
 * @method string getUrl
 * @property string id
 * @method string getId
 * @method string setId(string $value)
 * @property string name
 * @method string getName
 * @method string setName(string $value)
 * @property string group
 * @method string getGroup
 * @method string setGroup(string $value)
 * @property string authorLogin
 * @method string getAuthorLogin
 * @method string setAuthorLogin(string $value)
 * @property string created
 * @method \DateTime getCreated
 *
 * @link https://www.jetbrains.com/help/youtrack/incloud/Get-Attachments-of-an-Issue.html
 */
class Attachment extends BaseObject
{
    public function __construct(\SimpleXMLElement $xml = NULL, Connection $youtrack = NULL)
    {
        parent::__construct($xml, $youtrack);
        $this->updateDateAttributes(
            [
                'created',
            ]
        );
    }

    /**
     * Fetches the file content from this attachment
     *
     * If the connection object is not available, it will return false
     *
     * @return string|bool
     */
    public function fetchContent()
    {
        if ($this->youtrack) {
            return $this->youtrack->getAttachmentContent($this->getUrl());
        } else {
            return false;
        }
    }

    /**
     * Sets the url
     *
     * You can pass a array as $url and provide more information about the file.
     *
     * e.g.: `$url = ['filename' => $filename, 'file' => $filepath];`
     *
     * @param string|array $url
     * @return Attachment
     * @see getUrl
     * @see $url
     */
    public function setUrl($url)
    {
        // The API will always return http instead of https
        //
        // Is this a bug from YouTrack?
        // Reported: http://youtrack.jetbrains.com/issue/JT-21987
        //
        // So we fix this, by checking, if the connection is via HTTPS and if so
        // the protocol will be changed from http to https
        if (
            $this->youtrack &&
            $this->youtrack->isHttps() &&
            is_string($url) &&
            0 === strpos($url, 'http') &&
            0 !== strpos($url, 'https')
        ) {
            $url = 'https' . substr($url, strlen('https') - 1);
        }
        $this->attributes['url'] = $url;
        return $this;
    }
}
