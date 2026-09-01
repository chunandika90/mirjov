<!DOCTYPE html>
<html lang="en-US" dir="ltr">

  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    // Meta tags SEO — tiap halaman boleh set $pageTitle/$pageDescription/$pageImage/
    // $pageCanonical/$pageOgType SEBELUM require inc/head.php buat override; kalau
    // gak di-set, fallback ke default generic di bawah.
    // $pageTitleOverride dipakai kalau admin isi SEO Title custom (udah lengkap, jangan
    // ditambahin " — Svashta Home" lagi). Kalau kosong, judul otomatis dari $pageTitle.
    $seoTitle = !empty($pageTitleOverride) ? $pageTitleOverride : (!empty($pageTitle) ? $pageTitle . ' — Svashta Home' : 'Svashta Home — Bespoke Fine Furnishings');
    $seoDescription = $pageDescription ?? 'Svashta Home — bespoke fine furnishings, mebel custom premium buatan tangan pengrajin Indonesia. Sofa, meja, kursi, tempat tidur, dan kabinet dengan desain timeless.';
    $seoImage = $pageImage ?? (defined('SITE_URL') ? SITE_URL . '/assets/img/favicons/svashta_symbol_white.png' : '');
    $seoCanonical = $pageCanonical ?? (defined('SITE_URL') ? SITE_URL . '/pages/' . basename($_SERVER['SCRIPT_NAME']) . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '') : '');
    $seoOgType = $pageOgType ?? 'website';
    ?>
    <title><?= htmlspecialchars($seoTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($seoCanonical) ?>">
    <meta property="og:type" content="<?= htmlspecialchars($seoOgType) ?>">
    <meta property="og:site_name" content="Svashta Home">
    <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($seoImage) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($seoCanonical) ?>">
    <meta name="twitter:card" content="summary_large_image">

    <script>
    // Bersihin Service Worker & cache lama dari domain ini yang mungkin masih
    // nempel di browser pengunjung lama (domain pernah diarahin ke hosting
    // lain sebelumnya) — jalan otomatis, user gak perlu hard refresh manual.
    (function () {
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function (regs) {
          regs.forEach(function (r) { r.unregister(); });
        }).catch(function () {});
      }
      if (window.caches && caches.keys) {
        caches.keys().then(function (names) {
          names.forEach(function (name) { caches.delete(name); });
        }).catch(function () {});
      }
    })();
    </script>

    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/favicons/logo_svashtahome.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicons/logo_svashtahome.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicons/logo_svashtahome.png">
    <link rel="shortcut icon" type="image/x-icon" href="/assets/img/favicons/logo_svashtahome.png">
    <link rel="manifest" href="/assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="/assets/img/favicons/mstile-150x150.png">
    <meta name="theme-color" content="#ffffff">
    <script src="/vendors/overlayscrollbars/OverlayScrollbars.min.js"></script>

    <link href="/vendors/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="/assets/css/theme.css" rel="stylesheet" />
    <link href="/assets/css/user.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/user.css') ?: time() ?>" rel="stylesheet" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@100;200;300;400;500;600;700;900&amp;family=Roboto:wght@100;300;400;500;700;900&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
      /* Efek zoom halus — dipakai di foto hero besar (1 foto dominan di halaman
         detail) dan foto grid/listing (zoom pas di-hover). Container WAJIB
         overflow:hidden biar zoom-nya gak kelihatan keluar batas. */
      .kb-zoom-idle{animation:kbZoom 22s ease-in-out infinite alternate;}
      @keyframes kbZoom{from{transform:scale(1);}to{transform:scale(1.09);}}

      .kb-zoom-hover{overflow:hidden;}
      .kb-zoom-hover img{transition:transform 1.1s cubic-bezier(.25,.46,.45,.94);}
      .kb-zoom-hover:hover img{transform:scale(1.08);}

      @media (prefers-reduced-motion: reduce){
        .kb-zoom-idle{animation:none;}
        .kb-zoom-hover img{transition:none;}
        .kb-zoom-hover:hover img{transform:none;}
      }
    </style>
  </head>

  <body>
