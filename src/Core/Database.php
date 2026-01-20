<?php
namespace Core;
use PDO, PDOException;

class Database {
  private static ?PDO $pdo = null;
  public static function pdo(): PDO {
    if (!self::$pdo) {
      $cfg = require __DIR__ . '/../../config/config.php';
      $dsn = 'mysql:host='.$cfg['db']['host'].';dbname='.$cfg['db']['dbname'].';charset=utf8mb4';
      try {
        self::$pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
      } catch(PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'DB connection failed','details'=>$e->getMessage()]);
        exit;
      }
    }
    return self::$pdo;
  }
}
