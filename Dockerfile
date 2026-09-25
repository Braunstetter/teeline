#syntax=docker/dockerfile:1

# Versions
FROM dunglas/frankenphp:1-php8.5 AS frankenphp_upstream
FROM node:22-slim AS node_upstream

# The different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/build/building/multi-stage/#stop-at-a-specific-build-stage
# https://docs.docker.com/reference/compose-file/build/#target


# Base FrankenPHP image
FROM frankenphp_upstream AS frankenphp_base

SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]

WORKDIR /app

# persistent deps
# hadolint ignore=DL3008
RUN <<-EOF
	apt-get update
	apt-get install -y --no-install-recommends \
		file \
		git \
		procps
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
		imagick
	rm -rf /var/lib/apt/lists/*
EOF

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

###> recipes ###
###> doctrine/doctrine-bundle ###
RUN install-php-extensions pdo_pgsql
###< doctrine/doctrine-bundle ###
###< recipes ###

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics", context: stream_context_create(["http" => ["timeout" => 5]])) ? 1 : 0);'
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

# Dev FrankenPHP image
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off
ENV FRANKENPHP_WORKER_CONFIG=watch

# dev dependencies
RUN <<-EOF
	mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
	install-php-extensions xdebug
	useradd -m -s /bin/bash nonroot
	git config --system --add safe.directory /app
EOF

# A fresh named volume inherits owner and mode from the image directory, and this container
# runs as the host user, whose UID the image cannot know — so /data and /config have to be
# writable for anyone. Dev image only; the prod stage chowns them to www-data instead.
RUN chmod -R 777 /data /config

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]

# Node image: runs vite, never ships — the prod stage copies only what it built.
FROM node_upstream AS node_base

# Pinned, not "latest": two builds of the same commit must get the same npm. Bump by hand.
# Requires node >=22.22.2 — see `npm view npm engines` before raising it.
RUN npm install -g npm@12.0.2

# Pre-create the cache dir permissively: the named volume inherits these permissions, and
# the container runs as the host user, whose UID the image cannot know.
RUN mkdir -p /npm-cache && chmod 777 /npm-cache

WORKDIR /app


# Builds the assets the prod image serves. Split from the sources on purpose: npm ci only
# reruns when the lockfile changes, not on every edit to a template.
FROM node_base AS node_build

COPY --link package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY --link vite.config.js ./
COPY --link assets assets/
# Tailwind scans the templates for class names, so they belong in this stage too —
# without them the stylesheets come out holding nothing but preflight.
COPY --link templates templates/
RUN npm run build


# Builder for the prod FrankenPHP image
FROM frankenphp_base AS frankenphp_prod_builder

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# prevent the reinstallation of vendors at every changes in the source code
COPY --link composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

# copy sources
COPY --link --exclude=frankenphp/ . ./

# Before the warmup below: with reprise.cache enabled in prod, cache:warmup compiles
# entrypoints.json into a PHP file, so the assets have to exist by now.
COPY --link --from=node_build /app/public/build public/build

RUN <<-EOF
	mkdir -p var/cache var/log var/share
	composer dump-autoload --classmap-authoritative --no-dev
	composer dump-env prod
	composer run-script --no-dev post-install-cmd
	if [ -f importmap.php ]; then
		php bin/console asset-map:compile
	fi
	chmod +x bin/console
	chmod -R g=u var
	sync
EOF

# Collect shared libraries needed by FrankenPHP and PHP extensions
# hadolint ignore=DL3008,SC3054,DL4006
RUN <<-'EOF'
	apt-get update
	apt-get install -y --no-install-recommends libtree
	mkdir -p /tmp/libs /tmp/imagemagick/config
	IM_CODERS_DIR="$(find /usr/lib -type d -name coders -path '*ImageMagick*' | head -1)"
	cp -r "$IM_CODERS_DIR" /tmp/imagemagick/coders
	cp -r "$(dirname "$IM_CODERS_DIR")/filters" /tmp/imagemagick/filters
	find "$(dirname "$(dirname "$IM_CODERS_DIR")")" -name '*.xml' -exec cp {} /tmp/imagemagick/config/ ';'
	cp /etc/ImageMagick-*/*.xml /tmp/imagemagick/config/
	BINARIES=(frankenphp php file pgrep)
	for target in $(printf '%s\n' "${BINARIES[@]}" | xargs -I{} which {}) \
		$(find "$(php -r 'echo ini_get("extension_dir");')" -maxdepth 2 -name "*.so") \
		$(find /tmp/imagemagick -name "*.so"); do
		libtree -pv "$target" 2>/dev/null | grep -oP '(?:── )\K/\S+(?= \[)' | while IFS= read -r lib; do
			[ -f "$lib" ] && cp -n "$lib" /tmp/libs/
		done
	done
	rm -rf /var/lib/apt/lists/*
EOF

# Prod FrankenPHP image
FROM debian:13-slim AS frankenphp_prod

SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]

ENV APP_ENV=prod
ENV PHP_INI_SCAN_DIR=":/usr/local/etc/php/app.conf.d"

COPY --from=frankenphp_prod_builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp
COPY --from=frankenphp_prod_builder /usr/local/bin/php /usr/local/bin/php
COPY --from=frankenphp_prod_builder /usr/local/bin/docker-php-entrypoint /usr/local/bin/docker-php-entrypoint
COPY --from=frankenphp_prod_builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=frankenphp_prod_builder /tmp/libs /usr/lib
COPY --from=frankenphp_prod_builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d
COPY --from=frankenphp_prod_builder /usr/local/etc/php/php.ini /usr/local/etc/php/php.ini
COPY --from=frankenphp_prod_builder /usr/local/etc/php/app.conf.d /usr/local/etc/php/app.conf.d

COPY --from=frankenphp_prod_builder /etc/frankenphp/Caddyfile /etc/frankenphp/Caddyfile

# CA certificates for TLS, file/libmagic for Symfony MIME type detection
COPY --from=frankenphp_prod_builder /etc/ssl/certs/ca-certificates.crt /etc/ssl/certs/ca-certificates.crt
COPY --from=frankenphp_prod_builder /etc/ssl/openssl.cnf /etc/ssl/openssl.cnf
COPY --from=frankenphp_prod_builder /usr/bin/file /usr/bin/file
# For the worker healthcheck; its libraries ride along via the BINARIES list above.
COPY --from=frankenphp_prod_builder /usr/bin/pgrep /usr/bin/pgrep
COPY --from=frankenphp_prod_builder /usr/lib/file/magic.mgc /usr/lib/file/magic.mgc

COPY --from=frankenphp_prod_builder /tmp/imagemagick /usr/lib/imagemagick

ENV  OPENSSL_CONF=/etc/ssl/openssl.cnf XDG_CONFIG_HOME=/config XDG_DATA_HOME=/data SSL_CERT_FILE=/etc/ssl/certs/ca-certificates.crt
ENV  MAGICK_CODER_MODULE_PATH=/usr/lib/imagemagick/coders MAGICK_CODER_FILTER_PATH=/usr/lib/imagemagick/filters MAGICK_CONFIGURE_PATH=/usr/lib/imagemagick/config

RUN <<-EOF
	mkdir -p /data/caddy /config/caddy
	chown -R www-data:www-data /data /config
	# Remove setuid/setgid bits
	find / -perm /6000 -type f -exec chmod a-s {} + 2>/dev/null || true
EOF

COPY --link --exclude=var --from=frankenphp_prod_builder /app /app
# Group 0 + g=u for arbitrary-UID runtimes (e.g. OpenShift).
COPY --chown=www-data:0 --from=frankenphp_prod_builder /app/var /app/var
RUN chmod g=u /app/var

# The upload directory has to exist before the volume mounts over it: docker seeds a
# named volume from the image, and takes the ownership with it.
# Group 0 + g=u, same as var/ above: an arbitrary-UID runtime lands in group 0 and
# inherits what the owner can do.
RUN mkdir -p /app/public/media/uploads \
	&& chown -R www-data:0 /app/public/media \
	&& chmod -R g=u /app/public/media

COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

USER www-data

WORKDIR /app

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics", context: stream_context_create(["http" => ["timeout" => 5]])) ? 1 : 0);'
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

# Build-only, never shipped: fails the build if Imagick lost its coder modules.
FROM frankenphp_prod AS frankenphp_prod_verify
COPY --link frankenphp/verify-imagick.php /tmp/verify-imagick.php
RUN php /tmp/verify-imagick.php
