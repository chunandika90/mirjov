    </main>

    <?php
    $footerLatestPosts = [];
    try {
        $footerLatestPosts = db()->query('SELECT title, slug, COALESCE(published_at, created_at) AS post_date FROM blog_posts ORDER BY COALESCE(published_at, created_at) DESC LIMIT 3')->fetchAll();
    } catch (Throwable $e) {
        // DB belum siap — footer tetap tampil tanpa daftar artikel
    }
    ?>

    <section class="bg-1100 py-6 pb-9 px-3 px-lg-0" id="reach-us">
      <div class="container">
        <div class="row">
          <div class="col-lg-12">
            <div class="row">
              <div class="col-6 col-md-3 ps-lg-4 mb-4 mb-lg-0">
                <h5 class="text-white mb-3">Company</h5>
                <ul class="list-unstyled mb-0">
                  <li class="mb-1"><a class="text-700 hover-color-white" href="/#about-us">About</a></li>
                  <li class="mb-1"><a class="text-700 hover-color-white" href="/#services">Our Services</a></li>
                </ul>
              </div>
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
                    <img src="https://img.icons8.com/ios-filled/24/ffffff/domain.png" alt="Website" class="me-2">
                    <a href="https://www.svashtahome.com" class="text-white text-decoration-none" target="_blank">svashtahome.com</a>
                  </li>
                  <li class="mb-2 d-flex align-items-center">
                    <img src="https://img.icons8.com/ios-filled/24/ffffff/new-post.png" alt="Email" class="me-2">
                    <a href="mailto:project@svashtahome.com" class="text-white text-decoration-none">project@svashtahome.com</a>
                  </li>
                </ul>
              </div>
              <div class="col-6 col-md-3 ps-md-4 mb-4 mb-lg-0">
                <h5 class="text-white mb-3">FROM THE BLOG</h5>
                <ul class="list-unstyled mb-0" id="footer-blog-list">
                  <?php foreach ($footerLatestPosts as $fp): ?>
                    <li class="mb-3">
                      <a class="text-700 hover-color-white" href="/blog/<?= urlencode($fp['slug']) ?>"><?= htmlspecialchars($fp['title']) ?></a>
                      <p class="text-900 hover-color-white mb-0"><?= htmlspecialchars(date('M j, Y', strtotime($fp['post_date']))) ?></p>
                    </li>
                  <?php endforeach; ?>
                  <li class="mb-3"><a class="text-700 hover-color-white" href="/blog">Lihat semua artikel →</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <footer class="footer text-center bg-black py-3 position-relative w-100 bottom-0">
      <div class="container">
        <div class="row justify-content-between">
          <div class="col-12 col-md-auto mb-1 mb-md-0">
            <p class="mb-0">COPYRIGHT © SVASHTAHOME <?= date('Y') ?><span class="mx-2"></span></p>
          </div>
        </div>
      </div>
    </footer><a class="btn-back-to-top" href="#top"><img src="/assets/img/line-icons/upload-arrow.svg" width="8" alt=""></a>

    <script src="/vendors/popper/popper.min.js"></script>
    <script src="/vendors/bootstrap/bootstrap.min.js"></script>
    <script src="/vendors/anchorjs/anchor.min.js"></script>
    <script src="/vendors/is/is.min.js"></script>
    <script src="/vendors/hover-dir/hoverDir.min.js"></script>
    <script src="/vendors/isotope-layout/isotope.pkgd.min.js"></script>
    <script src="/vendors/isotope-packery/packery-mode.pkgd.min.js"></script>
    <script src="/vendors/swiper/swiper-bundle.min.js"></script>
    <script src="/vendors/fontawesome/all.min.js"></script>
    <script src="/vendors/lodash/lodash.min.js"></script>
    <script src="/vendors/imagesloaded/imagesloaded.pkgd.min.js"></script>
    <script src="/vendors/gsap/gsap.js"></script>
    <script src="/vendors/gsap/customEase.js"></script>
    <script src="/assets/js/theme.js"></script>

    <a href="https://wa.me/6281320300880?text=Hello%20Svashta%20Home%20I'm%20Interested%20in%20the%20Product"
      class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp"
      style="position:fixed;width:40px;height:40px;bottom:20px;right:20px;background-color:#25d366;border-radius:50px;box-shadow:2px 2px 3px #999;z-index:1000;display:flex;align-items:center;justify-content:center;">
      <img src="https://img.icons8.com/ios-filled/50/ffffff/whatsapp.png" alt="WhatsApp Icon" style="width:35px;height:35px;">
    </a>
  </body>
</html>
