export default {
    testEnvironment: 'node',
    testMatch: ['<rootDir>/ts-tests/**/*.test.ts'],
    transform: {
        '^.+\\.ts$': [
            'babel-jest',
            {
                babelrc: false,
                configFile: false,
                presets: [
                    ['@babel/preset-env', { targets: { node: 'current' } }],
                    '@babel/preset-typescript',
                ],
            },
        ],
    },
};
