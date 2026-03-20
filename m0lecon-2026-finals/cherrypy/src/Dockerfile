FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc \
    libc6-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY src/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt

COPY flag.txt /flag.txt
COPY src/ /app/

RUN gcc -o /readflag /app/readflag.c \
    && chmod 4755 /readflag \
    && rm /app/readflag.c

RUN RAND_USER=$(head -c 8 /dev/urandom | base64 | tr -dc 'a-zA-Z0-9' | head -c 12) \
    && RAND_PASS=$(head -c 16 /dev/urandom | base64 | tr -dc 'a-zA-Z0-9' | head -c 24) \
    && echo "${RAND_USER}:${RAND_PASS}" > /app/static_but_private/creds.txt

RUN chmod 400 /flag.txt

RUN useradd -m -s /bin/bash ctf \
    && mkdir -p /app/sessions \
    && chown -R ctf:ctf /app/sessions && chown -R ctf:ctf /app/static

USER ctf

EXPOSE 1337

ENV HOST=0.0.0.0
ENV PORT=1337

CMD ["python", "app.py"]
