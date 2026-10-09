# Threat model: WP Fort Knox

Read by Anthropic's OSS Scanner before it audits this repository. The README is the full
specification; this file says what counts as a vulnerability and how to test.

## What this project does and where untrusted input enters

WP Fort Knox is a single-file WordPress must-use plugin (`wp-fort-knox.php`). It exists to
contain an attacker who holds a stolen wp-admin **administrator** login. While it is active,
no web request (wp-admin, REST API, XML-RPC, admin-ajax, front end) may:

1. Modify files: install, upload, update or delete plugins, themes, core or language packs, or
   use the plugin and theme file editors.
2. Run the automatic (background) updater.
3. Give the `administrator` role on a site to a user who does not already hold it there, by any
   route: user creation, promotion, `set_role`/`add_role`, REST, registration, `default_role`,
   multisite `add_user_to_blog`, or another site's `{prefix}{id}_capabilities` key.
4. Leave a user with Super Admin on multisite (the grant is revoked as it happens).
5. Edit, delete, remove or promote a protected user (`WP_FORT_KNOX_PROTECTED_USERS` or the
   `wp_fort_knox_protected_users` filter), unless the protected user is acting on themselves.

Every blocked role grant, `default_role` change and Super Admin grant is logged with
`error_log()`.

Assume the attacker is an authenticated administrator using a browser or HTTP client; lower
roles and unauthenticated visitors are weaker cases of the same attacker. Untrusted input is
everything such a request can send into core code paths the plugin hooks: user, role, meta,
option and file-modification requests, REST and XML-RPC bodies, the `user_id` parameter on
`user-edit.php` (read by `filter_editable_roles()`), and `REMOTE_ADDR` / `REQUEST_URI`, which
go into the log line.

Trusted, and out of scope as attackers: anyone with shell, WP-CLI, filesystem, `wp-config.php`
or direct database access, and code (plugins, themes) already installed before the attacker
arrived.

## Components that matter most / least

- Most: the capability-meta sanitizer (`filter_add_user_metadata`, `filter_update_user_metadata`,
  `sanitize_capabilities_value`, `is_capabilities_key`), `filter_file_mod_allowed`,
  `filter_capabilities`, `filter_map_meta_cap` with `get_protected_user_ids`, and
  `is_runtime_disabled` with its caching before and after `init`.
- Also in scope: `filter_editable_roles` (its admin exception must never grant anything),
  `filter_default_role`, `block_super_admin_grant`, the admin notices and the log line format
  (escaping, log forging).
- Least: `tests/` (test-only code), `.github/workflows/` (in scope only for issues someone
  without write access to the repository can trigger).

## How to exercise it

- Unit tests (WordPress mocked with Brain Monkey, no database): `cd /src && composer test` runs
  `php -l`, PHPCS and PHPUnit.
- A real site is installed at `/var/www/wordpress` (latest WordPress, MariaDB). Start it with:

  ```sh
  service mariadb start
  php -S 127.0.0.1:8080 -t /var/www/wordpress &
  ```

  Then log in at `http://127.0.0.1:8080/wp-login.php`. The REST API is at
  `http://127.0.0.1:8080/?rest_route=/wp/v2/...` (plain permalinks). Accounts (password = login):
  `admin` (ID 1, administrator, the "client"), `agency_admin` (ID 2, administrator, listed in
  `WP_FORT_KNOX_PROTECTED_USERS`), `editor`, `author`, `subscriber`.
- `wp-content/mu-plugins/wp-fort-knox.php` is a symlink to `/src/wp-fort-knox.php`, so a patch in
  `/src` takes effect on the next request. `WP_DEBUG_LOG` is on, so the plugin's log lines land
  in `wp-content/debug.log`.
- WP-CLI is installed as `wp` (run it from `/var/www/wordpress`). The plugin exempts WP-CLI by
  design, so use it to set up state and check results, never as proof of a bypass. Useful
  toggles: `wp config set WP_FORT_KNOX_STRICT true --raw`, and `wp core multisite-convert` for a
  network (the main site and Network Admin work under `php -S`; sub-site admin URLs need rewrite
  rules it does not provide).
- A bypass is demonstrated by an HTTP request sequence against this site, starting from one of
  the accounts above, that achieves one of the five outcomes in the first section.

## How you rate severity

- **Critical:** an unauthenticated visitor, or a user below administrator, gains the
  administrator role, Super Admin, or PHP code execution or file write through the plugin's
  failure.
- **High:** a logged-in administrator achieves any of the five outcomes above in the default
  configuration. This is the attack the plugin exists to stop, so a working bypass is high even
  though it starts from an administrator account.
- **Medium:** a bypass that needs a non-default but documented configuration (strict mode,
  multisite) or another plugin's ordinary behaviour; forging or suppressing the log line for a
  blocked attempt; locking out or demoting a legitimate administrator from the web.
- **Low:** everything else, such as information leaks or notices that can be made misleading.

## Anything to leave alone

These are documented in the README as design decisions or known limits; do not report them
unless you find a way around the stated limit:

- WP-CLI bypasses everything except the automatic updater block.
- Direct database, filesystem or `wp-config.php` access, and `DISALLOW_FILE_MODS` not being
  defined (the plugin leaves that to the site owner on purpose).
- The `wp_fort_knox_disabled` filter, `WP_FORT_KNOX_DISABLED` constant and the other documented
  filters lifting or changing protection: only already-installed code can use them.
- On multisite, super admins skip `user_has_cap`, so strict mode's `activate_plugins` and
  `switch_themes` do not bind them; the file-modification lock still does.
- On multisite, a new site's owner becomes administrator of that new site
  (`wp_initialize_site` is skipped on purpose).
- What core lets an administrator do that is not one of the five outcomes: editing content and
  options, `unfiltered_html`, activating already-installed plugins outside strict mode, exporting
  data. Report one only if it leads to one of the five outcomes.
- Vulnerabilities in WordPress core or in other plugins that do not involve this plugin.

Patches: change `wp-fort-knox.php` only, keep PHP 7.0 compatibility, pass `composer phpcs`, and
add a PHPUnit test in `tests/` next to the existing ones.
