<?php

namespace Modera\BackendLanguagesBundle\Controller;

use Modera\ServerCrudBundle\Controller\AbstractCrudController;
use Modera\BackendConfigUtilsBundle\ModeraBackendConfigUtilsBundle;
use Modera\MJRSecurityIntegrationBundle\ModeraMJRSecurityIntegrationBundle;
use Modera\LanguagesBundle\Entity\Language;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2018 Modera Foundation
 */
class LanguagesController extends AbstractCrudController
{
    /**
     * {@inheritdoc}
     */
    public function getConfig(): array
    {
        return array(
            'entity' => Language::class,
            'security' => array(
                'role' => ModeraMJRSecurityIntegrationBundle::ROLE_BACKEND_USER,
                'actions' => array(
                    'create' => ModeraBackendConfigUtilsBundle::ROLE_ACCESS_BACKEND_SYSTEM_SETTINGS,
                    'update' => ModeraBackendConfigUtilsBundle::ROLE_ACCESS_BACKEND_SYSTEM_SETTINGS,
                    'remove' => ModeraBackendConfigUtilsBundle::ROLE_ACCESS_BACKEND_SYSTEM_SETTINGS,
                    'batchUpdate' => ModeraBackendConfigUtilsBundle::ROLE_ACCESS_BACKEND_SYSTEM_SETTINGS,
                ),
            ),
            'hydration' => array(
                'groups' => array(
                    'list' => function (Language $entity) {
                        return array(
                            'id' => $entity->getId(),
                            'name' => $entity->getName($this->getDisplayLocale()),
                            'locale' => $entity->getLocale(),
                            'isEnabled' => $entity->isEnabled(),
                            'isDefault' => $entity->isDefault(),
                        );
                    },
                    'remove' => function (Language $entity) {
                        return array(
                            'key' => $entity->getLocale(),
                        );
                    },
                ),
                'profiles' => array(
                    'list', 'remove',
                ),
            ),
        );
    }

    private function getDisplayLocale(): string
    {
        $request = $this->get('request_stack')->getCurrentRequest();
        return $request->getLocale();
    }
}
