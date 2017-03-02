<?php

use Zeus\Controller\ZeusController;

return [
    'routes' => [
        'zeus-service' => [
            'options' => [
                'route' => 'zeus (start|list|status) [<service>]',
                'defaults' => [
                    'controller' => ZeusController::class
                ]
            ]
        ]
    ]
];