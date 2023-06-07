# Drupal Tunnistamo integration

![CI](https://github.com/City-of-Helsinki/drupal-module-helfi-tunnistamo/workflows/CI/badge.svg) [![codecov](https://codecov.io/gh/City-of-Helsinki/drupal-module-helfi-tunnistamo/branch/main/graph/badge.svg?token=LG5QO84DC5)](https://codecov.io/gh/City-of-Helsinki/drupal-module-helfi-tunnistamo)

Provides an integration to [City-of-Helsinki/tunnistamo](https://github.com/City-of-Helsinki/tunnistamo) OpenID Connect (OIDC) service.

## Usage

Tunnistamo client should be enabled automatically, but in case it wasn't, you can
enable `tunnistamo` client from `/admin/config/services/openid-connect`.

## Redirect URL

`https://example.com/openid-connect/tunnistamo`

## Detect Tunnistamo environment automatically

Leave `environment_url` configuration empty and populate required `helfi_api_base.environment_resolver.settings` configuration.

See [Environment resolver documentation](https://github.com/City-of-Helsinki/drupal-module-helfi-api-base/blob/main/documentation/environment-resolver.md#active-environment) for more information.

## Local development

Add something like this to your `local.settings.php` file:

```php
# public/sites/default/local.settings.php
$config['openid_connect.client.tunnistamo']['settings']['client_id'] = 'your-tunnistamo-client-id';
$config['openid_connect.client.tunnistamo']['settings']['client_secret'] = 'your-client-secret';
// This might be something else, like 'https://tunnistamo.test.hel.ninja'.
$config['openid_connect.client.tunnistamo']['settings']['environment_url'] = 'https://api.hel.fi/sso';
```

See https://helsinkisolutionoffice.atlassian.net/wiki/spaces/HEL/pages/8283226135/Helfi-tunnistamo+moduuli for more information.

## Contact

Slack: #helfi-drupal (http://helsinkicity.slack.com/)

Mail: `drupal@hel.fi`
