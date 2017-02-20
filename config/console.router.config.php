<?php

use Zeus\Controller\ZeusController;

return [
    'routes' => [
        'zeus-service' => [
            'options' => [
                'route' => 'zeus (start|list) [<service>]',
                'defaults' => [
                    'controller' => ZeusController::class
                ]
            ]
        ]
    ]
];