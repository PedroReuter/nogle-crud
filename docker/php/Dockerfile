# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Dependências PHP
# ---------------------------------------------------------------------------
# Estágio separado para que a instalação do Composer seja refeita apenas
# quando composer.json ou composer.lock mudarem, e não a cada alteração de
# código da aplicação.
FROM composer:2 AS vendor

WORKDIR /app

# Só os arquivos de dependência primeiro: assim a instalação é refeita
# apenas quando eles mudam, e não a cada alteração de código.
COPY backend/composer.json backend/composer.lock ./

# `--no-dev` deixa fora Pest, Larastan e Pint: ferramentas de
# desenvolvimento não têm função na imagem que roda a aplicação, e cada
# pacote a menos é menos superfície e menos peso.
#
# `--no-scripts` porque os scripts do Laravel precisam do código da
# aplicação, que ainda não foi copiado; `--no-autoloader` porque o
# autoloader precisa enxergar as classes da aplicação para ser otimizado.
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

# Agora o código, e então o autoloader otimizado.
#
# O dump acontece AQUI, e não na imagem final: o Composer existe neste
# estágio e não na outra, que é uma imagem de PHP-FPM enxuta. Fazê-lo lá
# exigiria instalar o Composer numa imagem que nunca mais precisaria dele.
COPY backend/ ./

# `--optimize` monta o classmap completo, dispensando a busca por arquivo a
# cada classe carregada. Sem `--classmap-authoritative`: ele faz o
# autoloader recusar qualquer classe fora do mapa, o que quebra pacotes que
# geram classe em tempo de execução — ganho marginal por um risco real.
#
# `--no-scripts` porque `package:discover` boota a aplicação, e isso exige
# as extensões PHP e as variáveis de ambiente que só existem no container
# final. A descoberta de pacotes acontece no entrypoint.
RUN composer dump-autoload --no-dev --optimize --no-scripts --no-interaction

# ---------------------------------------------------------------------------
# Imagem da aplicação
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app

# As extensões necessárias à aplicação, e só elas:
#   pdo_pgsql — PostgreSQL
#   intl      — comparação e formatação sensíveis a locale
#   opcache   — compila o bytecode uma vez em vez de a cada requisição
#   zip       — usado pelo Composer
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev \
    && apk add --no-cache postgresql-libs icu-libs libzip fcgi \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl opcache zip \
    && apk del .build-deps

# Opcache ligado. Sem isto o PHP recompila cada arquivo a cada requisição,
# o que num framework do tamanho do Laravel é a diferença mais visível de
# desempenho que uma configuração isolada consegue produzir.
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /var/www/html

# A aplicação já vem do estágio anterior com as dependências instaladas e o
# autoloader otimizado — esta imagem não precisa do Composer.
COPY --from=vendor --chown=www-data:www-data /app ./

RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# O PHP-FPM roda como www-data; rodar como root daria ao processo que
# atende requisições mais poder do que ele precisa.
USER www-data

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
