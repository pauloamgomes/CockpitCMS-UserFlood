<?php

namespace UserFlood\Helper;

class Flood extends \Lime\Helper {

  protected $storage;
  protected $settings;

  public function initialize() {
    $this->storage  = $this->app->storage;
    $this->settings = array_merge([
      'errors'  => 4,    // max. allowed retries before lockout
      'lockout' => 20,   // minutes lockout
      'block'   => 4,    // deactivate user after 4 consecutive lockouts
      'failban' => true, // auto-blacklist malicious users based on ip behavior
    ], $this->app->retrieve('flood', []));
  }

  // add flood entry
  public function add($user) {
    if (!empty($user)) {
      $settings = $this->settings;
      $entry    = [
        'user'      => $user,
        'ipaddress' => $this->getIpAddress(),
        'timestamp' => time()
      ];
      $this->app->trigger('flood.insert', [$user, &$entry, &$settings]);
      $this->storage->insert('flood/log', $entry);
    }
  }

  // count user flood entries
  public function count($user, $options = []) {
    if ($user) {
      $options['user'] = $user;
    }
    return $this->storage->count('flood/log', $options);
  }

  // retrieve flood history
  public function get($user = FALSE, $options = []) {
    if ($user) {
      $options['filter'] = ['user' => $user];
    }
    $entries = $this->storage->find('flood/log', $options)->toArray();

    return $entries;
  }

  // set "lockout" field to account
  public function lock($_user, $lockStartTime = NULL) {
    $_user['lockout'] = $lockStartTime ?? time();
    $this->storage->save('cockpit/accounts', $_user);
    $this->app->trigger('flood.lockout', [$_user['user']]);
  }

  // unset "lockout" field from locked account
  public function unlock($_user) {
    if (!empty($_user['lockout'])) {
      unset($_user['lockout']);
      $this->storage->save('cockpit/accounts', $_user);
    }
  }

  // de-activate account to prevent Brute Force attacks
  public function block($_user) {
    $_user['active'] = FALSE;
    $this->app->trigger('flood.block', [$_user['user']]);
    $this->storage->save('cockpit/accounts', $_user);
  }

  // blacklist user by IP
  public function blacklist($ip) {
    $user = ['ip' => $ip];
    $this->app->trigger('flood.blacklist', [$user]);
    $this->storage->save('flood/blacklist', $user);
  }

  // whitelist user by IP
  public function whitelist($ip) {
    $user = ['ip' => $ip];
    $this->app->trigger('flood.whitelist', [$user]);
    $this->storage->save('flood/whitelist', $user);
  }

  // retrieve user failed login info [ errors, lockouts, blocks ]
  public function info($entry, $settings = []) {
    $settings      = $settings ?? $this->settings;
    $user          = $entry['user'];
    $lockStartTime = $entry['timestamp'] - ($settings['lockout'] * 60);
    $ip            = $entry['ipaddress'] ?? $this->getIpAddress();
    $ip_occurences = intval($this->count(false, [ 'ipaddress' => $entry['ipaddress'] ])) + 1;
    $malicious_ip  = ($same_ip >= $settings['errors']) || $this->isTrustedIp($ip);
    $errors        = intval($this->count($user, [ 'timestamp' => ['$gte' => $lockStartTime ] ])) + 1;
    $lockouts      = intval($errors / $settings['errors']);
    $blocks        = intval(($this->count($user) + 1) / $settings['errors']);
    return compact('errors', 'lockouts', 'blocks', 'malicious_ip', 'ip_occurences', 'ip');
  }

  public function isTrustedIp($ip = NULL) {
    $ip = $ip ?? $this->getIpAddress();
    return $this->storage->count('flood/whitelist', ['ip' => $ip]) || !$this->storage->count('flood/blacklist', ['ip' => $ip]);
  }

  // flush user flood history
  public function reset($user) {
    $this->app->trigger('flood.reset', [$user]);
    $this->storage->remove('flood/log', ['user' => $user]);
  }

  // flush flood history
  public function resetAll($id) {
    return $this->storage->remove('flood/log', []);
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
