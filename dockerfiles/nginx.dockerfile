FROM nginx:stable-alpine

ARG UID=1000
ARG GID=1000

ENV UID=${UID}
ENV GID=${GID}

# Cria diretório para os logs
RUN mkdir -p /var/log/nginx && \
    chown -R nginx:nginx /var/log/nginx && \
    chmod -R 755 /var/log/nginx

# Remove o grupo dialout (não utilizado)
RUN delgroup dialout 2>/dev/null || true

# Cria grupo e usuário laravel
RUN addgroup -g ${GID} --system laravel && \
    adduser -G laravel --system -D -s /bin/sh -u ${UID} laravel

# Configura o usuário do Nginx
RUN sed -i "s/user  nginx/user laravel/g" /etc/nginx/nginx.conf

# Cria diretório para os arquivos do site
RUN mkdir -p /var/www/html/public && \
    chown -R laravel:laravel /var/www/html && \
    chmod -R 755 /var/www/html

# Copia a configuração do Nginx
COPY ./dockerfiles/nginx/default.conf /etc/nginx/conf.d/default.conf

# Expõe a porta 80
EXPOSE 80

# Comando para iniciar o Nginx
CMD ["nginx", "-g", "daemon off;"]