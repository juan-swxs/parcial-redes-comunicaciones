<?php
/**
 * Seeder de contenido para la portada de Joomla (Parcial 2 COMM).
 *
 * Se ejecuta en segundo plano al arrancar el contenedor (ver start.sh):
 *   1. espera a que la instalación desatendida termine (configuration.php
 *      existe y la carpeta installation/ fue eliminada);
 *   2. si el contenido ya existe (marcador = alias del artículo principal) no hace nada;
 *   3. crea imágenes SVG, artículos destacados, módulos de la portada, ítems de
 *      menú y ajusta la plantilla Cassiopeia, registrando los assets (ACL) en el
 *      árbol nested-set de Joomla para que todo sea editable desde el administrador.
 */

const WEB = '/var/www/html';
const MARKER_ALIAS = 'bienvenida-portal-parcial-comm';

function logmsg(string $msg): void
{
    fwrite(STDOUT, '[seed-joomla] ' . $msg . PHP_EOL);
}

// ------------------------------------------------------------------
// 1. Esperar la instalación desatendida
// ------------------------------------------------------------------
$deadline = time() + 900;
while (!(is_file(WEB . '/configuration.php') && !is_dir(WEB . '/installation'))) {
    if (time() > $deadline) {
        logmsg('Joomla no terminó de instalarse en 15 min; se omite el contenido de ejemplo.');
        exit(0);
    }
    sleep(3);
}

define('_JEXEC', 1);
require WEB . '/configuration.php';
$cfg = new JConfig();
$p = $cfg->dbprefix;

$db = null;
for ($i = 0; $i < 60 && !$db; $i++) {
    try {
        $db = new PDO("pgsql:host={$cfg->host};dbname={$cfg->db}", $cfg->user, $cfg->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db->query("SELECT 1 FROM {$p}content LIMIT 1");
    } catch (Throwable $e) {
        $db = null;
        sleep(3);
    }
}
if (!$db) {
    logmsg('No fue posible conectar a la base de datos; se omite el contenido de ejemplo.');
    exit(0);
}

$st = $db->prepare("SELECT 1 FROM {$p}content WHERE alias = ?");
$st->execute([MARKER_ALIAS]);
if ($st->fetchColumn()) {
    // Portadas creadas por versiones anteriores mencionaban el token de Jupyter (ya no se usa)
    $db->exec("UPDATE {$p}content SET \"fulltext\" = replace(\"fulltext\", ' (token <code>parcial123</code>)', '')
               WHERE \"fulltext\" LIKE '%parcial123%'");
    logmsg('El contenido de la portada ya existe; nada que hacer.');
    exit(0);
}

// ------------------------------------------------------------------
// Utilidades
// ------------------------------------------------------------------
function one(PDO $db, string $sql, array $args = [])
{
    $st = $db->prepare($sql);
    $st->execute($args);
    return $st->fetchColumn();
}

/** Inserta un nodo hijo (al final) en una tabla nested-set y devuelve su id. */
function nestedInsert(PDO $db, string $table, int $parentId, array $row): int
{
    $parent = $db->query("SELECT rgt, level FROM {$table} WHERE id = {$parentId}")->fetch();
    $r = (int) $parent['rgt'];
    $db->exec("UPDATE {$table} SET rgt = rgt + 2 WHERE rgt >= {$r}");
    $db->exec("UPDATE {$table} SET lft = lft + 2 WHERE lft > {$r}");
    $row += ['parent_id' => $parentId, 'lft' => $r, 'rgt' => $r + 1, 'level' => (int) $parent['level'] + 1];
    $cols = array_keys($row);
    $quoted = implode(', ', array_map(fn($c) => '"' . $c . '"', $cols));
    $st = $db->prepare("INSERT INTO {$table} ({$quoted}) VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ') RETURNING id');
    $st->execute(array_values($row));
    return (int) $st->fetchColumn();
}

function asset(PDO $db, string $p, string $parentName, string $name, string $title): int
{
    $parentId = (int) one($db, "SELECT id FROM {$p}assets WHERE name = ?", [$parentName]);
    return nestedInsert($db, "{$p}assets", $parentId, ['name' => $name, 'title' => $title, 'rules' => '{}']);
}

// ------------------------------------------------------------------
// 2. Imágenes SVG para las tarjetas de la portada
// ------------------------------------------------------------------
function svgCard(string $c1, string $c2, string $label, string $icon): string
{
    $label = htmlspecialchars($label, ENT_XML1);
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630" width="1200" height="630">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$c1}"/><stop offset="1" stop-color="{$c2}"/>
    </linearGradient>
    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
      <path d="M40 0H0V40" fill="none" stroke="#ffffff" stroke-opacity=".08" stroke-width="1"/>
    </pattern>
  </defs>
  <rect width="1200" height="630" fill="url(#g)"/>
  <rect width="1200" height="630" fill="url(#grid)"/>
  <circle cx="1040" cy="90" r="220" fill="#fff" fill-opacity=".07"/>
  <circle cx="140" cy="590" r="160" fill="#fff" fill-opacity=".06"/>
  <g transform="translate(600 270)" fill="none" stroke="#fff" stroke-width="10" stroke-linecap="round" stroke-linejoin="round">{$icon}</g>
  <text x="600" y="545" text-anchor="middle" font-family="Segoe UI, Roboto, Helvetica, Arial, sans-serif"
        font-size="46" font-weight="700" fill="#fff" letter-spacing="2">{$label}</text>
</svg>
SVG;
}

$icons = [
    // red de nodos
    'red' => '<circle cx="0" cy="-110" r="34"/><circle cx="-150" cy="60" r="34"/><circle cx="150" cy="60" r="34"/><circle cx="0" cy="150" r="26"/>'
        . '<path d="M-20 -82L-130 34M20 -82L130 34M-116 64H116M-130 88L-22 140M130 88L22 140"/>',
    // contenedores apilados
    'docker' => '<rect x="-190" y="-40" width="110" height="80" rx="10"/><rect x="-55" y="-40" width="110" height="80" rx="10"/>'
        . '<rect x="80" y="-40" width="110" height="80" rx="10"/><rect x="-120" y="-150" width="110" height="80" rx="10"/>'
        . '<rect x="15" y="-150" width="110" height="80" rx="10"/><path d="M-230 80Q0 190 230 80"/>',
    // gráfica
    'chart' => '<path d="M-200 150H200M-200 150V-160"/><path d="M-170 90L-80 10L0 60L80 -70L170 -120"/>'
        . '<rect x="-160" y="100" width="40" height="40"/><rect x="-80" y="70" width="40" height="70"/><rect x="0" y="40" width="40" height="100"/><rect x="80" y="0" width="40" height="140"/>',
    // cuaderno
    'notebook' => '<rect x="-150" y="-170" width="300" height="320" rx="18"/><path d="M-100 -110H100M-100 -60H60M-100 -10H100"/>'
        . '<path d="M-90 60L-50 90L-90 120M-30 120H40"/>',
    // capas OSI
    'osi' => '<rect x="-200" y="-170" width="400" height="58" rx="12"/><rect x="-170" y="-90" width="340" height="58" rx="12"/>'
        . '<rect x="-140" y="-10" width="280" height="58" rx="12"/><rect x="-110" y="70" width="220" height="58" rx="12"/>',
    // lista de verificación
    'check' => '<rect x="-160" y="-170" width="320" height="330" rx="18"/><path d="M-110 -100L-85 -75L-45 -120M-10 -95H110"/>'
        . '<path d="M-110 -20L-85 5L-45 -40M-10 -15H110"/><path d="M-110 60L-85 85L-45 40M-10 65H110"/>',
    // escudo
    'shield' => '<path d="M0 -175L160 -115V10Q160 120 0 180Q-160 120 -160 10V-115Z"/><path d="M-70 5L-15 60L80 -50"/>',
];

$imgDir = WEB . '/images/parcial';
@mkdir($imgDir, 0755, true);
$images = [
    'hero.svg'      => svgCard('#0f2027', '#2c5364', 'PORTAL INSTITUCIONAL · PARCIAL COMM', $icons['red']),
    'docker.svg'    => svgCard('#1d4ed8', '#0ea5e9', '5 CONTENEDORES · 2 REDES', $icons['docker']),
    'grafana.svg'   => svgCard('#f97316', '#db2777', 'MONITOREO CON GRAFANA', $icons['chart']),
    'jupyter.svg'   => svgCard('#7c3aed', '#c026d3', 'CIENCIA DE DATOS CON JUPYTER', $icons['notebook']),
    'osi.svg'       => svgCard('#047857', '#14b8a6', 'MODELO OSI · CAPAS 2 · 3 · 4 · 7', $icons['osi']),
    'seguridad.svg' => svgCard('#334155', '#0f766e', 'SEGMENTACIÓN Y SEGURIDAD', $icons['shield']),
    'verificacion.svg' => svgCard('#0369a1', '#4f46e5', 'GUÍA DE VERIFICACIÓN', $icons['check']),
];
foreach ($images as $file => $svg) {
    file_put_contents("{$imgDir}/{$file}", $svg);
}
@chown($imgDir, 'www-data');
foreach (glob("{$imgDir}/*") as $f) {
    @chown($f, 'www-data');
}

// ------------------------------------------------------------------
// 3. Artículos destacados
// ------------------------------------------------------------------
$articles = [
    [
        'alias' => MARKER_ALIAS,
        'title' => 'Bienvenido al Portal Institucional de Comunicaciones',
        'img'   => 'hero.svg',
        'intro' => <<<'HTML'
<p class="lead">Este portal forma parte del <strong>Parcial 2 práctico de Comunicaciones (Ingeniería Mecatrónica)</strong>:
una infraestructura web completa desplegada con un único comando, <code>docker compose up -d</code>.</p>
<p>Cada visita que haces a esta página atraviesa el proxy inverso <strong>Nginx</strong>, es atendida por
<strong>Joomla</strong> sobre Apache y PHP, consulta la base de datos <strong>PostgreSQL</strong> y queda
registrada en los logs que analizan en tiempo real <strong>Grafana</strong> y <strong>Jupyter</strong>.</p>
HTML,
        'full'  => <<<'HTML'
<h3>¿Qué puedes hacer aquí?</h3>
<ul>
  <li>Navegar por los artículos para generar tráfico HTTP real.</li>
  <li>Abrir <a href="/grafana/">Grafana</a> y ver cómo aparecen tus peticiones en menos de 10 segundos.</li>
  <li>Abrir <a href="/jupyter/">Jupyter Lab</a> y ejecutar el cuaderno <em>analisis_datos.ipynb</em>.</li>
</ul>
HTML,
    ],
    [
        'alias' => 'arquitectura-cinco-contenedores',
        'title' => 'Arquitectura: 5 contenedores y 2 redes',
        'img'   => 'docker.svg',
        'intro' => <<<'HTML'
<p>Nginx, Joomla, PostgreSQL, Jupyter y Grafana se orquestan con Docker Compose sobre dos puentes Linux:
<code>frontend_net</code> y <code>backend_net</code>. Sólo Nginx publica un puerto en el host: el 80.</p>
HTML,
        'full'  => <<<'HTML'
<table class="table table-striped">
  <thead><tr><th>Contenedor</th><th>Imagen</th><th>Redes</th><th>Puerto</th></tr></thead>
  <tbody>
    <tr><td>nginx</td><td>nginx:alpine</td><td>frontend_net</td><td>80 (publicado)</td></tr>
    <tr><td>joomla</td><td>joomla:latest</td><td>frontend_net · backend_net</td><td>80</td></tr>
    <tr><td>database</td><td>postgres:16-alpine</td><td>backend_net (interna)</td><td>5432</td></tr>
    <tr><td>jupyter</td><td>jupyter/minimal-notebook</td><td>frontend_net · backend_net</td><td>8888</td></tr>
    <tr><td>grafana</td><td>grafana/grafana</td><td>frontend_net · backend_net</td><td>3000</td></tr>
  </tbody>
</table>
<p>Nginx enruta por prefijo: <code>/</code> → Joomla, <code>/jupyter/</code> → Jupyter (con WebSockets) y
<code>/grafana/</code> → Grafana.</p>
HTML,
    ],
    [
        'alias' => 'monitoreo-grafana',
        'title' => 'Monitoreo en tiempo real con Grafana',
        'img'   => 'grafana.svg',
        'intro' => <<<'HTML'
<p>Los logs de acceso de Nginx y de Apache se escriben en volúmenes compartidos y PostgreSQL los expone
como tablas con <code>file_fdw</code>. Grafana los grafica sin ninguna configuración manual.</p>
<p><a class="btn btn-primary" href="/grafana/">Abrir dashboards →</a></p>
HTML,
        'full'  => <<<'HTML'
<p>El dashboard <strong>Tráfico HTTP y logs</strong> muestra peticiones por código de estado, latencia
p50/p95, IPs de origen, rutas más visitadas, errores recientes y un visor de logs en vivo. El dashboard
<strong>PostgreSQL</strong> muestra conexiones, escrituras por tabla y sesiones activas.</p>
HTML,
    ],
    [
        'alias' => 'ciencia-de-datos-jupyter',
        'title' => 'Ciencia de datos con Jupyter Lab',
        'img'   => 'jupyter.svg',
        'intro' => <<<'HTML'
<p>Un cuaderno de Python precargado consulta PostgreSQL con SQLAlchemy y analiza el tráfico del portal con
gráficas interactivas: línea de tiempo, códigos HTTP, latencias y actividad de la base de datos.</p>
<p><a class="btn btn-primary" href="/jupyter/">Abrir Jupyter →</a></p>
HTML,
        'full'  => <<<'HTML'
<p>El kernel de Jupyter se comunica por WebSocket. Nginx reenvía las cabeceras <code>Upgrade</code> y
<code>Connection</code> para que el canal se establezca con la respuesta <code>101 Switching Protocols</code>.</p>
HTML,
    ],
    [
        'alias' => 'analisis-modelo-osi',
        'title' => 'El modelo OSI dentro del clúster',
        'img'   => 'osi.svg',
        'intro' => <<<'HTML'
<p>Desde las cabeceras <code>X-Forwarded-For</code> (capa 7) hasta el ARP entre interfaces
<code>veth</code> y puentes <code>br-*</code> (capa 2), la solución documenta cómo viaja cada paquete.</p>
HTML,
        'full'  => <<<'HTML'
<ul>
  <li><strong>Capa 7:</strong> HTTP, cabeceras del proxy, WebSockets y protocolo de PostgreSQL.</li>
  <li><strong>Capa 4:</strong> puertos TCP 80, 3000, 5432 y 8888, keep-alive y pools de conexiones.</li>
  <li><strong>Capa 3:</strong> subredes de cada bridge, DNS embebido 127.0.0.11, DNAT y MASQUERADE.</li>
  <li><strong>Capa 2:</strong> pares veth, puentes Linux y resolución ARP.</li>
</ul>
<p>El análisis completo está en el archivo <code>INFORME.md</code> del repositorio.</p>
HTML,
    ],
    [
        'alias' => 'segmentacion-y-seguridad',
        'title' => 'Segmentación de red y aislamiento de la base de datos',
        'img'   => 'seguridad.svg',
        'intro' => <<<'HTML'
<p>PostgreSQL vive únicamente en <code>backend_net</code>, una red <em>internal</em> sin gateway: no es
visible desde Nginx ni desde el host y no tiene salida a internet.</p>
HTML,
        'full'  => <<<'HTML'
<p>Sólo los servicios que necesitan datos (Joomla, Jupyter y Grafana) comparten esa red. El servidor DNS
de Docker sólo resuelve nombres dentro de las redes compartidas, así que para Nginx el nombre
<code>database</code> simplemente no existe.</p>
HTML,
    ],
    [
        'alias' => 'guia-de-verificacion',
        'title' => 'Guía de verificación en 3 pasos',
        'img'   => 'verificacion.svg',
        'intro' => <<<'HTML'
<ol>
  <li>Navega por este portal para generar tráfico.</li>
  <li>Abre <a href="/grafana/">Grafana</a> y mira cómo aparecen tus peticiones.</li>
  <li>Ejecuta el cuaderno en <a href="/jupyter/">Jupyter</a> con <em>Run All Cells</em>.</li>
</ol>
HTML,
        'full'  => <<<'HTML'
<p>Para comprobar el aislamiento de red desde una terminal:</p>
<pre>docker compose exec nginx sh -c "nc -zv -w2 database 5432 || echo 'aislamiento OK'"</pre>
<p>La guía completa está en la sección 3 de <code>INFORME.md</code>.</p>
HTML,
    ],
];

$db->beginTransaction();
try {
    $now = gmdate('Y-m-d H:i:s');
    $admin = (int) one($db, "SELECT id FROM {$p}users ORDER BY id LIMIT 1");
    $catId = (int) one($db, "SELECT id FROM {$p}categories WHERE extension = 'com_content' AND alias = 'uncategorised'");
    $catAsset = "com_content.category.{$catId}";
    $stage = (int) (one($db, "SELECT id FROM {$p}workflow_stages ORDER BY id LIMIT 1") ?: 1);

    $insArticle = $db->prepare("INSERT INTO {$p}content
        (asset_id, title, alias, introtext, \"fulltext\", state, catid, created, created_by, modified, modified_by,
         publish_up, images, urls, attribs, version, ordering, metakey, metadesc, access, hits, metadata, featured, language, note)
        VALUES (0, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, '{}', '{}', 1, ?, '', '', 1, 0,
                '{\"robots\":\"\",\"author\":\"\",\"rights\":\"\"}', 1, '*', '') RETURNING id");

    foreach ($articles as $i => $a) {
        $images = json_encode([
            'image_intro' => "images/parcial/{$a['img']}", 'image_intro_alt' => $a['title'],
            'float_intro' => '', 'image_intro_caption' => '',
            'image_fulltext' => "images/parcial/{$a['img']}", 'image_fulltext_alt' => $a['title'],
            'float_fulltext' => '', 'image_fulltext_caption' => '',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // created escalonado para que el orden por fecha coincida con el de la lista
        $created = gmdate('Y-m-d H:i:s', time() - $i * 60);
        $insArticle->execute([$a['title'], $a['alias'], $a['intro'], $a['full'], $catId, $created, $admin,
            $created, $admin, $created, $images, $i]);
        $id = (int) $insArticle->fetchColumn();

        $assetId = asset($db, $p, $catAsset, "com_content.article.{$id}", $a['title']);
        $db->prepare("UPDATE {$p}content SET asset_id = ? WHERE id = ?")->execute([$assetId, $id]);
        $db->prepare("INSERT INTO {$p}content_frontpage (content_id, ordering) VALUES (?, ?)")->execute([$id, $i + 1]);
        $db->prepare("INSERT INTO {$p}workflow_associations (item_id, stage_id, extension) VALUES (?, ?, 'com_content.article')")
           ->execute([$id, $stage]);
    }

    // --------------------------------------------------------------
    // 4. Módulos personalizados (banner de portada y accesos rápidos)
    // --------------------------------------------------------------
    $home = (int) one($db, "SELECT id FROM {$p}menu WHERE home = 1 AND client_id = 0 LIMIT 1");
    $moduleParams = json_encode([
        'prepare_content' => '0', 'backgroundimage' => '', 'layout' => '_:default', 'moduleclass_sfx' => '',
        'cache' => '1', 'cache_time' => '900', 'cachemode' => 'static', 'module_tag' => 'div',
        'bootstrap_size' => '0', 'header_tag' => 'h3', 'header_class' => '', 'style' => '0',
    ]);

    $hero = <<<'HTML'
<style>
.pc-hero{position:relative;overflow:hidden;border-radius:18px;padding:3rem 2.5rem;color:#fff;
  background:linear-gradient(120deg,#0f2027 0%,#203a43 45%,#2c5364 100%);box-shadow:0 18px 40px rgba(15,32,39,.35)}
.pc-hero:after{content:"";position:absolute;right:-80px;top:-80px;width:320px;height:320px;border-radius:50%;
  background:radial-gradient(circle,rgba(56,189,248,.35),transparent 70%)}
.pc-hero h1{font-size:clamp(1.8rem,4vw,2.8rem);font-weight:800;margin:0 0 .6rem;color:#fff}
.pc-hero p{font-size:1.1rem;max-width:760px;opacity:.9;margin:0 0 1.6rem}
.pc-badges{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.6rem}
.pc-badge{padding:.3rem .8rem;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);font-size:.85rem}
.pc-cta{display:flex;flex-wrap:wrap;gap:.8rem;position:relative;z-index:1}
.pc-cta a{display:inline-block;padding:.75rem 1.4rem;border-radius:12px;font-weight:700;text-decoration:none;transition:transform .15s}
.pc-cta a:hover{transform:translateY(-2px)}
.pc-cta .pc-primary{background:#38bdf8;color:#0f172a}
.pc-cta .pc-ghost{border:2px solid rgba(255,255,255,.6);color:#fff}
.pc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-top:1.4rem}
.pc-stat{background:#fff;border-radius:14px;padding:1.1rem;text-align:center;box-shadow:0 6px 18px rgba(15,23,42,.08);border-top:4px solid #38bdf8}
.pc-stat b{display:block;font-size:1.8rem;color:#0f172a}
.pc-stat span{color:#475569;font-size:.9rem}
</style>
<section class="pc-hero">
  <div class="pc-badges">
    <span class="pc-badge">Nginx</span><span class="pc-badge">Joomla</span><span class="pc-badge">PostgreSQL 16</span>
    <span class="pc-badge">Jupyter Lab</span><span class="pc-badge">Grafana</span>
  </div>
  <h1>Infraestructura web académica e industrial</h1>
  <p>Cinco servicios interconectados, desplegados con un solo comando y monitoreados en tiempo real:
     del navegador al proxy, del CMS a la base de datos y de los logs a los dashboards.</p>
  <div class="pc-cta">
    <a class="pc-primary" href="/grafana/">📊 Ver dashboards en Grafana</a>
    <a class="pc-ghost" href="/jupyter/">🧪 Abrir Jupyter Lab</a>
  </div>
</section>
<div class="pc-stats">
  <div class="pc-stat"><b>5</b><span>contenedores</span></div>
  <div class="pc-stat" style="border-top-color:#a78bfa"><b>2</b><span>redes bridge</span></div>
  <div class="pc-stat" style="border-top-color:#f97316"><b>1</b><span>puerto publicado (80)</span></div>
  <div class="pc-stat" style="border-top-color:#22c55e"><b>0</b><span>pasos manuales</span></div>
</div>
HTML;

    $links = <<<'HTML'
<ul class="list-unstyled" style="margin:0">
  <li style="margin-bottom:.6rem">📊 <a href="/grafana/d/joomla-logs">Tráfico HTTP y logs</a></li>
  <li style="margin-bottom:.6rem">🐘 <a href="/grafana/d/postgres-activity">Actividad de PostgreSQL</a></li>
  <li style="margin-bottom:.6rem">🧪 <a href="/jupyter/lab/tree/analisis_datos.ipynb">Cuaderno de análisis</a></li>
  <li>🛠️ <a href="/administrator/">Administración de Joomla</a></li>
</ul>
HTML;

    $insModule = $db->prepare("INSERT INTO {$p}modules
        (asset_id, title, note, content, ordering, position, published, module, access, showtitle, params, client_id, language)
        VALUES (0, ?, '', ?, ?, ?, 1, 'mod_custom', 1, ?, ?, 0, '*') RETURNING id");
    foreach ([
        ['Portada - Banner', $hero, 1, 'main-top', 0, [$home]],
        ['Accesos rápidos', $links, 0, 'sidebar-right', 1, [0]],
    ] as [$title, $content, $ordering, $position, $showTitle, $menus]) {
        $insModule->execute([$title, $content, $ordering, $position, $showTitle, $moduleParams]);
        $mid = (int) $insModule->fetchColumn();
        $aid = asset($db, $p, 'com_modules', "com_modules.module.{$mid}", $title);
        $db->prepare("UPDATE {$p}modules SET asset_id = ? WHERE id = ?")->execute([$aid, $mid]);
        foreach ($menus as $menuId) {
            $db->prepare("INSERT INTO {$p}modules_menu (moduleid, menuid) VALUES (?, ?)")->execute([$mid, $menuId]);
        }
    }
    // El menú principal queda primero en la barra lateral
    $db->exec("UPDATE {$p}modules SET ordering = -1 WHERE module = 'mod_menu' AND client_id = 0 AND position = 'sidebar-right'");

    // --------------------------------------------------------------
    // 5. Ítems de menú externos (Grafana / Jupyter)
    // --------------------------------------------------------------
    $menuParams = json_encode(['menu-anchor_title' => '', 'menu-anchor_css' => '', 'menu-anchor_rel' => '',
        'menu_image' => '', 'menu_image_css' => '', 'menu_text' => 1, 'menu_show' => 1]);
    foreach ([['Grafana', 'grafana', '/grafana/'], ['Jupyter Lab', 'jupyter-lab', '/jupyter/']] as [$title, $alias, $url]) {
        nestedInsert($db, "{$p}menu", 1, [
            'menutype' => 'mainmenu', 'title' => $title, 'alias' => $alias, 'note' => '', 'path' => $alias,
            'link' => $url, 'type' => 'url', 'published' => 1, 'component_id' => 0, 'browserNav' => 0,
            'access' => 1, 'img' => '', 'template_style_id' => 0, 'params' => $menuParams, 'home' => 0,
            'language' => '*', 'client_id' => 0,
        ]);
    }

    // --------------------------------------------------------------
    // 6. Diseño de la portada (blog en 3 columnas) y plantilla
    // --------------------------------------------------------------
    $params = json_decode((string) one($db, "SELECT params FROM {$p}menu WHERE id = ?", [$home]), true) ?: [];
    $params = array_merge($params, [
        'num_leading_articles' => '1', 'num_intro_articles' => '6', 'num_links' => '0',
        'blog_class_leading' => 'boxed', 'blog_class' => 'boxed columns-3',
        'orderby_sec' => 'front', 'link_intro_image' => '1',
        'show_category' => '0', 'show_author' => '0', 'show_publish_date' => '0', 'show_hits' => '0',
        'info_block_show_title' => '0', 'show_page_heading' => '0', 'show_feed_link' => '0',
        'page_title' => 'Portal Institucional - Parcial COMM',
    ]);
    $db->prepare("UPDATE {$p}menu SET params = ? WHERE id = ?")
       ->execute([json_encode($params, JSON_UNESCAPED_UNICODE), $home]);

    $tpl = $db->query("SELECT id, params FROM {$p}template_styles WHERE client_id = 0 AND home = '1' LIMIT 1")->fetch();
    if ($tpl) {
        $tp = json_decode($tpl['params'], true) ?: [];
        $tp = array_merge($tp, [
            'brand' => '1', 'siteTitle' => 'Portal Parcial COMM',
            'siteDescription' => 'Comunicaciones · Ingeniería Mecatrónica',
            'colorName' => 'colors_standard', 'stickyHeader' => 1, 'backTop' => 1,
        ]);
        $db->prepare("UPDATE {$p}template_styles SET params = ? WHERE id = ?")
           ->execute([json_encode($tp, JSON_UNESCAPED_UNICODE), $tpl['id']]);
    }

    $db->commit();
    logmsg('Contenido de la portada creado: ' . count($articles) . ' artículos, 2 módulos, 2 ítems de menú.');
} catch (Throwable $e) {
    $db->rollBack();
    logmsg('Error creando el contenido: ' . $e->getMessage());
}
