# Panel Prototype

This is a prototype of a user panel including an API.
Detailed documentation will follow, for now it's just nerdnotes.

## Dev env setup

* Requires [docker](https://docker.com) and [docker compose](https://docs.docker.com/compose/install/).
* Use the [EditorConfig](https://editorconfig.org/#download) plugin of the editor of your choice for no indenting pain.

## Installation

### Pontsun

Uses [liip/pontsun](https://github.com/liip/pontsun) for the docker-compose setup to have a decent local domain name and TLS certificates.

```bash
$ cd ../
$ git clone git@github.com:liip/pontsun.git
```

Follow the README and the mentioned [Resources](https://github.com/liip/pontsun#resources) for the installation.

**Important:**
  - For **dnsmasq**, make sure you use `helio.test` everywhere they use `docker.test`.
  - You can use the following `.env` instead of copying the mentioned `.env.example`.

```
### Project settings
COMPOSE_FILE=docker-compose.yml:docker-compose.ssh-agent.yml

COMPOSE_PROJECT_NAME=pontsun
PROJECT_NAME=helio
PROJECT_EXTENSION=test
PROJECT_DOMAIN=helio.test

# Used ports for traefik
PONTSUN_HTTP_PORT=80
PONTSUN_HTTPS_PORT=443

# Name of the docker network used for pontsun
PONTSUN_NETWORK=pontsun

### Images tags
PORTAINER_TAG=1.22.1
TRAEFIK_TAG=1.7.18-alpine
SSL_KEYGEN_TAG=1.0.0
``` 
 
Best is if you clone pontsun in the same parent directory as the panel.

### Panel

1. Copy `.panel.env.dist` to `.panel.env` and adjust the settings (`REPLACE_WITH_CORRECT_VALUE` - Ask your colleagues if you need credentials for DBs, Servers, Google Service account credentials, ...)
2. Start docker services
```bash
$ ./scripts/docker/up.sh
```
3. Install dependencies and setup the DB
```bash
$ ./scripts/docker/init.sh
```
4. install [node & npm](https://nodejs.org/) (`brew install npm` for macOS)
5. Run `npm install` to retrieve patternfly

Optionally: Ensure you have a valid  `./cnf/google-auth.json`

Open [https://panel.helio.test](https://panel.helio.test)

### Tips & Tricks

  - Use `./scripts/docker/shell /bin/sh` for access to the panel's shell.
  - Check container logs for confirmation link: `docker-compose logs -f panel`

## Code style

This project's code style is formatted using [php-cs-fixer](https://cs.symfony.com/). Invoke it using:

```bash
$ ./scripts/docker/shell.sh vendor/bin/php-cs-fixer fix
```

## Configuration
You can configure a lot of stuff using environment variables. Available env variables are listed in `.panel.env.dist`.

## External services

### Google
This panel makes calls to our backends via google IAP. Therefore, you have to set your service account's credentials in the env file and in `./cnf/google-auth.json`.

### Grafana
Grafana is used for visualizing statistics. 

#### `dashboard.json`
If you need to edit the grafana dashboard config, click "share" in your desired dashboard and use your browser's inspect tool to catch the request to `/api/snapshots`.
 
Copy the whole body into a new file called `dashboard.json`. 

You can place the file anywhere, just make sure to set the following ENV-Variable to the desired path:
```bash
DASHBOARD_CONFIG_JSON=~/cnf/dashboard.json
```

#### API Key
The Bearer-Token must be set in this varialbe
```bash
GRAFANA_API_KEY=apikey
```
**WARNING** Please note, that the key will be sent in `X-Grafana-Authorization`, not `Authorization` which is overwritten by Googles IAP. 

### Zapier

Zapier captures a few things, if you want to change the hook, here's how:
```bash 
ZAPIER_HOOK_URL=/hooks/catch/1234/blahd3d/
```

### Koala Intercom

Koala uses Intercom, to activate the Event Notification set:
```bash 
INTERCOM_API_KEY=ZESECRETAPIKEY
```


### Slack

Slack Webhook for Admin Notifications and Alerts. Activates if the following ENV-Variable is set:
```bash 
SLACK_WEBHOOK=dblasdjb/absdljbadso/34jb3lj
SLACK_WEBHOOK_ALERT=dblasdjb/absdljbadbbo/34jb3tt
```
