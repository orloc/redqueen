<?php

class Door {

    /**
     * Bit value for which door to trigger
     */
    const MAIN                  = 1;
    const CONFERENCE_EXTERIOR   = 2;
    const CONFERENCE_INTERIOR   = 4;
    const QUIET                 = 8;
    const WAREHOUSE_INTERIOR    = 16;
    const WAREHOUSE_EXTERIOR    = 32;

    static $identifiers = array(
        'MAIN' => self::MAIN,
        'CONFERENCE_EXTERIOR' => self::CONFERENCE_EXTERIOR,
        'CONFERENCE_INTERIOR' => self::CONFERENCE_INTERIOR,
        'QUIET' => self::QUIET,
        'WAREHOUSE_INTERIOR' => self::WAREHOUSE_INTERIOR,
        'WAREHOUSE_EXTERIOR' => self::WAREHOUSE_EXTERIOR
    );

    static $ldap_dn = array(
        self::MAIN              => 'cn=main,ou=door,dc=buffalolab,dc=org'
    );

    const DEFAULT_DOOR = self::MAIN;

    public $uniqueMember;

    public $cn;

    public $dn;

    public $description;

    public function __construct(array $data = null)
    {
        $this->_valid = true;
        $this->_dirtyFields = array();
        $this->uniqueMember = array();

        if ($data)
            $this->hydrate($data);
    }

    protected function hydrate(array $data)
    {
        $fields = array('dn', 'cn', 'uniqueMember', 'description');

        foreach ($fields as $field_name)
        {
            $data_key_name = strtolower($field_name);
            if (array_key_exists($data_key_name, $data))
            {
                $this->$field_name = $data[ $data_key_name ];
            }
            else
            {
                $this->$field_name = null;
            }
        }
    }

    static function get($door)
    {
        if (array_key_exists($door, self::$ldap_dn))
        {
            $dn = self::$ldap_dn[ $door ];
        }
        else if (array_key_exists($door, self::$identifiers))
        {
            $dn = self::$ldap_dn[ self::$identifiers[ $door ] ];
        }

        $ldap = Lookup::ldap();

        $door = $ldap->getEntry($dn);

        if ($door == null)
        {
            return null;
        }

        return new Door(Ldap_CompressionIterator::compressEntry($door));
    }

    static function getAll($memberDn = null)
    {
        $ldap = Lookup::ldap();

        $doors = array();
        
        $filter = Zend_Ldap_Filter::equals('objectClass', 'groupOfUniqueNames');
        if ($memberDn != null)
        {
            $filter = $filter->addAnd(Zend_Ldap_Filter::equals('uniqueMember', $memberDn));
        }
        $collection = $ldap->search($filter, sfConfig::get('ldap_doors_dn'), Zend_Ldap::SEARCH_SCOPE_SUB, array(), null, 'Ldap_CompressionIterator');

        foreach($collection as $entry)
        {
            $doors[] = new Door($entry);
        }

        return $doors;
    }

    static function getAllForTag($dn)
    {
        if ($dn instanceof Tag)
        {
            $dn = $dn->getDN();
        }

        return self::getAll($dn);
    }

    public function addTag($dn)
    {
        if ($dn instanceof Tag)
        {
            $dn = $dn->getDN();
        }

        $ldap = Lookup::ldap();

        $node = $ldap->getNode($this->dn);
        $node->appendToAttribute('uniqueMember', $dn);
        $node->update();
    }

    public function deleteTag($dn)
    {
        if ($dn instanceof Tag)
        {
            $dn = $dn->getDN();
        }

        $ldap = Lookup::ldap();

        $node = $ldap->getNode($this->dn);
        $node->removeFromAttribute('uniqueMember', $dn);
        $node->update();
    }

    public function canEnter($dn)
    {
        if ($dn instanceof Person)
        {
            $dn = $dn->getDN();
        }

        if (in_array($dn, $this->uniqueMember))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    static function open($door)
    {
        if (array_key_exists($door, self::$identifiers))
        {
            $value = self::$identifiers[ $door ];
        }
        else if (in_array($door, self::$identifiers))
        {
            $value = $door;
        }
        else
        {
            return false;
        }

        $script = realpath(dirname(__FILE__) . '/../control/trigger');
        if (empty($script))
        {
             sfContext::getInstance()->getLogger()->crit('Failed to find trigger application');
             return false;
        }
        
        $address = '0x378';
        
        #$value = pow(2, $bit);

        $duration = 5;
        $pause = 1; // added 2 seconds to wait for twitter

        $cmd = sprintf("%s %s %d %d %d >/dev/null &", $script, $address, $value, $duration, $pause);

        sfContext::getInstance()->getLogger()->debug("door cmd: $cmd");
        
        @exec($cmd);

        return true;
    }

    public function __toString()
    {
        return $this->cn;
    }
}
