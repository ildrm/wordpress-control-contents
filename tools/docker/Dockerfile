ARG WORDPRESS_TAG=6.9.4-php8.2-apache
FROM wordpress:${WORDPRESS_TAG}
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev \
    && docker-php-ext-install intl \
    && rm -rf /var/lib/apt/lists/*
