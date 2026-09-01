<?php
/**
 * Homepage dinamis — narik konten (hero slides, video, collaborators,
 * client reviews) dari database CMS. Kalau DB belum siap/error, situs
 * tetap tampil pakai konten fallback di bawah (bukan halaman kosong/error).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/upload.php';
require_once __DIR__ . '/../shared/helpers.php';
no_cache_headers();

function svashta_homepage_data(): array
{
    $data = [
        'slides' => [
            ['title' => 'BESPOKE FINE FURNISHINGS', 'subtitle' => 'WE CRAFTS BESPOKE FINE FURNISHINGS THAT BLEND TIMELESS DESIGN, EXQUISITE MATERIALS, AND REFINED INDONESIAN CRAFTSMANSHIP.', 'image_url' => 'assets/img/headers/homepage.jpg'],
            ['title' => 'INDONESIAN ARTISAN', 'subtitle' => 'WITH A BLEND OF HERITAGE TECHNIQUES AND MODERN PRECISION, WE HANDCRAFT FURNITURE THAT IS BOTH STRUCTURALLY SOUND AND ARTISTICALLY REFINED.', 'image_url' => 'assets/img/headers/homepage2.jpg'],
            ['title' => 'ARCHITECT OF COMFORT', 'subtitle' => 'TRUE COMFORT IS NOT JUST FELT — IT IS ENGINEERED.', 'image_url' => 'assets/img/headers/homepage4.jpg'],
            ['title' => 'SUSTAINABLE SOURCING', 'subtitle' => 'OUR COMMITMENT TO SUSTAINABILITY ENSURES YOUR FURNITURE IS MADE WITH RESPECT FOR NATURE AND FUTURE GENERATIONS.', 'image_url' => 'assets/img/headers/homepage5.jpg'],
        ],
        'video' => ['headline' => 'Watch our', 'slogan' => 'video', 'youtube_id' => 'jlWMTNZNOc0'],
        'collaborators' => [],
        'reviews' => [],
        'review_bg' => 'assets/img/backgrounds/testimonial.jpg',
        'partner_logos' => [],
        'latest_posts' => [],
    ];

    try {
        $pdo = db();

        $slides = $pdo->query('SELECT * FROM hero_slides ORDER BY sort_order')->fetchAll();
        if ($slides) {
            foreach ($slides as &$s) { $s['image_url'] = image_url($s['image_path']); }
            unset($s);
            $data['slides'] = $slides;
        }

        $video = $pdo->query('SELECT * FROM homepage_video WHERE id = 1')->fetch();
        if ($video && ($video['headline'] || $video['slogan'] || $video['youtube_id'] || $video['video_path'])) {
            $data['video'] = $video;
        }

        $data['collaborators'] = $pdo->query('SELECT * FROM collaborators ORDER BY sort_order')->fetchAll();
        foreach ($data['collaborators'] as &$c) { $c['image_url'] = image_thumb_url($c['image_path']); }
        unset($c);

        $reviews = $pdo->query('SELECT * FROM reviews ORDER BY sort_order')->fetchAll();
        foreach ($reviews as &$r) {
            $r['avatar_url'] = $r['avatar_path'] ? image_thumb_url($r['avatar_path']) : 'assets/img/team/9.jpg';
            $r['photo_url'] = $r['photo_path'] ? image_thumb_url($r['photo_path']) : null;
        }
        unset($r);
        $data['reviews'] = $reviews;

        $bg = $pdo->query('SELECT review_bg_image FROM homepage_backgrounds WHERE id=1')->fetch();
        if ($bg && !empty($bg['review_bg_image'])) {
            $data['review_bg'] = image_url($bg['review_bg_image']);
        }

        $partnerLogos = $pdo->query('SELECT * FROM partner_logos ORDER BY sort_order')->fetchAll();
        foreach ($partnerLogos as &$pl) { $pl['image_url'] = image_thumb_url($pl['image_path']); }
        unset($pl);
        $data['partner_logos'] = $partnerLogos;

        $data['latest_posts'] = $pdo->query('SELECT title, slug, COALESCE(published_at, created_at) AS post_date FROM blog_posts ORDER BY COALESCE(published_at, created_at) DESC LIMIT 3')->fetchAll();
    } catch (Throwable $e) {
        // DB belum siap / error koneksi — pakai fallback statis di atas, situs tetap tampil.
    }

    return $data;
}

$hp = svashta_homepage_data();
?>
<!DOCTYPE html>
<html lang="en-US" dir="ltr">

  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">


    <!-- ===============================================-->
    <!--    Document Title & SEO-->
    <!-- ===============================================-->
    <title>Svashta Home — Bespoke Fine Furnishings</title>
    <meta name="description" content="Svashta Home — bespoke fine furnishings, mebel custom premium buatan tangan pengrajin Indonesia. Sofa, meja, kursi, tempat tidur, dan kabinet dengan desain timeless, area BSD.">
    <link rel="canonical" href="<?= htmlspecialchars(SITE_URL) ?>/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Svashta Home">
    <meta property="og:title" content="Svashta Home — Bespoke Fine Furnishings">
    <meta property="og:description" content="Bespoke fine furnishings, mebel custom premium buatan tangan pengrajin Indonesia.">
    <meta property="og:image" content="<?= htmlspecialchars(SITE_URL) ?>/assets/img/favicons/svashta_symbol_white.png">
    <meta property="og:url" content="<?= htmlspecialchars(SITE_URL) ?>/">
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

    <!-- ===============================================-->
    <!--    Favicons-->
    <!-- ===============================================-->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/logo_svashtahome.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/logo_svashtahome.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/logo_svashtahome.png">
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/logo_svashtahome.png">
    <link rel="manifest" href="assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="assets/img/favicons/mstile-150x150.png">
    <meta name="theme-color" content="#ffffff">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="vendors/overlayscrollbars/OverlayScrollbars.min.js"></script>


    <!-- ===============================================-->
    <!--    Stylesheets-->
    <!-- ===============================================-->
    <link href="vendors/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/css/theme.css" rel="stylesheet" />
    <link href="assets/css/user.css?v=<?= @filemtime(__DIR__ . '/assets/css/user.css') ?: time() ?>" rel="stylesheet" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@100;200;300;400;500;600;700;900&amp;family=Roboto:wght@100;300;400;500;700;900&amp;display=swap" rel="stylesheet">
  </head>

<style>
.navbar-brand.brand-tight {
  display: flex;
  align-items: center;
  white-space: nowrap; /* prevents wrapping */
  gap: 0; /* no space between image and text */
}

.navbar-brand.brand-tight img {
  margin-right: -2px; /* minimal spacing or overlap if needed */
}
.navbar-nav .nav-link {
  white-space: nowrap;
}

</style>
<body onload="startMusicOnLoad()">
    <nav class="navbar navbar-expand-lg navbar-dark navbar-theme fixed-top p-0" data-navbar-on-scroll="data-navbar-on-scroll">
		<div class="container-fluid">
			  <a class="navbar-brand brand-tight" href="index.html">
				<img src="assets/img/favicons/svashta_symbol_white.png" alt="Svashta Icon" width="80" height="50">
				<span>SVASHTA HOME</span>
			  </a>
			
			<button class="navbar-toggler p-0" type="button" data-bs-toggle="collapse" data-bs-target="#primaryNavbarCollapse" aria-controls="primaryNavbarCollapse" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
			<div class="collapse navbar-collapse" id="primaryNavbarCollapse">
			  <ul class="navbar-nav ms-auto">
				<li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#home" data-bs-toggle="dropdown-on-hover" aria-haspopup="true" aria-expanded="false"><span class="nav-link-text">Home</span></a>
				
				  <div class="dropdown-menu dropdown-menu-end py-0 overflow-hidden" aria-labelledby="navbarDropdownMenuLink1">
						<a class="dropdown-item" href="#about-us">About US</a>
						<a class="dropdown-item" href="#services">Why Choose US</a>
						<a class="dropdown-item" href="#collaborations">Collaborations</a>
						<a class="dropdown-item" href="#reach-us">Contact</a>
				</li>
				<li class="nav-item"><a class="nav-link fw-medium" href="/product"><span class="nav-link-text">Product</span></a></li>
				<li class="nav-item"><a class="nav-link fw-medium "  href="/projects" data-bs-toggle="dropdown-on-hover" aria-haspopup="true" aria-expanded="false"><span class="nav-link-text">Projects</span></a>
				</li>
				<li class="nav-item"><a class="nav-link fw-medium "  href="/blog" data-bs-toggle="dropdown-on-hover" aria-haspopup="true" aria-expanded="false"><span class="nav-link-text">Blog</span></a>
				<!--  
				  <div class="dropdown-menu dropdown-menu-end py-0 overflow-hidden" aria-labelledby="navbarDropdownMenuLink1"><a class="dropdown-item" href="homes/one-page.html">One page</a><a class="dropdown-item" href="homes/header-static.html">Static header</a><a class="dropdown-item" href="homes/static-classic.html">Static classic</a><a class="dropdown-item" href="homes/slider-header.html">Slider header</a><a class="dropdown-item" href="homes/slider-classic.html">Slider classic</a><a class="dropdown-item" href="homes/text-left-header.html">Text left header</a><a class="dropdown-item" href="homes/text-left-classic.html">Text left classic</a><a class="dropdown-item" href="homes/typed-text-header.html">Typed text header</a><a class="dropdown-item" href="homes/typed-text-classic.html">Typed text classic</a><a class="dropdown-item" href="homes/text-left-slider.html">Text left slider</a><a class="dropdown-item" href="homes/text-left-classic-slider.html">Text left classic slider</a><a class="dropdown-item" href="homes/video-background.html">Video background</a><a class="dropdown-item" href="homes/video-classic.html">Video classic</a><a class="dropdown-item" href="homes/gradient-background.html">Gradient background</a><a class="dropdown-item" href="homes/gradient-classic.html">Gradient classic</a></div>
				--!>
				</li>
				<li class="nav-item"><a class="nav-link fw-medium "  href="/consultation" data-bs-toggle="dropdown-on-hover" aria-haspopup="true" aria-expanded="false"><span class="nav-link-text">Consultation</span></a>
				</li>
			  </ul>
			</div>
		</div>
      </div>
    </nav>

    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
      <div class="preloader" id="preloader" data-preloader>
        <div class="preloader-wrapper big active" data-preloader>
          <div class="spinner-layer spinner-white-only">
            <div class="circle-clipper left">
              <div class="circle"></div>
            </div>
            <div class="gap-patch">
              <div class="circle"></div>
            </div>
            <div class="circle-clipper right">
              <div class="circle"></div>
            </div>
          </div>
        </div>
      </div>
      <section class="py-0 overflow-hidden" id = "home">
	  
	  
	  
        <div class="swiper theme-slider" data-swiper='{"autoplay":{ "delay": 10000 },"loopedSlides":5,"loop":true,"slideToClickedSlide":true}'>
          <div class="swiper-wrapper">
            <?php foreach ($hp['slides'] as $slide): ?>
            <div class="swiper-slide">
			  <img class="header-slider" src="<?= htmlspecialchars($slide['image_url']) ?>" alt="image" />
			  <div class="header-overlay" data-zanim-timeline='{"delay":0.1}'>
				<div class="container">
				  <div class="d-flex align-items-center justify-content-between vh-100 w-100">
					<!-- Left Side Text -->
					<div class="header-text">
					  <div class="overflow-hidden">
						<h1 class="display-3 text-white fs-5 fs-md-7" data-zanim-xs='{"duration":2,"delay":0}'><?= htmlspecialchars($slide['title']) ?></h1>
					  </div>
					  <div class="overflow-hidden">
						<p class="text-uppercase text-400 ls-3 mt-2" data-zanim-xs='{"duration":2,"delay":0.1}'>
						  <?= nl2br(htmlspecialchars($slide['subtitle'])) ?>
						</p>
					  </div>
					  <div data-zanim-xs='{"from":{"opacity":0,"y":30},"to":{"opacity":1,"y":0},"duration":1.5,"delay":0.5}'>
						<a class="btn btn-sm btn-outline-light hvr-sweep-top mt-5 px-4" href="#services">OUR SERVICES</a>
					  </div>
					</div>
				  </div>
				</div>
			  </div>
			</div>
            <?php endforeach; ?>
          </div>
          <div class="swiper-nav">
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
          </div>
        </div>
      </section>


      <!-- ============================================-->
      <!-- <section> begin ============================-->
        <section class="pt-3 pt-lg-5 pb-6 pb-lg-8 text-center" style="scroll-margin-top:-25px" id="about-us">
		  <div class="container">
			<!-- Header -->
			<div class="row mb-4" data-zanim-timeline="{}" data-zanim-trigger="scroll">
			  <div class="col">
				<div class="overflow-hidden">
				  <h2 class="fs-sm-5 mb-2" data-zanim-xs='{"duration":1.5,"delay":0}'>What Defines Svashta Home</h2>
				</div>
				<div class="overflow-hidden">
				  <p class="text-uppercase fs--1 text-black ls-1 mb-0" data-zanim-xs='{"duration":1.5,"delay":0.1}'>At Svashta Home, every piece we create is a reflection of our commitment to excellence — where design,

craftsmanship, and comfort converge.</p>
				</div>
				<div class="overflow-hidden">
				  <hr class="hr-short border-black" data-zanim-xs='{"duration":1.5,"delay":0.2}' />
				</div>
			  </div>
			</div>

			<!-- Content Grid -->
			<div class="row align-items-center text-md-start text-center">
			  <!-- LEFT POINTS -->
			  <div class="col-md-4 mb-4 mb-md-0 ps-md-4">
				<div class="mb-4">
				  <h5 class="ls-2 mb-2">Premium Materials</h5>
				  <p class="mb-0">We work exclusively with the finest natural resources: solid Indonesian teakwood, premium veneers, genuine
leather, and a curated selection of luxurious fabrics. Every material is handpicked for its beauty, strength, and ability to age with grace.</p>
				</div>
				<div>
				  <h5 class="ls-2 mb-2">Expert Craftsmanship</h5>
				  <p class="mb-0">Our artisans bring decades of experience to each joint, curve, and stitch. With a blend of heritage techniques
and modern precision, we handcraft furniture that is both structurally sound and artistically refined.</p>
				</div>
			  </div>

			  <!-- IMAGE -->
			  <div class="col-md-4 px-lg-3 px-md-2 my-4 my-md-0">
				<img class="rounded w-100 w-sm-75 w-md-100" src="assets/img/about_us_new.jpg" alt="About Us Image" />
			  </div>

			  <!-- RIGHT POINTS -->
			  <div class="col-md-4 pe-md-4">
				<div class="mb-4">
				  <h5 class="ls-2 mb-2">Ergonomic Design</h5>
				  <p class="mb-0">True comfort is not just felt — it is engineered. Every Svashta piece is thoughtfully designed to support the body
with ideal posture and ease, making your home a sanctuary for both style and wellbeing.</p>
				</div>
				<div>
				  <h5 class="ls-2 mb-2">Sustainable Sourcing</h5>
				  <p class="mb-0">We are proud to champion conscious craftsmanship. From FSC-certified woods to eco-responsible production
practices, our commitment to sustainability ensures your furniture is made with respect for nature and future
generations.</p>
				</div>
			  </div>
			</div>

			<!-- BOTTOM POINT -->
			<div class="row mt-5">
			  <div class="col-md-8 offset-md-2">
				<h5 class="ls-2 mb-2">Timeless Aesthetics</h5>
				<p class="mb-0">Inspired by natural forms and architectural balance, our designs are made to transcend trends. Whether in a
contemporary villa or a classic residence, Svashta pieces bring quiet elegance and enduring character to any space.</p>
			  </div>
			</div>
		  </div>
		</section>
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================-->
      <section class="py-8 py-md-10 text-center">

        <?php
        // Video yang KEPUTER pas diklik: prioritas link YouTube kalau diisi (paling
        // gampang di-update dari CMS), file upload cuma dipakai kalau youtube_id-nya
        // kosong. Sebelumnya kebalik (upload selalu menang) — bikin update YouTube
        // link di CMS keliatan gak ke-apply kalau ada file upload lama yang nyangkut.
        if (!empty($hp['video']['youtube_id'])) {
            $bigPictureData = json_encode(['ytSrc' => $hp['video']['youtube_id']]);
        } elseif (!empty($hp['video']['video_path'])) {
            $bigPictureData = json_encode(['vidSrc' => image_url($hp['video']['video_path'])]);
        } else {
            $bigPictureData = json_encode(['ytSrc' => '']);
        }
        // Background/poster section ini SELALU coba ambil thumbnail YouTube kalau
        // youtube_id ada — gak peduli video yang keputer itu file upload atau YouTube,
        // biar posternya gak generic/statis terus. Fallback ke gambar statis cuma
        // kalau youtube_id-nya kosong beneran.
        // Coba maxresdefault (1280x720, HD) dulu — JS di bawah bakal otomatis turun
        // ke hqdefault (480x360) kalau ternyata videonya gak punya versi HD.
        $ytIdForThumb = $hp['video']['youtube_id'] ?? '';
        $videoBgImageHD = $ytIdForThumb ? 'https://img.youtube.com/vi/' . rawurlencode($ytIdForThumb) . '/maxresdefault.jpg' : '';
        $videoBgImageFallback = $ytIdForThumb ? 'https://img.youtube.com/vi/' . rawurlencode($ytIdForThumb) . '/hqdefault.jpg' : 'assets/img/backgrounds/our-video-bg.jpg';
        ?>
        <div class="container position-relative" style="aspect-ratio: 16 / 9; max-height: 640px;">
          <div class="bg-holder rounded-3 js-video-bg" data-hd-src="<?= htmlspecialchars($videoBgImageHD) ?>" style="background-image:url(<?= htmlspecialchars($videoBgImageFallback) ?>);background-position: 63% 50%;">
          </div>
          <!--/.bg-holder-->

          <div class="row justify-content-center align-items-center h-100 position-relative" style="z-index: 1;" data-zanim-timeline="{}" data-zanim-trigger="scroll">
            <div class="col-auto">
              <a class="video-modal btn btn-outline-white hvr-sweep-top rounded-circle p-0 btn-play mx-auto" href="#!" data-bigpicture='<?= htmlspecialchars($bigPictureData) ?>'><span class="fas fa-play" data-fa-transform="grow-1 right-2"></span></a>
            </div>
          </div>
        </div>
        <!-- end of .container-->
        <?php if ($videoBgImageHD): ?>
        <script>
        (function () {
          var el = document.querySelector('.js-video-bg');
          if (!el) return;
          var hdSrc = el.getAttribute('data-hd-src');
          var img = new Image();
          img.onload = function () {
            // YouTube balikin placeholder abu-abu 120x90 persis kalau video-nya
            // gak punya versi HD — cuma pakai maxresdefault kalau ini BUKAN itu.
            if (img.naturalWidth === 120 && img.naturalHeight === 90) return;
            el.style.backgroundImage = 'url(' + hdSrc + ')';
          };
          img.src = hdSrc;
        })();
        </script>
        <?php endif; ?>

      </section>
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================-->
      <!--
	  <section id= "why">

        <div class="container">
          <div class="row text-center mb-4" data-zanim-timeline="{}" data-zanim-trigger="scroll">
            <div class="col">
              <div class="overflow-hidden">
                <h2 class="fs-sm-5 mb-2" data-zanim-xs='{"duration":1.5,"delay":0}'>Why choose us</h2>
              </div>
              <div class="overflow-hidden">
                <p class="fs--1 text-uppercase text-black ls-1 mb-0" data-zanim-xs='{"duration":1.5,"delay":0.1}'>Choosing an agency is tough. <br class="d-block d-sm-none" />let us convince you</p>
              </div>
              <div class="overflow-hidden">
                <hr class="hr-short bg-black" data-zanim-xs='{"duration":1.5,"delay":0.2}' />
              </div>
            </div>
          </div>
          <div class="row text-center text-md-start">
            <div class="col-sm-6 col-md-4 px-lg-4 pt-md-3 pt-lg-0 pb-lg-4 h-100 feature-item position-relative">
              <h5 class="ps-lg-3">Dedicated</h5>
              <hr class="hr-feature d-block d-sm-none bg-black mt-0 mb-2" />
              <div class="border-lg-top border-lg-end py-lg-3 ps-lg-0 line-indicator line-indicator-top line-indicator-top-1">
                <div class="checked-indicator checked-indicator-top"><span class="fas fa-check" data-fa-transform="shrink-2"></span></div>
                <p class="px-lg-3 mb-0">Reign is a team of dedicated members <br class="d-block d-sm-none" /> towards their projects and clients.</p>
              </div>
            </div>
            <div class="col-sm-6 col-md-4 px-lg-4 pt-3 pt-sm-0 pt-md-3 pt-lg-0 pb-lg-4 h-100 feature-item position-relative">
              <h5 class="ps-lg-3">Professional</h5>
              <hr class="hr-feature d-block d-sm-none border-black mt-0 mb-2" />
              <div class="border-lg-top border-lg-end py-lg-3 ps-lg-0 line-indicator line-indicator-top line-indicator-top-2">
                <div class="checked-indicator checked-indicator-top"><span class="fas fa-check" data-fa-transform="shrink-2"></span></div>
                <p class="px-lg-3 mb-md-0">We are all professionals and ready to <br class="d-block d-sm-none" />provide projects quickly and efficiently.</p>
              </div>
            </div>
            <div class="col-sm-6 col-md-4 px-lg-4 pt-3 pt-lg-0 pb-lg-4 h-100 feature-item position-relative">
              <h5 class="ps-lg-3">Experienced</h5>
              <hr class="hr-feature d-block d-sm-none border-black mt-0 mb-2" />
              <div class="border-lg-top border-lg-end py-lg-3 ps-lg-0 line-indicator line-indicator-top line-indicator-top-3">
                <div class="checked-indicator checked-indicator-top"><span class="fas fa-check" data-fa-transform="shrink-2"></span></div>
                <p class="px-lg-3 mb-md-0">Our team is well trained, experienced <br class="d-block d-sm-none" />and know what we are doing.</p>
              </div>
            </div>
            <div class="col-12 my-3 d-none d-md-block">
              <div class="row justify-content-center">
                <div class="col-10 p-0"><img class="w-100" src="assets/img/illustration/process-image-6.png" alt="" /></div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4 px-lg-4 pt-3 pt-lg-4 feature-item position-relative">
              <div class="border-lg-bottom border-lg-end pt-0 py-lg-3 line-indicator line-indicator-bottom line-indicator-bottom-1">
                <div class="checked-indicator checked-indicator-bottom"><span class="fas fa-check" data-fa-transform="shrink-2"></span></div>
                <h5 class="mb-lg-0 ps-lg-3">Creative</h5>
                <hr class="hr-feature d-block d-sm-none border-black mt-0 mb-2" />
              </div>
              <p class="px-lg-3 pt-lg-3 mb-md-0">We have a number of brilliant minds ready<br class="d-block d-sm-none" /> for building your <br class="d-none d-md-block d-xl-none" />new projects.</p>
            </div>
            <div class="col-sm-6 col-md-4 pt-3 pt-lg-4 px-lg-4 feature-item position-relative">
              <div class="border-lg-bottom border-lg-end pt-0 py-lg-3 line-indicator line-indicator-bottom line-indicator-bottom-2">
                <div class="checked-indicator checked-indicator-bottom"><span class="fas fa-check" data-fa-transform="shrink-2"></span></div>
                <h5 class="mb-lg-0 ps-lg-3">24/7 support</h5>
                <hr class="hr-feature d-block d-sm-none border-black mt-0 mb-2" />
              </div>
              <p class="px-lg-3 pt-lg-3 mb-0">We are always at your service 24/7 for <br class="d-block d-sm-none" /> solving any difficulties you would face.</p>
            </div>
            <div class="col-sm-6 col-md-4 pt-3 pt-lg-4 px-lg-4 feature-item position-relative">
              <div class="border-lg-bottom border-lg-end pt-0 py-lg-3 line-indicator line-indicator-bottom line-indicator-bottom-3">
                <div class="checked-indicator checked-indicator-bottom"><span class="fas fa-check" data-fa-transform="shrink-2"></span></div>
                <h5 class="mb-lg-0 ps-lg-3">enthusiastic</h5>
                <hr class="hr-feature d-block d-sm-none border-black mt-0 mb-2" />
              </div>
              <p class="px-lg-3 pt-lg-3 mb-0">Reign-v5.0.0 has a bunch of energetic people<br class="d-block d-sm-none" /> who love what they are doing with us.</p>
            </div>
          </div>
        </div>
        <!-- end of .container-->

      <!-- </section>
	  
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================-->
      <!--
	  <section class="py-7 text-center" data-zanim-timeline="{}" data-zanim-trigger="scroll" id= "facts">

        <div class="bg-holder overlay" style="background-image:url(assets/img/backgrounds/fun-fact.jpg);">
        </div>
        <!--/.bg-holder

        <div class="container">
          <div class="row mb-4">
            <div class="col">
              <div class="overflow-hidden">
                <h2 class="fs-sm-5 text-white mb-1 mb-lg-2" data-zanim-xs='{"duration":1.5,"delay":0}'>do you know</h2>
              </div>
              <div class="overflow-hidden">
                <p class="fs--1 text-uppercase text-white ls-1 mb-0" data-zanim-xs='{"duration":1.5,"delay":0.1}'>some cool facts about our company</p>
              </div>
              <div class="overflow-hidden">
                <hr class="hr-short bg-white" data-zanim-xs='{"duration":1.5,"delay":0.2}' />
              </div>
            </div>
          </div>
          <div class="row justify-content-center">
            <div class="col-lg-8">
              <div class="row">
                <div class="col-md-4 mb-4 mb-lg-0">
                  <div class="overflow-hidden"><img class="fun-fact-icon mb-2" src="assets/img/line-icons/icons/download.svg" alt="" /></div>
                  <div class="overflow-hidden">
                    <h3 class="text-white font-base fw-normal mb-1" data-countup='{"endValue":7781914}'>0</h3>
                  </div>
                  <div class="overflow-hidden">
                    <p class="text-uppercase text-white fw-semi-bold ls-2 mb-0">total downloads</p>
                  </div>
                </div>
                <div class="col-md-4 mb-4 mb-lg-0">
                  <div class="overflow-hidden"><img class="fun-fact-icon mb-2" src="assets/img/line-icons/icons/clock.svg" alt="" /></div>
                  <div class="overflow-hidden">
                    <h3 class="text-white font-base fw-normal mb-1" data-countup='{"endValue":370704}'>0</h3>
                  </div>
                  <div class="overflow-hidden">
                    <p class="text-uppercase text-white fw-semi-bold ls-2 mb-0">minutes well spent</p>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="overflow-hidden"><img class="fun-fact-icon mb-2" src="assets/img/line-icons/icons/battery.svg" alt="" /></div>
                  <div class="overflow-hidden">
                    <h3 class="text-white font-base fw-normal mb-1" data-countup='{"endValue":12599}'>0</h3>
                  </div>
                  <div class="overflow-hidden">
                    <p class="text-uppercase text-white fw-semi-bold ls-2 mb-0">coffees consumed</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- end of .container

      </section>-->
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================-->
      <section class="text-center" id="services">

        <div class="container">
          <div class="row mb-4" data-zanim-timeline="{}" data-zanim-trigger="scroll">
            <div class="col">
              <div class="overflow-hidden">
                <h2 class="fs-md-5" data-zanim-xs='{"duration":1.5,"delay":0}'>OUR SERVICES</h2>
              </div>
              <div class="overflow-hidden">
                <p class="fs--1 text-uppercase text-black ls-1 mb-0" data-zanim-xs='{"duration":1.5,"delay":0.1}'>At Svashta Home, every service is a commitment to comfort, craftsmanship, and personal expression.

We offer more than furniture </p><p class="fs--1 text-uppercase text-black ls-1 mb-0" data-zanim-xs='{"duration":1.5,"delay":0.1}'> we offer bespoke experiences. Whether you’re creating something new or restoring

something loved, our services are designed to meet the highest standards of design, quality, and care.</p>
              </div>
              <div class="overflow-hidden">
                <hr class="hr-short bg-black opacity-100" data-zanim-xs='{"duration":1.5,"delay":0.2}' />
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-sm-6 col-lg-4 mb-4" data-zanim-timeline="{}" data-zanim-trigger="scroll">
              <div class="overflow-hidden">
                <div class="service-item p-3 p-md-4 h-100">
                  <div class="overflow-hidden">
                    <div class="px-4" data-zanim-xs='{"duration":1.5,"delay":"0"}'><img class="service-icon" src="assets/img/line-icons/icons/fountain-pen.svg" alt="" /></div>
                  </div>
                  <div class="overflow-hidden">
                    <h5 class="fw-normal ls-3 mb-2" data-zanim-xs='{"duration":1.5,"delay":"0.1"}'>Bespoke Loose Furniture</h5>
                  </div>
                  <div class="overflow-hidden">
                    <p class="fw-normal" data-zanim-xs='{"duration":1.5,"delay":"0.2"}'>Crafted for you, designed around you. 
					<br class="d-block d-sm-none"> From armchairs to beds and sofas, our artisan-made loose furniture is fully 
					<br class="d-block d-sm-none"> customizable and built using premium solid teakwood, genuine leather, and handpicked fabrics.
					<br class="d-block d-sm-none"> Made for your space, your posture, and your lifestyle.
					</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-4 mb-4" data-zanim-timeline="{}" data-zanim-trigger="scroll">
              <div class="overflow-hidden">
                <div class="service-item p-3 p-md-4 h-100">
                  <div class="overflow-hidden">
                    <div class="px-4" data-zanim-xs='{"duration":1.5,"delay":"0.2"}'><img class="service-icon" src="assets/img/line-icons/icons/pear.svg" alt="" /></div>
                  </div>
                  <div class="overflow-hidden">
                    <h5 class="fw-normal ls-3 mb-2" data-zanim-xs='{"duration":1.5,"delay":"0.1"}'>Restoration of Aged Bespoke Fine Furnishings</h5>
                  </div>
                  <div class="overflow-hidden">
                    <p class="fw-normal" data-zanim-xs='{"duration":1.5,"delay":"0.2"}'>Preserve the soul. Renew the structure.
					<br class="d-block d-sm-none"> We restore aged or heirloom-quality furniture with the utmost care repairing, refinishing, and reviving each piece while honoring its original craftsmanship.
					<br class="d-block d-sm-none"> Sustainable, sentimental, and artisan-led.
					</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-4 mb-4" data-zanim-timeline="{}" data-zanim-trigger="scroll">
              <div class="overflow-hidden">
                <div class="service-item p-3 p-md-4 h-100">
                  <div class="overflow-hidden">
                    <div class="px-4" data-zanim-xs='{"duration":1.5,"delay":"0.3"}'><img class="service-icon" src="assets/img/line-icons/icons/export.svg" alt="" /></div>
                  </div>
                  <div class="overflow-hidden">
                    <h5 class="fw-normal ls-3 mb-2" data-zanim-xs='{"duration":1.5,"delay":"0.1"}'>Drapery & Soft Furnishing Solutions</h5>
                  </div>
                  <div class="overflow-hidden">
                    <p class="fw-normal" data-zanim-xs='{"duration":1.5,"delay":"0.2"}'>The finishing touch that defines the room. 
					<br class="d-block d-sm-none"> We offer custom curtains, sheers, and textile accessories that complete your space with
softness and sophistication.
					<br class="d-block d-sm-none"> All tailored to your interiors using premium fabric selections.
					<br class="d-block d-sm-none"> Elegant, functional, and personal.
					</p>
                  </div>
                </div>
              </div>
            </div>
		</div>
		<div class="row justify-content-center">
            <div class="col-sm-6 col-md-6 col-lg-4 mb-4" data-zanim-timeline="{}" data-zanim-trigger="scroll">
              <div class="overflow-hidden">
                <div class="service-item p-3 p-md-4 h-100">
                  <div class="overflow-hidden">
                    <div class="px-4" data-zanim-xs='{"duration":1.5,"delay":"0.3"}'><img class="service-icon" src="assets/img/line-icons/icons/light-bulb.svg" alt="" /></div>
                  </div>
                  <div class="overflow-hidden">
                    <h5 class="fw-normal ls-3 mb-2" data-zanim-xs='{"duration":1.5,"delay":"0.4"}'>Material & Finish Consultation</h5>
                  </div>
                  <div class="overflow-hidden">
                    <p class="fw-normal" data-zanim-xs='{"duration":1.5,"delay":"0.2"}'>Guidance grounded in craftsmanship.
					<br class="d-block d-sm-none"> Choose the right textures and finishes with expert help.
					<br class="d-block d-sm-none"> We guide you through our range of teak tones, leather types, and fabric options so your furniture looks and feels just right.
					<br class="d-block d-sm-none"> We help you design with clarity and confidence.
					</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-6 col-lg-6 mb-4" data-zanim-timeline="{}" data-zanim-trigger="scroll">
              <div class="overflow-hidden">
                <div class="service-item p-3 p-md-4 h-100">
                  <div class="overflow-hidden">
                    <div class="px-4" data-zanim-xs='{"duration":1.5,"delay":"0.4"}'><img class="service-icon" src="assets/img/line-icons/icons/pie-chart.svg" alt="" /></div>
                  </div>
                  <div class="overflow-hidden">
                    <h5 class="fw-normal ls-3 mb-2" data-zanim-xs='{"duration":1.5,"delay":"0.5"}'>End-to-End Custom Design Support</h5>
                  </div>
                  <div class="overflow-hidden">
                    <p class="fw-normal" data-zanim-xs='{"duration":1.5,"delay":"0.2"}'>From concept to completion.
					<br class="d-block d-sm-none"> We work with homeowners, designers, and architects to co-create furniture that suits
specific spatial and aesthetic needs — from sketches to installation.
					<br class="d-block d-sm-none"> Your ideas. Our craftsmanship.
					<br class="d-block d-sm-none"> One timeless result.
					</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- end of .container-->

      </section>
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================
      <section class="py-6" id = "performance">

        <div class="bg-holder overlay overlay-1" style="background-image:url(assets/img/backgrounds/performance-bg.jpg);background-position: top;">
        </div>
        <!--/.bg-holder

        <div class="container">
          <div class="row">
            <div class="col-12 mb-4 text-center" data-zanim-timeline="{}" data-zanim-trigger="scroll">
              <div class="overflow-hidden">
                <h2 class="fs-md-5 text-white" data-zanim-xs='{"duration":1.5,"delay":0}'>Our Performance</h2>
              </div>
              <div class="overflow-hidden">
                <p class="fs--1 text-uppercase text-white ls-1 mb-0" data-zanim-xs='{"duration":1.5,"delay":0.1}'>Be surprised seeing the final outcome with us.</p>
              </div>
              <div class="overflow-hidden">
                <hr class="hr-short bg-white" data-zanim-xs='{"duration":1.5,"delay":0.2}' />
              </div>
            </div>
            <div class="col-lg-6 px-4 px-sm-3 px-lg-5 mb-4 mb-md-5 mb-lg-0" style="padding-top: 5px;">
              <div class="progress-line text-white" data-progress-line="data-progress-line" data-options='{"progress":90}'>
                <p class="mb-0 text-start">Google Page Speed</p>
              </div>
              <div class="progress-line text-white" data-progress-line="data-progress-line" data-options='{"progress":73}'>
                <p class="mb-0 text-start">Pingdom Page Speed</p>
              </div>
              <div class="progress-line text-white" data-progress-line="data-progress-line" data-options='{"progress":87}'>
                <p class="mb-0 text-start">Reign Performance Matrix</p>
              </div>
              <div class="progress-line text-white" data-progress-line="data-progress-line" data-options='{"progress":95}'>
                <p class="mb-0 text-start">Customer Satisfaction </p>
              </div>
            </div>
            <div class="col-lg-6 px-lg-4">
              <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item"><a class="nav-link fs-0 text-white ls-1 active" id="home-tab" data-bs-toggle="tab" href="#tab-home" role="tab" aria-controls="tab-home" aria-selected="true">UX/UI</a></li>
                <li class="nav-item"><a class="nav-link fs-0 text-white ls-1" id="profile-tab" data-bs-toggle="tab" href="#tab-profile" role="tab" aria-controls="tab-profile" aria-selected="false">Support</a></li>
                <li class="nav-item"><a class="nav-link fs-0 text-white ls-1l" id="contact-tab" data-bs-toggle="tab" href="#tab-contact" role="tab" aria-controls="tab-contact" aria-selected="false">Seo</a></li>
              </ul>
              <div class="tab-content pt-4" id="myTabContent">
                <div class="tab-pane fade show active" id="tab-home" role="tabpanel" aria-labelledby="home-tab">
                  <div class="d-flex align-items-center"><img src="assets/img/line-icons/diamond.svg" width="56" alt="" />
                    <div class="flex-1 ms-3 text-light">Reign is a team of multidisciplinary digital product experts. We extend the design and development departments of the most innovative companies. Our studio is small, working with a few client projects at a time.</div>
                  </div>
                </div>
                <div class="tab-pane fade" id="tab-profile" role="tabpanel" aria-labelledby="profile-tab">
                  <div class="d-flex align-items-center"><img src="assets/img/line-icons/customer-service.svg" width="56" alt="" />
                    <div class="flex-1 ms-3 text-light">Our friendly Support Team is available to help you 24 hours a day, seven days a week. We look forward to hearing from you! Our 24/7 support team is available to assist you with your any online presence needs.</div>
                  </div>
                </div>
                <div class="tab-pane fade" id="tab-contact" role="tabpanel" aria-labelledby="contact-tab">
                  <div class="d-flex align-items-center"><img src="assets/img/line-icons/monitor-3.svg" width="56" alt="" />
                    <div class="flex-1 ms-3 text-light">SEO is essential for every website for a long time business and marketing. We generally work on three keywords for SEO and charge a small amount. Usually, it will take 3-6 month for upgrading website search rank.</div>
                  </div>
                </div>
              </div>
              <div class="text-center text-lg-start"><a class="btn btn-outline-light hvr-sweep-top btn-sm mt-5 mt-lg-4 mb-2" href="pages/work.html">Our Latest Works</a></div>
            </div>
          </div>
        </div>
        <!-- end of .container

      </section>
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================
      <section>

        <div class="container" id = "how">
          <div class="row">
            <div class="col mb-4 text-center" data-zanim-timeline="{}" data-zanim-trigger="scroll">
              <div class="overflow-hidden">
                <h2 class="fs-md-5" data-zanim-xs='{"duration":1.5,"delay":0}'>how we did it</h2>
              </div>
              <div class="overflow-hidden">
                <p class="fs--1 text-uppercase text-black ls-1 mb-0" data-zanim-xs='{"duration":1.5,"delay":0.1}'>working together to achieve great results</p>
              </div>
              <div class="overflow-hidden">
                <hr class="hr-short bg-black" data-zanim-xs='{"duration":1.5,"delay":0.2}' />
              </div>
            </div>
          </div>
          <div class="row justify-content-center">
            <div class="col-lg-9 px-5">
              <div class="d-flex process-item border-dashed-start border-600 pb-5" data-zanim-timeline="{}" data-zanim-trigger="scroll">
                <div class="process-icon-circle"><img class="process-icon" src="assets/img/line-icons/medical-result.svg" alt="" /></div>
                <div class="flex-1 ms-4 ms-sm-5">
                  <h5 class="ls-2"><span class="bg-white pe-3">analysis &amp; planning</span></h5>
                  <p class="mb-0 pe-lg-5" data-zanim-xs='{"from":{"opacity":0},"to":{"opacity":1},"duration":1,"delay":0.2}'>At first, we will analyze the product and planning then convert to a full specification document that explains exactly what we will deliver to you. This process will include the technical &amp; functional requirements captured along with your branding and styling guidelines.</p>
                </div>
              </div>
              <div class="d-flex process-item border-dashed-start border-600 border-md-start-0 border-md-dashed-end pb-5" data-zanim-timeline="{}" data-zanim-trigger="scroll">
                <div class="flex-1 position-relative ms-4 ms-sm-5 ms-md-0 me-md-5">
                  <h5 class="text-md-end"><span class="bg-white ps-md-3">Design &amp; Development</span><span class="process-devider border-end-0 start-0"></span></h5>
                  <p class="mb-0 text-md-end ps-lg-6" data-zanim-xs='{"from":{"opacity":0},"to":{"opacity":1},"duration":1,"delay":0.3}'> This is the main production phase where we build the functionalities of your product. Once created, your product must pass through our quality assurance phase before you are finally presented with the finished deliverable.</p>
                </div>
                <div class="process-icon-circle"><img class="process-icon" src="assets/img/line-icons/web-programming.svg" alt="" /></div>
              </div>
              <div class="d-flex process-item border-dashed-start border-600 pb-5" data-zanim-timeline="{}" data-zanim-trigger="scroll">
                <div class="process-icon-circle"><img class="process-icon" src="assets/img/line-icons/technical-support.svg" alt="" /></div>
                <div class="flex-1 position-relative ms-4 ms-sm-5"><span class="process-devider border-start-0 end-0"></span>
                  <h5 class="ls-2"><span class="bg-white pe-3">Testing &amp; Fixing</span></h5>
                  <p class="mb-0 pe-lg-5" data-zanim-xs='{"from":{"opacity":0},"to":{"opacity":1},"duration":1,"delay":0.2}'>After building your product, we will review your product with our talented testing team. In these steps, we will test your product with different data set and conditions. Also, this phase is essential for fixing design and development issues. </p>
                </div>
              </div>
              <div class="d-flex process-item" data-zanim-timeline="{}" data-zanim-trigger="scroll">
                <div class="flex-1 position-relative ms-4 ms-sm-5 ms-md-0 me-md-5">
                  <h5 class="text-md-end"><span class="bg-white ps-md-3">LAUNCH &amp; GROW</span><span class="process-devider border-end-0 start-0"></span></h5>
                  <p class="mb-0 text-md-end ps-lg-5" data-zanim-xs='{"from":{"opacity":0},"to":{"opacity":1},"duration":1,"delay":0.3}'> The final step is where we launch your product to the production server. Here we consider components such as cloud architecture, performance, and cybersecurity if that is within that scope of your project. At this point, you are officially live!</p>
                </div>
                <div class="process-icon-circle"><img class="process-icon" src="assets/img/line-icons/server.svg" alt="" /></div>
              </div>
            </div>
          </div>
        </div>
        <!-- end of .container

      </section>
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================-->
	  <!--
      <section class="py-6 text-center bg-1100" data-zanim-timeline="{}" data-zanim-trigger="scroll">

        <div class="bg-holder overlay overlay-0" style="background-image:url(assets/img/backgrounds/fun-fact.jpg);background-position: bottom;">
        </div>
        <!--/.bg-holder

        <div class="container">
          <div class="overflow-hidden">
            <h3 class="font-base text-white" data-zanim-xs='{"duration":1.5,"delay":0}'>Excited To Start <br class="d-block d-sm-none" />Your Next Project?</h3>
          </div>
          <div data-zanim-xs='{"from":{"opacity":0,"y":30},"to":{"opacity":1,"y":0},"duration":1.5,"delay":0.1}'><a class="btn btn-sm btn-outline-light hvr-sweep-top mt-3" href="#">Let's get started </a></div>
        </div>
        <!-- end of .container

      </section>
	  
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================
      <section class="text-center pb-3" id="portfolio">

        <div class="container-fluid">
          <div class="row justify-content-center" data-zanim-timeline="{}" data-zanim-trigger="scroll">
            <div class="col-12 mb-4">
              <div class="overflow-hidden">
                <h2 class="fs-md-5 mb-2" data-zanim-xs='{"duration":1.5,"delay":0}'>recent projects</h2>
              </div>
              <div class="overflow-hidden">
                <p class="fs--1 text-uppercase text-black ls-1 mb-0" data-zanim-xs='{"duration":1.5,"delay":0.1}'>successful projects, happy clients, great results</p>
              </div>
              <div class="overflow-hidden">
                <hr class="hr-short bg-black opacity-100" data-zanim-xs='{"duration":1.5,"delay":0.2}' />
              </div>
            </div>
          </div>
          <ul class="nav font-sans-serif mb-3 justify-content-center" data-filter-nav="data-filter-nav">
            <li class="nav-item"><a class="isotope-nav text-uppercase font-base active" href="#!" data-filter="*">all</a></li>
            <li class="nav-item"> <a class="isotope-nav text-uppercase font-base" href="#!" data-filter=".photography">Photography</a></li>
            <li class="nav-item"> <a class="isotope-nav text-uppercase font-base" href="#!" data-filter=".design">Design</a></li>
            <li class="nav-item"> <a class="isotope-nav text-uppercase font-base" href="#!" data-filter=".mobile">Mobile</a></li>
            <li class="nav-item"> <a class="isotope-nav text-uppercase font-base" href="#!" data-filter=".marketing">Marketing </a></li>
          </ul>
          <div class="row g-3 mt-3" data-rp-isotope='{"layoutMode":"packery"}'>
            <div class="col-sm-6 col-lg-4 px-2 isotope-item design" data-zanim-xs='{"delay":0}'>
              <div class="hoverdir-item my-0" data-zanim-xs='{"duration":1.5,"animation":"zoom-in","delay":0}'>
                <div class="hoverdir-item-content"><a class="d-block" href="pages/work-single.html"><img class="img-fluid rounded" src="assets/img/projects/1.jpg" alt="" />
                    <div class="hoverdir-text">
                      <h3 class="text-white lh-1 fs-2">Website Design</h3>
                      <p class="ls-1 mb-0 text-700">Multipurpose HTML template <br> with bootstrap 5</p>
                    </div>
                  </a></div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-4 px-2 isotope-item design" data-zanim-xs='{"delay":0.1}'>
              <div class="hoverdir-item my-0" data-zanim-xs='{"duration":1.5,"animation":"zoom-in","delay":0}'>
                <div class="hoverdir-item-content"><a class="d-block" href="pages/work-single.html"><img class="img-fluid rounded" src="assets/img/projects/2.jpg" alt="" />
                    <div class="hoverdir-text">
                      <h3 class="text-white lh-1 fs-2">UI/UX Design</h3>
                      <p class="ls-1 mb-0 text-700">Most user friendly user <br> interface design</p>
                    </div>
                  </a></div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-4 px-2 isotope-item mobile" data-zanim-xs='{"delay":0.2}'>
              <div class="hoverdir-item my-0" data-zanim-xs='{"duration":1.5,"animation":"zoom-in","delay":0}'>
                <div class="hoverdir-item-content"><a class="d-block" href="pages/work-single.html"><img class="img-fluid rounded" src="assets/img/projects/3.jpg" alt="" />
                    <div class="hoverdir-text">
                      <h3 class="text-white lh-1 fs-2">Mobile Accessories</h3>
                      <p class="ls-1 mb-0 text-700">Popular mobile accessories <br> in 2021</p>
                    </div>
                  </a></div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-4 px-2 isotope-item photography" data-zanim-xs='{"delay":0.3}'>
              <div class="hoverdir-item my-0" data-zanim-xs='{"duration":1.5,"animation":"zoom-in","delay":0}'>
                <div class="hoverdir-item-content"><a class="d-block" href="pages/work-single.html"><img class="img-fluid rounded" src="assets/img/projects/4.jpg" alt="" />
                    <div class="hoverdir-text">
                      <h3 class="text-white lh-1 fs-2">Interior Photography</h3>
                      <p class="ls-1 mb-0 text-700">More than 50K happy <br> real state clients</p>
                    </div>
                  </a></div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-4 px-2 isotope-item mobile" data-zanim-xs='{"delay":0.4}'>
              <div class="hoverdir-item my-0" data-zanim-xs='{"duration":1.5,"animation":"zoom-in","delay":0}'>
                <div class="hoverdir-item-content"><a class="d-block" href="pages/work-single.html"><img class="img-fluid rounded" src="assets/img/projects/5.jpg" alt="" />
                    <div class="hoverdir-text">
                      <h3 class="text-white lh-1 fs-2">App Development</h3>
                      <p class="ls-1 mb-0 text-700">Most secured and optimized <br> mobile app development</p>
                    </div>
                  </a></div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-4 px-2 isotope-item marketing" data-zanim-xs='{"delay":0.5}'>
              <div class="hoverdir-item my-0" data-zanim-xs='{"duration":1.5,"animation":"zoom-in","delay":0}'>
                <div class="hoverdir-item-content"><a class="d-block" href="pages/work-single.html"><img class="img-fluid rounded" src="assets/img/projects/6.jpg" alt="" />
                    <div class="hoverdir-text">
                      <h3 class="text-white lh-1 fs-2">Content Writing</h3>
                      <p class="ls-1 mb-0 text-700"> More than 50K blog posts <br> on different subjects</p>
                    </div>
                  </a></div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-4 px-2 isotope-item design" data-zanim-xs='{"delay":0.6}'>
              <div class="hoverdir-item my-0" data-zanim-xs='{"duration":1.5,"animation":"zoom-in","delay":0}'>
                <div class="hoverdir-item-content"><a class="d-block" href="pages/work-single.html"><img class="img-fluid rounded" src="assets/img/projects/7.jpg" alt="" />
                    <div class="hoverdir-text">
                      <h3 class="text-white lh-1 fs-2">Packaging Designing</h3>
                      <p class="ls-1 mb-0 text-700">Beautiful packaging design done <br> by our designers</p>
                    </div>
                  </a></div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-4 px-2 isotope-item photography" data-zanim-xs='{"delay":0.35}'>
              <div class="hoverdir-item my-0" data-zanim-xs='{"duration":1.5,"animation":"zoom-in","delay":0}'>
                <div class="hoverdir-item-content"><a class="d-block" href="pages/work-single.html"><img class="img-fluid rounded" src="assets/img/projects/8.jpg" alt="" />
                    <div class="hoverdir-text">
                      <h3 class="text-white lh-1 fs-2">Model Photography</h3>
                      <p class="ls-1 mb-0 text-700">Exclusive model photography by <br> award winning photographers</p>
                    </div>
                  </a></div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-4 px-2 isotope-item design" data-zanim-xs='{"delay":0.4}'>
              <div class="hoverdir-item my-0" data-zanim-xs='{"duration":1.5,"animation":"zoom-in","delay":0}'>
                <div class="hoverdir-item-content"><a class="d-block" href="pages/work-single.html"><img class="img-fluid rounded" src="assets/img/projects/9.jpg" alt="" />
                    <div class="hoverdir-text">
                      <h3 class="text-white lh-1 fs-2">Digital Marketing</h3>
                      <p class="ls-1 mb-0 text-700">We spread your digital products <br> all over the world</p>
                    </div>
                  </a></div>
              </div>
            </div>
          </div>
        </div>
        <!-- end of .container

      </section>
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================-->
      <section class="py-5 text-center bg-black text-white" data-zanim-timeline="{}" data-zanim-trigger="scroll">

        <div class="container">
          <div class="overflow-hidden">
            <h4 class="fw-normal text-white mb-1" data-zanim-xs='{"duration":1.5,"delay":0}'>Want To See More<br class="d-block d-sm-none" /> From Our Works?</h4>
          </div>
          <div class="overflow-hidden">
            <p data-zanim-xs='{"duration":1.5,"delay":0.1}'>Our Team is always ready to help you</p>
          </div>
          <div data-zanim-xs='{"from":{"opacity":0,"y":30},"to":{"opacity":1,"y":0},"duration":1.5,"delay":0.5}'><a class="btn btn-outline-light btn-sm hvr-sweep-top mt-2" href="/projects">View All</a></div>
        </div>
        <!-- end of .container-->

      </section>
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================-->
      <section class="text-center" id="collaborations">

        <div class="container">
          <div class="row">
            <div class="col mb-4" data-zanim-timeline="{}" data-zanim-trigger="scroll">
              <div class="overflow-hidden">
                <h2 class="fs-md-5" data-zanim-xs='{"delay":0}'>OUR COLLABORATIONS</h2>
              </div>
              <div class="overflow-hidden">
                <p class="fs--1 text-uppercase text-black ls-1 mb-0" data-zanim-xs='{"delay":0.1}'>MEET OUR COLLABORATORS</p>
              </div>
              <div class="overflow-hidden">
                <hr class="hr-short bg-black" data-zanim-xs='{"delay":0.2}' />
              </div>
            </div>
          </div>
          <div class="row justify-content-center">
            <div class="col-lg-10">
              <div class="row">
                <?php foreach ($hp['collaborators'] as $c): ?>
                <div class="col-6 col-md-3 col-lg-3 px-2 team-item mb-4">
                  <a href="<?= htmlspecialchars($c['link_url'] ?: '#!') ?>" <?= $c['link_url'] ? 'target="_blank" rel="noopener"' : '' ?> class="d-block w-75 rounded-circle overflow-hidden mx-auto" style="cursor:pointer;">
                    <img class="img-fluid" src="<?= htmlspecialchars($c['image_url']) ?>" alt="<?= htmlspecialchars($c['name']) ?>" />
                  </a>
                  <h6 class="fw-bold mt-2 mb-1"><a class="text-black" href="<?= htmlspecialchars($c['link_url'] ?: '#!') ?>" <?= $c['link_url'] ? 'target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($c['name']) ?></a></h6>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <!-- end of .container-->

      </section>
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================-->
      <section class="testimonial">

        <div class="bg-holder overlay overlay-0" style="background-image:url(<?= htmlspecialchars($hp['review_bg']) ?>);">
        </div>
        <!--/.bg-holder-->

        <div class="container">
          <div class="row justify-content-center">
            <div class="col-12 mb-4 text-center" data-zanim-timeline="{}" data-zanim-trigger="scroll">
              <div class="overflow-hidden">
                <h2 class="fs-md-5 text-white mb-2" data-zanim-xs='{"duration":1.5,"delay":0}'>Our Client</h2>
              </div>
              <div class="overflow-hidden">
                <p class="fs--1 text-uppercase text-white ls-1 mb-0" data-zanim-xs='{"duration":1.5,"delay":0.1}'>see what our top valuable clients say about us</p>
              </div>
              <div class="overflow-hidden">
                <hr class="hr-short bg-white" data-zanim-xs='{"duration":1.5,"delay":0.2}' />
              </div>
            </div>
            <div class="col-sm-10 col-lg-8 text-center text-md-start px-sm-0">
              <div class="border rounded px-3 px-sm-4 px-md-6 py-5 mt-5">
                <div class="testimonial-quote"></div>
                <div class="rounded-circle testimonial-avatar" style="display:none;"></div>
                <div class="swiper-testimonial-container">
                  <div class="swiper-nav d-none d-lg-block">
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                  </div>
                  <div class="swiper testimonial-slider" data-swiper='{"navigation":{"nextEl":".swiper-testimonial-container .swiper-button-next","prevEl":".swiper-testimonial-container .swiper-button-prev"},"spaceBetween":5,"loop":true,"loopedSlides":5,"slideToClickedSlide":true}'>
                    <div class="swiper-wrapper">
                      <?php foreach ($hp['reviews'] as $r): ?>
                      <div class="swiper-slide">
                        <div class="item px-2" data-avatar="<?= htmlspecialchars($r['avatar_url']) ?>">
                          <div class="row g-4 align-items-center">
                            <?php if (!empty($r['photo_url'])): ?>
                            <div class="col-12 col-md-5">
                              <div class="rounded overflow-hidden mx-auto mx-md-0" style="aspect-ratio:4/3;">
                                <img src="<?= htmlspecialchars($r['photo_url']) ?>" alt="<?= htmlspecialchars($r['name']) ?>" class="w-100 h-100" style="object-fit:cover;">
                              </div>
                            </div>
                            <?php endif; ?>
                            <div class="col-12<?= !empty($r['photo_url']) ? ' col-md-7' : '' ?>">
                              <p class="text-white font-base mb-3"><?= htmlspecialchars($r['quote']) ?></p>
                              <div class="d-flex align-items-center gap-2">
                                <img src="<?= htmlspecialchars($r['avatar_url']) ?>" alt="" class="rounded-circle" width="40" height="40" style="object-fit:cover;">
                                <div>
                                  <h4 class="text-white fw-normal mb-0 fs-1 ls-1"><?= htmlspecialchars($r['name']) ?></h4>
                                  <p class="fs--1 text-uppercase mb-0 ls-2" style="color:#c9a15c;"><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></p>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination d-block d-lg-none"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- end of .container-->

      </section>
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================
      <section class="text-center" id="pricing">

        <div class="container">
          <div class="row" data-zanim-timeline="{}" data-zanim-trigger="scroll">
            <div class="col mb-4">
              <div class="overflow-hidden">
                <h2 class="fs-md-5" data-zanim-xs='{"duration":1.5,"delay":0}'>Our Pricing</h2>
              </div>
              <div class="overflow-hidden">
                <p class="fs--1 text-uppercase text-black ls-1 mb-0" data-zanim-xs='{"duration":1.5,"delay":0.1}'>Amazing services at affordable price</p>
              </div>
              <div class="overflow-hidden">
                <hr class="hr-short bg-black" data-zanim-xs='{"duration":1.5,"delay":0.2}' />
              </div>
            </div>
          </div>
          <div class="row" data-zanim-timeline="{}" data-zanim-trigger="scroll">
            <div class="col-sm-6 col-lg-3 mb-4 mb-lg-0 h-100">
              <div class="border border-200 rounded p-4 mx-auto" style="max-width: 300px;"><img src="assets/img/line-icons/helicopter.svg" width="56" alt="" />
                <h5 class="mt-3">standard</h5>
                <h2 class="fw-bold fs-5"><sup class="fs-1">$</sup><span class="font-base">199</span><small class="fs-0 text-lowercase">/ m</small></h2>
                <hr />
                <ul class="list-unstyled">
                  <li class="py-2">Up to 5 Pages</li>
                  <li class="py-2">1 Year Hosting</li>
                  <li class="py-2">3 Months Support</li>
                  <li class="py-2 text-400">SEO</li>
                  <li class="py-2 text-400">Security &amp; Backups</li>
                  <li class="py-2 text-400">24/7 Support</li>
                </ul>
                <div class="d-grid"><a class="btn btn-sm btn-outline-secondary mt-5 hvr-sweep-top" href="#!">get started</a></div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-4 mb-lg-0">
              <div class="border border-200 rounded p-4 mx-auto" style="max-width: 300px;">
                <div class="text-center"><img src="assets/img/line-icons/helicopter.svg" width="56" alt="" /></div>
                <h5 class="mt-3">standard</h5>
                <h2 class="fw-bold fs-5"><sup class="fs-1">$</sup><span class="font-base">499</span><small class="fs-0 text-lowercase">/ m</small></h2>
                <hr />
                <ul class="list-unstyled">
                  <li class="py-2">Up to 5 Pages</li>
                  <li class="py-2">1 Year Hosting</li>
                  <li class="py-2">3 Months Support</li>
                  <li class="py-2">SEO</li>
                  <li class="py-2 text-400">Security &amp; Backups</li>
                  <li class="py-2 text-400">24/7 Support</li>
                </ul>
                <div class="d-grid"><a class="btn btn-sm btn-outline-secondary mt-5 hvr-sweep-top" href="#!">get started</a></div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-4 mb-sm-0">
              <div class="border border-black rounded p-4 mx-auto" style="max-width: 300px;" data-zanim-xs='{"from":{"opacity":0,"y":20},"to":{"opacity":1,"y":0},"duration":1.5,"delay":0}'>
                <div class="text-center"><img src="assets/img/line-icons/airplane.svg" width="56" alt="" /></div>
                <h5 class="mt-3 ls-3">premium</h5>
                <h2 class="fw-bold fs-5"><sup class="fs-1">$</sup><span class="font-base">799</span><small class="fs-0 text-lowercase">/ m</small></h2>
                <hr class="bg-black opacity-100" />
                <ul class="list-unstyled">
                  <li class="py-2">Up to 5 Pages</li>
                  <li class="py-2">1 Year Hosting</li>
                  <li class="py-2">3 Months Support</li>
                  <li class="py-2">SEO</li>
                  <li class="py-2">Security &amp; Backups</li>
                  <li class="py-2 text-400">24/7 Support</li>
                </ul>
                <div class="d-grid"><a class="btn btn-sm btn-dark mt-5 hvr-sweep-top" href="#!">get started</a></div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-4 mb-sm-0">
              <div class="border border-200 rounded p-4 mx-auto" style="max-width: 300px;"><img src="assets/img/line-icons/pricing-rocket.svg" width="56" alt="" />
                <h5 class="mt-3">ultimate</h5>
                <h2 class="fw-bold fs-5"><sup class="fs-1">$</sup><span class="font-base">999</span><small class="fs-0 text-lowercase">/ m</small></h2>
                <hr />
                <ul class="list-unstyled">
                  <li class="py-2">Up to 5 Pages</li>
                  <li class="py-2">1 Year Hosting</li>
                  <li class="py-2">3 Months Support</li>
                  <li class="py-2">SEO</li>
                  <li class="py-2">Security &amp; Backups</li>
                  <li class="py-2">24/7 Support</li>
                </ul>
                <div class="d-grid"><a class="btn btn-sm btn-outline-secondary mt-5 hvr-sweep-top" href="#!">get started</a></div>
              </div>
            </div>
          </div>
        </div>
        <!-- end of .container

      </section>
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================-->
      <section class="bg-black py-6 clients">

        <div class="container">
          <div class="swiper-container swiper-clients">
            <div class="swiper-nav d-none d-lg-block">
              <div class="swiper-button-prev"></div>
              <div class="swiper-button-next"></div>
            </div>
            <div class="swiper" data-swiper='{"navigation":{"nextEl":".swiper-container .swiper-button-next","prevEl":".swiper-container .swiper-button-prev"},"autoplay":true,"loop":true,"slidesPerView":2,"grabCursor":true,"breakpoints":{"576":{"slidesPerView":3},"768":{"slidesPerView":3},"992":{"slidesPerView":4},"1200":{"slidesPerView":6}}}'>
              <div class="swiper-wrapper">
                <?php if (!empty($hp['partner_logos'])): ?>
                  <?php foreach ($hp['partner_logos'] as $logo): ?>
                    <div class="swiper-slide d-flex justify-content-center" style="width:140px"><img src="<?= htmlspecialchars($logo['image_url']) ?>" alt="" width="130" /></div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="swiper-slide d-flex justify-content-center" style="width:140px"><img src="assets/img/favicons/svashta_symbol_white.png" alt="" width="130" /></div>
                <?php endif; ?>
              </div>
              <div class="swiper-pagination d-block d-lg-none"></div>
            </div>
          </div>
        </div>
        <!-- end of .container-->

      </section>
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================
      <section id="blog">

        <div class="container">
          <div class="row">
            <div class="col text-center mb-4" data-zanim-timeline="{}" data-zanim-trigger="scroll">
              <div class="overflow-hidden">
                <h2 class="fs-md-5" data-zanim-xs='{"duration":1.5,"delay":0}'>From the blog</h2>
              </div>
              <div class="overflow-hidden">
                <p class="fs--1 text-uppercase text-black ls-1 mb-0" data-zanim-xs='{"duration":1.5,"delay":0.1}'>Awesome articles from the blog</p>
              </div>
              <div class="overflow-hidden">
                <hr class="hr-short bg-black" data-zanim-xs='{"duration":1.5,"delay":0.2}' />
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 col-lg-4 h-100 mb-4"><a href="pages/blog.html"> <img class="img-fluid rounded-top" src="assets/img/blogs/1.jpg" alt="" /></a>
              <div class="p-3 border rounded-bottom border-top-0">
                <h5 class="font-base text-transform-none fw-medium lh-1"><a class="text-black" href="pages/blog.html">23 Top HTML Landing Page Templates 2019.</a></h5>
                <p>Landing pages are the essential part of an online marketing campaign. A landing page is a page where a visitor comes to a ...</p><a class="text-dark fw-semi-bold" href="pages/blog.html">Read more<span class="fas fa-angle-right ms-1 text-900" data-fa-transform="down-2"></span></a>
                <hr />
                <div class="row justify-content-between align-items-center">
                  <div class="col pe-0">
                    <div class="d-flex"><a href="#!"><img class="rounded-circle" src="assets/img/team/1.jpg" width="40" height="40" alt="" /></a>
                      <div class="flex-1 ms-3">
                        <h6 class="mb-0 fw-semi-bold ls-1"><a class="text-dark" href="#!">Kit Harington</a></h6>
                        <p class="mb-0">6 Feb, 2019</p>
                      </div>
                    </div>
                  </div>
                  <div class="col-auto"><a class="mb-0 d-flex align-items-center d-block text-800" href="#!"><span class="me-2">86</span><img src="assets/img/line-icons/comment-inactive.svg" width="15" alt="" /></a></div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-4 h-100 mb-4"><a href="pages/blog.html"> <img class="img-fluid rounded-top" src="assets/img/blogs/2.jpg" alt="" /></a>
              <div class="p-3 border rounded-bottom border-top-0">
                <h5 class="font-base text-transform-none fw-medium lh-1"><a class="text-black" href="pages/blog.html">Testing Working Robots at the Canadian Agency.</a></h5>
                <p>Burgas was the first city in Bulgaria funded by the European Union on the program called “Regional development 2019 ...</p><a class="text-dark fw-semi-bold" href="pages/blog.html">Read more<span class="fas fa-angle-right ms-1 text-900" data-fa-transform="down-2"></span></a>
                <hr />
                <div class="row justify-content-between align-items-center">
                  <div class="col pe-0">
                    <div class="d-flex"><a href="#!"><img class="rounded-circle" src="assets/img/team/2.jpg" width="40" height="40" alt="" /></a>
                      <div class="flex-1 ms-3">
                        <h6 class="mb-0 fw-semi-bold ls-1"><a class="text-dark" href="#!">Emilla Clarke</a></h6>
                        <p class="mb-0">4 Feb, 2019</p>
                      </div>
                    </div>
                  </div>
                  <div class="col-auto"><a class="mb-0 d-flex align-items-center d-block text-800" href="#!"><span class="me-2">45</span><img src="assets/img/line-icons/comment-inactive.svg" width="15" alt="" /></a></div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-4 h-100 mb-4"><a href="pages/blog.html"> <img class="img-fluid rounded-top" src="assets/img/blogs/3.jpg" alt="" /></a>
              <div class="p-3 border rounded-bottom border-top-0">
                <h5 class="font-base text-transform-none fw-medium lh-1"><a class="text-black" href="pages/blog.html">Why does every website need to be accessible?</a></h5>
                <p>Maybe I should introduce myself first to give my words that bit of credibility a self-published article on the internet can have ...</p><a class="text-dark fw-semi-bold" href="pages/blog.html">Read more<span class="fas fa-angle-right ms-1 text-900" data-fa-transform="down-2"></span></a>
                <hr />
                <div class="row justify-content-between align-items-center">
                  <div class="col pe-0">
                    <div class="d-flex"><a href="#!"><img class="rounded-circle" src="assets/img/team/7.jpg" width="40" height="40" alt="" /></a>
                      <div class="flex-1 ms-3">
                        <h6 class="mb-0 fw-semi-bold ls-1"><a class="text-dark" href="#!">Alfie Allen</a></h6>
                        <p class="mb-0">1 Feb, 2019</p>
                      </div>
                    </div>
                  </div>
                  <div class="col-auto"><a class="mb-0 d-flex align-items-center d-block text-800" href="#!"><span class="me-2">73</span><img src="assets/img/line-icons/comment-inactive.svg" width="15" alt="" /></a></div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-4 h-100 mb-4 mb-md-0"><a href="pages/blog.html"> <img class="img-fluid rounded-top" src="assets/img/blogs/4.jpg" alt="" /></a>
              <div class="p-3 border rounded-bottom border-top-0">
                <h5 class="font-base text-transform-none fw-medium lh-1"><a class="text-black" href="pages/blog.html">We Are Figuring Out How to Make Google Sweat?</a></h5>
                <p>The Wild West era may be drawing to a close for tech corporations like Facebook and Google. New scrutiny from New York ...</p><a class="text-dark fw-semi-bold" href="pages/blog.html">Read more<span class="fas fa-angle-right ms-1 text-900" data-fa-transform="down-2"></span></a>
                <hr />
                <div class="row justify-content-between align-items-center">
                  <div class="col pe-0">
                    <div class="d-flex"><a href="#!"><img class="rounded-circle" src="assets/img/team/4.jpg" width="40" height="40" alt="" /></a>
                      <div class="flex-1 ms-3">
                        <h6 class="mb-0 fw-semi-bold ls-1"><a class="text-dark" href="#!">Peter Parker</a></h6>
                        <p class="mb-0">25 Jan, 2019</p>
                      </div>
                    </div>
                  </div>
                  <div class="col-auto"><a class="mb-0 d-flex align-items-center d-block text-800" href="#!"><span class="me-2">43</span><img src="assets/img/line-icons/comment-inactive.svg" width="15" alt="" /></a></div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-4 h-100 mb-4 mb-md-0"><a href="pages/blog.html"> <img class="img-fluid rounded-top" src="assets/img/blogs/5.jpg" alt="" /></a>
              <div class="p-3 border rounded-bottom border-top-0">
                <h5 class="font-base text-transform-none fw-medium lh-1"><a class="text-black" href="pages/blog.html">I Don’t Care What Marie Kondo Thinks of My Space.</a></h5>
                <p>Staring out from behind my laptop perched atop a small table in the middle of my studio apartment, I can show you where ...</p><a class="text-dark fw-semi-bold" href="pages/blog.html">Read more<span class="fas fa-angle-right ms-1 text-900" data-fa-transform="down-2"></span></a>
                <hr />
                <div class="row justify-content-between align-items-center">
                  <div class="col pe-0">
                    <div class="d-flex"><a href="#!"><img class="rounded-circle" src="assets/img/team/5.jpg" width="40" height="40" alt="" /></a>
                      <div class="flex-1 ms-3">
                        <h6 class="mb-0 fw-semi-bold ls-1"><a class="text-dark" href="#!">John Bradley</a></h6>
                        <p class="mb-0">17 Jan, 2019</p>
                      </div>
                    </div>
                  </div>
                  <div class="col-auto"><a class="mb-0 d-flex align-items-center d-block text-800" href="#!"><span class="me-2">13</span><img src="assets/img/line-icons/comment-inactive.svg" width="15" alt="" /></a></div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-4 h-100"><a href="pages/blog.html"> <img class="img-fluid rounded-top" src="assets/img/blogs/6.jpg" alt="" /></a>
              <div class="p-3 border rounded-bottom border-top-0">
                <h5 class="font-base text-transform-none fw-medium lh-1"><a class="text-black" href="pages/blog.html">What Your Microbiome Really Needs Is Fiber!</a></h5>
                <p>In recent years, we’ve begun to learn that most everything we eat — from probiotic yogurt to a serving of asparagus to a pork ...</p><a class="text-dark fw-semi-bold" href="pages/blog.html">Read more<span class="fas fa-angle-right ms-1 text-900" data-fa-transform="down-2"></span></a>
                <hr />
                <div class="row justify-content-between align-items-center">
                  <div class="col pe-0">
                    <div class="d-flex"><a href="#!"><img class="rounded-circle" src="assets/img/team/6.jpg" width="40" height="40" alt="" /></a>
                      <div class="flex-1 ms-3">
                        <h6 class="mb-0 fw-semi-bold ls-1"><a class="text-dark" href="#!">Peter Jackson</a></h6>
                        <p class="mb-0">1 Jan, 2019</p>
                      </div>
                    </div>
                  </div>
                  <div class="col-auto"><a class="mb-0 d-flex align-items-center d-block text-800" href="#!"><span class="me-2">13</span><img src="assets/img/line-icons/comment-inactive.svg" width="15" alt="" /></a></div>
                </div>
              </div>
            </div>
          </div>
          <div class="text-center"><a class="btn btn-sm btn-outline-dark hvr-sweep-top mt-5" href="#!">Load More</a></div>
        </div>
        <!-- end of .container

      </section>
      <!-- <section> close ============================-->
      <!-- ============================================-->




      <!-- ============================================-->
      <!-- <section> begin ============================-->
      <!-- ============================================-->
      <!-- <section> begin ============================
      <section id="contact">

        <div class="container">
          <div class="row">
            <div class="col text-center mb-4" data-zanim-timeline="{}" data-zanim-trigger="scroll">
              <div class="overflow-hidden">
                <h2 class="fs-md-5" data-zanim-xs='{"duration":1.5,"delay":0}'>Drop Us A Line</h2>
              </div>
              <div class="overflow-hidden">
                <p class="fs--1 text-uppercase text-black ls-1 mb-0" data-zanim-xs='{"duration":1.5,"delay":0.1}'>we are happy to listen from you anytime</p>
              </div>
              <div class="overflow-hidden">
                <hr class="hr-short border-black" data-zanim-xs='{"duration":1.5,"delay":0.2}' />
              </div>
            </div>
          </div>
          <div class="row justify-content-center">
            <div class="col-lg-6 mb-5 mb-lg-0 d-flex flex-column justify-content-between">
              <div class="row">
                <div class="col-12">
                  <h5 class="mb-3">Connect With Us</h5>
                </div>
                <div class="col-auto mb-2 mb-sm-0" style="width: 190px">
                  <div class="row">
                    <div class="col-1"><span class="fas fa-location-arrow text-700"></span></div>
                    <div class="col px-2">
                      <p class="mb-1 text-700"><strong>Svashta Home - Bespoke Fine Furnishings</strong></p>
                      <p class="mb-0 text-600">Ruko 92 Avenix A/11 - BSD City, Kab. Tangerang, Banten, Indonesia</p>
                    </div>
                  </div>
                </div>
                <div class="col-auto" style="width: 245px">
                  <div class="row mb-2 mb-sm-1">
                    <div class="col-1"><span class="fas fa-phone me-2 text-700"> </span></div>
                    <div class="col px-2"><a class="text-600" href="https://wa.me/6281320300880">0813-2030-0880</a><br /></div>
                  </div>
                  <div class="row">
                    <div class="col-1"><span class="fas fa-envelope me-2 text-700"></span></div>
                    <div class="col px-2"><a class="text-600" href="mailto:project@svashtahome.com">project@svashtahome.com</a></div>
                  </div>
                </div>
              </div>
              <div class="googlemap rounded data-map mt-4" data-latlng="48.8583701,2.2922873,17" data-scrollwheel="false" data-icon="assets/img/map-marker.png" data-zoom="17" data-theme="Default" style="min-height: 14.63rem;">
                <div class="marker-content py-3">
                  <h5>Eiffel Tower</h5>
                  <p>Gustave Eiffel's iconic, wrought-iron 1889 tower,<br /> with steps and elevators to observation decks.</p>
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <h5 class="mb-3">Feel free to drop us a line!</h5>
              <form class="zform text-left" method="post">
                <input type="hidden" name="to" value="username@domain.extension" />
                <div class="form-group mb-3">
                  <input class="fs-0 form-control" type="text" placeholder="Your Name" required="required" />
                </div>
                <div class="form-group mb-3">
                  <input class="fs-0 form-control" type="email" placeholder="Email Address" required="required" />
                </div>
                <div class="form-group mb-3">
                  <textarea class="fs-0 form-control contact-message" rows="8" placeholder="Type your message here" required="required"></textarea>
                </div>
                <div class="zform-feedback d-grid">
                  <button class="btn btn-dark hvr-sweep-top" type="submit">Give us a shot</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <!-- end of .container

      </section>
      <!-- <section> close ============================-->
      <!-- ============================================-->


    </main>
    <!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->




    <!--===============================================-->
    <!--    Footer-->
    <!--===============================================-->


    <!-- ============================================-->
    <!-- <section> begin ============================-->
    <section class="bg-1100 py-6 pb-9 px-3 px-lg-0" id = "reach-us">

      <div class="container">
        <div class="row">
          <div class="col-lg-12">
            <div class="row">
              <div class="col-6 col-md-3 ps-lg-4 mb-4 mb-lg-0">
                <h5 class="text-white mb-3">Company</h5>
                <ul class="list-unstyled mb-0">
                  <li class="mb-1"><a class="text-700 hover-color-white" href="index.html#about-us">About</a></li>
                  <li class="mb-1"><a class="text-700 hover-color-white" href="index.html#services">Our Services</a></li>
                  <li class="mb-1"><a class="text-700 hover-color-white" href="index.html">Certifications</a></li>
                  <li class="mb-1"><a class="text-700 hover-color-white" href="index.html">Careers</a></li>
                </ul>
              </div>
              <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

				<div class="col-6 col-md-3 ps-4 mb-4 mb-lg-0">
				  <h5 class="text-white mb-3">Contact US</h5>
				  <ul class="list-unstyled text-white">
					<li class="mb-2 d-flex align-items-center">
					  <img src="https://img.icons8.com/ios-filled/24/ffffff/whatsapp.png" alt="WhatsApp" class="me-2">
					  <a href="https://wa.me/6281320300880" class="text-white text-decoration-none" target="_blank">0813-2030-0880</a>
					</li>
					<li class="mb-2 d-flex align-items-center">
					  <img src="https://img.icons8.com/ios-filled/24/ffffff/instagram-new--v1.png" alt="Instagram" class="me-2">
					  <a href="https://instagram.com/svashta_home" class="text-white text-decoration-none" target="_blank">@svashta_home</a>
					</li>
					<li class="mb-2 d-flex align-items-center">
					  <img src="https://img.icons8.com/ios-filled/24/ffffff/youtube-play.png" alt="YouTube" class="me-2">
					  <a href="https://youtube.com/@SvashtaHome" class="text-white text-decoration-none" target="_blank">Svashta Home</a>
					</li>
					<li class="mb-2 d-flex align-items-center">
					  <img src="https://img.icons8.com/ios-filled/24/ffffff/domain.png" alt="Website" class="me-2">
					  <a href="https://www.svashtahome.com" class="text-white text-decoration-none" target="_blank">svashtahome.com</a>
					</li>
					<li class="mb-2 d-flex align-items-center">
					  <img src="https://img.icons8.com/ios-filled/24/ffffff/new-post.png" alt="Email" class="me-2">
					  <a href="mailto:project@svashtahome.com" class="text-white text-decoration-none">project@svashtahome.com</a>
					</li>
					<li class="mb-2 d-flex align-items-center">
					  <img src="https://img.icons8.com/ios-filled/24/ffffff/link.png" alt="Linktree" class="me-2">
					  <a href="https://linktr.ee/svashtahome" class="text-white text-decoration-none" target="_blank">svashtahome</a>
					</li>
					<li class="mb-2 d-flex align-items-center">
                        <img src="https://img.icons8.com/ios-filled/24/ffffff/marker.png" alt="Location" class="me-2">
                        <a href="https://www.google.com/maps/place/Svashta+Home+-+Bespoke+Fine+Furnishings/@-6.3170114,106.6476314,17z/data=!3m1!4b1!4m6!3m5!1s0x2e69e56a7880e9bb:0x100b633685e43986!8m2!3d-6.3170168!4d106.6525023!16s%2Fg%2F11sv6dj5ls?entry=ttu&g_ep=EgoyMDI1MDUwNy4wIKXMDSoASAFQAw%3D%3D"
                            class="text-white text-decoration-none"
                            target="_blank" rel="noopener noreferrer">
                            Ruko 92 Avenix A/11 - BSD City, Kab. Tangerang, Banten, Indonesia
                        </a>
                    </li>
				  </ul>
				</div>
              <div class="col-md-6 ps-md-4">
                <h5 class="text-white mb-3">FROM THE BLOG</h5>
                <ul class="list-unstyled mb-0">
                  <?php if (!empty($hp['latest_posts'])): ?>
                    <?php foreach ($hp['latest_posts'] as $fp): ?>
                      <li class="mb-3">
                        <a class="text-700 hover-color-white" href="/blog/<?= urlencode($fp['slug']) ?>"><?= htmlspecialchars($fp['title']) ?></a>
                        <p class="text-900 hover-color-white mb-0"><?= htmlspecialchars(date('M j, Y', strtotime($fp['post_date']))) ?></p>
                      </li>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <li class="mb-3"><a class="text-700 hover-color-white" href="/blog">Lihat semua artikel →</a></li>
                  <?php endif; ?>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- end of .container-->

    </section>
    <!-- <section> close ============================-->
    <!-- ============================================-->


    <footer class="footer text-center bg-black py-3 position-absolute w-100 bottom-0">
      <div class="container">
        <div class="row center">
          <div class="col-12 col-md-auto mb-1 mb-md-0">
            <p class="mb-0">Copyright © 2024 Svashta Home. All Rights Reserved.
          </div>
        </div>
      </div>
    </footer><a class="btn-back-to-top" href="#top"><img src="assets/img/line-icons/upload-arrow.svg" width="8" alt=""></a>

    <!-- ===============================================-->
    <!--    JavaScripts-->
    <!-- ===============================================-->
    <script src="vendors/popper/popper.min.js"></script>
    <script src="vendors/bootstrap/bootstrap.min.js"></script>
    <script src="vendors/anchorjs/anchor.min.js"></script>
    <script src="vendors/is/is.min.js"></script>
    <script src="vendors/bigpicture/BigPicture.js"></script>
    <script src="vendors/countup/countUp.umd.js"></script>
    <script src="vendors/progressbar/progressbar.min.js"></script>
    <script src="vendors/hover-dir/hoverDir.min.js"></script>
    <script src="vendors/isotope-layout/isotope.pkgd.min.js"></script>
    <script src="vendors/isotope-packery/packery-mode.pkgd.min.js"></script>
    <script src="vendors/swiper/swiper-bundle.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyARdVcREeBK44lIWnv5-iPijKqvlSAVwbw&callback=initMap" async></script>
    <script src="vendors/fontawesome/all.min.js"></script>
    <script src="vendors/lodash/lodash.min.js"></script>
    <script src="vendors/imagesloaded/imagesloaded.pkgd.min.js"></script>
    <script src="vendors/gsap/gsap.js"></script>
    <script src="vendors/gsap/customEase.js"></script>
    <script src="assets/js/theme.js"></script>
	<style>

        html, body {
        max-width: 100%;
        overflow-x: hidden;
        }

   /* WhatsApp Floating Button */
    .whatsapp-float {
      position: fixed;
      width: 40px;
      height: 40px;
      bottom: 20px;
      right: 20px;
      background-color: #25d366;
      color: white;
      border-radius: 50px;
      text-align: center;
      box-shadow: 2px 2px 3px #999;
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: transform 0.3s;
    }

    .whatsapp-float:hover {
      transform: scale(1.1);
    }

    .whatsapp-float img {
      width: 35px;
      height: 35px;
    }

    /* Speaker Toggle */
    .speaker-toggle {
      position: fixed;
      width: 40px;
      height: 40px;
      bottom: 90px;
      right: 20px;
      background-color: #808080;
      border-radius: 40px;
      text-align: center;
      box-shadow: 2px 2px 3px #999;
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: transform 0.3s;
      cursor: pointer;
    }

    .speaker-toggle:hover {
      transform: scale(1.1);
    }

    .speaker-toggle img {
      width: 35px;
      height: 35px;
    }

    /* Optional: adjust spacing for small screens */
    @media (max-width: 375px) {
      .whatsapp-float,
      .speaker-toggle {
        right: 10px;
      }
    }
  </style>
<!-- Background Music -->
  <audio id="background-music" loop >
    <source src="es_creamer.mp3" type="audio/mpeg">
    Your browser does not support the audio element.
  </audio>

  <!-- Speaker Button -->
  <div class="speaker-toggle" onclick="toggleAudio()" aria-label="Toggle Music">
    <img id="speaker-icon" src="https://img.icons8.com/ios-filled/50/ffffff/speaker.png" alt="Speaker" />
  </div>
  <!-- WhatsApp Floating Button -->
  <a
    href="https://wa.me/6281320300880?text=Hello%20Svashta%20Home%20I'm%20Interested%20in%20the%20Product"
    class="whatsapp-float"
    target="_blank"
    rel="noopener noreferrer"
    aria-label="Chat on WhatsApp"
  >
    <img src="https://img.icons8.com/ios-filled/50/ffffff/whatsapp.png" alt="WhatsApp Icon" />
  </a>
<script>
  const music = document.getElementById("background-music");
  const speakerIcon = document.getElementById("speaker-icon");
  const playMusicButton = document.querySelector(".play-music-button");
  let isPlaying = false;

  // This function will be called when the speaker button is clicked
  function toggleAudio() {
    if (isPlaying) {
      music.pause();
      speakerIcon.src = "https://img.icons8.com/ios-filled/50/ffffff/mute.png";
    } else {
      music.play().then(() => {
        speakerIcon.src = "https://img.icons8.com/ios-filled/50/ffffff/speaker.png";
      }).catch(error => {
        console.error("Error playing audio:", error);
      });
    }
    isPlaying = !isPlaying;
  }

  // Function to start the music when the user clicks "Play Music"
  function startMusic() {
    music.play().then(() => {
      console.log("Music started after user interaction.");
      playMusicButton.style.display = "none"; // Hide the button after music starts
    }).catch(error => {
      console.error("Error starting music:", error);
    });
  }

  // Attempt to play the music as soon as the page loads
  document.addEventListener("DOMContentLoaded", function() {
    music.play().then(() => {
      console.log("Music started automatically.");
    }).catch(error => {
      console.error("Autoplay failed, showing play button:", error);
      // Show the Play Music button if autoplay is blocked
      playMusicButton.style.display = "block";
    });
  });
  
  // Function to start music on page load
    function startMusicOnLoad() {
      music.play().then(() => {
        console.log("Music started automatically.");
        music.muted = false;  // Unmute after it starts playing
      }).catch(error => {
        console.error("Autoplay failed, showing play button:", error);
        // Show the Play Music button if autoplay is blocked
        playMusicButton.style.display = "block";
      });
    }
  
</script>
  </body>

</html>