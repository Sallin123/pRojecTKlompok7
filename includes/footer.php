<?php
$chatbotBasePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
if ($chatbotBasePath === '/') {
    $chatbotBasePath = '';
}
?>
<footer class="bg-gray-900 text-gray-400 text-xs py-8 border-t border-gray-800 mt-12">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <?php if (!empty($showHomeFooterDetails)): ?>
            <div class="mx-auto max-w-6xl px-2 text-left sm:px-4 lg:px-0">
                <div class="grid grid-cols-1 gap-8 border-b border-gray-700 pb-10 lg:grid-cols-12">
                    <div class="lg:col-span-7">
                        <span class="mb-3 block text-[10px] font-extrabold uppercase tracking-widest text-amber-400">LOKASI KANTOR DESA PEMAYAHAN</span>
                        <div class="relative h-64 overflow-hidden rounded-2xl border border-gray-700 bg-gray-800 shadow-xl">
                            <iframe
                                class="h-full w-full grayscale contrast-125 opacity-90 transition duration-500 hover:grayscale-0"
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126915.28588523998!2d106.789156!3d-6.2297465!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69f3e80f622123%3A0x100c5c32dd58d80!2sKantor%20Desa!5e0!3m2!1sid!2sid!4v1700000000000!5m2!1sid!2sid"
                                style="border:0;"
                                allowfullscreen=""
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                        </div>
                    </div>
                    <div class="flex flex-col justify-between space-y-6 text-left lg:col-span-5">
                        <div>
                            <span class="mb-3 block text-[10px] font-extrabold uppercase tracking-widest text-amber-400">KONTAK KAMI</span>
                            <ul class="space-y-4 text-xs">
                                <li class="flex items-start space-x-3">
                                    <span class="shrink-0 text-base text-amber-400">📍</span>
                                    <span class="leading-relaxed text-gray-300">Jl. Balai Desa Pemayahan No. 01, Kantor Desa Pemayahan, Indonesia</span>
                                </li>
                                <li class="flex items-center space-x-3">
                                    <span class="shrink-0 text-base text-amber-400">✉️</span>
                                    <span class="text-gray-300">pemdes@pemayahan.desa.id</span>
                                </li>
                                <li class="flex items-center space-x-3">
                                    <span class="shrink-0 text-base text-amber-400">📞</span>
                                    <span class="text-gray-300">+62 812-3456-7890 (Call Center / WA Kantor Desa)</span>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <span class="mb-3 block text-[10px] font-extrabold uppercase tracking-widest text-amber-400">MEDIA SOSIAL RESMI</span>
                            <div class="flex items-center space-x-3">
                                <a href="#" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-700 bg-gray-800 text-xs font-bold text-gray-300 transition hover:bg-amber-500 hover:text-slate-950">FB</a>
                                <a href="#" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-700 bg-gray-800 text-xs font-bold text-gray-300 transition hover:bg-amber-500 hover:text-slate-950">IG</a>
                                <a href="#" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-700 bg-gray-800 text-xs font-bold text-gray-300 transition hover:bg-amber-500 hover:text-slate-950">YT</a>
                                <a href="#" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-700 bg-gray-800 text-xs font-bold text-gray-300 transition hover:bg-amber-500 hover:text-slate-950">TT</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="max-w-7xl mx-auto px-4 <?php echo !empty($showHomeFooterDetails) ? 'pt-6' : ''; ?>">
            <p>&copy; <?php echo date('Y'); ?> Portal Resmi Desa Digital. Seluruh hak cipta dilindungi.</p>
        </div>
    </div>
</footer>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&amp;display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap-grid.min.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($chatbotBasePath . '/assets/css/chatbot.css?v=3', ENT_QUOTES, 'UTF-8'); ?>">
<section class="desa-chatbot" data-api-url="<?php echo htmlspecialchars($chatbotBasePath . '/chatbot_api.php', ENT_QUOTES, 'UTF-8'); ?>" aria-label="Chatbot Desa Pemayahan">
    <div class="desa-chatbot__panel" id="desa-chatbot-panel" aria-hidden="true" aria-labelledby="desa-chatbot-title" hidden>
        <header class="desa-chatbot__header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="desa-chatbot__avatar" role="img" aria-label="Maskot Bot Hansip berseragam hijau memegang tongkat">
                            <svg viewBox="0 0 96 96" aria-hidden="true" focusable="false">
                                <circle cx="48" cy="48" r="48" fill="#dce8d8" />
                                <path d="M13 96c1-20 8-33 22-38l13 8 13-8c14 5 21 18 22 38H13Z" fill="#278c58" />
                                <path d="m35 58 13 10 13-10 5 5-8 13H38l-8-13 5-5Z" fill="#e6eadb" />
                                <path d="m44 67 4 4 4-4-1 10h-6l-1-10Z" fill="#b83d36" />
                                <path d="M31 28c1-12 8-18 17-18s16 6 17 18l-3 9H34l-3-9Z" fill="#188652" />
                                <path d="M26 29c10-8 34-8 44 0l-2 7H28l-2-7Z" fill="#17683f" />
                                <path d="M39 18h18v3H39z" fill="#f3d54e" />
                                <path d="m44 15 4 5 4-5m-4 5v4m-6-4 6 4 6-4" fill="none" stroke="#f3d54e" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="48" cy="41" r="16" fill="#e7ae83" />
                                <path d="M32 39c2-10 9-15 16-15s14 5 16 15c-5-4-10-6-16-6s-11 2-16 6Z" fill="#342d26" />
                                <path d="m38 37 8 2m12-2-8 2" stroke="#342d26" stroke-width="2" stroke-linecap="round" />
                                <circle cx="41" cy="43" r="2" fill="#292821" />
                                <circle cx="55" cy="43" r="2" fill="#292821" />
                                <path d="M47 44c2 2 2 4 0 5m-4 3c3 3 8 3 11 0" fill="none" stroke="#9e453d" stroke-width="1.8" stroke-linecap="round" />
                                <path d="M18 65c4-5 10-7 16-6l7 8-5 8-15-1-6-4 3-5Z" fill="#17683f" />
                                <path d="M23 70c4-3 8-3 12 0l4 5-4 4-12-2-4-3 4-4Z" fill="#e7ae83" />
                                <path d="M63 59c7-2 13 1 16 7l4 8-9 5-11-8-5-7 5-5Z" fill="#17683f" />
                                <path d="M70 64c4-2 8 0 10 4l-3 5-8-2-3-4 4-3Z" fill="#e7ae83" />
                                <path d="M41 63h14v19H41z" fill="#278c58" />
                                <path d="M31 81h34v3H31z" fill="#f3d54e" />
                                <path d="M34 70h8v9h-8zm20 0h8v9h-8z" fill="#20764c" />
                                <g class="desa-chatbot__baton-swing">
                                    <path d="m72 65-31 13" stroke="#523a2a" stroke-width="4.5" stroke-linecap="round" />
                                    <path d="m41 78-3 1" stroke="#d7bd85" stroke-width="4.5" stroke-linecap="round" />
                                    <path d="m72 65 3-1" stroke="#d7bd85" stroke-width="4.5" stroke-linecap="round" />
                                </g>
                                <path d="M29 77c3-2 7-2 10 0l3 2-3 3-8-1-4-2 2-2Z" fill="#e7ae83" />
                                <path d="M36 78h5" stroke="#bf805d" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                        </span>
                    </div>
                    <div class="col">
                        <h2 class="desa-chatbot__title" id="desa-chatbot-title">Bot Hansip</h2>
                        <p class="desa-chatbot__subtitle">Siap jaga informasi Desa Pemayahan</p>
                    </div>
                    <div class="col-auto">
                        <button class="desa-chatbot__close" type="button" aria-label="Tutup chatbot" aria-controls="desa-chatbot-panel">×</button>
                    </div>
                </div>
            </div>
        </header>
        <div class="desa-chatbot__messages" role="log" aria-live="polite" aria-relevant="additions text">
            <p class="desa-chatbot__message desa-chatbot__message--assistant">Lapor! Saya Bot Hansip, siap bantu mencari informasi umum tentang Desa Pemayahan. Tanyakan profil, layanan, potensi, atau berita desa, ya. Untuk data pribadi dan status pengajuan, silakan hubungi kantor desa.</p>
        </div>
        <form class="desa-chatbot__form">
            <label class="desa-chatbot__sr-only" for="desa-chatbot-question">Pertanyaan Anda</label>
            <textarea id="desa-chatbot-question" name="question" rows="2" maxlength="1000" placeholder="Tulis pertanyaan untuk Bot Hansip..." required></textarea>
            <div class="desa-chatbot__form-footer">
                <span class="desa-chatbot__status" role="status" aria-live="polite"></span>
                <button class="desa-chatbot__send" type="submit"><span>Kirim</span><span aria-hidden="true">➤</span></button>
            </div>
        </form>
        <p class="desa-chatbot__notice">Jawaban AI dapat keliru. Untuk kepastian, hubungi kantor desa.</p>
    </div>
    <p class="desa-chatbot__greeting" aria-hidden="true">Lapor! Pos ronda aman <span>🫡</span></p>
    <button class="desa-chatbot__launcher" type="button" aria-label="Buka Bot Hansip" aria-expanded="false" aria-controls="desa-chatbot-panel">
        <svg class="desa-chatbot__mascot" viewBox="0 0 96 96" role="img" aria-label="Hansip berseragam hijau mengetukkan tongkat ke telapak tangan">
            <circle cx="48" cy="48" r="48" fill="#dce8d8" />
            <path d="M13 96c1-20 8-33 22-38l13 8 13-8c14 5 21 18 22 38H13Z" fill="#278c58" />
            <path d="m35 58 13 10 13-10 5 5-8 13H38l-8-13 5-5Z" fill="#e6eadb" />
            <path d="m44 67 4 4 4-4-1 10h-6l-1-10Z" fill="#b83d36" />
            <path d="M31 28c1-12 8-18 17-18s16 6 17 18l-3 9H34l-3-9Z" fill="#188652" />
            <path d="M26 29c10-8 34-8 44 0l-2 7H28l-2-7Z" fill="#17683f" />
            <path d="M39 18h18v3H39z" fill="#f3d54e" />
            <path d="m44 15 4 5 4-5m-4 5v4m-6-4 6 4 6-4" fill="none" stroke="#f3d54e" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="48" cy="41" r="16" fill="#e7ae83" />
            <path d="M32 39c2-10 9-15 16-15s14 5 16 15c-5-4-10-6-16-6s-11 2-16 6Z" fill="#342d26" />
            <path d="m38 37 8 2m12-2-8 2" stroke="#342d26" stroke-width="2" stroke-linecap="round" />
            <circle cx="41" cy="43" r="2" fill="#292821" />
            <circle cx="55" cy="43" r="2" fill="#292821" />
            <path d="M47 44c2 2 2 4 0 5m-4 3c3 3 8 3 11 0" fill="none" stroke="#9e453d" stroke-width="1.8" stroke-linecap="round" />
            <path d="M18 65c4-5 10-7 16-6l7 8-5 8-15-1-6-4 3-5Z" fill="#17683f" />
            <path d="M23 70c4-3 8-3 12 0l4 5-4 4-12-2-4-3 4-4Z" fill="#e7ae83" />
            <path d="M63 59c7-2 13 1 16 7l4 8-9 5-11-8-5-7 5-5Z" fill="#17683f" />
            <path d="M70 64c4-2 8 0 10 4l-3 5-8-2-3-4 4-3Z" fill="#e7ae83" />
            <path d="M41 63h14v19H41z" fill="#278c58" />
            <path d="M31 81h34v3H31z" fill="#f3d54e" />
            <path d="M34 70h8v9h-8zm20 0h8v9h-8z" fill="#20764c" />
            <g class="desa-chatbot__baton-swing">
                <path d="m72 65-31 13" stroke="#523a2a" stroke-width="4.5" stroke-linecap="round" />
                <path d="m41 78-3 1" stroke="#d7bd85" stroke-width="4.5" stroke-linecap="round" />
                <path d="m72 65 3-1" stroke="#d7bd85" stroke-width="4.5" stroke-linecap="round" />
            </g>
            <path d="M29 77c3-2 7-2 10 0l3 2-3 3-8-1-4-2 2-2Z" fill="#e7ae83" />
            <path d="M36 78h5" stroke="#bf805d" stroke-width="1.2" stroke-linecap="round" />
        </svg>
    </button>
</section>
<script src="<?php echo htmlspecialchars($chatbotBasePath . '/assets/js/chatbot.js?v=2', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
