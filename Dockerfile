FROM php:5.6-alpine

WORKDIR /app

COPY ./app /app

RUN apk add --no-cache tzdata && \
    cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime && \
    echo "Asia/Shanghai" > /etc/timezone && \
    apk del tzdata

RUN echo "*/5 * * * * php /app/aliyun-cdt-check.php > /dev/null 2>&1" > /etc/crontabs/root && \
    echo "1 8 * * * php /app/dailyjob.php > /dev/null 2>&1" >> /etc/crontabs/root

CMD ["crond", "-f"]
