<?php

namespace WHMCS\Module\Addon\PVEWhmcs\Admin;

/**
 * Created by Cristian G [DeVe]
 * Copyright DeVeLab & DrawnCodes
 * Licensed under the Apache License, Version 2.0 (the "License");
 */
class AdminDispatcher {

    /**
     * Dispatch request.
     *
     * @param string $action
     * @param array $parameters
     *
     * @return string
     */
    public function dispatchGET($action, $parameters, $data = false)
    {
        if (!$action) {
            // Default to index if no action specified
            $action = 'index';
        }

        $controller = new Controller();

        // Verify requested action is valid and callable
        if (is_callable(array($controller, $action))) {
            return $controller->$action($parameters, $data);
        }

        return '<p>Invalid action requested. Please go back and try again.</p>';
    }
    /**
     * Dispatch request.
     *
     * @param string $action
     * @param array $parameters
     *
     * @return string
     */
    public function dispatchPOST($action, $parameters, $data = false)
    {
        if (!$action) {
            // Default to index if no action specified
            $action = 'index';
        }

        $handler = new Handler();

        // Verify requested action is valid and callable
        if (is_callable(array($handler, $action))) {
            return $handler->$action($parameters, $data);
        }

        return '<p>Invalid action requested. Please go back and try again.</p>';
    }
}