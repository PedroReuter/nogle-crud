# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Build do SPA
# ---------------------------------------------------------------------------
FROM node:22-alpine AS build

WORKDIR /app

# `npm ci` em vez de `npm install`: instala exatamente as versões do
# package-lock.json e falha se ele estiver fora de sincronia com o
# package.json — o que torna o build reproduzível.
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# Servidor
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

# O Nginx serve o SPA e encaminha a API ao PHP-FPM na MESMA ORIGEM.
#
# É a decisão de topologia que dispensa CORS por completo e permite que o
# cookie de sessão seja same-site: do ponto de vista do navegador existe um
# único servidor, em que `/` é a interface e `/api` é a API.
# A zona do limite de requisições precisa ser declarada no contexto `http`,
# que é o diretório conf.d — dentro de um bloco `server` o Nginx recusa a
# diretiva e se recusa a iniciar.
COPY docker/nginx/rate-limit.conf /etc/nginx/conf.d/00-rate-limit.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Apenas o resultado do build entra na imagem final: nem node_modules, nem
# código-fonte, nem o próprio Node.
COPY --from=build /app/dist /usr/share/nginx/html

# Esta imagem não carrega o código do Laravel, e não precisa: o `fastcgi_param
# SCRIPT_FILENAME` é um caminho absoluto resolvido pelo container do PHP, não
# por este. O Nginx só serve os arquivos do SPA e repassa o restante.

EXPOSE 80
