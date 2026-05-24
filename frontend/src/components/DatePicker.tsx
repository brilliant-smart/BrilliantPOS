import React from 'react';
import ReactDatePicker from 'react-datepicker';
import { Calendar as CalendarIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { parseLocalDate } from '@/utils/date';

interface DatePickerProps {
  value: string; // ISO date string (YYYY-MM-DD)
  onChange: (value: string) => void;
  placeholder?: string;
  disabled?: boolean;
  required?: boolean;
  className?: string;
  minDate?: Date;
  maxDate?: Date;
}

export function DatePicker({
  value,
  onChange,
  placeholder = 'Select date',
  disabled = false,
  required = false,
  className,
  minDate,
  maxDate,
}: DatePickerProps) {
  const selectedDate = value ? parseLocalDate(value) : null;

  const handleChange = (date: Date | null) => {
    if (date) {
      // Convert to YYYY-MM-DD format (local time, not UTC)
      const formatted = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
      onChange(formatted);
    } else {
      onChange('');
    }
  };

  return (
    <div className="relative">
      <ReactDatePicker
        selected={selectedDate}
        onChange={handleChange}
        dateFormat="dd/MM/yyyy"
        placeholderText={placeholder}
        disabled={disabled}
        required={required}
        minDate={minDate}
        maxDate={maxDate}
        showPopperArrow={false}
        todayButton="Today"
        className={cn(
          "flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground dark:placeholder:text-muted-foreground/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 pr-10",
          className
        )}
      />
      <CalendarIcon className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground dark:text-foreground/70 pointer-events-none" />
    </div>
  );
}
