<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Helper\ElasticHelper;
use Helio\Panel\Utility\ServerUtility;

trait ElasticController
{
    /** @var ElasticHelper $auth */
    protected $elastic;

    /**
     * @return bool
     */
    public function setupElasticClient(): bool
    {
        $host = ['host' => ServerUtility::get('ELASTIC_HOST')];

        foreach (['port', 'scheme', 'user', 'pass', 'path'] as $variableName) {
            if (ServerUtility::get('ELASTIC_' . strtoupper($variableName), '')) {
                $host[$variableName] = ServerUtility::get('ELASTIC_' . strtoupper($variableName));
            }
        }

        $this->elastic = new ElasticHelper($host);
        return true;
    }
}