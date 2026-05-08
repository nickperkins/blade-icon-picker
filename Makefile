.PHONY: help install test build clean ci

help:
	@echo "install   — composer + npm install + build"
	@echo "test      — run Pest test suite"
	@echo "build     — bundle JS + CSS via esbuild"
	@echo "clean     — remove vendor, node_modules, dist, lock files"
	@echo "ci        — install + test (full pipeline)"

install:
	composer install
	npm install
	npm run build

test:
	vendor/bin/pest

build:
	npm run build

clean:
	rm -rf vendor node_modules resources/dist composer.lock package-lock.json

ci: install test
