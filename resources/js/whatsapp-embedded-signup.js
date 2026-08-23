const facebookSdkId = 'facebook-jssdk';
const allowedMetaOrigins = new Set([
    'https://www.facebook.com',
    'https://web.facebook.com',
]);

let facebookSdkPromise = null;
let destroyCurrentSignup = () => {};

function setFeedback(element, message, isError = false) {
    if (!element) {
        return;
    }

    element.textContent = message;
    element.classList.toggle('text-red-600', isError);
    element.classList.toggle('text-body', !isError);
}

function parseSessionPayload(data) {
    if (typeof data === 'object' && data !== null) {
        return data;
    }

    if (typeof data !== 'string') {
        return null;
    }

    try {
        return JSON.parse(data);
    } catch {
        return null;
    }
}

function loadFacebookSdk(appId, graphVersion) {
    if (window.FB) {
        window.FB.init({
            appId,
            cookie: true,
            fedCM: false,
            xfbml: false,
            version: graphVersion,
        });

        return Promise.resolve(window.FB);
    }

    if (facebookSdkPromise) {
        return facebookSdkPromise;
    }

    facebookSdkPromise = new Promise((resolve, reject) => {
        const timeout = window.setTimeout(() => reject(new Error('Facebook SDK timeout')), 15000);
        const previousInit = window.fbAsyncInit;

        window.fbAsyncInit = () => {
            if (typeof previousInit === 'function') {
                previousInit();
            }

            window.clearTimeout(timeout);
            window.FB.init({
                appId,
                cookie: true,
                fedCM: false,
                xfbml: false,
                version: graphVersion,
            });
            resolve(window.FB);
        };

        const existingScript = document.getElementById(facebookSdkId);

        if (existingScript) {
            existingScript.addEventListener('error', () => {
                window.clearTimeout(timeout);
                reject(new Error('Facebook SDK gagal dimuat'));
            }, { once: true });

            return;
        }

        const script = document.createElement('script');
        script.id = facebookSdkId;
        script.async = true;
        script.defer = true;
        script.crossOrigin = 'anonymous';
        script.src = 'https://connect.facebook.net/id_ID/sdk.js';
        script.addEventListener('error', () => {
            window.clearTimeout(timeout);
            reject(new Error('Facebook SDK gagal dimuat'));
        }, { once: true });
        document.head.append(script);
    });

    facebookSdkPromise = facebookSdkPromise.catch((error) => {
        facebookSdkPromise = null;

        throw error;
    });

    return facebookSdkPromise;
}

export function destroyWhatsAppEmbeddedSignup() {
    destroyCurrentSignup();
    destroyCurrentSignup = () => {};
}

export function initializeWhatsAppEmbeddedSignup() {
    destroyWhatsAppEmbeddedSignup();

    const root = document.querySelector('[data-whatsapp-embedded-signup]');

    if (!root) {
        return;
    }

    const connectButton = root.querySelector('[data-whatsapp-connect]');
    const pinInput = root.querySelector('[data-whatsapp-pin]');
    const feedback = root.querySelector('[data-whatsapp-feedback]');
    const appId = root.dataset.metaAppId;
    const configId = root.dataset.metaConfigId;
    const graphVersion = root.dataset.metaGraphVersion;
    const isConfigured = root.dataset.metaConfigured === 'true';

    let authorizationCode = null;
    let session = null;
    let isSubmitting = false;
    let hasSubmitted = false;

    const completeSignupWhenReady = async () => {
        if (!authorizationCode || !session?.wabaId || isSubmitting || hasSubmitted) {
            return;
        }

        const component = window.Livewire?.find(root.dataset.livewireId);

        if (!component) {
            setFeedback(feedback, 'Komponen Livewire tidak ditemukan. Muat ulang halaman.', true);

            return;
        }

        isSubmitting = true;
        hasSubmitted = true;
        connectButton.disabled = true;
        setFeedback(feedback, 'Memverifikasi aset WhatsApp melalui server...');

        try {
            await component.completeWhatsAppSignup(
                authorizationCode,
                session.wabaId,
                session.phoneNumberId,
            );
            setFeedback(feedback, 'Proses koneksi selesai.');
        } catch {
            setFeedback(feedback, 'Koneksi tidak dapat diselesaikan. Periksa pesan pada halaman.', true);
        } finally {
            isSubmitting = false;

            if (document.contains(connectButton)) {
                connectButton.disabled = false;
            }
        }
    };

    const handleMetaMessage = (event) => {
        if (!allowedMetaOrigins.has(event.origin)) {
            return;
        }

        const payload = parseSessionPayload(event.data);

        if (payload?.type !== 'WA_EMBEDDED_SIGNUP') {
            return;
        }

        if (payload.event === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING' || payload.event === 'FINISH') {
            session = {
                wabaId: String(payload.data?.waba_id ?? ''),
                phoneNumberId: payload.data?.phone_number_id
                    ? String(payload.data.phone_number_id)
                    : null,
            };
            void completeSignupWhenReady();

            return;
        }

        if (payload.event === 'CANCEL' || payload.event === 'ERROR') {
            setFeedback(feedback, 'Proses Embedded Signup dibatalkan atau gagal.', true);
        }
    };

    const handleConnect = () => {
        if (!pinInput.checkValidity()) {
            pinInput.reportValidity();

            return;
        }

        if (!window.FB) {
            setFeedback(feedback, 'SDK Meta belum siap. Tunggu sebentar lalu coba lagi.', true);

            return;
        }

        authorizationCode = null;
        session = null;
        hasSubmitted = false;
        setFeedback(feedback, 'Membuka Facebook Login for Business...');

        window.FB.login((response) => {
            const code = response?.authResponse?.code;

            if (!code) {
                setFeedback(feedback, 'Login Meta dibatalkan atau authorization code tidak diterima.', true);

                return;
            }

            authorizationCode = code;
            void completeSignupWhenReady();
        }, {
            config_id: configId,
            response_type: 'code',
            override_default_response_type: true,
            extras: {
                setup: {},
                featureType: 'whatsapp_business_app_onboarding',
                sessionInfoVersion: '3',
            },
        });
    };

    window.addEventListener('message', handleMetaMessage);

    if (!isConfigured) {
        connectButton.disabled = true;
        setFeedback(feedback, 'Konfigurasi Meta pada server belum lengkap.', true);
    } else {
        connectButton.disabled = true;
        setFeedback(feedback, 'Memuat SDK Meta...');

        loadFacebookSdk(appId, graphVersion)
            .then(() => {
                if (!document.contains(connectButton)) {
                    return;
                }

                connectButton.disabled = false;
                connectButton.addEventListener('click', handleConnect);
                setFeedback(feedback, 'SDK Meta siap. Masukkan PIN lalu hubungkan nomor.');
            })
            .catch(() => setFeedback(feedback, 'SDK Meta gagal dimuat. Periksa koneksi dan domain HTTPS.', true));
    }

    destroyCurrentSignup = () => {
        window.removeEventListener('message', handleMetaMessage);
        connectButton?.removeEventListener('click', handleConnect);
    };
}
