import js from '@eslint/js';
import jasmine from 'eslint-plugin-jasmine';
import jsdoc from 'eslint-plugin-jsdoc';
import globals from 'globals';

export default [
  { ignores: ['node_modules/**', 'coverage/**', 'types/**'] },
  js.configs.recommended,
  {
    files: ['**/*.{js,mjs}'],
    plugins: { jsdoc },
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: { ...globals.node, ...globals.es2021 },
    },
    rules: {
      complexity: ['warn', { max: 10 }],
      'max-lines': ['warn', { max: 300 }],
      'max-depth': ['warn', { max: 4 }],
      indent: ['error', 2, { SwitchCase: 1 }],
      'linebreak-style': ['error', 'unix'],
      quotes: ['error', 'single', { avoidEscape: true }],
      semi: ['error', 'always'],
      'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      eqeqeq: ['error', 'always'],
      'no-var': 'error',
      'prefer-const': 'error',
      'no-empty-function': 'error',
      'jsdoc/require-jsdoc': ['error', {
        require: { ClassDeclaration: true, MethodDefinition: true, FunctionDeclaration: true },
        publicOnly: true,
      }],
      'jsdoc/require-param': 'error',
      'jsdoc/require-param-description': 'error',
      'jsdoc/require-returns': 'error',
      'jsdoc/require-returns-description': 'error',
      'jsdoc/require-description': 'error',
    },
  },
  {
    // Specs: relax JSDoc rules and enable Jasmine globals
    files: ['specs/**/*.{js,mjs}'],
    plugins: { jasmine },
    languageOptions: { globals: { ...globals.jasmine } },
    rules: {
      'jasmine/no-focused-tests': 'error',
      'jasmine/no-disabled-tests': 'warn',
      'jsdoc/require-jsdoc': 'off',
      'jsdoc/require-param': 'off',
      'jsdoc/require-param-description': 'off',
      'jsdoc/require-returns': 'off',
      'jsdoc/require-returns-description': 'off',
      'jsdoc/require-description': 'off',
    },
  },
];
