# WP Fort Knox - The Paranoid Security Plugin

**Version:** 2.1.0
**Author:** WEFIXIT
**Requires:** WordPress 5.8+, PHP 7.0+
**License:** WTFPL

A zero-tolerance WordPress security plugin that locks down your site tighter than Fort Knox. Because paranoia is just good planning.

---

## 🎉 What's New in Version 2.1.0

Version 2.1.0 is the "we read our own code properly this time" release. We went through 2.0.0 line by line, checked every claim against WordPress core, and found that some of what the plugin (and this README) promised did not actually happen. It's our project and our mess, so here is the plain list of what was broken and what we did about it.

### The 2.0.0 Behaviors That Were Bugs:

- **🪦 The admin-creation hook was dead code.** 2.0.0 advertised a `pre_insert_user_data` filter that "blocks admin user creation." That hook does not exist. Core's filter is called `wp_pre_insert_user_data`, and its `$data` array has no `role` key anyway. The method never ran. Whatever protection you had came from the other layers, not from the thing we bragged about. The method is gone, and the protection now lives in a proper backstop (see below).

- **🤕 Editing an admin could silently demote them.** Hiding `administrator` from the role dropdown had a nasty side effect: on an existing admin's profile page, WordPress had no selected role to show ("No role for this site"), so saving the form posted an empty role and core obediently called `set_role('')`. The admin lost their role without anybody being told. We now keep the Administrator option in the dropdown on the edit screen (`user-edit.php`) of someone who already is an administrator, and only there. Nobody gets elevated by this, because that user already is one.

- **🫥 The `wp_fort_knox_disabled` filter could never fire.** 2.0.0 evaluated it once, in the constructor, at the moment the mu-plugin file was included. That is before regular plugins, before `pluggable.php` and before your theme exist, so no callback you could possibly have written was registered yet. The documented per-user example did nothing. The filter is now evaluated when WordPress actually checks a capability, so it works.

- **🤖 Auto-updates ran under WP-CLI cron.** Under WP-CLI the plugin bailed out completely, and the core automatic updater only checks `wp_is_file_mod_allowed()` and `AUTOMATIC_UPDATER_DISABLED`. So `wp cron event run --due-now` happily installed updates behind your back, which is the opposite of "you handle all updates manually." The updater block is now the one thing that stays active under WP-CLI.

We also fixed the upgrade instructions for v1.0.0. They told you to copy the old file to `wp-fort-knox-v1-backup.php` inside `mu-plugins`. WordPress loads every `.php` file in that directory, and v1 strips capabilities on every request, so the "backup" quietly undid your restore. See the new upgrade section.

### What Else Is New:

- **🧱 A real backstop against administrator elevation.** Any write of the capabilities meta that would add `administrator` to a user who isn't one is stripped and logged, and the user keeps the role(s) they had. That covers `set_role`, `add_role`, direct meta writes, the REST API, registration with a tampered default role and multisite `add_user_to_blog`.
- **🚫 `default_role` can't be set to administrator** (core only checks that the role exists).
- **👑 Multisite:** granting super admin is reverted and logged.
- **🛡️ Protected users (opt-in):** other admins can no longer edit, delete, remove or promote the accounts you list. Great against a hijacked admin taking over your agency account.
- **🔐 Strict mode (opt-in):** also blocks `activate_plugins` and `switch_themes`.
- **📝 Logging is always on** (not only with `WP_DEBUG`), with actor, IP and URI on every line, plus a `wp_fort_knox_blocked` action for audit plugins.
- **📢 The blocked user is told.** Blocked actors used to get a cheerful success message. Now they get an error notice.
- **🔧 Configurable:** four constants, four filters and one action. See [Configuration](#-configuration).
- **🔒 File modifications are locked through core's `file_mod_allowed` filter** instead of defining `DISALLOW_FILE_MODS`, which is what lets the disable filter actually lift it.
- **📦 Tooling:** Composer scripts, PHPCS (WordPress Coding Standards), PHPUnit and CI. Tabs everywhere, as WordPress intended.

### The 2.0.0 Recap (History):

2.0.0 was the release that stopped **permanently nuking capabilities** from the database like some kind of security-drunk cowboy. Capabilities are filtered at runtime, so disabling the plugin returns everything to normal with no restoration ceremony. It added the `WP_FORT_KNOX_DISABLED` constant, admin notices and a class-based structure. That architecture is unchanged and still the right call.

**Version 1.0.0 was a sledgehammer.** It permanently removed capabilities from roles in the database. If you disabled it without running the restoration commands, your admins stayed crippled. That's not security, that's just being mean.

**Version 2.x is a velvet rope.** The capabilities are still in the database, we just filter them out when WordPress checks them.

Same paranoia, better execution.

---

## 🔄 Upgrading

### From 2.0.0 to 2.1.0

Replace the file. That's it. There are no database changes and nothing to restore.

```bash
cd /path/to/wp-content/mu-plugins/
# Keep the old copy OUTSIDE mu-plugins, or give it a non-.php extension
mv wp-fort-knox.php wp-fort-knox-v2.0.0.php.bak
# Then install 2.1.0 using any method from the Installation section
```

What you will notice after upgrading:

- Editing an existing admin's profile no longer demotes them.
- Automatic updates stay off even under `wp cron event run`.
- The plugin does not define `DISALLOW_FILE_MODS` anymore. It locks file modifications through core's `file_mod_allowed` filter instead. Anything that goes through `wp_is_file_mod_allowed()` (which is everything in core) is still locked. Code that reads the constant directly will not see it. If you want that, define it yourself (see [Configuration](#-configuration)).
- The default managed capability list grew (themes, core, languages, file editors). See [What Does This Thing Actually Do?](#-what-does-this-thing-actually-do).
- Blocked admin-role changes now produce an error notice and a log line, always.

### From 1.0.0 to 2.1.0

If you're running v1.0.0, you need to upgrade. Not "should upgrade", **need to upgrade**. Here's why and how.

#### Why Upgrade?

Version 1.0.0 permanently strips capabilities from your WordPress database. If something goes wrong, you're manually fixing it. Version 2.x gives you the same security without the permanent consequences. It's a no-brainer.

#### The Upgrade Process

**Step 1: Understand Your Current State**

If you've been running v1.0.0, your administrator role (and possibly other roles) have already had their plugin management capabilities permanently removed from the database. This is not a drill, they're actually gone.

**Step 2: Get the v1.0.0 File Out of mu-plugins FIRST**

Do this **before** you run any restore command. v1.0.0 strips the capabilities again on every request, including under WP-CLI, so if it is still loaded it will undo your `wp cap add` the moment it runs.

Via SSH or SFTP:

```bash
# Navigate to mu-plugins
cd /path/to/wp-content/mu-plugins/

# Move the old version out of the way. WordPress only loads files ending in .php
# from this directory, so a .bak extension is safe. Do NOT name it
# wp-fort-knox-v1-backup.php: that would still load and still strip your caps.
mv wp-fort-knox.php wp-fort-knox-v1.php.bak
```

(Or move it somewhere outside `mu-plugins` entirely. Or just delete it, you won't need it.)

**Step 3: Restore the Capabilities v1.0.0 Destroyed**

This is the critical step. Version 1.0.0 destroyed these capabilities, and they don't magically come back. You need to restore them manually so that 2.1.0 can properly filter them at runtime.

Via WP-CLI:

```bash
# Option A: Nuclear option - Reset all roles to WordPress defaults
# This removes all custom capabilities added by other plugins.
# NOT recommended
wp role reset --all

# Option B: Recommended option - Restore just the plugin capabilities
# we removed with v1.0.0
# Use this if you have custom role configurations you want to preserve
wp cap add administrator install_plugins
wp cap add administrator update_plugins
wp cap add administrator delete_plugins
```

**Step 4: Install 2.1.0**

Use any method from the [Installation](#-installation---must-use-plugin-only) section.

**Step 5: Verify the Capabilities Are Back**

```bash
# Check that capabilities are back in the database
wp cap list administrator | grep plugin

# You should see:
# install_plugins
# update_plugins
# delete_plugins
# activate_plugins
# edit_plugins
```

(`upload_plugins` is not in that list and never was. It's a meta capability that core maps to `install_plugins`, so it is never stored anywhere.)

**Step 6: Test the New Version**

1. Log in to wp-admin as an administrator and try to install a plugin (should be blocked, with a notice from WP Fort Knox)
2. Install a plugin via WP-CLI (should work perfectly)
3. Temporarily disable via wp-config.php to verify the disable feature works

#### What If I Already Removed v1.0.0 Without Restoring Capabilities?

Then your admin users are currently hobbled and you didn't even know it. Follow **Step 3** above to restore the capabilities, then install 2.1.0. This is exactly why 2.x exists.

#### Post-Upgrade Checklist

- ✅ The v1.0.0 file is gone from `mu-plugins` (no backup ending in `.php` left behind)
- ✅ Plugin capabilities exist in database (verified via `wp cap list`)
- ✅ The 2.1.0 file is in the mu-plugins directory
- ✅ Plugin management is blocked in wp-admin (as intended)
- ✅ Plugin management works via WP-CLI (as intended)
- ✅ Temporary disable feature works (test it)

---

## 🚨 Installation - Must-Use Plugin Only

This plugin is **designed exclusively** for the `wp-content/mu-plugins` folder. It cannot and should not be installed as a regular plugin. It needs WordPress 5.8+ and PHP 7.0+.

### Quick Installation (One Command)

**Method 1: WP-CLI (Recommended)**

From your WordPress installation directory:

```bash
wp eval '
    $url    = "https://raw.githubusercontent.com/ngalatis/wp-fort-knox/v2.1.0/wp-fort-knox.php";
    $mu_dir = WP_CONTENT_DIR . "/mu-plugins";
    if ( ! is_dir( $mu_dir ) && ! mkdir( $mu_dir, 0755, true ) ) {
        fwrite( STDERR, "Could not create " . $mu_dir . "\n" );
        exit( 1 );
    }
    $code = file_get_contents( $url );
    if ( false === $code || "" === trim( $code ) ) {
        fwrite( STDERR, "Download failed or came back empty. Nothing was written.\n" );
        exit( 1 );
    }
    if ( false === file_put_contents( $mu_dir . "/wp-fort-knox.php", $code ) ) {
        fwrite( STDERR, "Could not write the plugin file.\n" );
        exit( 1 );
    }
    echo "WP Fort Knox v2.1.0 installed successfully!\n";
'
```

**Why WP-CLI method is better:**
- Automatically finds the correct wp-content path (works with custom directory structures)
- Creates mu-plugins directory if it doesn't exist
- Works regardless of your current directory
- Verifies you're in a valid WordPress installation
- Refuses to write anything (and exits non-zero) if the download fails or comes back empty, so you can't end up with a blank file pretending to be a security plugin

If PHP's `allow_url_fopen` is off on your server, use Method 2.

**Method 2: Direct Download**

```bash
# Create mu-plugins directory if needed
mkdir -p /path/to/wordpress/wp-content/mu-plugins

# Download the plugin (curl). -f makes curl fail on HTTP errors
# instead of saving the error page as your plugin.
curl -fSL -o /path/to/wordpress/wp-content/mu-plugins/wp-fort-knox.php \
https://raw.githubusercontent.com/ngalatis/wp-fort-knox/v2.1.0/wp-fort-knox.php

# Or with wget (wget leaves an empty file behind on failure, so clean up)
wget -O /path/to/wordpress/wp-content/mu-plugins/wp-fort-knox.php \
https://raw.githubusercontent.com/ngalatis/wp-fort-knox/v2.1.0/wp-fort-knox.php \
|| rm -f /path/to/wordpress/wp-content/mu-plugins/wp-fort-knox.php
```

**Note:** Replace `/path/to/wordpress/` with your actual WordPress installation path.

### Manual Installation

1. Download `wp-fort-knox.php` from the [v2.1.0 release](https://github.com/ngalatis/wp-fort-knox/releases/tag/v2.1.0)
2. Upload to your `wp-content/mu-plugins/` directory via SFTP
3. That's it. No activation needed. It runs automatically.

### Verify Installation

```bash
wp eval 'var_dump(class_exists("WP_Fort_Knox"));'
# Should output: bool(true)
```

That only proves the file loaded. It does **not** prove the restrictions work, because WP-CLI is exempt from them by design. For the real test, see [How do I verify it's actually working?](#how-do-i-verify-its-actually-working).

**Important:** Don't try to install this as a regular plugin. It defeats the whole purpose.

---

## 🔒 What Does This Thing Actually Do?

This plugin takes the nuclear option and **completely strips admin permissions** for anything file-related through the WordPress admin interface, and then keeps people from sneaking around it by minting new admins:

- ❌ **No file modifications** - No editing themes or plugins, no installs, no updates, no deletes, for plugins, themes, core or language packs. This is enforced through core's `file_mod_allowed` filter, which every one of those checks (and the automatic updater, and the file editors) routes through
- ❌ **No plugin or theme management** - The managed capability list is below
- ❌ **No automatic updates** - Blocked on every request, including WP-CLI cron
- ❌ **No admin user creation** - Any attempt to give a non-admin the `administrator` role is stripped, however it arrives (admin screens, REST, direct meta writes, registration, multisite)
- ❌ **No role elevation** - Can't promote existing users to administrator
- ❌ **`default_role` can't be set to administrator** - The write is reverted
- ❌ **No new super admins** (multisite) - Granting is reverted and logged
- ❌ **Administrator role hidden** - Doesn't appear in the user role dropdown (except on the edit screen of someone who already is an administrator, so saving it doesn't demote them)

### The Default Managed Capabilities

These are reported as `false` on every web request (admin, REST, front end), for every user, unless the plugin is disabled:

```
install_plugins   update_plugins   delete_plugins
install_themes    update_themes    delete_themes
update_core       edit_plugins     edit_themes     edit_files
install_languages update_languages
```

There is no `upload_plugins` or `upload_themes` in the list. Those are meta capabilities that core maps to `install_plugins` and `install_themes`, so blocking the real ones covers them.

**Strict mode** (`define('WP_FORT_KNOX_STRICT', true);`) adds `activate_plugins` and `switch_themes`. Clients then can't activate or deactivate plugins or switch themes either. The list is also filterable, see [Configuration](#-configuration).

### But Wait, There's More!

- ✅ **WP-CLI still works perfectly** - All admin operations work through command line (and manual `wp core update` / `wp plugin update` are not the automatic updater, so they are unaffected)
- ✅ **Security logging, always on** - Blocked elevation attempts are logged with the acting user, IP and URI
- ✅ **The blocked user gets told** - A one-time error notice in wp-admin instead of a fake success message
- ✅ **Protected users** (opt-in) - Other admins can't edit, delete or promote accounts you list
- ✅ **Multisite aware** - Works as a network-wide mu-plugin, with super admin handling (see [Technical Details](#-technical-details))
- ✅ **Capability monitoring** - Actively prevents unauthorized permission escalation

---

## 🤔 Why Take the Nuclear Option?

Let's be real here. Clients want administrator accounts because they like feeling in control. Fair enough. But here's the harsh truth: **clients are incredibly careless with their credentials**. They write them on sticky notes, use "admin/password123", and click every phishing link that lands in their inbox.

### The Attack Pattern Everyone Falls For:

1. Attacker steals admin credentials (easier than you'd think)
2. Logs in as the legitimate admin (no flags raised)
3. Creates additional admin accounts quietly
4. Installs backdoor plugins that provide persistent access
5. Comes back later to wreak havoc at their leisure

### How WP Fort Knox Stops This Dead:

Even if an attacker gets valid admin credentials, they **can't do jack shit** through the WordPress admin:

- Can't create additional admin accounts to hide their tracks
- Can't install backdoor plugins to maintain access
- Can't modify files to inject malicious code
- Can't escalate privileges of existing accounts
- Can't take over other admin accounts you've marked as protected

**Assuming there's no shell access with the same credentials** (and there shouldn't be), the attacker is essentially locked out of doing any real damage through the UI. They got WordPress admin login data? Great. They're worthless.

---

## 🛠️ "But What If the Client Wants to Install/Update Something?"

Tough luck. They go through you, the developer.

Look, I don't care what they want to install. Half the time clients install bloated, poorly-coded plugins that create more problems than they solve. The other half they decide to update their whole site on a Friday night and then ruin your weekend because it broke after the updates. This way, we maintain quality control and actually know what's running on the site.

---

## 💻 WP-CLI Commands - The Only Way to Admin

Since everything goes through WP-CLI now, here's your cheat sheet:

### User Management

```bash
# Create a new admin user
wp user create admin admin@example.com --role=administrator --user_pass=SecurePassword123!

# Promote existing user to admin
wp user set-role username administrator

# List all users with their roles
wp user list --fields=ID,user_login,roles

# Demote an admin user
wp user set-role username editor
```

### Plugin Management

```bash
# Install and activate a plugin
wp plugin install plugin-name --activate

# Update a specific plugin
wp plugin update plugin-name

# Update all plugins
wp plugin update --all

# List all installed plugins
wp plugin list

# Deactivate and delete a plugin
wp plugin deactivate plugin-name
wp plugin delete plugin-name
```

### Theme Management

```bash
# Install and activate a theme
wp theme install theme-name --activate

# Update a specific theme
wp theme update theme-name

# Update all themes
wp theme update --all

# List all installed themes
wp theme list
```

### Core Updates

```bash
# Update WordPress core
wp core update

# Update to a specific version
wp core update --version=6.4.1

# Check for updates
wp core check-update
```

### A Note on WP-CLI and Cron

These commands are *manual* updates and work fine. What does **not** work any more is the *automatic* updater: `wp cron event run --due-now` will no longer install updates for you. 2.0.0 let that slip through, 2.1.0 doesn't. See [Configuration](#-configuration) for the opt-out.

---

## ⚙️ Configuration

Everything below is optional. Out of the box the plugin does what the sections above describe.

### Constants (wp-config.php)

Put these in `wp-config.php`, above the "That's all, stop editing!" line.

| Constant | Effect |
| --- | --- |
| `WP_FORT_KNOX_DISABLED` | Hard disable. If set, the plugin registers nothing at all. Checked once, at include time. |
| `WP_FORT_KNOX_STRICT` | Also manage `activate_plugins` and `switch_themes`. |
| `WP_FORT_KNOX_PROTECTED_USERS` | Comma-separated user IDs or logins that other users can't edit, delete, remove or promote. |
| `WP_FORT_KNOX_ALLOW_AUTO_UPDATES` | Opt out of the extra automatic-updater block. See the note below. |

```php
// wp-config.php

// Emergency brake: the plugin does nothing while this exists.
// define( 'WP_FORT_KNOX_DISABLED', true );

// Also block activating plugins and switching themes.
define( 'WP_FORT_KNOX_STRICT', true );

// Accounts that nobody else can touch. IDs and logins can be mixed.
define( 'WP_FORT_KNOX_PROTECTED_USERS', '1, agency_admin' );

// Let WordPress run automatic updates under WP-CLI cron again.
// define( 'WP_FORT_KNOX_ALLOW_AUTO_UPDATES', true );
```

**A note on `WP_FORT_KNOX_ALLOW_AUTO_UPDATES`:** it removes the dedicated `automatic_updater_disabled` block, which is the part that applies under WP-CLI cron. It does not lift the file-modification lock that applies to web requests, and the core updater checks that lock too. In practice that means it matters for WP-CLI cron, and you should not expect it to turn automatic updates back on for the whole site. If you really want automatic updates, you want the plugin disabled.

**Want a lock nobody can lift?** The plugin deliberately does not define `DISALLOW_FILE_MODS`, because a constant can't be lifted by the `wp_fort_knox_disabled` filter and a `file_mod_allowed` filter can. If you want the unliftable version as well, add it yourself:

```php
// wp-config.php
define( 'DISALLOW_FILE_MODS', true );
```

**Multisite:** for a hard lock on who is a super admin, set the `$super_admins` global in `wp-config.php`. That list wins over the database option and can't be changed from the admin:

```php
// wp-config.php
$super_admins = array( 'your_login' );
```

### Filters

**`wp_fort_knox_disabled`** (bool, default `false`): return `true` to switch the plugin off for the current request. Add it from a regular plugin or your theme's `functions.php`. This is evaluated lazily, when WordPress checks a capability, so callbacks that depend on the current user work. Before `init` it is evaluated on every check; once `init` has fired the result is computed once and cached for the rest of the request. All hooks are still registered at include time, so you are protected from the first capability check. Returning `true` lifts every layer, the automatic-updater block included.

Example: disable it for one specific account, in `functions.php` or any regular plugin:

```php
add_filter( 'wp_fort_knox_disabled', function ( $disabled ) {
	// Replace 1 with the ID of the account that gets a free pass.
	if ( 1 === get_current_user_id() ) {
		return true;
	}
	return $disabled;
} );
```

This works because the filter is evaluated per request at check time, not once when the plugin file loads (which is why the same code did nothing in 2.0.0).

> **Warning:** lifting the restrictions for one account makes that account the new target. Whoever steals those credentials gets everything this plugin exists to take away: plugin installs, file editing and admin creation. Do not do this for an account you wouldn't hand the server keys to, and don't leave it in place longer than you need it.

Keep the callback cheap and free of side effects. Before `init` it can run on every capability check.

**`wp_fort_knox_managed_capabilities`** (array): the list of capabilities set to `false`. Add to it or remove from it.

```php
add_filter( 'wp_fort_knox_managed_capabilities', function ( $caps ) {
	// Also lock the Tools > Import screen (importers can install plugins).
	$caps[] = 'import';
	return $caps;
} );
```

Removing a file-related capability from this list does not unlock it. The file-modification lock (`file_mod_allowed`) still says no, because core's checks for installing, updating, deleting and editing go through it. To lift those, use the disable filter or the constant.

**`wp_fort_knox_protected_users`** (array of user IDs or logins): protected users, merged with whatever you put in `WP_FORT_KNOX_PROTECTED_USERS`. Logins are resolved to IDs lazily.

```php
add_filter( 'wp_fort_knox_protected_users', function ( $users ) {
	$users[] = 'agency_admin'; // a login
	$users[] = 42;             // or an ID
	return $users;
} );
```

Protected means: nobody else can `edit_user`, `delete_user`, `remove_user` or `promote_user` on that account. The user can still edit **themselves**. The default list is empty, so nothing changes until you add someone.

**`wp_fort_knox_log`** (bool, default `true`, receives `$event` and `$context`): return `false` to stop the `error_log()` line for an event. The `wp_fort_knox_blocked` action still fires.

```php
// Silence the error_log line, keep the action for your own audit trail.
add_filter( 'wp_fort_knox_log', '__return_false' );
```

### Actions

**`wp_fort_knox_blocked`** (`$event`, `$context`): fires after every logged block. Events are `admin_role_blocked`, `default_role_blocked` and `super_admin_blocked`. Use it to feed an audit plugin, a Slack webhook or whatever you like.

```php
add_action( 'wp_fort_knox_blocked', function ( $event, $context ) {
	// Example: forward to your own logger.
	do_action( 'my_audit_log', 'wp-fort-knox', $event, $context );
}, 10, 2 );
```

### Accessing the Instance

The plugin is a singleton. `WP_Fort_Knox::instance()` returns it, in case you need to get at it from your own code.

---

## 🔌 Disabling the Plugin

Need to disable WP Fort Knox? Easy. Version 2.0.0 made this civilized, and 2.1.0 made the programmatic option actually work.

**Option 1: Hard Disable (Recommended for emergency work)**

Add this to your wp-config.php:

```php
define( 'WP_FORT_KNOX_DISABLED', true );
```

The plugin sees this at include time and registers nothing at all: no capability filtering, no file-mod lock, no role protection, no auto-update block, no logging. All capabilities work normally. Remove the line when you're done. Simple.

**Option 2: Disable Programmatically (per request, per user, per anything)**

Use the `wp_fort_knox_disabled` filter from a regular plugin or your theme's `functions.php`. See the example and the warning in [Configuration](#-configuration).

**Option 3: Permanent Removal**

If you're really done with paranoia (bad choice, but whatever):

```bash
# Via SSH/SFTP, navigate to mu-plugins and remove the file
cd /path/to/wp-content/mu-plugins/
rm wp-fort-knox.php

# Or just rename it
mv wp-fort-knox.php wp-fort-knox.php.disabled
```

That's it. No capability restoration needed, no database cleanup, no prayer circles. The capabilities were never actually removed, just filtered. When the plugin's gone, the filters are gone, and everything works normally.

**Again, this is why 2.x exists.**

---

## 🔍 Security Logging

Logging is **always on**. It is not tied to `WP_DEBUG` any more.

Whenever the plugin blocks something, it writes one line to the PHP error log (`error_log()`) in this format:

```
[WP Fort Knox] <event> | actor=<id>:<login or "none"> | ip=<REMOTE_ADDR, "unknown" or "cli"> | uri=<REQUEST_URI, "unknown" or "cli"> | <key=value ...>
```

A real one, a plugin's AJAX handler calling `set_role( 'administrator' )` on editor #3 while `shop_manager` (#7) was logged in:

```
[WP Fort Knox] admin_role_blocked | actor=7:shop_manager | ip=203.0.113.7 | uri=/wp-admin/admin-ajax.php | user_id=3 meta_key=wp_capabilities assigned_role=editor
```

The `<key=value ...>` tail carries details about the specific event. The logged events and their keys are:

| Event | When | Keys |
| --- | --- | --- |
| `admin_role_blocked` | Something tried to give a non-admin the `administrator` role and it was stripped | `user_id`, `meta_key`, and `assigned_role` (comma-separated) when the plugin had to put the user's previous roles back or fall back to the default role |
| `default_role_blocked` | Something tried to set `default_role` to `administrator` and the old value was kept | `option`, `restored` |
| `super_admin_blocked` | Multisite: super admin was granted and immediately revoked | `user_id`, `revoked` (`true`/`false`) |

Things to know:

- **Where it goes:** wherever your PHP `error_log` points (the `WP_DEBUG_LOG` file, or your server's error log). The timestamp comes from whatever writes that log.
- **The IP is `REMOTE_ADDR`.** Behind a proxy or CDN that's the proxy's address unless your server is configured to restore the real client IP.
- **Actor feedback:** if the block happens in wp-admin for a logged-in user, they get a one-time error notice on their next admin page load, whatever their role (no `manage_options` check) (stored in a 60 second transient called `wp_fort_knox_notice_<user_id>`). The old "success message" for a blocked action was misleading and is gone.
- **Only blocks are logged,** not every capability check. You don't want a log line for every time WordPress asks whether somebody can install plugins.
- **Hook into it:** `wp_fort_knox_log` filters whether the line is written, and the `wp_fort_knox_blocked` action fires for audit plugins. See [Configuration](#-configuration).
- **WP-CLI is exempt,** so `wp user create --role=administrator` is not an event and is not logged by this plugin.

Check your debug log or server error log to monitor suspicious activity. `grep "WP Fort Knox" /path/to/error.log` is a fine start.

---

## ⚠️ Important Notes & Warnings

### Prerequisites - Read This Before Installing

This plugin is **not for casual WordPress users**. It's designed for developers and system administrators who manage WordPress sites professionally. You need:

**Required:**
- ✅ **SSH/SFTP access** to your server - You need to be able to upload files and navigate the filesystem
- ✅ **WP-CLI installed and working** - This is non-negotiable. All admin operations go through WP-CLI
- ✅ **Command line proficiency** - You should be comfortable with terminal commands and bash
- ✅ **WordPress knowledge** - Understanding of roles, capabilities, and how WordPress security works
- ✅ **WordPress 5.8+ and PHP 7.0+**

**Recommended:**
- ✅ **Root/sudo access** - For modifying wp-config.php with proper permissions (though you can work around this with chmod if needed)
- ✅ **Git familiarity** - For updating the plugin and tracking changes
- ✅ **Database backup strategy** - Because you should always have backups, paranoid or not

**If you don't have the above, this plugin is not for you.** Seriously. You'll lock yourself out and blame us. Don't do it.

### General Warnings

- **This plugin is aggressive by design.** It's not for everyone.
- **WP-CLI access is required** for any administrative file operations.
- **Existing admin users keep their roles** but can't perform file operations through wp-admin.
- **WP-CLI is exempt by design,** apart from the automatic updater block. Anyone who can run WP-CLI as the web user or with shell access can do anything. This plugin does not defend against that, and neither does anything else at this layer.
- **Direct database access is exempt too.** The plugin guards WordPress API calls, not raw SQL.
- **Cannot be deactivated from wp-admin** (because it's in mu-plugins, duh).
- To disable: Add constant to wp-config.php or SSH in and delete/rename the file.
- **Strict mode** stops clients from activating plugins and switching themes. That is the point, but tell them before they call you.
- **Lifting restrictions for one account** (via `wp_fort_knox_disabled`) makes that account the new target.

### ✨ Non-Destructive Operation

**Good news: This plugin does NOT permanently destroy capabilities.**

Since 2.0.0 the plugin uses runtime filtering instead of database modification. When the plugin is active, it filters out management capabilities when WordPress checks them. When the plugin is disabled, capabilities work normally again. No restoration needed, no database cleanup, no drama.

**If you're upgrading from v1.0.0:** See the upgrade instructions above. You'll need to restore the capabilities v1.0.0 destroyed before 2.1.0 can work properly.

---

## 🎯 Who Should Use This?

- Agencies managing client sites who are tired of cleaning up malware
- Developers who want to sleep peacefully at night
- Anyone who's dealt with "my site got hacked" calls on a Friday night one too many times
- Sites with clients who treat passwords like public information
- Paranoid sysadmins (the best kind of sysadmins)

---

## 📝 Technical Details

### Requirements and Loading

- WordPress 5.8+, PHP 7.0+, text domain `wp-fort-knox`
- Single-file mu-plugin, class `WP_Fort_Knox`, singleton via `WP_Fort_Knox::instance()`
- There is no `Network: true` header any more. It meant nothing for a mu-plugin, which loads on every site in a network anyway.

### What's Active When

| Context | What runs |
| --- | --- |
| `WP_FORT_KNOX_DISABLED` defined | Nothing. No hooks are registered. |
| WP-CLI (`WP_CLI` defined and true) | Only the automatic-updater block. Everything else is bypassed. |
| Web request (admin, REST, front end) | Everything. Filtering runs on every request, not only in wp-admin. The cost is negligible. |
| Any request where `wp_fort_knox_disabled` returns `true` | Hooks stay registered but every callback returns early or leaves values untouched. That includes the automatic-updater block, under WP-CLI too. |

**Runtime disable semantics:** `wp_fort_knox_disabled` is run through `apply_filters()` inside every hook callback. Before `init` has fired it is evaluated on every call, so callbacks added by your theme's `functions.php` count. Once `init` has fired, the result is computed once and cached for the rest of the request.

### Constants

- `WP_FORT_KNOX_DISABLED` - hard disable, registers nothing
- `WP_FORT_KNOX_STRICT` - adds `activate_plugins` and `switch_themes` to the managed list
- `WP_FORT_KNOX_PROTECTED_USERS` - comma-separated IDs or logins
- `WP_FORT_KNOX_ALLOW_AUTO_UPDATES` - opt out of the `automatic_updater_disabled` block

The plugin does **not** define `DISALLOW_FILE_MODS`.

### Managed Capabilities (Default)

`install_plugins`, `update_plugins`, `delete_plugins`, `install_themes`, `update_themes`, `delete_themes`, `update_core`, `edit_plugins`, `edit_themes`, `edit_files`, `install_languages`, `update_languages`. Strict mode adds `activate_plugins` and `switch_themes`. Filterable via `wp_fort_knox_managed_capabilities`. Each capability that is present in the user's capability set is set to `false`.

### WordPress Hooks Used

*Filters:*
- `file_mod_allowed` (priority `PHP_INT_MAX`, 1 arg) - returns `false` unless runtime-disabled. This is the file-modification lock. Every core check for installing, updating or deleting plugins, themes, core and languages, the file editors, the automatic updater and Site Health goes through `wp_is_file_mod_allowed()`.
- `automatic_updater_disabled` (priority `PHP_INT_MAX`, 1 arg) - returns `true` unless runtime-disabled. Registered even under WP-CLI, which is the one exception to the CLI bypass. Not registered when `WP_FORT_KNOX_ALLOW_AUTO_UPDATES` is set.
- `user_has_cap` (priority 999, 1 arg) - sets the managed capabilities to `false`.
- `editable_roles` (priority `PHP_INT_MAX`, 1 arg) - removes `administrator` from the editable roles, except on `user-edit.php` when the `user_id` request parameter names a user who already has the administrator role.
- `add_user_metadata` and `update_user_metadata` (priority `PHP_INT_MAX`, 5 args) - the capability-meta sanitizer, see below.
- `pre_update_option_default_role` (priority `PHP_INT_MAX`, 3 args) - if the new value is `administrator`, logs and returns the old value (or `subscriber` if the old value was `administrator` too).
- `map_meta_cap` (priority `PHP_INT_MAX`, 4 args) - for protected users, appends `do_not_allow` to `edit_user`, `delete_user`, `remove_user` and `promote_user` unless the actor is the protected user.

*Actions:*
- `granted_super_admin` (priority 10, 1 arg) - multisite: revokes super admin straight away and logs it.
- `admin_notices` (priority 10) - the info and warning notices on the plugins and new-user screens (only for users with `manage_options`), plus the one-time error notice for a blocked actor (shown to that actor whatever their role).

*Filters and action the plugin provides:*
- `wp_fort_knox_disabled`, `wp_fort_knox_managed_capabilities`, `wp_fort_knox_protected_users`, `wp_fort_knox_log`
- `wp_fort_knox_blocked` (action)

### Administrator Role Protection (Layers)

1. **`editable_roles` (cosmetic and UX).** Hides `administrator` from every role dropdown, except on `user-edit.php` when editing an existing administrator (`profile.php`, your own profile, has no role field, so it needs no exception). That exception is what stops the "No role for this site" demotion, and it cannot elevate anyone since that user is already an administrator.
2. **Capability-meta sanitizer (the real backstop).** When the current site's capabilities meta key (`$wpdb->get_blog_prefix() . 'capabilities'`, which follows `switch_to_blog()`; on multisite also any other site's `{base_prefix}{site_id}_capabilities` key) is written with `administrator` set, and the user's existing stored capabilities do not already contain `administrator`, the plugin strips `administrator` from the value. If no role remains (`set_role()` drops the old role before adding the new one), it puts the user's previous roles back; if there were none, it assigns `default_role`, or `subscriber` if `default_role` is itself `administrator`. It then performs the write with the sanitized value, logs `admin_role_blocked` and sets the actor notice. Because it sits on the user meta write, it covers `WP_User::set_role`, `add_role`, direct meta writes, the REST API, registration with a tampered default role and multisite `add_user_to_blog`. Existing administrators are untouched.
3. **`default_role` guard.** `pre_update_option_default_role` refuses `administrator` and keeps the old value (core's sanitizer only checks that the role exists). Logs `default_role_blocked`.
4. **Multisite super admin guard.** `granted_super_admin` triggers `revoke_super_admin()` and logs `super_admin_blocked`.

### Multisite Notes

- **Super admins skip `user_has_cap` entirely** (core returns early for them), so the capability filter does nothing for a super admin. For them, the file-modification lock (`file_mod_allowed`) is the layer that applies, and it covers all the file-related managed capabilities. Strict mode's `activate_plugins` and `switch_themes` do **not** apply to super admins.
- New super admins are revoked on grant. For a lock that can't be changed from the admin at all, set the `$super_admins` global in `wp-config.php`.
- The capability-meta sanitizer covers the capabilities key of the current site (it follows `switch_to_blog()`) and of every other site in the network.
- Creating a new site (Network Admin > Sites > Add New, or a blog signup) still makes its owner the administrator of that new site. That happens during `wp_initialize_site`, which the sanitizer skips: it grants nothing on any existing site, and blocking it would leave the new site without an administrator.
- The plugin loads on every site because it's a mu-plugin, so restrictions are network-wide.

### Disable Methods

- `WP_FORT_KNOX_DISABLED` constant in wp-config.php (hard, nothing registered)
- `wp_fort_knox_disabled` filter (runtime, per request)
- WP-CLI context (automatic bypass, except the automatic-updater block)
- Removing or renaming the file

### Code Quality

WordPress Coding Standards (tabs, Yoda conditions), `composer lint`, `composer phpcs`, `composer phpunit` (Brain Monkey), `composer test` for all of it, and a CI workflow on PHP 7.4 and 8.3. Releases are cut by the Release workflow: push a `vX.Y.Z` tag, or run it manually with the version number and it creates the tag for you. Either way it checks the version against the plugin header, runs the test suite, and builds the release notes from the matching changelog section below.

---

## 📜 WTFPL License

_Do What The Fuck You Want To Public License._

Use it, modify it, distribute it. Just don't blame us if you lock yourself out.

---

## 🤝 Support

Have a problem? Check your WP-CLI access first. Still have a problem? You probably did something wrong.

For serious issues: Open an issue or PR on the repo.

---

## ❓ FAQ (Frequently Asked Questions)

### Q: Will this slow down my WordPress site?

**A:** No. The filtering runs on every request (admin, REST and front end), because capability checks happen everywhere, but it amounts to a few array lookups. We're talking microseconds. Your visitors won't notice a thing. (2.0.0's README said "admin requests only." That was wrong.)

### Q: Can I use this on WordPress Multisite?

**A:** Yes. It's an mu-plugin, so it loads on every site in the network and applies network-wide. Super admins skip WordPress's capability filter entirely, so for them the file-modification lock is what applies (it covers all the file-related capabilities). New super admins are revoked on grant. For a hard lock on who is a super admin, use the `$super_admins` global in `wp-config.php`. Details in [Technical Details](#-technical-details).

### Q: What if I lose SSH access?

**A:** You're in trouble, but not because of this plugin. You'd need to contact your hosting provider to restore SSH access. Once you have it back, you can disable the plugin via wp-config.php or remove the file. This is why we recommend having a backup access method to your server.

### Q: Can I whitelist certain admin users to bypass restrictions?

**A:** Yes, but think hard first. Use the `wp_fort_knox_disabled` filter in a regular plugin or your theme's `functions.php` (example in [Configuration](#-configuration)). **Lifting restrictions for one account makes that account the new target**, and it lifts the whole plugin for that account on that request. The reverse is also available: `WP_FORT_KNOX_PROTECTED_USERS` makes specific accounts untouchable by other admins.

### Q: Does this protect against all WordPress attacks?

**A:** No. This plugin specifically protects against attacks that use compromised admin credentials to install backdoors or create additional admin accounts. You still need:
- Strong passwords and 2FA
- Regular security updates
- Server-level security (firewall, SSH key authentication, etc.)
- File permission hardening
- Database security

This plugin is **one layer** of a comprehensive security strategy, not the whole strategy.

### Q: Can I use this with plugin X (Wordfence, iThemes Security, etc.)?

**A:** Yes. This plugin doesn't conflict with other security plugins. It's complementary. Most security plugins focus on monitoring and blocking malicious requests. WP Fort Knox focuses on limiting what an attacker can do even with valid admin credentials. It is actually tested and working alongside Wordfence in multiple sites.

### Q: Why not just use `DISALLOW_FILE_MODS` alone?

**A:** `DISALLOW_FILE_MODS` is great, but it only blocks file modifications. Attackers can still create admin accounts and elevate user roles, which can be used for reconnaissance, data theft, or setting up future attacks. WP Fort Knox blocks these vectors too. We also lock file modifications through core's `file_mod_allowed` filter rather than defining the constant, so the disable filter can lift it. If you want the unliftable version as well, add `define('DISALLOW_FILE_MODS', true);` to wp-config.php yourself.

### Q: What happens to scheduled plugin updates?

**A:** Automatic updates are blocked, on web requests by the file-modification lock, and under WP-CLI cron by the plugin's own `automatic_updater_disabled` block. (2.0.0 only did the first part, so `wp cron event run --due-now` could still install updates. That was a bug.) You handle all updates manually via WP-CLI. This is actually a good thing: you maintain control over when updates happen and can test them properly. Manual `wp core update` and `wp plugin update` work as before because they aren't the automatic updater. The opt-out is `WP_FORT_KNOX_ALLOW_AUTO_UPDATES`, with the caveats described in [Configuration](#-configuration).

### Q: Can clients still access wp-admin?

**A:** Yes. They can log in and do everything except:
- Manage plugins and themes (install/update/delete), and update core and languages
- Edit files (plugins, themes, etc.)
- Create admin users
- Elevate users to admin
- Edit, delete or promote any account you listed as protected
- (Strict mode only) activate plugins or switch themes

They can still manage content, customize themes via the customizer (if it doesn't require file writes), manage users (non-admin), etc.

### Q: Do I need root access to use this?

**A:** Not strictly required, but **highly recommended**. You need to be able to:
1. Upload files to `wp-content/mu-plugins/`
2. Optionally modify `wp-config.php` (for the constants)
3. Run WP-CLI commands (may or may not need root depending on file permissions)

If your server is set up with proper file ownership (WordPress files owned by your user), you might not need root. But for modifying wp-config.php with secure permissions (600/400), root is helpful.

---

## 🔧 Troubleshooting

### Plugin doesn't seem to be active / restrictions not working

**Check:**
1. File is actually in `wp-content/mu-plugins/wp-fort-knox.php` (not in a subdirectory)
2. File permissions are readable (644)
3. No PHP errors - check your error logs: `tail -f /path/to/error.log`
4. Not accidentally disabled via `WP_FORT_KNOX_DISABLED` constant in wp-config.php
5. Verify with: `wp eval 'var_dump(class_exists("WP_Fort_Knox"));'` - should return `bool(true)` (this only proves the file loaded)

### I can still install plugins in wp-admin

**Possible causes:**
1. You're testing with WP-CLI (it bypasses the plugin by design)
2. The plugin file isn't loaded (see above)
3. `WP_FORT_KNOX_DISABLED` is defined in wp-config.php
4. A `wp_fort_knox_disabled` callback in a plugin or theme is returning `true` for your account
5. Another plugin is interfering (unlikely) - disable other security plugins temporarily to test

### Another admin can't edit my account / my own edits are refused

You (or someone) put the account in `WP_FORT_KNOX_PROTECTED_USERS` or the `wp_fort_knox_protected_users` filter. Protected accounts can only be edited by themselves, or via WP-CLI. Remove the entry to undo it.

### I can't activate plugins or switch themes

`WP_FORT_KNOX_STRICT` is on. That's what strict mode is for. Remove the constant, or use WP-CLI (`wp plugin activate`, `wp theme activate`).

### An admin account was demoted when I saved their profile

That's the 2.0.0 bug. Upgrade to 2.1.0. If it already happened, restore the role with WP-CLI: `wp user set-role username administrator`.

### WP-CLI commands fail with permission errors

**Solution:**
```bash
# Option 1: Run with --allow-root if you're actually root
wp plugin install plugin-name --allow-root

# Option 2: Fix file ownership
sudo chown -R www-data:www-data /path/to/wordpress
# Or whatever user your web server runs as (nginx, apache, etc.)

# Option 3: Run as the web server user
sudo -u www-data wp plugin install plugin-name
```

### Locked myself out / Can't disable the plugin

**Solution:**
If you can access SSH:
```bash
# Option 1: Remove the file
cd /path/to/wp-content/mu-plugins/
mv wp-fort-knox.php wp-fort-knox.php.disabled

# Option 2: Add disable constant to wp-config.php
echo "define('WP_FORT_KNOX_DISABLED', true);" >> wp-config.php
```

If you can't access SSH, contact your hosting provider. They can disable it for you.

### After upgrading from v1.0.0, capabilities are still broken

**Solution:**
First make sure no v1.0.0 file is still loading from `mu-plugins`. Any leftover file ending in `.php` there (a backup named `wp-fort-knox-v1-backup.php`, say) is loaded by WordPress, and v1.0.0 strips the capabilities again on every request, including under WP-CLI. Move it out or rename it to `.php.bak`. Then run:
```bash
# Not Recommended (see migration instructions)
wp role reset --all
# Or manually:
wp cap add administrator install_plugins update_plugins delete_plugins
```

See the full upgrade guide above.

### Plugin conflicts with my theme's admin features

**Not a bug, it's a feature.** If your theme's admin features require file modifications or plugin installations, they'll be blocked. You have two options:

1. **Disable temporarily** when you need those features:
   ```php
   define( 'WP_FORT_KNOX_DISABLED', true ); // in wp-config.php
   ```

2. **Programmatic control** - Use the `wp_fort_knox_disabled` filter from a regular plugin or your theme's `functions.php`. See the example (and the warning about what it does to that account) in [Configuration](#-configuration).

### How do I verify it's actually working?

WP-CLI is exempt from the restrictions by design, so **the real test is in the browser**.

**Browser test (the one that counts):**

1. Log in to wp-admin as an administrator.
2. Try to install a plugin (`/wp-admin/plugin-install.php`). It should be refused or hidden, and the plugins screen shows a WP Fort Knox notice.
3. Try to edit a theme or plugin file (`/wp-admin/theme-editor.php`). It should be refused.
4. Open Users, Add New. The Administrator role should not be in the dropdown and you should see a warning notice.
5. Open an existing administrator's profile and click Update. They should still be an administrator afterwards.
6. Check the error log for `[WP Fort Knox]` lines after any blocked attempt.

**Check the capability with WP-CLI (and understand the result):**

```bash
wp eval --user=<admin-login> 'var_dump(current_user_can("install_plugins"));'
```

Under WP-CLI this returns `bool(true)`, **even though the plugin is working**. That is expected: the CLI is exempt by design, so the capability filter never runs there. Without `--user` there is no user at all, so the check is meaningless. If you want to know whether a browser user is blocked, use the browser.

**Install via WP-CLI should still work perfectly:**

```bash
wp plugin install wordpress-seo
# Should succeed
```

---

## 📋 Changelog

### Version 2.1.0 (Current)
**Bug Fix and Hardening Release**

- **Fixed:** Removed the `pre_insert_user_data` method. That hook does not exist in core (the real one is `wp_pre_insert_user_data`, and its data has no `role` key), so it never ran. Protection comes from the capability-meta sanitizer below
- **Fixed:** Editing an existing administrator's profile no longer silently demotes them. `editable_roles` keeps the Administrator option on `user-edit.php` for a user who already is an administrator, so the form no longer posts an empty role
- **Fixed:** `wp_fort_knox_disabled` is now evaluated lazily at capability-check time instead of once in the constructor, so callbacks in plugins and themes can actually run. Before `init` it is evaluated on every call; after `init` the result is cached for the request
- **Fixed:** Automatic updates no longer run under WP-CLI cron (`wp cron event run --due-now`). `automatic_updater_disabled` is registered even under WP-CLI. Opt out with `WP_FORT_KNOX_ALLOW_AUTO_UPDATES`
- **Fixed:** v1.0.0 upgrade instructions no longer put a loadable `.php` backup in `mu-plugins` (it re-stripped capabilities on every request). The v1 file must be moved out before running the restore commands
- **Fixed:** Removed `upload_plugins` from the verification list (a meta capability, never stored) and from the managed list
- **Fixed:** Blocked actors no longer get a success message. They get an error notice
- **Fixed:** Removed the meaningless `Network: true` header
- **Fixed:** Role revert restored only the first old role; replaced by the capability-meta sanitizer, which keeps all of the user's previous roles
- **Changed:** File modifications are locked through core's `file_mod_allowed` filter instead of defining `DISALLOW_FILE_MODS`. Users who want the unliftable lock can define the constant in wp-config.php themselves
- **Changed:** Logging is always on (not only with `WP_DEBUG`) and every line carries actor, IP and URI
- **Changed:** Notices reworded to say what is blocked, that WP-CLI is the way, and how the owner can lift it. "Contact support" removed
- **Changed:** Verification instructions: the `wp eval` capability check is not meaningful under WP-CLI. Use a browser test
- **Changed:** Code follows WordPress Coding Standards, strings are translatable (text domain `wp-fort-knox`), and `License`, `Requires at least` and `Requires PHP` headers were added
- **Added:** Capability-meta sanitizer (`add_user_metadata` / `update_user_metadata`) that strips `administrator` from any capabilities write to a user who isn't one
- **Added:** `default_role` can't be set to `administrator` (`pre_update_option_default_role`)
- **Added:** Multisite: `granted_super_admin` is reverted and logged
- **Added:** Default managed capabilities now include themes, core, languages and file editors
- **Added:** `WP_FORT_KNOX_STRICT` (adds `activate_plugins` and `switch_themes`)
- **Added:** `WP_FORT_KNOX_PROTECTED_USERS` constant and `wp_fort_knox_protected_users` filter, enforced through `map_meta_cap`
- **Added:** Filters `wp_fort_knox_managed_capabilities` and `wp_fort_knox_log`, action `wp_fort_knox_blocked`
- **Added:** `WP_Fort_Knox::instance()`
- **Added:** Composer scripts (`lint`, `phpcs`, `phpunit`, `test`), PHPCS, PHPUnit and CI

### Version 2.0.0
**Major Release - Non-Destructive Architecture**

- **Changed:** Complete rewrite to use runtime capability filtering instead of permanent database modification
- **Added:** Temporary disable feature via `WP_FORT_KNOX_DISABLED` constant in wp-config.php
- **Added:** Admin notices on plugins and user pages to inform users about restrictions
- **Added:** `wp_fort_knox_disabled` filter for programmatic control (note: it could not actually fire until 2.1.0)
- **Improved:** Structured as proper PHP class with better code organization
- **Fixed:** Capabilities now automatically restore when plugin is disabled (no manual restoration needed)
- **Breaking Change:** Requires capability restoration if upgrading from v1.0.0 (see upgrade guide)

### Version 1.0.0
**Initial Release**

- Blocked file modifications via `DISALLOW_FILE_MODS` constant
- Permanently removed plugin management capabilities from all user roles (destructive)
- Blocked admin user creation and role elevation through wp-admin
- Security logging for admin user creation attempts
- WP-CLI compatibility maintained for all operations

**Deprecated:** This version is no longer recommended due to destructive capability removal.

---

**Remember:** Trust no one. Not even your clients. Especially not your clients.
