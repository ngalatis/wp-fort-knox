# Build for Anthropic's OSS Scanner (https://github.com/anthropics/oss-scanner).
#
# The scanner builds this with the repository as the build context, then audits the
# finished image with no network, so everything the audit needs is fetched here.
# The image holds:
#   /src                the checkout, with the Composer dev dependencies (PHPUnit,
#                       Brain Monkey, PHPCS) installed, so `composer test` works offline
#   /var/www/wordpress  a real WordPress site on MariaDB, with /src/wp-fort-knox.php
#                       symlinked into wp-content/mu-plugins, and WP-CLI as `wp`
# .oss-scanner/threat_model.md says how to start the site and which accounts exist.
FROM debian:bookworm
ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    WP_CLI_ALLOW_ROOT=1
RUN apt-get update && apt-get install -y --no-install-recommends \
      ca-certificates curl git unzip less \
      php-cli php-curl php-gd php-intl php-mbstring php-mysql php-xml php-zip \
      composer mariadb-server mariadb-client \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp \
    && wp --version

# scanner contract: the checkout lives inside the image, at /src
COPY . /src
WORKDIR /src

RUN composer install --no-interaction --prefer-dist --no-progress

# Lint, coding standards and unit tests. A failure is reported but does not abort the build.
RUN composer test || echo "WARNING: composer test reported failures"

# A WordPress site set up the way the README recommends: the plugin as a must-use
# plugin, a client administrator (admin) and an agency account (agency_admin) that
# WP_FORT_KNOX_PROTECTED_USERS protects. The site is configured with WP-CLI, which the
# plugin exempts by design. Passwords equal the login names.
ARG WP_PATH=/var/www/wordpress
RUN set -e; \
    service mariadb start; \
    mysql -e "CREATE DATABASE wordpress; \
      CREATE USER 'wordpress'@'localhost' IDENTIFIED BY 'wordpress'; \
      GRANT ALL PRIVILEGES ON wordpress.* TO 'wordpress'@'localhost';"; \
    mkdir -p "$WP_PATH"; \
    wp core download --path="$WP_PATH"; \
    cd "$WP_PATH"; \
    wp config create --dbname=wordpress --dbuser=wordpress --dbpass=wordpress --dbhost=localhost; \
    wp config set WP_DEBUG true --raw; \
    wp config set WP_DEBUG_LOG true --raw; \
    wp config set WP_DEBUG_DISPLAY false --raw; \
    wp config set WP_FORT_KNOX_PROTECTED_USERS agency_admin; \
    wp core install --url=http://127.0.0.1:8080 --title="WP Fort Knox" \
      --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email; \
    wp user create agency_admin agency_admin@example.com --role=administrator --user_pass=agency_admin; \
    wp user create editor editor@example.com --role=editor --user_pass=editor; \
    wp user create author author@example.com --role=author --user_pass=author; \
    wp user create subscriber subscriber@example.com --role=subscriber --user_pass=subscriber; \
    mkdir -p wp-content/mu-plugins; \
    ln -s /src/wp-fort-knox.php wp-content/mu-plugins/wp-fort-knox.php; \
    wp eval 'if ( ! class_exists( "WP_Fort_Knox" ) ) { fwrite( STDERR, "WP Fort Knox did not load\n" ); exit( 1 ); }'; \
    chown -R www-data:www-data "$WP_PATH"; \
    service mariadb stop
