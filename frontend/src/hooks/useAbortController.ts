import { useRef, useCallback, useEffect } from "react";

/**
 * Hook that provides an AbortController tied to the component lifecycle.
 * Automatically aborts pending requests when the component unmounts.
 *
 * Usage:
 *   const getSignal = useAbortController();
 *   useEffect(() => {
 *     api.get('/endpoint', { signal: getSignal() }).then(...);
 *   }, []);
 */
export function useAbortController() {
  const controllerRef = useRef<AbortController | null>(null);

  const getSignal = useCallback((): AbortSignal => {
    // Abort any previous request
    if (controllerRef.current) {
      controllerRef.current.abort();
    }
    controllerRef.current = new AbortController();
    return controllerRef.current.signal;
  }, []);

  useEffect(() => {
    return () => {
      if (controllerRef.current) {
        controllerRef.current.abort();
      }
    };
  }, []);

  return getSignal;
}