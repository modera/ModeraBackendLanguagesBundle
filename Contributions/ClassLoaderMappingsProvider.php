<?php

namespace Modera\BackendLanguagesBundle\Contributions;

use Sli\ExpanderBundle\Ext\ContributorInterface;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2014 Modera Foundation
 */
class ClassLoaderMappingsProvider implements ContributorInterface
{
    /**
     * @var string[]
     */
    private $items;

    public function __construct()
    {
        $this->items = array(
            'Modera.backend.languages' => '/bundles/moderabackendlanguages/js',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getItems()
    {
        return $this->items;
    }
}
