/*
Like `JSON.stringify`, but handles

- cyclical references (`obj.self = obj`)
- repeated references (`[value, value]`)
- `undefined`, `Infinity`, `NaN`, `-0`
- regular expressions
- dates
- `Map` and `Set`
- `BigInt`
- `ArrayBuffer` and Typed Arrays
- `URL` and `URLSearchParams`
- `Temporal`
- custom types via replacers, reducers and revivers

Supported beyond JSON:
  undefined, NaN, Infinity, -Infinity, -0, BigInt,
  Date, RegExp, Map, Set, Error,
  ArrayBuffer, TypedArrays, DataView,
  URL, URLSearchParams, Temporal.*

Custom types:
  stringify(val, { reducers: { Name: { test, reduce } } })
  parse(str, { revivers: { Name: fn } })
*/

const TYPED_ARRAYS = Object.create(null);
['Int8Array', 'Uint8Array', 'Uint8ClampedArray', 'Int16Array', 'Uint16Array',
  'Int32Array', 'Uint32Array', 'Float32Array', 'Float64Array',
  'BigInt64Array', 'BigUint64Array'].forEach(name => {
    if (typeof globalThis[name] !== 'undefined') TYPED_ARRAYS[name.toLowerCase()] = globalThis[name];
  });

const ERROR_TYPES = Object.assign(Object.create(null), {
  Error,
  TypeError,
  RangeError,
  SyntaxError,
  ReferenceError,
  URIError,
  EvalError
});

function bufToBase64(uint8) {
  if (typeof Buffer !== 'undefined') return Buffer.from(uint8).toString('base64');
  let s = ''; for (const b of uint8) s += String.fromCharCode(b);
  return btoa(s);
}

function base64ToBuf(str) {
  let bytes;
  if (typeof Buffer !== 'undefined') bytes = Buffer.from(str, 'base64');
  else { const bin = atob(str); bytes = new Uint8Array(bin.length); for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i); }
  const ab = new ArrayBuffer(bytes.length);
  new Uint8Array(ab).set(bytes);
  return new Uint8Array(ab);
}

function tag(type, value, id) {
  const o = {};
  o.$t = type;
  if (id !== undefined) o.$id = id;
  if (value !== undefined) o.$v = value;
  return o;
}

function setOwnProperty(obj, key, value) {
  Object.defineProperty(obj, key, {
    value,
    enumerable: true,
    configurable: true,
    writable: true,
  });
}

function isPlainObject(v) {
  if (typeof v !== 'object' || v === null || Array.isArray(v)) return false;
  const p = Object.getPrototypeOf(v);
  return p === null || p === Object.prototype;
}

function isBadKey(key) {
  const bad = new Set(['$id', '__proto__', 'constructor', 'prototype']);
  return bad.has(key);
}

function countRefs(value, counts) {
  if (value == null) return;
  if (typeof value === 'bigint') return;
  if (typeof value !== 'object') return;
  if (counts.has(value)) { counts.set(value, counts.get(value) + 1); return; }
  counts.set(value, 1);

  if (Array.isArray(value)) { for (const v of value) countRefs(v, counts); }
  else if (value instanceof Map) { for (const [k, v] of value) { countRefs(k, counts); countRefs(v, counts); } }
  else if (value instanceof Set) { for (const v of value) countRefs(v, counts); }
  else if (value instanceof Date || value instanceof RegExp || value instanceof Error) { /* leaf */ }
  else if (typeof URL !== 'undefined' && value instanceof URL) { /* leaf */ }
  else if (typeof URLSearchParams !== 'undefined' && value instanceof URLSearchParams) { /* leaf */ }
  else if (value instanceof ArrayBuffer || ArrayBuffer.isView(value)) { /* leaf */ }
  else { for (const v of Object.values(value)) countRefs(v, counts); }
}

function serialize(value, state) {
  const { counts, refs, nextId, reducers } = state;

  for (const name of Object.keys(reducers)) {
    const reducer = reducers[name];
    if (!reducer || typeof reducer.test !== 'function' || typeof reducer.reduce !== 'function') continue;

    if (reducer.test(value)) {
      let id;
      if (typeof value === 'object' && value !== null && counts.get(value) > 1) {
        if (refs.has(value)) return { $ref: refs.get(value) };
        id = nextId.v++; refs.set(value, id);
      }
      return tag(name, serialize(reducer.reduce(value), state), id);
    }
  }

  // Primitives
  if (value === undefined) return tag('undef');
  if (value === null) return null;
  if (typeof value === 'boolean') return value;
  if (typeof value === 'string') return value;
  if (typeof value === 'bigint') return tag('bigint', value.toString());
  if (typeof value === 'symbol') return tag('undef');
  if (typeof value === 'function') return tag('undef');
  if (typeof value === 'number') {
    if (Number.isNaN(value)) return tag('nan');
    if (value === Infinity) return tag('inf');
    if (value === -Infinity) return tag('-inf');
    if (Object.is(value, -0)) return tag('-0');
    return value;
  }

  // Objects — ref tracking
  if (refs.has(value)) return { $ref: refs.get(value) };
  let id;
  if (counts.get(value) > 1) { id = nextId.v++; refs.set(value, id); }

  const s = v => serialize(v, state);

  // Date
  if (value instanceof Date) return tag('date', value.toISOString(), id);

  // RegExp
  if (value instanceof RegExp) return tag('regexp', [value.source, value.flags], id);

  // Error
  if (value instanceof Error) {
    const d = { name: value.constructor.name, message: value.message };
    if (value.stack) d.stack = value.stack;
    if (value.cause !== undefined) d.cause = s(value.cause);
    return tag('error', d, id);
  }

  // Map
  if (value instanceof Map) {
    return tag('map', [...value.entries()].map(([k, v]) => [s(k), s(v)]), id);
  }

  // Set
  if (value instanceof Set) {
    return tag('set', [...value].map(s), id);
  }

  // URL
  if (typeof URL !== 'undefined' && value instanceof URL) return tag('url', value.href, id);

  // URLSearchParams
  if (typeof URLSearchParams !== 'undefined' && value instanceof URLSearchParams) {
    return tag('urlparams', value.toString(), id);
  }

  // ArrayBuffer
  if (value instanceof ArrayBuffer) return tag('arraybuffer', bufToBase64(new Uint8Array(value)), id);

  // TypedArrays & DataView
  if (ArrayBuffer.isView(value)) {
    const name = value.constructor.name.toLowerCase();
    const bytes = new Uint8Array(value.buffer, value.byteOffset, value.byteLength);
    return tag(name, bufToBase64(bytes), id);
  }

  // Temporal
  if (typeof globalThis.Temporal !== 'undefined') {
    const T = globalThis.Temporal;
    for (const tn of ['Instant', 'PlainDate', 'PlainTime', 'PlainDateTime', 'ZonedDateTime', 'Duration', 'PlainYearMonth', 'PlainMonthDay']) {
      if (T[tn] && value instanceof T[tn]) return tag('temporal.' + tn, value.toString(), id);
    }
  }

  // Array
  if (Array.isArray(value)) {
    const arr = value.map(s);
    if (id !== undefined) return tag('array', arr, id);
    return arr;
  }

  // Plain object
  const result = {};
  if (id !== undefined) result.$id = id;
  for (const key of Object.keys(value)) {
    setOwnProperty(result, key, s(value[key]));
  }
  return result;
}

function resolve(value, state) {
  const { registry, revivers } = state;
  if (value === null || typeof value !== 'object') return value;

  if (Array.isArray(value)) {
    for (let i = 0; i < value.length; i++) value[i] = resolve(value[i], state);
    return value;
  }

  const $ref = value.$ref;
  if ($ref !== undefined) return registry.get($ref);

  const $t = value.$t;
  const $id = value.$id;
  const $v = value.$v;

  if ($t !== undefined) {
    if ($t === 'array') {
      const arr = Array.isArray($v) ? $v : [];
      if ($id !== undefined) registry.set($id, arr);
      for (let i = 0; i < arr.length; i++) arr[i] = resolve(arr[i], state);
      return arr;
    }
    if ($t === 'map') {
      const map = new Map();
      if ($id !== undefined) registry.set($id, map);
      if (Array.isArray($v)) for (const p of $v) map.set(resolve(p[0], state), resolve(p[1], state));
      return map;
    }
    if ($t === 'set') {
      const set = new Set();
      if ($id !== undefined) registry.set($id, set);
      if (Array.isArray($v)) for (const v of $v) set.add(resolve(v, state));
      return set;
    }

    const resolved = $v !== undefined ? resolve($v, state) : undefined;
    const revived = reviveTag($t, resolved, revivers);
    if ($id !== undefined) registry.set($id, revived);
    return revived;
  }

  if ($id !== undefined) registry.set($id, value);
  for (const key of Object.keys(value)) {
    setOwnProperty(value, key, resolve(value[key], state));
  }
  if ($id !== undefined) delete value.$id;
  return value;
}

function reviveTag(type, data, revivers) {
  if (
    typeof type === 'string' &&
    revivers &&
    Object.prototype.hasOwnProperty.call(revivers, type) &&
    typeof revivers[type] === 'function'
  ) {
    return revivers[type](data);
  }

  switch (type) {
    case 'undef': return undefined;
    case 'nan': return NaN;
    case 'inf': return Infinity;
    case '-inf': return -Infinity;
    case '-0': return -0;
    case 'bigint': return BigInt(data);
    case 'date': return new Date(data);
    case 'regexp': return new RegExp(data[0], data[1]);
    case 'url': return new URL(data);
    case 'urlparams': return new URLSearchParams(data);
    case 'arraybuffer': return base64ToBuf(data).buffer;
    case 'error': {
      const Ctor = (data && typeof data.name === 'string' && Object.prototype.hasOwnProperty.call(ERROR_TYPES, data.name))
        ? ERROR_TYPES[data.name]
        : Error;
      const err = new Ctor(data.message);
      if (data.stack) err.stack = data.stack;
      if (data.cause !== undefined) err.cause = data.cause;
      return err;
    }
    default: break;
  }

  // TypedArrays / DataView
  if (typeof type === 'string' && Object.prototype.hasOwnProperty.call(TYPED_ARRAYS, type)) {
    const bytes = base64ToBuf(data);
    const Ctor = TYPED_ARRAYS[type];
    return new Ctor(bytes.buffer, 0, bytes.byteLength / Ctor.BYTES_PER_ELEMENT);
  }
  if (type === 'dataview') {
    const bytes = base64ToBuf(data);
    return new DataView(bytes.buffer, 0, bytes.byteLength);
  }

  // Temporal
  if (type.startsWith('temporal.') && typeof globalThis.Temporal !== 'undefined') {
    const tn = type.slice(9); // e.g. "Instant"
    if (globalThis.Temporal[tn]) return globalThis.Temporal[tn].from(data);
  }

  return data; // unknown tag — pass through
}

function isHeader(arr, totalLen) {
  if (!Array.isArray(arr) || arr.length === 0) return false;
  if (totalLen !== undefined && arr.length !== totalLen - 1) return false;
  return arr.every(k =>
    typeof k === 'string' ||
    (Array.isArray(k) && k.length === 1)
  );
}

function headerKey(entry) {
  return Array.isArray(entry) ? entry[0] : entry;
}

function encode(obj) {
  if (typeof obj !== 'object' || obj === null || Array.isArray(obj)) return obj;
  const keys = Object.keys(obj);
  const isIdent = /^[a-zA-Z_$][a-zA-Z0-9_$]*$/;
  const header = keys.map(k => isIdent.test(k) ? k : [k]);
  return [header, ...keys.map(k => obj[k])];
}

function decode(raw) {
  if (!Array.isArray(raw) || raw.length < 2 || !isHeader(raw[0], raw.length)) return raw;
  const header = raw[0];
  const result = {};
  for (let i = 0; i < header.length; i++) {
    const key = headerKey(header[i]);
    const value = raw[i + 1];

    if (isBadKey(key)) continue
    if (isPlainObject(value) && typeof result[key] === 'object' && result[key] !== null) {
      for (const [k, v] of Object.entries(value)) {
        if (isBadKey(k)) continue
        setOwnProperty(result[key], k, v);
      }
    } else {
      setOwnProperty(result, key, value);
    }
  }
  return result;
}

function parseLiteral(str) {
  if (str.length > 10 * 1024 * 1024) throw new Error('Input too large');
  let i = 0;
  let depth = 0;
  const MAX_DEPTH = 128;

  function error(msg) { throw new SyntaxError(`${msg} at position ${i}`); }
  function skipWs() { while (i < str.length && /\s/.test(str[i])) i++; }

  function parseValue() {
    skipWs();
    if (i >= str.length) error('Unexpected end of input');
    const ch = str[i];
    if (ch === '"') return parseString();
    if (ch === '{') return parseObject();
    if (ch === '[') return parseArray();
    if (ch === '-' || (ch >= '0' && ch <= '9')) return parseNumber();
    if (str.startsWith('true', i)) { i += 4; return true; }
    if (str.startsWith('false', i)) { i += 5; return false; }
    if (str.startsWith('null', i)) { i += 4; return null; }
    error(`Unexpected character '${ch}'`);
  }

  function parseString() {
    i++;
    let result = '';
    while (i < str.length && str[i] !== '"') {
      if (str[i] === '\\') {
        i++;
        if (i >= str.length) error('Unexpected end of string escape');
        switch (str[i]) {
          case '"': result += '"'; break;
          case '\\': result += '\\'; break;
          case 'n': result += '\n'; break;
          case 'r': result += '\r'; break;
          case 't': result += '\t'; break;
          case '0': result += '\0'; break;
          default: result += str[i];
        }
      } else { result += str[i]; }
      i++;
    }
    if (i >= str.length) error('Unterminated string');
    i++;
    return result;
  }

  function parseNumber() {
    const start = i;
    if (str[i] === '-') i++;
    while (i < str.length && str[i] >= '0' && str[i] <= '9') i++;
    if (i < str.length && str[i] === '.') { i++; while (i < str.length && str[i] >= '0' && str[i] <= '9') i++; }
    if (i < str.length && (str[i] === 'e' || str[i] === 'E')) { i++; if (i < str.length && (str[i] === '+' || str[i] === '-')) i++; while (i < str.length && str[i] >= '0' && str[i] <= '9') i++; }
    return Number(str.slice(start, i));
  }

  function parseArray() {
    i++;
    const result = [];
    skipWs();
    if (str[i] === ']') { i++; return result; }
    while (true) {
      result.push(parseValue());
      skipWs();
      if (str[i] === ']') { i++; return result; }
      if (str[i] !== ',') error("Expected ',' or ']'");
      i++;
    }
  }

  function parseObject() {
    i++;
    const result = {};
    skipWs();
    if (str[i] === '}') { i++; return result; }
    while (true) {
      skipWs();
      const key = parseKey();
      skipWs();
      if (str[i] !== ':') error("Expected ':'");
      i++;
      const value = parseValue();
      setOwnProperty(result, key, value);
      skipWs();
      if (str[i] === '}') { i++; return result; }
      if (str[i] !== ',') error("Expected ',' or '}'");
      i++;
    }
  }

  function parseKey() {
    if (str[i] === '"') return parseString();
    if (str[i] === '[') {
      i++; skipWs();
      const key = parseValue();
      skipWs();
      if (str[i] !== ']') error("Expected ']' in computed key");
      i++;
      return String(key);
    }
    const start = i;
    while (i < str.length && /[a-zA-Z0-9_$]/.test(str[i])) i++;
    if (i === start) error('Expected object key');
    return str.slice(start, i);
  }

  const result = parseValue();
  skipWs();
  if (i < str.length) error('Unexpected trailing content');
  return result;
}

function stringifyLiteral(value) {
  if (value === null || value === undefined) return 'null';
  if (typeof value === 'boolean') return String(value);
  if (typeof value === 'number') {
    if (!isFinite(value)) return 'null';
    return Object.is(value, -0) ? '-0' : String(value);
  }
  if (typeof value === 'string') return escapeString(value);
  if (Array.isArray(value)) return '[' + value.map(stringifyLiteral).join(',') + ']';
  if (typeof value === 'object') {
    const entries = Object.keys(value).map(k => formatKey(k) + ':' + stringifyLiteral(value[k]));
    return '{' + entries.join(',') + '}';
  }
  return 'null';
}

function escapeString(s) {
  let out = '"';
  for (let i = 0; i < s.length; i++) {
    const ch = s[i];
    if (ch === '"') out += '\\"';
    else if (ch === '\\') out += '\\\\';
    else if (ch === '\n') out += '\\n';
    else if (ch === '\r') out += '\\r';
    else if (ch === '\t') out += '\\t';
    else if (ch === '\0') out += '\\0';
    else out += ch;
  }
  return out + '"';
}

function formatKey(key) {
  if (/^[a-zA-Z_$][a-zA-Z0-9_$]*$/.test(key)) return key;
  return '[' + escapeString(key) + ']';
}

function parse(str, options = {}) {
  const revivers =
    options && typeof options.revivers === 'object' && options.revivers !== null
      ? options.revivers
      : {};
  const compact = options && options.compact !== undefined ? options.compact : true;
  const raw = parseLiteral(str);
  const resolved = resolve(raw, { registry: new Map(), revivers });
  if (compact) return decode(resolved);
  return resolved;
}

function stringify(value, options = {}) {
  const reducers =
    options && typeof options.reducers === 'object' && options.reducers !== null
      ? options.reducers
      : {};
  const compact = options && options.compact !== undefined ? options.compact : true;
  const counts = new Map();
  countRefs(value, counts);
  let out = serialize(value, { counts, refs: new Map(), nextId: { v: 0 }, reducers });
  if (compact && isPlainObject(out) && out.$t === undefined && out.$id === undefined) {
    out = encode(out);
  }
  return stringifyLiteral(out);
}

module.exports = {
  parse, stringify,
  parseLiteral, stringifyLiteral,
  encode, decode, serialize, resolve,
  countRefs, isHeader, headerKey, isPlainObject,
};
