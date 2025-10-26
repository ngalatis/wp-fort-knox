# WP Fort Knox - The Paranoid Security Plugin

**Version:** 2.0.0
**Author:** WEFIXIT

A zero-tolerance WordPress security plugin that locks down your site tighter than Fort Knox. Because paranoia is just good planning.

---

## 🎉 What's New in Version 2.0.0

Version 2.0.0 introduces a fundamental philosophical shift in how this plugin operates. Instead of **permanently nuking capabilities** from the WordPress database like some kind of security-drunk cowboy, we now use **runtime filtering**. What does this mean for you?

### The Big Changes:

- **✨ Non-Destructive by Design** - Capabilities are filtered at runtime, not permanently deleted. When you disable the plugin, everything returns to normal automatically. No more manual restoration ceremonies.

- **🔧 Temporary Disable Feature** - Add `define('WP_FORT_KNOX_DISABLED', true);` to wp-config.php and the plugin steps aside gracefully. Need to do some emergency work? Boom, done. Remove the constant when finished.

- **📢 Admin Notices** - The plugin now actually tells users why they can't do things instead of silently crushing their dreams. Revolutionary, we know.

- **🏗️ Cleaner Architecture** - Properly structured as a class because we're professionals (sometimes).

### Why This Matters:

**Version 1.0.0 was a sledgehammer.** It permanently removed capabilities from user roles in the database. Effective? Sure. Reversible? Not without incantations and WP-CLI knowledge. If you disabled v1.0.0 without running the restoration commands, your admins were still crippled. That's not security, that's just being mean.

**Version 2.0.0 is a velvet rope.** Same security, zero permanent damage. The capabilities are still there in the database, we just filter them out when WordPress checks them. Disable the plugin and WordPress goes back to normal business immediately.

Same paranoia, better execution.

---

## 🔄 Upgrading from Version 1.0.0 to 2.0.0

If you're running v1.0.0, you need to upgrade. Not "should upgrade" - **need to upgrade**. Here's why and how.

### Why Upgrade?

Version 1.0.0 permanently strips capabilities from your WordPress database. If something goes wrong, you're manually fixing it. Version 2.0.0 gives you the same security without the permanent consequences. It's a no-brainer.

### The Upgrade Process

**Step 1: Understand Your Current State**

If you've been running v1.0.0, your administrator role (and possibly other roles) have already had their plugin management capabilities permanently removed from the database. This is not a drill - they're actually gone.

**Step 2: Replace the Plugin File**

Via SSH or SFTP:

```bash
# Navigate to mu-plugins
cd /path/to/wp-content/mu-plugins/

# Backup the old version (just in case you enjoy suffering)
cp wp-fort-knox.php wp-fort-knox-v1-backup.php

# Replace with v2.0.0
# Upload the new wp-fort-knox.php file here
```

**Step 3: Restore the Capabilities v1.0.0 Destroyed**

This is the critical step. Version 1.0.0 destroyed these capabilities, and they don't magically come back. You need to restore them manually so that v2.0.0 can properly filter them at runtime.

Via WP-CLI:

```bash
# Option A: Nuclear option - Reset all roles to WordPress defaults
# This is the easiest and most reliable method
wp role reset --all

# Option B: Surgical option - Restore just the plugin capabilities
# Use this if you have custom role configurations you want to preserve
wp cap add administrator install_plugins
wp cap add administrator upload_plugins
wp cap add administrator update_plugins
wp cap add administrator delete_plugins
wp cap add administrator activate_plugins
wp cap add administrator edit_plugins

# If you modified other roles in v1.0.0, restore their capabilities too
wp cap add editor install_plugins
wp cap add editor upload_plugins
# ... etc
```

**Step 4: Verify Everything Works**

```bash
# Check that capabilities are back in the database
wp cap list administrator | grep plugin

# You should see:
# install_plugins
# upload_plugins
# update_plugins
# delete_plugins
# activate_plugins
# edit_plugins
```

**Step 5: Test the New Version**

1. Try to access the Plugins page in wp-admin (you should see a notice from WP Fort Knox)
2. Try to install a plugin via wp-admin (should be blocked)
3. Install a plugin via WP-CLI (should work perfectly)
4. Temporarily disable via wp-config.php to verify the disable feature works

### What If I Already Removed v1.0.0 Without Restoring Capabilities?

Then your admin users are currently hobbled and you didn't even know it. Follow **Step 3** above to restore the capabilities, then install v2.0.0. This is exactly why v2.0.0 exists.

### Post-Upgrade Checklist

- ✅ Plugin capabilities exist in database (verified via `wp cap list`)
- ✅ v2.0.0 file is in mu-plugins directory
- ✅ Plugin management is blocked in wp-admin (as intended)
- ✅ Plugin management works via WP-CLI (as intended)
- ✅ Temporary disable feature works (test it)

---

## 🚨 Installation - Must-Use Plugin Only

This plugin is **designed exclusively** for the `wp-content/mu-plugins` folder. It cannot and should not be installed as a regular plugin.

### Quick Installation (One Command)

**Method 1: WP-CLI (Recommended)**

From your WordPress installation directory:

```bash
wp eval '
    $mu_dir = WP_CONTENT_DIR . "/mu-plugins";
    if (!is_dir($mu_dir)) { mkdir($mu_dir, 0755, true); }
    file_put_contents(
        $mu_dir . "/wp-fort-knox.php",
        file_get_contents("https://raw.githubusercontent.com/YOUR_USERNAME/wp-fort-knox/v2.0.0/wp-fort-knox.php")
    );
    echo "WP Fort Knox v2.0.0 installed successfully!\n";
'
```

**Why WP-CLI method is better:**
- Automatically finds the correct wp-content path (works with custom directory structures)
- Creates mu-plugins directory if it doesn't exist
- Works regardless of your current directory
- Verifies you're in a valid WordPress installation

**Method 2: Direct Download**

```bash
# Create mu-plugins directory if needed
mkdir -p /path/to/wordpress/wp-content/mu-plugins

# Download the plugin (curl)
curl -o /path/to/wordpress/wp-content/mu-plugins/wp-fort-knox.php \
https://raw.githubusercontent.com/YOUR_USERNAME/wp-fort-knox/v2.0.0/wp-fort-knox.php

# Or with wget
wget -O /path/to/wordpress/wp-content/mu-plugins/wp-fort-knox.php \
https://raw.githubusercontent.com/YOUR_USERNAME/wp-fort-knox/v2.0.0/wp-fort-knox.php
```

**Note:** Replace `YOUR_USERNAME` with the actual GitHub username/organization and `/path/to/wordpress/` with your actual WordPress installation path.

### Manual Installation

1. Download `wp-fort-knox.php` from the [v2.0.0 release](https://github.com/YOUR_USERNAME/wp-fort-knox/releases/tag/v2.0.0)
2. Upload to your `wp-content/mu-plugins/` directory via SFTP
3. That's it. No activation needed. It runs automatically.

### Verify Installation

```bash
wp eval 'var_dump(class_exists("WP_Fort_Knox"));'
# Should output: bool(true)
```

**Important:** Don't try to install this as a regular plugin. It defeats the whole purpose.

---

## 🔒 What Does This Thing Actually Do?

This plugin takes the nuclear option and **completely strips admin permissions** for anything file-related through the WordPress admin interface:

- ❌ **No file modifications** - Can't edit themes, plugins, or any other files
- ❌ **No plugin management** - Can't install, update, or delete plugins
- ❌ **No admin user creation** - Can't create new administrator accounts
- ❌ **No role elevation** - Can't promote existing users to administrator
- ❌ **Administrator role hidden** - Doesn't even appear in the user role dropdown

### But Wait, There's More!

- ✅ **WP-CLI still works perfectly** - All admin operations work through command line
- ✅ **Security logging** - Attempts to create admin users are logged with IP addresses
- ✅ **Network compatible** - Works on WordPress multisite installations
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

**Assuming there's no shell access with the same credentials** (and there shouldn't be), the attacker is essentially locked out of doing any real damage through the UI. They got credentials? Great. They're worthless.

---

## 🛠️ "But What If the Client Wants to Install/Update Something?"

Tough luck. They go through you, the developer. 

Look, I don't care what they want to install. Half the time clients install bloated, poorly-coded plugins that create more problems than they solve. The other half they decide to update their whole site on a Friday night and then ruin your weekend because it broke after the updates. This way, we maintain quality control and actually know what's running on the site.

**Added bonus:** This can be excellent leverage for clients who mysteriously "forget" to pay their bills on time. No payment, no updates, hard to leave without your involvement. Simple business.

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

### Disabling the Plugin (v2.0.0)

Need to temporarily disable WP Fort Knox? Easy. Version 2.0.0 made this civilized.

**Option 1: Temporary Disable (Recommended)**

Add this to your wp-config.php:

```php
define('WP_FORT_KNOX_DISABLED', true);
```

The plugin sees this and steps aside. All capabilities work normally. Remove the line when you're done. Simple.

**Option 2: Permanent Removal**

If you're really done with paranoia (bad choice, but whatever):

```bash
# Via SSH/SFTP, navigate to mu-plugins and remove the file
cd /path/to/wp-content/mu-plugins/
rm wp-fort-knox.php

# Or just rename it
mv wp-fort-knox.php wp-fort-knox.php.disabled
```

That's it. No capability restoration needed, no database cleanup, no prayer circles. The capabilities were never actually removed, just filtered. When the plugin's gone, the filters are gone, and everything works normally.

**Again, this is why v2.0.0 exists.**

---

## 🔍 Security Logging

Every attempt to create an admin user (successful or not) is logged to your error log with:
- Username
- User ID
- IP address of the request
- Timestamp

Check your WordPress debug log or server error logs to monitor suspicious activity.

---

## ⚠️ Important Notes & Warnings

### Prerequisites - Read This Before Installing

This plugin is **not for casual WordPress users**. It's designed for developers and system administrators who manage WordPress sites professionally. You need:

**Required:**
- ✅ **SSH/SFTP access** to your server - You need to be able to upload files and navigate the filesystem
- ✅ **WP-CLI installed and working** - This is non-negotiable. All admin operations go through WP-CLI
- ✅ **Command line proficiency** - You should be comfortable with terminal commands and bash
- ✅ **WordPress knowledge** - Understanding of roles, capabilities, and how WordPress security works

**Recommended:**
- ✅ **Root/sudo access** - For modifying wp-config.php with proper permissions (though you can work around this with chmod if needed)
- ✅ **Git familiarity** - For updating the plugin and tracking changes
- ✅ **Database backup strategy** - Because you should always have backups, paranoid or not

**If you don't have the above, this plugin is not for you.** Seriously. You'll lock yourself out and blame us. Don't do it.

### General Warnings

- **This plugin is aggressive by design.** It's not for everyone.
- **WP-CLI access is required** for any administrative file operations.
- **Existing admin users keep their roles** but can't perform file operations through wp-admin.
- **Cannot be deactivated from wp-admin** (because it's in mu-plugins, duh).
- To disable: Add constant to wp-config.php or SSH in and delete/rename the file.

### ✨ Version 2.0.0: Non-Destructive Operation

**Good news: This plugin NO LONGER permanently destroys capabilities.**

Version 2.0.0 uses runtime filtering instead of database modification. When the plugin is active, it filters out plugin management capabilities when WordPress checks them. When the plugin is disabled, capabilities work normally again. No restoration needed, no database cleanup, no drama.

**If you're upgrading from v1.0.0:** See the upgrade instructions above. You'll need to restore the capabilities v1.0.0 destroyed before v2.0.0 can work properly.

---

## 🎯 Who Should Use This?

- Agencies managing client sites who are tired of cleaning up malware
- Developers who want to sleep peacefully at night
- Anyone who's dealt with "my site got hacked" calls on a Friday night one too many times
- Sites with clients who treat passwords like public information
- Paranoid sysadmins (the best kind of sysadmins)

---

## 📝 Technical Details

**Defines:** `DISALLOW_FILE_MODS` constant (only when not in WP-CLI)

**Filters Capabilities at Runtime (Non-Destructive):**
- `install_plugins`
- `upload_plugins`
- `update_plugins`
- `delete_plugins`

**WordPress Hooks Used:**

*Filters:*
- `user_has_cap` - Runtime capability filtering (the magic sauce)
- `editable_roles` - Hides administrator role from dropdowns
- `pre_insert_user_data` - Blocks admin user creation attempts
- `wp_fort_knox_disabled` - Programmatic disable control

*Actions:*
- `set_user_role` - Prevents role elevation to administrator
- `admin_notices` - Shows informational notices on relevant admin pages

**Disable Methods:**
- WP-CLI context (automatic bypass)
- `WP_FORT_KNOX_DISABLED` constant in wp-config.php
- `wp_fort_knox_disabled` filter hook

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

**A:** No. The capability filtering happens on admin requests only, not on the frontend. The performance impact is negligible - we're talking microseconds on admin page loads. Your visitors won't notice a thing.

### Q: Can I use this on WordPress Multisite?

**A:** Yes. The plugin is network-compatible and works on WordPress multisite installations. It will apply restrictions network-wide since it's an mu-plugin.

### Q: What if I lose SSH access?

**A:** You're in trouble, but not because of this plugin. You'd need to contact your hosting provider to restore SSH access. Once you have it back, you can disable the plugin via wp-config.php or remove the file. This is why we recommend having a backup access method to your server.

### Q: Can I whitelist certain admin users to bypass restrictions?

**A:** Not in v2.0.0. Everyone goes through WP-CLI, no exceptions. This is by design - security that has exceptions isn't secure. If you need this feature, you can modify the code or use the `wp_fort_knox_disabled` filter to implement your own logic.

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

**A:** `DISALLOW_FILE_MODS` is great, but it only blocks file modifications. Attackers can still create admin accounts and elevate user roles, which can be used for reconnaissance, data theft, or setting up future attacks. WP Fort Knox blocks these vectors too.

### Q: What happens to scheduled plugin updates?

**A:** Automatic updates via wp-cron are blocked by `DISALLOW_FILE_MODS`. You handle all updates manually via WP-CLI. This is actually a good thing - you maintain control over when updates happen and can test them properly.

### Q: Can clients still access wp-admin?

**A:** Yes. They can log in and do everything except:
- Manage plugins (install/update/delete)
- Edit files (plugins, themes, etc.)
- Create admin users
- Elevate users to admin

They can still manage content, customize themes via the customizer (if it doesn't require file writes), manage users (non-admin), etc.

### Q: Do I need root access to use this?

**A:** Not strictly required, but **highly recommended**. You need to be able to:
1. Upload files to `wp-content/mu-plugins/`
2. Optionally modify `wp-config.php` (for the disable constant)
3. Run WP-CLI commands (may or may not need root depending on file permissions)

If your server is set up with proper file ownership (WordPress files owned by your user), you might not need root. But for modifying wp-config.php with secure permissions (600/400), root is helpful.

---

## 🔧 Troubleshooting

### Plugin doesn't seem to be active / restrictions not working

**Check:**
1. File is actually in `wp-content/mu-plugins/wp-fort-knox.php` (not in a subdirectory)
2. File permissions are readable (644 or 644)
3. No PHP errors - check your error logs: `tail -f /path/to/error.log`
4. Not accidentally disabled via `WP_FORT_KNOX_DISABLED` constant in wp-config.php
5. Verify with: `wp eval 'var_dump(class_exists("WP_Fort_Knox"));'` - should return `bool(true)`

### I can still install plugins in wp-admin

**Possible causes:**
1. You're testing with WP-CLI (it bypasses the plugin by design)
2. The plugin file isn't loaded (see above)
3. You're on a staging/local environment and don't have `ABSPATH` properly defined
4. Another plugin is interfering (unlikely) - disable other security plugins temporarily to test

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
You forgot to restore the capabilities. Run:
```bash
wp role reset --all
# Or manually:
wp cap add administrator install_plugins upload_plugins update_plugins delete_plugins activate_plugins edit_plugins
```

See the full upgrade guide above.

### Plugin conflicts with my theme's admin features

**Not a bug, it's a feature.** If your theme's admin features require file modifications or plugin installations, they'll be blocked. You have two options:

1. **Disable temporarily** when you need those features:
   ```php
   define('WP_FORT_KNOX_DISABLED', true); // in wp-config.php
   ```

2. **Programmatic control** - Add this to your theme's functions.php:
   ```php
   add_filter('wp_fort_knox_disabled', function($disabled) {
       // Your custom logic here
       // Example: Disable for specific user
       if (get_current_user_id() === 1) {
           return true;
       }
       return $disabled;
   });
   ```

### How do I verify it's actually working?

**Test scenarios:**

1. **Try to install a plugin via wp-admin** - Should be blocked (buttons disabled/hidden)
2. **Try to create admin user via wp-admin** - Should see error or no admin role in dropdown
3. **Install via WP-CLI** - Should work perfectly:
   ```bash
   wp plugin install wordpress-seo
   # Should succeed
   ```
4. **Check capabilities are filtered:**
   ```bash
   # This should show install_plugins = false (filtered at runtime)
   wp eval 'var_dump(current_user_can("install_plugins"));'
   ```

---

## 📋 Changelog

### Version 2.0.0 (Current)
**Major Release - Non-Destructive Architecture**

- **Changed:** Complete rewrite to use runtime capability filtering instead of permanent database modification
- **Added:** Temporary disable feature via `WP_FORT_KNOX_DISABLED` constant in wp-config.php
- **Added:** Admin notices on plugins and user pages to inform users about restrictions
- **Added:** `wp_fort_knox_disabled` filter for programmatic control
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

