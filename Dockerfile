FROM php:8.1-cli

RUN apt-get update && apt-get install -y default-mysql-client && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql mysqli

COPY . /app/

WORKDIR /app/

RUN chmod +x start.sh

RUN echo "<?php phpinfo(); ?>" > /app/phpinfo.php

EXPOSE 8080

CMD ["./start.sh"]
