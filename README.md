# wp-api-sites-endpoints

Feature plugin for the Sites endpoints of the WordPress REST API. Requires
WordPress multisite.

The endpoints exposed here are the working ground for
[#40365](https://core.trac.wordpress.org/ticket/40365). The classes in `lib/`
are kept close to what a core patch would look like, so that porting them is a
copy rather than a rewrite.

## Endpoints

| Method | Route | Description |
| --- | --- | --- |
| `GET` | `wp/v2/sites` | List sites |
| `POST` | `wp/v2/sites` | Create a site |
| `GET` | `wp/v2/sites/<id>` | Retrieve a site |
| `PUT`, `PATCH` | `wp/v2/sites/<id>` | Update a site |
| `DELETE` | `wp/v2/sites/<id>` | Delete a site |

## Development

Requires PHP 7.4 or later, Composer, Node, and Docker.

```sh
composer install
npm install
npm run env:start
```

The development site runs as a multisite at http://localhost:8888, with the
plugin already active. Log in at http://localhost:8888/wp-admin with `admin` and
`password`.

### Tests

```sh
npm run test:php
```

This runs PHPUnit inside the wp-env test container. wp-env ships the WordPress
PHPUnit test files and points `WP_TESTS_DIR` at them, so no separate test
library installation is needed.

To run a single test case:

```sh
npm run test:php -- --filter test_get_items
```

### Static analysis and coding standards

```sh
composer phpstan
composer lint
composer format
```

PHPStan runs at level 5, the same level WordPress Core uses.

### Stopping the environment

```sh
npm run env:stop
npm run env:destroy
```
