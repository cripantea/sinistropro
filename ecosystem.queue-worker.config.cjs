module.exports = {
  apps: [
    {
      name: 'queue-worker',
      cwd: __dirname,
      script: 'artisan',
      interpreter: 'php',
      args: 'queue:work --queue=default,automations,emails --tries=3 --sleep=3 --timeout=180 --max-time=3600',
    },
  ],
};
