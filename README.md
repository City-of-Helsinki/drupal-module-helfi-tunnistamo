# Drupal Tunnistamo integration

![CI](https://github.com/City-of-Helsinki/drupal-module-helfi-tunnistamo/workflows/CI/badge.svg) [![codecov](https://codecov.io/gh/City-of-Helsinki/drupal-module-helfi-tunnistamo/branch/main/graph/badge.svg?token=LG5QO84DC5)](https://codecov.io/gh/City-of-Helsinki/drupal-module-helfi-tunnistamo)

Provides an integration to [City-of-Helsinki/tunnistamo](https://github.com/City-of-Helsinki/tunnistamo) OpenID Connect (OIDC) service.

## Usage

Tunnistamo client should be enabled automatically, but in case it wasn't, you can
enable `tunnistamo` client from `/admin/config/services/openid-connect`.

Contact the Helsinki Profiili team for client credentials. Make sure only AD authentication method is enabled.

The redirect URL should be `https://example.com/openid-connect/tunnistamo` when using the default configuration.

## Configuration

Populate the following environment variables:

- `TUNNISTAMO_CLIENT_ID`: The client ID
- `TUNNISTAMO_CLIENT_SECRET`: The client secret
- `TUNNISTAMO_ENVIRONMENT_URL`: See [Authorization servers](https://helsinkisolutionoffice.atlassian.net/wiki/spaces/HEL/pages/8283226135/Helfi-tunnistamo+moduuli) for available environments. Please note the authorization server is tied to your credentials

### Hide Tunnistamo login button

Go to Configuration &rarr; OpenID Connect &rarr; Settings and change `OpenID buttons display in user login form` setting to `Hidden`.

## Automatically map AD group to a Drupal role

```php
$config['openid_connect.client.azure-ad']['settings']['ad_roles'] = [
  [
    'ad_role' => '[role from AD]',
    'roles' => ['super_administrator'],
  ],
];
```

## Local development

Add something like this to your `local.settings.php` file:

```php
# public/sites/default/local.settings.php
$config['openid_connect.client.tunnistamo']['settings']['client_id'] = 'your-tunnistamo-client-id';
$config['openid_connect.client.tunnistamo']['settings']['client_secret'] = 'your-client-secret';
// See the Confluence link below for available environments.
$config['openid_connect.client.tunnistamo']['settings']['environment_url'] = 'http://example.com';
```

See https://helsinkisolutionoffice.atlassian.net/wiki/spaces/HEL/pages/8283226135/Helfi-tunnistamo+moduuli for more information.

## Contact

Slack: #helfi-drupal (http://helsinkicity.slack.com/)
