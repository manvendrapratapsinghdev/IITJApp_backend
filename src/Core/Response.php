<?php
namespace Core;

class Response {
  public static function json($data, int $status=200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    return null;
  }

  public static function success($data = null, string $message = 'Success') {
    return self::json([
      'success' => true,
      'message' => $message,
      'data' => $data
    ], 200);
  }

  public static function error(string $message = 'Error', int $status = 500, $data = null) {
    return self::json([
      'success' => false,
      'message' => $message,
      'data' => $data
    ], $status);
  }

  public static function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
  }
}
