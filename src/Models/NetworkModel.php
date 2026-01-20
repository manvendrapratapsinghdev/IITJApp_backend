<?php
namespace Models;
use Core\Database;
use PDO;
class NetworkModel {
  private PDO $db;
  public function __construct(){ $this->db = Database::pdo(); }
  public function connectOrAccept(int $uid,int $other): bool {
    $st=$this->db->prepare('SELECT * FROM connections WHERE (user_id=? AND other_user_id=?) OR (user_id=? AND other_user_id=?) LIMIT 1');
    $st->execute([$uid,$other,$other,$uid]);
    $row=$st->fetch();
    if($row){ $st=$this->db->prepare('UPDATE connections SET status="accepted" WHERE id=?'); return $st->execute([$row['id']]); }
    $st=$this->db->prepare('INSERT INTO connections (user_id,other_user_id,status) VALUES (?,?, "pending")');
    return $st->execute([$uid,$other]);
  }
  public function listForUser(int $uid): array { $st=$this->db->prepare('SELECT * FROM connections WHERE user_id=? OR other_user_id=?'); $st->execute([$uid,$uid]); return $st->fetchAll(); }
}
