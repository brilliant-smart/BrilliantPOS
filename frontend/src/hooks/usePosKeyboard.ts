import { useEffect, useCallback } from 'react';

export interface KeyboardShortcut {
  key: string;
  ctrlKey?: boolean;
  shiftKey?: boolean;
  altKey?: boolean;
  callback: () => void;
  description: string;
}

export interface UsePosKeyboardReturn {
  shortcuts: KeyboardShortcut[];
}

interface UsePosKeyboardProps {
  onPayment?: () => void;        // F6
  onHold?: () => void;           // F8
  onRecall?: () => void;         // F9
  onVoid?: () => void;           // F10
  onSearch?: () => void;         // F3
  onNewSale?: () => void;        // F4
  enabled?: boolean;
}

export const usePosKeyboard = ({
  onPayment,
  onHold,
  onRecall,
  onVoid,
  onSearch,
  onNewSale,
  enabled = true,
}: UsePosKeyboardProps): UsePosKeyboardReturn => {
  
  const shortcuts: KeyboardShortcut[] = [
    { key: 'F3', callback: onSearch || (() => {}), description: 'Product Search' },
    { key: 'F4', callback: onNewSale || (() => {}), description: 'New Sale' },
    { key: 'F6', callback: onPayment || (() => {}), description: 'Payment' },
    { key: 'F8', callback: onHold || (() => {}), description: 'Hold Cart' },
    { key: 'F9', callback: onRecall || (() => {}), description: 'Recall Cart' },
    { key: 'F10', callback: onVoid || (() => {}), description: 'Void Sale' },
  ];

  const handleKeyDown = useCallback((event: KeyboardEvent) => {
    if (!enabled) return;

    // Don't trigger if user is typing in input/textarea
    const target = event.target as HTMLElement;
    const isInputField = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT';
    
    // Allow function keys even in input fields, but not regular keys
    const isFunctionKey = event.key.startsWith('F');
    
    if (isInputField && !isFunctionKey) return;

    // Check each shortcut
    for (const shortcut of shortcuts) {
      if (
        event.key === shortcut.key &&
        !!event.ctrlKey === !!shortcut.ctrlKey &&
        !!event.shiftKey === !!shortcut.shiftKey &&
        !!event.altKey === !!shortcut.altKey
      ) {
        event.preventDefault();
        event.stopPropagation();
        shortcut.callback();
        break;
      }
    }
  }, [enabled, shortcuts]);

  useEffect(() => {
    if (!enabled) return;

    window.addEventListener('keydown', handleKeyDown);
    
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [enabled, handleKeyDown]);

  return {
    shortcuts,
  };
};
