const { convertToWIB, formatWIB, getWIBTimestamp } = require('../src/utils/dateHelper');

describe('dateHelper (zona waktu WIB, UTC+7)', () => {
  test('convertToWIB menambah 7 jam dari UTC', () => {
    expect(convertToWIB('2026-01-01T00:00:00Z')).toBe('2026-01-01 07:00:00');
  });

  test('convertToWIB melewati pergantian hari', () => {
    expect(convertToWIB('2026-01-01T20:30:00Z')).toBe('2026-01-02 03:30:00');
  });

  test('formatWIB memakai format bawaan DD MMM YYYY HH:mm', () => {
    expect(formatWIB('2026-03-05T10:15:00Z')).toBe('05 Mar 2026 17:15');
  });

  test('formatWIB menerima format khusus', () => {
    expect(formatWIB('2026-03-05T10:15:00Z', 'YYYY/MM/DD')).toBe('2026/03/05');
  });

  test('getWIBTimestamp berbentuk YYYY-MM-DD HH:mm:ss', () => {
    expect(getWIBTimestamp()).toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
  });
});
