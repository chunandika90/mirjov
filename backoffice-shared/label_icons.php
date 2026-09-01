<?php
/**
 * 4 ikon penanganan barang (fragile / handle with care / this way up / keep dry)
 * buat dicetak di bawah foto produk pada label stock — SVG inline biar gak
 * bergantung ke gambar eksternal (aman dicetak/PDF, gak bisa putus linknya).
 */
function label_handling_icons(): void
{
    ?>
    <div class="label-handling-icons">
      <svg viewBox="0 0 100 100" title="Fragile">
        <rect x="4" y="4" width="92" height="92" rx="14" fill="none" stroke="currentColor" stroke-width="6"/>
        <path d="M36 18 L36 34 Q36 50 50 54 Q64 50 64 34 L64 18 Z" fill="none" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>
        <line x1="50" y1="54" x2="50" y2="72" stroke="currentColor" stroke-width="4"/>
        <line x1="37" y1="79" x2="63" y2="79" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
        <path d="M50 18 L44 31 L54 40 L45 50" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <svg viewBox="0 0 100 100" title="Handle with care">
        <rect x="4" y="4" width="92" height="92" rx="14" fill="none" stroke="currentColor" stroke-width="6"/>
        <rect x="37" y="20" width="26" height="22" rx="2" fill="none" stroke="currentColor" stroke-width="4"/>
        <path d="M22 58 Q22 40 40 43 L40 58" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M78 58 Q78 40 60 43 L60 58" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M18 58 Q18 82 35 84 Q50 86 65 84 Q82 82 82 58" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
      </svg>
      <svg viewBox="0 0 100 100" title="This way up">
        <rect x="4" y="4" width="92" height="92" rx="14" fill="none" stroke="currentColor" stroke-width="6"/>
        <line x1="35" y1="74" x2="35" y2="28" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
        <polyline points="23,40 35,20 47,40" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="65" y1="74" x2="65" y2="28" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
        <polyline points="53,40 65,20 77,40" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="20" y1="82" x2="80" y2="82" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
      </svg>
      <svg viewBox="0 0 100 100" title="Keep dry">
        <rect x="4" y="4" width="92" height="92" rx="14" fill="none" stroke="currentColor" stroke-width="6"/>
        <path d="M18 46 Q18 18 50 18 Q82 18 82 46 Q71 38 60 46 Q50 38 40 46 Q29 38 18 46 Z" fill="none" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>
        <line x1="50" y1="18" x2="50" y2="68" stroke="currentColor" stroke-width="4"/>
        <path d="M50 68 Q50 79 39 79" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
        <path d="M63 56 q4 7 0 11 q-4 -4 0 -11 Z" fill="currentColor"/>
        <path d="M75 64 q4 7 0 11 q-4 -4 0 -11 Z" fill="currentColor"/>
        <path d="M51 60 q4 7 0 11 q-4 -4 0 -11 Z" fill="currentColor"/>
      </svg>
    </div>
    <?php
}
