// Tests de replay : un dossier par bug (<pr>/), lancés contre la stack de bench/env
// Les specs *.bo.spec.js utilisent la session BO créée par auth.setup.js
const { defineConfig } = require('@playwright/test');
const PORT = process.env.PS_PORT || 8081;
// Un bug = <pr>/replay*.spec.js (visible par l'agent en B/C/D) + <pr>/oracle*.spec.js (CACHÉ à l'agent, juge l'évaluation)
module.exports = defineConfig({
  testDir: '.',
  timeout: 60_000,
  retries: 0,
  reporter: [['list'], ['json', { outputFile: `results-${PORT}.json` }]],
  outputDir: `test-results-${PORT}`,
  use: {
    baseURL: `http://localhost:${PORT}`,
    locale: 'fr-FR',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'setup', testMatch: 'auth.setup.js' },
    { name: 'fo', testMatch: /\/(replay\w*|oracle\w*)\.spec\.js$/ },
    { name: 'bo', testMatch: /\/(replay\w*|oracle\w*)\.bo\.spec\.js$/, dependencies: ['setup'], use: { storageState: `.auth/bo-${PORT}.json` } },
  ],
});
