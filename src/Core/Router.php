<?php
namespace Core;
class Router {
  private array $routes = [];
  public function get(string $p,string $h){$this->add('GET',$p,$h);} 
  public function post(string $p,string $h){$this->add('POST',$p,$h);} 
  public function put(string $p,string $h){$this->add('PUT',$p,$h);} 
  public function patch(string $p,string $h){$this->add('PATCH',$p,$h);} 
  public function delete(string $p,string $h){$this->add('DELETE',$p,$h);} 
  private function add($m,$p,$h){
    $pattern = preg_replace('#:([a-zA-Z_][a-zA-Z0-9_]*)#','(?P<$1>[^/]+)',$p);
    $pattern = '#^'.$pattern.'$#';
    $this->routes[] = compact('m','p','pattern','h');
  }
  public function dispatch(string $method,string $uri){
    $uri = parse_url($uri, PHP_URL_PATH);
    foreach($this->routes as $r){
      if($r['m']!==$method) continue;
      if(preg_match($r['pattern'],$uri,$m)){
        [$cls,$act]=explode('@',$r['h']);
        $fqcn='Controllers\\'.$cls;
        $obj=new $fqcn();
        $params = array_filter($m,'is_string',ARRAY_FILTER_USE_KEY);
        return $obj->$act($params);
      }
    }
    Response::json(['error'=>'Not Found','uri'=>$uri],404);
  }
}
