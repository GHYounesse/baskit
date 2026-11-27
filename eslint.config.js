import js from '@eslint/js';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';

export default [
    { ignores: ['vendor/**', 'node_modules/**', 'public/**', 'storage/**', 'playwright-report/**', 'test-results/**', 'e2e/**', '.docker-local/**'] },
    js.configs.recommended,
    {
        files: ['resources/js/**/*.{js,jsx}'],
        plugins: { react, 'react-hooks': reactHooks },
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            parserOptions: { ecmaFeatures: { jsx: true } },
            // route() is Ziggy's global; axios is put on window in bootstrap.js.
            globals: { ...globals.browser, route: 'readonly' },
        },
        settings: { react: { version: 'detect' } },
        rules: {
            ...react.configs.recommended.rules,
            ...react.configs['jsx-runtime'].rules,
            ...reactHooks.configs.recommended.rules,
            // Plain JSX without TypeScript or PropTypes in this project.
            'react/prop-types': 'off',
            // An apostrophe in JSX text is fine.
            'react/no-unescaped-entities': 'off',
            // Newer, stricter hooks rules: shown as warnings so older components can be tidied over time.
            'react-hooks/set-state-in-effect': 'warn',
            'no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_', caughtErrors: 'none' }],
        },
    },
];
