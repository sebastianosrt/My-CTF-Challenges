FROM debian:bookworm-slim

RUN apt -y update \
 && apt -y upgrade \
 && apt -y install autoconf automake libtool m4 pkg-config libpcre2-dev nettle-dev bzip2 libbrotli-dev libdeflate-dev libunwind-dev zlib1g-dev libzstd-dev php-fpm git make build-essential

WORKDIR /app
ARG APP_REPO
RUN git clone "$APP_REPO"

ARG APP_VERSION
RUN cd /app/lighttpd1.4 \
 && git checkout "$APP_VERSION" \
 && ./autogen.sh \
 && ./configure --disable-dependency-tracking \
 && make "-j$(nproc)" install

RUN useradd http

COPY proxy.conf /app/lighttpd.conf

CMD ["lighttpd", "-f", "/app/lighttpd.conf", "-D"]