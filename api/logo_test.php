<?php

declare(strict_types=1);

function detectarTransparencia(string $rutaArchivo, string $extension): string
{
    $extension = strtolower($extension);

    if ($extension === 'png') {
        $cabecera = file_get_contents($rutaArchivo, false, null, 0, 33);
        if ($cabecera === false || strlen($cabecera) < 26) {
            return 'No determinado';
        }

        $colorType = ord($cabecera[25]);

        if (in_array($colorType, [4, 6], true)) {
            return 'Sí — canal alfa (RGBA)';
        }

        if ($colorType === 3) {
            return 'Posible — paleta indexada (verificar tRNS)';
        }

        return 'No — sin canal alfa';
    }

    if (in_array($extension, ['jpg', 'jpeg'], true)) {
        return 'No — formato JPG no soporta alfa';
    }

    if ($extension === 'gif') {
        return 'Posible — verificar índice transparente';
    }

    if ($extension === 'webp') {
        return 'Verificar manual — WebP soporta alfa';
    }

    if ($extension === 'svg') {
        return 'N/A — vectorial';
    }

    return 'Desconocido';
}

// Auditoría de marca: evalúa específicamente las 4 variantes de logo
// disponibles en assets/img/ (logo*.png) — no cualquier imagen del proyecto.
$directorioImg = __DIR__ . '/../assets/img';
$archivos = glob($directorioImg . '/logo*.png') ?: [];
sort($archivos);

$muestras = [];
foreach ($archivos as $rutaCompleta) {
    $nombreArchivo = basename($rutaCompleta);
    $extension = pathinfo($rutaCompleta, PATHINFO_EXTENSION);
    $dimensiones = @getimagesize($rutaCompleta);
    $pesoKb = round(filesize($rutaCompleta) / 1024, 1);

    $muestras[] = [
        'nombre' => $nombreArchivo,
        'ruta_relativa' => '../assets/img/' . $nombreArchivo,
        'ancho' => $dimensiones[0] ?? null,
        'alto' => $dimensiones[1] ?? null,
        'peso_kb' => $pesoKb,
        'transparencia' => detectarTransparencia($rutaCompleta, $extension),
    ];
}

$fondos = [
    ['clase' => 'logo-lab__bg--grafito', 'nombre' => 'Grafito Nocturno (oficial)', 'hex' => '#10141E'],
    ['clase' => 'logo-lab__bg--obsidiana', 'nombre' => 'Negro Obsidiana (alternativo)', 'hex' => '#0A0C14'],
    ['clase' => 'logo-lab__bg--carbono', 'nombre' => 'Carbono Profundo (contraste intermedio)', 'hex' => '#1E2230'],
    ['clase' => 'logo-lab__bg--claro', 'nombre' => 'Modo Día (comparación de contraste)', 'hex' => '#F4F5F7'],
];
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratorio de Logos | El Efecto Ajedrez</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="../favicon.ico">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body class="logo-lab-body">

    <button type="button" class="dash-topbar__theme-toggle auth-theme-toggle" data-theme-toggle aria-label="Cambiar entre modo claro y oscuro">🌙</button>

    <?php if (empty($muestras)): ?>
        <section class="logo-lab__bg logo-lab__bg--grafito">
            <div class="container">
                <p class="logo-lab__empty">No se encontraron imágenes en <code>assets/img/</code>.</p>
            </div>
        </section>
    <?php endif; ?>

    <?php foreach ($fondos as $fondo): ?>
        <section class="logo-lab__bg <?= htmlspecialchars($fondo['clase']) ?>">
            <div class="container">
                <header class="logo-lab__heading">
                    <h2 class="logo-lab__title"><?= htmlspecialchars($fondo['nombre']) ?></h2>
                    <p class="logo-lab__hex"><?= htmlspecialchars($fondo['hex']) ?></p>
                </header>
                <div class="arf-grid logo-lab__grid">
                    <?php foreach ($muestras as $muestra): ?>
                        <article class="arf-grid__item logo-lab__card">
                            <div class="logo-lab__card-image-wrap">
                                <img src="<?= htmlspecialchars($muestra['ruta_relativa']) ?>" alt="<?= htmlspecialchars($muestra['nombre']) ?>" loading="lazy">
                            </div>
                            <div class="arf-grid__body logo-lab__card-body">
                                <h3 class="arf-grid__title logo-lab__filename"><?= htmlspecialchars($muestra['nombre']) ?></h3>
                                <p class="arf-grid__meta">
                                    <?= $muestra['ancho'] !== null ? htmlspecialchars((string) $muestra['ancho']) . '×' . htmlspecialchars((string) $muestra['alto']) . ' px' : 'Dimensiones N/D' ?>
                                    · <?= htmlspecialchars((string) $muestra['peso_kb']) ?> KB
                                </p>
                                <p class="arf-grid__meta logo-lab__alpha"><?= htmlspecialchars($muestra['transparencia']) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endforeach; ?>

    <script src="../assets/js/main.js" defer></script>
</body>
</html>
