const DB_NAME = 'dieselindo-peoplehub-attendance';
const STORE_NAME = 'pending-events';

const openQueue = () => new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1);
    request.onupgradeneeded = () => {
        if (!request.result.objectStoreNames.contains(STORE_NAME)) {
            request.result.createObjectStore(STORE_NAME, { keyPath: 'external_event_id' });
        }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
});

const withStore = async (mode, operation) => {
    const db = await openQueue();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, mode);
        const request = operation(transaction.objectStore(STORE_NAME));
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
        transaction.oncomplete = () => db.close();
    });
};

const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const send = async (url, event) => {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token() },
        body: JSON.stringify(event),
    });
    if (!response.ok) throw new Error(`Attendance sync failed (${response.status})`);
    return response;
};

const flush = async (url) => {
    const queued = await withStore('readonly', (store) => store.getAll());
    for (const event of queued) {
        try {
            await send(url, event);
            await withStore('readwrite', (store) => store.delete(event.external_event_id));
        } catch {
            break;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-attendance-form]');
    if (!(form instanceof HTMLFormElement) || !('indexedDB' in window)) return;
    const syncUrl = form.dataset.syncUrl;
    if (!syncUrl) return;

    const idField = form.querySelector('[data-event-id]');
    const deviceTime = form.querySelector('[data-device-time]');
    const deviceInfo = form.querySelector('[data-device-info]');
    if (idField instanceof HTMLInputElement && !idField.value) idField.value = crypto.randomUUID();
    if (deviceInfo instanceof HTMLInputElement) deviceInfo.value = `${navigator.platform}|${navigator.language}`.slice(0, 500);

    form.querySelector('[data-location-button]')?.addEventListener('click', () => {
        navigator.geolocation?.getCurrentPosition((position) => {
            const latitude = form.querySelector('[data-latitude]');
            const longitude = form.querySelector('[data-longitude]');
            const accuracy = form.querySelector('[data-accuracy]');
            if (latitude instanceof HTMLInputElement) latitude.value = String(position.coords.latitude);
            if (longitude instanceof HTMLInputElement) longitude.value = String(position.coords.longitude);
            if (accuracy instanceof HTMLInputElement) accuracy.value = String(Math.round(position.coords.accuracy));
            const status = form.querySelector('[data-location-status]');
            if (status) status.textContent = status.ownerDocument.documentElement.lang.startsWith('id') ? 'Lokasi siap dikirim' : 'Location is ready';
        });
    });

    form.addEventListener('submit', async (event) => {
        if (deviceTime instanceof HTMLInputElement) deviceTime.value = new Date().toISOString();
        if (navigator.onLine || form.querySelector('input[type="file"]')?.files?.length) return;
        event.preventDefault();
        const data = new FormData(form);
        const queued = {
            source_public_id: data.get('source_public_id'), external_event_id: data.get('external_event_id'),
            event_type: data.get('event_type'), occurred_at: new Date(String(data.get('occurred_at'))).toISOString(),
            device_recorded_at: data.get('device_recorded_at'), latitude: data.get('latitude') || null,
            longitude: data.get('longitude') || null, gps_accuracy_meters: data.get('gps_accuracy_meters') || null,
            device_info: data.get('device_info'), was_offline: true,
        };
        await withStore('readwrite', (store) => store.put(queued));
        window.location.reload();
    });

    window.addEventListener('online', () => flush(syncUrl));
    if (navigator.onLine) flush(syncUrl);
});
