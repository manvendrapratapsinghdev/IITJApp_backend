<?php
namespace Core;
class Auth {
  private static function b64e($d){ return rtrim(strtr(base64_encode($d),'+/','-_'),'='); }
  private static function b64d($d){ return base64_decode(strtr($d,'-_','+/')); }
  public static function issueToken(int $uid,string $role='user',int $ttl=15552000): string {
    $cfg = require __DIR__.'/../../config/config.php';
    $h = ['alg'=>'HS256','typ'=>'JWT'];
    $p = ['iss'=>'health-api','sub'=>$uid,'role'=>$role,'iat'=>time(),'exp'=>time()+$ttl];
    $seg = [self::b64e(json_encode($h)), self::b64e(json_encode($p))];
    $sig = hash_hmac('sha256', implode('.',$seg), $cfg['secret'], true);
    $seg[] = self::b64e($sig);
    return implode('.',$seg);
  }
  public static function verify(string $jwt): array {
    $cfg = require __DIR__.'/../../config/config.php';
    $parts = explode('.',$jwt);
    if (count($parts)!==3) throw new \Exception('Invalid token');
    [$h,$p,$s] = $parts;
    $exp = self::b64e(hash_hmac('sha256', $h.'.'.$p, $cfg['secret'], true));
    if (!hash_equals($exp, $s)) throw new \Exception('Invalid signature');
    $payload = json_decode(self::b64d($p), true);
    if (!$payload || ($payload['exp']??0) < time()) throw new \Exception('Token expired');
    return $payload;
  }
  public static function bearer(): ?string {
    $hdr = $_SERVER['HTTP_AUTHORIZATION']
      ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
      ?? getallheaders()['Authorization'] ?? '';

    if (stripos($hdr,'Bearer ') === 0) {
      return trim(substr($hdr,7));
    }
    return null;
  }
  public static function requireUser(): array {
    $t = self::bearer();
    if (!$t) { 
      Response::json(['error'=>'Missing Bearer token','logout_required'=>true],401); 
      exit; 
    }
    
    try { 
      $payload = self::verify($t);
      
      // Additional check: verify token exists in database and user status
      $userModel = new \Models\UserModel();
      $dbUser = $userModel->findByToken($t);
      
      if (!$dbUser) {
        Response::json([
          'error'=>'Invalid token',
          'logout_required'=>true,
          'reason'=>'Token not found in database'
        ],401); 
        exit;
      }
      
      // Check if user is deleted
      if ($dbUser['is_deleted']) {
        // Clear the token since user is deleted
        $userModel->clearToken((int)$dbUser['id']);
        Response::json([
          'error'=>'Account has been deleted',
          'logout_required'=>true,
          'reason'=>'User account deleted by administrator'
        ],401); 
        exit;
      }
      
      // Check if user is blocked
      if ($dbUser['is_blocked']) {
        // For blocked users, clear token so they can't access anything
        $userModel->clearToken((int)$dbUser['id']);
        Response::json([
          'error'=>'Account has been blocked',
          'logout_required'=>true,
          'reason'=>'User account blocked by administrator'
        ],403); 
        exit;
      }
      
      // Update last active timestamp
      $userModel->updateLastActive((int)$dbUser['id']);
      
      return $payload; 
    } catch (\Exception $e) {
      Response::json([
        'error'=>$e->getMessage(),
        'logout_required'=>true
      ],401); 
      exit;
    }
  }
  
  public static function requireRole(array $allowedRoles): array {
    $payload = self::requireUser();
    
    if (!in_array($payload['role'], $allowedRoles)) {
      Response::json([
        'error'=>'Insufficient permissions',
        'required_roles'=>$allowedRoles,
        'your_role'=>$payload['role']
      ],403); 
      exit;
    }
    
    return $payload;
  }
}
