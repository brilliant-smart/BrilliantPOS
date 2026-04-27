import { useState } from 'react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';

interface Product {
  id: number;
  name: string;
  sku?: string;
  track_batch?: boolean;
  track_expiry?: boolean;
}

interface ProductSearchSelectProps {
  products: Product[];
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
}

export function ProductSearchSelect({
  products,
  value,
  onChange,
  placeholder = 'Select product...',
}: ProductSearchSelectProps) {
  const [open, setOpen] = useState(false);
  const selectedProduct = products.find((p) => p.id.toString() === value);

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          role="combobox"
          aria-expanded={open}
          className="w-full justify-between font-normal"
        >
          {selectedProduct ? (
            <span className="truncate">
              {selectedProduct.name}
              {(selectedProduct.track_batch || selectedProduct.track_expiry) && (
                <span className="ml-2 text-xs text-blue-600 dark:text-blue-400">
                  {selectedProduct.track_batch && '📦'} {selectedProduct.track_expiry && '📅'}
                </span>
              )}
            </span>
          ) : (
            <span className="text-muted-foreground">{placeholder}</span>
          )}
          <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
        <Command>
          <CommandInput placeholder="Search products..." />
          <CommandList>
            <CommandEmpty>No product found.</CommandEmpty>
            <CommandGroup>
              {products.map((product) => (
                <CommandItem
                  key={product.id}
                  value={`${product.name} ${product.sku || ''}`}
                  onSelect={() => {
                    onChange(product.id.toString());
                    setOpen(false);
                  }}
                  className="data-[selected=true]:bg-primary data-[selected=true]:text-primary-foreground"
                >
                  <Check
                    className={cn(
                      'mr-2 h-4 w-4',
                      value === product.id.toString() ? 'opacity-100' : 'opacity-0'
                    )}
                  />
                  <span className="flex-1 truncate">{product.name}</span>
                  {product.sku && (
                    <span className="ml-2 text-xs text-muted-foreground">{product.sku}</span>
                  )}
                  {(product.track_batch || product.track_expiry) && (
                    <span className="ml-1 text-xs text-blue-600 dark:text-blue-400">
                      {product.track_batch && '📦'} {product.track_expiry && '📅'}
                    </span>
                  )}
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}