import { fileURLToPath } from 'node:url';
import js from '@eslint/js';
import prettierConfig from 'eslint-config-prettier';
import reactHooks from 'eslint-plugin-react-hooks';
import tseslint from 'typescript-eslint';

const projectRoot = fileURLToPath(new URL('.', import.meta.url));

export default tseslint.config(
    { ignores: ['dist/**', 'node_modules/**'] },
    js.configs.recommended,
    ...tseslint.configs.recommendedTypeChecked,
    reactHooks.configs.flat['recommended-latest'],
    {
        languageOptions: {
            parserOptions: {
                projectService: {
                    allowDefaultProject: ['eslint.config.js', 'vite.config.ts'],
                },
                tsconfigRootDir: projectRoot,
            },
        },
    },
    prettierConfig,
);
