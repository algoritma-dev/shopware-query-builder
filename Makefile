qa: phpstan rector-check cs-check phpunit
qa-fix: phpstan rector-fix cs-fix phpunit

BUNDLE_PATH=custom/static-plugins/ShopwareQueryBuilder
DOCKER=docker compose exec --workdir=/var/www/html/$(BUNDLE_PATH) shop

phpstan:
	$(DOCKER) vendor/bin/phpstan analyse

rector-check:
	$(DOCKER) vendor/bin/rector process --dry-run

cs-check:
	$(DOCKER) vendor/bin/php-cs-fixer fix --dry-run --diff

phpunit:
	$(DOCKER) vendor/bin/phpunit

rector-fix:
	$(DOCKER) vendor/bin/rector process

cs-fix:
	$(DOCKER) vendor/bin/php-cs-fixer fix

phony: qa qa-fix phpstan rector-check cs-check phpunit rector-fix cs-fix