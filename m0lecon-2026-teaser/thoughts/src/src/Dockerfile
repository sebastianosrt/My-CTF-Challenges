FROM node:24-alpine

WORKDIR /app

COPY package.json ./

RUN npm install && \
    apk add --no-cache curl netcat-openbsd iputils

COPY . .

EXPOSE 3000

CMD ["node", "main.js"]
