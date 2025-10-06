<?php
session_start();

// Se já está logado, vai pro painel
if (!empty($_SESSION['user'])) {
  header('Location: admin.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = trim($_POST['usuario'] ?? '');
  $p = trim($_POST['senha'] ?? '');
  if ($u === 'admin' && $p === '123') {
    session_regenerate_id(true);
    $_SESSION['user'] = 'admin';
    header('Location: admin.php'); // destino após login
    exit;
  } else {
    $error = 'Usuário ou senha inválidos.';
  }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar • BurgerHub</title>

<!-- mesmas fontes/feel do painel -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">

<style>
:root{
  --bg:#ffffff; --ink:#1B1B1F; --muted:#6B7186; --line:#E8E8EE; --surface:#F7F8FA;
  --card:#fff; --mustard:#F2B705; --mustard-700:#D9A205; --ketchup:#D7263D;
  --shadow:0 10px 30px rgba(0,0,0,.08); --elev:.28s cubic-bezier(.2,.8,.2,1);
  --font-body:"Manrope",Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
  --font-heading:"Sora","Manrope",Inter,system-ui,sans-serif;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0; font-family:var(--font-body); color:var(--ink); background:linear-gradient(180deg,#fafbfc,#f3f5f9);
  display:grid; place-items:center; padding:24px;
}
.shell{
  width:min(940px,100%); display:grid; grid-template-columns: 420px 1fr; gap:20px;
}
@media (max-width: 900px){ .shell{ grid-template-columns: 1fr; } }

.brand{
  display:flex; align-items:center; gap:12px; margin-bottom:18px;
}
.brand .logo{
  width:46px; height:46px; border-radius:14px; display:grid; place-items:center;
  background:linear-gradient(180deg,#fff,var(--surface)); border:1px solid var(--line);
  box-shadow:var(--shadow); color:var(--ketchup); font-size:20px;
}
.brand strong{display:block; font-family:var(--font-heading); font-weight:800; font-size:18px}
.brand small{color:var(--muted)}

.card{
  background:var(--card); border:1px solid var(--line); border-radius:16px; box-shadow:var(--shadow);
  padding:22px; display:grid; gap:14px; transition:transform var(--elev), box-shadow var(--elev);
}
.card:hover{ transform:translateY(-2px) }

h1{font-family:var(--font-heading); margin:0; font-size:28px}
p.muted{color:var(--muted); margin:0}

label{display:grid; gap:6px; font-weight:700}
input[type="text"],input[type="password"]{
  border:1px solid var(--line); border-radius:12px; background:#fff; padding:12px 14px; font:inherit;
}
.input-row{position:relative}
.input-row .show{
  position:absolute; right:8px; top:50%; transform:translateY(-50%);
  border:0; background:#fff; color:var(--muted); cursor:pointer; padding:6px 8px; border-radius:8px;
}

.btn{
  display:inline-flex; align-items:center; justify-content:center; gap:8px;
  border:1px solid var(--line); background:var(--mustard); color:#111; font-weight:800;
  padding:12px; border-radius:12px; cursor:pointer; width:100%;
  transition:transform var(--elev), box-shadow var(--elev), background .2s ease;
}
.btn:hover{ transform:translateY(-2px); box-shadow:var(--shadow); background:var(--mustard-700); }

.error{
  background:#fee2e2; color:#991b1b; border:1px solid #fecaca; border-radius:12px;
  padding:10px 12px; font-weight:600;
}

.side{
  background:#fff; border:1px solid var(--line); border-radius:16px; box-shadow:var(--shadow);
  padding:22px; display:grid; gap:10px; align-content:start;
}
.side .tip{
  background:var(--surface); border:1px dashed var(--line); border-radius:12px;
  padding:12px; color:var(--muted); font-size:14px;
}
.kbd{
  display:inline-flex; align-items:center; gap:6px; border:1px solid var(--line);
  background:#fff; padding:6px 10px; border-radius:10px; font-weight:700; font-size:13px;
}
.footer{ text-align:center; color:var(--muted); font-size:12px; margin-top:6px; }
</style>
</head>
<body>

<div class="shell">
  <div class="card">
    <div class="brand">
      <div class="logo">🍔</div>
      <div>
        <strong>BurgerHub</strong>
        <small>Painel de Controle</small>
      </div>
    </div>

    <h1>Entrar</h1>
    <p class="muted">Use suas credenciais para acessar o painel.</p>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <label>Usuário
        <input type="text" name="usuario" placeholder="admin" required>
      </label>
      <label>Senha
        <div class="input-row">
          <input id="pwd" type="password" name="senha" placeholder="•••" required>
          <button class="show" type="button" aria-label="Mostrar senha" onclick="togglePwd()">👁</button>
        </div>
      </label>
      <button class="btn" type="submit">Entrar no painel</button>
    </form>

    <div class="footer">Dica: <strong>admin</strong> / <strong>123</strong></div>
  </div>

  <aside class="side">
    <div class="tip">
      <strong>Atalho:</strong> pressione
      <span class="kbd">Enter</span> para enviar.
    </div>
    <div class="tip">
      Após logar, você será levado ao <b>admin.php</b>, que carrega seu <b>admin.html</b> já protegido.
    </div>
  </aside>
</div>

<script>
function togglePwd(){
  const i = document.getElementById('pwd');
  i.type = (i.type === 'password') ? 'text' : 'password';
}
</script>

</body>
</html>
