<?php
$errorMessage = trim((string) ($error ?? ''));
$successMessage = trim((string) ($success ?? ''));
$csrfTokenValue = (string) ($csrfToken ?? '');
$tokenValue = (string) ($token ?? '');
$usuarioValue = (string) ($usuario ?? '');
$tokenValidValue = !empty($tokenValid);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RECALDE | Nueva contraseña</title>
    <style>
        :root {
            --bg-a: #0b1220;
            --bg-b: #1e293b;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #475569;
            --primary: #0f766e;
            --primary-strong: #115e59;
            --danger-bg: #fee2e2;
            --danger-text: #991b1b;
            --ok-bg: #dcfce7;
            --ok-text: #166534;
            --ring: rgba(15, 118, 110, 0.26);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Trebuchet MS", "Segoe UI", Tahoma, sans-serif;
            background:
                radial-gradient(900px 500px at 0% 0%, rgba(14, 165, 233, 0.35) 0%, transparent 55%),
                radial-gradient(900px 500px at 100% 100%, rgba(16, 185, 129, 0.35) 0%, transparent 55%),
                linear-gradient(140deg, var(--bg-a), var(--bg-b));
            display: grid;
            place-items: center;
            padding: 20px;
        }

        .card {
            width: min(520px, 100%);
            background: var(--card);
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 20px 55px rgba(2, 6, 23, 0.38);
        }

        h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        .subtitle {
            margin: 8px 0 20px;
            color: var(--muted);
            line-height: 1.45;
        }

        .msg {
            margin: 0 0 14px;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.92rem;
        }

        .msg.error {
            background: var(--danger-bg);
            color: var(--danger-text);
        }

        .msg.success {
            background: var(--ok-bg);
            color: var(--ok-text);
        }

        .user-chip {
            display: inline-block;
            margin-bottom: 14px;
            background: #e2e8f0;
            color: #1e293b;
            border-radius: 999px;
            padding: 6px 11px;
            font-size: 0.84rem;
            font-weight: 700;
        }

        label {
            display: block;
            font-weight: 700;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }

        input[type="password"] {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 1rem;
            outline: none;
            margin-bottom: 14px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        input[type="password"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--ring);
        }

        .check {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 14px;
            color: #334155;
            font-size: 0.9rem;
            user-select: none;
        }

        .check input {
            accent-color: var(--primary);
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
        }

        button,
        a.btn {
            border: 0;
            border-radius: 10px;
            padding: 11px 15px;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            background: var(--primary);
            color: #fff;
        }

        button:hover {
            background: var(--primary-strong);
        }

        button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        a.btn {
            background: #e2e8f0;
            color: #1e293b;
        }

        a.btn:hover {
            background: #cbd5e1;
        }

        .note {
            margin-top: 14px;
            font-size: 0.84rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <main class="card" role="main">
        <h1>Nueva contraseña</h1>
        <p class="subtitle">Define una nueva contraseña segura para recuperar tu acceso.</p>

        <?php if ($errorMessage !== ''): ?>
            <p class="msg error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <p class="msg success" role="status"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($tokenValidValue): ?>
            <?php if ($usuarioValue !== ''): ?>
                <span class="user-chip">Usuario: <?= htmlspecialchars($usuarioValue, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>

            <form method="post" action="?route=reset-password-save" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenValue, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($tokenValue, ENT_QUOTES, 'UTF-8') ?>">

                <label for="reset-password">Nueva contraseña</label>
                <input
                    id="reset-password"
                    name="new_password"
                    type="password"
                    minlength="8"
                    maxlength="255"
                    required
                    autocomplete="new-password"
                >

                <label for="reset-password-confirm">Confirmar contraseña</label>
                <input
                    id="reset-password-confirm"
                    name="confirm_password"
                    type="password"
                    minlength="8"
                    maxlength="255"
                    required
                    autocomplete="new-password"
                >

                <label class="check" for="show-password-toggle">
                    <input id="show-password-toggle" type="checkbox">
                    Mostrar contraseñas
                </label>

                <div class="actions">
                    <button type="submit">Actualizar contraseña</button>
                    <a class="btn" href="?route=login">Volver al login</a>
                </div>
            </form>

            <p class="note">Recomendado: usa al menos 8 caracteres, combinando letras, números y símbolos.</p>
        <?php else: ?>
            <div class="actions">
                <a class="btn" href="?route=forgot-password">Solicitar nuevo enlace</a>
                <a class="btn" href="?route=login">Volver al login</a>
            </div>
        <?php endif; ?>
    </main>

    <script>
        (function () {
            const toggle = document.getElementById('show-password-toggle');
            const first = document.getElementById('reset-password');
            const second = document.getElementById('reset-password-confirm');
            if (!toggle || !first || !second) {
                return;
            }

            toggle.addEventListener('change', function () {
                const type = toggle.checked ? 'text' : 'password';
                first.type = type;
                second.type = type;
            });
        })();
    </script>
</body>
</html>

