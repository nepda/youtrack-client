<?php

namespace YouTrack;

use SimpleXMLElement;

/**
 * A class describing a youtrack project.
 *
 * @property string name
 * @method string getName
 * @method string setName(string $value)
 * @property string id
 * @method string getId
 * @method string getShortName
 * @method string getAssigneesUsers
 * @method string setId(string $value)
 * @property string lead
 * @method string getLead
 * @method string setLead(string $value)
 * @property string description
 * @method string getDescription
 * @method string setDescription(string $value)
 *
 * @link https://www.jetbrains.com/help/youtrack/incloud/GET-Project.html
 */
class Project extends BaseObject
{
    /**
     * This extra constructor sets subsystems, if they are present in the response
     *
     * @param SimpleXMLElement $xml
     * @param Connection $youtrack
     */
    public function __construct(SimpleXMLElement $xml = null, Connection $youtrack = null)
    {
        parent::__construct($xml, $youtrack);

        if (isset($xml->subsystems)) {

            $this->attributes['subsystems'] = [];
            foreach ($xml->subsystems->sub as $subsystemNode) {
                $system = new Subsystem(null, $youtrack);
                $system->__set('name', (string)$subsystemNode['value']);
                $this->attributes['subsystems'][] = $system;
            }
        }

        if (isset($xml->assigneesLogin)) {
            $this->attributes['assigneesUsers'] = [];
            foreach ($xml->assigneesLogin->sub as $assigneesLogin) {
                $user = new User(null, $youtrack);
                $user->__set('login', (string)$assigneesLogin['value']);
                $this->attributes['assigneesUsers'][] = $user;
            }
        }
    }

    /**
     * Gets the subsystems of the projects
     *
     * @return array|Subsystem[]
     * @throws NotConnectedException
     */
    public function getSubsystems()
    {
        if (is_null($this->attributes['subsystems'])) {
            if (is_null($this->youtrack)) {
                throw new NotConnectedException();
            }
            $this->attributes['subsystems'] = $this->youtrack->getSubsystems($this->getShortName());
        }
        return $this->attributes['subsystems'];
    }

    /**
     * @param string $name
     * @param bool $isDefault
     * @param string $default_assignee_login
     * @return string
     * @throws NotConnectedException
     */
    public function createSubsystem($name, $isDefault, $default_assignee_login)
    {
        if (is_null($this->youtrack)) {
            throw new NotConnectedException();
        }

        $subsystem = new Subsystem(null, $this->youtrack);
        $subsystem->__set('name', $name);
        $subsystem->__set('isDefault', $isDefault);
        $subsystem->__set('defaultAssignee', $default_assignee_login);

        return $this->youtrack->createSubsystem($this->getShortName(), $subsystem);
    }
}
