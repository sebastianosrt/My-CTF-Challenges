FROM alpine:3.22.2

ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true

WORKDIR /usr/app
COPY ./src/package.json .

# Install system packages and npm dependencies
RUN apk add --update --no-cache nodejs npm socat chromium-chromedriver su-exec ca-certificates nss-tools curl wget && \
    npm install

COPY --chown=root:root --chmod=500 ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY ./src/ .


CMD ["/bin/sh", "/entrypoint.sh"]
