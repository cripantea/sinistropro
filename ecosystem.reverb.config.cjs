module.exports = {
  apps: [
    {
      name: 'reverb',
      cwd: __dirname,
      script: 'artisan',
      interpreter: 'php',
      args: 'reverb:start',
    },
  ],
};
