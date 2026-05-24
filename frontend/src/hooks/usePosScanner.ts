import { useRef, useCallback, useState, useEffect } from 'react';
import { Product, ProductUnitType } from '@/types/ims';
import { productApi, BarcodeSearchResult } from '@/app/api/products';
import { toast } from 'sonner';

export interface UsePosScanner {
  scannerRef: React.RefObject<HTMLInputElement>;
  lastScanned: Product | null;
  isScanning: boolean;
  focusScanner: () => void;
  searchResults: Product[];
  isSearching: boolean;
  searchQuery: string;
  clearSearch: () => void;
}

interface UsePosScannerProps {
  onProductScanned: (product: Product, unitType?: ProductUnitType | null) => void;
  onError?: (message: string) => void;
}

export const usePosScanner = ({
  onProductScanned,
  onError,
}: UsePosScannerProps): UsePosScanner => {
  const scannerRef = useRef<HTMLInputElement>(null);
  const [lastScanned, setLastScanned] = useState<Product | null>(null);
  const [isScanning, setIsScanning] = useState(false);
  const [searchResults, setSearchResults] = useState<Product[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const searchTimeoutRef = useRef<NodeJS.Timeout>();
  const searchAbortRef = useRef<AbortController | null>(null);

  // Focus scanner input
  const focusScanner = useCallback(() => {
    setTimeout(() => {
      scannerRef.current?.focus();
    }, 100);
  }, []);

  // Handle barcode scan
  const handleScan = useCallback(async (barcode: string) => {
    if (!barcode || barcode.trim().length < 3) return;

    setIsScanning(true);

    try {
      const result: BarcodeSearchResult = await productApi.searchByBarcode(barcode);
      const product = result.product;
      const matchedUnitType = result.matched_unit_type || null;
      setLastScanned(product);
      onProductScanned(product, matchedUnitType);

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
  }, [onProductScanned, onError, focusScanner]);

  // Handle product search (debounced)
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
      // Cancel any in-flight search
      if (searchAbortRef.current) {
        searchAbortRef.current.abort();
      }
      const abortController = new AbortController();
      searchAbortRef.current = abortController;

      try {
        const response = await productApi.search(query, { signal: abortController.signal });
        // searchProducts returns response.data which may be an array or { data: [...] }
        const products = Array.isArray(response) ? response : (response?.data ?? []);
        setSearchResults(products);
      } catch (error: any) {
        // Silently ignore cancelled requests
        if (error.name !== 'CanceledError' && error.name !== 'AbortError') {
          console.error('Search failed:', error);
          setSearchResults([]);
        }
      } finally {
        setIsSearching(false);
      }
    }, 300);
  }, []);

  // Handle selecting a product from search results
  const handleSelectProduct = useCallback((product: Product) => {
    setLastScanned(product);
    onProductScanned(product, null);
    
    // Clear search
    if (scannerRef.current) {
      scannerRef.current.value = '';
    }
    setSearchResults([]);
    setSearchQuery('');
    
    // Refocus scanner
    focusScanner();
  }, [onProductScanned, focusScanner]);

  // Clear search results when query is empty
  const clearSearch = useCallback(() => {
    setSearchResults([]);
    setSearchQuery('');
  }, []);

  // Auto-focus on mount
  useEffect(() => {
    focusScanner();
  }, [focusScanner]);

  // Setup input listener
  useEffect(() => {
    const input = scannerRef.current;
    if (!input) return;

    const handleInput = (e: Event) => {
      const target = e.target as HTMLInputElement;
      const value = target.value;
      
      // If empty, clear search results
      if (!value || value.trim().length === 0) {
        setSearchResults([]);
        setSearchQuery('');
        return;
      }
      
      // If input looks like a barcode (numeric), don't search
      if (/^\d+$/.test(value) && value.length > 6) {
        // Wait for Enter key for barcode
        return;
      }
      
      // Otherwise, search for products
      handleSearch(value);
    };

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
        
        // If multiple results, show them
        if (searchResults.length > 1) {
          // User needs to click or use arrow keys to select
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
