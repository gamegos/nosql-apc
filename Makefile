dockerfile = ci/test/php$(PHP_VERSION)/Dockerfile
image      = apc-test:php$(PHP_VERSION)

workdir = ${shell pwd}
uid     = ${shell id -u}

test-install: check-PHP_VERSION
	docker build -t $(image) --build-arg uid=$(uid) -f $(dockerfile) .
	docker run --rm -v $(workdir):/app -e GITHUB_TOKEN $(image) composer-install

test-run: check-PHP_VERSION
ifdef CODE_COVERAGE
	docker run --rm -v $(workdir):/app $(image) /app/vendor/bin/phpunit --coverage-clover clover.xml
else
	docker run --rm -v $(workdir):/app $(image) /app/vendor/bin/phpunit
endif

check-%:
	${if $($(*)), , ${error $(*) undefined}}


# Shortcuts
test-php55: PHP_VERSION=5.5
test-php55:
	make --no-print-directory test-install
	make --no-print-directory test-run

test-php56: PHP_VERSION=5.6
test-php56:
	make --no-print-directory test-install
	make --no-print-directory test-run

test-php70: PHP_VERSION=7.0
test-php70:
	make --no-print-directory test-install
	make --no-print-directory test-run

test-php71: PHP_VERSION=7.1
test-php71:
	make --no-print-directory test-install
	make --no-print-directory test-run

test-php72: PHP_VERSION=7.2
test-php72:
	make --no-print-directory test-install
	make --no-print-directory test-run

test-php73: PHP_VERSION=7.3
test-php73:
	make --no-print-directory test-install
	make --no-print-directory test-run
