FROM node:20.18.1-slim

RUN apt-get update && apt-get install -y \
    wget \
    xz-utils \
    libgtk-3-0 \
    libdbus-glib-1-2 \
    libxt6 \
    libxcomposite1 \
    libxdamage1 \
    libxrandr2 \
    libasound2 \
    libpangocairo-1.0-0 \
    libatk1.0-0 \
    libcairo-gobject2 \
    libgdk-pixbuf2.0-0 \
    libx11-xcb1 \
    libxcb1 \
    libxss1 \
    libnss3 \
    libdrm2

WORKDIR /app

COPY . .

RUN npm install && npx puppeteer browsers install firefox@stable

EXPOSE 1337

CMD ["npm", "start"]