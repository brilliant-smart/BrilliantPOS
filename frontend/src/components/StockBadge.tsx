import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { PackageCheck, PackageX, AlertTriangle } from "lucide-react";

interface StockBadgeProps {
  stockStatus: 'in_stock' | 'low_stock' | 'out_of_stock';
  stockQuantity?: number;
  className?: string;
  showIcon?: boolean;
  showQuantity?: boolean;
}

export function StockBadge({
  stockStatus,
  stockQuantity,
  className,
  showIcon = true,
  showQuantity = false,
}: StockBadgeProps) {
  const getStockConfig = () => {
    switch (stockStatus) {
      case 'in_stock':
        return {
          label: 'In Stock',
          variant: 'default' as const,
          icon: PackageCheck,
          className: 'bg-green-500 hover:bg-green-600 dark:bg-green-700 dark:hover:bg-green-600 text-white',
        };
      case 'low_stock':
        return {
          label: 'Low Stock',
          variant: 'secondary' as const,
          icon: AlertTriangle,
          className: 'bg-yellow-500 hover:bg-yellow-600 dark:bg-yellow-600 dark:hover:bg-yellow-500 text-white',
        };
      case 'out_of_stock':
        return {
          label: 'Out of Stock',
          variant: 'destructive' as const,
          icon: PackageX,
          className: 'bg-red-500 hover:bg-red-600 dark:bg-red-700 dark:hover:bg-red-600 text-white',
        };
      default:
        return {
          label: 'Unknown',
          variant: 'outline' as const,
          icon: PackageX,
          className: '',
        };
    }
  };

  const config = getStockConfig();
  const Icon = config.icon;

  return (
    <Badge
      variant={config.variant}
      className={cn(config.className, className)}
    >
      {showIcon && <Icon className="w-3 h-3 mr-1" />}
      {config.label}
      {showQuantity && stockQuantity !== undefined && (
        <span className="ml-1">({stockQuantity})</span>
      )}
    </Badge>
  );
}
