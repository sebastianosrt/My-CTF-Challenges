const JSON_SCRIPT_ESCAPE_MAP = {
  '<': '\\u003c',
  '>': '\\u003e',
  '&': '\\u0026',
  '\u2028': '\\u2028',
  '\u2029': '\\u2029'
};

function serializeJsonScript(value) {
  return JSON.stringify(value).replace(
    /[<>&\u2028\u2029]/g,
    (char) => JSON_SCRIPT_ESCAPE_MAP[char]
  );
}

module.exports = { serializeJsonScript };
