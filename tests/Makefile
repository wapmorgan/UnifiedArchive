build:
	docker build --tag php:ua tests/

test:
	docker run --rm --interactive --tty --volume $(PWD):/app -w /app -e XDEBUG_MODE=coverage php:ua ./vendor/bin/phpunit --coverage-text

run:
	docker run --rm --interactive --tty --volume $(PWD):/app -w /app php:ua bash