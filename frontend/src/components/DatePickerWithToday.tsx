import { useState } from 'react';
import { Calendar as CalendarIcon } from 'lucide-react';
import { format } from 'date-fns';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { parseLocalDate } from '@/utils/date';

interface DatePickerWithTodayProps {
  value: string; // ISO date string (YYYY-MM-DD)
  onChange: (value: string) => void;
  placeholder?: string;
  disabled?: boolean;
  required?: boolean;
  className?: string;
  minDate?: Date;
  maxDate?: Date;
}

export function DatePickerWithToday({
  value,
  onChange,
  placeholder = "Pick a date",
  disabled = false,
  required = false,
  className,
  minDate,
  maxDate
}: DatePickerWithTodayProps) {
  const [open, setOpen] = useState(false);

  const dateValue = value ? parseLocalDate(value) : undefined;

  const handleSelect = (date: Date | undefined) => {
    if (date) {
      onChange(format(date, 'yyyy-MM-dd'));
      setOpen(false);
    }
  };

  const handleToday = () => {
    const today = new Date();
    onChange(format(today, 'yyyy-MM-dd'));
    setOpen(false);
  };

  // Calculate year range for dropdown (current year ± 10 years for past, +50 for future)
  const currentYear = new Date().getFullYear();
  const fromYear = minDate ? minDate.getFullYear() : currentYear - 10;
  const toYear = maxDate ? maxDate.getFullYear() : currentYear + 50;

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          className={cn(
            "w-full justify-between text-left font-normal",
            !value && "text-muted-foreground dark:text-muted-foreground/80",
            className
          )}
          disabled={disabled}
        >
          <span>{value ? format(parseLocalDate(value), 'PPP') : placeholder}</span>
          <CalendarIcon className="ml-2 h-4 w-4 text-muted-foreground dark:text-foreground/70" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="end">
        <div className="p-3 border-b">
          <Button 
            variant="outline" 
            className="w-full" 
            onClick={handleToday}
            size="sm"
          >
            Today
          </Button>
        </div>
        <Calendar
          mode="single"
          selected={dateValue}
          onSelect={handleSelect}
          initialFocus
          required={required}
          disabled={minDate || maxDate ? (date) => {
            if (minDate && date < minDate) return true;
            if (maxDate && date > maxDate) return true;
            return false;
          } : undefined}
          captionLayout="dropdown-buttons"
          fromYear={fromYear}
          toYear={toYear}
        />
      </PopoverContent>
    </Popover>
  );
}
