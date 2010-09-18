<?php

class Lookup {
  static function twitter() {
    static $twitter;
    if (!$twitter) {
      require_once(sfConfig::get('sf_root_dir').'/vendor/Arc90/Service/Twitter.php');
      $twitter = new Arc90_Service_Twitter(sfConfig::get('app_twitter_username'), sfConfig::get('app_twitter_password'));
    }
    return $twitter;
  }

  /**
   * @return Zend_Ldap
   */
  static function ldap() {
    static $ldap;
    if (!$ldap) {
      $options = array(
          'host'              => sfConfig::get('app_ldap_host'),
	  'useStartTls'       => sfConfig::get('app_ldap_use_tls'),
          'username'          => sfConfig::get('app_ldap_admin_dn'),
          'password'          => sfConfig::get('app_ldap_admin_password'),
          'bindRequiresDn'    => true,
          'baseDn'            => sfConfig::get('app_ldap_base_dn'),
      );
      $ldap = new Zend_Ldap($options);
      $ldap->bind();
    }
    return $ldap;
  }

  /**
   * inetOrgPerson
   * 
   * @param $username
   * @return Person
   */
  static function person($username) {
    $ldap = self::ldap();
    try {
      $result = $ldap->search('(uid='.$username.')', sfConfig::get('app_ldap_members_dn'), Zend_Ldap::SEARCH_SCOPE_ONE, array(), null, 'Ldap_CompressionIterator');
      if ($result && $data = $result->getFirst()) {
        return new Person($data);
      }
    } catch (Zend_Ldap_Exception $e) {
      return false;
    }
    return false;
  }

  /**
   * simpleSecurityObject, child of inetOrgPerson
   * 
   * @param $username
   * @return Person
   */
  static function tag($rfid) {
    $ldap = self::ldap();
    try {
      $result = $ldap->search('(uid=RFID:'.$rfid.')', sfconfig::get('app_ldap_members_dn'), Zend_Ldap::SEARCH_SCOPE_SUB, array(), null, 'Ldap_CompressionIterator');
      if ($result && $data = $result->getFirst()) {
        return new Tag($data);
      }
    } catch (Zend_Ldap_Exception $e) {
      return false;
    }
    return false;
  }
}
