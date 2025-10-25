# m0leCon CTF 2026 Teaser

## [web] SecureTexBin (18 solves)

Welcome to the world’s most secure textbin. No JavaScript. No CSS. No images. Just plain text.

Author: Sebastiano Sartor <@sebsrt>

## Overview

The challenge is a simple app composed by a fronted service that uses a backend API to store text files.
The goal is to execute XSS on a bot and read the flag from the localStorage.

## Solution

The frontend service runs **Node.js 20.18.1** and is vulnerable to **CVE-2025-22150**: undici generated multipart boundaries using `Math.random()` so they are predictable, enabling multipart form-data smuggling.

By observing file IDs produced by `uploadText` in the frontend service you can recover the `Math.random()` state and predict the next boundary undici will use. The boundary format from the [fix commit](https://github.com/nodejs/undici/commit/711e20772764c29f6622ddc937c63b6eefdf07d0) is:

```
----formdata-undici-0${`${Math.floor(Math.random() * 1e11)}`.padStart(11, '0')}
```

With a predicted boundary an attacker can smuggle arbitrary **Content-Type** values for uploaded files to the backend. Chaining this with a known Firefox bug can be used to bypass CSP and achieve XSS.

To bypass the CSP and achieve XSS it's possible to exploit a Firefox [bug](https://bugzilla.mozilla.org/show_bug.cgi?id=1864434).

## Exploit

[here](m0lecon-2026-teaser/securetextbin/solution)