import { describe, it, expect } from 'vitest';
import { isAuthenticated, isOwner, isManager, isCashier } from './guards';
import type { User } from './types';

describe('auth guards', () => {
  const owner: User = { id: 1, name: 'Owner', email: 'owner@store.com', role: 'owner' };
  const manager: User = { id: 2, name: 'Manager', email: 'manager@store.com', role: 'manager' };
  const cashier: User = { id: 3, name: 'Cashier', email: 'cashier@store.com', role: 'cashier' };

  // ---- isAuthenticated ----

  it('returns true for any authenticated user', () => {
    expect(isAuthenticated(owner)).toBe(true);
    expect(isAuthenticated(manager)).toBe(true);
    expect(isAuthenticated(cashier)).toBe(true);
  });

  it('returns false for null', () => {
    expect(isAuthenticated(null)).toBe(false);
  });

  // ---- isOwner ----

  it('returns true only for owner role', () => {
    expect(isOwner(owner)).toBe(true);
    expect(isOwner(manager)).toBe(false);
    expect(isOwner(cashier)).toBe(false);
  });

  it('returns false for null', () => {
    expect(isOwner(null)).toBe(false);
  });

  // ---- isManager ----

  it('returns true only for manager role', () => {
    expect(isManager(manager)).toBe(true);
    expect(isManager(owner)).toBe(false);
    expect(isManager(cashier)).toBe(false);
  });

  // ---- isCashier ----

  it('returns true only for cashier role', () => {
    expect(isCashier(cashier)).toBe(true);
    expect(isCashier(owner)).toBe(false);
    expect(isCashier(manager)).toBe(false);
  });

  it('returns false for null', () => {
    expect(isCashier(null)).toBe(false);
  });
});