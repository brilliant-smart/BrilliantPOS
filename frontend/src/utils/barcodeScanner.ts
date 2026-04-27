/**
 * External Barcode Scanner Support
 * 
 * This utility provides support for external USB/Bluetooth barcode scanners
 * commonly used in Nigerian POS systems (e.g., EVAWGIB, POS Maid compatible scanners)
 * 
 * These scanners typically work by emulating keyboard input, sending the barcode
 * followed by an Enter key press.
 */

interface BarcodeScannerOptions {
  onScan: (barcode: string) => void;
  minLength?: number;
  maxLength?: number;
  timeout?: number; // Time window to capture scanned input (ms)
  preventDefault?: boolean;
  ignoreIfFocusOn?: string[]; // CSS selectors to ignore scanning when focused
}

class ExternalBarcodeScanner {
  private buffer: string = '';
  private timeout: NodeJS.Timeout | null = null;
  private options: BarcodeScannerOptions;
  private isActive: boolean = false;

  constructor(options: BarcodeScannerOptions) {
    this.options = {
      minLength: 3,
      maxLength: 50,
      timeout: 100, // 100ms between characters
      preventDefault: true,
      ignoreIfFocusOn: ['input', 'textarea', 'select'],
      ...options,
    };
  }

  /**
   * Start listening for barcode scanner input
   */
  start() {
    if (this.isActive) return;
    
    this.isActive = true;
    document.addEventListener('keydown', this.handleKeyDown);
    document.addEventListener('keypress', this.handleKeyPress);
  }

  /**
   * Stop listening for barcode scanner input
   */
  stop() {
    if (!this.isActive) return;
    
    this.isActive = false;
    document.removeEventListener('keydown', this.handleKeyDown);
    document.removeEventListener('keypress', this.handleKeyPress);
    this.reset();
  }

  /**
   * Handle keydown events
   */
  private handleKeyDown = (event: KeyboardEvent) => {
    // Check if we should ignore this input
    if (this.shouldIgnoreEvent(event)) {
      return;
    }

    // Handle Enter key - this typically indicates end of barcode scan
    if (event.key === 'Enter' && this.buffer.length > 0) {
      if (this.options.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
      }
      
      this.processBuffer();
      return;
    }
  };

  /**
   * Handle keypress events to capture characters
   */
  private handleKeyPress = (event: KeyboardEvent) => {
    // Check if we should ignore this input
    if (this.shouldIgnoreEvent(event)) {
      return;
    }

    // Ignore special keys
    if (event.key.length > 1 || event.ctrlKey || event.altKey || event.metaKey) {
      return;
    }

    // Add character to buffer
    this.buffer += event.key;

    // Reset timeout
    if (this.timeout) {
      clearTimeout(this.timeout);
    }

    // Set new timeout - if no new character arrives within timeout, process buffer
    this.timeout = setTimeout(() => {
      if (this.buffer.length >= (this.options.minLength || 3)) {
        // Auto-process if max length reached
        if (this.buffer.length >= (this.options.maxLength || 50)) {
          this.processBuffer();
        }
      }
    }, this.options.timeout);
  };

  /**
   * Check if we should ignore this event
   */
  private shouldIgnoreEvent(event: KeyboardEvent): boolean {
    const target = event.target as HTMLElement;
    
    // Check if focus is on an input element we should ignore
    if (this.options.ignoreIfFocusOn && this.options.ignoreIfFocusOn.length > 0) {
      const tagName = target.tagName.toLowerCase();
      if (this.options.ignoreIfFocusOn.includes(tagName)) {
        return true;
      }

      // Check if any selector matches
      for (const selector of this.options.ignoreIfFocusOn) {
        if (target.matches(selector)) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Process the buffer and trigger callback
   */
  private processBuffer() {
    const barcode = this.buffer.trim();
    
    // Validate length
    if (
      barcode.length >= (this.options.minLength || 3) &&
      barcode.length <= (this.options.maxLength || 50)
    ) {
      this.options.onScan(barcode);
    }

    this.reset();
  }

  /**
   * Reset buffer and timeout
   */
  private reset() {
    this.buffer = '';
    if (this.timeout) {
      clearTimeout(this.timeout);
      this.timeout = null;
    }
  }

  /**
   * Check if scanner is active
   */
  isScanning(): boolean {
    return this.isActive;
  }
}

/**
 * Create a barcode scanner instance
 */
export const createBarcodeScanner = (options: BarcodeScannerOptions): ExternalBarcodeScanner => {
  return new ExternalBarcodeScanner(options);
};

export default ExternalBarcodeScanner;
