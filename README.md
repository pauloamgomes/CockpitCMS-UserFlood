# Cockpit CMS UserFlood Addon

This addon extends Cockpit CMS (Next) core by providing a very simple feature of locking users after a configurable number of failed login attempts.
At this phase the Addon functionality is very basic but functional, an user is set to inactive (blocked) after x number of failed login attempts.

## Installation

Installation can be performed with or without php composer (But keep in mind that after downloaded/extracted the addon must be named UserFlood).

### Without php composer
Download zip and extract to 'your-cockpit-docroot/addons/UserFlood' (e.g. cockpitcms/addons/UserFlood)

### Using php composer
```bash
$ cd your-cockpit-docroot/addons
$ composer create-project pauloamgomes/cockpit-cms-userflood UserFlood
```

## Configuration

If no configuration is provided a default of 10 login failures is used.
The number of failed attempts can be configured as below:

```yaml
flood:
  errors: 5
```

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


