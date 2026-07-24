import js from '@eslint/js';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['node_modules', 'public', 'vendor', 'storage'] },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            ecmaVersion: 2022,
            globals: globals.browser,
        },
        plugins: {
            'react-hooks': reactHooks,
            'react-refresh': reactRefresh,
        },
        rules: {
            ...reactHooks.configs.recommended.rules,
            'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
            '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
            // Pre-existing debt as of Phase 13: many form components cast loosely-typed
            // values through `any` and EditableGrid-style panels seed local state from a
            // query result inside an effect. Both are real cleanup targets but not
            // correctness bugs, so they're warnings rather than CI-blocking errors.
            '@typescript-eslint/no-explicit-any': 'warn',
            'react-hooks/set-state-in-effect': 'warn',
        },
    },
);
