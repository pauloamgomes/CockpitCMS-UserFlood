<?php

namespace UserFlood\Helper;

class Flood extends \Lime\Helper {

  protected $storage;

  public function initialize() {
    $this->storage = $this->app->storage;
  }

  public function count($user) {
    return $this->storage->count('cockpit/flood', ['user' => $user]);
  }

  public function add($user) {
    $settings = $this->app->retrieve('config/flood', ['errors' => 10]);

    $_user = $this->storage->findOne('cockpit/accounts', ['user' => $user]);
    if (!$_user || !$_user['active']) {
      return;
    }

    $entry = [
      'user' => $user,
      'ipaddress' => $this->getIpAddress(),
      'timestamp' => time(),
    ];

    $this->app->trigger('flood.insert', [$user, &$entry]);
    $this->storage->insert('cockpit/flood', $entry);
    $count = $this->count($user);

    if ($count >= $settings['errors']) {
      $_user['active'] = FALSE;
      $this->storage->save('cockpit/accounts', $_user);
      $this->app->trigger('flood.block', [$user]);
    }
  }

  public function get($user = FALSE) {
    $options = [];
    if ($user) {
      $options['filter'] = ['user' => $user];
    }
    $entries = $this->storage->find('cockpit/flood', $options)->toArray();

    return $entries;
  }

  public function reset($user) {
    $this->app->trigger('flood.reset', [$user]);
    $this->storage->remove('cockpit/flood', ['user' => $user]);
  }

  public function resetAll($id) {
    return $this->storage->remove('cockpit/flood', []);
  }

  /**
   * Retrieves user real ip address.
   * Based on http://itman.in/en/how-to-get-client-ip-address-in-php/
   */
  protected function getIpAddress() {
    // check for shared internet/ISP IP
    if (!empty($_SERVER['HTTP_CLIENT_IP']) && $this->validateIp($_SERVER['HTTP_CLIENT_IP'])) {
      return $_SERVER['HTTP_CLIENT_IP'];
    }

    // check for IPs passing through proxies
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      // check if multiple ips exist in var
      if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== FALSE) {
        $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        foreach ($iplist as $ip) {
          if ($this->validateIp($ip)) {
            return $ip;
          }
        }
      }
      else {
        if ($this->validateIp($_SERVER['HTTP_X_FORWARDED_FOR'])) {
          return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
      }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED']) && $this->validateIp($_SERVER['HTTP_X_FORWARDED'])) {
      return $_SERVER['HTTP_X_FORWARDED'];
    }
    if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && $this->validateIp($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
      return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && $this->validateIp($_SERVER['HTTP_FORWARDED_FOR'])) {
      return $_SERVER['HTTP_FORWARDED_FOR'];
    }
    if (!empty($_SERVER['HTTP_FORWARDED']) && $this->validateIp($_SERVER['HTTP_FORWARDED'])) {
      return $_SERVER['HTTP_FORWARDED'];
    }

    return $_SERVER['REMOTE_ADDR'];
  }

  /**
   * Validates an ip address.
   * Based on http://itman.in/en/how-to-get-client-ip-address-in-php/
   */
  protected function validateIp($ip) {
    if (strtolower($ip) === 'unknown') {
      return FALSE;
    }

    // generate ipv4 network address
    $ip = ip2long($ip);

    // if the ip is set and not equivalent to 255.255.255.255
    if ($ip !== FALSE && $ip !== -1) {
      // make sure to get unsigned long representation of ip
      // due to discrepancies between 32 and 64 bit OSes and
      // signed numbers (ints default to signed in PHP)
      $ip = sprintf('%u', $ip);
      // do private network range checking
      if ($ip >= 0 && $ip <= 50331647) return FALSE;
      if ($ip >= 167772160 && $ip <= 184549375) return FALSE;
      if ($ip >= 2130706432 && $ip <= 2147483647) return FALSE;
      if ($ip >= 2851995648 && $ip <= 2852061183) return FALSE;
      if ($ip >= 2886729728 && $ip <= 2887778303) return FALSE;
      if ($ip >= 3221225984 && $ip <= 3221226239) return FALSE;
      if ($ip >= 3232235520 && $ip <= 3232301055) return FALSE;
      if ($ip >= 4294967040) return FALSE;
    }
    return TRUE;
  }

}
