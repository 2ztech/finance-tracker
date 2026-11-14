const { test, expect } = require('@playwright/test');

test.describe('Security headers', () => {
  test('dynamic responses carry hardening headers', async ({ request }) => {
    const res = await request.get('/login');
    expect(res.ok()).toBeTruthy();
    const h = res.headers();

    expect(h['x-content-type-options']).toBe('nosniff');
    expect(h['x-frame-options']).toBe('DENY');
    expect(h['referrer-policy']).toBe('strict-origin-when-cross-origin');
    expect(h['permissions-policy']).toContain('geolocation=()');

    const csp = h['content-security-policy'];
    expect(csp).toContain("default-src 'self'");
    expect(csp).toContain("frame-ancestors 'none'");
    expect(csp).toContain("img-src 'self' data: blob:");
  });
});
