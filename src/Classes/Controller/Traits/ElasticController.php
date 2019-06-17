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

    /**
     * @param int $from
     * @param int $size
     * @return ElasticHelper
     */
    protected function setWindow(int $from = 0, int $size = 10): ElasticHelper
    {
        if (\is_array($this->params) && \array_key_exists('from', $this->params) && $this->params['from']) {
            $from = (int)$this->params['from'];
        }
        if (\is_array($this->params) && \array_key_exists('size', $this->params) && $this->params['size']) {
            $size = (int)$this->params['size'];
        }
        $this->elastic->setFrom($from);
        $this->elastic->setSize($size);
        return $this->elastic;
    }
}