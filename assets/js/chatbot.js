(() => {
    'use strict';

    const root = document.querySelector('.desa-chatbot');
    if (!root) return;

    const panel = root.querySelector('.desa-chatbot__panel');
    const launcher = root.querySelector('.desa-chatbot__launcher');
    const closeButton = root.querySelector('.desa-chatbot__close');
    const form = root.querySelector('.desa-chatbot__form');
    const input = root.querySelector('#desa-chatbot-question');
    const messages = root.querySelector('.desa-chatbot__messages');
    const status = root.querySelector('.desa-chatbot__status');
    const sendButton = root.querySelector('.desa-chatbot__send');
    const apiUrl = root.dataset.apiUrl;
    const maxLength = 1000;
    let closeTimer;

    const setOpen = (isOpen) => {
        window.clearTimeout(closeTimer);
        root.classList.toggle('is-open', isOpen);
        launcher.setAttribute('aria-expanded', String(isOpen));
        panel.setAttribute('aria-hidden', String(!isOpen));

        if (isOpen) {
            panel.hidden = false;
            window.requestAnimationFrame(() => {
                panel.classList.add('is-open');
                input.focus();
            });
            return;
        }

        panel.classList.remove('is-open');
        closeTimer = window.setTimeout(() => {
            if (!panel.classList.contains('is-open')) panel.hidden = true;
        }, 220);
        launcher.focus();
    };

    const addMessage = (text, kind) => {
        const message = document.createElement('p');
        message.className = `desa-chatbot__message desa-chatbot__message--${kind}`;
        message.textContent = text;
        messages.append(message);
        messages.scrollTop = messages.scrollHeight;
        return message;
    };

    launcher.addEventListener('click', () => setOpen(panel.hidden));
    closeButton.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) setOpen(false);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = input.value.trim();
        if (!question || question.length > maxLength || sendButton.disabled) return;

        addMessage(question, 'user');
        input.value = '';
        input.disabled = true;
        sendButton.disabled = true;
        status.textContent = 'Sedang menyiapkan jawaban…';

        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ question })
            });
            let result;
            try {
                result = await response.json();
            } catch {
                throw new Error('Maaf, chatbot belum dapat menjawab. Silakan coba kembali nanti.');
            }

            if (!response.ok || !result || typeof result !== 'object'
                || result.success !== true || typeof result.answer !== 'string') {
                const messagesByCode = {
                    invalid_request: 'Format pertanyaan tidak valid. Silakan coba kembali.',
                    invalid_question: 'Pertanyaan wajib diisi dan maksimal 1.000 karakter.',
                    rate_limited: 'Terlalu banyak pertanyaan dalam waktu singkat. Silakan tunggu sebentar.',
                    missing_api_key: 'Asisten belum dikonfigurasi karena kunci API belum tersedia di server. Hubungi pengelola website.',
                    invalid_config: 'File konfigurasi chatbot tidak valid atau lokasinya salah. Pengelola perlu memeriksa GEMINI_CONFIG_PATH di server.',
                    invalid_api_key: 'Kunci API ditolak atau tidak memiliki izin. Hubungi pengelola website.',
                    connection_failed: 'Koneksi ke layanan AI gagal. Silakan coba kembali nanti.',
                    model_not_found: 'Tidak ada model Gemini yang tersedia untuk konfigurasi API saat ini. Pengelola perlu memeriksa GEMINI_MODEL dan izin API di server.',
                    model_unavailable: 'Model belum dapat diverifikasi untuk konfigurasi ini. Silakan coba kembali nanti.',
                    model_unsupported: 'Model yang dikonfigurasi belum mendukung percakapan.',
                    quota_unavailable: 'Kuota layanan AI sedang habis atau mencapai batas penggunaan. Silakan coba kembali nanti.',
                    service_unavailable: 'Layanan AI gagal memproses permintaan. Silakan coba kembali nanti.'
                };
                throw new Error(messagesByCode[result.error?.code]
                    || 'Maaf, chatbot belum dapat menjawab. Silakan coba kembali nanti.');
            }

            addMessage(result.answer, 'assistant');
            status.textContent = '';
        } catch (error) {
            const safeMessage = error instanceof TypeError
                ? 'Koneksi belum tersedia. Periksa jaringan Anda lalu coba kembali.'
                : (typeof error.message === 'string'
                    ? error.message
                    : 'Maaf, chatbot belum dapat menjawab. Silakan coba kembali nanti.');
            addMessage(safeMessage, 'error');
            status.textContent = '';
        } finally {
            input.disabled = false;
            sendButton.disabled = false;
            input.focus();
        }
    });
})();
