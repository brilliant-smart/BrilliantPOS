import { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useToast } from "@/hooks/use-toast";
import { addStock, reduceStock, adjustStock } from "@/app/api/inventory";
import { Loader2, Plus, Minus, Settings } from "lucide-react";

interface Product {
  id: number;
  name: string;
  stock_quantity: number;
  price: number;
}

interface StockAdjustmentModalProps {
  open: boolean;
  onClose: () => void;
  product: Product | null;
  onSuccess: () => void;
}

export function StockAdjustmentModal({
  open,
  onClose,
  product,
  onSuccess,
}: StockAdjustmentModalProps) {
  const { toast } = useToast();
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<"add" | "reduce" | "adjust">("add");

  // Form state
  const [quantity, setQuantity] = useState<string>("");
  const [type, setType] = useState<string>("purchase");
  const [notes, setNotes] = useState<string>("");
  const [unitCost, setUnitCost] = useState<string>("");

  const resetForm = () => {
    setQuantity("");
    setType("purchase");
    setNotes("");
    setUnitCost("");
  };

  const handleClose = () => {
    resetForm();
    onClose();
  };

  const handleSubmit = async () => {
    if (!product) return;

    const qty = parseInt(quantity);
    if (isNaN(qty) || qty < 0) {
      toast({
        title: "Invalid Quantity",
        description: "Please enter a valid quantity.",
        variant: "destructive",
      });
      return;
    }

    setLoading(true);
    try {
      let response;

      if (activeTab === "add") {
        response = await addStock(product.id, {
          quantity: qty,
          type: type as any,
          notes: notes || undefined,
          unit_cost: unitCost ? parseFloat(unitCost) : undefined,
        });
      } else if (activeTab === "reduce") {
        response = await reduceStock(product.id, {
          quantity: qty,
          type: type as any,
          notes: notes || undefined,
        });
      } else {
        response = await adjustStock(product.id, qty, notes || undefined);
      }

      toast({
        title: "Success",
        description: response.message || "Stock updated successfully",
      });

      resetForm();
      onSuccess();
      handleClose();
    } catch (error: any) {
      toast({
        title: "Error",
        description: error.response?.data?.message || "Failed to update stock",
        variant: "destructive",
      });
    } finally {
      setLoading(false);
    }
  };

  if (!product) return null;

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>Manage Stock - {product.name}</DialogTitle>
          <DialogDescription>
            Current Stock: <span className="font-semibold">{product.stock_quantity}</span> units
          </DialogDescription>
        </DialogHeader>

        <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)}>
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="add">
              <Plus className="w-4 h-4 mr-1" />
              Add Stock
            </TabsTrigger>
            <TabsTrigger value="reduce">
              <Minus className="w-4 h-4 mr-1" />
              Reduce Stock
            </TabsTrigger>
            <TabsTrigger value="adjust">
              <Settings className="w-4 h-4 mr-1" />
              Adjust
            </TabsTrigger>
          </TabsList>

          <TabsContent value="add" className="space-y-4">
            <div>
              <Label htmlFor="add-quantity">Quantity to Add</Label>
              <Input
                id="add-quantity"
                type="number"
                min="1"
                value={quantity}
                onChange={(e) => setQuantity(e.target.value)}
                placeholder="Enter quantity"
              />
            </div>

            <div>
              <Label htmlFor="add-type">Movement Type</Label>
              <Select value={type} onValueChange={setType}>
                <SelectTrigger id="add-type">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="purchase">Purchase/Restock</SelectItem>
                  <SelectItem value="return">Customer Return</SelectItem>
                  <SelectItem value="adjustment">Adjustment</SelectItem>
                  <SelectItem value="initial">Initial Stock</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div>
              <Label htmlFor="unit-cost">Unit Cost (Optional)</Label>
              <Input
                id="unit-cost"
                type="number"
                step="0.01"
                min="0"
                value={unitCost}
                onChange={(e) => setUnitCost(e.target.value)}
                placeholder="₦0.00"
              />
            </div>

            <div>
              <Label htmlFor="add-notes">Notes (Optional)</Label>
              <Textarea
                id="add-notes"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Add notes about this stock movement..."
                rows={3}
              />
            </div>
          </TabsContent>

          <TabsContent value="reduce" className="space-y-4">
            <div>
              <Label htmlFor="reduce-quantity">Quantity to Reduce</Label>
              <Input
                id="reduce-quantity"
                type="number"
                min="1"
                max={product.stock_quantity}
                value={quantity}
                onChange={(e) => setQuantity(e.target.value)}
                placeholder="Enter quantity"
              />
            </div>

            <div>
              <Label htmlFor="reduce-type">Movement Type</Label>
              <Select value={type} onValueChange={setType}>
                <SelectTrigger id="reduce-type">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="sale">Sale</SelectItem>
                  <SelectItem value="damage">Damage/Loss</SelectItem>
                  <SelectItem value="adjustment">Adjustment</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div>
              <Label htmlFor="reduce-notes">Notes (Optional)</Label>
              <Textarea
                id="reduce-notes"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Add notes about this stock movement..."
                rows={3}
              />
            </div>
          </TabsContent>

          <TabsContent value="adjust" className="space-y-4">
            <div>
              <Label htmlFor="adjust-quantity">New Stock Quantity</Label>
              <Input
                id="adjust-quantity"
                type="number"
                min="0"
                value={quantity}
                onChange={(e) => setQuantity(e.target.value)}
                placeholder="Enter new quantity"
              />
              <p className="text-sm text-muted-foreground mt-1">
                Current: {product.stock_quantity} → New: {quantity || "0"}
              </p>
            </div>

            <div>
              <Label htmlFor="adjust-notes">Reason for Adjustment</Label>
              <Textarea
                id="adjust-notes"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Explain why you're adjusting the stock..."
                rows={3}
              />
            </div>
          </TabsContent>
        </Tabs>

        <DialogFooter>
          <Button variant="outline" onClick={handleClose} disabled={loading}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={loading || !quantity}>
            {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            Update Stock
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
