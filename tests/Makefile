# don't use git hash - it leads to a lot of excessive images
IMAGE?=php:ua

build:
	@echo Building $(IMAGE)
	docker build --tag $(IMAGE) tests/

test:
	docker run --rm --interactive --tty --volume $(PWD):/app -w /app -e XDEBUG_MODE=coverage --add-host=host.docker.internal:host-gateway $(IMAGE) ./vendor/bin/phpunit --coverage-text

run:
	docker run --rm --interactive --tty --volume $(PWD):/app -w /app --add-host=host.docker.internal:host-gateway $(IMAGE) bash
