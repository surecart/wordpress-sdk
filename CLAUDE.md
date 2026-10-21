# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

The SureCart Licensing SDK: a PHP library that WordPress plugin and theme authors bundle into their products. It adds license activation against the SureCart API (`https://api.surecart.com`) and serves updates for the bundling product through WordPress's update system. It is not a plugin itself. There is no plugin header, and `.wp-env.json` mounts the repo with `mappings` rather than `plugins` so wp-env doesn't try to activate it.

Consumers load it with `require_once __DIR__ . '/licensing/src/Client.php'` (no Composer autoloader), usually on `init`, then `new \SureCart\Licensing\Client( $name, $public_token, __FILE__ )`. Only `src/` ships; everything else is development tooling.

## Commands

Tests need Docker. Lint does not.

```bash
composer install && yarn install    # first time
yarn start:tests                    # start the tests environment (.wp-env.tests.json, port 8004)
yarn test:php                       # run the whole PHPUnit suite inside it
vendor/bin/phpcs                    # lint (WPCS 3, config in phpcs.xml); vendor/bin/phpcbf to auto-fix

# one file, or tests matching a name
npx wp-env run cli --config=.wp-env.tests.json --env-cwd=wp-content/plugins/wordpress-sdk vendor/bin/phpunit tests/unit/SettingsTest.php
npx wp-env run cli --config=.wp-env.tests.json --env-cwd=wp-content/plugins/wordpress-sdk vendor/bin/phpunit --filter fingerprint

# a different PHP version
WP_ENV_PHP_VERSION=7.4 npx wp-env start --config=.wp-env.tests.json
```

`yarn start` runs a separate development site (`.wp-env.json`, port 8003). Keep tests on the tests config, because the WordPress test suite resets the database it runs against. If the first `yarn start:tests` fails with "Environment not initialized", run it again. `.yarnrc` sets `ignore-engines`, because wp-env 12 pulls in `@wp-playground/cli`, which declares Node ≥24.18; the Docker runtime works on older Node.

CI (`.github/workflows/code-analysis.yml`) only runs phpcs, on PHP 7.4 and 8.0. `composer.json` pins `config.platform.php` to 7.4 so `composer.lock` stays installable there. Keep it when updating dependencies.

## Architecture

`Client` (`src/Client.php`) is the entry point and service locator. Its constructor works out the product's slug, basename, version and type (plugin or theme) from `__FILE__`, then creates `License`, `Activation` and `Updater`. Each accessor (`license()`, `activation()`, `updater()`, `settings()`) `require_once`s its class file if needed and caches one instance per client. `Settings` is only created when the consumer calls `settings()`, usually through `settings()->add_page()`.

- **`Client::send_request()`** is the only HTTP path. It returns the decoded JSON body, or a `WP_Error`, and maps a 404 to the error code `not_found`. Callers branch on `not_found` to tell "removed remotely" from transient failures. That distinction is deliberate: a timeout or 5xx must never clear the stored license.
- **`Settings`** stores everything in one option, `{lowercased client name without spaces}_license_options`. Magic `__get` / `__set` map `$settings->license_key`, `license_id` and `activation_id` to `sc_`-prefixed keys inside it. It also renders the license page and handles the form post.
- **`License::activate()`** validates the key, creates the activation, then checks the release slug against the client slug. If any step throws, it deletes the activation it just created and restores the previous options. `validate()` and `validate_release()` throw `\Exception`; `activate()` catches them and returns a `WP_Error`. Their messages aren't escaped at the throw, so phpcs excludes `ExceptionNotEscaped`.
- **`Activation`** wraps `v1/public/activations`. `get_fingerprint()` normalizes the site URL (https, lowercase host, no leading `www.`, no trailing slash, port and path kept) and is filterable through `surecart_licensing_fingerprint`.
- **`Updater`** hooks `pre_set_site_transient_update_plugins` and `plugins_api` for plugins, or `pre_set_site_transient_update_themes` for themes. It reads `release_json` from `License::get_current_release()` and caches it for 3 hours, except on `update-core.php`. `clear_cache()` runs on activation and deactivation.

Other extension points: the `SURECART_LICENSING_ENDPOINT` constant, and the filters `surecart_licensing_endpoint` and `surecart_licensing_is_local`.

## Constraints

- **The namespace is shared across products.** Every product that bundles the SDK uses `SureCart\Licensing`, and the first copy loaded wins. Changing a public method signature, constructor argument or filter argument breaks older bundled copies running next to yours. Make changes backward compatible (add optional arguments, don't remove or reorder), and pass the client to new filters so callbacks can target one product by `$client->slug`.
- **Keep `src/` on old PHP syntax.** `composer.json` declares PHP ≥5.3, and the code uses `array()`, no type declarations, no `??` and no arrow functions. Keep to that in `src/`; tests can use anything PHPUnit 9 supports.
- **`Settings::add_notice()` may run outside wp-admin**, where `add_settings_error()` doesn't exist, so it returns early there.
- **Bump `Client::$version` on release.** It's sent to the API as `X-SURECART-WP-LICENSING-SDK-VERSION`. Add an entry to `changelog.txt` too, in its `= x.y.z - Month Dth, YYYY =` format with `- New:` / `- Improvement:` / `- Fix:` lines.

## Conventions

- **Translate user-facing strings with `$this->client->__( '...' )`**, not `__( '...', 'surecart' )`. It uses the bundling product's text domain, which `set_textdomain()` can change.
- **Don't put `\` before WordPress functions.** Write `is_wp_error()`, not `\is_wp_error()`. Core classes keep it: `new \WP_Error()`.
- **Comments:** PHP gets full WordPress-style docblocks (class, method, property, `@param` / `@return`) because phpcs requires them. Make the summary line say something the method name doesn't. Inline comments explain the why or a non-obvious edge case, in 1–2 lines.

## Git

- Never commit or push without the user's explicit request, and ask for a review before committing.
- **Never add `Co-Authored-By` lines to commit messages.** Commits use the developer's git account only.
- Internal branches are named `{name}/sur-{ticket}-{short-description}`. Outside contributors open PRs from forks. PRs target `main`.
- Releases: make the version bump and changelog entry (see Constraints) on a `release/vX.Y.Z` branch, open a PR to `main`, then tag `vX.Y.Z` and publish a GitHub release.

## Tests

Tests live in `tests/unit/` and extend `WP_UnitTestCase`, using `set_up()` / `tear_down()` (not `setUp()`). They stub the API with the `pre_http_request` filter rather than making network calls. To change the site URL, use the `site_url` filter, not `update_option( 'siteurl' )`: wp-env defines `WP_SITEURL`, which overrides the option.
