module.exports = {
  apps: [
    {
      name: 'whatsapp-service',
      cwd: __dirname,
      script: 'server.js',
      env: {
        NODE_ENV: 'production',
      },
    },
  ],
};
