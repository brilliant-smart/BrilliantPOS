import { useState } from 'react';
import { Calendar as CalendarIcon } from 'lucide-react';
import { format } from 'date-fns';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

interface MonthYearPickerProps {
  value: string; // ISO date string (YYYY-MM-DD) or (YYYY-MM)
  onChange: (value: string) => void;
  placeholder?: string;
  disabled?: boolean;
  required?: boolean;
  className?: string;
  minDate?: Date;
  maxDate?: Date;
}

const MONTHS = [
  { value: '01', label: 'January' },
  { value: '02', label: 'February' },
  { value: '03', label: 'March' },
  { value: '04', label: 'April' },
  { value: '05', label: 'May' },
  { value: '06', label: 'June' },
  { value: '07', label: 'July' },
  { value: '08', label: 'August' },
  { value: '09', label: 'September' },
  { value: '10', label: 'October' },
  { value: '11', label: 'November' },
  { value: '12', label: 'December' },
];

export function MonthYearPicker({ 
  value, 
  onChange, 
  placeholder = "Select month & year",
  disabled = false,
  className,
  minDate,
  maxDate
}: MonthYearPickerProps) {
  const [open, setOpen] = useState(false);

  // Parse the current value
  const currentDate = value ? new Date(value) : null;
  const currentMonth = currentDate ? format(currentDate, 'MM') : '';
  const currentYear = currentDate ? format(currentDate, 'yyyy') : '';

  // Generate year range
  const currentYearNum = new Date().getFullYear();
  const fromYear = minDate ? minDate.getFullYear() : currentYearNum - 10;
  const toYear = maxDate ? maxDate.getFullYear() : currentYearNum + 50;
  const years = Array.from({ length: toYear - fromYear + 1 }, (_, i) => fromYear + i);

  // State for selection
  const [selectedMonth, setSelectedMonth] = useState(currentMonth);
  const [selectedYear, setSelectedYear] = useState(currentYear);

  const handleMonthChange = (month: string) => {
    setSelectedMonth(month);
    if (selectedYear) {
      // Store as YYYY-MM-01 for database compatibility
      const newDate = `${selectedYear}-${month}-01`;
      onChange(newDate);
      setOpen(false);
    }
  };

  const handleYearChange = (year: string) => {
    setSelectedYear(year);
    if (selectedMonth) {
      // Store as YYYY-MM-01 for database compatibility
      const newDate = `${year}-${selectedMonth}-01`;
      onChange(newDate);
      setOpen(false);
    }
  };

  const handleThisMonth = () => {
    const today = new Date();
    const month = format(today, 'MM');
    const year = format(today, 'yyyy');
    setSelectedMonth(month);
    setSelectedYear(year);
    onChange(`${year}-${month}-01`);
    setOpen(false);
  };

  const displayValue = currentDate 
    ? format(currentDate, 'MMMM yyyy')  // e.g., "September 2028"
    : placeholder;

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          className={cn(
            "w-full justify-between text-left font-normal",
            !value && "text-muted-foreground",
            className
          )}
          disabled={disabled}
        >
          <span>{displayValue}</span>
          <CalendarIcon className="ml-2 h-4 w-4 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-4" align="end">
        <div className="space-y-4">
          <div className="flex justify-between items-center">
            <h4 className="font-medium text-sm">Select Month & Year</h4>
            <Button 
              variant="outline" 
              size="sm"
              onClick={handleThisMonth}
            >
              This Month
            </Button>
          </div>

          <div className="space-y-3">
            <div className="space-y-2">
              <label className="text-sm font-medium">Month</label>
              <Select value={selectedMonth} onValueChange={handleMonthChange}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Select month" />
                </SelectTrigger>
                <SelectContent>
                  {MONTHS.map((month) => (
                    <SelectItem key={month.value} value={month.value}>
                      {month.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <label className="text-sm font-medium">Year</label>
              <Select value={selectedYear} onValueChange={handleYearChange}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Select year" />
                </SelectTrigger>
                <SelectContent className="max-h-[300px]">
                  {years.reverse().map((year) => (
                    <SelectItem key={year} value={year.toString()}>
                      {year}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          {selectedMonth && selectedYear && (
            <div className="pt-2 border-t">
              <p className="text-sm text-muted-foreground text-center">
                Selected: <span className="font-medium text-foreground">
                  {MONTHS.find(m => m.value === selectedMonth)?.label} {selectedYear}
                </span>
              </p>
            </div>
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}
