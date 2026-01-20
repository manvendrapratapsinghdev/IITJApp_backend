How to run (XAMPP):
1) Create MySQL DB 'health_db' and import database/schema.sql
2) Edit config/config.php with your DB user/pass and set a long random 'secret'
3) Copy this folder to: D:\xampp\htdocs\health
4) Start Apache; ensure mod_rewrite is ON. 
5) Test: http://localhost/health/public/api/health  (or run: php -S localhost:8000 -t public and open http://localhost:8000/api/health)

Key endpoints:
POST /api/auth/signup  {name,email,phone,password}
POST /api/auth/login   {email,password}
GET  /api/me           (Authorization: Bearer <token>)
