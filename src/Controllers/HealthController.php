<?php
namespace Controllers;

class HealthController {
    public function index() {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }
}
