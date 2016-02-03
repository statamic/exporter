<?php

class Hooks_exporter extends Hooks
{
    public function exporter__export()
    {
        $migration = $this->core->migrate();

        $app = \Slim\Slim::getInstance();

        $response = $app->response();
        $response->header('Content-Type', 'application/json');
        $response->body(json_encode($migration));

        $app->halt();
    }
}
