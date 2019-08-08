# Panel Prototype

This is a prototype of a user panel including an API.
Detailed documentation will follow, for now it's just nerdnotes.

# ENV Variables etc.
You can configure a lot of stuff through ENV-Variables. Here's how.

## Google
This panel makes calls to our backends via google IAP. Therefore, you have to set your service account's credentials.

### `googleauth.json`
If you need to change the user-config for Google auth, just set the following two env variables.
```bash
GOOGLE_AUTH_USER_ID=demo-542.googleusercontent.com
GOOGLE_AUTH_JSON_PATH=~/cnf/googleauth.json
```

## Grafana
To access Grafana, do as follows.
### `dashboard.json`
If you need to edit the grafana dashboard config, click "share" in your desired dashboard and use your browser's inspect tool to catch the request to `/api/snapshots`.
 
You want to copy the whole body into your json file 

You can place the file anywhere, just make sure to set the following ENV-Variable to the desired path:
```bash
DASHBOARD_CONFIG_JSON=~/cnf/dashboard.json
```

### API Key
the Barier-Token must be set in this varialbe
```bash
GRAFANA_API_KEY=apikey
```
**WARNING** Please note, that the key will be sent in `X-Grafana-Authorization`, not `Authorization` which is overwritten by Googles IAP. 

## Zapier

Zapier captures a few things, if you want to change the hook, here's how:
```bash 
ZAPIER_HOOK_URL=/hooks/catch/1234/blahd3d/
```

## Slack

Slack Webhook for Admin Notifications. Activates on PROD if the following ENV-Variable is set:
```bash 
SLACK_WEBHOOK=dblasdjb/absdljbadso/34jb3lj
```

## Varia
There are a lot more variables that you can set, they are pretty obiously named...
```bash
SCRIPT_HASH=sha1 of the setup script
SCRIPT_HASH_FILE=File where to find the hash (takes precedence over the above)
JWT_SECRET=random secret for jwt etc.
SITE_ENV
DB_USERNAME
DB_NAME
DB_HOST
DB_PASSWORD
```
