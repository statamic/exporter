<?php

class Hooks_exporter extends Hooks
{
    public function exporter__export()
    {
        $migration = $this->tasks->migrate();

        $app = \Slim\Slim::getInstance();

        $response = $app->response();
        $response->header('Content-Type', 'application/json');
        $response->header('Content-disposition', 'attachment; filename=export.json');
        $response->body(json_encode($migration, JSON_PRETTY_PRINT));

        $app->halt();
    }
}
