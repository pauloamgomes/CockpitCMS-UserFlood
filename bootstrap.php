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

$this->on('cockpit.authentication.failed', function($user = FALSE) use ($app) {
  $app->helper('flood')->add($user);
});

$this->on('cockpit.authentication.success', function(&$user) use ($app) {
  $app->helper('flood')->reset($user['user']);
});

$this->on('cockpit.accounts.save', function(&$user, $update) use ($app) {
  if ($user['active'] && !empty($app->helper('flood')->get($user['user']))) {
    $app->helper('flood')->reset($user['user']);
  }
});
