import { describe, it, expect, beforeEach } from 'vitest';
import { tokenStorage } from './token';

describe('tokenStorage', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('stores and retrieves a token', () => {
    tokenStorage.set('my-token-123');

    expect(tokenStorage.get()).toBe('my-token-123');
  });

  it('returns null when no token is set', () => {
    expect(tokenStorage.get()).toBeNull();
  });

  it('clears a stored token', () => {
    tokenStorage.set('token-to-clear');
    expect(tokenStorage.get()).toBe('token-to-clear');

    tokenStorage.clear();
    expect(tokenStorage.get()).toBeNull();
  });

  it('overwrites a previous token', () => {
    tokenStorage.set('first-token');
    tokenStorage.set('second-token');

    expect(tokenStorage.get()).toBe('second-token');
  });
});