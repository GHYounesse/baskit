# ARG defines a variable that can be changed at build time (docker build --build-arg NAME=value).
# Define the PHP and Node.js versions to use. Change them here to upgrade, 
# Or change at build time using --build-arg ( for example docker build --build-arg PHP_VERSION=8.3)
ARG PHP_VERSION=8.3
ARG NODE_VERSION=22


# ==================================================================================================
# STAGE 1: base. PHP-FPM with the extensions Laravel and its libraries need
# ==================================================================================================

# The starting image is "php:8.3-fpm-bookworm" is the official PHP 8.3 with FPM (FastCGI Process Manager) on Debian 12.
# With the name "base"
#Navigateur ──► Nginx ──► PHP-FPM ──► Laravel
#                │            │
#                │            └─ exécute index.php, parle à PostgreSQL
#                └─ sert directement les images, CSS et JS

FROM php:${PHP_VERSION}-fpm-bookworm AS base

# Executing a shell command while building the image (the build stop at the first error and print each command).
RUN set -eux; \
    # Refresh the list of installable Debian packages.
    apt-get update; \
    # Install only the development headers PHP extensions need to compile, plus "libfcgi-bin"
    apt-get install -y --no-install-recommends \
        libpq-dev libicu-dev libzip-dev libfcgi-bin; \
    # Compile (on all available CPU cores) and enable the PHP extensions:
    #   bcmath    exact decimal maths          intl      internationalisation (formatting, collation)
    #   opcache   keeps compiled PHP in memory pcntl     lets the queue worker stop cleanly on signals
    #   pdo_pgsql talk to PostgreSQL           zip       read/write zip files (used by Composer)
    docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_pgsql zip; \
    # Mark and protect the runtime libraries (libpq5, libicu72, libzip4) and the FastCGI binary (libfcgi-bin)
    apt-mark manual libpq5 libicu72 libzip4 libfcgi-bin; \
    # Remove the development headers and other build tools we no longer need. This makes the image smaller.
    apt-get purge -y --auto-remove libpq-dev libicu-dev libzip-dev; \
    # Delete the downloaded package lists: they are only useful at install time.
    rm -rf /var/lib/apt/lists/*


# ==================================================================================================
# STAGE 2: vendor. Install the PHP libraries (Composer dependencies)
# ==================================================================================================

# The starting image is "base" defined above, with the name "vendor".
FROM base AS vendor

# Copy the Composer program (/usr/bin/composer) from its official image and place it in the /usr/bin/composer in the "vendor" stage.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# WORKDIR sets the folder where the following instructions run (and creates it if needed).
WORKDIR /app

# WITH_DEV=0 (default) installs production libraries only. WITH_DEV=1 also installs the dev libraries
ARG WITH_DEV=0

# Copy ONLY the dependency lists first. Docker re-runs the slow "composer install" below only when
# these two files change, not every time you edit a controller. This is the cache trick.
COPY composer.json composer.lock ./

# Download the libraries listed in composer.lock.
#   $([ "$WITH_DEV" = "1" ] || echo --no-dev)  adds --no-dev unless WITH_DEV is 1
#   --no-interaction --no-progress             no questions asked, no progress bar noise in logs
#   --no-scripts                               do not run Laravel's scripts yet (the app is not here yet)
#   --no-autoloader                            build the autoloader later, once the code is copied
#   --prefer-dist                              download release archives instead of cloning git repos
RUN composer install $([ "$WITH_DEV" = "1" ] || echo --no-dev) --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist

# Now copy the folders Composer needs to see to build its class map (these hold PHP classes).
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes

# Generate the autoloader: the file that lets PHP find every class without scanning folders.
#   --optimize --classmap-authoritative  precompute the full list of classes so production is fast
RUN composer dump-autoload $([ "$WITH_DEV" = "1" ] || echo --no-dev) --optimize --classmap-authoritative --no-scripts


# ==================================================================================================
# STAGE 3: assets. Build the frontend (React, Tailwind) with Vite
# ==================================================================================================

# A different base image: Node.js, because Vite is a JavaScript tool. PHP is not needed here.
FROM node:${NODE_VERSION}-bookworm-slim AS assets
WORKDIR /app

# Same cache trick as before: copy the dependency lists first...
COPY package.json package-lock.json ./

# ...then install them. "npm ci" installs exactly what package-lock.json says (reproducible).
# --no-audit --no-fund skip network calls and messages we do not need.
RUN npm ci --no-audit --no-fund

# Copy the files Vite and Tailwind need: their config files, the React source, and the public folder.
COPY vite.config.js tailwind.config.js postcss.config.js jsconfig.json ./
COPY resources ./resources
COPY public ./public

# The app name shown in browser tab titles. Vite writes it INTO the JavaScript at build time, so it
# cannot be changed later from the server's .env. ENV makes it visible to the build command below.
ARG VITE_APP_NAME=Baskit
ENV VITE_APP_NAME=${VITE_APP_NAME}

# Build the production frontend. The result lands in /app/public/build (hashed .js and .css files).
RUN npm run build


# ==================================================================================================
# STAGE 4: app. The final image
# ==================================================================================================

# Start again from "base": PHP-FPM with extensions, and none of the Composer or Node tools.
FROM base AS app
WORKDIR /var/www/html

# Our PHP settings (memory, upload size, OPcache), PHP-FPM tuning, and the startup script.
# Files in conf.d / php-fpm.d are read automatically. "zz-" makes ours load last, so they win.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-baskit.ini
COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-baskit.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Copy the application. Order matters for the cache: things that change rarely come first
# (libraries), things that change often come last (your own code), so a small code edit only
# invalidates the last layers.
#
# The PHP libraries built in the "vendor" stage:
COPY --from=vendor /app/vendor ./vendor
# Laravel's entry command and the composer manifest:
COPY artisan composer.json ./
# The application folders:
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
# Only the Blade views are needed at runtime (the React source is already compiled):
COPY resources/views ./resources/views
# The public folder (index.php, images):
COPY public ./public
COPY app ./app
# The compiled JavaScript and CSS built in the "assets" stage:
COPY --from=assets /app/public/build ./public/build

# One RUN for several small setup jobs, so they make a single layer.
# Security idea: PHP may write ONLY to the runtime folders; the code itself stays owned by root
# and cannot be modified by the web process if it were ever compromised.
RUN set -eux; \
    # Make the startup script executable.
    chmod +x /usr/local/bin/entrypoint.sh; \
    # Create the folders Laravel writes to (logs, cache, sessions, compiled views, uploads).
    mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache; \
    # Give those folders (and only those) to "www-data", the unprivileged user PHP runs as.
    chown -R www-data:www-data storage bootstrap/cache; \
    # The directory itself (not its files) so storage:link can create public/storage at start.
    chown www-data:www-data public; \
    # Mount point of the volume shared with the web container (see docker/entrypoint.sh).
    mkdir /public-shared; \
    chown www-data:www-data /public-shared; \
    # Let Laravel register its installed packages (writes bootstrap/cache/packages.php).
    php artisan package:discover --ansi

# From here on, everything runs as "www-data" instead of root.
USER www-data

# Documentation only: PHP-FPM listens on port 9000 (Nginx connects to it). It does not publish it.
EXPOSE 9000

# ENTRYPOINT is the script that always runs first when the container starts. It prepares storage,
# caches the config if RUN_OPTIMIZE=true, and refreshes the shared public files. It ends with
# "exec", which launches the CMD below.
ENTRYPOINT ["entrypoint.sh"]

# CMD is the default command the entrypoint hands over to: start PHP-FPM.
# Compose overrides it for the "queue" and "scheduler" containers, which run the same image
# but start "php artisan queue:work" and "php artisan schedule:work" instead.
CMD ["php-fpm"]
