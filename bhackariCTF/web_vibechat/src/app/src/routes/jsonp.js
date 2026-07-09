function isSafeCallback(value) {
  return /^[a-zA-Z][0-9a-zA-Z\\.]*$/.test(value);
}

function mountJsonpRoute(app) {
  app.get('/jsonp', (req, res) => {
    const callback = String(req.query.callback || 'showSettings');

    let body = `${callback}();`;

    if (!isSafeCallback(callback))
      body = `error: 'Invalid callback requested. ${callback}'`;

    res.type('application/javascript').send(body);
  });
}

module.exports = { mountJsonpRoute };
