# Drupal Tunnistamo integration

![CI](https://github.com/City-of-Helsinki/drupal-module-helfi-tunnistamo/workflows/CI/badge.svg)

## Usage

Tunnistamo client should be enabled automatically, but in case it didn't you can
enable `tunnistamo` client from `/admin/config/services/openid-connect`.

## Redirect URL

`https://example.com/openid-connect/tunnistamo`

## Local development

Add these to your local.settings.php:

```
$config['openid_connect.settings.tunnistamo']['settings']['client_id'] = 'your-client-id';
$config['openid_connect.settings.tunnistamo']['settings']['client_secret'] = 'your-client-secret';
$config['openid_connect.settings.tunnistamo']['settings']['is_production'] = FALSE;
```

## Production environemnt

```
$config['openid_connect.settings.tunnistamo']['settings']['client_id'] = getenv('TUNNISTAMO_CLIENT_ID');
$config['openid_connect.settings.tunnistamo']['settings']['client_secret'] = getenv('TUNNISTAMO_CLIENT_SECRET');
$config['openid_connect.settings.tunnistamo']['settings']['is_production'] = getenv('TUNNISTAMO_ENV') === 'production';;
```

## Contact

Slack: #helfi-drupal (http://helsinkicity.slack.com/)

Mail: helfi-drupal-aaaactuootjhcono73gc34rj2u@druid.slack.com
