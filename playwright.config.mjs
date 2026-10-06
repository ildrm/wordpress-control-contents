import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  use: { baseURL: 'http://localhost:8876', browserName: 'chromium', channel: process.env.UWCMP_BROWSER_CHANNEL || undefined, trace: 'retain-on-failure' },
  reporter: [['list'], ['json', { outputFile: 'build/e2e-results.json' }]],
});
