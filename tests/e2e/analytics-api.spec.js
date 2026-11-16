const { test, expect, request } = require('@playwright/test');
const { login } = require('./helpers');

test.describe('Analytics API', () => {
  test('rejects unauthenticated requests with JSON 401', async () => {
    const api = await request.newContext({ baseURL: 'http://127.0.0.1:8099' });
    const response = await api.get('/api/analytics');
    expect(response.status()).toBe(401);
    const body = await response.json();
    expect(body.ok).toBe(false);
    expect(body.error.code).toBe('unauthorized');
    await api.dispose();
  });

  test('returns analytics state and persists layouts', async ({ page }) => {
    await login(page);

    const response = await page.request.get('/api/analytics?range=last_6');
    expect(response.status()).toBe(200);
    const state = await response.json();
    expect(state.ok).toBe(true);
    expect(state.currency).toBe('MYR');
    expect(state.widgets).toHaveLength(5);
    expect(state.catalog).toHaveLength(8);
    expect(state.filters.range.preset).toBe('last_6');
    expect(typeof state.csrf_token).toBe('string');

    const layout = state.layout;
    layout.widgets[0].size = 'wide';
    layout.widgets[0].config = { chart: 'line' };

    const save = await page.request.post('/api/analytics/layout', {
      headers: { 'X-CSRF-Token': state.csrf_token },
      data: { layout },
    });
    expect(save.status()).toBe(200);
    const saved = await save.json();
    expect(saved.layout.widgets[0].size).toBe('wide');
    expect(saved.layout.widgets[0].config.chart).toBe('line');

    const after = await page.request.get('/api/analytics');
    const afterState = await after.json();
    expect(afterState.layout.widgets[0].size).toBe('wide');

    const reset = await page.request.post('/api/analytics/layout/reset', {
      headers: { 'X-CSRF-Token': state.csrf_token },
    });
    expect(reset.status()).toBe(200);
    const resetState = await reset.json();
    expect(resetState.layout.widgets).toHaveLength(5);
    expect(resetState.layout.widgets[0].size).toBe('full');
  });

  test('rejects layout posts without a CSRF token', async ({ page }) => {
    await login(page);
    const response = await page.request.post('/api/analytics/layout', {
      data: { layout: { version: 1, widgets: [] } },
    });
    expect(response.status()).toBe(403);
    const body = await response.json();
    expect(body.error.code).toBe('csrf_failed');
  });

  test('rejects invalid layouts with details', async ({ page }) => {
    await login(page);
    const state = await (await page.request.get('/api/analytics')).json();
    const response = await page.request.post('/api/analytics/layout', {
      headers: { 'X-CSRF-Token': state.csrf_token },
      data: { layout: { version: 1, widgets: [{ id: 'x', key: 'nope' }] } },
    });
    expect(response.status()).toBe(422);
    const body = await response.json();
    expect(body.error.code).toBe('invalid_layout');
    expect(Array.isArray(body.error.details)).toBe(true);
  });
});
