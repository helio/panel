#!/bin/bash
#
# Installs & Sets up necessary things for the panel to run
#
docker-compose exec panel composer install --no-scripts
docker-compose exec panel php vendor/bin/doctrine orm:schema-tool:update --force
