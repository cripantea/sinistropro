const config = require('./config');

module.exports = function requireInternalSecret(req, res, next) {
  const provided = req.get('X-Internal-Secret');
  if (!config.internalSecret || provided !== config.internalSecret) {
    return res.status(401).json({ error: 'unauthorized' });
  }
  next();
};
