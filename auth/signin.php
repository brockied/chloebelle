<?php
// Minimal HTML login page that works during maintenance
// Save as: /auth/signin.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login</title>
  <style>
    :root{--bg:#0d1117;--card:#161b22;--border:#30363d;--text:#e6edf3;--muted:#9da7b3;--grad1:#6a5acd;--grad2:#ff5aad}
    *{box-sizing:border-box}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,'Helvetica Neue',Arial,'Noto Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:16px}
    .card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;max-width:380px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,.25)}
    h1{font-size:1.25rem;margin:0 0 16px}
    .field{margin-bottom:14px}
    label{display:block;font-size:.9rem;margin-bottom:6px;color:var(--muted)}
    input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid var(--border);background:#0b0f14;color:var(--text)}
    button{width:100%;padding:12px 14px;border:0;border-radius:10px;background:linear-gradient(135deg,var(--grad1),var(--grad2));color:#fff;font-weight:600;cursor:pointer}
    .hint{margin-top:12px;color:var(--muted);font-size:.85rem;text-align:center}
    .error{background:#301024;border:1px solid #5c1b3e;color:#ff9ccc;padding:10px;border-radius:10px;margin-bottom:12px;display:none}
  </style>
</head>
<body>
  <form class="card" id="loginForm">
    <h1>Admin Login</h1>
    <div id="err" class="error"></div>
    <div class="field"><label>Email or Username</label><input type="text" name="email" required></div>
    <div class="field"><label>Password</label><input type="password" name="password" required></div>
    <input type="hidden" name="remember_me" value="1">
    <button type="submit">Sign In</button>
    <div class="hint">Maintenance mode won’t block this page.</div>
  </form>

  <script>
    const form = document.getElementById('loginForm');
    const err = document.getElementById('err');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      err.style.display = 'none';
      const formData = new FormData(form);
      const payload = Object.fromEntries(formData.entries());
      try {
        const res = await fetch('/auth/login.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
          window.location.href = data.redirect || '/admin/';
        } else {
          err.textContent = data.message || 'Login failed';
          err.style.display = 'block';
        }
      } catch (ex) {
        err.textContent = 'Network error. Please try again.';
        err.style.display = 'block';
      }
    });
  </script>
</body>
</html>
