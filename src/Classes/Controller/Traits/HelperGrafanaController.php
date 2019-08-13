<?php

namespace Helio\Panel\Controller\Traits;

use Exception;
use DateTime;
use DateInterval;
use GuzzleHttp\Exception\GuzzleException;
use Helio\Panel\Model\Instance;
use Helio\Panel\Utility\ServerUtility;
use Slim\Http\StatusCode;

/**
 * Trait HelperGrafanaController.
 *
 * @property Instance $instance
 */
trait HelperGrafanaController
{
    use HelperGoogleAuthenticatedController;

    /** @var DateTime */
    protected $start;

    /** @var DateTime */
    protected $end;

    /** @var int */
    protected $step;

    /**
     * @return bool
     */
    public function setupGrafana(): bool
    {
        try {
            $this->baseUrl = 'https://graphsapi.idling.host';
            $this->start = (new DateTime('now', ServerUtility::getTimezoneObject()))->sub(new DateInterval('P7D'));
            $this->end = new DateTime('now', ServerUtility::getTimezoneObject());
            $this->step = 1200;
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @return array
     *
     * @throws GuzzleException
     */
    public function createSnapshot(): array
    {
        // get config
        $result = $this->requestIapProtectedResource('/api/snapshots', 'POST', [
            'body' => json_encode([
                'dashboard' => $this->getDashboardSnapshotConfig(),
                'name' => $this->instance->getFqdn(),
                'expires' => 0,
            ], JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION),
            'headers' => $this->getGrafanaRequestHeaders(),
        ]);

        // report success
        if (StatusCode::HTTP_OK === $result->getStatusCode()) {
            return json_decode($result->getBody()->getContents(), true);
        }

        return [];
    }

    /**
     * @return array
     *
     * @throws GuzzleException
     * @throws Exception
     */
    protected function getDashboardSnapshotConfig(): array
    {
        $dashboard = $this->getBasicDashboardConfig();

        // now we fetch all datasets for all panels and due to how ugly grafana processes these snapshots, we have to refine the data a bit...
        foreach ($dashboard['panels'] as $pkey => $panel) {
            if (array_key_exists('snapshotData', $panel)) {
                foreach ($panel['snapshotData'] as $skey => $snapshotDatum) {
                    if (array_key_exists('query', $snapshotDatum)) {
                        $dashboard['panels'][$pkey]['snapshotData'][$skey]['datapoints'] = $this->parseQueryDataIntoSnapshotData($this->requestIapProtectedResource(
                            $this->getEndpointForQuery($snapshotDatum['query']),
                            'GET',
                            ['headers' => $this->getGrafanaRequestHeaders()]
                        )->getBody()->getContents());
                    }
                }
            }
        }

        return $dashboard;
    }

    /**
     * @return array
     */
    protected function getBasicDashboardConfig(): array
    {
        // load default snapshot config
        $dashboard = json_decode(file_get_contents(ServerUtility::get('DASHBOARD_CONFIG_JSON', ServerUtility::getClassesPath(['Instance', 'dashboard.json']))), true);

        // if someone entered the dashboard json including the outer object, push it up one level
        if (array_key_exists('dashboard', $dashboard)) {
            $dashboard = $dashboard['dashboard'];
        }

        // set dashboard name
        $dashboard['title'] = 'Dashboard from ' . $this->end->format('m/d');

        $dashboard['snapshot']['timestamp'] = $this->end->format(DATE_RFC3339_EXTENDED);
        $dashboard['time'] = [
            'from' => $this->start->format(DATE_RFC3339_EXTENDED),
            'to' => $this->end->format(DATE_RFC3339_EXTENDED),
            'raw' => [
                'from' => 'now-7d',
                'to' => 'now',
            ],
        ];

        return $dashboard;
    }

    /**
     * @param string $query
     *
     * @return string
     */
    public function getEndpointForQuery(string $query): string
    {
        // TODO: parse and replace instanceId into the query here!
        $query = urlencode($query);

        return '/api/datasources/proxy/2/api/v1/query_range?start=' . $this->start->getTimestamp() . '&end=' . $this->end->getTimestamp() . '&step=' . $this->step . '&query=' . $query;
    }

    /**
     * @param string $raw
     *
     * @return array
     */
    protected function parseQueryDataIntoSnapshotData(string $raw): array
    {
        $dps = json_decode($raw, true)['data']['result'][0]['values'] ?? [];

        // grafana expects millisecond timestamps and for whatever reason expects the timestamp / value pair to switched in the dataset.
        foreach ($dps as $dkey => $dp) {
            $dps[$dkey][1] = 1000 * $dp[0];
            $dps[$dkey][0] = $dp[1];
        }

        return $dps;
    }

    /**
     * @return array
     */
    protected function getGrafanaRequestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . ServerUtility::get('GRAFANA_API_KEY'),
            'X-Grafana-Authorization' => 'Bearer ' . ServerUtility::get('GRAFANA_API_KEY'),
        ];
    }
}
