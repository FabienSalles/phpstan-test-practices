PHP ?= 8.4
DEPS ?= highest
IMAGE = phpstan-test-practices:$(PHP)
DOCKER = docker run --rm -v "$(CURDIR)":/app -v phpstan-test-practices-composer:/root/.composer/cache $(IMAGE)

.PHONY: image install ci test analyse

image:
	docker build --build-arg PHP_VERSION=$(PHP) -t $(IMAGE) docker/

install: image
	$(DOCKER) composer update --no-interaction --no-progress $(if $(filter lowest,$(DEPS)),--prefer-lowest)

ci: install
	$(DOCKER) composer ci

test:
	$(DOCKER) composer test

analyse:
	$(DOCKER) composer analyse
