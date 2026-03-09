<?php
$errorMessage = trim((string) ($error ?? ''));
$successMessage = trim((string) ($success ?? ''));
$identifierValue = (string) ($identificador ?? '');
$csrfTokenValue = (string) ($csrfToken ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RECALDE | Recuperar contraseña</title>
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
            width: min(500px, 100%);
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

        label {
            display: block;
            font-weight: 700;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }

        input[type="text"] {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 1rem;
            outline: none;
            margin-bottom: 14px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        input[type="text"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--ring);
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

        .brand-mini-signature {
            position: fixed;
            right: 12px;
            bottom: 10px;
            z-index: 2;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid rgba(156, 235, 230, 0.28);
            background: rgba(5, 28, 44, 0.45);
            color: rgba(232, 255, 252, 0.85);
            font-size: 10.5px;
            letter-spacing: 0.02em;
            line-height: 1;
            pointer-events: none;
            user-select: none;
        }

        @media (max-width: 640px) {
            .brand-mini-signature {
                right: 8px;
                bottom: 8px;
                font-size: 10px;
                padding: 4px 8px;
            }
        }
    </style>
</head>
<body>
    <main class="card" role="main">
        <h1>Recuperar contraseña</h1>
        <p class="subtitle">Ingresa tu usuario o correo. Si existe una cuenta, te enviaremos un enlace para restablecerla.</p>

        <?php if ($errorMessage !== ''): ?>
            <p class="msg error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <p class="msg success" role="status"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <form method="post" action="?route=forgot-password-send" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenValue, ENT_QUOTES, 'UTF-8') ?>">

            <label for="forgot-identificador">Usuario o correo</label>
            <input
                id="forgot-identificador"
                name="identificador"
                type="text"
                maxlength="120"
                value="<?= htmlspecialchars($identifierValue, ENT_QUOTES, 'UTF-8') ?>"
                required
                autocomplete="username email"
                autofocus
            >

            <div class="actions">
                <button type="submit">Enviar enlace</button>
                <a class="btn" href="?route=login">Volver al login</a>
            </div>
        </form>

        <p class="note">El enlace de recuperación expira automáticamente y solo puede usarse una vez.</p>
    </main>
    <div class="brand-mini-signature" aria-hidden="true">Att: TecnologyArt</div>
</body>
</html>
