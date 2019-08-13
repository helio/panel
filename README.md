# Panel Prototype

This is a prototype of a user panel including an API.
Detailed documentation will follow, for now it's just nerdnotes.

## Dev env setup

* Requires [docker](https://docker.com) and [docker compose](https://docs.docker.com/compose/install/).
* Use the [EditorConfig](https://editorconfig.org/#download) plugin of the editor of your choice for no indenting pain.

## Installation

1. Copy `.panel.env.dist` to `.panel.env` and adjust the settings to contain the right values.
2. Ensure you have a valid  `./cnf/google-auth.json`
3. Start docker services, install dependencies and setup the DB
```bash
$ ./scripts/docker/up.sh
$ ./scripts/docker/init.sh
```

If the `Dockerfile` got changed, rebuild it using `./scripts/docker/build.sh`.

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

### Slack

Slack Webhook for Admin Notifications. Activates on PROD if the following ENV-Variable is set:
```bash 
SLACK_WEBHOOK=dblasdjb/absdljbadso/34jb3lj
```
