#!/bin/sh

s=$(head -c 24 /dev/urandom | base64 | tr -d '\n' | tr '+/' '-_' | tr -d '=' | tr 'A-Z' 'a-z')

echo "SECRET=${s,,}" > .env

docker compose up --build