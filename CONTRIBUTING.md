# Contributing

Thanks for considering contributing to this extension! Since it is an open source
product, its successful further development depends largely on improving and
optimizing it together.

The development of this extension follows the official
[TYPO3 coding standards](https://github.com/TYPO3/coding-standards). To ensure the
stability and cleanliness of the code, various code quality tools are used and most
components are covered with test cases. For continuous integration, we use GitHub Actions.

## Preparation

```bash
# Clone repository
git clone https://github.com/CPS-IT/mailqueue.git
cd mailqueue

# Install dependencies
composer install
```

## Run linters

```bash
# All linters
composer lint

# Specific linters
composer lint:composer
composer lint:editorconfig
composer lint:php
composer lint:typoscript

# Fix all CGL issues
composer fix

# Fix specific CGL issues
composer fix:composer
composer fix:editorconfig
composer fix:php
composer fix:typoscript
```

## Run static code analysis

```bash
# All static code analyzers
composer sca

# Specific static code analyzers
composer sca:php
```

## Run tests

```bash
# All tests
composer test

# Specific tests
composer test:functional
composer test:unit

# All tests with code coverage
composer test:coverage

# Specific tests with code coverage
composer test:coverage:functional
composer test:coverage:unit

# Merge code coverage of all test suites
composer test:coverage:merge
```

### Test reports

Code coverage reports are written to `.Build/log/coverage`. You can open the
last merged HTML report like follows:

```bash
open .Build/coverage/html/_merged/index.html
```

💡 Make sure to merge coverage reports as written above.

## Submit a pull request

Once you have finished your work, please **submit a pull request** and describe
what you've done. Ideally, your PR references an issue describing the problem
you're trying to solve.

All described code quality tools are automatically executed on each pull request
for all currently supported PHP versions and TYPO3 versions. Take a look at the
appropriate [workflows](.github/workflows) to get a detailed overview.
