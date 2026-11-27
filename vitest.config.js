import { defineConfig } from 'vitest/config';

// Unit tests for the plain JavaScript helpers. Page and component behaviour is covered by Playwright.
export default defineConfig({
    test: {
        include: ['resources/js/**/*.test.js'],
        environment: 'node',
    },
});
