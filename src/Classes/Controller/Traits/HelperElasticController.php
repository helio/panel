<?php

namespace Helio\Panel\Controller\Traits;

use \Exception;
use Helio\Panel\App;
use Helio\Panel\Helper\ElasticHelper;

trait HelperElasticController
{

    /**
     * Set window. Always go throuth this function to retrieve results like $this->setWindow()->getLogEntries();
     *
     * @param int $from
     * @param int $size
     * @return ElasticHelper
     * @throws Exception
     */
    protected function setWindow(int $from = 0, int $size = 10): ElasticHelper
    {
        if (is_array($this->params) && array_key_exists('from', $this->params) && $this->params['from']) {
            $from = (int)$this->params['from'];
        }
        if (is_array($this->params) && array_key_exists('size', $this->params) && $this->params['size']) {
            $size = (int)$this->params['size'];
        }
        App::getElasticHelper()->setFrom($from);
        App::getElasticHelper()->setSize($size);

        return App::getElasticHelper();
    }
}