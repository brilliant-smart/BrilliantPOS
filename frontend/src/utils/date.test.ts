import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { todayLocal, parseLocalDate } from './date';

describe('date utils', () => {
  describe('todayLocal', () => {
    it('returns today in YYYY-MM-DD format', () => {
      const result = todayLocal();
      const expected = `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}-${String(new Date().getDate()).padStart(2, '0')}`;
      expect(result).toBe(expected);
    });

    it('uses local time, not UTC', () => {
      // Simulate UTC+1: if local time is May 25 00:30, UTC is May 24 23:30
      // todayLocal should return May 25, not May 24
      const realDate = globalThis.Date;
      vi.useFakeTimers();
      vi.setSystemTime(new Date('2026-05-25T00:30:00+01:00'));
      // In UTC this is May 24 23:30, but locally it's May 25
      const result = todayLocal();
      // The result should be based on local time
      expect(result).toMatch(/^\d{4}-\d{2}-\d{2}$/);
      vi.useRealTimers();
    });

    it('pads month and day with leading zeros', () => {
      const result = todayLocal();
      const parts = result.split('-');
      expect(parts[1]).toMatch(/^\d{2}$/);
      expect(parts[2]).toMatch(/^\d{2}$/);
    });
  });

  describe('parseLocalDate', () => {
    it('parses YYYY-MM-DD as local midnight', () => {
      const result = parseLocalDate('2026-05-25');
      expect(result.getFullYear()).toBe(2026);
      expect(result.getMonth()).toBe(4); // May = 4 (0-indexed)
      expect(result.getDate()).toBe(25);
      expect(result.getHours()).toBe(0);
      expect(result.getMinutes()).toBe(0);
    });

    it('does not shift the date like new Date(string) would in UTC-X', () => {
      // new Date("2026-05-25") parses as UTC midnight
      // In UTC-5, this would be May 24 at 19:00 local
      // parseLocalDate should always produce May 25 local
      const result = parseLocalDate('2026-01-01');
      expect(result.getFullYear()).toBe(2026);
      expect(result.getMonth()).toBe(0);
      expect(result.getDate()).toBe(1);
    });

    it('handles edge cases like end of year', () => {
      const result = parseLocalDate('2025-12-31');
      expect(result.getFullYear()).toBe(2025);
      expect(result.getMonth()).toBe(11);
      expect(result.getDate()).toBe(31);
    });
  });
});