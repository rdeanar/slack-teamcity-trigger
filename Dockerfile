FROM php:7.3-cli-alpine

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN set -eux; \
    composer config -g github-oauth.github.com "c2cf68f5ea048f440ec451af81068798f2c02fdb"; \
	composer global require "hirak/prestissimo:^0.3" --prefer-dist --no-progress --no-suggest --classmap-authoritative; \
	composer clear-cache
ENV PATH="${PATH}:/root/.composer/vendor/bin"

WORKDIR /app

COPY composer.json composer.lock ./
RUN set -eux; \
	composer install --prefer-dist --no-scripts --no-progress --no-suggest; \
	composer clear-cache

COPY ./src/ ./

CMD ["/usr/local/bin/php", "/app/server.php"]


