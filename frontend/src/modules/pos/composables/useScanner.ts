import { useRef, useCallback, useState, useEffect } from 'react';
import { Product } from '@/types/ims';
import { productApi } from '@/app/api/products';
import { toast } from 'sonner';
import { ScannerConfig } from '../types';

export interface UseScannerReturn {
  scannerRef: React.RefObject<HTMLInputElement>;
  lastScanned: Product | null;
  isScanning: boolean;
  focusScanner: () => void;
  searchResults: Product[];
  isSearching: boolean;
  searchQuery: string;
  clearSearch: () => void;
}

interface UseScannerProps {
  onProductScanned: (product: Product) => void;
  onError?: (message: string) => void;
  config?: ScannerConfig;
}

const DEFAULT_CONFIG: ScannerConfig = {
  debounceMs: 300,
  minBarcodeLength: 3,
  autoFocus: true,
};

/**
 * Scanner Hook for POS
 * Handles both barcode scanning and product search
 */
export const useScanner = ({
  onProductScanned,
  onError,
  config = DEFAULT_CONFIG,
}: UseScannerProps): UseScannerReturn => {
  const scannerRef = useRef<HTMLInputElement>(null);
  const [lastScanned, setLastScanned] = useState<Product | null>(null);
  const [isScanning, setIsScanning] = useState(false);
  const [searchResults, setSearchResults] = useState<Product[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const searchTimeoutRef = useRef<NodeJS.Timeout>();

  const mergedConfig = { ...DEFAULT_CONFIG, ...config };

  /**
   * Focus scanner input with delay
   */
  const focusScanner = useCallback(() => {
    if (!mergedConfig.autoFocus) return;
    
    setTimeout(() => {
      scannerRef.current?.focus();
    }, 100);
  }, [mergedConfig.autoFocus]);

  /**
   * Handle barcode scan
   * Scans are typically numeric and happen very fast
   */
  const handleScan = useCallback(async (barcode: string) => {
    if (!barcode || barcode.trim().length < (mergedConfig.minBarcodeLength || 3)) return;

    setIsScanning(true);
    
    try {
      const product = await productApi.searchByBarcode(barcode);
      setLastScanned(product);
      onProductScanned(product);
      
      // Clear input and search results immediately
      if (scannerRef.current) {
        scannerRef.current.value = '';
      }
      setSearchResults([]);
      setSearchQuery('');
      setIsSearching(false);
      
      // Refocus scanner
      focusScanner();
      
    } catch (error: any) {
      const message = error.response?.data?.message || 'Product not found';
      
      if (onError) {
        onError(message);
      } else {
        toast.error(message);
      }
      
      // Clear input and search on error
      if (scannerRef.current) {
        scannerRef.current.value = '';
      }
      setSearchResults([]);
      setSearchQuery('');
      
      // Refocus scanner
      focusScanner();
    } finally {
      setIsScanning(false);
    }
  }, [onProductScanned, onError, focusScanner, mergedConfig.minBarcodeLength]);

  /**
   * Handle product search (debounced)
   * Search happens when user types text (not numeric barcode)
   */
  const handleSearch = useCallback(async (query: string) => {
    if (!query || query.trim().length < 2) {
      setSearchResults([]);
      setSearchQuery('');
      return;
    }

    setSearchQuery(query);
    setIsSearching(true);

    // Clear previous timeout
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    // Debounce search
    searchTimeoutRef.current = setTimeout(async () => {
      try {
        const response = await productApi.search(query);
        // Handle both paginated and non-paginated responses
        const products = response.data || response;
        setSearchResults(Array.isArray(products) ? products : []);
      } catch (error: any) {
        console.error('Search failed:', error);
        setSearchResults([]);
      } finally {
        setIsSearching(false);
      }
    }, mergedConfig.debounceMs || 300);
  }, [mergedConfig.debounceMs]);

  /**
   * Handle selecting a product from search results
   */
  const handleSelectProduct = useCallback((product: Product) => {
    setLastScanned(product);
    onProductScanned(product);
    
    // Clear search
    if (scannerRef.current) {
      scannerRef.current.value = '';
    }
    setSearchResults([]);
    setSearchQuery('');
    
    // Refocus scanner
    focusScanner();
  }, [onProductScanned, focusScanner]);

  /**
   * Clear search results manually
   */
  const clearSearch = useCallback(() => {
    setSearchResults([]);
    setSearchQuery('');
  }, []);

  /**
   * Auto-focus on mount
   */
  useEffect(() => {
    if (mergedConfig.autoFocus) {
      focusScanner();
    }
  }, [focusScanner, mergedConfig.autoFocus]);

  /**
   * Setup input listener
   * Intelligently detects barcode vs text search
   */
  useEffect(() => {
    const input = scannerRef.current;
    if (!input) return;

    /**
     * Handle input changes
     * Distinguish between barcode (numeric) and search (text)
     */
    const handleInput = (e: Event) => {
      const target = e.target as HTMLInputElement;
      const value = target.value;
      
      // If empty, clear search results
      if (!value || value.trim().length === 0) {
        setSearchResults([]);
        setSearchQuery('');
        return;
      }
      
      // If input looks like a barcode (numeric, >6 digits), don't search
      // Wait for Enter key instead
      if (/^\d+$/.test(value) && value.length > 6) {
        return;
      }
      
      // Otherwise, initiate product search
      handleSearch(value);
    };

    /**
     * Handle key press
     * Enter key triggers barcode scan or selects search result
     */
    const handleKeyPress = (e: KeyboardEvent) => {
      const value = input.value.trim();
      
      if (e.key === 'Enter') {
        e.preventDefault();
        
        if (!value) return;
        
        // If there's exactly one search result, select it
        if (searchResults.length === 1) {
          handleSelectProduct(searchResults[0]);
          return;
        }
        
        // If multiple results, user needs to click to select
        if (searchResults.length > 1) {
          return;
        }
        
        // Otherwise, try barcode scan
        handleScan(value);
      }
    };

    input.addEventListener('input', handleInput);
    input.addEventListener('keypress', handleKeyPress);
    
    return () => {
      input.removeEventListener('input', handleInput);
      input.removeEventListener('keypress', handleKeyPress);
    };
  }, [handleScan, handleSearch, searchResults, handleSelectProduct]);

  return {
    scannerRef,
    lastScanned,
    isScanning,
    focusScanner,
    searchResults,
    isSearching,
    searchQuery,
    clearSearch,
  };
};
