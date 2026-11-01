/**
 * Minimal fetch wrapper: JSON in/out, CSRF header, consistent result shape.
 */
export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? window.GENI?.config?.csrfToken ?? '';
}

/**
 * @param {string} url
 * @param {{method?: string, data?: FormData|Object|null}} options
 * @returns {Promise<{ok: boolean, status: number, data: any}>}
 */
export async function request(url, { method = 'GET', data = null } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    };

    let body = null;
    let httpMethod = method.toUpperCase();

    if (data instanceof FormData) {
        // Browsers cannot send multipart PUT/PATCH/DELETE to PHP reliably: spoof the method.
        if (!['GET', 'POST'].includes(httpMethod)) {
            data.set('_method', httpMethod);
            httpMethod = 'POST';
        }
        body = data;
    } else if (data !== null) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(data);
    }

    let response;
    try {
        response = await fetch(url, { method: httpMethod, headers, body, credentials: 'same-origin' });
    } catch (error) {
        return { ok: false, status: 0, data: {} };
    }

    let payload = {};
    try {
        payload = await response.json();
    } catch (error) {
        payload = {};
    }

    return { ok: response.ok, status: response.status, data: payload ?? {} };
}
