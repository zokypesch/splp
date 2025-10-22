{
  "preset": "ts-jest",
  "testEnvironment": "node",
  "roots": ["<rootDir>/src"],
  "testMatch": [
    "**/__tests__/**/*.ts",
    "**/?(*.)+(spec|test).ts"
  ],
  "transform": {
    "^.+\\.ts$": "ts-jest"
  },
  "collectCoverageFrom": [
    "src/**/*.ts",
    "!src/**/*.d.ts",
    "!src/examples/**",
    "!src/**/__tests__/**"
  ],
  "coverageDirectory": "coverage",
  "coverageReporters": [
    "text",
    "lcov",
    "html"
  ],
  "setupFilesAfterEnv": ["<rootDir>/src/__tests__/setup.ts"],
  "testTimeout": 30000,
  "moduleNameMapping": {
    "^@/(.*)$": "<rootDir>/src/$1"
  }
}
