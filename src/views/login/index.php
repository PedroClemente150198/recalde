<?php
$errorMessage = trim((string) ($error ?? ''));
$successMessage = trim((string) ($success ?? ''));
$usuarioValue = (string) ($usuario ?? '');
$csrfTokenValue = (string) ($csrfToken ?? '');
$waitSecondsValue = max(0, (int) ($waitSeconds ?? 0));
$rememberUserValue = !empty($rememberUser);
$appVersion = defined('APP_VERSION') ? (string) APP_VERSION : '1.0.0';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RECALDE | Login</title>
    <style>
        :root {
            --ink: #0f172a;
            --ink-soft: #475569;
            --light: #f8fafc;
            --line: #dbe4ee;
            --brand-700: #0f766e;
            --brand-600: #0ea5a0;
            --brand-500: #14b8a6;
            --accent: #f59e0b;
            --danger-bg: #fee2e2;
            --danger: #991b1b;
            --ok-bg: #dcfce7;
            --ok: #166534;
            --ring: rgba(20, 184, 166, 0.28);
            --card-shadow: 0 30px 80px rgba(2, 6, 23, 0.42);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Avenir Next", "Gill Sans MT", "Trebuchet MS", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1300px 700px at 100% 0%, rgba(20, 184, 166, 0.25), transparent 60%),
                radial-gradient(1000px 650px at 0% 100%, rgba(245, 158, 11, 0.18), transparent 55%),
                linear-gradient(145deg, #0b1220, #132338);
            display: grid;
            place-items: center;
            padding: 24px;
            overflow-x: hidden;
        }

        .backdrop {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }

        .blob {
            position: absolute;
            border-radius: 999px;
            filter: blur(40px);
            opacity: 0.32;
            animation: float 10s ease-in-out infinite;
        }

        .blob.a {
            width: 340px;
            height: 340px;
            right: -80px;
            top: -90px;
            background: #22d3ee;
        }

        .blob.b {
            width: 280px;
            height: 280px;
            left: -70px;
            bottom: -90px;
            background: #f59e0b;
            animation-delay: -2.4s;
        }

        .blob.c {
            width: 220px;
            height: 220px;
            right: 32%;
            bottom: 8%;
            background: #34d399;
            animation-delay: -5.8s;
        }

        @keyframes float {
            0%,
            100% {
                transform: translateY(0px) scale(1);
            }
            50% {
                transform: translateY(-14px) scale(1.03);
            }
        }

        .auth-shell {
            position: relative;
            z-index: 1;
            width: min(980px, 100%);
            display: grid;
            grid-template-columns: 1.06fr 1fr;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transform: translateY(8px);
            animation: lift 0.55s ease forwards;
        }

        @keyframes lift {
            to {
                transform: translateY(0);
            }
        }

        .auth-aside {
            color: #ecfeff;
            padding: 34px 30px 28px;
            background:
                linear-gradient(165deg, rgba(15, 118, 110, 0.95), rgba(2, 132, 199, 0.84)),
                repeating-linear-gradient(
                    -45deg,
                    rgba(255, 255, 255, 0.05) 0px,
                    rgba(255, 255, 255, 0.05) 7px,
                    rgba(255, 255, 255, 0.01) 7px,
                    rgba(255, 255, 255, 0.01) 14px
                );
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 28px;
        }

        .tag {
            align-self: flex-start;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.2);
            color: #f0fdfa;
            font-size: 0.76rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .brand {
            margin: 10px 0 0;
            font-size: 2.1rem;
            line-height: 1;
            letter-spacing: 0.09em;
        }

        .intro {
            margin: 12px 0 0;
            color: rgba(240, 253, 250, 0.92);
            line-height: 1.54;
            font-size: 0.98rem;
        }

        .feature-list {
            display: grid;
            gap: 11px;
            margin-top: 18px;
        }

        .feature {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            color: #e0f2fe;
            font-size: 0.9rem;
            line-height: 1.43;
        }

        .feature::before {
            content: "";
            display: block;
            margin-top: 6px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #fbbf24;
            box-shadow: 0 0 0 5px rgba(251, 191, 36, 0.18);
            flex-shrink: 0;
        }

        .aside-foot {
            margin-top: auto;
            color: rgba(236, 254, 255, 0.88);
            font-size: 0.82rem;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 34px 30px;
            position: relative;
        }

        .auth-card::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(80% 60% at 100% 0%, rgba(20, 184, 166, 0.09), transparent 70%),
                radial-gradient(60% 50% at 0% 100%, rgba(245, 158, 11, 0.1), transparent 70%);
        }

        .auth-card-inner {
            position: relative;
            z-index: 1;
        }

        .title {
            margin: 0;
            font-size: 1.8rem;
            line-height: 1.08;
            letter-spacing: 0.01em;
        }

        .subtitle {
            margin: 9px 0 22px;
            color: var(--ink-soft);
            font-size: 0.95rem;
        }

        .msg {
            margin: 0 0 14px;
            padding: 11px 13px;
            border-radius: 10px;
            font-size: 0.91rem;
            border-left: 4px solid transparent;
        }

        .msg.error {
            background: var(--danger-bg);
            color: var(--danger);
            border-left-color: #dc2626;
        }

        .msg.success {
            background: var(--ok-bg);
            color: var(--ok);
            border-left-color: #16a34a;
        }

        .field {
            margin-bottom: 13px;
        }

        .field label {
            display: block;
            font-size: 0.88rem;
            font-weight: 800;
            margin-bottom: 6px;
            color: #0b1324;
            letter-spacing: 0.015em;
        }

        .field input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 11px;
            padding: 12px 12px;
            font-size: 0.98rem;
            background: #fff;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.12s ease;
            box-shadow: inset 0 1px 0 rgba(15, 23, 42, 0.02);
        }

        .field input:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 4px var(--ring);
            transform: translateY(-1px);
        }

        .meta-row {
            margin-top: 4px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .check {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #334155;
            font-size: 0.9rem;
            user-select: none;
        }

        .check input {
            accent-color: var(--brand-600);
        }

        .forgot-link {
            color: #0e7490;
            font-size: 0.89rem;
            text-decoration: none;
            font-weight: 800;
        }

        .forgot-link:hover {
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        button[type="submit"] {
            width: 100%;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff;
            padding: 12px 14px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            letter-spacing: 0.02em;
            box-shadow: 0 12px 24px rgba(15, 118, 110, 0.28);
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
        }

        button[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 26px rgba(15, 118, 110, 0.34);
            filter: saturate(1.08);
        }

        button[type="submit"]:disabled {
            opacity: 0.78;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .hint {
            margin: 11px 0 0;
            color: var(--ink-soft);
            text-align: center;
            font-size: 0.84rem;
            min-height: 1.2rem;
        }

        @media (max-width: 920px) {
            .auth-shell {
                grid-template-columns: 1fr;
            }

            .auth-aside {
                gap: 16px;
                padding: 24px 22px;
            }

            .brand {
                font-size: 1.85rem;
            }

            .auth-card {
                padding: 26px 22px;
            }

            .title {
                font-size: 1.62rem;
            }
        }
    </style>
</head>
<body>
    <div class="backdrop" aria-hidden="true">
        <span class="blob a"></span>
        <span class="blob b"></span>
        <span class="blob c"></span>
    </div>

    <main class="auth-shell" role="main">
        <aside class="auth-aside" aria-hidden="true">
            <div>
                <span class="tag">Gestion textil</span>
                <h1 class="brand">RECALDE</h1>
                <p class="intro">Inicia sesión para administrar clientes, pedidos, inventario y ventas en un solo panel.</p>

                <div class="feature-list">
                    <div class="feature">Control de acceso con bloqueo temporal por intentos fallidos.</div>
                    <div class="feature">Recuperación de contraseña segura mediante enlace de correo.</div>
                    <div class="feature">Inicio rápido con opción de recordar usuario en este equipo.</div>
                </div>
            </div>

            <div class="aside-foot">Version <?= htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8') ?></div>
        </aside>

        <section class="auth-card">
            <div class="auth-card-inner">
                <h2 class="title">Bienvenido de nuevo</h2>
                <p class="subtitle">Ingresa tus credenciales para acceder al sistema.</p>

                <?php if ($errorMessage !== ''): ?>
                    <p class="msg error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($successMessage !== ''): ?>
                    <p class="msg success" role="status"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form method="post" action="?route=validar-login" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenValue, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="field">
                        <label for="login-usuario">Usuario</label>
                        <input
                            id="login-usuario"
                            type="text"
                            name="usuario"
                            maxlength="60"
                            value="<?= htmlspecialchars($usuarioValue, ENT_QUOTES, 'UTF-8') ?>"
                            required
                            autocomplete="username"
                            autofocus
                        >
                    </div>

                    <div class="field">
                        <label for="login-password">Contraseña</label>
                        <input
                            id="login-password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="meta-row">
                        <label class="check" for="remember-user">
                            <input
                                id="remember-user"
                                type="checkbox"
                                name="remember_user"
                                value="1"
                                <?= $rememberUserValue ? 'checked' : '' ?>
                            >
                            Recordarme en este equipo
                        </label>
                        <a class="forgot-link" href="?route=forgot-password">Olvide mi contraseña</a>
                    </div>

                    <div class="meta-row" style="margin-top:-3px;">
                        <label class="check" for="show-password-toggle">
                            <input id="show-password-toggle" type="checkbox">
                            Mostrar contraseña
                        </label>
                    </div>

                    <button
                        id="login-submit"
                        type="submit"
                        <?= $waitSecondsValue > 0 ? 'disabled' : '' ?>
                    >
                        <?= $waitSecondsValue > 0 ? 'Espera para reintentar' : 'Entrar al panel' ?>
                    </button>

                    <p class="hint" id="login-hint">
                        <?= $waitSecondsValue > 0
                            ? 'Demasiados intentos. Podras reintentar en ' . $waitSecondsValue . ' segundos.'
                            : 'Tus credenciales se procesan con protecciones de seguridad activas.' ?>
                    </p>
                </form>
            </div>
        </section>
    </main>

    <script>
        (function () {
            const passwordInput = document.getElementById('login-password');
            const toggle = document.getElementById('show-password-toggle');
            const submitButton = document.getElementById('login-submit');
            const hint = document.getElementById('login-hint');
            let remaining = <?= (int) $waitSecondsValue ?>;

            if (toggle && passwordInput) {
                toggle.addEventListener('change', function () {
                    passwordInput.type = toggle.checked ? 'text' : 'password';
                });
            }

            if (submitButton && hint && remaining > 0) {
                const timer = window.setInterval(function () {
                    remaining -= 1;
                    if (remaining <= 0) {
                        window.clearInterval(timer);
                        submitButton.disabled = false;
                        submitButton.textContent = 'Entrar al panel';
                        hint.textContent = 'Ya puedes intentar iniciar sesión nuevamente.';
                        return;
                    }

                    hint.textContent = 'Demasiados intentos. Podras reintentar en ' + remaining + ' segundos.';
                }, 1000);
            }
        })();
    </script>
</body>
</html>

