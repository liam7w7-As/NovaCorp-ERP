<!DOCTYPE html>
<html lang="es" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar - GISECA SRL ERP</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            background: var(--fondo, var(--gc-fondo));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrap {
            width: 100%;
            max-width: 380px;
            padding: 0 16px;
        }

        .login-card {
            background: #fff;
            border-radius: 10px;
            padding: 32px 30px;
            box-shadow: 0 4px 24px rgba(16, 24, 40, .08);
            border: 1px solid var(--borde, var(--gc-borde));
        }

        [data-bs-theme="dark"] .login-card {
            background: var(--gc-superficie);
        }

        .brand-top {
            text-align: center;
            margin-bottom: 22px;
        }

        .brand-top .logo-image {

            width: 250px;

            height: 110px;

            object-fit: contain;

            display: block;

            margin: 0 auto 15px auto;

        }

        .brand-top .name {
            font-size: 22px;
            font-weight: 800;
        }

        .brand-top .sub {
            color: var(--gris-metal-claro, var(--gc-gris-claro));
            font-size: 12px;
        }

        .demo-hint {
            background: var(--naranja-suave, var(--gc-primario-suave));
            border: 1px solid #F3C7AE;
            color: var(--naranja-oscuro, var(--gc-primario-oscuro));
            font-size: 12px;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 16px;
        }

        .error-msg {
            background: var(--rojo-suave, var(--gc-rojo-suave));
            color: var(--rojo, var(--gc-rojo));
            font-size: 12.5px;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 14px;
        }
    </style>
    <script>
        (function() {
            const tema = localStorage.getItem('giseca_tema') || 'light';
            document.documentElement.setAttribute('data-bs-theme', tema);
        })();
    </script>
</head>

<body class="login-body">

    <div class="login-wrap login-container">
        <div class="login-card">
            <div class="brand-top">

                <img src="{{ $logo['url'] }}" alt="Logo GISECA" class="logo-image">

                <div class="name">
                    SISTEMA GISECA
                </div>

                <div class="sub">
                    Sistema ERP Comercial — Modo local
                </div>

            </div>

            <!-- div class="demo-hint">💻 Funciona 100% en tu navegador, sin instalar nada. Los datos se guardan en esta computadora.</div -->

            @if ($errors->any())
                <div class="error-msg">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div style="margin-bottom:16px;">
                    <label class="form-label-giseca" for="email">Usuario</label>
                    <input type="email" id="email" name="email" class="form-control-giseca"
                        placeholder="Ingrese su usuario o correo" value="{{ old('email') }}" required autofocus>
                </div>
                <div style="margin-bottom:20px;">
                    <label class="form-label-giseca" for="password">Contraseña</label>

                    <div style="position:relative;">
                        <input type="password" id="password" name="password" class="form-control-giseca"
                            placeholder="Ingrese su contraseña" required style="padding-right:45px;">

                        <button type="button" onclick="togglePassword()"
                            style="
        position:absolute;
        right:12px;
        top:50%;
        transform:translateY(-50%);
        border:none;
        background:none;
        cursor:pointer;
        color:var(--gc-gris-claro);
        font-size:18px;
      ">
                            <i id="iconPassword" class="bi bi-eye"></i>
                        </button>

                    </div>
                </div>
                <button type="submit" class="btn-giseca btn-primario"
                    style="width:100%; justify-content:center; padding:11px;">
                    Ingresar al sistema
                </button>
                <!-- p style="text-align:center; font-size:11.5px; color:var(--gris-metal-claro, var(--gc-gris-claro)); margin-top:14px; margin-bottom:0;">
          Usuario: <strong>admin@giseca.com</strong> &nbsp;|&nbsp; Contraseña: <strong>admin123</strong>
        </p -->
            </form>
        </div>
    </div>
    <script>
        function togglePassword() {

            const password = document.getElementById('password');
            const icon = document.getElementById('iconPassword');

            if (password.type === "password") {

                password.type = "text";

                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');

            } else {

                password.type = "password";

                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');

            }

        }
    </script>
</body>

</html>
