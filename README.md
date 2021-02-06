# Cockpit CMS UserFlood Addon

This addon extends Cockpit CMS (Next) core by providing a very simple feature of locking users after a configurable number of failed login attempts.

## Installation

### Manual

Download [latest release](https://github.com/pauloamgomes/CockpitCMS-UserFlood) and extract to `COCKPIT_PATH/addons/UserFlood` directory

### Git

```sh
git clone https://github.com/pauloamgomes/CockpitCMS-UserFlood.git ./addons/UserFlood
```

### Cockpit CLI

```sh
php ./cp install/addon --name UserFlood --url https://github.com/pauloamgomes/CockpitCMS-UserFlood.git
```

### Composer

1. Make sure path to cockpit addons is defined in your projects' _composer.json_ file:

  ```json
  {
      "name": "MY_PROJECT",
      "extra": {
          "installer-paths": {
              "cockpit/addons/{$name}": ["type:cockpit-module"]
          }
      }
  }
  ```

2. In your project root run:

  ```sh
  composer require pauloamgomes/cockpitcms-userflood
  ```

---

## Configuration

The number of failed attempts can be configured as below:

```yaml
flood:
  errors:  4    # max. allowed retries before lockout
  lockout: 20   # minutes lockout
  block:   4    # deactivate user after 4 consecutive lockouts
  failban: true # auto-blacklist malicious users based on ip behavior
```

If no configuration is provided a default of 4 login failures is used.
An `user` is set to inactive (blocked) after 16 consecutive failed login attempts (tot. 4 lockouts).
The `failban` option automatically blacklist user's IP related to max number of allowed `errors`.

## Usage

The UserFlood Addon doesn't provide (yet) any user interface, it works on the background during the authentication workflow, using the `cockpit.authentication.failed`, `cockpit.authentication.success` and `cockpit.accounts.save` events.

* A user is set to inactive (blocked) when it fails n login attempts.
* In order to be able to login user needs to be set again to Active via admin interface.

## Extensibility

The UserFlood Addon provides the following events that can be handled by other Addons:

* `flood.insert` - on each failure attempt
* `flood.block` - when max number of failed attemps is reached and user is locked
* `flood.reset` - when flood user entries are removed

## Todo and Improvements

* Block user via ip address
* Define a timeline where the blocking should occur (e.g. block if 10 attemps in last hour)
* Create admin interface with list of all flood events

## Copyright and license

Copyright 2018 pauloamgomes under the MIT license.
