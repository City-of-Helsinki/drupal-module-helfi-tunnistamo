# Drupal Tunnistamo integration

![CI](https://github.com/City-of-Helsinki/drupal-module-helfi-tunnistamo/workflows/CI/badge.svg)

## Usage

Tunnistamo client should be enabled automatically, but in case it wasn't, you can
enable `tunnistamo` client from `/admin/config/services/openid-connect`.

### Upgrading from 1.x to 2.x

- Run `composer require "drupal/helfi_tunnistamo:^2.0" -W` in your project's root
- Run database updates: `drush updb -y`
- Delete old openid_connect clients: `rm conf/cmi/openid_connect.settings.facebook.yml conf/cmi/openid_connect.settings.generic.yml conf/cmi/openid_connect.settings.github.yml conf/cmi/openid_connect.settings.google.yml conf/cmi/openid_connect.settings.linkedin.yml conf/cmi/openid_connect.settings.tunnistamo.yml`
- Re-create tunnistamo client from `/admin/config/people/openid-connect`

## Redirect URL

`https://example.com/openid-connect/tunnistamo`

## Detect Tunnistamo environment automatically

Leave `environment_url` configuration empty and populate required `helfi_api_base.environment_resolver.settings` configuration.

See [Environment resolver documentation](https://github.com/City-of-Helsinki/drupal-module-helfi-api-base/blob/main/documentation/environment-resolver.md#active-environment) for more information.

## Overriding credentials from environment variables

```
$config['openid_connect.client.tunnistamo']['settings']['client_id'] = getenv('TUNNISTAMO_CLIENT_ID');
$config['openid_connect.client.tunnistamo']['settings']['client_secret'] = getenv('TUNNISTAMO_CLIENT_SECRET');
$config['openid_connect.client.tunnistamo']['settings']['environment_url'] = getenv('TUNNISTAMO_ENVIRONMENT_URL');
```

## Contact

Slack: #helfi-drupal (http://helsinkicity.slack.com/)

Mail: helfi-drupal-aaaactuootjhcono73gc34rj2u@druid.slack.com
