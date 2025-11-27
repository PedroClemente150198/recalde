
<h1>Login</h1>
<?php if(isset($error)): ?>
    <p style="color:red"><?= $error ?></p>
<?php endif; ?>
<form method="post" action="/recalde/">
    <input type="text" name="usuario" placeholder="Usuario">
    <input type="password" name="password" placeholder="Contraseña">
    <button type="submit">Entrar</button>
</form>
