FROM alpine:latest

RUN apk add --no-cache \
     autoconf automake libtool m4 pkgconfig \
     pcre2-dev nettle-dev bzip2 brotli-dev libdeflate-dev \
     libunwind-dev zlib-dev zstd-dev \
     php83-fpm git make build-base \
     linux-headers

WORKDIR /app
ARG APP_REPO
RUN git clone "$APP_REPO"

RUN cd /app/lighttpd1.4 \
 && ./autogen.sh \
 && ./configure --disable-dependency-tracking \
 && make "-j$(nproc)" install

RUN adduser -D http

COPY proxy.conf /app/lighttpd.conf

CMD ["lighttpd", "-f", "/app/lighttpd.conf", "-D"]