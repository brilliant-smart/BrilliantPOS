import { describe, it, expect, vi, beforeEach } from 'vitest';
import { handleApiError } from './errorHandler';
import { AxiosError } from 'axios';

vi.mock('sonner', () => ({
  toast: {
    error: vi.fn(),
  },
}));

describe('handleApiError', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('extracts message from AxiosError response', () => {
    const error = new AxiosError(
      'Request failed',
      'ERR_BAD_RESPONSE',
      undefined,
      undefined,
      {
        status: 400,
        statusText: 'Bad Request',
        data: { message: 'Invalid input data' },
        headers: {},
        config: {} as any,
      },
    );

    const message = handleApiError(error);

    expect(message).toBe('Invalid input data');
  });

  it('flattens Laravel validation errors when no message', () => {
    const error = new AxiosError(
      'Validation failed',
      'ERR_BAD_RESPONSE',
      undefined,
      undefined,
      {
        status: 422,
        statusText: 'Unprocessable Content',
        data: {
          errors: {
            email: ['The email field is required.'],
            name: ['The name must be at least 3 characters.'],
          },
        },
        headers: {},
        config: {} as any,
      },
    );

    const message = handleApiError(error);

    expect(message).toContain('The email field is required.');
    expect(message).toContain('The name must be at least 3 characters.');
  });

  it('prefers message over errors when both are present', () => {
    const error = new AxiosError(
      'Validation failed',
      'ERR_BAD_RESPONSE',
      undefined,
      undefined,
      {
        status: 422,
        statusText: 'Unprocessable Content',
        data: {
          message: 'Validation failed',
          errors: {
            email: ['The email field is required.'],
          },
        },
        headers: {},
        config: {} as any,
      },
    );

    const message = handleApiError(error);

    expect(message).toBe('Validation failed');
  });

  it('returns session expired message for 401', () => {
    const error = new AxiosError(
      'Unauthorized',
      'ERR_BAD_RESPONSE',
      undefined,
      undefined,
      {
        status: 401,
        statusText: 'Unauthorized',
        data: {},
        headers: {},
        config: {} as any,
      },
    );

    const message = handleApiError(error);

    expect(message).toBe('Your session has expired. Please log in again.');
  });

  it('returns permission message for 403', () => {
    const error = new AxiosError(
      'Forbidden',
      'ERR_BAD_RESPONSE',
      undefined,
      undefined,
      {
        status: 403,
        statusText: 'Forbidden',
        data: {},
        headers: {},
        config: {} as any,
      },
    );

    const message = handleApiError(error);

    expect(message).toBe("You don't have permission to perform this action.");
  });

  it('returns not found message for 404', () => {
    const error = new AxiosError(
      'Not Found',
      'ERR_BAD_RESPONSE',
      undefined,
      undefined,
      {
        status: 404,
        statusText: 'Not Found',
        data: {},
        headers: {},
        config: {} as any,
      },
    );

    const message = handleApiError(error);

    expect(message).toBe('The requested resource was not found.');
  });

  it('returns server error message for 500', () => {
    const error = new AxiosError(
      'Server Error',
      'ERR_BAD_RESPONSE',
      undefined,
      undefined,
      {
        status: 500,
        statusText: 'Internal Server Error',
        data: {},
        headers: {},
        config: {} as any,
      },
    );

    const message = handleApiError(error);

    expect(message).toBe('A server error occurred. Please try again later.');
  });

  it('returns network error message when no response', () => {
    const error = new AxiosError('Network Error', 'ERR_NETWORK');

    const message = handleApiError(error);

    expect(message).toBe('Network error. Please check your connection.');
  });

  it('returns error.message for generic Error instances', () => {
    const error = new Error('Something went wrong');

    const message = handleApiError(error);

    expect(message).toBe('Something went wrong');
  });

  it('returns fallback message for unknown error types', () => {
    const message = handleApiError('unknown error string');

    expect(message).toBe('An unexpected error occurred. Please try again.');
  });

  it('uses custom fallback message when provided', () => {
    const message = handleApiError(null, 'Custom fallback');

    expect(message).toBe('Custom fallback');
  });

  it('shows toast notification', async () => {
    const { toast } = await import('sonner');

    const error = new AxiosError('Unauthorized', 'ERR_BAD_RESPONSE', undefined, undefined, {
      status: 401,
      statusText: 'Unauthorized',
      data: {},
      headers: {},
      config: {} as any,
    });

    handleApiError(error);

    expect(toast.error).toHaveBeenCalledWith('Your session has expired. Please log in again.');
  });
});