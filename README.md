# Drupal Tunnistamo integration

![CI](https://github.com/City-of-Helsinki/drupal-module-helfi-tunnistamo/workflows/CI/badge.svg)

## Usage

Tunnistamo client should be enabled automatically, but in case it wasn't, you can
enable `tunnistamo` client from `/admin/config/services/openid-connect`.

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

## Local development

TBD: How to set up tunnistamo-authentication on local development environment.
https://helsinkisolutionoffice.atlassian.net/wiki/spaces/HEL/pages/8283226135/Helfi-tunnistamo+moduuli

## Contact

Slack: #helfi-drupal (http://helsinkicity.slack.com/)

Mail: `drupal@hel.fi`
