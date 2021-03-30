<?php
/**
 * Cockpit user flood addon
 *
 * @author  Paulo Gomes
 * @package CockpitCMS-UserFlood
 * @license MIT
 *
 * @source  https://github.com/pauloamgomes/CockpitCMS-UserFlood
 * @see     { README.md } for usage info.
 */

$this->helpers['flood'] = 'UserFlood\\Helper\\Flood';

/**
 * Invalid logins.
 *
 * @see { Cockpit\Controller\Auth::check() }
 * @see { Cockpit\Controller\RestAPI::authUser() }
 */
$this->on('cockpit.authentication.failed', function($data = []) use ($app) {
  if (is_array($data) && !empty($data['user'])) {
    $app->helper('flood')->add($data['user']);
  }
});

/**
 * Succesful logins.
 *
 * @see { Cockpit\Controller\Auth::check() }
 * @see { Cockpit\Controller\RestAPI::authUser() }
 */
$this->on('cockpit.authentication.success', function(&$data = []) use ($app) {
  if (is_array($data) && !empty($data['user'])) {
    $app->helper('flood')->reset($data['user']);
  }
});

/**
 * Accounts updates.
 *
 * @see { Cockpit\Controller\Accounts::save() }
 */
$this->on('cockpit.accounts.save', function(&$user, $update) use ($app) {
  if ($user['active'] && !empty($app->helper('flood')->get($user['user']))) {
    $app->helper('flood')->reset($user['user']);
  }
});

/**
 * Add Flood entry.
 *
 * @see { UserFlood\Helper\Flood::add() }
 */
$this->on('flood.insert', function($user, &$entry, &$settings) {

  // search for saved user
  $_user = $this->storage->findOne('cockpit/accounts', ['user' => $user]);

  // de-activated user or invalid user name
  if (empty($_user) || empty($_user['active'])) {
    return;
  }

  $flood = $this->helper('flood');

  // parse flood entries
  $login = $flood->info($entry, $settings);

  // automatically ban malicious ip
  if ($login['malicious_ip'] && $settings['failban']) {
    $flood->blacklist($login['ip']);
  }

  // lockout user after 4 retries
  if ($login['errors'] >= $settings['errors']) {
    $flood->lock($_user, $entry['timestamp']);
  }

  // deactivate user after 4 consecutive lockouts
  if ($login['blocks'] >= $settings['block']) {
    $flood->block($_user);
  }

  // save debug info
  if ($this['debug']) {
    $entry['debug.info'] = $login;
  }
});

/**
 * Reset Flood history.
 *
 * @see { UserFlood\Helper\Flood::reset() }
 */
$this->on('flood.reset', function($user) {
  if (!empty($_user = $this->storage->findOne('cockpit/accounts', ['user' => $user]))) {
    $this->helper('flood')->unlock($_user);
  }
});

/**
 * Lockout banned IPs
 */
$this->on('admin.init', function() {

  $malicous_ip = !$this->helper('flood')->isTrustedIp();

  $this->bind('/auth/login',           function() { return $this->stop(404); }, $malicous_ip);
  $this->bind('/api/cockpit/authUser', function() { return $this->stop(404); }, $malicous_ip);

}, 100);
