# WP Fort Knox - The Paranoid Security Plugin

**Version:** 1.0.0  
**Author:** WEFIXIT

A zero-tolerance WordPress security plugin that locks down your site tighter than Fort Knox. Because paranoia is just good planning.

---

## 🚨 Installation - Must-Use Plugin Only

This plugin is **designed exclusively** for the `wp-content/mu-plugins` folder. It cannot and should not be installed as a regular plugin.

**Installation Steps:**
1. Copy `wp-fort-knox.php` to your `wp-content/mu-plugins/` directory
2. That's it. No activation needed. It runs automatically.
3. Seriously, don't try to install this as a regular plugin. It defeats the whole purpose.

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

**Assuming there's no shell access** (and there shouldn't be), the attacker is essentially locked out of doing any real damage through the UI. They got credentials? Great. They're worthless.

---

## 🛠️ "But What If the Client Wants to Install/Update Something?"

Tough luck. They go through us. 

Look, I don't care what they want to install. Half the time clients install bloated, poorly-coded plugins that create more problems than they solve. This way, we maintain quality control and actually know what's running on the site.

**Added bonus:** This can be excellent leverage for clients who mysteriously "forget" to pay their bills on time. No payment, no updates. Simple business.

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

### Disabling the Plugin & Restoring Admin Capabilities

If you need to disable WP Fort Knox and restore normal WordPress functionality:

```bash
# Step 1: Remove or rename the plugin file (via SSH/SFTP)
# Navigate to wp-content/mu-plugins/ and delete or rename wp-fort-knox.php

# Step 2: Reset all roles to WordPress defaults (easiest method)
wp role reset --all

# Or reset just the administrator role
wp role reset administrator

# Verify capabilities were restored
wp cap list administrator | grep plugin
```

**Alternative method** - Manually restore specific capabilities if you don't want to reset everything:

```bash
# Restore plugin management capabilities to administrators
wp cap add administrator install_plugins
wp cap add administrator upload_plugins
wp cap add administrator update_plugins
wp cap add administrator delete_plugins
wp cap add administrator activate_plugins
wp cap add administrator edit_plugins
```

**Pro tip:** Before disabling the plugin, save these commands somewhere accessible. You'll need them.

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

- **This plugin is aggressive by design.** It's not for everyone.
- **WP-CLI access is required** for any administrative file operations.
- **Existing admin users keep their roles** but can't perform file operations through wp-admin.
- **Cannot be deactivated from wp-admin** (because it's in mu-plugins, duh).
- To disable: SSH in and delete/rename the file from mu-plugins folder.

### ⚠️⚠️⚠️ CRITICAL WARNING: DESTRUCTIVE CAPABILITY REMOVAL ⚠️⚠️⚠️

**This plugin PERMANENTLY removes capabilities from WordPress roles in a destructive way.**

When the plugin runs, it strips plugin management capabilities from all user roles. **These capabilities DO NOT automatically return when you disable the plugin.** If you disable WP Fort Knox without restoring capabilities, your admin users will still be unable to manage plugins through wp-admin.

You've been warned.

---

## 🎯 Who Should Use This?

- Agencies managing client sites who are tired of cleaning up malware
- Developers who want to sleep peacefully at night
- Anyone who's dealt with "my site got hacked" calls one too many times
- Sites with clients who treat passwords like public information
- Paranoid sysadmins (the best kind of sysadmins)

---

## 📝 Technical Details

**Defines:** `DISALLOW_FILE_MODS` constant (only when not in WP-CLI)

**Removes Capabilities:**
- `install_plugins`
- `upload_plugins`
- `update_plugins`
- `delete_plugins`

**Filters Used:**
- `editable_roles` - Hides administrator role
- `pre_insert_user_data` - Blocks admin user creation
- `wp_update_user` - Prevents role elevation
- `user_has_cap` - Monitors capability changes

**Actions Used:**
- `init` - Sets up restrictions early
- `user_register` - Logs admin user creation

---

## 📜 License

Use it, modify it, distribute it. Just don't blame us if you lock yourself out.

---

## 🤝 Support

Have a problem? Check your WP-CLI access first. Still have a problem? You probably did something wrong.

For serious issues: Open an issue or PR on the repo.

---

**Remember:** Trust no one. Not even your clients. Especially not your clients.

