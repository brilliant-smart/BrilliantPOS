import { useState, useEffect } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Loader2, TrendingUp, TrendingDown, Activity } from "lucide-react";
import { getStockHistory, StockMovement } from "@/app/api/inventory";
import { useToast } from "@/hooks/use-toast";
import { format } from "date-fns";
import { ScrollArea } from "@/components/ui/scroll-area";

interface StockHistoryModalProps {
  open: boolean;
  onClose: () => void;
  productId: number | null;
  productName: string;
}

export function StockHistoryModal({
  open,
  onClose,
  productId,
  productName,
}: StockHistoryModalProps) {
  const { toast } = useToast();
  const [loading, setLoading] = useState(false);
  const [history, setHistory] = useState<StockMovement[]>([]);

  useEffect(() => {
    if (open && productId) {
      loadHistory();
    }
  }, [open, productId]);

  const loadHistory = async () => {
    if (!productId) return;

    setLoading(true);
    try {
      const data = await getStockHistory(productId);
      setHistory(data);
    } catch (error: any) {
      toast({
        title: "Error",
        description: error.response?.data?.message || "Failed to load stock history",
        variant: "destructive",
      });
    } finally {
      setLoading(false);
    }
  };

  const getTypeConfig = (type: string) => {
    const configs: Record<string, { label: string; variant: any; className: string }> = {
      purchase: { label: "Purchase", variant: "default", className: "bg-blue-500 dark:bg-blue-700 text-white" },
      sale: { label: "Sale", variant: "secondary", className: "bg-purple-500 dark:bg-purple-700 text-white" },
      adjustment: { label: "Adjustment", variant: "outline", className: "bg-muted dark:bg-muted text-foreground" },
      damage: { label: "Damage", variant: "destructive", className: "bg-orange-500 dark:bg-orange-700 text-white" },
      return: { label: "Return", variant: "default", className: "bg-green-500 dark:bg-green-700 text-white" },
      initial: { label: "Initial", variant: "secondary", className: "bg-indigo-500 dark:bg-indigo-700 text-white" },
    };
    return configs[type] || { label: type, variant: "outline", className: "" };
  };

  const getQuantityIcon = (quantity: number) => {
    if (quantity > 0) return <TrendingUp className="w-4 h-4 text-green-500 dark:text-green-400" />;
    if (quantity < 0) return <TrendingDown className="w-4 h-4 text-red-500 dark:text-red-400" />;
    return <Activity className="w-4 h-4 text-muted-foreground dark:text-muted-foreground/80" />;
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[800px]">
        <DialogHeader>
          <DialogTitle>Stock History - {productName}</DialogTitle>
          <DialogDescription>
            Complete movement history for this product
          </DialogDescription>
        </DialogHeader>

        {loading ? (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
          </div>
        ) : history.length === 0 ? (
          <div className="text-center py-8 text-muted-foreground dark:text-muted-foreground/80">
            No stock movements recorded yet
          </div>
        ) : (
          <ScrollArea className="h-[500px] w-full">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead className="text-right">Change</TableHead>
                  <TableHead className="text-right">Previous</TableHead>
                  <TableHead className="text-right">New</TableHead>
                  <TableHead>User</TableHead>
                  <TableHead>Notes</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {history.map((movement) => {
                  const typeConfig = getTypeConfig(movement.type);
                  return (
                    <TableRow key={movement.id}>
                      <TableCell className="text-sm">
                        {format(new Date(movement.created_at), "MMM dd, yyyy HH:mm")}
                      </TableCell>
                      <TableCell>
                        <Badge variant={typeConfig.variant} className={typeConfig.className}>
                          {typeConfig.label}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex items-center justify-end gap-1">
                          {getQuantityIcon(movement.quantity)}
                          <span
                            className={
                              movement.quantity > 0
                                ? "text-green-600 dark:text-green-400 font-medium"
                                : movement.quantity < 0
                                ? "text-red-600 dark:text-red-400 font-medium"
                                : ""
                            }
                          >
                            {movement.quantity > 0 ? "+" : ""}
                            {movement.quantity}
                          </span>
                        </div>
                      </TableCell>
                      <TableCell className="text-right text-muted-foreground dark:text-muted-foreground/80">
                        {movement.previous_stock}
                      </TableCell>
                      <TableCell className="text-right font-medium">
                        {movement.new_stock}
                      </TableCell>
                      <TableCell className="text-sm">
                        {movement.user?.name || "Unknown"}
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground dark:text-muted-foreground/80 max-w-[200px] truncate">
                        {movement.notes || "-"}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </ScrollArea>
        )}
      </DialogContent>
    </Dialog>
  );
}
